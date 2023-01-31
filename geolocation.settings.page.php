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
    echo '<link rel="stylesheet" href="'.get_osm_leaflet_css_url().'"/>';
    echo '<script src="'.get_osm_leaflet_js_url().'"></script>';
?>
    <style type="text/css">

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
    <div class="notice notice-info">
        <p><?php _e( 'Thank you for using the geolocation plugin. I would appreciate your <a href="https://wordpress.org/support/plugin/geolocation/reviews/#new-post" target="_blank">feedback</a>, and I am also open to <a href="https://wordpress.org/support/plugin/geolocation/#new-topic" target="_blank">suggestions</a>.', 'geolocation' ); ?></p>
    </div>
    <div class="wrap">
        <h2><?php _e('Geolocation Plugin Settings', 'geolocation'); ?></h2>
    </div>
   <form method="post" action="options.php" id="settings">
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
                    <input type="radio" id="geolocation_map_position_shortcode" name="geolocation_map_position" value="shortcode" <?php is_value('geolocation_map_position', 'shortcode'); ?>><label for="geolocation_map_position_shortcode"><?php _e('Wherever I put the shortcode: ', 'geolocation'); echo esc_attr((string) get_option('geolocation_shortcode')); ?>.</label>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('How would you like your maps to be displayed?', 'geolocation'); ?></th>
                <td class="display">
                    <input type="radio" id="geolocation_map_display_plain" name="geolocation_map_display" value="plain" <?php is_value('geolocation_map_display', 'plain'); ?>>
                    <label for="geolocation_map_display_plain"><?php _e('Plain text.', 'geolocation'); ?></label><br />
                    <input type="radio" id="geolocation_map_display_link" name="geolocation_map_display" value="link" <?php is_value('geolocation_map_display', 'link'); ?>>
                    <label for="geolocation_map_display_link"><?php _e('Simple link w/hover.', 'geolocation'); ?></label><br />
<?php /*  ?>
                    <input type="radio" id="geolocation_map_display_debug" name="geolocation_map_display" value="debug" <?php is_value('geolocation_map_display', 'debug'); ?>>
                    <label for="geolocation_map_display_debug"><?php _e('Debug', 'geolocation'); ?></label><br />
<?php // */ ?>
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
<?php echo get_geo_div(); ?>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"></th>
                <td class="position">
                    <input type="checkbox" id="geolocation_wp_pin" name="geolocation_wp_pin" value="1" <?php is_checked('geolocation_wp_pin'); ?> onclick="javascript:updatePin();"><label for="geolocation_wp_pin"><?php _e('Show your support for WordPress by using the WordPress map pin.', 'geolocation'); ?></label>
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
                <td>
	            <select id="geolocation_provider" name="geolocation_provider" onchange="providerSelected(this.value);">
                    <option value="google"<?php if ((string) get_option('geolocation_provider') == 'google') { echo ' selected'; }?>>Google Maps</option>
                    <option value="osm"<?php if ((string) get_option('geolocation_provider') == 'osm') { echo ' selected'; }?>>Open Street Maps</option>
                    </select>
                </td>
            </tr>
            <tr valign="top" class="google-apikey">
                <th scope="row">Google Maps API key</th>
                <td>
                    <input type="text" name="geolocation_google_maps_api_key" value="<?php echo esc_attr((string) get_option('geolocation_google_maps_api_key')); ?>" />
                </td>
            </tr>
            <tr valign="top" class="osm-urls">
                <th scope="row">OSM URLs</th>
                <td>
            <table>
<?php if ( is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' )) { ?>
                 <tr>
                     <th><?php _e('Use Proxy', 'geolocation') ?></th>
		     <td> <input type="checkbox" id="geolocation_osm_use_proxy" name="geolocation_osm_use_proxy" value="1" <?php is_checked('geolocation_osm_use_proxy'); ?>><label for="geolocation_osm_use_proxy"><?php _e('Make use of proxy plugin.', 'geolocation'); ?></label> </td>
		</tr>
<?php } ?>
                 <tr>
                     <th><?php _e('Tiles url (Caching)', 'geolocation') ?></th>
		     <td><?php echo get_osm_tiles_url(); ?></td>
                </tr>
                <tr>
                     <th><?php _e('Leaflet JS', 'geolocation') ?></th>
		     <td><?php echo get_osm_leaflet_js_url(); ?></td>
                </tr>
                <tr>
                     <th><?php _e('Leaflet CSS', 'geolocation') ?></th>
		     <td><?php echo get_osm_leaflet_css_url(); ?></td>
                </tr>
                <tr>
		<th><?php _e('Nominatim ([Reverse-]Geocoding)', 'geolocation') ?></th>
		     <td><?php echo get_osm_nominatim_url(); ?></td>
                </tr>
             </table>
                </td>
 
	    <tr valign="top">
                <th scope="row"><?php _e('Used Language for Adresses', 'geolocation'); ?></th>
                <td>
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
     <script types="text/javascript">
        var file;
        var zoomlevel = <?php echo (int) esc_attr((string) get_option('geolocation_default_zoom')); ?>;
        var path = '<?php echo esc_js(plugins_url('img/zoom/', __FILE__)); ?>';

	var lat_lng = [52.5162778,13.3733267];
   	var map = {}
        var myMapBounds = [];

        var iconOptions = {
		iconUrl: '<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))) ; ?>'
        }
        var customIcon = L.icon(iconOptions);
        var markerOptions = {}
	var myMarker = {};

	function setMarkerOptions(){
		if (document.getElementById("geolocation_wp_pin").checked) {
        		markerOptions = {
       		     	    icon: customIcon,
        		    clickable: false,
        		    draggable: false
        		}
		} else {
        		markerOptions = {
        		    clickable: false,
        		    draggable: false
        		}
		}
	}

	function initializeMap(){
   		map = L.map(document.getElementById("map")).setView(lat_lng, zoomlevel);
        	myMapBounds = [];
		L.tileLayer('<?php echo get_osm_tiles_url(); ?>', {
        		attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors' 
        	}).addTo(map);

		setMarkerOptions();
		myMarker = L.marker(lat_lng, markerOptions).addTo(map);
		map.setView(myMarker.getLatLng(), zoomlevel);
	}

	function updatePin() {
		setMarkerOptions();
	        if (myMarker != undefined) {
	              map.removeLayer(myMarker);
                };
		myMarker = L.marker(lat_lng, markerOptions).addTo(map);
		map.setView(myMarker.getLatLng(), zoomlevel);
		updateMap();
	}
	function updateMap() {
		map.setView(myMarker.getLatLng(), zoomlevel);
	}
        function swap_zoom_sample(id) {
            zoomlevel = document.getElementById(id).value;
	    updateMap();
        }
	function providerSelected(value) {
		if (value == "google") {
			document.getElementsByClassName("google-apikey")[0].style.display = "";
			document.getElementsByClassName("osm-urls")[0].style.display = "none";
		} else {
			document.getElementsByClassName("google-apikey")[0].style.display = "none";
			document.getElementsByClassName("osm-urls")[0].style.display = "";
		}
	}
    	function initializeForm() {
		var provider = document.getElementById("geolocation_provider").value;
		providerSelected(provider);
		zoomlevel = <?php echo (int) esc_attr((string) get_option('geolocation_default_zoom')); ?>;
		initializeMap();
	}

	document.addEventListener("DOMContentLoaded", initializeForm());
    </script>
    </form>
<?php
}

?>
