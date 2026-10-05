<?php
/**
 * Settings
 *
 * This is the plugin specific settings functionality.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Initialize the available languages for this plugin.
 *
 * @return void
 */
function geolocation_languages_init() {
	$plugin_rel_path = basename( __DIR__ ) . '/languages/'; /* Relative to WP_PLUGIN_DIR */
	load_plugin_textdomain( 'geolocation', false, $plugin_rel_path );
}

/**
 * Get the active language for the site this plugin is running at.
 *
 * @return string
 */
function geolocation_get_site_lang() {
	$language = substr( get_locale(), 0, 2 );
	return $language;
}

/**
 * Get all settings of this plugin including their type, sanitizing and default value.
 *
 * @return array
 */
function geolocation_get_settings_definition() {
	return array(
		'geolocation_map_width'           => array( 'integer', 'absint', '450' ),
		'geolocation_map_height'          => array( 'integer', 'absint', '200' ),
		'geolocation_default_zoom'        => array( 'integer', 'absint', '16' ),
		'geolocation_map_position'        => array( 'string', 'geolocation_sanitize_position', 'after' ),
		'geolocation_map_display'         => array( 'string', 'geolocation_sanitize_display', 'map' ),
		'geolocation_wp_pin'              => array( 'string', 'sanitize_text_field', null ),
		'geolocation_google_maps_api_key' => array( 'string', 'sanitize_text_field', null ),
		'geolocation_updateAddresses'     => array( 'string', 'sanitize_text_field', null ),
		'geolocation_map_width_page'      => array( 'integer', 'absint', '600' ),
		'geolocation_map_height_page'     => array( 'integer', 'absint', '300' ),
		'geolocation_provider'            => array( 'string', 'geolocation_sanitize_provider', 'osm' ),
		'geolocation_shortcode'           => array( 'string', 'sanitize_text_field', '[geolocation]' ),
		'geolocation_track_trim'          => array( 'integer', 'absint', null ),
		'geolocation_osm_use_proxy'       => array( 'string', 'sanitize_text_field', null ),
		'geolocation_osm_strict_privacy'  => array( 'string', 'sanitize_text_field', null ),
		'geolocation_osm_tiles_url'       => array( 'string', 'sanitize_text_field', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' ),
		'geolocation_osm_nominatim_url'   => array( 'string', 'geolocation_sanitize_url', 'https://nominatim.openstreetmap.org/' ),
	);
}

/**
 * Register all needed settings for this plugin.
 *
 * @return void
 */
function geolocation_register_settings() {
	foreach ( geolocation_get_settings_definition() as $name => $definition ) {
		register_setting(
			'geolocation-settings-group',
			$name,
			array(
				'type'              => $definition[0],
				'sanitize_callback' => $definition[1],
			)
		);
	}
}

/**
 * Only allow the supported map providers to be stored.
 *
 * @param mixed $value The submitted provider.
 * @return string
 */
function geolocation_sanitize_provider( $value ) {
	return in_array( $value, array( 'google', 'osm' ), true ) ? $value : 'osm';
}

/**
 * Only allow the supported positions to be stored.
 *
 * @param mixed $value The submitted position.
 * @return string
 */
function geolocation_sanitize_position( $value ) {
	return in_array( $value, array( 'before', 'after', 'shortcode' ), true ) ? $value : 'after';
}

/**
 * Only allow the supported display modes to be stored.
 *
 * @param mixed $value The submitted display mode.
 * @return string
 */
function geolocation_sanitize_display( $value ) {
	return in_array( $value, array( 'plain', 'link', 'map', 'debug' ), true ) ? $value : 'map';
}

/**
 * Sanitize a URL setting.
 *
 * @param mixed $value The submitted URL.
 * @return string
 */
function geolocation_sanitize_url( $value ) {
	return esc_url_raw( (string) $value );
}

/**
 * Unregister all settings for this plugin.
 *
 * @return void
 */
function geolocation_unregister_settings() {
	foreach ( array_keys( geolocation_get_settings_definition() ) as $name ) {
		unregister_setting( 'geolocation-settings-group', $name );
	}
}

/**
 * Apply all default settings for this Plugin.
 *
 * @return void
 */
function geolocation_default_settings() {
	foreach ( geolocation_get_settings_definition() as $name => $definition ) {
		if ( null !== $definition[2] && ! get_option( $name ) ) {
			update_option( $name, $definition[2] );
		}
	}
	update_option( 'geolocation_updateAddresses', false );
}

/**
 * Delete all settings stored for this Plugin.
 *
 * @return void
 */
function geolocation_delete_settings() {
	foreach ( array_keys( geolocation_get_settings_definition() ) as $name ) {
		delete_option( $name );
	}
	delete_option( 'geolocation_version' );
	delete_option( 'geolocation_markers_version' );
	geolocation_delete_legacy_settings();
}

/**
 * Delete settings of former versions which are not used anymore.
 *
 * @return void
 */
function geolocation_delete_legacy_settings() {
	delete_option( 'geolocation_osm_leaflet_js_url' );
	delete_option( 'geolocation_osm_leaflet_css_url' );
}

/**
 * Apply defaults and clean up once after the plugin has been updated.
 *
 * @return void
 */
function geolocation_maybe_upgrade() {
	if ( GEOLOCATION__VERSION === get_option( 'geolocation_version' ) ) {
		return;
	}
	geolocation_default_settings();
	geolocation_delete_legacy_settings();
	update_option( 'geolocation_version', GEOLOCATION__VERSION );
}

/**
 * Delete the addresses derived by this plugin, the tracks of the posts and the cached lookups.
 *
 * The coordinates of the posts are kept as they are standard geo data.
 *
 * @return void
 */
function geolocation_delete_addresses() {
	global $wpdb;
	delete_post_meta_by_key( 'geo_address' );
	delete_post_meta_by_key( 'geo_address_reverse' );
	delete_post_meta_by_key( 'geo_track' );
	delete_post_meta_by_key( 'geo_track_km' );
	delete_post_meta_by_key( 'geo_track_ele' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- there is no API to delete transients by prefix.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_geolocation_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_geolocation_' ) . '%'
		)
	);
}

/**
 * Activate the PLugin and set defaults.
 *
 * @return void
 */
function geolocation_activate() {
	geolocation_default_settings();
}

/**
 * Unregister this Plugin and clean up.
 *
 * @return void
 */
function geolocation_uninstall() {
	wp_unschedule_hook( 'geolocation_update_addresses_batch' );
	geolocation_unregister_settings();
	geolocation_delete_settings();
	geolocation_delete_addresses();
}

/**
 * Add the settings page to the options menu.
 *
 * @return void
 */
function geolocation_add_settings() {
	require_once GEOLOCATION__PLUGIN_DIR . 'geolocation-settings-page.php';
	$hook_suffix = add_options_page( __( 'Geolocation Plugin Settings', 'geolocation' ), 'Geolocation', 'manage_options', 'geolocation.php', 'geolocation_settings_page' );
	if ( $hook_suffix ) {
		add_action( 'load-' . $hook_suffix, 'geolocation_add_help_tab' );
	}
}
