<?php
/**
 * OSM
 *
 * This is the provider specific pool for the provider "open streetmaps (osm)".
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <geolocation@yann-michel.de>
 * @license GPL2
 */
/**
 * Print the admin header for OSM usage.
 *
 * @return void
 */
function admin_head_osm() {
	global $post;
	$post_id = $post->ID;
	wp_enqueue_style( 'osm_leaflet_css', get_osm_leaflet_css_url(), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'osm_leaflet_js', get_osm_leaflet_js_url(), array(), GEOLOCATION__VERSION, true );?>
	<script type="text/javascript">
		function ready(fn) {
			if (document.readyState != 'loading') {
				fn();
			} else {
				document.addEventListener('DOMContentLoaded', fn);
			}
		}
		ready(() => {
			var hasLocation = false;
			var postLatitude = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_latitude', true ) ); ?>';
			var postLongitude = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_longitude', true ) ); ?>';
			var isPublic = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_public', true ) ); ?>';
			var postAddress = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_address', true ) ); ?>';
			var postAddressReverse = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_address_reverse', true ) ); ?>';
			var isGeoEnabled = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_enabled', true ) ); ?>';
			var zoomlevel = <?php echo (int) esc_attr( (string) get_option( 'geolocation_default_zoom' ) ); ?>;
			var image = '<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) ); ?>';
			var shadow = '<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ) ); ?>';
			var iconOptions = {
				iconUrl: image,
				shadowUrl: shadow,
				iconSize:     [25, 34],
				shadowSize:   [39, 23],
				iconAnchor:   [5, 34], 
				shadowAnchor: [3, 25], 
				popupAnchor:  [12, -30]
			}

			var customIcon = L.icon(iconOptions);
			var markerOptions = {
				<?php if ( (bool) get_option( 'geolocation_wp_pin' ) ) { ?>
					icon: customIcon,
					<?php } ?>clickable: false,
					draggable: false
			};
			var myMarker = {};

			if (isPublic === '0') {
				document.getElementById('geolocation-public').removeAttribute('checked');
			} else {
				document.getElementById('geolocation-public').setAttribute('checked', true);
			}

			if (isGeoEnabled === '0') {
				disableGeo();
			} else {
				enableGeo();
			}

			var map = L.map("geolocation-map").setView([52.5162778, 13.3733267], zoomlevel);
			L.tileLayer('<?php echo esc_js( get_osm_tiles_url() ); ?>', {
				attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
			}).addTo(map);
			myMarker = L.marker([52.5162778, 13.3733267], markerOptions).addTo(map);
			map.setView(myMarker.getLatLng(), map.getZoom());
			if ((postLatitude !== '') && (postLongitude !== '')) {
				myMarker.setLatLng([postLatitude, postLongitude]);
				map.setView(myMarker.getLatLng(), zoomlevel);
				hasLocation = true;
				document.getElementById('geolocation-latitude').value = postLatitude;
				document.getElementById('geolocation-longitude').value = postLongitude;
				document.getElementById('geolocation-address-reverse').value = postAddressReverse;
				if (postAddress !== '') {
					document.getElementById('geolocation-address').value = postAddress;
				} else {
					reverseGeocode(postLatitude, postLongitude);
				}
			}
			setTimeout(function() {
				map.invalidateSize(true);
			}, 100);

			var currentAddress;
			var customAddress = false;
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
				var request = new XMLHttpRequest();
				request.open('GET', '<?php echo esc_url( get_osm_nominatim_url() ); ?>/search?format=json&accept-language=\'<?php echo esc_js( get_site_lang() ); ?>\'&limit=1&q=' + address, true);
				request.onload = function() {
					if (this.status >= 200 && this.status < 400) {
						// Success!
						var data = JSON.parse(this.response);
						console.log(data);
						document.getElementById('geolocation-latitude').value = data[0].lat;
						document.getElementById('geolocation-longitude').value = data[0].lon;
						lat_lng = [data[0].lat, data[0].lon];
						myMarker.setLatLng(lat_lng);
						map.setView(myMarker.getLatLng(), map.getZoom());
						hasLocation = true;
						reverseGeocode(data[0].lat, data[0].lon);
					} else {
						// error
					}
				};
				request.send();
			}

			function reverseGeocode(lat, lon) {
				var request = new XMLHttpRequest();
				request.open('GET', '<?php echo esc_url( get_osm_nominatim_url() ); ?>/reverse?format=json&accept-language=\'<?php echo esc_js( get_site_lang() ); ?>\'&lat=' + lat + '&lon=' + lon, true);
				request.onload = function() {
					if (this.status >= 200 && this.status < 400) {
						// Success!
						var data = JSON.parse(this.response);
						document.getElementById('geolocation-address').value = data.display_name;
						document.getElementById('geolocation-address-reverse').value = data.display_name;
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

/**
 * Add_needed funtcionality for using geolocation for map links.
 *
 * @param [type] $posts Your posts.
 * @return void
 */
function add_geo_support_osm( $posts ) {
	default_settings();
	global $post_count;
	$post_count = count( $posts );

	$zoom = (int) get_option( 'geolocation_default_zoom' );
	wp_enqueue_style( 'osm_leaflet_css', get_osm_leaflet_css_url(), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'osm_leaflet_js', get_osm_leaflet_js_url(), array(), GEOLOCATION__VERSION, true );
	?>
	<script type="text/javascript">
		function ready(fn) {
			if (document.readyState != 'loading') {
				fn();
			} else {
				document.addEventListener('DOMContentLoaded', fn);
			}
		}
		ready(() => {
			var map = L.map(document.getElementById("map"));
			L.tileLayer('<?php echo esc_js( get_osm_tiles_url() ); ?>', {
				attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
			}).addTo(map);

			var allowDisappear = true;
			var cancelDisappear = false;

			var iconOptions = {
				iconUrl: '<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) ); ?>',
				shadowUrl: '<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ) ); ?>',
				iconSize:     [25, 34],
				shadowSize:   [39, 23],
				iconAnchor:   [5, 34], 
				shadowAnchor: [3, 25], 
				popupAnchor:  [12, -30]
			}
			var customIcon = L.icon(iconOptions);
			var markerOptions = {
				<?php if ( (bool) get_option( 'geolocation_wp_pin' ) ) { ?>
					icon: customIcon,
				<?php } ?>
				clickable: false,
				draggable: false
			}
			if ( 'map' == '<?php echo esc_attr( (string) get_option( 'geolocation_map_display' ) ); ?>' ) {
				var geolocationMaps = document.querySelectorAll('.geolocation-map');
				var name = {};
				var postmap = {};
				for (var i = 0; i < geolocationMaps.length; i++) {
					name = geolocationMaps[i].getAttribute('name');
					if ( 'me' !== name ) {
						postmap = L.map(document.getElementById(geolocationMaps[i].id));
						L.tileLayer('<?php echo esc_js( get_osm_tiles_url() ); ?>', {
							attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
						}).addTo(postmap);
						var lat = name.split(',')[0];
						var lng = name.split(',')[1];
						var lat_lng = [lat, lng];
						L.marker(lat_lng, markerOptions).addTo(postmap);
						postmap.setView(new L.LatLng(lat, lng), <?php echo esc_js( $zoom ); ?>);
						//console.log( geolocationMaps[i].id);
					}
				}
			} else {
				var geolocationLinks = document.querySelectorAll('.geolocation-link');

				for (var i = 0; i < geolocationLinks.length; i++) {
						geolocationLinks[i].addEventListener('mouseover', function() {
							var lat = this.getAttribute('name').split(',')[0];
							var lng = this.getAttribute('name').split(',')[1];
							var lat_lng = [lat, lng];
							L.marker(lat_lng, markerOptions).addTo(map);
							map.setView(new L.LatLng(lat, lng), <?php echo esc_js( $zoom ); ?>);

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
			}
		});
	</script>
	<?php
}

/**
 * Display_all location on a map inside a page.
 *
 * @param [type] $content the content to be shown.
 * @return mixed
 */
function display_location_page_osm( $content ) {
	global $post;
	$html = '';
	settype( $html, 'string' );
	$script = '';
	settype( $script, 'string' );
	settype( $category, 'string' );
	$category    = (string) get_post_meta( $post->ID, 'category', true );
	$category_id = get_cat_ID( $category );
	$counter     = 0;

	wp_enqueue_style( 'osm_leaflet_css', get_osm_leaflet_css_url(), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'osm_leaflet_js', get_osm_leaflet_js_url(), array(), GEOLOCATION__VERSION, true );

	if ( is_user_logged_in() ) {
		$pargs  = array(
			'post_type'      => 'post',
			'cat'            => $category_id,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'geo_latitude',
					'value'   => '0',
					'compare' => '!=',
				),
				array(
					'key'     => 'geo_longitude',
					'value'   => '0',
					'compare' => '!=',
				),
				array(
					'key'     => 'geo_enabled',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);
	} else {
		$pargs  = array(
			'post_type'      => 'post',
			'cat'            => $category_id,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'geo_latitude',
					'value'   => '0',
					'compare' => '!=',
				),
				array(
					'key'     => 'geo_longitude',
					'value'   => '0',
					'compare' => '!=',
				),
				array(
					'key'     => 'geo_enabled',
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => 'geo_public',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);
	}
	$zoom   = 1;
	$script = $script . "<script type=\"text/javascript\">
	function ready(fn) {
			if (document.readyState != 'loading') {
				fn();
			} else {
				document.addEventListener('DOMContentLoaded', fn);
			}
		}
		ready(() => {
        var mymap = L.map('mapid').setView([51.505, -0.09], " . $zoom . ");
        var myMapBounds = [];
        var lat_lng = [];
	L.tileLayer('" . esc_js( get_osm_tiles_url() ) . "', {
        	attribution: '&copy; <a href=\"http://osm.org/copyright\">OpenStreetMap</a> contributors' 
        }).addTo(mymap);
        var image = '" . esc_js( esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) ) . "';
		var shadow = '" . esc_js( esc_url( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ) ) . "';
		var iconOptions = {
			iconUrl: image,
			shadowUrl: shadow,
            iconSize:     [25, 34],
            shadowSize:   [39, 23],
            iconAnchor:   [5, 34], 
            shadowAnchor: [3, 25], 
            popupAnchor:  [12, -30]
		};
	var customIcon = L.icon(iconOptions);
	var markerOptions = {";
	if ( (bool) get_option( 'geolocation_wp_pin' ) ) {
		$script = $script . '                      icon: customIcon,';
	}
	$script = $script . '	       clickable: false,
	       draggable: false
     	    }';

	$post_query = new WP_Query( $pargs );
	while ( $post_query->have_posts() ) {
		$post_query->the_post();
		$post_title     = get_the_title();
		$post_id        = (int) get_the_ID();
		$post_latitude  = (string) get_post_meta( $post_id, 'geo_latitude', true );
		$post_longitude = (string) get_post_meta( $post_id, 'geo_longitude', true );
		$script         = $script . '
        lat_lng = [' . $post_latitude . ',' . $post_longitude . "];
        L.marker(lat_lng, markerOptions).addTo(mymap).bindPopup('<a href=\"" . esc_attr( (string) get_permalink( $post_id ) ) . '">' . $post_title . "</a>');
        myMapBounds.push(lat_lng);";
		++$counter;
	}
	wp_reset_postdata();
	$script = $script . '
        mymap.fitBounds(myMapBounds);
		});
</script>';

	if ( $counter > 0 ) {
		$width  = esc_attr( (string) get_option( 'geolocation_map_width_page' ) );
		$height = esc_attr( (string) get_option( 'geolocation_map_height_page' ) );
		$html   = $html . '<div id="mapid" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
		$html   = $html . $script;
	}
	$content = str_replace( (string) get_option( 'geolocation_shortcode' ), $html, $content );
	return $content;
}

/**
 * Pull the JSON for the given geoinformation.
 *
 * @param [type] $latitude The Latitude.
 * @param [type] $longitude The Longitude.
 * @return mixed
 */
function pull_json_osm( $latitude, $longitude ) {
	$json = get_osm_nominatim_url() . '/reverse?format=json&accept-language=' . get_site_lang() . '&lat=' . $latitude . '&lon=' . $longitude;
	$ch   = curl_init( $json );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
	$jsonfile = curl_exec( $ch );
	curl_close( $ch );
	$decoded = json_decode( (string) $jsonfile, true );
	return $decoded;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Get the tiles url to be used.
 *
 * @return string
 */
function get_osm_tiles_url() {
	if ( ( (bool) get_option( 'geolocation_osm_use_proxy' ) ) && is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' ) ) {
		$proxy_cached_url = apply_filters( 'osm_tiles_proxy_get_proxy_url', '' );
		return $proxy_cached_url;
	} else {
		$param = (string) get_option( 'geolocation_osm_tiles_url' );
		return $param;
	}
}

/**
 * Get the Leaflet JS URL to be used.
 *
 * @return mixed
 */
function get_osm_leaflet_js_url() {
	if ( ( (bool) get_option( 'geolocation_osm_use_proxy' ) ) && is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' ) ) {
		$leaflet_js_url = apply_filters( 'osm_tiles_proxy_get_leaflet_js_url', '' );
		return $leaflet_js_url;
	} else {
		$param = plugins_url( 'js/leaflet.js', __FILE__ );
		return $param;
	}
}

/**
 * Get the Leaflet CSS URL to be used.
 *
 * @return mixed
 */
function get_osm_leaflet_css_url() {
	if ( ( (bool) get_option( 'geolocation_osm_use_proxy' ) ) && is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' ) ) {
		$leaflet_css_url = apply_filters( 'osm_tiles_proxy_get_leaflet_css_url', '' );
		return $leaflet_css_url;
	} else {
		$param = plugins_url( 'js/leaflet.css', __FILE__ );
		return $param;
	}
}

/**
 * Get the OpenStreetmaps Nominatim URL to be used.
 *
 * @return string
 */
function get_osm_nominatim_url() {
	$param = (string) get_option( 'geolocation_osm_nominatim_url' );
	return $param;
}

?>
