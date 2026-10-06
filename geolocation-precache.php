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
 * A run takes one post after the other, like a visitor opening one map after the other, with a short
 * pause in between. This keeps the load on the tile servers small and even.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/** The shortest and the longest pause in seconds after the tiles of a post have been requested. */
define( 'GEOLOCATION__PRECACHE_PAUSE_MIN', 4 );
define( 'GEOLOCATION__PRECACHE_PAUSE_MAX', 8 );
/** The number of tiles requested for one post at most. */
define( 'GEOLOCATION__PRECACHE_POST_LIMIT', 60 );
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
 * Get the posts whose maps are pre-cached: published posts with an enabled location, the newest first.
 *
 * @return array The ids of the posts.
 */
function geolocation_precache_post_ids() {
	return get_posts(
		array(
			'post_type'      => 'post',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			// The newest posts first: they are visited most, and a run which is stopped has covered them already.
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
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
 * Check whether the answer to the request for a tile is that tile.
 *
 * The proxy answers with another image if it does not deliver a tile, e.g. outside the area it allows.
 *
 * @param array|WP_Error $response The answer of wp_remote_get().
 * @param string         $url The URL of the tile.
 * @return bool
 */
function geolocation_precache_is_tile( $response, $url ) {
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) || 0 !== strpos( (string) wp_remote_retrieve_header( $response, 'content-type' ), 'image/' ) ) {
		return false;
	}
	$final = '';
	if ( isset( $response['http_response'] ) && is_object( $response['http_response'] ) && method_exists( $response['http_response'], 'get_response_object' ) ) {
		$object = $response['http_response']->get_response_object();
		$final  = isset( $object->url ) ? (string) $object->url : '';
	}
	// After redirects the address still has to be the one of the tile.
	return '' === $final || strtok( $final, '?' ) === $url;
}

/**
 * Request tiles through the proxy, which stores them.
 *
 * @param array $urls The URLs of the tiles.
 * @return int The number of tiles delivered or stored.
 */
function geolocation_precache_fetch( $urls ) {
	$stored = 0;
	foreach ( $urls as $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 20,
				'redirection' => 3,
				// The site requests itself, a certificate it cannot check must not stop that.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
		clearstatcache();
		if ( geolocation_precache_is_tile( $response, $url ) || file_exists( geolocation_tile_file( $url ) ) ) {
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
	$template = geolocation_precache_tiles_url();
	if ( '' === $template ) {
		return false;
	}
	$post_ids                  = geolocation_precache_post_ids();
	list( , , $missing_tiles ) = geolocation_precache_missing( $post_ids, $template );
	wp_unschedule_hook( 'geolocation_precache_batch' );
	update_option(
		'geolocation_precache_status',
		array(
			'running'   => true,
			'cancelled' => false,
			'requested' => 0,
			'stored'    => 0,
			// What the run has to do, to show its progress.
			'missing'   => count( $missing_tiles ),
			'steps'     => 0,
			'posts'     => count( $post_ids ),
			'done'      => 0,
			'last'      => 0,
			'started'   => time(),
			'time'      => time(),
		),
		false
	);
	wp_schedule_single_event( time(), 'geolocation_precache_batch', array( 0 ) );
	return true;
}

/**
 * Get the state of the current or the last run.
 *
 * @return array Empty if there has been no run yet.
 */
function geolocation_precache_get_status() {
	$status = get_option( 'geolocation_precache_status' );
	if ( ! is_array( $status ) ) {
		return array();
	}
	return array_merge(
		array(
			'running'   => false,
			'cancelled' => false,
			'requested' => 0,
			'stored'    => 0,
			'missing'   => 0,
			'steps'     => 0,
			'posts'     => 0,
			'done'      => 0,
			'last'      => 0,
			'started'   => 0,
			'time'      => 0,
		),
		$status
	);
}

/**
 * Check whether a run is in progress.
 *
 * @return bool
 */
function geolocation_precache_is_running() {
	$status = geolocation_precache_get_status();
	return ! empty( $status['running'] );
}

/**
 * Stop the run in progress.
 *
 * @return void
 */
function geolocation_precache_cancel() {
	wp_unschedule_hook( 'geolocation_precache_batch' );
	$status = geolocation_precache_get_status();
	if ( ! empty( $status['running'] ) ) {
		$status['running']   = false;
		$status['cancelled'] = true;
		$status['time']      = time();
		update_option( 'geolocation_precache_status', $status, false );
	}
}

/**
 * Pre-cache the tiles of the next post which misses some, and schedule the following step.
 *
 * Posts whose tiles are stored already are passed without a pause.
 *
 * @param int $offset The number of posts already processed.
 * @return void
 */
function geolocation_precache_batch( $offset = 0 ) {
	$offset   = (int) $offset;
	$template = geolocation_precache_tiles_url();
	$status   = geolocation_precache_get_status();
	if ( empty( $status['running'] ) ) {
		// The run has been cancelled.
		return;
	}
	$all_ids  = '' === $template ? array() : geolocation_precache_post_ids();
	$post_ids = array_slice( $all_ids, $offset );

	// Stops at the first post missing tiles.
	list( $posts, , $missing ) = geolocation_precache_missing( $post_ids, (string) $template, 1 );
	$missing                   = array_slice( $missing, 0, min( GEOLOCATION__PRECACHE_POST_LIMIT, max( 0, GEOLOCATION__PRECACHE_LIMIT - $status['requested'] ) ) );
	$stored                    = geolocation_precache_fetch( $missing );

	// Read the state again: the run may have been cancelled while the tiles were requested.
	$status = geolocation_precache_get_status();
	if ( empty( $status['running'] ) ) {
		return;
	}
	$status['requested'] += count( $missing );
	$status['stored']    += $stored;
	$status['steps']     += empty( $missing ) ? 0 : 1;
	$status['posts']      = count( $all_ids );
	$status['done']       = min( count( $all_ids ), $offset + $posts );
	$status['last']       = $posts > 0 ? (int) $post_ids[ $posts - 1 ] : $status['last'];
	$status['time']       = time();
	$status['running']    = $posts < count( $post_ids ) && $status['requested'] < GEOLOCATION__PRECACHE_LIMIT;
	update_option( 'geolocation_precache_status', $status, false );

	if ( $status['running'] ) {
		// The pause varies, so the requests do not arrive in a fixed rhythm.
		wp_schedule_single_event( time() + wp_rand( GEOLOCATION__PRECACHE_PAUSE_MIN, GEOLOCATION__PRECACHE_PAUSE_MAX ), 'geolocation_precache_batch', array( $offset + $posts ) );
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
	geolocation_precache_fetch( array_slice( $missing, 0, GEOLOCATION__PRECACHE_POST_LIMIT ) );
}

/**
 * Handle the buttons of the settings page which start and cancel pre-caching.
 *
 * @return void
 */
function geolocation_precache_request() {
	check_admin_referer( 'geolocation_precache' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	if ( isset( $_GET['cancel'] ) ) {
		geolocation_precache_cancel();
		$result = 'cancelled';
	} else {
		$result = geolocation_precache_start() ? 'started' : 'failed';
	}
	wp_safe_redirect( add_query_arg( 'geolocation-precache', $result, admin_url( 'options-general.php?page=geolocation.php' ) ) );
	exit;
}

/**
 * Answer the settings page asking for the progress of pre-caching.
 *
 * @return void
 */
function geolocation_precache_status_request() {
	check_ajax_referer( 'geolocation_precache' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	wp_send_json_success(
		array(
			'running' => geolocation_precache_is_running(),
			'html'    => geolocation_precache_status_html(),
		)
	);
}

/**
 * Build the status of pre-caching for the settings page: the progress of a run, or the stored tiles and the last run.
 *
 * @return string The escaped HTML.
 */
function geolocation_precache_status_html() {
	$status = geolocation_precache_get_status();
	$action = wp_nonce_url( admin_url( 'admin-post.php?action=geolocation_precache' ), 'geolocation_precache' );
	$failed = empty( $status ) ? 0 : max( 0, $status['requested'] - $status['stored'] );
	$html   = '';

	if ( ! empty( $status['running'] ) ) {
		$remaining = max( 0, $status['missing'] - $status['requested'] );
		// Estimated from the tiles a post needed so far, the pause and a few seconds for requesting them.
		$per_post = $status['steps'] > 0 ? max( 1, $status['requested'] / $status['steps'] ) : 5;
		$seconds  = (int) round( ceil( $remaining / $per_post ) * ( ( GEOLOCATION__PRECACHE_PAUSE_MIN + GEOLOCATION__PRECACHE_PAUSE_MAX ) / 2 + 4 ) );
		$html    .= '<p><strong>' . esc_html__( 'Pre-caching is running in the background.', 'geolocation' ) . '</strong></p>';
		$html    .= '<p><progress id="geolocation-precache-progress" style="width:100%;max-width:400px;" max="' . esc_attr( (string) max( 1, $status['missing'] ) ) . '" value="' . esc_attr( (string) min( $status['requested'], max( 1, $status['missing'] ) ) ) . '"></progress></p>';
		if ( $status['done'] > 0 ) {
			/* translators: 1: number of the post, 2: number of posts, 3: title of the post. */
			$html .= '<p>' . esc_html( sprintf( __( 'Post %1$d of %2$d: %3$s', 'geolocation' ), $status['done'], $status['posts'], html_entity_decode( get_the_title( $status['last'] ), ENT_QUOTES, 'UTF-8' ) ) ) . '</p>';
		}
		/* translators: 1: number of requested tiles, 2: number of stored tiles, 3: number of tiles which could not be stored. */
		$html .= '<p>' . esc_html( sprintf( __( 'Tiles: %1$d requested, %2$d stored, %3$d failed.', 'geolocation' ), $status['requested'], $status['stored'], $failed ) ) . '</p>';
		/* translators: 1: time of day, 2: a duration like "2 mins". */
		$html .= '<p>' . esc_html( sprintf( __( 'Started at %1$s, about %2$s remaining.', 'geolocation' ), wp_date( get_option( 'time_format' ), $status['started'] ), human_time_diff( time(), time() + max( 1, $seconds ) ) ) ) . '</p>';
		if ( time() - $status['time'] > 3 * MINUTE_IN_SECONDS ) {
			$html .= '<p>' . esc_html__( 'Nothing has happened for a few minutes. The background tasks of WordPress (WP-Cron) may not be running on this site.', 'geolocation' ) . '</p>';
		}
		$html .= '<p><a class="button" id="geolocation-precache-cancel" href="' . esc_url( add_query_arg( 'cancel', '1', $action ) ) . '">' . esc_html__( 'Cancel', 'geolocation' ) . '</a></p>';
		return $html;
	}

	list( $posts, $tiles, $missing ) = geolocation_precache_missing( geolocation_precache_post_ids(), geolocation_precache_tiles_url() );
	/* translators: 1: number of stored tiles, 2: number of tiles needed, 3: number of posts. */
	$html .= '<p>' . esc_html( sprintf( __( '%1$d of %2$d tiles for the maps of %3$d posts are stored on your server.', 'geolocation' ), $tiles - count( $missing ), $tiles, $posts ) ) . '</p>';
	if ( ! empty( $status ) ) {
		/* translators: 1: date and time, 2: number of requested tiles, 3: number of stored tiles, 4: number of tiles which could not be stored. */
		$html .= '<p>' . esc_html( sprintf( __( 'Last run (%1$s): %2$d tiles requested, %3$d stored, %4$d failed.', 'geolocation' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['time'] ), $status['requested'], $status['stored'], $failed ) );
		$html .= $status['cancelled'] ? ' ' . esc_html__( 'The run was cancelled.', 'geolocation' ) : '';
		$html .= '</p>';
		if ( $failed > 0 ) {
			$html .= '<p>' . esc_html__( 'Some tiles could not be stored. Either your server cannot request its own address, or the tiles lie outside the area allowed in the settings of the proxy plugin.', 'geolocation' ) . '</p>';
		}
	}
	$html .= '<p><a class="button" id="geolocation-precache" href="' . esc_url( $action ) . '">' . esc_html__( 'Pre-cache the missing tiles', 'geolocation' ) . '</a></p>';
	return $html;
}
