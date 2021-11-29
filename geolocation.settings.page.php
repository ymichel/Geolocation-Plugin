<?php
function geolocation_settings_page()
{
    if ((bool) get_option('geolocation_updateAddresses')) {
        updateGeolocationAddresses();
    }

    default_settings();
    $zoomImage = (string) get_option('geolocation_default_zoom');
    if ((bool) get_option('geolocation_wp_pin')) {
        $zoomImage = 'wp_'.$zoomImage.'.png';
    } else {
        $zoomImage = $zoomImage.'.png';
    }
?>
    <style type="text/css">
        #zoom_level_sample {
            background: url('<?php echo esc_url(plugins_url('img/zoom/'.$zoomImage, __FILE__)); ?>');
            width: 390px;
            height: 190px;
            border: solid 1px #999;
        }

        #preload {
            display: none;
        }

        .dimensions strong {
            width: 50px;
            float: left;
        }

        .dimensions input {
            width: 50px;
            margin-right: 5px;
        }

        .zoom label {
            width: 50px;
            margin: 0 5px 0 2px;
        }

        .position label {
            margin: 0 5px 0 2px;
        }
    </style>
    <script type="text/javascript">
        var file;
        var zoomlevel = <?php echo (int) esc_attr((string) get_option('geolocation_default_zoom')); ?>;
        var path = '<?php echo esc_js(plugins_url('img/zoom/', __FILE__)); ?>';

        function swap_zoom_sample(id) {
            zoomlevel = document.getElementById(id).value;
            pin_click();
        }

        function pin_click() {
            var div = document.getElementById('zoom_level_sample');
            file = path + zoomlevel + '.png';
            if (document.getElementById('geolocation_wp_pin').checked)
                file = path + 'wp_' + zoomlevel + '.png';
            div.style.background = 'url(' + file + ')';
        }
    </script>
    <div class="wrap">
        <h2><?php _e('Geolocation Plugin Settings', 'geolocation'); ?></h2>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('geolocation-settings-group'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('Dimensions', 'geolocation'); ?></th>
                <td class="dimensions">
                    <strong><?php _e('Width', 'geolocation'); ?>:</strong><input type="text" name="geolocation_map_width" value="<?php echo esc_attr((string) get_option('geolocation_map_width')); ?>" />px<br />
                    <strong><?php _e('Height', 'geolocation'); ?>:</strong><input type="text" name="geolocation_map_height" value="<?php echo esc_attr((string) get_option('geolocation_map_height')); ?>" />px
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Position', 'geolocation'); ?></th>
                <td class="position">
                    <input type="radio" id="geolocation_map_position_before" name="geolocation_map_position" value="before" <?php is_value('geolocation_map_position', 'before'); ?>><label for="geolocation_map_position_before"><?php _e('Before the post.', 'geolocation'); ?></label><br />
                    <input type="radio" id="geolocation_map_position_after" name="geolocation_map_position" value="after" <?php is_value('geolocation_map_position', 'after'); ?>><label for="geolocation_map_position_after"><?php _e('After the post.', 'geolocation'); ?></label><br />
                    <input type="radio" id="geolocation_map_position_shortcode" name="geolocation_map_position" value="shortcode" <?php is_value('geolocation_map_position', 'shortcode'); ?>><label for="geolocation_map_position_shortcode"><?php _e('Wherever I put the shortcode: ', 'geolocation');
                                                                                                                                                                                                                                                get_option('geolocation_shortcode') ?>.</label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('How would you like your maps to be displayed?', 'geolocation'); ?></th>
                <td class="display">
                    <input type="radio" id="geolocation_map_display_plain" name="geolocation_map_display" value="plain" <?php is_value('geolocation_map_display', 'plain'); ?>>
                    <label for="geolocation_map_display_plain"><?php _e('Plain text.', 'geolocation'); ?></label><br />
                    <input type="radio" id="geolocation_map_display_link" name="geolocation_map_display" value="link" <?php is_value('geolocation_map_display', 'link'); ?>>
                    <label for="geolocation_map_display_link"><?php _e('Simple link w/hover.', 'geolocation'); ?></label><br />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Default Zoom Level', 'geolocation'); ?></th>
                <td class="zoom">
                    <input type="radio" id="geolocation_default_zoom_globe" name="geolocation_default_zoom" value="1" <?php is_value('geolocation_default_zoom', '1'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_globe"><?php _e('Globe', 'geolocation'); ?></label>

                    <input type="radio" id="geolocation_default_zoom_country" name="geolocation_default_zoom" value="3" <?php is_value('geolocation_default_zoom', '3'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_country"><?php _e('Country', 'geolocation'); ?></label>
                    <input type="radio" id="geolocation_default_zoom_state" name="geolocation_default_zoom" value="6" <?php is_value('geolocation_default_zoom', '6'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_state"><?php _e('State', 'geolocation'); ?></label>
                    <input type="radio" id="geolocation_default_zoom_city" name="geolocation_default_zoom" value="9" <?php is_value('geolocation_default_zoom', '9'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_city"><?php _e('City', 'geolocation'); ?></label>
                    <input type="radio" id="geolocation_default_zoom_street" name="geolocation_default_zoom" value="16" <?php is_value('geolocation_default_zoom', '16'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_street"><?php _e('Street', 'geolocation'); ?></label>
                    <input type="radio" id="geolocation_default_zoom_block" name="geolocation_default_zoom" value="18" <?php is_value('geolocation_default_zoom', '18'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_block"><?php _e('Block', 'geolocation'); ?></label>
                    <br />
                    <div id="zoom_level_sample"></div>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"></th>
                <td class="position">
                    <input type="checkbox" id="geolocation_wp_pin" name="geolocation_wp_pin" value="1" <?php is_checked('geolocation_wp_pin'); ?> onclick="javascript:pin_click();"><label for="geolocation_wp_pin"><?php _e('Show your support for WordPress by using the WordPress map pin.', 'geolocation'); ?></label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Dimensions Page', 'geolocation'); ?></th>
                <td class="dimensions">
                    <strong><?php _e('Width', 'geolocation'); ?>:</strong><input type="text" name="geolocation_map_width_page" value="<?php echo esc_attr((string) get_option('geolocation_map_width_page')); ?>" />px<br />
                    <strong><?php _e('Height', 'geolocation'); ?>:</strong><input type="text" name="geolocation_map_height_page" value="<?php echo esc_attr((string) get_option('geolocation_map_height_page')); ?>" />px
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Maps Provider</th>
                <td class="apikey">
	            <select name="geolocation_provider">
                    <option value="google"<?php if ((string) get_option('geolocation_provider') == 'google') { echo ' selected'; }?>>Google Maps</option>
                    <option value="osm"<?php if ((string) get_option('geolocation_provider') == 'osm') { echo ' selected'; }?>>Open Street Maps</option>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Google Maps API key</th>
                <td class="apikey">
                    <input type="text" name="geolocation_google_maps_api_key" value="<?php echo esc_attr((string) get_option('geolocation_google_maps_api_key')); ?>" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('Used Language for Adresses', 'geolocation'); ?></th>
                <td class="apikey">
                    <?php echo esc_attr((string) getSiteLang()); ?>
            </tr>
            <tr valign="top">
                <th scope="row"></th>
                <td class="position">
                    <input type="checkbox" id="geolocation_updateAddresses" name="geolocation_updateAddresses" value="1" <?php is_checked('geolocation_updateAddresses'); ?>><label for="geolocation_updateAddresses"><?php _e('Update all addresses from posts that have location information<br>(only once this setup is saved).', 'geolocation'); ?></label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'geolocation') ?>" alt="" />
        </p>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="page_options" value="geolocation_map_width,geolocation_map_height,geolocation_default_zoom,geolocation_map_position,geolocation_wp_pin" />
    </form>
    <div id="preload">
        <img src="<?php echo esc_url(plugins_url('img/zoom/1.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/3.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/6.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/9.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/16.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/18.png', __FILE__)); ?>" alt="" />

        <img src="<?php echo esc_url(plugins_url('img/zoom/wp_1.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/wp_3.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/wp_6.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/wp_9.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/wp_16.png', __FILE__)); ?>" alt="" />
        <img src="<?php echo esc_url(plugins_url('img/zoom/wp_18.png', __FILE__)); ?>" alt="" />
    </div>
<?php
}

?>
