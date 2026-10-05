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
 * Add the help tab explaining the shortcode and its attributes to the settings page.
 *
 * @return void
 */
function geolocation_add_help_tab() {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}

	// The examples use the shortcode as it is configured.
	$shortcode = trim( (string) get_option( 'geolocation_shortcode' ) );
	$tag       = preg_match( '/^\[([^\[\]\s]+)\]$/', $shortcode, $matches ) ? $matches[1] : 'geolocation';
	$rows      = array(
		'cat'    => array( __( 'Categories as slugs, names or ids, separated by commas.', 'geolocation' ), 'cat="travel,europe"' ),
		'tag'    => array( __( 'Tags as slugs, names or ids, separated by commas.', 'geolocation' ), 'tag="hiking"' ),
		'width'  => array( __( 'Width of the map in pixels or percent.', 'geolocation' ), 'width="100%"' ),
		'height' => array( __( 'Height of the map in pixels.', 'geolocation' ), 'height="400"' ),
		'zoom'   => array( __( 'Fixed zoom level from 1 to 19. Without it the map is fitted to the markers.', 'geolocation' ), 'zoom="6"' ),
		'route'  => array( __( 'Connects the locations with a line, in the order of the post dates.', 'geolocation' ), 'route="1"' ),
	);

	$content  = '<p>' . esc_html__( 'Put the shortcode on a page to show a map with the locations of your posts. The following attributes are optional and can be combined.', 'geolocation' ) . '</p>';
	$content .= '<table class="widefat striped" style="max-width:760px;"><thead><tr>';
	$content .= '<th>' . esc_html__( 'Attribute', 'geolocation' ) . '</th>';
	$content .= '<th>' . esc_html__( 'Meaning', 'geolocation' ) . '</th>';
	$content .= '<th>' . esc_html__( 'Example', 'geolocation' ) . '</th>';
	$content .= '</tr></thead><tbody>';
	foreach ( $rows as $attribute => $row ) {
		$content .= '<tr><td><code>' . esc_html( $attribute ) . '</code></td>';
		$content .= '<td>' . esc_html( $row[0] ) . '</td>';
		$content .= '<td style="white-space:nowrap;"><code>' . esc_html( '[' . $tag . ' ' . $row[1] . ']' ) . '</code></td></tr>';
	}
	$content .= '</tbody></table>';
	$content .= '<p>' . esc_html__( 'The route follows the publication dates of the posts, oldest first, and connects the locations with straight lines. Combine it with "cat" to show the route of a single trip.', 'geolocation' ) . ' <code>' . esc_html( '[' . $tag . ' cat="italy-2026" route="1"]' ) . '</code></p>';
	$content .= '<p>' . esc_html__( 'A page can contain several maps.', 'geolocation' ) . ' ' . esc_html__( 'Without the attribute "cat" the custom field "category" of the page is used.', 'geolocation' ) . '</p>';

	$screen->add_help_tab(
		array(
			'id'      => 'geolocation-shortcode',
			'title'   => __( 'Shortcode', 'geolocation' ),
			'content' => $content,
		)
	);
}

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
					<p class="description"><?php esc_html_e( 'On pages the shortcode shows a map of all posts. Optional attributes: cat, tag, width, height, zoom and route, e.g. [geolocation cat="travel" height="400"].', 'geolocation' ); ?> <?php esc_html_e( 'All attributes are explained under "Help" at the top right of this page.', 'geolocation' ); ?></p>
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
								<?php $osm_proxy_tiles_url = geolocation_osm_proxy_tiles_url(); ?>
								<?php if ( '' !== $osm_proxy_tiles_url ) : ?>
									<p><strong><?php esc_html_e( 'Currently used (from the proxy plugin):', 'geolocation' ); ?></strong><br /><code><?php echo esc_html( $osm_proxy_tiles_url ); ?></code></p>
								<?php endif; ?>
								<input type="text" class="regular-text" id="geolocation_osm_tiles_url" name="geolocation_osm_tiles_url" value="<?php echo esc_attr( $osm_tiles_url ); ?>" />
								<p class="description">
									<?php
									if ( '' !== $osm_proxy_tiles_url ) {
										esc_html_e( 'Fallback: only used when the proxy is switched off or does not deliver tiles.', 'geolocation' );
									} else {
										esc_html_e( 'The address the map tiles are loaded from.', 'geolocation' );
									}
									?>
								</p>
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
