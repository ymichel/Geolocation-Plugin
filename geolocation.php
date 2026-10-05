<?php
/**
 * Plugin Name: Geolocation
 * Plugin URI: https://wordpress.org/extend/plugins/geolocation/
 * Description: Displays post geotag information on an embedded map.
 * Version: 1.12.0
 * Author: Yann Michel
 * Author URI: https://github.com/ymichel/Geolocation-Plugin/
 * Text Domain: geolocation
 * License: GPLv2+
 *
 * @package geolocation
 */

/*
	Copyright 2010 Chris Boyd  (email : chris@chrisboyd.net)
	2018-2026 Yann Michel (email : yann@michelpunkt.de)

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
define( 'GEOLOCATION__VERSION', '1.12.0' );
define( 'GEOLOCATION__UPDATE_BATCH_SIZE', 10 );

add_action( 'init', 'geolocation_languages_init' );
add_action( 'admin_init', 'geolocation_maybe_upgrade' );
add_action( 'admin_init', 'geolocation_register_settings' );
add_action( 'admin_menu', 'geolocation_add_settings' );
add_action( 'admin_notices', 'geolocation_custom_admin_notice' );
add_action( 'admin_enqueue_scripts', 'geolocation_admin_enqueue' );
add_action( 'add_meta_boxes_post', 'geolocation_add_custom_box' );
add_action( 'save_post_post', 'geolocation_save_postdata' );
add_action( 'save_post_post', 'geolocation_flush_page_markers' );
add_action( 'deleted_post', 'geolocation_flush_page_markers' );
add_action( 'geolocation_update_addresses_batch', 'geolocation_update_addresses_batch' );
add_filter( 'the_content', 'geolocation_display_location', 5 );
add_filter( 'plugin_row_meta', 'geolocation_append_support_and_faq_links', 10, 2 );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'geolocation_customizer_action_links' );
register_activation_hook( __FILE__, 'geolocation_activate' );
register_uninstall_hook( __FILE__, 'geolocation_uninstall' );

require_once GEOLOCATION__PLUGIN_DIR . 'geolocation-settings.php';
// To do: add support for multiple Map API providers.
switch ( get_option( 'geolocation_provider' ) ) {
	case 'google':
		require_once GEOLOCATION__PLUGIN_DIR . 'geolocation-map-provider-google.php';
		break;
	case 'osm':
		require_once GEOLOCATION__PLUGIN_DIR . 'geolocation-map-provider-osm.php';
		break;
}

/**
 * Append provided links for support and faq.
 *
 * @param array  $links_array The array to be extended.
 * @param string $plugin_file_name The plugin the links are shown for.
 * @return array
 */
function geolocation_append_support_and_faq_links( $links_array, $plugin_file_name ) {
	if ( plugin_basename( __FILE__ ) === $plugin_file_name ) {
		$links_array[] = '<a href="https://wordpress.org/support/plugin/geolocation/reviews/#new-post" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Review', 'geolocation' ) . '</a>';
		$links_array[] = '<a href="https://github.com/ymichel/Geolocation-Plugin/issues" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'geolocation' ) . '</a>';
	}
	return $links_array;
}

/**
 * Append actions for cusstomizing/settigs of this plugin.
 *
 * @param array $links_array The array to be extended.
 * @return array
 */
function geolocation_customizer_action_links( $links_array ) {
	$config_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=geolocation.php' ) ) . '">' . esc_html__( 'Settings', 'geolocation' ) . '</a>';
	array_unshift( $links_array, $config_link );
	return $links_array;
}

/**
 * Check whether maps must not be shown to visitors.
 *
 * In the strict privacy mode maps are only shown if the tiles are delivered by
 * the OSM tiles proxy, so the browsers of the visitors never connect to an
 * external map server. Without a working proxy only the location text is shown.
 *
 * @return boolean
 */
function geolocation_maps_blocked() {
	if ( 'osm' !== get_option( 'geolocation_provider' ) || ! (bool) get_option( 'geolocation_osm_strict_privacy' ) ) {
		return false;
	}
	return ! function_exists( 'geolocation_osm_proxy_tiles_url' ) || '' === geolocation_osm_proxy_tiles_url();
}

/**
 * Display the admin notices of this plugin.
 *
 * @return void
 */
function geolocation_custom_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings_url = admin_url( 'options-general.php?page=geolocation.php' );
	if ( 'google' === get_option( 'geolocation_provider' ) && ! get_option( 'geolocation_google_maps_api_key' ) ) {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Google Maps API key is missing for', 'geolocation' ); ?> <a href="<?php echo esc_url( $settings_url ); ?>">Geolocation</a>!</p>
		</div>
		<?php
	}
	if ( geolocation_maps_blocked() ) {
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Geolocation: strict privacy mode is active, but the tiles proxy is not available. Only the location text is shown.', 'geolocation' ); ?> <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Settings', 'geolocation' ); ?></a></p>
		</div>
		<?php
	}
}

/**
 * Add the custom box to the post editor.
 *
 * @return void
 */
function geolocation_add_custom_box() {
	add_meta_box( 'geolocation_sectionid', __( 'Geolocation', 'geolocation' ), 'geolocation_inner_custom_box', 'post', 'advanced' );
}

/**
 * Provide the inner elements of the added custom box (for the editor).
 *
 * @return void
 */
function geolocation_inner_custom_box() {
	?>
	<?php wp_nonce_field( plugin_basename( __FILE__ ), 'geolocation_nonce', false ); ?>
	<label class="screen-reader-text" for="geolocation-address"><?php esc_html_e( 'Geolocation', 'geolocation' ); ?></label>
	<div class="taghint"><?php esc_html_e( 'Enter your address', 'geolocation' ); ?></div>
	<input type="hidden" id="geolocation-address-reverse" name="geolocation-address-reverse" class="newtag form-input-tip" size="25" autocomplete="off" value="" />
	<input type="text" id="geolocation-address" name="geolocation-address" class="newtag form-input-tip" size="25" autocomplete="off" value="" />
	<input id="geolocation-load" type="button" class="button geolocationadd" value="<?php esc_attr_e( 'Load', 'geolocation' ); ?>" />
	<input id="geolocation-locate" type="button" class="button" value="<?php esc_attr_e( 'My location', 'geolocation' ); ?>" />
	<input id="geolocation-remove" type="button" class="button" value="<?php esc_attr_e( 'Remove location', 'geolocation' ); ?>" />
	<span id="geolocation-status" role="status" style="margin-left:5px;"></span>
	<input type="hidden" id="geolocation-remove-flag" name="geolocation-remove" value="" />
	<input type="hidden" id="geolocation-latitude" name="geolocation-latitude" />
	<input type="hidden" id="geolocation-longitude" name="geolocation-longitude" />
	<div id="geolocation-map" style="border:solid 1px #c6c6c6;width:<?php echo esc_attr( (string) get_option( 'geolocation_map_width' ) ); ?>px;height:<?php echo esc_attr( (string) get_option( 'geolocation_map_height' ) ); ?>px;margin-top:5px;"></div>
	<div style="margin:5px 0 0 0;">
		<input id="geolocation-public" name="geolocation-public" type="checkbox" value="1" />
		<label for="geolocation-public"><?php esc_html_e( 'Public', 'geolocation' ); ?></label>
		<div style="float:right">
			<input id="geolocation-enabled" name="geolocation-on" type="radio" value="1" />
			<label for="geolocation-enabled"><?php esc_html_e( 'On', 'geolocation' ); ?></label>
			<input id="geolocation-disabled" name="geolocation-on" type="radio" value="0" />
			<label for="geolocation-disabled"><?php esc_html_e( 'Off', 'geolocation' ); ?></label>
		</div>
	</div>
	<?php
}

/**
 * Convert an EXIF GPS coordinate (degrees, minutes, seconds as rationals) to a decimal value.
 *
 * @param mixed $parts The EXIF coordinate parts.
 * @param mixed $ref The EXIF hemisphere reference (N, S, E or W).
 * @return string
 */
function geolocation_exif_to_decimal( $parts, $ref ) {
	if ( ! is_array( $parts ) || count( $parts ) < 3 ) {
		return '';
	}
	$values = array();
	foreach ( array_slice( array_values( $parts ), 0, 3 ) as $part ) {
		$fraction = explode( '/', (string) $part );
		$divisor  = isset( $fraction[1] ) ? (float) $fraction[1] : 1.0;
		if ( ! is_numeric( $fraction[0] ) || 0.0 === $divisor ) {
			return '';
		}
		$values[] = (float) $fraction[0] / $divisor;
	}
	$decimal = $values[0] + ( $values[1] + ( $values[2] / 60 ) ) / 60;
	if ( in_array( strtoupper( trim( (string) $ref ) ), array( 'S', 'W' ), true ) ) {
		$decimal = -$decimal;
	}
	return (string) $decimal;
}

/**
 * Read the geo position from the EXIF data of the post's featured image.
 *
 * @param int $post_id The posts id.
 * @return array Latitude and longitude, or an empty array if not available.
 */
function geolocation_get_featured_image_position( $post_id ) {
	if ( ! function_exists( 'exif_read_data' ) ) {
		return array();
	}
	$post_img_id = get_post_thumbnail_id( $post_id );
	if ( empty( $post_img_id ) || ! in_array( get_post_mime_type( $post_img_id ), array( 'image/jpeg', 'image/tiff' ), true ) ) {
		return array();
	}
	$orig_img_path = wp_get_original_image_path( $post_img_id, false );
	if ( empty( $orig_img_path ) || ! is_readable( $orig_img_path ) ) {
		return array();
	}
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- exif_read_data() raises warnings for images without or with broken EXIF data.
	$exif = @exif_read_data( $orig_img_path, 'GPS', true );
	if ( ! isset( $exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLongitude'] ) ) {
		return array();
	}
	$latitude  = geolocation_exif_to_decimal( $exif['GPS']['GPSLatitude'], isset( $exif['GPS']['GPSLatitudeRef'] ) ? $exif['GPS']['GPSLatitudeRef'] : 'N' );
	$longitude = geolocation_exif_to_decimal( $exif['GPS']['GPSLongitude'], isset( $exif['GPS']['GPSLongitudeRef'] ) ? $exif['GPS']['GPSLongitudeRef'] : 'E' );
	if ( '' === $latitude || '' === $longitude ) {
		return array();
	}
	return array( $latitude, $longitude );
}

/**
 * Get a sanitized text value from the submitted post data.
 *
 * The nonce is verified by the caller, geolocation_save_postdata().
 *
 * @param string $key The name of the submitted field.
 * @return string
 */
function geolocation_get_posted_value( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
}

/**
 * Save the post and derive geo metadata.
 *
 * @param [type] $post_id The posts id.
 * @return int
 */
function geolocation_save_postdata( $post_id ) {
	// Check authorization, permissions, autosave, etc.
	if ( ( ! isset( $_POST['geolocation_nonce'] ) ) ||
		( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['geolocation_nonce'] ) ), plugin_basename( __FILE__ ) ) ) ||
		( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		( ( 'page' === geolocation_get_posted_value( 'post_type' ) ) && ( ! current_user_can( 'edit_page', $post_id ) ) ) ||
		( ! current_user_can( 'edit_post', $post_id ) )
	) {
		return $post_id;
	}

	$latitude        = geolocation_clean_coordinate( geolocation_get_posted_value( 'geolocation-latitude' ) );
	$longitude       = geolocation_clean_coordinate( geolocation_get_posted_value( 'geolocation-longitude' ) );
	$address         = geolocation_get_posted_value( 'geolocation-address' );
	$address_reverse = geolocation_get_posted_value( 'geolocation-address-reverse' );

	if ( '1' === geolocation_get_posted_value( 'geolocation-remove' ) && empty( $latitude ) && empty( $longitude ) ) {
		// The location has been removed in the editor.
		foreach ( array( 'geo_latitude', 'geo_longitude', 'geo_address', 'geo_address_reverse', 'geo_enabled', 'geo_public' ) as $meta_key ) {
			delete_post_meta( $post_id, $meta_key );
		}
		return $post_id;
	}

	if ( ( empty( $latitude ) ) || ( empty( $longitude ) ) ) {
		// check the featured image for geodata if no data was available in the post already.
		$position = geolocation_get_featured_image_position( $post_id );
		if ( ! empty( $position ) ) {
			list( $latitude, $longitude ) = $position;
		}
	}

	if ( ( ! empty( $latitude ) ) && ( ! empty( $longitude ) ) ) {
		update_post_meta( $post_id, 'geo_latitude', $latitude );
		update_post_meta( $post_id, 'geo_longitude', $longitude );

		$address_geocoded = geolocation_reverse_geocode( $latitude, $longitude );
		if ( ( '' === $address ) || ( $address === $address_reverse ) ) {
			$address = $address_geocoded;
		}
		if ( '' !== $address ) {
			update_post_meta( $post_id, 'geo_address', wp_slash( $address ) );
		}
		update_post_meta( $post_id, 'geo_address_reverse', wp_slash( $address_geocoded ) );

		update_post_meta( $post_id, 'geo_enabled', empty( geolocation_get_posted_value( 'geolocation-on' ) ) ? 0 : 1 );
		update_post_meta( $post_id, 'geo_public', empty( geolocation_get_posted_value( 'geolocation-public' ) ) ? 0 : 1 );
	}

	return $post_id;
}

/**
 * Enqueue the scripts for the post edit tools.
 *
 * @param string $hook_suffix The current admin page.
 * @return void
 */
function geolocation_admin_enqueue( $hook_suffix ) {
	$post = get_post();
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! $post || 'post' !== $post->post_type ) {
		return;
	}
	// To do: add support for multiple Map API providers.
	switch ( get_option( 'geolocation_provider' ) ) {
		case 'google':
			geolocation_admin_enqueue_google( $post->ID );
			break;
		case 'osm':
			geolocation_admin_enqueue_osm( $post->ID );
			break;
	}
}

/**
 * Get the map settings shared by all scripts.
 *
 * @return array
 */
function geolocation_get_map_settings() {
	return array(
		'zoom'         => (int) get_option( 'geolocation_default_zoom' ),
		'usePin'       => (bool) get_option( 'geolocation_wp_pin' ),
		'pinUrl'       => plugins_url( 'img/wp_pin.png', __FILE__ ),
		'pinShadowUrl' => plugins_url( 'img/wp_pin_shadow.png', __FILE__ ),
	);
}

/**
 * Get the map settings and the geo data of a post for the post editor's script.
 *
 * @param int $post_id The posts id.
 * @return array
 */
function geolocation_get_admin_post_data( $post_id ) {
	return array_merge(
		geolocation_get_map_settings(),
		array(
			'latitude'       => (string) get_post_meta( $post_id, 'geo_latitude', true ),
			'longitude'      => (string) get_post_meta( $post_id, 'geo_longitude', true ),
			'address'        => (string) get_post_meta( $post_id, 'geo_address', true ),
			'addressReverse' => (string) get_post_meta( $post_id, 'geo_address_reverse', true ),
			'isPublic'       => (string) get_post_meta( $post_id, 'geo_public', true ),
			'isEnabled'      => (string) get_post_meta( $post_id, 'geo_enabled', true ),
			'i18n'           => array(
				'locateFailed' => __( 'Your location could not be determined.', 'geolocation' ),
			),
		)
	);
}

/**
 * Enqueue the frontend scripts and styles of the selected provider.
 *
 * @return void
 */
function geolocation_enqueue_front() {
	// To do: add support for multiple Map API providers.
	switch ( get_option( 'geolocation_provider' ) ) {
		case 'google':
			geolocation_enqueue_front_google();
			break;
		case 'osm':
			geolocation_enqueue_front_osm();
			break;
	}
}

/**
 * Provide the DIV tage according to the definition and parameters.
 *
 * @param mixed  $id The suffix of the DIV's id, usually the post id.
 * @param string $position The location to be shown as "latitude,longitude", empty for the popup map.
 * @return string The escaped HTML of the DIV.
 */
function geolocation_get_geo_div( $id = null, $position = '' ) {
	$width  = esc_attr( (string) get_option( 'geolocation_map_width' ) );
	$height = esc_attr( (string) get_option( 'geolocation_map_height' ) );
	$data   = '' === $position ? '' : ' data-geolocation="' . esc_attr( (string) $position ) . '"';
	return '<div id="map' . esc_attr( (string) $id ) . '" class="geolocation-map"' . $data . ' style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
}

/**
 * Print the DIV tag.
 *
 * @return void
 */
function geolocation_add_geo_div() {
	echo geolocation_get_geo_div(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in geolocation_get_geo_div().
}

/**
 * Check if the geo data of a post may be shown to the current visitor.
 *
 * The stored flags are interpreted as booleans and not compared as strings.
 *
 * @param int $post_id The id of the post to check.
 * @return boolean
 */
function geolocation_post_is_visible( $post_id ) {
	$latitude  = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_latitude', true ) );
	$longitude = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_longitude', true ) );
	$on        = (bool) get_post_meta( $post_id, 'geo_enabled', true );
	$public    = (bool) get_post_meta( $post_id, 'geo_public', true );

	if ( empty( $latitude ) || empty( $longitude ) || ! $on ) {
		return false;
	}
	return $public || is_user_logged_in();
}

/**
 * Build the query arguments for the overview map of a page.
 *
 * Only posts having coordinates are selected here. Whether a post is shown
 * is decided afterwards by geolocation_post_is_visible().
 *
 * @param int $category_id The id of the category to filter for (0 = all).
 * @return array
 */
function geolocation_page_query_args( $category_id ) {
	return array(
		'post_type'      => 'post',
		'cat'            => $category_id,
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => 'geo_latitude',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'geo_longitude',
				'compare' => 'EXISTS',
			),
		),
	);
}

/**
 * Diagnostic output for overview pages, only shown with ?geodebug=1 in the URL.
 *
 * @param string $category The category name taken from the custom field.
 * @param int    $category_id The resolved category id.
 * @param int    $candidates Number of posts having coordinates.
 * @param int    $shown Number of posts finally put on the map.
 * @param bool   $shortcode_found Whether the shortcode was found in the page content.
 * @return string
 */
function geolocation_page_debug( $category, $category_id, $candidates, $shown, $shortcode_found ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostic switch.
	if ( ! isset( $_GET['geodebug'] ) ) {
		return '';
	}
	$info = array(
		'logged_in'       => is_user_logged_in(),
		'category'        => $category,
		'category_id'     => (int) $category_id,
		'shortcode_found' => (bool) $shortcode_found,
		'candidates'      => (int) $candidates,
		'shown'           => (int) $shown,
	);
	return '<pre class="geolocation-debug">GEODEBUG ' . esc_html( wp_json_encode( $info ) ) . '</pre>';
}

/**
 * Dieplay all needed funcitons per page or post.
 *
 * @param [type] $content The content the location shall be displayed for.
 * @return mixed
 */
function geolocation_display_location( $content ) {
	if ( is_page() ) {
		return geolocation_display_location_page( $content );
	} else {
		return geolocation_display_location_post( $content );
	}
}

/**
 * Collect the locations of all visible posts to be shown on a page's map.
 *
 * The posts can be limited to a category by the page's custom field "category".
 * The result is cached until a post is saved or deleted, at most for one hour.
 *
 * @return array The category, its id, the number of posts having coordinates and the markers.
 */
function geolocation_get_page_markers() {
	$category  = (string) get_post_meta( get_the_ID(), 'category', true );
	$cache_key = 'geolocation_pm3_' . md5( get_option( 'geolocation_markers_version' ) . '|' . $category . '|' . ( is_user_logged_in() ? '1' : '0' ) );
	$result    = get_transient( $cache_key );
	if ( is_array( $result ) && isset( $result['markers'] ) ) {
		return $result;
	}

	$category_id = get_cat_ID( $category );
	$posts       = get_posts( geolocation_page_query_args( $category_id ) );
	$markers     = array();
	foreach ( $posts as $geo_post ) {
		if ( ! geolocation_post_is_visible( $geo_post->ID ) ) {
			continue;
		}
		$markers[] = array(
			'lat'     => (float) geolocation_clean_coordinate( get_post_meta( $geo_post->ID, 'geo_latitude', true ) ),
			'lng'     => (float) geolocation_clean_coordinate( get_post_meta( $geo_post->ID, 'geo_longitude', true ) ),
			'url'     => (string) get_permalink( $geo_post ),
			'title'   => html_entity_decode( get_the_title( $geo_post ), ENT_QUOTES, 'UTF-8' ),
			'date'    => (string) get_the_date( '', $geo_post ),
			'image'   => (string) get_the_post_thumbnail_url( $geo_post, 'medium' ),
			// Only a manually written excerpt is used; generating one would run the content filters for every post.
			'excerpt' => has_excerpt( $geo_post ) ? wp_trim_words( wp_strip_all_tags( $geo_post->post_excerpt ), 25 ) : '',
		);
	}
	$result = array(
		'category'    => $category,
		'category_id' => $category_id,
		'candidates'  => count( $posts ),
		'markers'     => $markers,
	);
	set_transient( $cache_key, $result, HOUR_IN_SECONDS );
	return $result;
}

/**
 * Invalidate the cached locations of the pages' maps.
 *
 * @return void
 */
function geolocation_flush_page_markers() {
	update_option( 'geolocation_markers_version', (string) microtime( true ) );
}

/**
 * Replace the shortcode inside a page by a map showing all posts' locations.
 *
 * @param [type] $content The content the functionality shall be provided for.
 * @return mixed
 */
function geolocation_display_location_page( $content ) {
	$shortcode       = (string) get_option( 'geolocation_shortcode' );
	$shortcode_found = '' !== $shortcode && false !== strpos( $content, $shortcode );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostic switch.
	if ( ! $shortcode_found && ! isset( $_GET['geodebug'] ) ) {
		return $content;
	}

	$html   = '';
	$result = geolocation_get_page_markers();
	if ( $shortcode_found && ! empty( $result['markers'] ) && ! geolocation_maps_blocked() ) {
		geolocation_enqueue_front();
		// Markers lying close together are grouped on the overview map.
		wp_enqueue_script( 'geolocation_markercluster' );
		wp_enqueue_style( 'geolocation_markercluster' );
		$map_id = 'google' === get_option( 'geolocation_provider' ) ? 'mymap' : 'mapid';
		$width  = esc_attr( (string) get_option( 'geolocation_map_width_page' ) );
		$height = esc_attr( (string) get_option( 'geolocation_map_height_page' ) );
		$html   = '<div id="' . $map_id . '" class="geolocation-map geolocation-page-map" data-markers="' . esc_attr( wp_json_encode( $result['markers'] ) ) . '" style="width:' . $width . 'px;height:' . $height . 'px;"></div>';
	}
	$content = geolocation_replace_shortcode( $content, $html );
	return $content . geolocation_page_debug( $result['category'], $result['category_id'], $result['candidates'], count( $result['markers'] ), $shortcode_found );
}

/**
 * Replace the plugin's shortcode inside the content.
 *
 * @param string $content The content to be changed.
 * @param string $replacement The HTML to be shown instead of the shortcode.
 * @return string
 */
function geolocation_replace_shortcode( $content, $replacement ) {
	$shortcode = (string) get_option( 'geolocation_shortcode' );
	if ( '' === $shortcode ) {
		return $content;
	}
	return str_replace( $shortcode, $replacement, $content );
}

/**
 * Show the location of a post according to the settings.
 *
 * The scripts and styles for maps are only enqueued if a location is actually shown.
 *
 * @param string $content The content the functionality shall be provided for.
 * @return string
 */
function geolocation_display_location_post( $content ) {
	$post = get_post();
	if ( ! $post ) {
		return $content;
	}
	if ( ! geolocation_post_is_visible( $post->ID ) ) {
		return geolocation_replace_shortcode( $content, '' );
	}
	$latitude  = geolocation_clean_coordinate( get_post_meta( $post->ID, 'geo_latitude', true ) );
	$longitude = geolocation_clean_coordinate( get_post_meta( $post->ID, 'geo_longitude', true ) );
	$on        = (bool) get_post_meta( $post->ID, 'geo_enabled', true );
	$public    = (bool) get_post_meta( $post->ID, 'geo_public', true );

	$address = (string) get_post_meta( $post->ID, 'geo_address', true );
	if ( '' === $address ) {
		$address = geolocation_reverse_geocode( $latitude, $longitude );
		// obviously was missing so add to post for future performance improvement.
		if ( '' !== $address ) {
			update_post_meta( $post->ID, 'geo_address', wp_slash( $address ) );
		}
	}

	$html      = '';
	$posted_at = esc_html__( 'Posted from ', 'geolocation' ) . esc_html( $address );
	$display   = (string) get_option( 'geolocation_map_display' );
	if ( in_array( $display, array( 'link', 'map' ), true ) && geolocation_maps_blocked() ) {
		// Strict privacy mode without a working proxy: show the text only.
		$display = 'plain';
	}
	switch ( $display ) {
		case 'plain':
			$html = '<div class="geolocation-plain" id="geolocation' . $post->ID . '">' . $posted_at . '.</div>';
			wp_enqueue_style( 'geolocation_css', plugins_url( 'style.css', __FILE__ ), array(), GEOLOCATION__VERSION, 'all' );
			break;
		case 'link':
			$html = '<div><a class="geolocation-link" href="#" id="geolocation' . $post->ID . '" data-geolocation="' . esc_attr( $latitude . ',' . $longitude ) . '" onclick="return false;">' . $posted_at . '.</a></div>';
			// The popup map shown while hovering a location link.
			add_action( 'wp_footer', 'geolocation_add_geo_div' );
			geolocation_enqueue_front();
			break;
		case 'map':
			$html = '<div class="geolocation-link" id="geolocation' . $post->ID . '">' . $posted_at . ':</div>' . geolocation_get_geo_div( $post->ID, $latitude . ',' . $longitude );
			geolocation_enqueue_front();
			break;
		case 'debug':
			$html = '<pre> $latitude: ' . esc_html( $latitude ) . '<br> $longitude: ' . esc_html( $longitude ) . '<br> $address: ' . esc_html( $address ) . '<br> $on: ' . esc_html( (string) $on ) . '<br> $public: ' . esc_html( (string) $public ) . '</pre>';
			break;
	}

	switch ( (string) get_option( 'geolocation_map_position' ) ) {
		case 'before':
			$content = $html . '<br/><br/>' . geolocation_replace_shortcode( $content, '' );
			break;
		case 'after':
			$content = geolocation_replace_shortcode( $content, '' ) . '<br/><br/>' . $html;
			break;
		case 'shortcode':
			$content = geolocation_replace_shortcode( $content, $html );
			break;
	}
	return $content;
}

/**
 * Schedule the update of all posts' addresses in the background.
 *
 * @return void
 */
function geolocation_update_addresses() {
	wp_unschedule_hook( 'geolocation_update_addresses_batch' );
	wp_schedule_single_event( time(), 'geolocation_update_addresses_batch', array( 0 ) );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Addresses are being updated in the background.', 'geolocation' ) . '</p></div>';
}

/**
 * Update the addresses of one batch of posts having geo data and schedule the next batch.
 *
 * @param int $offset The number of posts already processed.
 * @return void
 */
function geolocation_update_addresses_batch( $offset = 0 ) {
	$offset   = (int) $offset;
	$post_ids = get_posts(
		array(
			'post_type'      => 'post',
			'posts_per_page' => GEOLOCATION__UPDATE_BATCH_SIZE,
			'offset'         => $offset,
			'post_status'    => 'publish',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
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
		)
	);

	foreach ( $post_ids as $index => $post_id ) {
		if ( $index > 0 ) {
			// Respect the usage policy of the geocoding service (max. 1 request per second).
			sleep( 1 );
		}
		$post_latitude  = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_latitude', true ) );
		$post_longitude = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_longitude', true ) );
		if ( '' === $post_latitude || '' === $post_longitude ) {
			continue;
		}
		$post_address_new = (string) geolocation_reverse_geocode( $post_latitude, $post_longitude, true );
		if ( '' !== $post_address_new ) {
			update_post_meta( $post_id, 'geo_address', wp_slash( $post_address_new ) );
		}
	}

	if ( count( $post_ids ) === GEOLOCATION__UPDATE_BATCH_SIZE ) {
		wp_schedule_single_event( time() + 5, 'geolocation_update_addresses_batch', array( $offset + GEOLOCATION__UPDATE_BATCH_SIZE ) );
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
function geolocation_build_addresses( $city, $state, $country ) {
	$city    = (string) $city;
	$state   = (string) $state;
	$country = (string) $country;
	$address = '';
	if ( ( '' !== $city ) && ( '' !== $state ) && ( '' !== $country ) ) {
		$address = $city . ', ' . $state . ', ' . $country;
	} elseif ( ( '' !== $city ) && ( '' !== $state ) ) {
		$address = $city . ', ' . $state;
	} elseif ( ( '' !== $state ) && ( '' !== $country ) ) {
		$address = $state . ', ' . $country;
	} elseif ( '' !== $country ) {
		$address = $country;
	}
	return esc_html( $address );
}

/**
 * Reverse geocode the GPS data into readyble names.
 *
 * @param [type] $latitude The Latitude of the location.
 * @param [type] $longitude The longitude of the location.
 * @param bool   $force Whether to bypass the cached result.
 * @return mixed
 */
function geolocation_reverse_geocode( $latitude, $longitude, $force = false ) {
	$cache_key = 'geolocation_rg_' . md5( get_option( 'geolocation_provider' ) . '|' . geolocation_get_site_lang() . '|' . $latitude . ',' . $longitude );
	if ( ! $force ) {
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$city    = '';
	$state   = '';
	$country = '';
	//
	// To do: add support for multiple Map API providers.
	switch ( get_option( 'geolocation_provider' ) ) {
		case 'google':
			$json = geolocation_pull_json_google( $latitude, $longitude );
			if ( empty( $json['results'] ) ) {
				break;
			}
			// The results are ordered from the most specific to the broadest one: the first match wins.
			foreach ( $json['results'] as $result ) {
				foreach ( $result['address_components'] as $address_part ) {
					if ( ! in_array( 'political', $address_part['types'], true ) ) {
						continue;
					}
					if ( '' === $city && in_array( 'locality', $address_part['types'], true ) ) {
						$city = $address_part['long_name'];
					} elseif ( '' === $state && in_array( 'administrative_area_level_1', $address_part['types'], true ) ) {
						$state = $address_part['long_name'];
					} elseif ( '' === $country && in_array( 'country', $address_part['types'], true ) ) {
						$country = $address_part['long_name'];
					}
				}
			}
			break;
		case 'osm':
			$json = geolocation_pull_json_osm( $latitude, $longitude );
			if ( isset( $json['address']['city'] ) ) {
				$city = $json['address']['city'];
			} elseif ( isset( $json['address']['town'] ) ) {
							$city = $json['address']['town'];
			}
			if ( isset( $json['address']['suburb'] ) ) {
				$state = $json['address']['suburb'];
			} elseif ( isset( $json['address']['municipality'] ) ) {
				$state = $json['address']['municipality'];
			}
			if ( isset( $json['address']['country'] ) ) {
				$country = $json['address']['country'];
			}
			break;
	}
	$address = geolocation_build_addresses( $city, $state, $country );
	// Keep failed lookups only for a short time, so they are retried, but not on every page view.
	set_transient( $cache_key, $address, '' === $address ? 10 * MINUTE_IN_SECONDS : MONTH_IN_SECONDS );
	return $address;
}

/**
 * Clean the given coordinates.
 *
 * @param mixed $coordinate The coordinates to be cleaned.
 * @return string
 */
function geolocation_clean_coordinate( $coordinate ) {
	$coordinate = trim( (string) $coordinate );
	if ( ! is_numeric( $coordinate ) ) {
		return '';
	}
	return (string) floatval( $coordinate );
}
