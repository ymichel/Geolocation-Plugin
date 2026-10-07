<?php
/**
 * OSM
 *
 * Provider-specific code for OpenStreetMap.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Enqueue the scripts and styles for the post editor's meta box using OSM.
 *
 * @param int $post_id The id of the edited post.
 * @return void
 */
function geolocation_admin_enqueue_osm( $post_id ) {
	$data = array_merge(
		geolocation_get_admin_post_data( $post_id ),
		array(
			'tilesUrl'     => geolocation_get_osm_tiles_url(),
			'nominatimUrl' => geolocation_get_osm_nominatim_url(),
			'language'     => geolocation_get_site_lang(),
		)
	);
	if ( geolocation_strict_privacy() ) {
		// The browser of the author does not connect to external servers either: no tiles without the proxy,
		// and addresses are looked up through this site.
		$data['mapBlocked']   = geolocation_maps_blocked();
		$data['tilesUrl']     = $data['mapBlocked'] ? '' : $data['tilesUrl'];
		$data['nominatimUrl'] = '';
		$data['geocodeUrl']   = add_query_arg(
			array(
				'action'   => 'geolocation_geocode',
				'_wpnonce' => wp_create_nonce( 'geolocation_geocode' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}
	wp_enqueue_style( 'osm_leaflet_css', geolocation_get_osm_leaflet_css_url(), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'osm_leaflet_js', geolocation_get_osm_leaflet_js_url(), array(), GEOLOCATION__VERSION, true );
	wp_enqueue_script( 'geolocation_admin_common', plugins_url( 'js/geolocation-admin-common.js', __FILE__ ), array(), GEOLOCATION__VERSION, true );
	wp_add_inline_script( 'geolocation_admin_common', 'var geolocationAdmin = ' . wp_json_encode( $data ) . ';', 'before' );
	wp_enqueue_script( 'geolocation_admin_osm', plugins_url( 'js/geolocation-admin-osm.js', __FILE__ ), array( 'osm_leaflet_js', 'geolocation_admin_common' ), GEOLOCATION__VERSION, true );
}

/**
 * Enqueue the scripts and styles for the frontend maps using OSM.
 *
 * @return void
 */
function geolocation_enqueue_front_osm() {
	if ( wp_script_is( 'geolocation_front_osm', 'enqueued' ) ) {
		return;
	}
	// Leaflet is not enqueued: the script loads it once a map is needed, clustering only for overview maps.
	$data = array_merge(
		geolocation_get_map_settings(),
		array(
			'tilesUrl' => geolocation_get_osm_tiles_url(),
			'library'  => array(
				'styles'         => array( geolocation_versioned_url( geolocation_get_osm_leaflet_css_url(), GEOLOCATION__VERSION ) ),
				'scripts'        => array( geolocation_versioned_url( geolocation_get_osm_leaflet_js_url(), GEOLOCATION__VERSION ) ),
				'clusterStyles'  => array(
					geolocation_versioned_url( plugins_url( 'js/MarkerCluster.css', __FILE__ ), '1.5.3' ),
					geolocation_versioned_url( plugins_url( 'js/MarkerCluster.Default.css', __FILE__ ), '1.5.3' ),
				),
				'clusterScripts' => array( geolocation_versioned_url( plugins_url( 'js/leaflet.markercluster.js', __FILE__ ), '1.5.3' ) ),
			),
		)
	);
	wp_enqueue_style( 'geolocation_css', plugins_url( 'style.css', __FILE__ ), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'geolocation_front_common', plugins_url( 'js/geolocation-front-common.js', __FILE__ ), array(), GEOLOCATION__VERSION, true );
	wp_add_inline_script( 'geolocation_front_common', 'var geolocationFront = ' . wp_json_encode( $data ) . ';', 'before' );
	wp_enqueue_script( 'geolocation_front_osm', plugins_url( 'js/geolocation-front-osm.js', __FILE__ ), array( 'geolocation_front_common' ), GEOLOCATION__VERSION, true );
}

/**
 * Ask Nominatim for the address of a position.
 *
 * @param string $latitude The latitude.
 * @param string $longitude The longitude.
 * @return array The decoded answer, or an empty array if the request failed.
 */
function geolocation_pull_json_osm( $latitude, $longitude ) {
	$url  = geolocation_get_osm_nominatim_url() . '/reverse';
	$args = array(
		'format'          => 'json',
		'accept-language' => geolocation_get_site_lang(),
		'lat'             => rawurlencode( (string) $latitude ),
		'lon'             => rawurlencode( (string) $longitude ),
	);

	// Build query string for GET request.
	$request_url = add_query_arg( $args, $url );

	$result = wp_remote_get(
		$request_url,
		array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => 'WordPress-Geolocation-Plugin/' . GEOLOCATION__VERSION . '; ' . home_url(),
			),
		)
	);

	if ( is_wp_error( $result ) ) {
		return array();
	}

	$body = wp_remote_retrieve_body( $result );
	if ( empty( $body ) ) {
		return array();
	}

	$decoded = json_decode( $body, true );
	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Check whether the tiles and Leaflet shall be delivered by the OSM tiles proxy plugin.
 *
 * @return bool
 */
function geolocation_osm_use_proxy() {
	if ( ! (bool) get_option( 'geolocation_osm_use_proxy' ) ) {
		return false;
	}
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' );
}

/**
 * Ask the OSM tiles proxy plugin for a URL.
 *
 * The proxy answers with an empty value if the requested way of delivery is
 * switched off in its settings, so every result has to be checked.
 *
 * @param string $filter The name of the proxy plugin's filter.
 * @return string The URL or an empty string.
 */
function geolocation_osm_proxy_url( $filter ) {
	$url = apply_filters( $filter, '' );
	return is_string( $url ) ? trim( $url ) : '';
}

/**
 * Get the tiles URL provided by the OSM tiles proxy plugin.
 *
 * The proxy's cached URL is preferred, then its REST URL.
 *
 * @return string The URL, or an empty string if the proxy is not used or does not deliver tiles.
 */
function geolocation_osm_proxy_tiles_url() {
	if ( ! geolocation_osm_use_proxy() ) {
		return '';
	}
	foreach ( array( 'osm_tiles_proxy_get_proxy_url', 'osm_tiles_proxy_get_proxy_rest_url' ) as $filter ) {
		$url = geolocation_osm_proxy_url( $filter );
		if ( '' !== $url ) {
			return $url;
		}
	}
	return '';
}

/**
 * Get the tiles url to be used.
 *
 * The own tiles URL is the fallback if the proxy does not deliver tiles. Whether
 * visitors may get maps with that fallback is decided by geolocation_maps_blocked().
 *
 * @return string
 */
function geolocation_get_osm_tiles_url() {
	$url = geolocation_osm_proxy_tiles_url();
	return '' !== $url ? $url : (string) get_option( 'geolocation_osm_tiles_url' );
}

/**
 * Get the Leaflet JS URL to be used.
 *
 * @return string
 */
function geolocation_get_osm_leaflet_js_url() {
	$url = geolocation_osm_use_proxy() ? geolocation_osm_proxy_url( 'osm_tiles_proxy_get_leaflet_js_url' ) : '';
	return '' !== $url ? $url : plugins_url( 'js/leaflet.js', __FILE__ );
}

/**
 * Get the Leaflet CSS URL to be used.
 *
 * @return string
 */
function geolocation_get_osm_leaflet_css_url() {
	$url = geolocation_osm_use_proxy() ? geolocation_osm_proxy_url( 'osm_tiles_proxy_get_leaflet_css_url' ) : '';
	return '' !== $url ? $url : plugins_url( 'js/leaflet.css', __FILE__ );
}

/**
 * Get the OpenStreetMap Nominatim URL to be used.
 *
 * @return string
 */
function geolocation_get_osm_nominatim_url() {
	$param = (string) get_option( 'geolocation_osm_nominatim_url' );
	return untrailingslashit( $param );
}
