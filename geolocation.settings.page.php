<?php
/**
 * Settings Page
 *
 * This is the plugin specific settings page.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <geolocation@yann-michel.de>
 * @license GPL2
 */

/**
 * Provide all needed items for the settings page.
 *
 * @return void
 */
function geolocation_settings_page() {
	require_once GEOLOCATION__PLUGIN_DIR . 'geolocation.map-provider-google.php';
	require_once GEOLOCATION__PLUGIN_DIR . 'geolocation.map-provider-osm.php';

	if ( (bool) get_option( 'geolocation_updateAddresses' ) ) {
		update_geolocation_addresses();
	}

	default_settings();
	wp_enqueue_style( 'osm_leaflet_css', get_osm_leaflet_css_url(), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'osm_leaflet_js', get_osm_leaflet_js_url(), array(), GEOLOCATION__VERSION, false );
	wp_enqueue_script( 'google_maps_api', 'https://maps.googleapis.com/maps/api/js' . get_google_maps_api_key( '?' ) . '&callback=initMap', array(), GEOLOCATION__VERSION, false );
	?>
	<script type="text/javascript">
		function initMap() {
			//console.log("google maps is ready.");
		}
	</script>
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
		<h2><?php esc_html_e( 'Geolocation Plugin Settings', 'geolocation' ); ?></h2>
	</div>
	<form method="post" action="options.php" id="settings">
		<?php settings_fields( 'geolocation-settings-group' ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Dimensions', 'geolocation' ); ?></th>
				<td class="dimensions">
					<strong><?php esc_html_e( 'Width', 'geolocation' ); ?>:</strong><input type="text" name="geolocation_map_width" value="<?php echo esc_attr( (string) get_option( 'geolocation_map_width' ) ); ?>" />px<br />
					<strong><?php esc_html_e( 'Height', 'geolocation' ); ?>:</strong><input type="text" name="geolocation_map_height" value="<?php echo esc_attr( (string) get_option( 'geolocation_map_height' ) ); ?>" />px
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Position', 'geolocation' ); ?></th>
				<td class="position">
					<input type="radio" id="geolocation_map_position_before" name="geolocation_map_position" value="before" <?php is_value( 'geolocation_map_position', 'before' ); ?>><label for="geolocation_map_position_before"><?php esc_html_e( 'Before the post.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_position_after" name="geolocation_map_position" value="after" <?php is_value( 'geolocation_map_position', 'after' ); ?>><label for="geolocation_map_position_after"><?php esc_html_e( 'After the post.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_position_shortcode" name="geolocation_map_position" value="shortcode" <?php is_value( 'geolocation_map_position', 'shortcode' ); ?>><label for="geolocation_map_position_shortcode">
																																					<?php
																																					esc_html_e( 'Wherever I put the shortcode: ', 'geolocation' );
																																					echo esc_attr( (string) get_option( 'geolocation_shortcode' ) );
																																					?>
					.</label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'How would you like your geolocation to be displayed?', 'geolocation' ); ?></th>
				<td class="display">
					<input type="radio" id="geolocation_map_display_plain" name="geolocation_map_display" value="plain" <?php is_value( 'geolocation_map_display', 'plain' ); ?>>
					<label for="geolocation_map_display_plain"><?php esc_html_e( 'Plain text.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_display_link" name="geolocation_map_display" value="link" <?php is_value( 'geolocation_map_display', 'link' ); ?>>
					<label for="geolocation_map_display_link"><?php esc_html_e( 'Simple link w/hover.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_display_map" name="geolocation_map_display" value="map" <?php is_value( 'geolocation_map_display', 'map' ); ?>>
					<label for="geolocation_map_display_map"><?php esc_html_e( 'Simple map (static).', 'geolocation' ); ?></label><br />
					<?php
					/*
					?>
					<input type="radio" id="geolocation_map_display_debug" name="geolocation_map_display" value="debug" <?php is_value('geolocation_map_display', 'debug'); ?>>
					<label for="geolocation_map_display_debug"><?php esc_html_e('Debug', 'geolocation'); ?></label><br />
					<?php
					*/
					?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Default Zoom Level', 'geolocation' ); ?></th>
				<td class="zoom">
					<input type="radio" id="geolocation_default_zoom_globe" name="geolocation_default_zoom" value="1" <?php is_value( 'geolocation_default_zoom', '1' ); ?> onclick="swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_globe"><?php esc_html_e( 'Globe', 'geolocation' ); ?></label>

					<input type="radio" id="geolocation_default_zoom_country" name="geolocation_default_zoom" value="3" <?php is_value( 'geolocation_default_zoom', '3' ); ?> onclick="swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_country"><?php esc_html_e( 'Country', 'geolocation' ); ?></label>
					<input type="radio" id="geolocation_default_zoom_state" name="geolocation_default_zoom" value="6" <?php is_value( 'geolocation_default_zoom', '6' ); ?> onclick="swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_state"><?php esc_html_e( 'State', 'geolocation' ); ?></label>
					<input type="radio" id="geolocation_default_zoom_city" name="geolocation_default_zoom" value="9" <?php is_value( 'geolocation_default_zoom', '9' ); ?> onclick="swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_city"><?php esc_html_e( 'City', 'geolocation' ); ?></label>
					<input type="radio" id="geolocation_default_zoom_street" name="geolocation_default_zoom" value="16" <?php is_value( 'geolocation_default_zoom', '16' ); ?> onclick="swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_street"><?php esc_html_e( 'Street', 'geolocation' ); ?></label>
					<input type="radio" id="geolocation_default_zoom_block" name="geolocation_default_zoom" value="18" <?php is_value( 'geolocation_default_zoom', '18' ); ?> onclick="swap_zoom_sample(this.id);"><label for="geolocation_default_zoom_block"><?php esc_html_e( 'Block', 'geolocation' ); ?></label>
					<br />
					<?php echo get_geo_div(); ?>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"></th>
				<td class="position">
					<input type="checkbox" id="geolocation_wp_pin" name="geolocation_wp_pin" value="1" <?php is_checked( 'geolocation_wp_pin' ); ?> onclick="updatePin();"><label for="geolocation_wp_pin"><?php esc_html_e( 'Show your support for WordPress by using the WordPress map pin.', 'geolocation' ); ?></label>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Dimensions Page', 'geolocation' ); ?></th>
				<td class="dimensions">
					<strong><?php esc_html_e( 'Width', 'geolocation' ); ?>:</strong><input type="text" name="geolocation_map_width_page" value="<?php echo esc_attr( (string) get_option( 'geolocation_map_width_page' ) ); ?>" />px<br />
					<strong><?php esc_html_e( 'Height', 'geolocation' ); ?>:</strong><input type="text" name="geolocation_map_height_page" value="<?php echo esc_attr( (string) get_option( 'geolocation_map_height_page' ) ); ?>" />px
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Maps Provider</th>
				<td>
					<select id="geolocation_provider" name="geolocation_provider" onchange="providerSelected(this.value);">
						<option value="google" 
						<?php
						if ( (string) get_option( 'geolocation_provider' ) === 'google' ) {
													echo ' selected';
						}
						?>
												>Google Maps</option>
						<option value="osm" 
						<?php
						if ( (string) get_option( 'geolocation_provider' ) === 'osm' ) {
												echo ' selected';
						}
						?>
											>Open Street Maps</option>
					</select>
				</td>
			</tr>
			<tr valign="top" class="google-apikey">
				<th scope="row">Google Maps API key</th>
				<td>
					<input type="text" name="geolocation_google_maps_api_key" value="<?php echo esc_attr( (string) get_option( 'geolocation_google_maps_api_key' ) ); ?>" />
				</td>
			</tr>
			<tr valign="top" class="osm-urls">
				<th scope="row">OSM URLs</th>
				<td>
					<table>
						<?php if ( is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' ) ) { ?>
							<tr>
								<th><?php esc_html_e( 'Use Proxy', 'geolocation' ); ?></th>
								<td> <input type="checkbox" id="geolocation_osm_use_proxy" name="geolocation_osm_use_proxy" value="1" <?php is_checked( 'geolocation_osm_use_proxy' ); ?>><label for="geolocation_osm_use_proxy"><?php esc_html_e( 'Make use of proxy plugin.', 'geolocation' ); ?></label> </td>
							</tr>
						<?php } ?>
						<tr>
							<th><?php esc_html_e( 'Tiles url (Caching)', 'geolocation' ); ?></th>
							<td><?php echo esc_html( get_osm_tiles_url() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Leaflet JS', 'geolocation' ); ?></th>
							<td><?php echo esc_html( get_osm_leaflet_js_url() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Leaflet CSS', 'geolocation' ); ?></th>
							<td><?php echo esc_html( get_osm_leaflet_css_url() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Nominatim ([Reverse-]Geocoding)', 'geolocation' ); ?></th>
							<td><?php echo esc_html( get_osm_nominatim_url() ); ?></td>
						</tr>
					</table>
				</td>

			<tr valign="top">
				<th scope="row"><?php esc_html_e( 'Used Language for Adresses', 'geolocation' ); ?></th>
				<td>
					<?php echo esc_attr( (string) get_site_lang() ); ?>
			</tr>
			<tr valign="top">
				<th scope="row"></th>
				<td class="position">
					<input type="checkbox" id="geolocation_updateAddresses" name="geolocation_updateAddresses" value="1" <?php is_checked( 'geolocation_updateAddresses' ); ?>><label for="geolocation_updateAddresses"><?php esc_html_e( 'Update all addresses from posts that have location information (only once this setup is saved).', 'geolocation' ); ?></label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes', 'geolocation' ); ?>" alt="" />
		</p>
		<script types="text/javascript">

			var zoomlevel;
			var provider = '<?php echo esc_js( (string) get_option( 'geolocation_provider' ) ); ?>';

			var lat_lng = [52.5162778, 13.3733267];
			var osm_map = {};
			var image = '<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin.png', __FILE__ ) ) ); ?>';
			var shadow =  {};
			var shadowUrl = '<?php echo esc_js( esc_url( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ) ); ?>';

			var osm_iconOptions = {
				iconUrl: image,
				shadowUrl: shadowUrl,
				iconSize:     [25, 34],
				shadowSize:   [39, 23],
				iconAnchor:   [5, 34], 
				shadowAnchor: [3, 25], 
				popupAnchor:  [12, -30]
			}
			var osmCustomIcon = {};
			var osmMarkerOptions = {}
			var osmMarker = {};

			var google_map = {};
			var googleCenter = {};
			var googleOptions = {};
			var googleMarker = {};

			function setMarkerOptions() {
				//console.log("setMarkerOptions");
				if (document.getElementById("geolocation_wp_pin").checked) {
					switch (provider) {
						case 'google':
							googleCenter = new google.maps.LatLng(lat_lng[0], lat_lng[1]);
							googleOptions = {
								zoom: zoomlevel,
								center: googleCenter,
								mapTypeId: google.maps.MapTypeId.ROADMAP
							}
							shadow = new google.maps.MarkerImage("<?php echo esc_js( plugins_url( 'img/wp_pin_shadow.png', __FILE__ ) ); ?>",
								new google.maps.Size(39, 23),
								new google.maps.Point(0, 0),
								new google.maps.Point(12, 25)
							);
							googleMarker = new google.maps.Marker({
								position: googleCenter,
								map: google_map,
								icon: image,
								shadow: shadow,
								title: "Post Location"
							});
							break;
						case 'osm':
							osmCustomIcon = L.icon(osm_iconOptions);
							osmMarkerOptions = {
								icon: osmCustomIcon,
								clickable: false,
								draggable: false
							}
							osmMarker = L.marker(lat_lng, osmMarkerOptions).addTo(osm_map);
							break;
					}
				} else {
					switch (provider) {
						case 'google':
							googleOptions = {
								zoom: zoomlevel,
								center: googleCenter,
								mapTypeId: google.maps.MapTypeId.ROADMAP
							}
							googleMarker = new google.maps.Marker({
								position: googleCenter,
								map: google_map,
								title: "Post Location"
							});
							break;
						case 'osm':
							osmMarkerOptions = {
								clickable: false,
								draggable: false
							}
							osmMarker = L.marker(lat_lng, osmMarkerOptions).addTo(osm_map);
							break;
					}
				}
			}

			function initializeMap() {
				//console.log("initializeMap");
				switch (provider) {
						case 'google':
							google_map = new google.maps.Map(document.getElementById("map"), googleOptions);
							break;
						case 'osm':
							osm_map = L.map(document.getElementById("map")).setView(lat_lng, zoomlevel);
							L.tileLayer('<?php echo esc_js( get_osm_tiles_url() ); ?>', {attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'}).addTo(osm_map);
							break;
				}                
				setMarkerOptions();
				updateMap();
			}

			function updatePin() {
				//console.log("updatePin");
				setMarkerOptions();
				switch (provider) {
						case 'google':
							google_map.setZoom(zoomlevel);
							googleMarker.setPosition(googleCenter);
							google_map.setCenter(googleCenter);
							if (google_map && google_map.remove) {
								google_map.off();
								google_map.remove();
							} 
							break;
						case 'osm':
							if (osmMarker != undefined) {
								osm_map.removeLayer(osmMarker);
							};
							osmMarker = L.marker(lat_lng, osmMarkerOptions).addTo(osm_map);
							osm_map.setView(osmMarker.getLatLng(), zoomlevel);
							if (osm_map && osm_map.remove) {
								osm_map.off();
								osm_map.remove();
							}
							break;
				}
				initializeMap();
			}

			function updateMap() {
				//console.log("updateMap");
				switch (provider) {
						case 'google':
							google_map.setZoom(zoomlevel);
							googleMarker.setPosition(googleCenter);
							google_map.setCenter(googleCenter);
							break;
						case 'osm':
							osm_map.setView(osmMarker.getLatLng(), zoomlevel);
							break;
				}
			}

			function swap_zoom_sample(id) {
				//console.log("swap_zoom_sample("+id+")");
				zoomlevel = parseInt(document.getElementById(id).value);
				//console.log("       value: "+ zoomlevel);
				updateMap();
			}

			function providerSelected(value) {
				//console.log("providerSelected("+value+")");
				if (provider && value != provider) {                    
					switch (provider) {
							case 'google':
								if (osm_map && osm_map.remove) {
									osm_map.off();
									osm_map.remove();
								}
								break;
							case 'osm':
								if (google_map && google_map.remove) {
									google_map.off();
									google_map.remove();
								}
								break;
					}
				}
				provider = value;
				switch (provider) {
					case 'google':
						document.getElementsByClassName("google-apikey")[0].style.display = "";
						document.getElementsByClassName("osm-urls")[0].style.display = "none";
						break;
					case 'osm':
						document.getElementsByClassName("google-apikey")[0].style.display = "none";
						document.getElementsByClassName("osm-urls")[0].style.display = "";
						break;
				}
				initializeMap();
			}

			function initializeForm() {
				//console.log("initializeForm");
				var newProvider = document.getElementById("geolocation_provider").value;
				zoomlevel = <?php echo (int) esc_attr( (string) get_option( 'geolocation_default_zoom' ) ); ?>;
				providerSelected(newProvider);
			}

			function ready(fn) {
				if (document.readyState != 'loading') {
					fn();
				} else {
					document.addEventListener('DOMContentLoaded', fn);
				}
			}
			ready(() => {
				//console.log("ready");
				initializeForm();
			});
		</script>
	</form>
	<?php
}

?>
