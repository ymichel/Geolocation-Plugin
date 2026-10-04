<?php
/**
 * OSM
 *
 * This is the provider specific pool for the provider "open streetmaps (osm)".
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <geolocation@yann-michel.de>
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
	$data = array_merge(
		geolocation_get_map_settings(),
		array( 'tilesUrl' => geolocation_get_osm_tiles_url() )
	);
	wp_enqueue_style( 'geolocation_css', plugins_url( 'style.css', __FILE__ ), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_style( 'osm_leaflet_css', geolocation_get_osm_leaflet_css_url(), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'osm_leaflet_js', geolocation_get_osm_leaflet_js_url(), array(), GEOLOCATION__VERSION, true );
	wp_enqueue_script( 'geolocation_front_common', plugins_url( 'js/geolocation-front-common.js', __FILE__ ), array(), GEOLOCATION__VERSION, true );
	wp_add_inline_script( 'geolocation_front_common', 'var geolocationFront = ' . wp_json_encode( $data ) . ';', 'before' );
	wp_enqueue_script( 'geolocation_front_osm', plugins_url( 'js/geolocation-front-osm.js', __FILE__ ), array( 'osm_leaflet_js', 'geolocation_front_common' ), GEOLOCATION__VERSION, true );
}

/**
 * Pull the JSON for the given geoinformation.
 *
 * @param [type] $latitude The Latitude.
 * @param [type] $longitude The Longitude.
 * @return mixed
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
 * Get the tiles url to be used.
 *
 * @return string
 */
function geolocation_get_osm_tiles_url() {
	if ( geolocation_osm_use_proxy() ) {
		return (string) apply_filters( 'osm_tiles_proxy_get_proxy_url', '' );
	}
	return (string) get_option( 'geolocation_osm_tiles_url' );
}

/**
 * Get the Leaflet JS URL to be used.
 *
 * @return string
 */
function geolocation_get_osm_leaflet_js_url() {
	if ( geolocation_osm_use_proxy() ) {
		return (string) apply_filters( 'osm_tiles_proxy_get_leaflet_js_url', '' );
	}
	return plugins_url( 'js/leaflet.js', __FILE__ );
}

/**
 * Get the Leaflet CSS URL to be used.
 *
 * @return string
 */
function geolocation_get_osm_leaflet_css_url() {
	if ( geolocation_osm_use_proxy() ) {
		return (string) apply_filters( 'osm_tiles_proxy_get_leaflet_css_url', '' );
	}
	return plugins_url( 'js/leaflet.css', __FILE__ );
}

/**
 * Get the OpenStreetmaps Nominatim URL to be used.
 *
 * @return string
 */
function geolocation_get_osm_nominatim_url() {
	$param = (string) get_option( 'geolocation_osm_nominatim_url' );
	return untrailingslashit( $param );
}
