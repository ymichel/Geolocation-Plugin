<?php
/**
 * Google Maps
 *
 * Provider-specific code for Google Maps.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Enqueue the scripts for the post editor's meta box using Google Maps.
 *
 * @param int $post_id The id of the edited post.
 * @return void
 */
function geolocation_admin_enqueue_google( $post_id ) {
	wp_enqueue_script( 'geolocation_admin_common', plugins_url( 'js/geolocation-admin-common.js', __FILE__ ), array(), GEOLOCATION__VERSION, true );
	wp_add_inline_script( 'geolocation_admin_common', 'var geolocationAdmin = ' . wp_json_encode( geolocation_get_admin_post_data( $post_id ) ) . ';', 'before' );
	wp_enqueue_script( 'geolocation_admin_google', plugins_url( 'js/geolocation-admin-google.js', __FILE__ ), array( 'geolocation_admin_common' ), GEOLOCATION__VERSION, true );
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external API without a version.
	wp_enqueue_script( 'google_maps_api', geolocation_get_google_maps_api_url(), array( 'geolocation_admin_google' ), null, true );
}

/**
 * Enqueue the scripts and styles for the frontend maps using Google Maps.
 *
 * @return void
 */
function geolocation_enqueue_front_google() {
	if ( wp_script_is( 'geolocation_front_google', 'enqueued' ) ) {
		return;
	}
	wp_enqueue_style( 'geolocation_css', plugins_url( 'style.css', __FILE__ ), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'geolocation_front_common', plugins_url( 'js/geolocation-front-common.js', __FILE__ ), array(), GEOLOCATION__VERSION, true );
	// The Google Maps API is not enqueued: the script loads it once a map is needed, clustering only for overview maps.
	$data = array_merge(
		geolocation_get_map_settings(),
		array(
			'library' => array(
				'scripts'        => array( geolocation_get_google_maps_api_url() ),
				'callback'       => 'geolocationInitMap',
				'clusterScripts' => array( geolocation_versioned_url( plugins_url( 'js/markerclusterer.min.js', __FILE__ ), '2.6.2' ) ),
			),
		)
	);
	wp_add_inline_script( 'geolocation_front_common', 'var geolocationFront = ' . wp_json_encode( $data ) . ';', 'before' );
	wp_enqueue_script( 'geolocation_front_google', plugins_url( 'js/geolocation-front-google.js', __FILE__ ), array( 'geolocation_front_common' ), GEOLOCATION__VERSION, true );
}

/**
 * Ask the Google Geocoding API for the address of a position.
 *
 * @param string $latitude The latitude.
 * @param string $longitude The longitude.
 * @return array The decoded answer, or an empty array if the request failed.
 */
function geolocation_pull_json_google( $latitude, $longitude ) {
	$args   = array(
		'language' => rawurlencode( geolocation_get_site_lang() ),
		'latlng'   => rawurlencode( $latitude . ',' . $longitude ),
	);
	$apikey = (string) get_option( 'geolocation_google_maps_api_key' );
	if ( '' !== $apikey ) {
		$args['key'] = rawurlencode( $apikey );
	}
	$url      = add_query_arg( $args, 'https://maps.googleapis.com/maps/api/geocode/json' );
	$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return array();
	}
	$body    = wp_remote_retrieve_body( $response );
	$decoded = json_decode( $body, true );
	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Get the URL of the Google Maps JavaScript API including the stored API key.
 *
 * @return string
 */
function geolocation_get_google_maps_api_url() {
	$args   = array(
		'loading'  => 'async',
		'callback' => 'geolocationInitMap',
	);
	$apikey = (string) get_option( 'geolocation_google_maps_api_key' );
	if ( '' !== $apikey ) {
		$args['key'] = rawurlencode( $apikey );
	}
	return add_query_arg( $args, 'https://maps.googleapis.com/maps/api/js' );
}
