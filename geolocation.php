<?php
/**
 * Plugin Name: Geolocation
 * Plugin URI: https://wordpress.org/extend/plugins/geolocation/
 * Description: Displays post geotag information on an embedded map.
 * Version: 1.9.7
 * Author: Yann Michel
 * Author URI: https://www.yann-michel.de/geolocation
 * Text Domain: geolocation
 * License: GPL2
 */

/*
	Copyright 2010 Chris Boyd  (email : chris@chrisboyd.net)
	2018-2023 Yann Michel (email : geolocation@yann-michel.de)

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

define( 'GEOLOCATION__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GEOLOCATION__VERSION', '1.8.2' );

add_action( 'upgrader_process_complete', 'plugin_upgrade_completed', 10, 2 );
add_action( 'plugins_loaded', 'languages_init' );
add_action( 'wp_head', 'add_geo_support' );
add_action( 'admin_menu', 'add_settings' );
add_filter( 'the_content', 'display_location', 5 );
admin_init();
register_activation_hook( __FILE__, 'activate' );
register_uninstall_hook( __FILE__, 'uninstall' );

require_once GEOLOCATION__PLUGIN_DIR . 'geolocation.settings.php';
// To do: add support for multiple Map API providers.
switch ( get_option( 'geolocation_provider' ) ) {
	case 'google':
		require_once GEOLOCATION__PLUGIN_DIR . 'geolocation.map-provider-google.php';
		break;
	case 'osm':
		require_once GEOLOCATION__PLUGIN_DIR . 'geolocation.map-provider-osm.php';
		break;
}

/**
 * Append provided links for support and faq.
 *
 * @param [type] $links_array The array to be extended.
 * @param [type] $plugin_file_name The Plugin filename to be registered.
 * @return mixed
 */
function geolocation_append_support_and_faq_links( $links_array, $plugin_file_name ) {
	if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
		$links_array[] = '<a href="https://wordpress.org/support/plugin/geolocation/reviews/#new-post" target="_blank">' . __( 'Review', 'geolocation' ) . '</a>';
		$links_array[] = '<a href="https://wordpress.org/support/plugin/geolocation/#new-topic" target="_blank">' . __( 'Support', 'geolocation' ) . '</a>';
	}
	return $links_array;
}
add_filter( 'plugin_row_meta', 'geolocation_append_support_and_faq_links', 10, 2 );

/**
 * Append actions for cusstomizing/settigs of this plugin.
 *
 * @param [type] $links_array The array to be extended.
 * @param [type] $plugin_file_name The Plugin filename ro be registered.
 * @return mixed
 */
function geolocation_customizer_action_links( $links_array, $plugin_file_name ) {
	if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
		$config_link = '<a href="options-general.php?page=geolocation">' . __( 'Settings', 'geolocation' ) . '</a>';
		array_unshift( $links_array, $config_link );
	}
	return $links_array;
}
add_action( 'plugin_action_links', 'geolocation_customizer_action_links', 10, 2 );

/**
 * Post Plugin routine when completed.
 *
 * @param [type] $upgrader_object The object that is updated.
 * @param [type] $options The options for the upgraded object.
 * @return void
 */
function plugin_upgrade_completed( $upgrader_object, $options ) {
	$our_plugin = plugin_basename( __FILE__ );
	if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
		foreach ( $options['plugins'] as $plugin ) {
			if ( $plugin === $our_plugin ) {
				register_settings();
				default_settings();
			}
		}
	}
}

/**
 * Display custom admin notice in key is provided.
 *
 * @return void
 */
function geolocation_custom_admin_notice() {
	if ( ! get_option( 'geolocation_google_maps_api_key' ) && get_option( 'geolocation_provider' ) === 'google' ) { ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Google Maps API key is missing for', 'geolocation' ); ?> <a href="options-general.php?page=geolocation">Geolocation</a>!</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'geolocation_custom_admin_notice' );

/**
 * Add the custom box to the post editor.
 *
 * @return void
 */
function geolocation_add_custom_box() {
	if ( function_exists( 'add_meta_box' ) ) {
		add_meta_box( 'geolocation_sectionid', __( 'Geolocation', 'geolocation' ), 'geolocation_inner_custom_box', 'post', 'advanced' );
	} else {
		add_action( 'dbx_post_advanced', 'geolocation_old_custom_box' );
	}
}

/**
 * Provide the inner elements of the added custom box (for the editor).
 *
 * @return void
 */
function geolocation_inner_custom_box() {
	?>
	<input type="hidden" id="geolocation_nonce" name="geolocation_nonce" value="<?php echo esc_html( wp_create_nonce( plugin_basename( __FILE__ ) ) ); ?>" />
	<label class="screen-reader-text" for="geolocation-address">Geolocation</label>
	<div class="taghint"><?php echo esc_html_e( 'Enter your address', 'geolocation' ); ?></div>
	<input type="hidden" id="geolocation-address-reverse" name="geolocation-address-reverse" class="newtag form-input-tip" size="25" autocomplete="off" value="" />
	<input type="text" id="geolocation-address" name="geolocation-address" class="newtag form-input-tip" size="25" autocomplete="off" value="" />
	<input id="geolocation-load" type="button" class="button geolocationadd" value="<?php echo esc_html_e( 'Load', 'geolocation' ); ?>" tabindex="3" />
	<input type="hidden" id="geolocation-latitude" name="geolocation-latitude" />
	<input type="hidden" id="geolocation-longitude" name="geolocation-longitude" />
	<div id="geolocation-map" style="border:solid 1px #c6c6c6;width:<?php echo esc_attr( (string) get_option( 'geolocation_map_width' ) ); ?>px;height:<?php echo esc_attr( (string) get_option( 'geolocation_map_height' ) ); ?>px;margin-top:5px;"></div>
	<div style="margin:5px 0 0 0;">
		<input id="geolocation-public" name="geolocation-public" type="checkbox" value="1" />
		<label for="geolocation-public"><?php echo esc_html_e( 'Public', 'geolocation' ); ?></label>
		<div style="float:right">
			<input id="geolocation-enabled" name="geolocation-on" type="radio" value="1" />
			<label for="geolocation-enabled"><?php echo esc_html_e( 'On', 'geolocation' ); ?></label>
			<input id="geolocation-disabled" name="geolocation-on" type="radio" value="0" />
			<label for="geolocation-disabled"><?php echo esc_html_e( 'Off', 'geolocation' ); ?></label>
		</div>
	</div>
	<?php
}

/**
 * Prints the edit form for pre-WordPress 2.5 post/page
 *
 * @return void
 */
function geolocation_old_custom_box() {
	?>
	<div class="dbx-b-ox-wrapper">
		<fieldset id="geolocation_fieldsetid" class="dbx-box">
			<div class="dbx-h-andle-wrapper">
				<h3 class="dbx-handle"><?php echo esc_html_e( 'Geolocation', 'geolocation' ); ?></h3>
			</div>
			<div class="dbx-c-ontent-wrapper">
				<div class="dbx-content">
					<?php
					geolocation_inner_custom_box();
					?>
				</div>
			</div>
		</fieldset>
	</div>
	<?php
}

/**
 * Save the post and derive geo metadata.
 *
 * @param [type] $post_id The posts id.
 * @return int
 */
function geolocation_save_postdata( $post_id ) {
	// Check authorization, permissions, autosave, etc.
	if ( (!isset($_POST['geolocation_nonce']))  ||
		( ! wp_verify_nonce( $_POST['geolocation_nonce'], plugin_basename( __FILE__ ) ) ) ||
		( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		( ( 'page' === $_POST['post_type'] ) && ( ! current_user_can( 'edit_page', $post_id ) ) ) ||
		( ! current_user_can( 'edit_post', $post_id ) )
	) {
		return $post_id;
	}

	$latitude        = clean_coordinate( $_POST['geolocation-latitude'] );
	$longitude       = clean_coordinate( $_POST['geolocation-longitude'] );
	$address         = $_POST['geolocation-address'];
	$address_reverse = $_POST['geolocation-address-reverse'];

	if ( ( empty( $latitude ) ) || ( empty( $longitude ) ) ) {
		// check the featured image for geodata if no data was available in the post already.
		$post_img_id = get_post_thumbnail_id();
		if ( 0 !== $post_img_id ) {
			$orig_img_path = wp_get_original_image_path( $post_img_id, false );
			if ( false !== $orig_img_path ) {
				$exif = exif_read_data( $orig_img_path, 0, true );

				if ( ( isset( $exif['GPS']['GPSLatitude'] ) ) && ( isset( $exif['GPS']['GPSLongitude'] ) ) ) {
					$gps_latitude   = $exif['GPS']['GPSLatitude'];
					$gps_latitude_g = explode( '/', $gps_latitude[0] );
					$gps_latitude_m = explode( '/', $gps_latitude[1] );
					$gps_latitude_s = explode( '/', $gps_latitude[2] );
					$gps_lat_g      = $gps_latitude_g[0] / $gps_latitude_g[1];
					$gps_lat_m      = $gps_latitude_m[0] / $gps_latitude_m[1];
					$gps_lat_s      = $gps_latitude_s[0] / $gps_latitude_s[1];
					$latitude       = $gps_lat_g + ( $gps_lat_m + ( $gps_lat_s / 60 ) ) / 60;

					$gps_longitude   = $exif['GPS']['GPSLongitude'];
					$gps_longitude_g = explode( '/', $gps_longitude[0] );
					$gps_longitude_m = explode( '/', $gps_longitude[1] );
					$gps_longitude_s = explode( '/', $gps_longitude[2] );
					$gps_lon_g       = $gps_longitude_g[0] / $gps_longitude_g[1];
					$gps_lon_m       = $gps_longitude_m[0] / $gps_longitude_m[1];
					$gps_lon_s       = $gps_longitude_s[0] / $gps_longitude_s[1];
					$longitude       = $gps_lon_g + ( $gps_lon_m + ( $gps_lon_s / 60 ) ) / 60;
				}
			}
		}
	}

	if ( ( ! empty( $latitude ) ) && ( ! empty( $longitude ) ) ) {
		update_post_meta( $post_id, 'geo_latitude', $latitude );
		update_post_meta( $post_id, 'geo_longitude', $longitude );

		if ( ( '' === $address ) || ( $address === $address_reverse ) ) {
			$address = reverse_geocode( $latitude, $longitude );
		}
		if ( '' !== $address ) {
			update_post_meta( $post_id, 'geo_address', $address );
		}

		$address_reverse = reverse_geocode( $latitude, $longitude );
		update_post_meta( $post_id, 'geo_address_reverse', $address_reverse );

		if ( $_POST['geolocation-on'] ) {
			update_post_meta( $post_id, 'geo_enabled', 1 );
		} else {
			update_post_meta( $post_id, 'geo_enabled', 0 );
		}

		if ( $_POST['geolocation-public'] ) {
			update_post_meta( $post_id, 'geo_public', 1 );
		} else {
			update_post_meta( $post_id, 'geo_public', 0 );
		}
	}

	return $post_id;
}

/**
 * Initialize core functionality, i.e., post, menu and save.
 *
 * @return void
 */
function admin_init() {
	add_action( 'admin_head-post-new.php', 'admin_head' );
	add_action( 'admin_head-post.php', 'admin_head' );
	add_action( 'admin_menu', 'geolocation_add_custom_box' );
	add_action( 'save_post_post', 'geolocation_save_postdata' );
}

/**
 * Generate the header section, i.e., the post edit tools.
 *
 * @return void
 */
function admin_head() {
	// To do: add support for multiple Map API providers.
	switch ( get_option( 'geolocation_provider' ) ) {
		case 'google':
			admin_head_google();
			break;
		case 'osm':
			admin_head_osm();
			break;
	}
}

/**
 * Provide the DIV tage according to the definition and parameters.
 *
 * @return mixed
 */
function get_geo_div( $id = null, $name = 'me' ) {
	$width  = esc_attr( (string) get_option( 'geolocation_map_width' ) );
	$height = esc_attr( (string) get_option( 'geolocation_map_height' ) );
	return '<div id="map' . $id . '" class="geolocation-map" name="' . $name . '" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
}

/**
 * Print the DIV tag.
 *
 * @return void
 */
function add_geo_div() {
	if ( ( esc_attr( (string) get_option( 'geolocation_map_display' ) ) !== 'plain' ) ) {
		echo get_geo_div();
	}
}

/**
 * Add header funtions for supporting the geolocation map depending on the used provider.
 *
 * @return void
 */
function add_geo_support() {
	global $posts;
	$tmp_posts = $posts;
	$geo_count = 0;
	// evaluate if the posts to be shown have geo data.
	foreach ( $tmp_posts as $post ) {
			$latitude  = get_post_meta( $post->ID, 'geo_latitude', true );
			$longitude = get_post_meta( $post->ID, 'geo_longitude', true );
			$on        = (bool) get_post_meta( $post->ID, 'geo_enabled', true );
			$public    = (bool) get_post_meta( $post->ID, 'geo_public', true );
		if ( ! ( empty( $latitude )
		|| empty( $longitude )
		|| '' === $on
		|| false === $on
		|| ( ( '' === $public || false === $public ) && ( ! is_user_logged_in() ) )
			) ) {
			++$geo_count;
		}
	}

	// only enable geo support if there is geodata available to be shown.
	if ( $geo_count > 0 ) {
		add_action( 'wp_footer', 'add_geo_div' );
		if ( ( esc_attr( (string) get_option( 'geolocation_map_display' ) ) !== 'plain' ) || ( is_user_logged_in() ) ) {
			wp_enqueue_style( 'geolocation_css', esc_url( plugins_url( 'style.css', __FILE__ ) ), array(), GEOLOCATION__VERSION, 'all' );
			// To do: add support for multiple Map API providers.
			switch ( get_option( 'geolocation_provider' ) ) {
				case 'google':
					add_geo_support_google( $posts );
					break;
				case 'osm':
					add_geo_support_osm( $posts );
					break;
			}
		}
	}
}

/**
 * Check if a post has the defined shorttag to be replaced by the plugin.
 *
 * @param [type] $content The content to be checked for the shortcode.
 * @return boolean
 */
function geo_has_shortcode( $content ) {
	$pos = strpos( $content, esc_attr( (string) get_option( 'geolocation_shortcode' ) ) );
	if ( false === $pos ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Dieplay all needed funcitons per page or post.
 *
 * @param [type] $content The content the location shall be displayed for.
 * @return mixed
 */
function display_location( $content ) {
	default_settings();
	if ( is_page() ) {
		return display_location_page( $content );
	} else {
		return display_location_post( $content );
	}
}

/**
 * Provide the page's functionality for the selected provider.
 *
 * @param [type] $content The content the functionality shall be provided for.
 * @return mixed
 */
function display_location_page( $content ) {
	// To do: add support for multiple Map API providers.
	switch ( get_option( 'geolocation_provider' ) ) {
		case 'google':
			return display_location_page_google( $content );
		case 'osm':
			return display_location_page_osm( $content );
	}
}

/**
 * Provide the post's functionality for the selected provider.
 *
 * @param [type] $content The content the functionality shall be provided for.
 * @return mixed
 */
function display_location_post( $content ) {
	default_settings();
	$shortcode = get_option( 'geolocation_shortcode' );
	global $post;
	$html = '';
	settype( $html, 'string' );
	$latitude  = get_post_meta( $post->ID, 'geo_latitude', true );
	$longitude = get_post_meta( $post->ID, 'geo_longitude', true );
	$on        = (bool) get_post_meta( $post->ID, 'geo_enabled', true );
	$public    = (bool) get_post_meta( $post->ID, 'geo_public', true );

	if ( ( ( empty( $latitude ) ) || ( empty( $longitude ) ) ) ||
		( '' === $on || false === $on ) ||
		( ( '' === $public || false === $public ) && ( ! is_user_logged_in() ) )
	) {
		$content = str_replace( esc_attr( (string) $shortcode ), '', $content );
		return $content;
	}

	$address = (string) get_post_meta( $post->ID, 'geo_address', true );
	if ( empty( $address ) ) {
		$address = reverse_geocode( $latitude, $longitude );
		// obviously was missing so add to post for future performance improvement.
		update_post_meta( $post->ID, 'geo_address', $address );
	}

	switch ( esc_attr( (string) get_option( 'geolocation_map_display' ) ) ) {
		case 'plain':
			$html = '<div class="geolocation-plain" id="geolocation' . $post->ID . '">' . __( 'Posted from ', 'geolocation' ) . esc_html( $address ) . '.</div>';
			break;
		case 'link':
			$html = '<div><a class="geolocation-link" href="#" id="geolocation' . $post->ID . '" name="' . $latitude . ',' . $longitude . '" onclick="return false;">' . __( 'Posted from ', 'geolocation' ) . esc_html( $address ) . '.</a></div>';
			break;
		case 'map':
			$html = '<div class="geolocation-link" id="geolocation' . $post->ID . '">' . __( 'Posted from ', 'geolocation' ) . esc_html( $address ) . ':</div>' . get_geo_div( $post->ID, $latitude . ',' . $longitude );
			break;
		case 'debug':
			$html = '<pre> $latitude: ' . $latitude . '<br> $longitude: ' . $longitude . '<br> $address: ' . $address . '<br> $on: ' . (string) $on . '<br> $public: ' . (string) $public . '</pre>';
			break;
	}

	switch ( esc_attr( (string) get_option( 'geolocation_map_position' ) ) ) {
		case 'before':
			$content = str_replace( esc_attr( (string) $shortcode ), '', $content );
			$content = $html . '<br/><br/>' . $content;
			break;
		case 'after':
			$content = str_replace( esc_attr( (string) $shortcode ), '', $content );
			$content = $content . '<br/><br/>' . $html;
			break;
		case 'shortcode':
			$content = str_replace( esc_attr( (string) $shortcode ), $html, $content );
			break;
	}
	return $content;
}

/**
 * Update all posts' addresses if there is public geo data available.
 *
 * @return void
 */
function update_geolocation_addresses() {
	$args = array(
		'post_type'      => 'post',
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
		),
	);

	$post_query = new WP_Query( $args );
	if ( $post_query->have_posts() ) {
		$counter = 0;
		while ( $post_query->have_posts() ) {
			$post_query->the_post();
			$post_id          = (int) get_the_ID();
			$post_latitude    = get_post_meta( $post_id, 'geo_latitude', true );
			$post_longitude   = get_post_meta( $post_id, 'geo_longitude', true );
			$post_address_new = (string) reverse_geocode( $post_latitude, $post_longitude );
			update_post_meta( $post_id, 'geo_address', $post_address_new );
			++$counter;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $counter ) . ' ' . esc_html__( 'Addresses have been updated!', 'geolocation' ) . '</p></div>';
	}
}

/**
 * Build a stable address for the given attruibutes (to be later shown at the DIV).
 *
 * @param [type] $city The name of the city of the location.
 * @param [type] $state The name of the state of the location.
 * @param [type] $country The name of the countr of the location.
 * @return mixed
 */
function build_addresses( $city, $state, $country ) {
	$address = '';
	if ( ( $city != '' ) && ( $state != '' ) && ( $country != '' ) ) {
		$address = $city . ', ' . $state . ', ' . $country;
	} elseif ( ( $city != '' ) && ( $state != '' ) ) {
		$address = $city . ', ' . $state;
	} elseif ( ( $state != '' ) && ( $country != '' ) ) {
		$address = $state . ', ' . $country;
	} elseif ( $country != '' ) {
		$address = $country;
	}
	return esc_html( $address );
}

/**
 * Reverse geocode the GPS data into readyble names.
 *
 * @param [type] $latitude The Latitude of the location.
 * @param [type] $longitude The longitude of the location.
 * @return mixed
 */
function reverse_geocode( $latitude, $longitude ) {
	$city    = '';
	$state   = '';
	$country = '';
	//
	// To do: add support for multiple Map API providers.
	switch ( get_option( 'geolocation_provider' ) ) {
		case 'google':
			$json = pull_json_google( $latitude, $longitude );
			foreach ( $json->results as $result ) {
				foreach ( $result->address_components as $address_part ) {
					if ( in_array( 'political', $address_part->types, true ) ) {
						if ( ( in_array( 'locality', $address_part->types, true ) ) ) {
							$city = $address_part->long_name;
						} elseif ( ( in_array( 'administrative_area_level_1', $address_part->types, true ) ) ) {
							$state = $address_part->long_name;
						} elseif ( ( in_array( 'country', $address_part->types, true ) ) ) {
							$country = $address_part->long_name;
						}
					}
				}
			}
			break;
		case 'osm':
			$json    = pull_json_osm( $latitude, $longitude );
			if ( isset($json['address']['city'])) {
				$city    = $json['address']['city'];
			} else if ( isset($json['address']['town'])) {
	                        $city    = $json['address']['town'];
			}
			if ( isset($json['address']['suburb'])) {
				$state   = $json['address']['suburb'];
			} else if ( isset($json['address']['municipality'])) {
				$state   = $json['address']['municipality'];
			}
			if ( isset($json['address']['country'])) {
				$country = $json['address']['country'];
			}
			break;
	}
	/**
	 * Build a stable address for the given attruibutes (to be later shown at the DIV).
	 *
	 * @param [type] $city
	 * @param [type] $state
	 * @param [type] $country
	 * @return void
	 */
	return build_addresses( $city, $state, $country );
}

/**
 * Clean the given coordinates.
 *
 * @param [type] $coordinate The coordinates to be cleaned.
 * @return mixed
 */
function clean_coordinate( $coordinate ) {
	$pattern = '/^(\-)?(\d{1,3})\.(\d{1,15})/';
	preg_match( $pattern, $coordinate, $matches );
	if ( null === $matches ) {
		return '';
	}
	return isset( $matches[0] ) ? $matches[0] : '';
}

/**
 * Provide checked attribute in case an option was true.
 *
 * @param [type] $field The attribute to test.
 * @return mixed
 */
function is_checked( $field ) {
	if ( (bool) get_option( $field ) ) {
		echo ' checked="checked" ';
	}
}

/**
 * Provide checked attribute in case an options value is the given one.
 *
 * @param [type] $field The attribute to test.
 * @param [type] $value The value to test for.
 * @return mixed
 */
function is_value( $field, $value ) {
	if ( (string) get_option( $field ) === $value ) {
		echo ' checked="checked" ';
	}
}

?>
