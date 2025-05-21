<?php
/**
 * Settings
 *
 * This is the plugin specific settings functionality.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <geolocation@yann-michel.de>
 * @license GPL2
 */

/**
 * Initialize the available languages for this plugin.
 *
 * @return void
 */
function languages_init() {
	$plugin_rel_path = basename( __DIR__ ) . '/languages/'; /* Relative to WP_PLUGIN_DIR */
	load_plugin_textdomain( 'geolocation', false, $plugin_rel_path );
}

/**
 * Get the active language for the site this plugin is running at.
 *
 * @return string
 */
function get_site_lang() {
	$language = substr( get_locale(), 0, 2 );
	return $language;
}

/**
 * Register all needed settings for this plugin.
 *
 * @return void
 */
function register_settings() {
	register_setting( 'geolocation-settings-group', 'geolocation_map_width' );
	register_setting( 'geolocation-settings-group', 'geolocation_map_height' );
	register_setting( 'geolocation-settings-group', 'geolocation_default_zoom' );
	register_setting( 'geolocation-settings-group', 'geolocation_map_position' );
	register_setting( 'geolocation-settings-group', 'geolocation_map_display' );
	register_setting( 'geolocation-settings-group', 'geolocation_wp_pin' );
	register_setting( 'geolocation-settings-group', 'geolocation_google_maps_api_key' );
	register_setting( 'geolocation-settings-group', 'geolocation_updateAddresses' );
	register_setting( 'geolocation-settings-group', 'geolocation_map_width_page' );
	register_setting( 'geolocation-settings-group', 'geolocation_map_height_page' );
	register_setting( 'geolocation-settings-group', 'geolocation_provider' );
	register_setting( 'geolocation-settings-group', 'geolocation_shortcode' );
	register_setting( 'geolocation-settings-group', 'geolocation_osm_use_proxy' );
	register_setting( 'geolocation-settings-group', 'geolocation_osm_tiles_url' );
	register_setting( 'geolocation-settings-group', 'geolocation_osm_nominatim_url' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_osm_leaflet_js_url' );
	delete_option( 'geolocation_osm_leaflet_js_url' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_osm_leaflet_css_url' );
	delete_option( 'geolocation_osm_leaflet_css_url' );
}

/**
 * Unregister all settings for this plugin.
 *
 * @return void
 */
function unregister_settings() {
	unregister_setting( 'geolocation-settings-group', 'geolocation_map_width' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_map_height' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_default_zoom' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_map_position' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_map_display' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_wp_pin' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_google_maps_api_key' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_updateAddresses' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_map_width_page' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_map_height_page' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_provider' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_shortcode' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_osm_use_proxy' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_osm_tiles_url' );
	unregister_setting( 'geolocation-settings-group', 'geolocation_osm_nominatim_url' );
}

/**
 * Apply the value for the given setting to be stored.
 *
 * @param [type] $name The name of the attribute to store.
 * @param [type] $value The value of the attribute to be stored.
 * @return void
 */
function default_setting( $name, $value ) {
	if ( ! get_option( $name ) ) {
		update_option( $name, $value );
	}
}

/**
 * Apply all default settings for this Plugin.
 *
 * @return void
 */
function default_settings() {
	default_setting( 'geolocation_map_width', '450' );
	default_setting( 'geolocation_map_height', '200' );
	default_setting( 'geolocation_default_zoom', '16' );
	default_setting( 'geolocation_map_position', 'after' );
	default_setting( 'geolocation_map_display', 'map' );
	update_option( 'geolocation_updateAddresses', false );
	default_setting( 'geolocation_map_width_page', '600' );
	default_setting( 'geolocation_map_height_page', '300' );
	default_setting( 'geolocation_provider', 'osm' );
	default_setting( 'geolocation_shortcode', '[geolocation]' );
	default_setting( 'geolocation_osm_use_proxy', false );
	default_setting( 'geolocation_osm_tiles_url', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png' );
	default_setting( 'geolocation_osm_nominatim_url', 'https://nominatim.openstreetmap.org/' );
}

/**
 * Delete all settings stored for this Plugin.
 *
 * @return void
 */
function delete_settings() {
	delete_option( 'geolocation_map_width' );
	delete_option( 'geolocation_map_height' );
	delete_option( 'geolocation_default_zoom' );
	delete_option( 'geolocation_map_position' );
	delete_option( 'geolocation_map_display' );
	delete_option( 'geolocation_updateAddresses' );
	delete_option( 'geolocation_map_width_page' );
	delete_option( 'geolocation_map_height_page' );
	delete_option( 'geolocation_provider' );
	delete_option( 'geolocation_shortcode' );
	delete_option( 'geolocation_osm_use_proxy' );
	delete_option( 'geolocation_osm_tiles_url' );
	delete_option( 'geolocation_osm_leaflet_js_url' );
	delete_option( 'geolocation_osm_leaflet_css_url' );
	delete_option( 'geolocation_osm_nominatim_url' );
}

/**
 * Delete all posts geo_adress attributes.
 *
 * @return void
 */
function delete_addresses() {
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
	);

	$post_query = new WP_Query( $args );
	if ( $post_query->have_posts() ) {
		while ( $post_query->have_posts() ) {
			$post_query->the_post();
			$post = get_post();
			delete_post_meta( $post->ID, 'geo_address' );
		}
	}
}

/**
 * Activate the PLugin and set defaults.
 *
 * @return void
 */
function activate() {
	register_settings();
	default_settings();
}

/**
 * Unregister this Plugin and clean up.
 *
 * @return void
 */
function uninstall() {
	unregister_settings();
	delete_settings();
	delete_addresses();
}

/**
 * Add settings to options and register Plugin.
 *
 * @return void
 */
function add_settings() {
	if ( is_admin() ) {
		require_once GEOLOCATION__PLUGIN_DIR . 'geolocation.settings.page.php';
		add_options_page( __( 'Geolocation Plugin Settings', 'geolocation' ), 'Geolocation', 'manage_options', 'geolocation.php', 'geolocation_settings_page' );
		add_action( 'admin_init', 'register_settings' );
	}
}
