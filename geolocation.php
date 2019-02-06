<?php
/*
Plugin Name: Geolocation
Plugin URI: https://wordpress.org/extend/plugins/geolocation/
Description: Displays post geotag information on an embedded map.
Version: 0.4.2
Author: Yann Michel
Author URI: https://www.yann-michel.de/geolocation
Text Domain: geolocation
License: GPL2
*/

/*  Copyright 2010 Chris Boyd  (email : chris@chrisboyd.net)
              2018 Yann Michel (email : geolocation@yann-michel.de)

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

add_action('upgrader_process_complete', 'plugin_upgrade_completed', 10, 2);
add_action('plugins_loaded', 'languages_init');
add_action('wp_head', 'add_geo_support');
add_action('wp_footer', 'add_geo_div');
add_action('admin_menu', 'add_settings');
add_filter('the_content', 'display_location', 5);

admin_init();
register_activation_hook(__FILE__, 'activate');
wp_enqueue_script("jquery");

define('PROVIDER', 'google');
define('SHORTCODE', '[geolocation]');

function languages_init() {
    $plugin_rel_path = basename(dirname(__FILE__)).'/languages/'; /* Relative to WP_PLUGIN_DIR */
    load_plugin_textdomain('geolocation', 'false', $plugin_rel_path);
}

function activate() {
    register_settings();
    add_option('geolocation_map_width', '350');
    add_option('geolocation_map_height', '150');
    add_option('geolocation_default_zoom', '16');
    add_option('geolocation_map_position', 'after');
    add_option('geolocation_map_display', 'link');
    add_option('geolocation_wp_pin', '1');
}

function plugin_upgrade_completed($upgrader_object, $options) {
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
function geolocation_custom_admin_notice() {
    if (!get_option('geolocation_google_maps_api_key')) { ?>
	<div class="notice notice-error">
		<p><?php _e('Google Maps API key is missing for', 'geolocation'); ?> <a href="options-general.php?page=geolocation">Geolocation</a>!</p>
	</div>
	
<?php 
    }
}
add_action('admin_notices', 'geolocation_custom_admin_notice');

function geolocation_add_custom_box() {
        if (function_exists('add_meta_box')) {
            add_meta_box('geolocation_sectionid', __('Geolocation', 'geolocation'), 'geolocation_inner_custom_box', 'post', 'advanced');
        } else {
            add_action('dbx_post_advanced', 'geolocation_old_custom_box');
        }
}

function geolocation_inner_custom_box() {
    echo '<input type="hidden" id="geolocation_nonce" name="geolocation_nonce" value="'. 
    wp_create_nonce(plugin_basename(__FILE__)).'" />';
    echo '
		<label class="screen-reader-text" for="geolocation-address">Geolocation</label>
		<div class="taghint">'.__('Enter your address', 'geolocation').'</div>
		<input type="text" id="geolocation-address" name="geolocation-address" class="newtag form-input-tip" size="25" autocomplete="off" value="" />
		<input id="geolocation-load" type="button" class="button geolocationadd" value="'.__('Load', 'geolocation').'" tabindex="3" />
		<input type="text" id="geolocation-latitude" name="geolocation-latitude" />
		<input type="text" id="geolocation-longitude" name="geolocation-longitude" />
		<div id="geolocation-map" style="border:solid 1px #c6c6c6;width:265px;height:200px;margin-top:5px;"></div>
		<div style="margin:5px 0 0 0;">
			<input id="geolocation-public" name="geolocation-public" type="checkbox" value="1" />
			<label for="geolocation-public">'.__('Public', 'geolocation').'</label>
			<div style="float:right">
				<input id="geolocation-enabled" name="geolocation-on" type="radio" value="1" />
				<label for="geolocation-enabled">'.__('On', 'geolocation').'</label>
				<input id="geolocation-disabled" name="geolocation-on" type="radio" value="0" />
				<label for="geolocation-disabled">'.__('Off', 'geolocation').'</label>
			</div>
		</div>
	';
}

/* Prints the edit form for pre-WordPress 2.5 post/page */
function geolocation_old_custom_box() {
    echo '<div class="dbx-b-ox-wrapper">'."\n";
    echo '<fieldset id="geolocation_fieldsetid" class="dbx-box">'."\n";
    echo '<div class="dbx-h-andle-wrapper"><h3 class="dbx-handle">'. 
        __('Geolocation', 'geolocation')."</h3></div>";   
   
    echo '<div class="dbx-c-ontent-wrapper"><div class="dbx-content">';

    geolocation_inner_custom_box();

    echo "</div></div></fieldset></div>\n";
}

function geolocation_save_postdata($post_id) {
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

function admin_init() {
    add_action('admin_head-post-new.php', 'admin_head');
    add_action('admin_head-post.php', 'admin_head');
    add_action('admin_menu', 'geolocation_add_custom_box');
    add_action('save_post', 'geolocation_save_postdata');
}

function admin_head() {
    global $post;
    $post_id = $post->ID;
    $zoom = (int) get_option('geolocation_default_zoom');
    echo '		<script type="text/javascript" src="https://www.google.com/jsapi"></script>
		<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js'.get_google_maps_api_key("?").'"></script>'; ?>
		<script type="text/javascript">
		 	var $j = jQuery.noConflict();
			$j(function() {
				$j(document).ready(function() {
				    var hasLocation = false;
					var center = new google.maps.LatLng(0.0,0.0);
					var postLatitude =  '<?php echo esc_js((string) get_post_meta($post_id, 'geo_latitude', true)); ?>';
					var postLongitude =  '<?php echo esc_js((string) get_post_meta($post_id, 'geo_longitude', true)); ?>';
					var public = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_public', true)); ?>';
					var on = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_enabled', true)); ?>';
					
					if(public == '0')
						$j("#geolocation-public").attr('checked', false);
					else
						$j("#geolocation-public").attr('checked', true);
					
					if(on == '0')
						disableGeo();
					else
						enableGeo();
					
					if((postLatitude != '') && (postLongitude != '')) {
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
						title:'Post Location'<?php if ((bool) get_option('geolocation_wp_pin')) { ?>,
						icon: image,
						shadow: shadow
					<?php } ?>
					});
					
					if((!hasLocation) && (google.loader.ClientLocation)) {
				      center = new google.maps.LatLng(google.loader.ClientLocation.latitude, google.loader.ClientLocation.longitude);
				      reverseGeocode(center);
				    }
				    else if(!hasLocation) {
				    	map.setZoom(1);
				    }
					
					google.maps.event.addListener(map, 'click', function(event) {
						placeMarker(event.latLng);
					});
					
					var currentAddress;
					var customAddress = false;
					$j("#geolocation-address").click(function(){
						currentAddress = $j(this).val();
						if(currentAddress != '')
							$j("#geolocation-address").val('');
					});
					
					$j("#geolocation-load").click(function(){
						if($j("#geolocation-address").val() != '') {
							customAddress = true;
							currentAddress = $j("#geolocation-address").val();
							geocode(currentAddress);
						}
					});
					
					$j("#geolocation-address").keyup(function(e) {
						if(e.keyCode == 13)
							$j("#geolocation-load").click();
					});
					
					$j("#geolocation-enabled").click(function(){
						enableGeo();
					});
					
					$j("#geolocation-disabled").click(function(){
						disableGeo();
					});
									
					function placeMarker(location) {
						marker.setPosition(location);
						map.setCenter(location);
						if((location.lat() != '') && (location.lng() != '')) {
							$j("#geolocation-latitude").val(location.lat());
							$j("#geolocation-longitude").val(location.lng());
						}
						
						if(!customAddress)
							reverseGeocode(location);
					}
					
					function geocode(address) {
						var geocoder = new google.maps.Geocoder();
					    if (geocoder) {
							geocoder.geocode({"address": address}, function(results, status) {
								if (status == google.maps.GeocoderStatus.OK) {
									placeMarker(results[0].geometry.location);
									if(!hasLocation) {
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
							geocoder.geocode({"latLng": location}, function(results, status) {
							if (status == google.maps.GeocoderStatus.OK) {
							  if(results[1]) {
							  	var address = results[1].formatted_address;
							  	if(address == "")
							  		address = results[7].formatted_address;
							  	else {
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
						
						if(public == '1')
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
						
						if(public == '1')
							$j("#geolocation-public").attr('checked', 'checked');
					}
				});
			});
		</script>
<?php		
}

function get_geo_div() {
    $width = esc_attr((string) get_option('geolocation_map_width'));
    $height = esc_attr((string) get_option('geolocation_map_height'));
    return '<div id="map" class="geolocation-map" style="width:'.$width.'px;height:'.$height.'px;"></div>';
}

function add_geo_div() {
    if ((esc_attr((string) get_option('geolocation_map_display')) <> 'plain') ) {
        echo get_geo_div();
    }
}

function add_geo_support() {
    global $geolocation_options, $posts;
    if ((esc_attr((string) get_option('geolocation_map_display')) <> 'plain') || (is_admin())) {
	
       // To do: add support for multiple Map API providers
       switch (PROVIDER) {
           case 'google':
               add_google_maps($posts);
               break;
       }

    }
    echo '<link type="text/css" rel="stylesheet" href="'.esc_url(plugins_url('style.css', __FILE__)).'" />';
}

function add_google_maps($posts) {
    default_settings();
    $zoom = (int) get_option('geolocation_default_zoom');
    global $post_count;
    $post_count = count($posts);
    echo '<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js'.get_google_maps_api_key("?").'"></script>
	<script type="text/javascript">
		var $j = jQuery.noConflict();
		$j(function(){
			var center = new google.maps.LatLng(0.0, 0.0);
			var myOptions = {
		      zoom: '.$zoom.',
		      center: center,
		      mapTypeId: google.maps.MapTypeId.ROADMAP
		    };
		    var map = new google.maps.Map(document.getElementById("map"), myOptions);
		    var image = "'.esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))).'";
		    var shadow = new google.maps.MarkerImage("'.plugins_url('img/wp_pin_shadow.png', __FILE__).'",
		    	new google.maps.Size(39, 23),
				new google.maps.Point(0, 0),
				new google.maps.Point(12, 25));
		    var marker = new google.maps.Marker({
					position: center, 
					map: map, 
					title:"Post Location"';
                if ((bool) get_option('geolocation_wp_pin')) {
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
				map.setZoom('.$zoom.');
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

function geo_has_shortcode($content) {
    $pos = strpos($content, SHORTCODE);
    if ($pos === false) {
            return false;
    } else {
            return true;
    }
}

function display_location($content) {
    default_settings();
    global $post;
    $html = ''; 
    settype($html, "string");

    $latitude = clean_coordinate(get_post_meta($post->ID, 'geo_latitude', true));
    $longitude = clean_coordinate(get_post_meta($post->ID, 'geo_longitude', true));
    $on = (bool) get_post_meta($post->ID, 'geo_enabled', true);
    $public = (bool) get_post_meta($post->ID, 'geo_public', true);

    if (((empty($latitude)) || (empty($longitude))) ||
        ($on === '' || $on === false) ||
        ($public === '' || $public === false)) {
        $content = str_replace(SHORTCODE, '', $content);
        return $content;
    }
    
    $address = (string) get_post_meta($post->ID, 'geo_address', true);
    if (empty($address)) {
        $address = reverse_geocode($latitude, $longitude);
    }
    
    switch (esc_attr((string) get_option('geolocation_map_display')))
    {
        case 'plain':
                $html = '<div class="geolocation-plain" id="geolocation'.$post->ID.'">'.__('Posted from ', 'geolocation').esc_html($address).'.</div>';
            break;
        case 'link':
                $html = '<a class="geolocation-link" href="#" id="geolocation'.$post->ID.'" name="'.$latitude.','.$longitude.'" onclick="return false;">'.__('Posted from ', 'geolocation').esc_html($address).'.</a>';
            break;
        case 'full':
            $html = get_geo_div();
            break;
        case 'debug':
            $html = '<pre> $latitude: '.$latitude.'<br> $longitude: '.$longitude.'<br> $address: '.$address.'<br> $on: '.(string) $on.'<br> $public: '.(string) $public.'</pre>';
            break;
    }
        
    switch (esc_attr((string) get_option('geolocation_map_position')))
    {
        case 'before':
            $content = str_replace(SHORTCODE, '', $content);
            $content = $html.'<br/><br/>'.$content;
            break;
        case 'after':
            $content = str_replace(SHORTCODE, '', $content);
            $content = $content.'<br/><br/>'.$html;
            break;
        case 'shortcode':
            $content = str_replace(SHORTCODE, $html, $content);
            break;
    }

    return $content;
}

function pullGoogleJSON($latitude, $longitude) {
    $url = "https://maps.googleapis.com/maps/api/geocode/json".get_google_maps_api_key("?")."&latlng=".$latitude.",".$longitude;
    $result = wp_remote_get($url);
    return json_decode($result['body']);
}

function buildAddress($city, $state, $country) {
    $address = '';
    if (($city != '') && ($state != '') && ($country != '')) {
            $address = $city.', '.$state.', '.$country;
    } else if (($city != '') && ($state != '')) {
            $address = $city.', '.$state;
    } else if (($state != '') && ($country != '')) {
            $address = $state.', '.$country;
    } else if ($country != '') {
            $address = $country;
    }
    return esc_html($address);
}

function reverse_geocode($latitude, $longitude) {
    $json = pullGoogleJSON($latitude, $longitude);
    $city = '';
    $state = '';
    $country = '';
    foreach ($json->results as $result)
    {
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

function clean_coordinate($coordinate) {
    $pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
    preg_match($pattern, $coordinate, $matches);
    return $matches[0];
}

function add_settings() {
    if (is_admin()) { // admin actions
        add_options_page(__('Geolocation Plugin Settings', 'geolocation'), 'Geolocation', 'administrator', 'geolocation.php', 'geolocation_settings_page');
            add_action('admin_init', 'register_settings');
    } else {
        // non-admin enqueues, actions, and filters
    }
}

function register_settings() {
    register_setting('geolocation-settings-group', 'geolocation_map_width');
    register_setting('geolocation-settings-group', 'geolocation_map_height');
    register_setting('geolocation-settings-group', 'geolocation_default_zoom');
    register_setting('geolocation-settings-group', 'geolocation_map_position');
    register_setting('geolocation-settings-group', 'geolocation_map_display');
    register_setting('geolocation-settings-group', 'geolocation_wp_pin');
    register_setting('geolocation-settings-group', 'geolocation_google_maps_api_key');
}

function get_google_maps_api_key($sep) {
    $apikey = (string) get_option('geolocation_google_maps_api_key');
    if ($apikey != "") {
        return $sep.'key='.$apikey;
    }
    return '';
}

function is_checked($field) {
    if ((bool) get_option($field)) {
                echo ' checked="checked" ';
    }
}

function is_value($field, $value) {
    if ((string) get_option($field) == $value) {
                echo ' checked="checked" ';
    }
}

function default_settings() {
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
}

function geolocation_settings_page() {
    default_settings();
    $zoomImage = (string) get_option('geolocation_default_zoom');
    if ((bool) get_option('geolocation_wp_pin')) {
            $zoomImage = 'wp_'.$zoomImage.'.png';
    } else {
            $zoomImage = $zoomImage.'.png';
    }
    ?>
	<style type="text/css">
		#zoom_level_sample { background: url('<?php echo esc_url(plugins_url('img/zoom/'.$zoomImage, __FILE__)); ?>'); width:390px; height:190px; border: solid 1px #999; }
		#preload { display: none; }
		.dimensions strong { width: 50px; float: left; }
		.dimensions input { width: 50px; margin-right: 5px; }
		.zoom label { width: 50px; margin: 0 5px 0 2px; }
		.position label { margin: 0 5px 0 2px; }
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
			if(document.getElementById('geolocation_wp_pin').checked)
				file = path + 'wp_' + zoomlevel + '.png';
			div.style.background = 'url(' + file + ')';
		}
	</script>
	<div class="wrap"><h2><?php _e('Geolocation Plugin Settings', 'geolocation'); ?></h2></div>
	
	<form method="post" action="options.php">
    <?php settings_fields('geolocation-settings-group'); ?>
    <table class="form-table">
        <tr valign="top">
	        <tr valign="top">
	        <th scope="row"><?php _e('Dimensions', 'geolocation'); ?></th>
	        <td class="dimensions">
	        	<strong><?php _e('Width', 'geolocation'); ?>:</strong><input type="text" name="geolocation_map_width" value="<?php echo esc_attr((string) get_option('geolocation_map_width')); ?>" />px<br/>
	        	<strong><?php _e('Height', 'geolocation'); ?>:</strong><input type="text" name="geolocation_map_height" value="<?php echo esc_attr((string) get_option('geolocation_map_height')); ?>" />px
	        </td>
        </tr>
        <tr valign="top">
        	<th scope="row"><?php _e('Position', 'geolocation'); ?></th>
        	<td class="position">
				<input type="radio" id="geolocation_map_position_before" name="geolocation_map_position" value="before"<?php is_value('geolocation_map_position', 'before'); ?>><label for="geolocation_map_position_before"><?php _e('Before the post.', 'geolocation'); ?></label><br/>
				<input type="radio" id="geolocation_map_position_after" name="geolocation_map_position" value="after"<?php is_value('geolocation_map_position', 'after'); ?>><label for="geolocation_map_position_after"><?php _e('After the post.', 'geolocation'); ?></label><br/>
				<input type="radio" id="geolocation_map_position_shortcode" name="geolocation_map_position" value="shortcode"<?php is_value('geolocation_map_position', 'shortcode'); ?>><label for="geolocation_map_position_shortcode"><?php _e('Wherever I put the <strong>[geolocation]</strong> shortcode.', 'geolocation'); ?></label>
	        </td>
        </tr>
        <tr valign="top">
	        <th scope="row"><?php _e('How would you like your maps to be displayed?', 'geolocation'); ?></th>
                <td class="display">
                                <input type="radio" id="geolocation_map_display_plain" name="geolocation_map_display" value="plain"<?php is_value('geolocation_map_display', 'plain'); ?>>
                <label for="geolocation_map_display_plain"><?php _e('Plain text.', 'geolocation'); ?></label><br/>
                                <input type="radio" id="geolocation_map_display_link" name="geolocation_map_display" value="link"<?php is_value('geolocation_map_display', 'link'); ?>>
                <label for="geolocation_map_display_link"><?php _e('Simple link w/hover.', 'geolocation'); ?></label><br/>
                                <input type="radio" id="geolocation_map_display_debug" name="geolocation_map_display" value="debug"<?php is_value('geolocation_map_display', 'debug'); ?>>
                <label for="geolocation_map_display_debug"><?php _e('Debug.', 'geolocation'); ?></label><br/>
                </td>        
        </tr>                        
        <tr valign="top">
	        <th scope="row"><?php _e('Default Zoom Level', 'geolocation'); ?></th>
	        <td class="zoom">        	
				<input type="radio" id="geolocation_default_zoom_globe" name="geolocation_default_zoom" value="1"<?php is_value('geolocation_default_zoom', '1'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_globe"><?php _e('Globe', 'geolocation'); ?></label>
				
				<input type="radio" id="geolocation_default_zoom_country" name="geolocation_default_zoom" value="3"<?php is_value('geolocation_default_zoom', '3'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_country"><?php _e('Country', 'geolocation'); ?></label>
				<input type="radio" id="geolocation_default_zoom_state" name="geolocation_default_zoom" value="6"<?php is_value('geolocation_default_zoom', '6'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_state"><?php _e('State', 'geolocation'); ?></label>
				<input type="radio" id="geolocation_default_zoom_city" name="geolocation_default_zoom" value="9"<?php is_value('geolocation_default_zoom', '9'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_city"><?php _e('City', 'geolocation'); ?></label>
				<input type="radio" id="geolocation_default_zoom_street" name="geolocation_default_zoom" value="16"<?php is_value('geolocation_default_zoom', '16'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_street"><?php _e('Street', 'geolocation'); ?></label>
				<input type="radio" id="geolocation_default_zoom_block" name="geolocation_default_zoom" value="18"<?php is_value('geolocation_default_zoom', '18'); ?> onclick="javascipt:swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_block"><?php _e('Block', 'geolocation'); ?></label>
				<br/>
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
        	<th scope="row">Google Maps API key</th>
        	<td class="apikey">        	
	        	<input type="text" name="geolocation_google_maps_api_key" value="<?php echo esc_attr((string) get_option('geolocation_google_maps_api_key')); ?>" />
        </tr>
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'geolocation') ?>" />
    </p>
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="geolocation_map_width,geolocation_map_height,geolocation_default_zoom,geolocation_map_position,geolocation_wp_pin" />
</form>
	<div id="preload">
		<img src="<?php echo esc_url(plugins_url('img/zoom/1.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/3.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/6.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/9.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/16.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/18.png', __FILE__)); ?>"/>
		
		<img src="<?php echo esc_url(plugins_url('img/zoom/wp_1.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/wp_3.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/wp_6.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/wp_9.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/wp_16.png', __FILE__)); ?>"/>
		<img src="<?php echo esc_url(plugins_url('img/zoom/wp_18.png', __FILE__)); ?>"/>
	</div>
	<?php
}

?>
