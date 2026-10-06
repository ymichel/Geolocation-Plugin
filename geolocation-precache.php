<?php
/**
 * Pre-caching of map tiles
 *
 * With the OSM tiles proxy plugin the tiles of a map are stored on the own server when they are requested
 * for the first time. The functions in this file request the tiles of the maps of the posts in advance,
 * so the first visitor of a post does not have to wait for them.
 *
 * Only the first view of a post's map is covered: what visitors reach by moving or zooming the map
 * is still fetched by the proxy on demand.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/** The number of tiles requested by one batch. */
define( 'GEOLOCATION__PRECACHE_BATCH', 20 );
/** The seconds between two batches, to respect the usage policy of the tile servers. */
define( 'GEOLOCATION__PRECACHE_PAUSE', 20 );
/** The number of tiles one run requests at most. */
define( 'GEOLOCATION__PRECACHE_LIMIT', 5000 );
/** The highest zoom level the maps of this plugin use. */
define( 'GEOLOCATION__MAX_ZOOM', 18 );

/**
 * Get the position of a location in the grid of tiles of a zoom level (Web Mercator).
 *
 * @param float $latitude The latitude.
 * @param float $longitude The longitude.
 * @param int   $zoom The zoom level.
 * @return array The position as [ x, y ] in tiles, with fractions.
 */
function geolocation_tile_position( $latitude, $longitude, $zoom ) {
	$tiles    = pow( 2, (int) $zoom );
	$latitude = max( -85.0511287798, min( 85.0511287798, (float) $latitude ) );
	$sinus    = sin( deg2rad( $latitude ) );
	return array(
		( (float) $longitude + 180 ) / 360 * $tiles,
		( 0.5 - log( ( 1 + $sinus ) / ( 1 - $sinus ) ) / ( 4 * M_PI ) ) * $tiles,
	);
}

/**
 * Get the tiles a map shows for a view, the way Leaflet selects them.
 *
 * @param array $center The centre of the view as [ x, y ] in tiles, see geolocation_tile_position().
 * @param int   $zoom The zoom level.
 * @param int   $width The width of the map in pixels.
 * @param int   $height The height of the map in pixels.
 * @return array The tiles as [ zoom, x, y ].
 */
function geolocation_view_tiles( $center, $zoom, $width, $height ) {
	$zoom  = (int) $zoom;
	$count = (int) pow( 2, $zoom );
	$left  = (int) round( $center[0] * 256 - $width / 2 );
	$top   = (int) round( $center[1] * 256 - $height / 2 );
	$tiles = array();
	$x_min = (int) floor( $left / 256 );
	$x_max = (int) ceil( ( $left + $width ) / 256 ) - 1;
	$y_min = max( 0, (int) floor( $top / 256 ) );
	$y_max = min( $count - 1, (int) ceil( ( $top + $height ) / 256 ) - 1 );
	for ( $y = $y_min; $y <= $y_max; $y++ ) {
		for ( $x = $x_min; $x <= $x_max; $x++ ) {
			// The map repeats beyond the date line.
			$tiles[] = array( $zoom, ( ( $x % $count ) + $count ) % $count, $y );
		}
	}
	return $tiles;
}

/**
 * Find the view a map uses to show all given positions, the way Leaflet fits a map to bounds.
 *
 * @param array $points The positions as [ latitude, longitude ] pairs.
 * @param int   $width The width of the map in pixels.
 * @param int   $height The height of the map in pixels.
 * @param int   $padding The free space kept at every edge in pixels.
 * @return array The centre as [ x, y ] in tiles and the zoom level.
 */
function geolocation_fit_view( $points, $width, $height, $padding = 20 ) {
	$latitudes  = array();
	$longitudes = array();
	foreach ( $points as $point ) {
		$latitudes[]  = (float) $point[0];
		$longitudes[] = (float) $point[1];
	}
	for ( $zoom = GEOLOCATION__MAX_ZOOM; $zoom >= 0; $zoom-- ) {
		$north_west = geolocation_tile_position( max( $latitudes ), min( $longitudes ), $zoom );
		$south_east = geolocation_tile_position( min( $latitudes ), max( $longitudes ), $zoom );
		$fits       = ( $south_east[0] - $north_west[0] ) * 256 <= $width - 2 * $padding && ( $south_east[1] - $north_west[1] ) * 256 <= $height - 2 * $padding;
		if ( $fits || 0 === $zoom ) {
			break;
		}
	}
	return array(
		array( ( $north_west[0] + $south_east[0] ) / 2, ( $north_west[1] + $south_east[1] ) / 2 ),
		$zoom,
	);
}

/**
 * Get the tiles the map of a post shows at first, with the size and the zoom level of the settings.
 *
 * @param int $post_id The posts id.
 * @return array The tiles as [ zoom, x, y ]; empty if the post has no location.
 */
function geolocation_get_post_tiles( $post_id ) {
	$latitude  = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_latitude', true ) );
	$longitude = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_longitude', true ) );
	if ( '' === (string) $latitude || '' === (string) $longitude ) {
		return array();
	}
	// The map has a border of one pixel at every edge.
	$width  = max( 50, (int) get_option( 'geolocation_map_width' ) - 2 );
	$height = max( 50, (int) get_option( 'geolocation_map_height' ) - 2 );
	$track  = geolocation_get_track( $post_id );
	if ( empty( $track ) ) {
		$zoom = max( 0, min( GEOLOCATION__MAX_ZOOM, (int) get_option( 'geolocation_default_zoom' ) ) );
		return geolocation_view_tiles( geolocation_tile_position( $latitude, $longitude, $zoom ), $zoom, $width, $height );
	}
	// The map shows the whole track and the location.
	$track[]               = array( (float) $latitude, (float) $longitude );
	list( $center, $zoom ) = geolocation_fit_view( $track, $width, $height );
	return geolocation_view_tiles( $center, $zoom, $width, $height );
}

/**
 * Build the URL of a tile from the tiles URL, the way Leaflet does.
 *
 * @param string $template The tiles URL with the placeholders {s}, {z}, {x} and {y}.
 * @param array  $tile The tile as [ zoom, x, y ].
 * @return string
 */
function geolocation_tile_url( $template, $tile ) {
	$subdomains = array( 'a', 'b', 'c' );
	return str_replace(
		array( '{s}', '{z}', '{x}', '{y}' ),
		array( $subdomains[ abs( $tile[1] + $tile[2] ) % 3 ], $tile[0], $tile[1], $tile[2] ),
		$template
	);
}

/**
 * Get the file the proxy stores a tile in, derived from the URL of the tile.
 *
 * @param string $url The URL of the tile.
 * @return string The path, or an empty string if the URL does not point into the content folder of this site.
 */
function geolocation_tile_file( $url ) {
	$base = trailingslashit( content_url() );
	if ( 0 !== strpos( $url, $base ) || false !== strpos( $url, '..' ) ) {
		return '';
	}
	return trailingslashit( WP_CONTENT_DIR ) . substr( $url, strlen( $base ) );
}

/**
 * Get the tiles URL to be pre-cached.
 *
 * Pre-caching needs the proxy plugin storing the tiles as files of this site.
 *
 * @return string The tiles URL, or an empty string if pre-caching is not possible.
 */
function geolocation_precache_tiles_url() {
	if ( 'osm' !== get_option( 'geolocation_provider' ) || ! geolocation_osm_use_proxy() ) {
		return '';
	}
	$url = geolocation_osm_proxy_url( 'osm_tiles_proxy_get_proxy_url' );
	return '' !== $url && '' !== geolocation_tile_file( geolocation_tile_url( $url, array( 0, 0, 0 ) ) ) ? $url : '';
}

/**
 * Get the posts whose maps are pre-cached: published posts with an enabled location.
 *
 * @return array The ids of the posts.
 */
function geolocation_precache_post_ids() {
	return get_posts(
		array(
			'post_type'      => 'post',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only run from the settings page and in the background.
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'geo_latitude',
					'compare' => 'EXISTS',
				),
				array(
					'key'   => 'geo_enabled',
					'value' => '1',
				),
			),
		)
	);
}

/**
 * Get the URLs of the tiles of the given posts which are not stored yet.
 *
 * @param array  $post_ids The ids of the posts.
 * @param string $template The tiles URL.
 * @param int    $limit Stop after this number of missing tiles, 0 for no limit.
 * @return array The number of posts looked at, the number of their tiles and the URLs of the missing ones.
 */
function geolocation_precache_missing( $post_ids, $template, $limit = 0 ) {
	$posts   = 0;
	$seen    = array();
	$missing = array();
	foreach ( $post_ids as $post_id ) {
		++$posts;
		foreach ( geolocation_get_post_tiles( $post_id ) as $tile ) {
			$url = geolocation_tile_url( $template, $tile );
			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			if ( ! file_exists( geolocation_tile_file( $url ) ) ) {
				$missing[] = $url;
			}
		}
		if ( $limit > 0 && count( $missing ) >= $limit ) {
			break;
		}
	}
	return array( $posts, count( $seen ), $missing );
}

/**
 * Request tiles through the proxy, which stores them.
 *
 * @param array $urls The URLs of the tiles.
 * @return int The number of tiles stored afterwards.
 */
function geolocation_precache_fetch( $urls ) {
	$stored = 0;
	foreach ( $urls as $url ) {
		wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				// The site requests itself, a certificate it cannot check must not stop that.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
		clearstatcache();
		if ( file_exists( geolocation_tile_file( $url ) ) ) {
			++$stored;
		}
	}
	return $stored;
}

/**
 * Start pre-caching the tiles of all posts in the background.
 *
 * @return bool Whether the run has been started.
 */
function geolocation_precache_start() {
	if ( '' === geolocation_precache_tiles_url() ) {
		return false;
	}
	wp_unschedule_hook( 'geolocation_precache_batch' );
	update_option(
		'geolocation_precache_status',
		array(
			'running'   => true,
			'requested' => 0,
			'stored'    => 0,
			'time'      => time(),
		),
		false
	);
	wp_schedule_single_event( time(), 'geolocation_precache_batch', array( 0 ) );
	return true;
}

/**
 * Pre-cache the tiles of one batch of posts and schedule the next batch.
 *
 * @param int $offset The number of posts already processed.
 * @return void
 */
function geolocation_precache_batch( $offset = 0 ) {
	$offset   = (int) $offset;
	$template = geolocation_precache_tiles_url();
	$status   = get_option( 'geolocation_precache_status' );
	$status   = is_array( $status ) ? $status : array();
	$status   = array_merge(
		array(
			'requested' => 0,
			'stored'    => 0,
		),
		$status
	);
	$post_ids = '' === $template ? array() : array_slice( geolocation_precache_post_ids(), $offset );

	list( $posts, , $missing ) = geolocation_precache_missing( $post_ids, (string) $template, GEOLOCATION__PRECACHE_BATCH );
	$missing                   = array_slice( $missing, 0, max( 0, GEOLOCATION__PRECACHE_LIMIT - $status['requested'] ) );
	$status['requested']      += count( $missing );
	$status['stored']         += geolocation_precache_fetch( $missing );
	$status['time']            = time();
	$status['running']         = $posts < count( $post_ids ) && $status['requested'] < GEOLOCATION__PRECACHE_LIMIT;
	update_option( 'geolocation_precache_status', $status, false );

	if ( $status['running'] ) {
		wp_schedule_single_event( time() + ( empty( $missing ) ? 1 : GEOLOCATION__PRECACHE_PAUSE ), 'geolocation_precache_batch', array( $offset + $posts ) );
	}
}

/**
 * Check whether the tiles of a post are pre-cached when it is saved.
 *
 * This is the case unless it has been switched off in the settings.
 *
 * @return bool
 */
function geolocation_precache_on_save() {
	return '0' !== (string) get_option( 'geolocation_osm_precache_on_save' );
}

/**
 * Pre-cache the tiles of a post after it has been saved, unless this is switched off.
 *
 * @param int $post_id The posts id.
 * @return void
 */
function geolocation_precache_saved_post( $post_id ) {
	if ( ! geolocation_precache_on_save() || wp_is_post_revision( $post_id ) || 'publish' !== get_post_status( $post_id ) || '' === geolocation_precache_tiles_url() ) {
		return;
	}
	// In the background, as the location is stored by another function running on this hook.
	wp_schedule_single_event( time() + 5, 'geolocation_precache_post', array( (int) $post_id ) );
}

/**
 * Pre-cache the tiles of one post.
 *
 * @param int $post_id The posts id.
 * @return void
 */
function geolocation_precache_post( $post_id ) {
	$template = geolocation_precache_tiles_url();
	if ( '' === $template ) {
		return;
	}
	list( , , $missing ) = geolocation_precache_missing( array( (int) $post_id ), $template );
	geolocation_precache_fetch( array_slice( $missing, 0, 2 * GEOLOCATION__PRECACHE_BATCH ) );
}

/**
 * Handle the button of the settings page which starts pre-caching.
 *
 * @return void
 */
function geolocation_precache_request() {
	check_admin_referer( 'geolocation_precache' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	$started = geolocation_precache_start();
	wp_safe_redirect( add_query_arg( 'geolocation-precache', $started ? 'started' : 'failed', admin_url( 'options-general.php?page=geolocation.php' ) ) );
	exit;
}
