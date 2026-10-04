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

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

require_once dirname( __DIR__ ) . '/geolocation.php';
