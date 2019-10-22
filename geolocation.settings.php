<?php

function languages_init()
{
    $plugin_rel_path = basename(dirname(__FILE__)) . '/languages/'; /* Relative to WP_PLUGIN_DIR */
    load_plugin_textdomain('geolocation', 'false', $plugin_rel_path);
}

function getSiteLang()
{
    $language = substr(get_locale(), 0, 2);
    return $language;
}

function register_settings()
{
    register_setting('geolocation-settings-group', 'geolocation_map_width');
    register_setting('geolocation-settings-group', 'geolocation_map_height');
    register_setting('geolocation-settings-group', 'geolocation_default_zoom');
    register_setting('geolocation-settings-group', 'geolocation_map_position');
    register_setting('geolocation-settings-group', 'geolocation_map_display');
    register_setting('geolocation-settings-group', 'geolocation_wp_pin');
    register_setting('geolocation-settings-group', 'geolocation_google_maps_api_key');
    register_setting('geolocation-settings-group', 'geolocation_updateAddresses');
    register_setting('geolocation-settings-group', 'geolocation_map_width_page');
    register_setting('geolocation-settings-group', 'geolocation_map_height_page');
    register_setting('geolocation-settings-group', 'geolocation_provider');
    register_setting('geolocation-settings-group', 'geolocation_shortcode');
}

function activate()
{
    register_settings();
    add_option('geolocation_map_width', '450');
    add_option('geolocation_map_height', '200');
    add_option('geolocation_default_zoom', '16');
    add_option('geolocation_map_position', 'after');
    add_option('geolocation_map_display', 'link');
    add_option('geolocation_wp_pin', '1');
    add_option('geolocation_map_width_page', '600');
    add_option('geolocation_map_height_page', '250');
    add_option('geolocation_provider', 'google');
    add_option('geolocation_shortcode', '[geolocation]');
}

function default_settings()
{
    if (!get_option('geolocation_map_width')) {
        update_option('geolocation_map_width', '450');
    }
    if (!get_option('geolocation_map_height')) {
        update_option('geolocation_map_height', '200');
    }
    if (!get_option('geolocation_default_zoom')) {
        update_option('geolocation_default_zoom', '16');
    }
    if (!get_option('geolocation_map_position')) {
        update_option('geolocation_map_position', 'after');
    }
    if (!get_option('geolocation_map_display')) {
        update_option('geolocation_map_display', 'link');
    }
    update_option('geolocation_updateAddresses', false);
    if (!get_option('geolocation_map_width_page')) {
        update_option('geolocation_map_width_page', '600');
    }
    if (!get_option('geolocation_map_height_page')) {
        update_option('geolocation_map_height_page', '250');
    }
    if (!get_option('geolocation_provider')) {
        update_option('geolocation_provider', 'google');
    }
    if (!get_option('geolocation_shortcode')) {
        update_option('geolocation_shortcode', '[geolocation]');
    }


}

function add_settings()
{
    if (is_admin()) { // admin actions
        require_once(GEOLOCATION__PLUGIN_DIR . 'geolocation.settings.page.php');
        add_options_page(__('Geolocation Plugin Settings', 'geolocation'), 'Geolocation', 'administrator', 'geolocation.php', 'geolocation_settings_page');
        add_action('admin_init', 'register_settings');
    }
}