<?php
/*
Plugin Name: Geolocation
Plugin URI: https://wordpress.org/extend/plugins/geolocation/
Description: Displays post geotag information on an embedded map.
Version: 0.7
Author: Yann Michel
Author URI: https://www.yann-michel.de/geolocation
Text Domain: geolocation
License: GPL2
*/


/*  Copyright 2010 Chris Boyd  (email : chris@chrisboyd.net)
              2018-2020 Yann Michel (email : geolocation@yann-michel.de)

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

add_action( 'wp_enqueue_scripts', 'add_my_scripts' ); 
function add_my_scripts () {
    wp_enqueue_script(
        'geolocation', 
        get_template_directory_uri() . '/js/jquery.elementReady.js',
        array('jquery')
    );
}
require_once(GEOLOCATION__PLUGIN_DIR . 'geolocation.settings.php');

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
    if (!get_option('geolocation_google_maps_api_key')) { ?>
        <div class="notice notice-error">
            <p><?php _e('Google Maps API key is missing for', 'geolocation'); ?> <a
                        href="options-general.php?page=geolocation">Geolocation</a>!</p>
        </div>
        <?php
    }
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
{
    echo '<input type="hidden" id="geolocation_nonce" name="geolocation_nonce" value="' .
        wp_create_nonce(plugin_basename(__FILE__)) . '" />';
    echo '
		<label class="screen-reader-text" for="geolocation-address">Geolocation</label>
		<div class="taghint">' . __('Enter your address', 'geolocation') . '</div>
		<input type="text" id="geolocation-address" name="geolocation-address" class="newtag form-input-tip" size="25" autocomplete="off" value="" />
		<input id="geolocation-load" type="button" class="button geolocationadd" value="' . __('Load', 'geolocation') . '" tabindex="3" />
		<input type="hidden" id="geolocation-latitude" name="geolocation-latitude" />
		<input type="hidden" id="geolocation-longitude" name="geolocation-longitude" />
		<div id="geolocation-map" style="border:solid 1px #c6c6c6;width:265px;height:200px;margin-top:5px;"></div>
		<div style="margin:5px 0 0 0;">
			<input id="geolocation-public" name="geolocation-public" type="checkbox" value="1" />
			<label for="geolocation-public">' . __('Public', 'geolocation') . '</label>
			<div style="float:right">
				<input id="geolocation-enabled" name="geolocation-on" type="radio" value="1" />
				<label for="geolocation-enabled">' . __('On', 'geolocation') . '</label>
				<input id="geolocation-disabled" name="geolocation-on" type="radio" value="0" />
				<label for="geolocation-disabled">' . __('Off', 'geolocation') . '</label>
			</div>
		</div>
	';
}

/* Prints the edit form for pre-WordPress 2.5 post/page */
function geolocation_old_custom_box()
{
    echo '<div class="dbx-b-ox-wrapper">' . "\n";
    echo '<fieldset id="geolocation_fieldsetid" class="dbx-box">' . "\n";
    echo '<div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">' .
        __('Geolocation', 'geolocation') . "</h3></div>";

    echo '<div class="dbx-c-ontent-wrapper"><div class="dbx-content">';

    geolocation_inner_custom_box();

    echo "</div></div></fieldset></div>\n";
}

function geolocation_save_postdata($post_id)
{
    // Check authorization, permissions, autosave, etc
    if ((!wp_verify_nonce($_POST['geolocation_nonce'], plugin_basename(__FILE__))) ||
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
        (('page' == $_POST['post_type']) && (!current_user_can('edit_page', $post_id))) ||
        (!current_user_can('edit_post', $post_id))) {
        return $post_id;
    }

    $latitude = clean_coordinate($_POST['geolocation-latitude']);
    $longitude = clean_coordinate($_POST['geolocation-longitude']);
    $address = reverse_geocode($latitude, $longitude);
    $public = $_POST['geolocation-public'];
    $on = $_POST['geolocation-on'];

    if ((!empty($latitude)) && (!empty($longitude))) {
        update_post_meta($post_id, 'geo_latitude', $latitude);
        update_post_meta($post_id, 'geo_longitude', $longitude);

        if ($address != '') {
            update_post_meta($post_id, 'geo_address', $address);
        }
        if ($on) {
            update_post_meta($post_id, 'geo_enabled', 1);
        } else {
            update_post_meta($post_id, 'geo_enabled', 0);
        }
        if ($public) {
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
    add_action('save_post', 'geolocation_save_postdata');
}

function admin_head()
{
    global $post;
    $post_id = $post->ID;
    $zoom = (int)get_option('geolocation_default_zoom');
    echo '		<script type="text/javascript" src="https://www.google.com/jsapi"></script>
		<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js' . get_google_maps_api_key("?") . '"></script>'; ?>
    <script type="text/javascript">
        var $j = jQuery.noConflict();
        $j(function () {
            $j(document).ready(function () {
                var hasLocation = false;
                var center = new google.maps.LatLng(0.0, 0.0);
                var postLatitude = '<?php echo esc_js((string)get_post_meta($post_id, 'geo_latitude', true)); ?>';
                var postLongitude = '<?php echo esc_js((string)get_post_meta($post_id, 'geo_longitude', true)); ?>';
                var isPublic = '<?php echo esc_js((string)get_post_meta($post_id, 'geo_public', true)); ?>';
                var isGeoEnabled = '<?php echo esc_js((string)get_post_meta($post_id, 'geo_enabled', true)); ?>';

                if (isPublic === '0')
                    $j("#geolocation-public").attr('checked', false);
                else
                    $j("#geolocation-public").attr('checked', true);

                if (isGeoEnabledon === '0')
                    disableGeo();
                else
                    enableGeo();

                if ((postLatitude !== '') && (postLongitude !== '')) {
                    center = new google.maps.LatLng(postLatitude, postLongitude);
                    hasLocation = true;
                    $j("#geolocation-latitude").val(center.lat());
                    $j("#geolocation-longitude").val(center.lng());
                    reverseGeocode(center);
                }

                var myOptions = {
                    'zoom': <?php echo $zoom; ?>,
                    'center': center,
                    'mapTypeId': google.maps.MapTypeId.ROADMAP
                };
                var image = '<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))); ?>';
                var shadow = new google.maps.MarkerImage('<?php echo esc_js(esc_url(plugins_url('img/wp_pin_shadow.png', __FILE__))); ?>',
                    new google.maps.Size(39, 23),
                    new google.maps.Point(0, 0),
                    new google.maps.Point(12, 25));

                var map = new google.maps.Map(document.getElementById('geolocation-map'), myOptions);
                var marker = new google.maps.Marker({
                    position: center,
                    map: map,
                    title: 'Post Location'<?php if ((bool)get_option('geolocation_wp_pin')) { ?>,
                    icon: image,
                    shadow: shadow
                    <?php } ?>
                });

                if ((!hasLocation) && (google.loader.ClientLocation)) {
                    center = new google.maps.LatLng(google.loader.ClientLocation.latitude, google.loader.ClientLocation.longitude);
                    reverseGeocode(center);
                } else if (!hasLocation) {
                    map.setZoom(1);
                }

                google.maps.event.addListener(map, 'click', function (event) {
                    placeMarker(event.latLng);
                });

                var currentAddress;
                var customAddress = false;
                $j("#geolocation-address").click(function () {
                    currentAddress = $j(this).val();
                    if (currentAddress !== '')
                        $j("#geolocation-address").val('');
                });

                $j("#geolocation-load").click(function () {
                    if ($j("#geolocation-address").val() !== '') {
                        customAddress = true;
                        currentAddress = $j("#geolocation-address").val();
                        geocode(currentAddress);
                    }
                });

                $j("#geolocation-address").keyup(function (e) {
                    if (e.keyCode === 13)
                        $j("#geolocation-load").click();
                });

                $j("#geolocation-enabled").click(function () {
                    enableGeo();
                });

                $j("#geolocation-disabled").click(function () {
                    disableGeo();
                });

                function placeMarker(location) {
                    marker.setPosition(location);
                    map.setCenter(location);
                    if ((location.lat() !== '') && (location.lng() !== '')) {
                        $j("#geolocation-latitude").val(location.lat());
                        $j("#geolocation-longitude").val(location.lng());
                    }

                    if (!customAddress)
                        reverseGeocode(location);
                }

                function geocode(address) {
                    var geocoder = new google.maps.Geocoder();
                    if (geocoder) {
                        geocoder.geocode({"address": address}, function (results, status) {
                            if (status === google.maps.GeocoderStatus.OK) {
                                placeMarker(results[0].geometry.location);
                                if (!hasLocation) {
                                    map.setZoom(16);
                                    hasLocation = true;
                                }
                            }
                        });
                    }
                    $j("#geodata").html(latitude + ', ' + longitude);
                }

                function reverseGeocode(location) {
                    var geocoder = new google.maps.Geocoder();
                    if (geocoder) {
                        geocoder.geocode({"latLng": location}, function (results, status) {
                            if (status === google.maps.GeocoderStatus.OK) {
                                if (results[1]) {
                                    var address = results[1].formatted_address;
                                    if (address === "") {
                                        address = results[7].formatted_address;
                                    } else {
                                        $j("#geolocation-address").val(address);
                                        placeMarker(location);
                                    }
                                }
                            }
                        });
                    }
                }

                function enableGeo() {
                    $j("#geolocation-address").removeAttr('disabled');
                    $j("#geolocation-load").removeAttr('disabled');
                    $j("#geolocation-map").css('filter', '');
                    $j("#geolocation-map").css('opacity', '');
                    $j("#geolocation-map").css('-moz-opacity', '');
                    $j("#geolocation-public").removeAttr('disabled');
                    $j("#geolocation-map").removeAttr('readonly');
                    $j("#geolocation-disabled").removeAttr('checked');
                    $j("#geolocation-enabled").attr('checked', 'checked');

                    if (isPublic === '1')
                        $j("#geolocation-public").attr('checked', 'checked');
                }

                function disableGeo() {
                    $j("#geolocation-address").attr('disabled', 'disabled');
                    $j("#geolocation-load").attr('disabled', 'disabled');
                    $j("#geolocation-map").css('filter', 'alpha(opacity=50)');
                    $j("#geolocation-map").css('opacity', '0.5');
                    $j("#geolocation-map").css('-moz-opacity', '0.5');
                    $j("#geolocation-map").attr('readonly', 'readonly');
                    $j("#geolocation-public").attr('disabled', 'disabled');

                    $j("#geolocation-enabled").removeAttr('checked');
                    $j("#geolocation-disabled").attr('checked', 'checked');

                    if (isPublic === '1')
                        $j("#geolocation-public").attr('checked', 'checked');
                }
            });
        });
    </script>
    <?php
}

function get_geo_div()
{
    $width = esc_attr((string)get_option('geolocation_map_width'));
    $height = esc_attr((string)get_option('geolocation_map_height'));
    return '<div id="map" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
}

function add_geo_div()
{
    if ((esc_attr((string)get_option('geolocation_map_display')) <> 'plain')) {
        echo get_geo_div();
    }
}

function add_geo_support()
{
    global $posts;
    if ((esc_attr((string)get_option('geolocation_map_display')) <> 'plain') || (is_user_logged_in())) {

        // To do: add support for multiple Map API providers
        switch (get_option('geolocation_provider')) {
            case 'google':
                add_google_maps($posts);
                break;
            case 'osm':
                add_osm_maps($posts);
                break;
        }

    }
    echo '<link type="text/css" rel="stylesheet" href="' . esc_url(plugins_url('style.css', __FILE__)) . '" />';
}

function add_google_maps($posts)
{
    default_settings();
    $zoom = (int)get_option('geolocation_default_zoom');
    global $post_count;
    $post_count = count($posts);
    echo '<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js' . get_google_maps_api_key("?") . '"></script>
	<script type="text/javascript">
		var $j = jQuery.noConflict();
		$j(function(){
			var center = new google.maps.LatLng(0.0, 0.0);
			var myOptions = {
		      zoom: ' . $zoom . ',
		      center: center,
		      mapTypeId: google.maps.MapTypeId.ROADMAP
		    }
		    var map = new google.maps.Map(document.getElementById("map"), myOptions);
		    var image = "' . esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))) . '";
		    var shadow = new google.maps.MarkerImage("' . plugins_url('img/wp_pin_shadow.png', __FILE__) . '",
		    	new google.maps.Size(39, 23),
				new google.maps.Point(0, 0),
				new google.maps.Point(12, 25));
		    var marker = new google.maps.Marker({
					position: center, 
					map: map, 
					title:"Post Location"';
    if ((bool)get_option('geolocation_wp_pin')) {
        echo ',
					icon: image,
					shadow: shadow';
    }
    echo '});
			
			var allowDisappear = true;
			var cancelDisappear = false;
		    
			$j(".geolocation-link").mouseover(function(){
				$j("#map").stop(true, true);
				var lat = $j(this).attr("name").split(",")[0];
				var lng = $j(this).attr("name").split(",")[1];
				var latlng = new google.maps.LatLng(lat, lng);
				placeMarker(latlng);
				
				var offset = $j(this).offset();
				$j("#map").fadeTo(250, 1);
				$j("#map").css("z-index", "99");
				$j("#map").css("visibility", "visible");
				$j("#map").css("top", offset.top + 20);
				$j("#map").css("left", offset.left);
				
				allowDisappear = false;
				$j("#map").css("visibility", "visible");
			});
			
			$j(".geolocation-link").mouseover(function(){
			});
			
			$j(".geolocation-link").mouseout(function(){
				allowDisappear = true;
				cancelDisappear = false;
				setTimeout(function() {
					if((allowDisappear) && (!cancelDisappear))
					{
						$j("#map").fadeTo(500, 0, function() {
							$j("#map").css("z-index", "-1");
							allowDisappear = true;
							cancelDisappear = false;
						});
					}
			    },800);
			});
			
			$j("#map").mouseover(function(){
				allowDisappear = false;
				cancelDisappear = true;
				$j("#map").css("visibility", "visible");
			});
			
			$j("#map").mouseout(function(){
				allowDisappear = true;
				cancelDisappear = false;
				$j(".geolocation-link").mouseout();
			});
			
			function placeMarker(location) {
				map.setZoom(' . $zoom . ');
				marker.setPosition(location);
				map.setCenter(location);
			}
			
			google.maps.event.addListener(map, "center_changed", function() {
          			// 5 seconds after the center of the map has changed, pan back to the
          			// marker.
          			window.setTimeout(function() {
          			  map.panTo(marker.getPosition());
          			}, 5000);
        		});
			google.maps.event.addListener(map, "click", function() {
				window.location = "https://maps.google.com/maps?q=" + map.center.lat() + ",+" + map.center.lng();
			});
		});
	</script>';
}

function add_osm_maps($posts)
{
    default_settings();
    global $post_count;
    $post_count = count($posts);

    echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css"/>';
    //echo '<script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js" integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og==" crossorigin=""></script>';
}

function geo_has_shortcode($content)
{
    $pos = strpos($content, get_option('geolocation_shortcode'));
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
function display_location_page_osm($content)
{
    global $post;
    $html = '';
    settype($html, "string");
    $script = '';
    settype($script, "string");
    settype($category, "string");
    $category = (string)get_post_meta($post->ID, 'category', true);
    $category_id = get_cat_ID($category);
    $counter = 0;

    $pargs = array(
        'post_type' => 'post',
        'cat' => $category_id,
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
            ),
            array(
                'key' => 'geo_public',
                'value' => '1',
                'compare' => '='
            )
        )
    );
    $zoom = (int)get_option('geolocation_default_zoom');
    $script = $script . "<script src=\"https://unpkg.com/leaflet@1.5.1/dist/leaflet.js\"></script>";
    $script = $script . "<script type=\"text/javascript\">
        var mymap = L.map('mapid').setView([51.505, -0.09], ".$zoom.");
        var myMapBounds = [];
        L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', { 
     attribution: '&copy; <a href=\"http://osm.org/copyright\">OpenStreetMap</a> contributors' 
    }).addTo(mymap);";

    $post_query = new WP_Query($pargs);
    while ($post_query->have_posts()) {
        $post_query->the_post();
        $postTitle = get_the_title();
        $post_id = (integer)get_the_ID();
        $postLatitude = (string)get_post_meta($post_id, 'geo_latitude', true);
        $postLongitude = (string)get_post_meta($post_id, 'geo_longitude', true);
        $script = $script . "
        var lat_lng = [" . $postLatitude . "," . $postLongitude . "];
        L.marker(lat_lng).addTo(mymap).bindPopup('<a href=\"" . get_permalink($post_id) . "\">" . $postTitle . "</a>');
        myMapBounds.push(lat_lng);";
        $counter = $counter + 1;
    }
    $script = $script . "
        mymap.fitBounds(myMapBounds);
</script>";

    if ($counter > 0) {
        $width = esc_attr((string)get_option('geolocation_map_width_page'));
        $height = esc_attr((string)get_option('geolocation_map_height_page'));
        $html = $html . '<div id="mapid" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
        $html = $html . $script;
    }
    $content = str_replace((string) get_option('geolocation_shortcode'), $html, $content);
    return $content;
}

function display_location_page_google($content)
{
    global $post;
    $html = '';
    settype($html, "string");
    $script = '';
    settype($script, "string");
    settype($category, "string");
    $category = (string)get_post_meta($post->ID, 'category', true);
    $category_id = get_cat_ID($category);
    $counter = 0;

    $pargs = array(
        'post_type' => 'post',
        'cat' => $category_id,
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
            ),
            array(
                'key' => 'geo_public',
                'value' => '1',
                'compare' => '='
            )
        )
    );

    $script = $script . "<script type=\"text/javascript\" src=\"//maps.googleapis.com/maps/api/js'" . get_google_maps_api_key("?") . "'\"></script>
<script type=\"text/javascript\">
      var map = new google.maps.Map(
        document.getElementById('mymap'), {
          mapTypeId: google.maps.MapTypeId.ROADMAP
        }
      );
      var bounds = new google.maps.LatLngBounds();";

    $post_query = new WP_Query($pargs);
    while ($post_query->have_posts()) {
        $post_query->the_post();
        $post_id = (integer)get_the_ID();
        $postLatitude = (string)get_post_meta($post_id, 'geo_latitude', true);
        $postLongitude = (string)get_post_meta($post_id, 'geo_longitude', true);
        $script = $script . "
      marker = new google.maps.Marker({
            position: new google.maps.LatLng(" . $postLatitude . "," . $postLongitude . "),
            map: map
      });
      bounds.extend(marker.position);";
        $counter = $counter + 1;
    }
    $script = $script . "
       map.fitBounds(bounds);
</script>";

    if ($counter > 0) {
        $width = esc_attr((string)get_option('geolocation_map_width_page'));
        $height = esc_attr((string)get_option('geolocation_map_height_page'));
        $html = $html . '<div id="mymap" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
        $html = $html . $script;
    }
    $content = str_replace((string) get_option('geolocation_shortcode'), $html, $content);
    return $content;
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
    $on = (bool)get_post_meta($post->ID, 'geo_enabled', true);
    $public = (bool)get_post_meta($post->ID, 'geo_public', true);

    if (((empty($latitude)) || (empty($longitude))) ||
        ($on === '' || $on === false) ||
        ($public === '' || $public === false)) {
        $content = str_replace($shortcode, '', $content);
        return $content;
    }

    $address = (string)get_post_meta($post->ID, 'geo_address', true);
    if (empty($address)) {
        $address = reverse_geocode($latitude, $longitude);
    }

    switch (esc_attr((string)get_option('geolocation_map_display'))) {
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
            $html = '<pre> $latitude: ' . $latitude . '<br> $longitude: ' . $longitude . '<br> $address: ' . $address . '<br> $on: ' . (string)$on . '<br> $public: ' . (string)$public . '</pre>';
            break;
    }

    switch (esc_attr((string)get_option('geolocation_map_position'))) {
        case 'before':
            $content = str_replace($shortcode, '', $content);
            $content = $html . '<br/><br/>' . $content;
            break;
        case 'after':
            $content = str_replace($shortcode, '', $content);
            $content = $content . '<br/><br/>' . $html;
            break;
        case 'shortcode':
            $content = str_replace($shortcode, $html, $content);
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
            $post_id = (integer)get_the_ID();
            $postLatitude = get_post_meta($post_id, 'geo_latitude', true);
            $postLongitude = get_post_meta($post_id, 'geo_longitude', true);
            $postAddressNew = (string)reverse_geocode($postLatitude, $postLongitude);
            update_post_meta($post_id, 'geo_address', $postAddressNew);
            $counter = $counter + 1;
        }
        echo '<div class="notice notice-success is-dismissible"><p>' . __($counter . ' Addresses have been updated!', 'geolocation') . '</p></div>';
    }
}


function pullGoogleJSON($latitude, $longitude)
{
    $url = "https://maps.googleapis.com/maps/api/geocode/json" . get_google_maps_api_key("?") . "&language=" . getSiteLang() . "&latlng=" . $latitude . "," . $longitude;
    $decoded = json_decode(wp_remote_get($url)['body']);
    return $decoded;
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
    $json = pullGoogleJSON($latitude, $longitude);
    $city = '';
    $state = '';
    $country = '';
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
    return buildAddress($city, $state, $country);
}

function clean_coordinate($coordinate)
{
    $pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
    preg_match($pattern, $coordinate, $matches);
    return $matches[0];
}

function get_google_maps_api_key($sep)
{
    $apikey = (string)get_option('geolocation_google_maps_api_key');
    if ($apikey != "") {
        return $sep . 'key=' . $apikey;
    }
    return '';
}

function is_checked($field)
{
    if ((bool)get_option($field)) {
        echo ' checked="checked" ';
    }
}

function is_value($field, $value)
{
    if ((string)get_option($field) == $value) {
        echo ' checked="checked" ';
    }
}

?>
