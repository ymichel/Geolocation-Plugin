<?php

/** This is the provider specific pool for the provider "open streetmaps (osm)". **/
function admin_head_osm()
{
	global $post;
	$post_id = $post->ID;?>
<?php /**
	<link rel="stylesheet" href="<?php echo get_osm_leaflet_css_url(); ?>" />
     <script src="<?php echo get_osm_leaflet_js_url(); ?>"></script>
	*/?>
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI=" crossorigin=""/>
	<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js" integrity="sha256-WBkoXOwTeyKclOHuWtc+i2uENFpDZ9YPdf5Hf+D7ewM=" crossorigin=""></script>
	<script type="text/javascript">
		function ready(fn) {
			if (document.readyState != 'loading') {
				fn();
			} else {
				document.addEventListener('DOMContentLoaded', fn);
			}
		}
		ready(() => {
			let hasLocation = false;
			let postLatitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_latitude', true)); ?>';
			let postLongitude = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_longitude', true)); ?>';
			let isPublic = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_public', true)); ?>';
			var postAddress = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_address', true)); ?>';
			let isGeoEnabled = '<?php echo esc_js((string) get_post_meta($post_id, 'geo_enabled', true)); ?>';
			let zoomlevel = <?php echo (int) esc_attr((string) get_option('geolocation_default_zoom')); ?>;
			let image = '<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))); ?>';
			let iconOptions = {
				iconUrl: image
			};
			let customIcon = L.icon(iconOptions);
			let markerOptions = {
				<?php if ((bool) get_option('geolocation_wp_pin')) { ?>
					icon: customIcon,
				<?php } ?>clickable: false,
				draggable: false
			};
			let myMarker = {};

			if (isPublic === '0') {
				document.getElementById('geolocation-public').setAttribute('checked', false);
			} else {
				document.getElementById('geolocation-public').setAttribute('checked', true);
			}

			if (isGeoEnabled === '0') {
				disableGeo();
			} else {
				enableGeo();
			}

			let map = L.map("geolocation-map").setView([52.5162778, 13.3733267], zoomlevel);
			L.tileLayer('<?php echo get_osm_tiles_url(); ?>', {
				attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
			}).addTo(map);
			myMarker = L.marker([52.5162778, 13.3733267], markerOptions).addTo(map);
			map.setView(myMarker.getLatLng(), map.getZoom());
			if ((postLatitude !== '') && (postLongitude !== '')) {
				myMarker.setLatLng([postLatitude, postLongitude]);
				map.setView(myMarker.getLatLng(), zoomlevel);
				hasLocation = true;
				document.getElementById('geolocation-latitude').value = postLatitude;
				document.getElementById('geolocation-latitude').value = postLongitude;
				if (postAddress !== '') {
					document.getElementById('geolocation-address').value = postAddress;
				} else {
					reverseGeocode(postLatitude, postLongitude);
				}
			}
			let currentAddress;
			let customAddress = false;
			document.getElementById('geolocation-address').addEventListener('click', event => {
				currentAddress = document.getElementById('geolocation-address').value;
				if (currentAddress !== '')
					document.getElementById('geolocation-address').value = '';
			});
			document.getElementById('geolocation-load').addEventListener('click', event => {
				if (document.getElementById('geolocation-address').value !== '') {
					customAddress = true;
					currentAddress = document.getElementById('geolocation-address').value;
					geocode(currentAddress);
				}
			});
			document.getElementById('geolocation-address').addEventListener('keyup', function(e) {
				if (e.key === 'Enter')
					document.getElementById('geolocation-load').click();
			});
			document.getElementById('geolocation-enabled').addEventListener('click', event => {
				enableGeo();
			});
			document.getElementById('geolocation-disabled').addEventListener('click', event => {
				disableGeo();
			});

			function geocode(address) {
				let request = new XMLHttpRequest();
				request.open('GET', '<?php echo get_osm_nominatim_url(); ?>/search?format=json&accept-language=\'<?php echo getSiteLang(); ?>\'&limit=1&q=' + address, true);
				request.onload = function() {
					if (this.status >= 200 && this.status < 400) {
						// Success!
						let data = JSON.parse(this.response);
						document.getElementById('geolocation-latitude').value = data[0].lat;
						document.getElementById('geolocation-longitude').value = data[0].lon;
						lat_lng = [data[0].lat, data[0].lon];
						myMarker.setLatLng(lat_lng);
						map.setView(myMarker.getLatLng(), map.getZoom());
						hasLocation = true;
					} else {
						// error
					}
				};
				request.send();
			}

			function reverseGeocode(lat, lon) {
				let request = new XMLHttpRequest();
				request.open('GET', '<?php echo get_osm_nominatim_url(); ?>/reverse?format=json&accept-language=\'<?php echo getSiteLang(); ?>\'&lat=' + lat + '&lon=' + lon, true);
				request.onload = function() {
					if (this.status >= 200 && this.status < 400) {
						// Success!
						let data = JSON.parse(this.response);
						document.getElementById('geolocation-address').value = data.display_name;
					} else {
						// error
					}
				};
				request.send();
			}

			function enableGeo() {
				document.getElementById('geolocation-address').removeAttribute('disabled');
				document.getElementById('geolocation-load').removeAttribute('disabled');
				document.getElementById('geolocation-map').style.filter = '';
				document.getElementById('geolocation-map').style.opacity = '';
				document.getElementById('geolocation-map').style.MozOpacity = '';
				document.getElementById('geolocation-public').removeAttribute('disabled');
				document.getElementById('geolocation-map').removeAttribute('readonly');
				document.getElementById('geolocation-disabled').removeAttribute('checked');
				document.getElementById('geolocation-enabled').setAttribute('checked', true);
				if (isPublic === '1')
					document.getElementById('geolocation-public').setAttribute('checked', true);
			}

			function disableGeo() {
				document.getElementById('geolocation-address').setAttribute('disabled', 'disabled');
				document.getElementById('geolocation-load').setAttribute('disabled', 'disabled');
				document.getElementById('geolocation-map').style.filter = 'alpha(opacity=50)';
				document.getElementById('geolocation-map').style.opacity = '0.5';
				document.getElementById('geolocation-map').style.MozOpacity = '0.5';
				document.getElementById('geolocation-public').setAttribute('disabled', 'disabled');
				document.getElementById('geolocation-map').setAttribute('readonly', 'readonly');
				document.getElementById('geolocation-enabled').removeAttribute('checked');
				document.getElementById('geolocation-disabled').setAttribute('checked', true);
				if (isPublic === '1')
					document.getElementById('geolocation-public').setAttribute('checked', true);
			}
		})
	</script>
<?php
}

function add_geo_support_osm($posts)
{
	default_settings();
	global $post_count;
	$post_count = count($posts);


	echo '<link rel="stylesheet" href="' . get_osm_leaflet_css_url() . '"/>';
	echo '<script src="' . get_osm_leaflet_js_url() . '"></script>';

	$zoom = (int) get_option('geolocation_default_zoom'); ?>
	<script type="text/javascript">
		function ready(fn) {
			if (document.readyState != 'loading') {
				fn();
			} else {
				document.addEventListener('DOMContentLoaded', fn);
			}
		}
		ready(() => {
			var map = L.map(document.getElementById("map")).setView([51.505, -0.09], <?php echo $zoom; ?>);
			L.tileLayer('<?php echo get_osm_tiles_url(); ?>', {
				attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
			}).addTo(map);

			var allowDisappear = true;
			var cancelDisappear = false;

			var iconOptions = {
				iconUrl: '<?php echo esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))); ?>'
			}
			var customIcon = L.icon(iconOptions);
			var markerOptions = {
				<?php if ((bool) get_option('geolocation_wp_pin')) { ?>
					icon: customIcon,
				<?php           } 	?>
				clickable: false,
				draggable: false
			}
			var geolocationLinks = document.querySelectorAll('.geolocation-link');

			for (var i = 0; i < geolocationLinks.length; i++) {
				geolocationLinks[i].addEventListener('mouseover', function() {
					var lat = this.getAttribute('name').split(',')[0];
					var lng = this.getAttribute('name').split(',')[1];
					var lat_lng = [lat, lng];
					L.marker(lat_lng, markerOptions).addTo(map);
					map.setView(new L.LatLng(lat, lng), <?php echo $zoom; ?>);

					const rect = this.getBoundingClientRect();
					const top = rect.top + window.scrollY + 20;
					const left = rect.left + window.scrollX;

					document.querySelector('#map').style.opacity = 1;
					document.querySelector('#map').style.zIndex = '99';
					document.querySelector('#map').style.visibility = 'visible';
					document.querySelector("#map").style.top = top + "px";
					document.querySelector("#map").style.left = left + "px";

					allowDisappear = false;
				});

				geolocationLinks[i].addEventListener('mouseout', function() {
					allowDisappear = true;
					cancelDisappear = false;
					setTimeout(function() {
						if ((allowDisappear) && (!cancelDisappear)) {
							document.querySelector('#map').style.opacity = 0;
							document.querySelector('#map').style.zIndex = '-1';
							allowDisappear = true;
							cancelDisappear = false;
						}
					}, 800);
				});
			}

			document.querySelector("#map").addEventListener("mouseover", function() {
				allowDisappear = false;
				cancelDisappear = true;
				this.style.visibility = "visible";
			});

			document.querySelector("#map").addEventListener("mouseout", function() {
				allowDisappear = true;
				cancelDisappear = false;
				document.querySelectorAll(".geolocation-link").forEach(el => el.dispatchEvent(new Event("mouseout")));
			});

		});
	</script>
<?php
}

function display_location_page_osm($content)
{
	global $post;
	$html = '';
	settype($html, "string");
	$script = '';
	settype($script, "string");
	settype($category, "string");
	$category = (string) get_post_meta($post->ID, 'category', true);
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
	//$zoom = (int) get_option('geolocation_default_zoom');
	$zoom = 1;
	$script = $script . "<script src=\"" . get_osm_leaflet_js_url() . "\"></script>";
	$script = $script . "<script type=\"text/javascript\">
        var mymap = L.map('mapid').setView([51.505, -0.09], " . $zoom . ");
        var myMapBounds = [];
        var lat_lng = [];
	L.tileLayer('" . get_osm_tiles_url() . "', {
        	attribution: '&copy; <a href=\"http://osm.org/copyright\">OpenStreetMap</a> contributors' 
        }).addTo(mymap);
        var image = '" . esc_js(esc_url(plugins_url('img/wp_pin.png', __FILE__))) . "';
	var iconOptions = {
	       iconUrl: image
	    }
	var customIcon = L.icon(iconOptions);
	var markerOptions = {";
	if ((bool) get_option('geolocation_wp_pin')) {
		$script = $script . "                      icon: customIcon,";
	}
	$script = $script . "	       clickable: false,
	       draggable: false
     	    }";

	$post_query = new WP_Query($pargs);
	while ($post_query->have_posts()) {
		$post_query->the_post();
		$postTitle = get_the_title();
		$post_id = (int) get_the_ID();
		$postLatitude = (string) get_post_meta($post_id, 'geo_latitude', true);
		$postLongitude = (string) get_post_meta($post_id, 'geo_longitude', true);
		$script = $script . "
        lat_lng = [" . $postLatitude . "," . $postLongitude . "];
        L.marker(lat_lng, markerOptions).addTo(mymap).bindPopup('<a href=\"" . esc_attr((string) get_permalink($post_id)) . "\">" . $postTitle . "</a>');
        myMapBounds.push(lat_lng);";
		$counter = $counter + 1;
	}
	wp_reset_postdata();
	$script = $script . "
        mymap.fitBounds(myMapBounds);
</script>";

	if ($counter > 0) {
		$width = esc_attr((string) get_option('geolocation_map_width_page'));
		$height = esc_attr((string) get_option('geolocation_map_height_page'));
		$html = $html . '<div id="mapid" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
		$html = $html . $script;
	}
	$content = str_replace((string) get_option('geolocation_shortcode'), $html, $content);
	return $content;
}

function pullJSON_osm($latitude, $longitude)
{
	$json = get_osm_nominatim_url() . "/reverse?format=json&accept-language=" . getSiteLang() . "&lat=" . $latitude . "&lon=" . $longitude . "&addressdetails=1";
	$ch = curl_init($json);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	$jsonfile = curl_exec($ch);
	curl_close($ch);
	$decoded = json_decode((string) $jsonfile, true);
	return $decoded;
}

// function reverse_geocode($latitude, $longitude)
// {
// 	$json = pullJSON($latitude, $longitude);
// 	$city = $json["address"]["city"];
// 	$state = $json["address"]["suburb"];
// 	$country = $json["address"]["country"];

// 	return buildAddress($city, $state, $country);
// }

include_once ABSPATH . 'wp-admin/includes/plugin.php';

function get_osm_tiles_url()
{
	if (((bool) get_option('geolocation_osm_use_proxy')) && is_plugin_active('osm-tiles-proxy/osm-tiles-proxy.php')) {
		$proxy_cached_url   = apply_filters('osm_tiles_proxy_get_proxy_url', $proxy_cached_url);
		return $proxy_cached_url;
	} else {
		$param = (string) get_option('geolocation_osm_tiles_url');
		return $param;
	}
}

function get_osm_leaflet_js_url()
{
	if (((bool) get_option('geolocation_osm_use_proxy')) && is_plugin_active('osm-tiles-proxy/osm-tiles-proxy.php')) {
		$leaflet_js_url     = apply_filters('osm_tiles_proxy_get_leaflet_js_url', $leaflet_js_url);
		return $leaflet_js_url;
	} else {
		//$param = (string) get_option('geolocation_osm_leaflet_js_url');
        $param = "/wp-content/plugins/geolocation/js/leaflet.js";
		return $param;
	}
}

function get_osm_leaflet_css_url()
{
	if (((bool) get_option('geolocation_osm_use_proxy')) && is_plugin_active('osm-tiles-proxy/osm-tiles-proxy.php')) {
		$leaflet_css_url    = apply_filters('osm_tiles_proxy_get_leaflet_css_url', $leaflet_css_url);
		return $leaflet_css_url;
	} else {
		//$param = (string) get_option('geolocation_osm_leaflet_css_url');
		$param = "/wp-content/plugins/geolocation/js/leaflet.css";
		return $param;
	}
}

function get_osm_nominatim_url()
{
	$param = (string) get_option('geolocation_osm_nominatim_url');
	return $param;
}

?>