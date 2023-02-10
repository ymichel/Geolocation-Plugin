<?php
/*
Plugin Name: Geolocation
Plugin URI: https://wordpress.org/extend/plugins/geolocation/
Description: Displays post geotag information on an embedded map.
Version: 1.6
Author: Yann Michel
Author URI: https://www.yann-michel.de/geolocation
Text Domain: geolocation
License: GPL2
*/


/*  Copyright 2010 Chris Boyd  (email : chris@chrisboyd.net)
              2018-2023 Yann Michel (email : geolocation@yann-michel.de)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define('GEOLOCATION__PLUGIN_DIR', plugin_dir_path(__FILE__));

add_action('upgrader_process_complete', 'plugin_upgrade_completed', 10, 2);
add_action('plugins_loaded', 'languages_init');
add_action('wp_head', 'add_geo_support');
add_action('wp_footer', 'add_geo_div');
add_action('admin_menu', 'add_settings');
add_filter('the_content', 'display_location', 5);
admin_init();
register_activation_hook(__FILE__, 'activate');
register_uninstall_hook(__FILE__, 'uninstall');

require_once(GEOLOCATION__PLUGIN_DIR . 'geolocation.settings.php');

// To do: add support for multiple Map API providers
switch (get_option('geolocation_provider')) {
    case 'google':
        require_once(GEOLOCATION__PLUGIN_DIR . 'geolocation.map-provider_google.php');
        break;
        //case 'osm':
        //    require_once(GEOLOCATION__PLUGIN_DIR . 'geolocation.map-provider_osm.php');
        //    break;
}
require_once(GEOLOCATION__PLUGIN_DIR . 'geolocation.map-provider_osm.php');

function geolocation_append_support_and_faq_links($links_array, $plugin_file_name)
{
    if (strpos($plugin_file_name, basename(__FILE__))) {
        $links_array[] = '<a href="https://wordpress.org/support/plugin/geolocation/reviews/#new-post" target="_blank">' . __('Review', 'geolocation') . '</a>';
        $links_array[] = '<a href="https://wordpress.org/support/plugin/geolocation/#new-topic" target="_blank">' . __('Support', 'geolocation') . '</a>';
    }
    return $links_array;
}
add_filter('plugin_row_meta', 'geolocation_append_support_and_faq_links', 10, 2);

function geolocation_customizer_action_links($links_array, $plugin_file_name)
{

    if (strpos($plugin_file_name, basename(__FILE__))) {
        $config_link = '<a href="options-general.php?page=geolocation">' . __('Settings', 'geolocation') . '</a>';
        array_unshift($links_array, $config_link);
    }
    return $links_array;
}
add_action('plugin_action_links', 'geolocation_customizer_action_links', 10, 2);

function plugin_upgrade_completed($upgrader_object, $options)
{
    $our_plugin = plugin_basename(__FILE__);
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        foreach ($options['plugins'] as $plugin) {
            if ($plugin == $our_plugin) {
                register_settings();
                default_settings();
            }
        }
    }
}

// display custom admin notice
function geolocation_custom_admin_notice()
{
    if (!get_option('geolocation_google_maps_api_key') and get_option('geolocation_provider') == 'google') { ?>
        <div class="notice notice-error">
            <p><?php _e('Google Maps API key is missing for', 'geolocation'); ?> <a href="options-general.php?page=geolocation">Geolocation</a>!</p>
        </div>
    <?php }
}

add_action('admin_notices', 'geolocation_custom_admin_notice');

function geolocation_add_custom_box()
{
    if (function_exists('add_meta_box')) {
        add_meta_box('geolocation_sectionid', __('Geolocation', 'geolocation'), 'geolocation_inner_custom_box', 'post', 'advanced');
    } else {
        add_action('dbx_post_advanced', 'geolocation_old_custom_box');
    }
}

function geolocation_inner_custom_box()
{ ?>
    <input type="hidden" id="geolocation_nonce" name="geolocation_nonce" value="<?php echo wp_create_nonce(plugin_basename(__FILE__)); ?>" />
    <label class="screen-reader-text" for="geolocation-address">Geolocation</label>
    <div class="taghint"><?php echo __('Enter your address', 'geolocation'); ?></div>
    <input type="text" id="geolocation-address" name="geolocation-address" class="newtag form-input-tip" size="25" autocomplete="off" value="" />
    <input id="geolocation-load" type="button" class="button geolocationadd" value="<?php echo  __('Load', 'geolocation'); ?>" tabindex="3" />
    <input type="hidden" id="geolocation-latitude" name="geolocation-latitude" />
    <input type="hidden" id="geolocation-longitude" name="geolocation-longitude" />
    <div id="geolocation-map" style="border:solid 1px #c6c6c6;width:<?php echo esc_attr((string) get_option('geolocation_map_width')); ?>px;height:<?php echo esc_attr((string) get_option('geolocation_map_height')); ?>px;margin-top:5px;"></div>
    <div style="margin:5px 0 0 0;">
        <input id="geolocation-public" name="geolocation-public" type="checkbox" value="1" />
        <label for="geolocation-public"><?php echo  __('Public', 'geolocation'); ?></label>
        <div style="float:right">
            <input id="geolocation-enabled" name="geolocation-on" type="radio" value="1" />
            <label for="geolocation-enabled"><?php echo  __('On', 'geolocation'); ?></label>
            <input id="geolocation-disabled" name="geolocation-on" type="radio" value="0" />
            <label for="geolocation-disabled"><?php echo  __('Off', 'geolocation'); ?></label>
        </div>
    </div>
<?php
}

/* Prints the edit form for pre-WordPress 2.5 post/page */
function geolocation_old_custom_box()
{ ?>
    <div class="dbx-b-ox-wrapper">
        <fieldset id="geolocation_fieldsetid" class="dbx-box">
            <div class="dbx-h-andle-wrapper">
                <h3 class="dbx-handle"><?php echo __('Geolocation', 'geolocation'); ?></h3>
            </div>
            <div class="dbx-c-ontent-wrapper">
                <div class="dbx-content">
                    <?php
                    geolocation_inner_custom_box();
                    ?>
                </div>
            </div>
        </fieldset>
    </div>
<?php
}


function geolocation_save_postdata($post_id)
{
    // Check authorization, permissions, autosave, etc
    if ((!wp_verify_nonce($_POST['geolocation_nonce'], plugin_basename(__FILE__))) ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        (('page' == $_POST['post_type']) && (!current_user_can('edit_page', $post_id))) ||
        (!current_user_can('edit_post', $post_id))
    ) {
        return $post_id;
    }

    $latitude = clean_coordinate($_POST['geolocation-latitude']);
    $longitude = clean_coordinate($_POST['geolocation-longitude']);

    if ((empty($latitude)) || (empty($longitude))) {
        //check the featured image for geodata if no data was available in the post already
        $post_img_id = get_post_thumbnail_id();
        if ($post_img_id !== 0) {

            $orig_img_path = wp_get_original_image_path($post_img_id, false);
            if ($orig_img_path !== false) {
                $exif = exif_read_data($orig_img_path);

                if ((isset($exif["GPSLatitude"])) and (isset($exif["GPSLongitude"]))) {
                    $GPSLatitude = $exif["GPSLatitude"];
                    $GPSLatitude_g = explode("/", $GPSLatitude[0]);
                    $GPSLatitude_m = explode("/", $GPSLatitude[1]);
                    $GPSLatitude_s = explode("/", $GPSLatitude[2]);
                    $GPSLat_g = $GPSLatitude_g[0] / $GPSLatitude_g[1];
                    $GPSLat_m = $GPSLatitude_m[0] / $GPSLatitude_m[1];
                    $GPSLat_s = $GPSLatitude_s[0] / $GPSLatitude_s[1];
                    $latitude = $GPSLat_g + ($GPSLat_m + ($GPSLat_s / 60)) / 60;

                    $GPSLongitude = $exif["GPSLongitude"];
                    $GPSLongitude_g = explode("/", $GPSLongitude[0]);
                    $GPSLongitude_m = explode("/", $GPSLongitude[1]);
                    $GPSLongitude_s = explode("/", $GPSLongitude[2]);
                    $GPSLon_g = $GPSLongitude_g[0] / $GPSLongitude_g[1];
                    $GPSLon_m = $GPSLongitude_m[0] / $GPSLongitude_m[1];
                    $GPSLon_s = $GPSLongitude_s[0] / $GPSLongitude_s[1];
                    $longitude = $GPSLon_g + ($GPSLon_m + ($GPSLon_s / 60)) / 60;
                }
            }
        }
    }

    if ((!empty($latitude)) && (!empty($longitude))) {
        update_post_meta($post_id, 'geo_latitude', $latitude);
        update_post_meta($post_id, 'geo_longitude', $longitude);

        $address = reverse_geocode($latitude, $longitude);
        if ($address != '') {
            update_post_meta($post_id, 'geo_address', $address);
        }

        if ($_POST['geolocation-on']) {
            update_post_meta($post_id, 'geo_enabled', 1);
        } else {
            update_post_meta($post_id, 'geo_enabled', 0);
        }

        if ($_POST['geolocation-public']) {
            update_post_meta($post_id, 'geo_public', 1);
        } else {
            update_post_meta($post_id, 'geo_public', 0);
        }
    }

    return $post_id;
}

function admin_init()
{
    add_action('admin_head-post-new.php', 'admin_head');
    add_action('admin_head-post.php', 'admin_head');
    add_action('admin_menu', 'geolocation_add_custom_box');
    add_action('save_post_post', 'geolocation_save_postdata');
}

function admin_head()
{
    // To do: add support for multiple Map API providers
    switch (get_option('geolocation_provider')) {
        case 'google':
            admin_head_google();
            break;
        case 'osm':
            admin_head_osm();
            break;
    }
}

function get_geo_div()
{
    $width = esc_attr((string) get_option('geolocation_map_width'));
    $height = esc_attr((string) get_option('geolocation_map_height'));
    return '<div id="map" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
}

function add_geo_div()
{
    if ((esc_attr((string) get_option('geolocation_map_display')) <> 'plain')) {
        echo get_geo_div();
    }
}

function add_geo_support()
{
    global $posts;
    if ((esc_attr((string) get_option('geolocation_map_display')) <> 'plain') || (is_user_logged_in())) {

        // To do: add support for multiple Map API providers
        switch (get_option('geolocation_provider')) {
            case 'google':
                add_geo_support_google($posts);
                break;
            case 'osm':
                add_geo_support_osm($posts);
                break;
        }
    }
    echo '<link type="text/css" rel="stylesheet" href="' . esc_url(plugins_url('style.css', __FILE__)) . '" />';
}

function geo_has_shortcode($content)
{
    $pos = strpos($content, esc_attr((string) get_option('geolocation_shortcode')));
    if ($pos === false) {
        return false;
    } else {
        return true;
    }
}

function display_location($content)
{
    default_settings();
    if (is_page()) {
        return display_location_page($content);
    } else {
        return display_location_post($content);
    }
}

function display_location_page($content)
{
    // To do: add support for multiple Map API providers
    switch (get_option('geolocation_provider')) {
        case 'google':
            return display_location_page_google($content);
        case 'osm':
            return display_location_page_osm($content);
    }
}

function display_location_post($content)
{
    default_settings();
    $shortcode = get_option('geolocation_shortcode');
    global $post;
    $html = '';
    settype($html, "string");
    $latitude = clean_coordinate(get_post_meta($post->ID, 'geo_latitude', true));
    $longitude = clean_coordinate(get_post_meta($post->ID, 'geo_longitude', true));
    $on = (bool) get_post_meta($post->ID, 'geo_enabled', true);
    $public = (bool) get_post_meta($post->ID, 'geo_public', true);

    if (((empty($latitude)) || (empty($longitude))) ||
        ($on === '' || $on === false) ||
        ($public === '' || $public === false)
    ) {
        $content = str_replace(esc_attr((string) $shortcode), '', $content);
        return $content;
    }

    $address = (string) get_post_meta($post->ID, 'geo_address', true);
    if (empty($address)) {
        $address = reverse_geocode($latitude, $longitude);
    }

    switch (esc_attr((string) get_option('geolocation_map_display'))) {
        case 'plain':
            $html = '<div class="geolocation-plain" id="geolocation' . $post->ID . '">' . __('Posted from ', 'geolocation') . esc_html($address) . '.</div>';
            break;
        case 'link':
            $html = '<a class="geolocation-link" href="#" id="geolocation' . $post->ID . '" name="' . $latitude . ',' . $longitude . '" onclick="return false;">' . __('Posted from ', 'geolocation') . esc_html($address) . '.</a>';
            break;
        case 'full':
            $html = get_geo_div();
            break;
        case 'debug':
            $html = '<pre> $latitude: ' . $latitude . '<br> $longitude: ' . $longitude . '<br> $address: ' . $address . '<br> $on: ' . (string) $on . '<br> $public: ' . (string) $public . '</pre>';
            break;
    }

    switch (esc_attr((string) get_option('geolocation_map_position'))) {
        case 'before':
            $content = str_replace(esc_attr((string) $shortcode), '', $content);
            $content = $html . '<br/><br/>' . $content;
            break;
        case 'after':
            $content = str_replace(esc_attr((string) $shortcode), '', $content);
            $content = $content . '<br/><br/>' . $html;
            break;
        case 'shortcode':
            $content = str_replace(esc_attr((string) $shortcode), $html, $content);
            break;
    }
    return $content;
}

function updateGeolocationAddresses()
{
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'geo_latitude',
                'value' => '0',
                'compare' => '!='
            ),
            array(
                'key' => 'geo_longitude',
                'value' => '0',
                'compare' => '!='
            )
        )
    );

    $post_query = new WP_Query($args);
    if ($post_query->have_posts()) {
        $counter = 0;
        while ($post_query->have_posts()) {
            $post_query->the_post();
            $post_id = (int) get_the_ID();
            $postLatitude = get_post_meta($post_id, 'geo_latitude', true);
            $postLongitude = get_post_meta($post_id, 'geo_longitude', true);
            $postAddressNew = (string) reverse_geocode($postLatitude, $postLongitude);
            update_post_meta($post_id, 'geo_address', $postAddressNew);
            $counter = $counter + 1;
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . __($counter . ' Addresses have been updated!', 'geolocation') . '</p></div>';
    }
}

function buildAddress($city, $state, $country)
{
    $address = '';
    if (($city != '') && ($state != '') && ($country != '')) {
        $address = $city . ', ' . $state . ', ' . $country;
    } else if (($city != '') && ($state != '')) {
        $address = $city . ', ' . $state;
    } else if (($state != '') && ($country != '')) {
        $address = $state . ', ' . $country;
    } else if ($country != '') {
        $address = $country;
    }
    return esc_html($address);
}

function reverse_geocode($latitude, $longitude)
{
    $city = '';
    $state = '';
    $country = '';
    //
    // To do: add support for multiple Map API providers
    switch (get_option('geolocation_provider')) {
        case 'google':
            $json = pullJSON_google($latitude, $longitude);
            foreach ($json->results as $result) {
                foreach ($result->address_components as $addressPart) {
                    if (in_array('political', $addressPart->types)) {
                        if ((in_array('locality', $addressPart->types))) {
                            $city = $addressPart->long_name;
                        } else if ((in_array('administrative_area_level_1', $addressPart->types))) {
                            $state = $addressPart->long_name;
                        } else if ((in_array('country', $addressPart->types))) {
                            $country = $addressPart->long_name;
                        }
                    }
                }
            }
            break;
        case 'osm':
            $json = pullJSON_osm($latitude, $longitude);
            $city = $json["address"]["city"];
            $state = $json["address"]["suburb"];
            $country = $json["address"]["country"];
            break;
    }
    return buildAddress($city, $state, $country);
}

function clean_coordinate($coordinate)
{
    $pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
    preg_match($pattern, $coordinate, $matches);
    if ($matches == null) {
        return '';
    }
    return $matches[0];
}

function is_checked($field)
{
    if ((bool) get_option($field)) {
        echo ' checked="checked" ';
    }
}

function is_value($field, $value)
{
    if ((string) get_option($field) == $value) {
        echo ' checked="checked" ';
    }
}

?>