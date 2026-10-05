<?php
/**
 * Tests for the plugin's helper functions.
 *
 * @package geolocation
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for the plugin's helper functions.
 */
class GeolocationTest extends TestCase {

	const OWN_TILES   = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
	const PROXY_TILES = 'https://example.org/wp-content/cache/osm-tiles/{s}/{z}/{x}/{y}.png';
	const PROXY_REST  = 'https://example.org/wp-json/osm-tiles-proxy/v1/tiles/{s}/{z}/{x}/{y}.png';
	const PROXY_FILE  = 'osm-tiles-proxy/osm-tiles-proxy.php';

	/**
	 * Reset the stubbed WordPress state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['geolocation_test_logged_in'] = false;
		$GLOBALS['geolocation_test_post_meta'] = array();
		$GLOBALS['geolocation_test_filters']   = array();
		$GLOBALS['geolocation_test_plugins']   = array();
		$GLOBALS['geolocation_test_options']   = array( 'geolocation_osm_tiles_url' => self::OWN_TILES );
	}

	/**
	 * Numeric coordinates are normalized, everything else is rejected.
	 *
	 * @return void
	 */
	public function test_clean_coordinate() {
		$this->assertSame( '53.550861', geolocation_clean_coordinate( '53.5508610' ) );
		$this->assertSame( '-13.5', geolocation_clean_coordinate( ' -13.5 ' ) );
		$this->assertSame( '9', geolocation_clean_coordinate( 9 ) );
		$this->assertSame( '', geolocation_clean_coordinate( '' ) );
		$this->assertSame( '', geolocation_clean_coordinate( null ) );
		$this->assertSame( '', geolocation_clean_coordinate( '53.5,9.9' ) );
		$this->assertSame( '', geolocation_clean_coordinate( '<script>' ) );
	}

	/**
	 * The address is built from the available parts.
	 *
	 * @return void
	 */
	public function test_build_addresses() {
		$this->assertSame( 'Hamburg, Altstadt, Germany', geolocation_build_addresses( 'Hamburg', 'Altstadt', 'Germany' ) );
		$this->assertSame( 'Hamburg, Altstadt', geolocation_build_addresses( 'Hamburg', 'Altstadt', '' ) );
		$this->assertSame( 'Altstadt, Germany', geolocation_build_addresses( '', 'Altstadt', 'Germany' ) );
		$this->assertSame( 'Germany', geolocation_build_addresses( '', '', 'Germany' ) );
		$this->assertSame( 'Germany', geolocation_build_addresses( 'Hamburg', '', 'Germany' ) );
		$this->assertSame( '', geolocation_build_addresses( '', '', '' ) );
		$this->assertSame( '', geolocation_build_addresses( null, null, null ) );
	}

	/**
	 * The address is escaped for HTML output.
	 *
	 * @return void
	 */
	public function test_build_addresses_escapes_html() {
		$this->assertSame( 'A &amp; B', geolocation_build_addresses( '', '', 'A & B' ) );
	}

	/**
	 * EXIF coordinates are converted to decimal degrees respecting the hemisphere.
	 *
	 * @return void
	 */
	public function test_exif_to_decimal() {
		$this->assertEqualsWithDelta( 52.51, (float) geolocation_exif_to_decimal( array( '52/1', '30/1', '3600/100' ), 'N' ), 0.000001 );
		$this->assertEqualsWithDelta( -33.8675, (float) geolocation_exif_to_decimal( array( '33/1', '52/1', '3/1' ), 'S' ), 0.000001 );
		$this->assertEqualsWithDelta( -13.366667, (float) geolocation_exif_to_decimal( array( '13/1', '22/1', '0/1' ), 'w' ), 0.000001 );
		$this->assertEqualsWithDelta( 13.5, (float) geolocation_exif_to_decimal( array( '13', '30', '0' ), 'E' ), 0.000001 );
	}

	/**
	 * Broken EXIF coordinates are rejected instead of raising errors.
	 *
	 * @return void
	 */
	public function test_exif_to_decimal_rejects_invalid_values() {
		$this->assertSame( '', geolocation_exif_to_decimal( array( '13/0', '22/1', '0/1' ), 'E' ) );
		$this->assertSame( '', geolocation_exif_to_decimal( array( '13/1', '22/1' ), 'E' ) );
		$this->assertSame( '', geolocation_exif_to_decimal( array( 'x/1', '22/1', '0/1' ), 'E' ) );
		$this->assertSame( '', geolocation_exif_to_decimal( '13.5', 'E' ) );
	}

	/**
	 * Only the supported providers can be stored.
	 *
	 * @return void
	 */
	public function test_sanitize_provider() {
		$this->assertSame( 'google', geolocation_sanitize_provider( 'google' ) );
		$this->assertSame( 'osm', geolocation_sanitize_provider( 'osm' ) );
		$this->assertSame( 'osm', geolocation_sanitize_provider( 'bing' ) );
		$this->assertSame( 'osm', geolocation_sanitize_provider( null ) );
	}

	/**
	 * Store the geo data of a test post.
	 *
	 * @param int   $post_id The post id.
	 * @param mixed $latitude The latitude.
	 * @param mixed $longitude The longitude.
	 * @param mixed $enabled The enabled flag.
	 * @param mixed $is_public The public flag.
	 * @return void
	 */
	private function set_geo( $post_id, $latitude, $longitude, $enabled, $is_public ) {
		$GLOBALS['geolocation_test_post_meta'][ $post_id ] = array(
			'geo_latitude'  => $latitude,
			'geo_longitude' => $longitude,
			'geo_enabled'   => $enabled,
			'geo_public'    => $is_public,
		);
	}

	/**
	 * Visitors only see enabled and public locations.
	 *
	 * @return void
	 */
	public function test_post_is_visible_for_visitors() {
		$this->set_geo( 1, '53.55', '9.99', '1', '1' );
		$this->set_geo( 2, '53.55', '9.99', '1', '0' );
		$this->set_geo( 3, '53.55', '9.99', '0', '1' );
		$this->set_geo( 4, '53.55', '9.99', '1', '' );
		$this->set_geo( 5, '', '', '1', '1' );
		$this->set_geo( 6, 'abc', '9.99', '1', '1' );
		$this->assertTrue( geolocation_post_is_visible( 1 ) );
		$this->assertFalse( geolocation_post_is_visible( 2 ) );
		$this->assertFalse( geolocation_post_is_visible( 3 ) );
		$this->assertFalse( geolocation_post_is_visible( 4 ) );
		$this->assertFalse( geolocation_post_is_visible( 5 ) );
		$this->assertFalse( geolocation_post_is_visible( 6 ) );
		$this->assertFalse( geolocation_post_is_visible( 99 ) );
	}

	/**
	 * Logged in users also see locations which are not public.
	 *
	 * @return void
	 */
	public function test_post_is_visible_for_logged_in_users() {
		$GLOBALS['geolocation_test_logged_in'] = true;
		$this->set_geo( 2, '53.55', '9.99', '1', '0' );
		$this->set_geo( 3, '53.55', '9.99', '0', '1' );
		$this->assertTrue( geolocation_post_is_visible( 2 ) );
		$this->assertFalse( geolocation_post_is_visible( 3 ) );
	}

	/**
	 * Switch on the proxy option and mark the proxy plugin as active.
	 *
	 * @param array $filters The URLs returned by the proxy plugin's filters.
	 * @return void
	 */
	private function enable_proxy( $filters ) {
		$GLOBALS['geolocation_test_options']['geolocation_osm_use_proxy'] = '1';
		$GLOBALS['geolocation_test_plugins']                              = array( self::PROXY_FILE );
		$GLOBALS['geolocation_test_filters']                              = $filters;
	}

	/**
	 * Without the proxy the own tiles URL and the bundled Leaflet are used.
	 *
	 * @return void
	 */
	public function test_osm_urls_without_proxy() {
		$this->assertSame( self::OWN_TILES, geolocation_get_osm_tiles_url() );
		$this->assertStringEndsWith( '/geolocation/js/leaflet.js', geolocation_get_osm_leaflet_js_url() );
		$this->assertStringEndsWith( '/geolocation/js/leaflet.css', geolocation_get_osm_leaflet_css_url() );
	}

	/**
	 * The proxy option alone must not change anything while the proxy plugin is inactive.
	 *
	 * @return void
	 */
	public function test_osm_urls_with_option_but_inactive_proxy_plugin() {
		$this->enable_proxy( array( 'osm_tiles_proxy_get_proxy_url' => self::PROXY_TILES ) );
		$GLOBALS['geolocation_test_plugins'] = array();
		$this->assertSame( self::OWN_TILES, geolocation_get_osm_tiles_url() );
		$this->assertStringEndsWith( '/geolocation/js/leaflet.js', geolocation_get_osm_leaflet_js_url() );
	}

	/**
	 * With the proxy enabled its cached tiles URL and its Leaflet are used.
	 *
	 * @return void
	 */
	public function test_osm_urls_with_proxy() {
		$this->enable_proxy(
			array(
				'osm_tiles_proxy_get_proxy_url'       => self::PROXY_TILES,
				'osm_tiles_proxy_get_proxy_rest_url'  => self::PROXY_REST,
				'osm_tiles_proxy_get_leaflet_js_url'  => 'https://example.org/proxy/leaflet.js',
				'osm_tiles_proxy_get_leaflet_css_url' => 'https://example.org/proxy/leaflet.css',
			)
		);
		$this->assertSame( self::PROXY_TILES, geolocation_get_osm_tiles_url() );
		$this->assertSame( 'https://example.org/proxy/leaflet.js', geolocation_get_osm_leaflet_js_url() );
		$this->assertSame( 'https://example.org/proxy/leaflet.css', geolocation_get_osm_leaflet_css_url() );
	}

	/**
	 * If the proxy's cache is switched off its REST URL is used.
	 *
	 * @return void
	 */
	public function test_osm_tiles_url_falls_back_to_proxy_rest_url() {
		$this->enable_proxy(
			array(
				'osm_tiles_proxy_get_proxy_url'      => '',
				'osm_tiles_proxy_get_proxy_rest_url' => self::PROXY_REST,
			)
		);
		$this->assertSame( self::PROXY_REST, geolocation_get_osm_tiles_url() );
	}

	/**
	 * A proxy without any usable answer must never leave the maps without tiles or Leaflet.
	 *
	 * @return void
	 */
	public function test_osm_urls_fall_back_to_own_urls_if_proxy_answers_nothing() {
		$this->enable_proxy(
			array(
				'osm_tiles_proxy_get_proxy_url'       => false,
				'osm_tiles_proxy_get_proxy_rest_url'  => '',
				'osm_tiles_proxy_get_leaflet_js_url'  => false,
				'osm_tiles_proxy_get_leaflet_css_url' => '',
			)
		);
		$this->assertSame( self::OWN_TILES, geolocation_get_osm_tiles_url() );
		$this->assertStringEndsWith( '/geolocation/js/leaflet.js', geolocation_get_osm_leaflet_js_url() );
		$this->assertStringEndsWith( '/geolocation/js/leaflet.css', geolocation_get_osm_leaflet_css_url() );
	}

	/**
	 * Without the strict privacy mode maps are never blocked.
	 *
	 * @return void
	 */
	public function test_maps_not_blocked_without_strict_mode() {
		$GLOBALS['geolocation_test_options']['geolocation_provider'] = 'osm';
		$this->assertFalse( geolocation_maps_blocked() );
	}

	/**
	 * In the strict privacy mode maps are shown as long as the proxy delivers the tiles.
	 *
	 * @return void
	 */
	public function test_maps_not_blocked_in_strict_mode_with_working_proxy() {
		$this->enable_proxy( array( 'osm_tiles_proxy_get_proxy_url' => self::PROXY_TILES ) );
		$GLOBALS['geolocation_test_options']['geolocation_provider']           = 'osm';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '1';
		$this->assertFalse( geolocation_maps_blocked() );
	}

	/**
	 * In the strict privacy mode maps are blocked whenever the proxy does not deliver tiles.
	 *
	 * @return void
	 */
	public function test_maps_blocked_in_strict_mode_without_working_proxy() {
		$GLOBALS['geolocation_test_options']['geolocation_provider']           = 'osm';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '1';

		// The proxy option is switched off.
		$this->assertTrue( geolocation_maps_blocked() );

		// The proxy option is on, but the proxy plugin is not active.
		$this->enable_proxy( array( 'osm_tiles_proxy_get_proxy_url' => self::PROXY_TILES ) );
		$GLOBALS['geolocation_test_plugins'] = array();
		$this->assertTrue( geolocation_maps_blocked() );

		// The proxy plugin is active, but delivers neither cached nor REST tiles.
		$this->enable_proxy(
			array(
				'osm_tiles_proxy_get_proxy_url'      => '',
				'osm_tiles_proxy_get_proxy_rest_url' => false,
			)
		);
		$this->assertTrue( geolocation_maps_blocked() );
	}

	/**
	 * The strict privacy mode only concerns OpenStreetMap.
	 *
	 * @return void
	 */
	public function test_maps_not_blocked_for_google() {
		$GLOBALS['geolocation_test_options']['geolocation_provider']           = 'google';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '1';
		$this->assertFalse( geolocation_maps_blocked() );
	}

	/**
	 * A shortcode in square brackets is found with and without attributes.
	 *
	 * @return void
	 */
	public function test_shortcode_regex_matches_attributes() {
		$GLOBALS['geolocation_test_options']['geolocation_shortcode'] = '[geolocation]';
		$regex = geolocation_shortcode_regex();
		$this->assertSame( 1, preg_match( $regex, 'a [geolocation] b' ) );
		$this->assertSame( 1, preg_match( $regex, 'a [geolocation cat="travel" height="400"] b', $matches ) );
		$this->assertSame( 'cat="travel" height="400"', $matches[1] );
		$this->assertSame( 0, preg_match( $regex, 'a [geolocations] b' ) );
		$this->assertSame( 0, preg_match( $regex, 'a [other] b' ) );
	}

	/**
	 * A marker text without brackets is matched literally, an empty one never.
	 *
	 * @return void
	 */
	public function test_shortcode_regex_for_custom_marker_text() {
		$GLOBALS['geolocation_test_options']['geolocation_shortcode'] = '%%geo.map%%';
		$this->assertSame( 1, preg_match( geolocation_shortcode_regex(), 'a %%geo.map%% b' ) );
		$this->assertSame( 0, preg_match( geolocation_shortcode_regex(), 'a %%geoXmap%% b' ) );

		$GLOBALS['geolocation_test_options']['geolocation_shortcode'] = '';
		$this->assertSame( '', geolocation_shortcode_regex() );
		$this->assertSame( 'a [geolocation] b', geolocation_replace_shortcode( 'a [geolocation] b', 'X' ) );
	}

	/**
	 * Every occurrence of the shortcode is replaced, including its attributes.
	 *
	 * @return void
	 */
	public function test_replace_shortcode() {
		$GLOBALS['geolocation_test_options']['geolocation_shortcode'] = '[geolocation]';
		$this->assertSame( 'a X b X c', geolocation_replace_shortcode( 'a [geolocation] b [geolocation zoom="5"] c', 'X' ) );
		$this->assertSame( 'a $1 b', geolocation_replace_shortcode( 'a [geolocation] b', '$1' ) );
	}

	/**
	 * The attributes of a map are reduced to the supported ones and cleaned.
	 *
	 * @return void
	 */
	public function test_sanitize_map_atts() {
		$clean = geolocation_sanitize_map_atts(
			array(
				'CAT'     => ' travel , europe ,, ',
				'tag'     => 'hiking',
				'width'   => '100%',
				'height'  => '400',
				'zoom'    => '6',
				'route'   => 'yes',
				'onclick' => 'alert(1)',
			)
		);
		$this->assertSame(
			array(
				'cat'    => 'travel,europe',
				'tag'    => 'hiking',
				'width'  => '100%',
				'height' => '400px',
				'zoom'   => '6',
				'route'  => '1',
			),
			$clean
		);
	}

	/**
	 * Invalid sizes and zoom levels are dropped or limited.
	 *
	 * @return void
	 */
	public function test_sanitize_map_atts_rejects_invalid_values() {
		$clean = geolocation_sanitize_map_atts(
			array(
				'category' => 'travel',
				'width'    => '100%;background:url(x)',
				'height'   => '10',
				'zoom'     => '99',
			)
		);
		$this->assertSame( 'travel', $clean['cat'] );
		$this->assertSame( '', $clean['width'] );
		$this->assertSame( '', $clean['height'] );
		$this->assertSame( '19', $clean['zoom'] );

		$this->assertSame( '640px', geolocation_sanitize_map_atts( array( 'width' => '640' ) )['width'] );
		$this->assertSame( '640px', geolocation_sanitize_map_atts( array( 'width' => '640px' ) )['width'] );
		$this->assertSame( '', geolocation_sanitize_map_atts( array( 'height' => '300;color:red' ) )['height'] );
		$this->assertSame( '', geolocation_sanitize_map_atts( array( 'width' => '5%' ) )['width'] );
		$this->assertSame( '', geolocation_sanitize_map_atts( array( 'zoom' => 'max' ) )['zoom'] );
		$this->assertSame( '', geolocation_sanitize_map_atts( '' )['cat'] );
		$this->assertSame( '1', geolocation_sanitize_map_atts( array( 'route' => '1' ) )['route'] );
		$this->assertSame( '1', geolocation_sanitize_map_atts( array( 'route' => 'TRUE' ) )['route'] );
		$this->assertSame( '', geolocation_sanitize_map_atts( array( 'route' => '0' ) )['route'] );
		$this->assertSame( '', geolocation_sanitize_map_atts( array( 'route' => 'red' ) )['route'] );
		$this->assertSame( '', geolocation_sanitize_map_atts( array() )['route'] );
	}

	/**
	 * The attributes of the block are turned into the attributes of the shortcode.
	 *
	 * @return void
	 */
	public function test_block_to_map_atts() {
		$this->assertSame( array(), geolocation_block_to_map_atts( array() ) );
		$this->assertSame( array(), geolocation_block_to_map_atts( null ) );

		$atts = geolocation_block_to_map_atts(
			array(
				'categories' => array( 3, '7', 0 ),
				'tags'       => array( 12 ),
				'width'      => '80%',
				'height'     => 400,
				'zoom'       => 6,
				'route'      => true,
				'align'      => 'wide',
			)
		);
		$this->assertSame( '3,7', $atts['cat'] );
		$this->assertSame( '12', $atts['tag'] );
		$this->assertSame( '80%', $atts['width'] );
		$this->assertSame( '400', $atts['height'] );
		$this->assertSame( '6', $atts['zoom'] );
		$this->assertSame( '1', $atts['route'] );

		// A wide or full aligned block without a width fills its container.
		$this->assertSame( '100%', geolocation_block_to_map_atts( array( 'align' => 'full' ) )['width'] );
		$this->assertArrayNotHasKey( 'width', geolocation_block_to_map_atts( array( 'align' => 'left' ) ) );

		// Values are still checked by the sanitizer of the shortcode.
		$clean = geolocation_sanitize_map_atts(
			geolocation_block_to_map_atts(
				array(
					'categories' => 'travel',
					'width'      => '100%;color:red',
					'height'     => 10,
					'zoom'       => 99,
					'route'      => false,
				)
			)
		);
		$this->assertSame( '', $clean['cat'] );
		$this->assertSame( '', $clean['width'] );
		$this->assertSame( '', $clean['height'] );
		$this->assertSame( '19', $clean['zoom'] );
		$this->assertSame( '', $clean['route'] );
	}
}
