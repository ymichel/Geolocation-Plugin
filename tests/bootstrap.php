<?php
/**
 * PHPUnit bootstrap: loads the plugin with minimal stubs of the WordPress API.
 *
 * Only the functions needed to load the plugin files and to run its pure
 * helper functions are stubbed, no WordPress installation is required.
 *
 * @package geolocation
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Squiz.Commenting.FunctionComment.Missing, Generic.CodeAnalysis.UnusedFunctionParameter.Found

$GLOBALS['geolocation_test_options']   = array();
$GLOBALS['geolocation_test_logged_in'] = false;
$GLOBALS['geolocation_test_post_meta'] = array();
$GLOBALS['geolocation_test_filters']   = array();
$GLOBALS['geolocation_test_plugins']   = array();

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function register_activation_hook( $file, $callback ) {
}

function register_uninstall_hook( $file, $callback ) {
}

function get_option( $name, $default_value = false ) {
	return isset( $GLOBALS['geolocation_test_options'][ $name ] ) ? $GLOBALS['geolocation_test_options'][ $name ] : $default_value;
}

function is_user_logged_in() {
	return $GLOBALS['geolocation_test_logged_in'];
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return isset( $GLOBALS['geolocation_test_post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['geolocation_test_post_meta'][ $post_id ][ $key ] : '';
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function sanitize_title( $title ) {
	return strtolower( trim( preg_replace( '/[^A-Za-z0-9]+/', '-', (string) $title ), '-' ) );
}

function current_user_can( $capability ) {
	return true;
}

function apply_filters( $hook, $value ) {
	return array_key_exists( $hook, $GLOBALS['geolocation_test_filters'] ) ? $GLOBALS['geolocation_test_filters'][ $hook ] : $value;
}

function is_plugin_active( $plugin ) {
	return in_array( $plugin, $GLOBALS['geolocation_test_plugins'], true );
}

function plugins_url( $path = '', $plugin = '' ) {
	return 'https://example.org/wp-content/plugins/geolocation/' . ltrim( $path, '/' );
}

require_once dirname( __DIR__ ) . '/geolocation.php';
require_once dirname( __DIR__ ) . '/geolocation-map-provider-osm.php';
