<?php
/**
 * Settings Page
 *
 * This is the plugin specific settings page.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Provide all needed items for the settings page.
 *
 * @return void
 */
function geolocation_settings_page() {
	require_once GEOLOCATION__PLUGIN_DIR . 'geolocation-map-provider-google.php';
	require_once GEOLOCATION__PLUGIN_DIR . 'geolocation-map-provider-osm.php';

	// Restore defaults for emptied settings and reset the one-time update flag.
	$update_addresses = (bool) get_option( 'geolocation_updateAddresses' );
	geolocation_default_settings();

	// Cache option values.
	$map_width           = (string) get_option( 'geolocation_map_width' );
	$map_height          = (string) get_option( 'geolocation_map_height' );
	$map_width_page      = (string) get_option( 'geolocation_map_width_page' );
	$map_height_page     = (string) get_option( 'geolocation_map_height_page' );
	$shortcode           = (string) get_option( 'geolocation_shortcode' );
	$provider            = (string) get_option( 'geolocation_provider' );
	$default_zoom        = (int) get_option( 'geolocation_default_zoom' );
	$wp_pin              = (bool) get_option( 'geolocation_wp_pin' );
	$google_maps_api_key = (string) get_option( 'geolocation_google_maps_api_key' );
	$osm_use_proxy       = (bool) get_option( 'geolocation_osm_use_proxy' );
	$osm_strict_privacy  = (bool) get_option( 'geolocation_osm_strict_privacy' );
	$osm_tiles_url       = (string) get_option( 'geolocation_osm_tiles_url' );
	$osm_nominatim_url   = (string) get_option( 'geolocation_osm_nominatim_url' );
	$site_lang           = (string) geolocation_get_site_lang();

	if ( $update_addresses ) {
		geolocation_update_addresses();
	}

	wp_enqueue_style( 'osm_leaflet_css', geolocation_get_osm_leaflet_css_url(), array(), GEOLOCATION__VERSION, 'all' );
	wp_enqueue_script( 'osm_leaflet_js', geolocation_get_osm_leaflet_js_url(), array(), GEOLOCATION__VERSION, true );
	wp_enqueue_script( 'geolocation_settings', plugins_url( 'js/geolocation-settings.js', __FILE__ ), array( 'osm_leaflet_js' ), GEOLOCATION__VERSION, true );
	wp_add_inline_script(
		'geolocation_settings',
		'var geolocationSettings = ' . wp_json_encode(
			array_merge(
				geolocation_get_map_settings(),
				array(
					'provider' => $provider,
					'tilesUrl' => geolocation_get_osm_tiles_url(),
				)
			)
		) . ';',
		'before'
	);
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- external API without a version.
	wp_enqueue_script( 'google_maps_api', geolocation_get_google_maps_api_url(), array( 'geolocation_settings' ), null, true );
	?>
	<style type="text/css">
		#preload { display: none; }
		.dimensions strong { width: 50px; float: left; }
		.dimensions input { width: 70px; margin-right: 5px; }
		.zoom label { width: 50px; margin: 0 5px 0 2px; }
		.position label { margin: 0 5px 0 2px; }
	</style>
	<div class="wrap">
		<h1><?php esc_html_e( 'Geolocation Plugin Settings', 'geolocation' ); ?></h1>
	<form method="post" action="options.php" id="settings">
		<?php settings_fields( 'geolocation-settings-group' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Dimensions', 'geolocation' ); ?></th>
				<td class="dimensions">
					<strong><?php esc_html_e( 'Width', 'geolocation' ); ?>:</strong>
					<input type="number" min="1" name="geolocation_map_width" value="<?php echo esc_attr( $map_width ); ?>" />px<br />
					<strong><?php esc_html_e( 'Height', 'geolocation' ); ?>:</strong>
					<input type="number" min="1" name="geolocation_map_height" value="<?php echo esc_attr( $map_height ); ?>" />px
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Position', 'geolocation' ); ?></th>
				<td class="position">
					<input type="radio" id="geolocation_map_position_before" name="geolocation_map_position" value="before" <?php checked( get_option( 'geolocation_map_position' ), 'before' ); ?>>
					<label for="geolocation_map_position_before"><?php esc_html_e( 'Before the post.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_position_after" name="geolocation_map_position" value="after" <?php checked( get_option( 'geolocation_map_position' ), 'after' ); ?>>
					<label for="geolocation_map_position_after"><?php esc_html_e( 'After the post.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_position_shortcode" name="geolocation_map_position" value="shortcode" <?php checked( get_option( 'geolocation_map_position' ), 'shortcode' ); ?>>
					<label for="geolocation_map_position_shortcode">
						<?php esc_html_e( 'Wherever I put the shortcode: ', 'geolocation' ); ?>
						<?php echo esc_html( $shortcode ); ?>.
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'How would you like your geolocation to be displayed?', 'geolocation' ); ?></th>
				<td class="display">
					<input type="radio" id="geolocation_map_display_plain" name="geolocation_map_display" value="plain" <?php checked( get_option( 'geolocation_map_display' ), 'plain' ); ?>>
					<label for="geolocation_map_display_plain"><?php esc_html_e( 'Plain text.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_display_link" name="geolocation_map_display" value="link" <?php checked( get_option( 'geolocation_map_display' ), 'link' ); ?>>
					<label for="geolocation_map_display_link"><?php esc_html_e( 'Simple link w/hover.', 'geolocation' ); ?></label><br />
					<input type="radio" id="geolocation_map_display_map" name="geolocation_map_display" value="map" <?php checked( get_option( 'geolocation_map_display' ), 'map' ); ?>>
					<label for="geolocation_map_display_map"><?php esc_html_e( 'Simple map (static).', 'geolocation' ); ?></label><br />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Default Zoom Level', 'geolocation' ); ?></th>
				<td class="zoom">
					<?php
					$zoom_levels = array(
						'1'  => __( 'Globe', 'geolocation' ),
						'3'  => __( 'Country', 'geolocation' ),
						'6'  => __( 'State', 'geolocation' ),
						'9'  => __( 'City', 'geolocation' ),
						'16' => __( 'Street', 'geolocation' ),
						'18' => __( 'Block', 'geolocation' ),
					);
					foreach ( $zoom_levels as $value => $label ) :
						?>
						<input type="radio" id="geolocation_default_zoom_<?php echo esc_attr( $value ); ?>" name="geolocation_default_zoom" value="<?php echo esc_attr( $value ); ?>" <?php checked( $default_zoom, $value ); ?>>
						<label for="geolocation_default_zoom_<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
					<br />
					<?php echo geolocation_get_geo_div(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in geolocation_get_geo_div(). ?>
				</td>
			</tr>
			<tr>
				<th scope="row"></th>
				<td class="position">
					<input type="checkbox" id="geolocation_wp_pin" name="geolocation_wp_pin" value="1" <?php checked( $wp_pin ); ?>>
					<label for="geolocation_wp_pin"><?php esc_html_e( 'Show your support for WordPress by using the WordPress map pin.', 'geolocation' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Dimensions Page', 'geolocation' ); ?></th>
				<td class="dimensions">
					<strong><?php esc_html_e( 'Width', 'geolocation' ); ?>:</strong>
					<input type="number" min="1" name="geolocation_map_width_page" value="<?php echo esc_attr( $map_width_page ); ?>" />px<br />
					<strong><?php esc_html_e( 'Height', 'geolocation' ); ?>:</strong>
					<input type="number" min="1" name="geolocation_map_height_page" value="<?php echo esc_attr( $map_height_page ); ?>" />px
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Maps Provider', 'geolocation' ); ?></th>
				<td>
					<select id="geolocation_provider" name="geolocation_provider">
						<option value="google" <?php selected( $provider, 'google' ); ?>>Google Maps</option>
						<option value="osm" <?php selected( $provider, 'osm' ); ?>>Open Street Maps</option>
					</select>
				</td>
			</tr>
			<tr class="google-apikey" style="<?php echo 'google' === $provider ? '' : 'display:none;'; ?>">
				<th scope="row"><?php esc_html_e( 'Google Maps API key', 'geolocation' ); ?></th>
				<td>
					<input type="text" name="geolocation_google_maps_api_key" value="<?php echo esc_attr( $google_maps_api_key ); ?>" />
				</td>
			</tr>
			<tr class="osm-urls" style="<?php echo 'osm' === $provider ? '' : 'display:none;'; ?>">
				<th scope="row"><?php esc_html_e( 'OSM URLs', 'geolocation' ); ?></th>
				<td>
					<table>
						<?php if ( is_plugin_active( 'osm-tiles-proxy/osm-tiles-proxy.php' ) ) : ?>
							<tr>
								<th><?php esc_html_e( 'Use Proxy', 'geolocation' ); ?></th>
								<td>
									<input type="checkbox" id="geolocation_osm_use_proxy" name="geolocation_osm_use_proxy" value="1" <?php checked( $osm_use_proxy ); ?>>
									<label for="geolocation_osm_use_proxy"><?php esc_html_e( 'Make use of proxy plugin.', 'geolocation' ); ?></label>
								</td>
							</tr>
						<?php endif; ?>
						<tr>
							<th><?php esc_html_e( 'Strict privacy mode (GDPR)', 'geolocation' ); ?></th>
							<td>
								<input type="checkbox" id="geolocation_osm_strict_privacy" name="geolocation_osm_strict_privacy" value="1" <?php checked( $osm_strict_privacy ); ?>>
								<label for="geolocation_osm_strict_privacy"><?php esc_html_e( 'Only show maps if the tiles are delivered by the proxy plugin.', 'geolocation' ); ?></label>
								<p class="description"><?php esc_html_e( 'If the proxy is not available, only the location text is shown and the browsers of your visitors do not connect to external map servers.', 'geolocation' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="geolocation_osm_tiles_url"><?php esc_html_e( 'Tiles url (Caching)', 'geolocation' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="geolocation_osm_tiles_url" name="geolocation_osm_tiles_url" value="<?php echo esc_attr( $osm_tiles_url ); ?>" />
								<?php if ( geolocation_get_osm_tiles_url() !== (string) get_option( 'geolocation_osm_tiles_url' ) ) : ?>
									<br /><code><?php echo esc_html( geolocation_get_osm_tiles_url() ); ?></code>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Leaflet JS', 'geolocation' ); ?></th>
							<td><?php echo esc_html( geolocation_get_osm_leaflet_js_url() ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Leaflet CSS', 'geolocation' ); ?></th>
							<td><?php echo esc_html( geolocation_get_osm_leaflet_css_url() ); ?></td>
						</tr>
						<tr>
							<th><label for="geolocation_osm_nominatim_url"><?php esc_html_e( 'Nominatim ([Reverse-]Geocoding)', 'geolocation' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="geolocation_osm_nominatim_url" name="geolocation_osm_nominatim_url" value="<?php echo esc_attr( $osm_nominatim_url ); ?>" />
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Used Language for Addresses', 'geolocation' ); ?></th>
				<td><?php echo esc_html( $site_lang ); ?></td>
			</tr>
			<tr>
				<th scope="row"></th>
				<td class="position">
					<input type="checkbox" id="geolocation_updateAddresses" name="geolocation_updateAddresses" value="1">
					<label for="geolocation_updateAddresses"><?php esc_html_e( 'Update all addresses from posts that have location information (only once this setup is saved).', 'geolocation' ); ?></label>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes', 'geolocation' ); ?>" />
		</p>
	</form>
	</div>
	<?php
}
?>
