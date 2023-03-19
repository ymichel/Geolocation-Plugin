<?php
/**
 * Google Maps
 *
 * This is the provider specific pool for the provider "Google Maps".
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <geolocation@yann-michel.de>
 * @license GPL2
 */
/**
 * Print the admin header for Google Maps usage.
 *
 * @return void
 */
function admin_head_google() {
	global $post;
	$post_id = $post->ID;
	$zoom    = (int) get_option( 'geolocation_default_zoom' );
	wp_enqueue_script( 'google_jsapi', 'https://www.google.com/jsapi', array(), GEOLOCATION__VERSION, true );
	wp_enqueue_script( 'google_maps_api', 'https://maps.googleapis.com/maps/api/js' . get_google_maps_api_key( '?' ) . '&callback=initMap', array(), GEOLOCATION__VERSION, true );
	?>
	<script type="text/javascript">
		function initMap() {
			//console.log("google maps is ready.");
		}
	</script>
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
			var center = new google.maps.LatLng(52.5162778, 13.3733267);
			var post_latitude = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_latitude', true ) ); ?>';
			var post_longitude = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_longitude', true ) ); ?>';
			var postAddress = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_address', true ) ); ?>';
			var postAddressReverse = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_address_reverse', true ) ); ?>';
			var isPublic = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_public', true ) ); ?>';
			var isGeoEnabled = '<?php echo esc_js( (string) get_post_meta( $post_id, 'geo_enabled', true ) ); ?>';

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


			if ((post_latitude !== '') && (post_longitude !== '')) {
				center = new google.maps.LatLng(post_latitude, post_longitude);
				hasLocation = true;
				document.getElementById('geolocation-latitude').value = post_latitude;
				document.getElementById('geolocation-longitude').value = post_longitude;
				document.getElementById('geolocation-address-reverse').value = postAddressReverse;
				if (postAddress !== '') {
					document.getElementById('geolocation-address').value = postAddress;
				} else {
					reverseGeocode(center);
				}

			}

			var myOptions = {
				'zoom': <?php echo esc_js( $zoom ); ?>,
				'center': center,
				'mapTypeId': google.maps.MapTypeId.ROADMAP
			};
			var image = '<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) ); ?>';
			var shadow = new google.maps.MarkerImage('<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ) ); ?>',
				new google.maps.Size(39, 23),
				new google.maps.Point(0, 0),
				new google.maps.Point(12, 25));

			var map = new google.maps.Map(document.getElementById('geolocation-map'), myOptions);
			var marker = new google.maps.Marker({
				position: center,
				map: map,
				<?php
				if ( (bool) get_option( 'geolocation_wp_pin' ) ) {
					?>
					icon: image,
					shadow: shadow,
				<?php } ?>
				title: 'Post Location'
			});

			if ((!hasLocation) && (google.loader.ClientLocation)) {
				center = new google.maps.LatLng(google.loader.ClientLocation.latitude, google.loader.ClientLocation.longitude);
				reverseGeocode(center);
			} else if (!hasLocation) {
				map.setZoom(1);
			}

			google.maps.event.addListener(map, 'click', function(event) {
				placeMarker(event.latLng);
			});

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

			function placeMarker(location) {
				marker.setPosition(location);
				map.setCenter(location);
				if ((location.lat() !== '') && (location.lng() !== '')) {
					document.getElementById('geolocation-latitude').value = location.lat();
					document.getElementById('geolocation-longitude').value = location.lng();
					//console.log(location);
				}
				reverseGeocode(location);
			}

			function geocode(address) {
				var geocoder = new google.maps.Geocoder();
				if (geocoder) {
					geocoder.geocode({
						"address": address
					}, function(results, status) {
						if (status === google.maps.GeocoderStatus.OK) {
							placeMarker(results[0].geometry.location);
							if (!hasLocation) {
								map.setZoom(<?php echo esc_js( $zoom ); ?>);
								hasLocation = true;
							}
						}
					});
				}
				//document.querySelector("#geodata").innerHTML = post_latitude + ', ' + post_longitude;
			}

			function reverseGeocode(location) {
				var geocoder = new google.maps.Geocoder();
				if (geocoder) {
					geocoder.geocode({
						"latLng": location
					}, function(results, status) {
						if (status === google.maps.GeocoderStatus.OK) {
							if (results[1]) {
								var address = results[1].formatted_address;
								if (address === "") {
									address = results[7].formatted_address;
									document.getElementById('geolocation-address-reverse').value = address;
								} else {
									document.getElementById('geolocation-address').value = address;
									document.getElementById('geolocation-address-reverse').value = address;
									placeMarker(location);
								}
							}
						}
					});
				}
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
		});
	</script>
	<?php
}

/**
 * Add_needed funtcionality for using geolocation for map links.
 *
 * @param [type] $posts Your posts.
 * @return void
 */
function add_geo_support_google( $posts ) {
	default_settings();
	global $post_count;
	$post_count = count( $posts );

	$zoom = (int) get_option( 'geolocation_default_zoom' );
	wp_enqueue_script( 'google_maps_api', 'https://maps.googleapis.com/maps/api/js' . get_google_maps_api_key( '?' ) . '&callback=initMap', array(), GEOLOCATION__VERSION, true );
	?>
	<script type="text/javascript">
		function initMap() {
			//console.log("google maps is ready.");
		}
	</script>
	<script type="text/javascript">
		function ready(fn) {
			if (document.readyState != 'loading') {
				fn();
			} else {
				document.addEventListener('DOMContentLoaded', fn);
			}
		}
		ready(() => {
			var center = new google.maps.LatLng(0.0, 0.0);
			var myOptions = {
				zoom: <?php echo esc_js( $zoom ); ?>,
				center: center,
				mapTypeId: google.maps.MapTypeId.ROADMAP
			}
			var map = new google.maps.Map(document.getElementById("map"), myOptions);
			var image = "<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) ); ?>";
			var shadow = new google.maps.MarkerImage("<?php echo esc_url( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ); ?>",
				new google.maps.Size(39, 23),
				new google.maps.Point(0, 0),
				new google.maps.Point(12, 25)
			);
			var marker = new google.maps.Marker({
				position: center,
				map: map,
				<?php if ( (bool) get_option( 'geolocation_wp_pin' ) ) { ?>
					icon: image,
					shadow: shadow,
				<?php } ?>
				title: "Post Location"
			});
						if ( 'map' == '<?php echo esc_attr( (string) get_option( 'geolocation_map_display' ) ); ?>' ) {
				var postmap = {};
				var geolocationMaps = document.querySelectorAll('.geolocation-map');
				for (var i = 0; i < geolocationMaps.length; i++) {
					name = geolocationMaps[i].getAttribute('name');
										if ( 'me' !== name ) {
						postmap = new google.maps.Map(document.getElementById(geolocationMaps[i].id), myOptions);
						var lat = name.split(',')[0];
						var lng = name.split(',')[1];
						var latlng = new google.maps.LatLng(lat, lng);
						postmap.setZoom(<?php echo esc_js( $zoom ); ?>);
						marker.setMap(postmap);
						marker.setPosition(latlng);
						postmap.setCenter(latlng);
						//console.log( geolocationMaps[i].id );
					}
				}
						} else {

				var allowDisappear = true;
				var cancelDisappear = false;
	
				var geolocationLinks = document.querySelectorAll('.geolocation-link');

				for (var i = 0; i < geolocationLinks.length; i++) {
					geolocationLinks[i].addEventListener('mouseover', function() {
						//TODO? $j("#map").stop(true, true);
						var lat = this.getAttribute('name').split(',')[0];
						var lng = this.getAttribute('name').split(',')[1];
						var latlng = new google.maps.LatLng(lat, lng);
						placeMarker(latlng);

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

				function placeMarker(location) {
					map.setZoom(<?php echo esc_js( $zoom ); ?>);
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
			}
		});
	</script>
<?php }

/**
 * Display_all location on a map inside a page.
 *
 * @param [type] $content the content to be shown.
 * @return mixed
 */
function display_location_page_google( $content ) {
	global $post;
	$html = '';
	settype( $html, 'string' );
	$script = '';
	settype( $script, 'string' );
	settype( $category, 'string' );
	$category    = (string) get_post_meta( $post->ID, 'category', true );
	$category_id = get_cat_ID( $category );
	$counter     = 0;

	wp_enqueue_script( 'google_maps_api', 'https://maps.googleapis.com/maps/api/js' . get_google_maps_api_key( '?' ) . '&callback=initMap', array(), GEOLOCATION__VERSION, true );

	if ( is_user_logged_in() ) {
		$pargs = array(
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
		$pargs = array(
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

	$script = $script . '<script type="text/javascript">
	function initMap() { }
</script>';
	$script = $script . "<script type=\"text/javascript\">
	function ready(fn) {
		if (document.readyState != 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}
	ready(() => {
      var map = new google.maps.Map(
        document.getElementById('mymap'), {
          mapTypeId: google.maps.MapTypeId.ROADMAP
        }
      );
	  var image = \"" . esc_js( esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) ) . '"
	  var shadow = new google.maps.MarkerImage("' . esc_url( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ) . '",
	  		new google.maps.Size(39, 23),
	  		new google.maps.Point(0, 0),
	  		new google.maps.Point(12, 25)
	  );
      var bounds = new google.maps.LatLngBounds();';

	$post_query = new WP_Query( $pargs );
	while ( $post_query->have_posts() ) {
		$post_query->the_post();
		$post_title     = get_the_title();
		$post_id        = (int) get_the_ID();
		$post_latitude  = (string) get_post_meta( $post_id, 'geo_latitude', true );
		$post_longitude = (string) get_post_meta( $post_id, 'geo_longitude', true );
		$script         = $script . '
      marker = new google.maps.Marker({
            position: new google.maps.LatLng(' . $post_latitude . ',' . $post_longitude . '),';
		if ( (bool) get_option( 'geolocation_wp_pin' ) ) {
			$script = $script . '
			icon: image,
				shadow: shadow,';
		}
			$script = $script . '
			map: map,
			url: "' . esc_attr( (string) get_permalink( $post_id ) ) . '",
			title: "' . $post_title . '"
      });
      bounds.extend(marker.position);';
		++$counter;
	}
	wp_reset_postdata();
	$script = $script . '
       map.fitBounds(bounds);
	});
</script>';

	if ( $counter > 0 ) {
		$width  = esc_attr( (string) get_option( 'geolocation_map_width_page' ) );
		$height = esc_attr( (string) get_option( 'geolocation_map_height_page' ) );
		$html   = $html . '<div id="mymap" class="geolocation-map" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
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
function pull_json_google( $latitude, $longitude ) {
	$url     = 'https://maps.googleapis.com/maps/api/geocode/json' . get_google_maps_api_key( '?' ) . '&language=' . get_site_lang() . '&latlng=' . $latitude . ',' . $longitude;
	$decoded = json_decode( wp_remote_get( $url )['body'] );
	return $decoded;
}

/**
 * Get the stored Google Maps API key.
 *
 * @param [type] $sep The Seperator.
 * @return string
 */
function get_google_maps_api_key( $sep ) {
	$apikey = (string) get_option( 'geolocation_google_maps_api_key' );
	if ( $apikey ) {
		return $sep . 'key=' . $apikey;
	}
	return '';
}

?>
