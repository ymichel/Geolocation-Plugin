<?php
/**
 * Pre-caching of map tiles
 *
 * With the OSM tiles proxy plugin the tiles of a map are stored on the own server when they are requested
 * for the first time. The functions in this file request the tiles of the maps of the posts in advance,
 * so the first visitor of a post does not have to wait for them.
 *
 * Only the first view of a map is covered, for the maps of the posts and for the overview maps of shortcodes
 * and blocks: what visitors reach by moving or zooming a map is still fetched by the proxy on demand.
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
/** The width in pixels of a map on a phone, where a wide map is narrower and may use another zoom level. */
define( 'GEOLOCATION__PRECACHE_PHONE_WIDTH', 340 );
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
 * Get the widths a map is shown with: its own width and, if it is wider than a phone, the width on a phone.
 *
 * A map which is fitted to its content may use another zoom level when it is narrower.
 *
 * @param int $width The width of the map in pixels.
 * @return array The widths of the map without its border.
 */
function geolocation_precache_widths( $width ) {
	// The map has a border of one pixel at every edge.
	$widths = array( max( 50, (int) $width - 2 ) );
	if ( $width > GEOLOCATION__PRECACHE_PHONE_WIDTH ) {
		$widths[] = GEOLOCATION__PRECACHE_PHONE_WIDTH - 2;
	}
	return $widths;
}

/**
 * Get the width of the content area of the theme, which a map with a width in percent refers to.
 *
 * @param bool $wide Whether the map is aligned wide or full.
 * @return int The width in pixels.
 */
function geolocation_get_content_width( $wide = false ) {
	$layout = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings( array( 'layout' ) ) : array();
	$key    = $wide ? 'wideSize' : 'contentSize';
	if ( is_array( $layout ) && isset( $layout[ $key ] ) && preg_match( '/^(\d+)px$/', trim( (string) $layout[ $key ] ), $matches ) ) {
		return (int) $matches[1];
	}
	$width = isset( $GLOBALS['content_width'] ) ? (int) $GLOBALS['content_width'] : 0;
	return $width > 0 ? $width : 640;
}

/**
 * Get the tiles the map of a post shows at first, with the size and the zoom level of the settings.
 *
 * @param int $post_id The posts id.
 * @return array The tiles as [ zoom, x, y ]; empty if the post has no location.
 */
function geolocation_get_location_tiles( $post_id ) {
	$latitude  = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_latitude', true ) );
	$longitude = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_longitude', true ) );
	if ( '' === (string) $latitude || '' === (string) $longitude || ! get_post_meta( $post_id, 'geo_enabled', true ) ) {
		return array();
	}
	$widths = geolocation_precache_widths( (int) get_option( 'geolocation_map_width' ) );
	$height = max( 50, (int) get_option( 'geolocation_map_height' ) - 2 );
	$track  = geolocation_get_track( $post_id );
	if ( empty( $track ) ) {
		// A narrower map shows a part of the same tiles.
		$zoom = max( 0, min( GEOLOCATION__MAX_ZOOM, (int) get_option( 'geolocation_default_zoom' ) ) );
		return geolocation_view_tiles( geolocation_tile_position( $latitude, $longitude, $zoom ), $zoom, $widths[0], $height );
	}
	// The map shows the whole track and the location.
	$track[] = array( (float) $latitude, (float) $longitude );
	$tiles   = array();
	foreach ( $widths as $width ) {
		list( $center, $zoom ) = geolocation_fit_view( $track, $width, $height );
		$tiles                 = array_merge( $tiles, geolocation_view_tiles( $center, $zoom, $width, $height ) );
	}
	return $tiles;
}

/**
 * Find the overview maps in the content of a page or post: shortcodes (pages only) and blocks.
 *
 * @param WP_Post $post The page or post.
 * @return array One entry per map with the cleaned attributes ("atts"), whether the custom field "category"
 *               of the page applies ("legacy") and whether the map is aligned wide or full ("wide").
 */
function geolocation_get_overview_maps( $post ) {
	$maps  = array();
	$regex = geolocation_shortcode_regex();
	if ( 'page' === $post->post_type && '' !== $regex && preg_match_all( $regex, $post->post_content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$raw    = isset( $match[1] ) ? str_replace( array( '&#8220;', '&#8221;', '&#8243;', '&quot;', "\u{201C}", "\u{201D}", "\u{2033}" ), '"', $match[1] ) : '';
			$maps[] = array(
				'atts'   => geolocation_sanitize_map_atts( '' === $raw ? array() : shortcode_parse_atts( $raw ) ),
				'legacy' => true,
				'wide'   => false,
			);
		}
	}
	if ( function_exists( 'parse_blocks' ) && false !== strpos( $post->post_content, 'wp:geolocation/map' ) ) {
		$blocks = parse_blocks( $post->post_content );
		while ( $blocks ) {
			$block = array_shift( $blocks );
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks = array_merge( $blocks, $block['innerBlocks'] );
			}
			if ( isset( $block['blockName'] ) && 'geolocation/map' === $block['blockName'] ) {
				$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
				$maps[]     = array(
					'atts'   => geolocation_sanitize_map_atts( geolocation_block_to_map_atts( $attributes ) ),
					'legacy' => false,
					'wide'   => isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ),
				);
			}
		}
	}
	return $maps;
}

/**
 * Collect what an overview map shows to visitors: the locations and, for a route, the tracks.
 *
 * @param array $map The map as returned by geolocation_get_overview_maps().
 * @param int   $page_id The id of the page or post containing the map.
 * @return array The positions the map is fitted to, as [ latitude, longitude ] pairs.
 */
function geolocation_get_overview_points( $map, $page_id ) {
	$atts       = $map['atts'];
	$categories = $atts['cat'];
	$legacy     = false;
	if ( '' === $categories && $map['legacy'] ) {
		$categories = (string) get_post_meta( $page_id, 'category', true );
		$legacy     = true;
	}
	$category_ids = geolocation_get_term_ids( $categories, 'category' );
	$tag_ids      = geolocation_get_term_ids( $atts['tag'], 'post_tag' );
	if ( ( ! $legacy && '' !== $categories && empty( $category_ids ) ) || ( '' !== $atts['tag'] && empty( $tag_ids ) ) ) {
		return array();
	}
	$points     = array();
	$with_track = array();
	foreach ( get_posts( geolocation_page_query_args( $category_ids, $tag_ids ) ) as $geo_post ) {
		$latitude  = geolocation_clean_coordinate( get_post_meta( $geo_post->ID, 'geo_latitude', true ) );
		$longitude = geolocation_clean_coordinate( get_post_meta( $geo_post->ID, 'geo_longitude', true ) );
		// Visitors only see locations which are enabled and public.
		if ( empty( $latitude ) || empty( $longitude ) || ! get_post_meta( $geo_post->ID, 'geo_enabled', true ) || ! get_post_meta( $geo_post->ID, 'geo_public', true ) ) {
			continue;
		}
		$points[] = array( (float) $latitude, (float) $longitude );
		if ( '' !== $atts['route'] && '' !== (string) get_post_meta( $geo_post->ID, 'geo_track_km', true ) ) {
			$with_track[] = $geo_post->ID;
		}
	}
	// The map is fitted to the tracks as well, which are delivered with the same number of positions as on the website.
	$positions = empty( $with_track ) ? 0 : max( 50, min( GEOLOCATION__TRACK_POINTS, intdiv( GEOLOCATION__TRACK_POINTS_PAGE, count( $with_track ) ) ) );
	$locations = count( $points );
	foreach ( $with_track as $track_post_id ) {
		$points = array_merge( $points, geolocation_get_track( $track_post_id, $positions ) );
	}
	return array( $points, $locations );
}

/**
 * Get the tiles the overview maps of a page or post show at first.
 *
 * @param WP_Post $post The page or post.
 * @return array The tiles as [ zoom, x, y ].
 */
function geolocation_get_overview_tiles( $post ) {
	$tiles = array();
	foreach ( geolocation_get_overview_maps( $post ) as $map ) {
		$result = geolocation_get_overview_points( $map, $post->ID );
		if ( empty( $result ) || empty( $result[0] ) ) {
			continue;
		}
		list( $points, $locations ) = $result;
		$atts                       = $map['atts'];
		if ( preg_match( '/^(\d+)%$/', $atts['width'], $matches ) ) {
			$width = (int) round( geolocation_get_content_width( $map['wide'] ) * $matches[1] / 100 );
		} else {
			$width = '' !== $atts['width'] ? (int) $atts['width'] : (int) get_option( 'geolocation_map_width_page' );
		}
		$height = max( 50, ( '' !== $atts['height'] ? (int) $atts['height'] : (int) get_option( 'geolocation_map_height_page' ) ) - 2 );
		foreach ( geolocation_precache_widths( $width ) as $inner ) {
			if ( '' !== $atts['zoom'] || 1 === count( $points ) ) {
				// A fixed zoom level, or a single location: the map is centred on the middle of all positions.
				$zoom       = '' !== $atts['zoom'] ? (int) $atts['zoom'] : max( 0, min( GEOLOCATION__MAX_ZOOM, (int) get_option( 'geolocation_default_zoom' ) ) );
				$latitudes  = array_column( $points, 0 );
				$longitudes = array_column( $points, 1 );
				$center     = geolocation_tile_position( ( min( $latitudes ) + max( $latitudes ) ) / 2, ( min( $longitudes ) + max( $longitudes ) ) / 2, $zoom );
			} else {
				list( $center, $zoom ) = geolocation_fit_view( $points, $inner, $height, 30 );
			}
			$tiles = array_merge( $tiles, geolocation_view_tiles( $center, $zoom, $inner, $height ) );
		}
	}
	return $tiles;
}

/**
 * Get the tiles the maps of a page or post show at first: the map of its location and its overview maps.
 *
 * @param int $post_id The id of the page or post.
 * @return array The tiles as [ zoom, x, y ].
 */
function geolocation_get_post_tiles( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}
	$tiles = 'post' === $post->post_type ? geolocation_get_location_tiles( $post->ID ) : array();
	return array_merge( $tiles, geolocation_get_overview_tiles( $post ) );
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
 * Get the tiles URL of the proxy plugin if it stores the tiles as files of this site.
 *
 * This does not depend on the settings of this plugin: it tells whether pre-caching would be possible.
 *
 * @return string The tiles URL, or an empty string.
 */
function geolocation_precache_proxy_url() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' ) ) {
		return '';
	}
	$url = apply_filters( 'osm_tiles_proxy_get_proxy_url', '' );
	$url = is_string( $url ) ? trim( $url ) : '';
	return '' !== $url && '' !== geolocation_tile_file( geolocation_tile_url( $url, array( 0, 0, 0 ) ) ) ? $url : '';
}

/**
 * Get the tiles URL to be pre-cached.
 *
 * Pre-caching needs OpenStreetMap with the proxy plugin in use, storing the tiles as files of this site.
 *
 * @return string The tiles URL, or an empty string if pre-caching is not possible.
 */
function geolocation_precache_tiles_url() {
	if ( 'osm' !== get_option( 'geolocation_provider' ) || ! get_option( 'geolocation_osm_use_proxy' ) ) {
		return '';
	}
	return geolocation_precache_proxy_url();
}

/**
 * Get the published pages and posts containing an overview map: a block, or on pages the shortcode.
 *
 * @return array The ids, pages first.
 */
function geolocation_precache_map_ids() {
	global $wpdb;
	$shortcode = trim( (string) get_option( 'geolocation_shortcode' ) );
	$shortcode = '[' === substr( $shortcode, 0, 1 ) ? rtrim( $shortcode, ']' ) : $shortcode;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- there is no API to search the raw content; only run from the settings page and in the background.
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND ( ( post_type IN ( 'page', 'post' ) AND post_content LIKE %s ) OR ( post_type = 'page' AND post_content LIKE %s ) ) ORDER BY post_type ASC, post_date DESC",
			'%' . $wpdb->esc_like( 'wp:geolocation/map' ) . '%',
			'%' . $wpdb->esc_like( '' !== $shortcode ? $shortcode : 'wp:geolocation/map' ) . '%'
		)
	);
	return array_map( 'intval', $ids );
}

/**
 * Get the pages and posts whose maps are pre-cached.
 *
 * Pages and posts with an overview map come first, as these maps need the most tiles;
 * then the published posts with an enabled location, the newest first.
 *
 * @return array The ids of the pages and posts.
 */
function geolocation_precache_post_ids() {
	$posts = get_posts(
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
	return array_values( array_unique( array_merge( geolocation_precache_map_ids(), array_map( 'intval', $posts ) ) ) );
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
 * Pre-cache the tiles of a page or post after it has been saved, unless this is switched off.
 *
 * @param int $post_id The id of the page or post.
 * @return void
 */
function geolocation_precache_saved_post( $post_id ) {
	if ( ! geolocation_precache_on_save() || wp_is_post_revision( $post_id ) || 'publish' !== get_post_status( $post_id ) || '' === geolocation_precache_tiles_url() ) {
		return;
	}
	// In the background, as the location is stored by another function running on this hook.
	if ( ! wp_next_scheduled( 'geolocation_precache_post', array( (int) $post_id ) ) ) {
		wp_schedule_single_event( time() + 5, 'geolocation_precache_post', array( (int) $post_id ) );
	}
}

/**
 * Pre-cache the tiles of one page or post, and of the overview maps: a new location may change what they show.
 *
 * @param int $post_id The id of the page or post.
 * @return void
 */
function geolocation_precache_post( $post_id ) {
	$template = geolocation_precache_tiles_url();
	if ( '' === $template ) {
		return;
	}
	$ids                 = array_unique( array_merge( array( (int) $post_id ), geolocation_precache_map_ids() ) );
	list( , , $missing ) = geolocation_precache_missing( $ids, $template );
	geolocation_precache_fetch( array_slice( $missing, 0, 2 * GEOLOCATION__PRECACHE_POST_LIMIT ) );
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
			/* translators: 1: number of the page or post being processed, 2: number of pages and posts, 3: its title. */
			$html .= '<p>' . esc_html( sprintf( __( 'Step %1$d of %2$d: %3$s', 'geolocation' ), $status['done'], $status['posts'], html_entity_decode( get_the_title( $status['last'] ), ENT_QUOTES, 'UTF-8' ) ) ) . '</p>';
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
	/* translators: 1: number of stored tiles, 2: number of tiles needed, 3: number of pages and posts. */
	$html .= '<p>' . esc_html( sprintf( __( '%1$d of %2$d tiles for the maps of %3$d pages and posts are stored on your server.', 'geolocation' ), $tiles - count( $missing ), $tiles, $posts ) ) . '</p>';
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
