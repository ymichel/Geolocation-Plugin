<?php
/**
 * Link to an external map
 *
 * Optionally the location of a post gets a link which opens it in the map service of the selected provider.
 * Nothing is requested from that service until a visitor follows the link; in strict privacy mode, or if the
 * site owner wants it, a notice is shown before the visitor leaves the website.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Get the address which opens a location in the map service of a provider.
 *
 * @param string $provider The provider, "osm" or "google".
 * @param mixed  $latitude The latitude.
 * @param mixed  $longitude The longitude.
 * @param int    $zoom The zoom level, used by OpenStreetMap.
 * @return string
 */
function geolocation_get_external_map_url( $provider, $latitude, $longitude, $zoom = 16 ) {
	// Seven decimals are more precise than a map can show.
	$latitude  = rtrim( rtrim( number_format( (float) $latitude, 7, '.', '' ), '0' ), '.' );
	$longitude = rtrim( rtrim( number_format( (float) $longitude, 7, '.', '' ), '0' ), '.' );
	if ( 'google' === $provider ) {
		return 'https://www.google.com/maps/search/?api=1&query=' . $latitude . '%2C' . $longitude;
	}
	$zoom = max( 1, min( 19, (int) $zoom ) );
	return 'https://www.openstreetmap.org/?mlat=' . $latitude . '&mlon=' . $longitude . '#map=' . $zoom . '/' . $latitude . '/' . $longitude;
}

/**
 * Check whether visitors get a notice before the link takes them to the map service.
 *
 * The strict privacy mode always shows the notice.
 *
 * @return bool
 */
function geolocation_map_link_notice() {
	return geolocation_strict_privacy() || (bool) get_option( 'geolocation_map_link_notice' );
}

/**
 * Check whether the link to the external map is shown.
 *
 * @param string $choice "show" or "hide" to overrule the setting, e.g. by a block; anything else for the setting.
 * @return bool
 */
function geolocation_map_link_shows( $choice = '' ) {
	if ( in_array( $choice, array( 'show', 'hide' ), true ) ) {
		return 'show' === $choice;
	}
	return (bool) get_option( 'geolocation_map_link' );
}

/**
 * Get the pin shown in front of the link: the one the maps of this plugin use for the locations.
 *
 * @param string $provider The provider, "osm" or "google".
 * @return string The escaped HTML.
 */
function geolocation_get_map_link_icon( $provider ) {
	if ( get_option( 'geolocation_wp_pin' ) ) {
		return '<img class="geolocation-map-link-icon" src="' . esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) . '" alt="" width="25" height="34" />';
	}
	if ( 'google' === $provider ) {
		// The pin of Google Maps is not part of this plugin, so a look-alike is drawn.
		return '<svg class="geolocation-map-link-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 34" width="24" height="34" aria-hidden="true" focusable="false"><path d="M12 1C5.9 1 1 5.9 1 12c0 7.6 8.6 12.3 10.2 20.1.2.8 1.4.8 1.6 0C14.4 24.3 23 19.6 23 12 23 5.9 18.1 1 12 1z" fill="#ea4335" stroke="#b31412"/><circle cx="12" cy="12" r="4.2" fill="#b31412"/></svg>';
	}
	return '<img class="geolocation-map-link-icon" src="' . esc_url( plugins_url( 'js/images/marker-icon-2x.png', __FILE__ ) ) . '" alt="" width="25" height="41" />';
}

/**
 * Build the link which opens a location in the map service of the selected provider.
 *
 * @param mixed $latitude The latitude.
 * @param mixed $longitude The longitude.
 * @param int   $width The width of the map above the link in pixels, 0 if there is none.
 * @return string The escaped HTML.
 */
function geolocation_get_map_link( $latitude, $longitude, $width = 0 ) {
	$provider = 'google' === get_option( 'geolocation_provider' ) ? 'google' : 'osm';
	$service  = 'google' === $provider ? 'Google Maps' : 'OpenStreetMap';
	$url      = geolocation_get_external_map_url( $provider, $latitude, $longitude, (int) get_option( 'geolocation_default_zoom' ) );
	/* translators: %s: name of the map service, e.g. "OpenStreetMap". */
	$label = geolocation_get_map_link_icon( $provider ) . esc_html( sprintf( __( 'Open in %s', 'geolocation' ), $service ) );
	// Below a map the link is as wide as the map, so it starts at the same edge.
	$box = '<div class="geolocation-map-link-box"' . ( $width > 0 ? ' style="width:' . (int) $width . 'px;max-width:100%;"' : '' ) . '>';
	wp_enqueue_style( 'geolocation_css', plugins_url( 'style.css', __FILE__ ), array(), GEOLOCATION__VERSION, 'all' );

	if ( ! geolocation_map_link_notice() ) {
		// No referrer: the map service does not learn which page the visitor comes from.
		return $box . '<a class="geolocation-map-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $label . '</a></div>';
	}

	// With a notice the link only works through the script showing it. Without JavaScript it stays hidden
	// and has no address, so nobody leaves the website without having seen the notice.
	wp_enqueue_script( 'geolocation_map_link', plugins_url( 'js/geolocation-map-link.js', __FILE__ ), array(), GEOLOCATION__VERSION, true );
	if ( ! wp_script_is( 'geolocation_map_link', 'done' ) && ! wp_scripts()->get_data( 'geolocation_map_link', 'before' ) ) {
		$host = 'google' === $provider ? 'google.com' : 'openstreetmap.org';
		$data = array(
			'title'  => geolocation_block_text( __( 'You are leaving this website', 'geolocation' ) ),
			/* translators: %s: host name of the map service, e.g. "openstreetmap.org". */
			'text'   => geolocation_block_text( sprintf( __( 'The map opens on %s. That service receives your IP address.', 'geolocation' ), $host ) ),
			'open'   => geolocation_block_text( __( 'Open map', 'geolocation' ) ),
			'cancel' => geolocation_block_text( __( 'Cancel', 'geolocation' ) ),
		);
		wp_add_inline_script( 'geolocation_map_link', 'var geolocationMapLink = ' . wp_json_encode( $data ) . ';', 'before' );
	}
	return $box . '<a class="geolocation-map-link" data-url="' . esc_url( $url ) . '" hidden>' . $label . '</a></div>';
}
