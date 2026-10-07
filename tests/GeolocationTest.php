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

	/**
	 * A submitted track is reduced to valid positions.
	 *
	 * @return void
	 */
	public function test_sanitize_track() {
		$this->assertSame( array(), geolocation_sanitize_track( '' ) );
		$this->assertSame( array(), geolocation_sanitize_track( 'no json' ) );
		$this->assertSame( array(), geolocation_sanitize_track( '[[53.5,9.9]]' ) );
		$this->assertSame( array(), geolocation_sanitize_track( '{"a":"<script>"}' ) );

		$track = geolocation_sanitize_track( '[[53.5511111,9.9936999],[53.5511111,9.9936999],["53.6","10.0"],[91,10],[53.7,181],["x",1],[53.8],"<script>",[53.9,10.3,"extra"]]' );
		$this->assertSame( array( array( 53.55111, 9.9937 ), array( 53.6, 10.0 ), array( 53.9, 10.3 ) ), $track );
		$this->assertSame( $track, geolocation_sanitize_track( $track ) );
	}

	/**
	 * The length of a track is measured along its positions.
	 *
	 * @return void
	 */
	public function test_track_length() {
		// One degree of latitude is about 111.2 km.
		$this->assertEqualsWithDelta( 111.2, geolocation_track_length( array( array( 50.0, 10.0 ), array( 51.0, 10.0 ) ) ), 0.1 );
		$this->assertEqualsWithDelta( 222.4, geolocation_track_length( array( array( 50.0, 10.0 ), array( 51.0, 10.0 ), array( 50.0, 10.0 ) ) ), 0.2 );
		$this->assertSame( 0.0, geolocation_track_length( array() ) );
	}

	/**
	 * Simplifying keeps the shape and the ends of a track.
	 *
	 * @return void
	 */
	public function test_simplify_track() {
		// A straight line with a sharp corner in the middle.
		$points = array();
		for ( $i = 0; $i <= 500; $i++ ) {
			$points[] = array( 50.0 + $i / 1000, 10.0 );
		}
		for ( $i = 1; $i <= 500; $i++ ) {
			$points[] = array( 50.5, 10.0 + $i / 1000 );
		}
		$simple = geolocation_simplify_track( $points, 100 );
		$this->assertLessThanOrEqual( 100, count( $simple ) );
		$this->assertSame( $points[0], $simple[0] );
		$this->assertSame( end( $points ), end( $simple ) );
		$this->assertContains( array( 50.5, 10.0 ), $simple );
		$this->assertEqualsWithDelta( geolocation_track_length( $points ), geolocation_track_length( $simple ), 0.01 );

		// A track which is short enough is not changed.
		$this->assertSame( $points, geolocation_simplify_track( $points, 2000 ) );

		// A winding track is reduced as far as requested.
		$winding = array();
		for ( $i = 0; $i < 3000; $i++ ) {
			$winding[] = array( 50.0 + $i / 10000, 10.0 + sin( $i / 20 ) / 100 );
		}
		$this->assertLessThanOrEqual( 120, count( geolocation_simplify_track( $winding, 120 ) ) );
		$this->assertCount( 2, geolocation_simplify_track( $winding, 1 ) );
	}

	/**
	 * Both ends of a track can be cut off.
	 *
	 * @return void
	 */
	public function test_trim_track() {
		// About 11 km, one position every 111 metres.
		$points = array();
		for ( $i = 0; $i <= 100; $i++ ) {
			$points[] = array( 50.0 + $i / 1000, 10.0 );
		}
		$this->assertSame( $points, geolocation_trim_track( $points, 0 ) );

		$trimmed = geolocation_trim_track( $points, 1000 );
		$this->assertEqualsWithDelta( 9.1, geolocation_track_length( $trimmed ), 0.3 );
		$this->assertGreaterThan( 50.008, $trimmed[0][0] );
		$this->assertLessThan( 50.092, end( $trimmed )[0] );
		$this->assertGreaterThanOrEqual( 1.0, geolocation_distance( $points[0], $trimmed[0] ) );
		$this->assertGreaterThanOrEqual( 1.0, geolocation_distance( end( $points ), end( $trimmed ) ) );

		// Nothing is left of a track which is shorter than both cuts.
		$this->assertSame( array(), geolocation_trim_track( $points, 6000 ) );
	}

	/**
	 * Submitted elevations are reduced to valid values.
	 *
	 * @return void
	 */
	public function test_sanitize_elevation() {
		$this->assertSame( array(), geolocation_sanitize_elevation( '' ) );
		$this->assertSame( array(), geolocation_sanitize_elevation( '[1,2,3]' ) );
		$this->assertSame( array(), geolocation_sanitize_elevation( '{"profile":[100]}' ) );
		$this->assertSame( array(), geolocation_sanitize_elevation( '{"profile":[100,"<script>",300]}' ) );

		$clean = geolocation_sanitize_elevation( '{"profile":[100.4,"250",99999,-9999],"up":"1200.6","down":-5,"min":"x","extra":"<b>"}' );
		$this->assertSame( array( 100, 250, 9000, -500 ), $clean['profile'] );
		$this->assertSame( 1201, $clean['up'] );
		$this->assertSame( 0, $clean['down'] );
		$this->assertSame( -500, $clean['min'] );
		$this->assertSame( 9000, $clean['max'] );
		$this->assertSame( array( 'profile', 'up', 'down', 'min', 'max' ), array_keys( $clean ) );
		$this->assertSame( $clean, geolocation_sanitize_elevation( $clean ) );

		// A profile is limited to 400 elevations.
		$this->assertCount( 400, geolocation_sanitize_elevation( array( 'profile' => range( 1, 1000 ) ) )['profile'] );
	}

	/**
	 * The elevation profile is drawn as an SVG image with escaped labels.
	 *
	 * @return void
	 */
	public function test_elevation_svg() {
		$labels = array(
			'title' => 'Elevation <profile>',
			'min'   => '200 m',
			'max'   => '1,200 m',
		);
		$this->assertSame( '', geolocation_get_elevation_svg( array(), '42 km', 450, $labels ) );

		$svg = geolocation_get_elevation_svg( geolocation_sanitize_elevation( array( 'profile' => array( 200, 1200, 700 ) ) ), '42 km', 450, $labels );
		$this->assertStringStartsWith( '<svg class="geolocation-elevation"', $svg );
		$this->assertStringContainsString( 'viewBox="0 0 450 120"', $svg );
		$this->assertStringContainsString( 'Elevation &lt;profile&gt;', $svg );
		$this->assertStringNotContainsString( '<profile>', $svg );
		$this->assertStringContainsString( '>1,200 m</text>', $svg );
		$this->assertStringContainsString( '>42 km</text>', $svg );
		// The lowest elevation lies on the base line, the highest at the top, the last position at the right edge.
		$this->assertStringContainsString( 'd="M0,102 L225,6 L450,54"', $svg );

		// A flat track does not divide by zero; the image is at least 200 pixels wide.
		$this->assertStringContainsString( 'd="M0,102 L200,102"', geolocation_get_elevation_svg( geolocation_sanitize_elevation( array( 'profile' => array( 5, 5 ) ) ), '1.0 km', 100, $labels ) );
	}

	/**
	 * The details of a track are shown unless they are switched off; a block can overrule the settings.
	 *
	 * @return void
	 */
	public function test_track_switches() {
		$this->assertSame( '1', geolocation_sanitize_switch( '1' ) );
		$this->assertSame( '1', geolocation_sanitize_switch( 'on' ) );
		$this->assertSame( '0', geolocation_sanitize_switch( '0' ) );
		$this->assertSame( '0', geolocation_sanitize_switch( '' ) );
		$this->assertSame( '0', geolocation_sanitize_switch( null ) );

		// Never saved: shown.
		unset( $GLOBALS['geolocation_test_options']['geolocation_track_figures'], $GLOBALS['geolocation_test_options']['geolocation_track_profile'] );
		$this->assertTrue( geolocation_track_shows( 'figures' ) );
		$this->assertTrue( geolocation_track_shows( 'profile' ) );

		$GLOBALS['geolocation_test_options']['geolocation_track_figures'] = '0';
		$GLOBALS['geolocation_test_options']['geolocation_track_profile'] = '1';
		$this->assertFalse( geolocation_track_shows( 'figures' ) );
		$this->assertTrue( geolocation_track_shows( 'profile' ) );
		$this->assertFalse( geolocation_track_shows( 'figures', '' ) );
		$this->assertFalse( geolocation_track_shows( 'figures', 'anything' ) );
		$this->assertTrue( geolocation_track_shows( 'figures', 'show' ) );
		$this->assertFalse( geolocation_track_shows( 'profile', 'hide' ) );

		unset( $GLOBALS['geolocation_test_options']['geolocation_track_figures'], $GLOBALS['geolocation_test_options']['geolocation_track_profile'] );
	}

	/**
	 * Locations are converted to positions in the grid of tiles.
	 *
	 * @return void
	 */
	public function test_tile_position() {
		$this->assertEqualsWithDelta( array( 0.5, 0.5 ), geolocation_tile_position( 0, 0, 0 ), 1e-9 );
		$this->assertEqualsWithDelta( array( 1.0, 1.0 ), geolocation_tile_position( 0, 0, 1 ), 1e-9 );
		$this->assertEqualsWithDelta( array( 0.0, 0.0 ), geolocation_tile_position( 85.0511287798, -180, 3 ), 1e-6 );
		// Beyond the poles of the projection the position stays inside the grid.
		$this->assertEqualsWithDelta( 8.0, geolocation_tile_position( -90, 0, 3 )[1], 1e-6 );

		// Hamburg, town hall: tile 34587/21180 at zoom level 16.
		$position = geolocation_tile_position( 53.5511, 9.9937, 16 );
		$this->assertSame( 34587, (int) floor( $position[0] ) );
		$this->assertSame( 21180, (int) floor( $position[1] ) );
	}

	/**
	 * The tiles of a view cover the map and nothing more.
	 *
	 * @return void
	 */
	public function test_view_tiles() {
		// A map of one tile centred on the middle of a tile shows exactly that tile.
		$this->assertSame( array( array( 4, 5, 6 ) ), geolocation_view_tiles( array( 5.5, 6.5 ), 4, 256, 256 ) );
		// Centred on a corner it shows the four tiles around it.
		$this->assertSame(
			array( array( 4, 4, 5 ), array( 4, 5, 5 ), array( 4, 4, 6 ), array( 4, 5, 6 ) ),
			geolocation_view_tiles( array( 5.0, 6.0 ), 4, 256, 256 )
		);
		// 448 x 198 pixels around Hamburg at zoom level 16.
		$tiles = geolocation_view_tiles( geolocation_tile_position( 53.5511, 9.9937, 16 ), 16, 448, 198 );
		$this->assertContains( array( 16, 34587, 21180 ), $tiles );
		$this->assertLessThanOrEqual( 6, count( $tiles ) );

		// At the top of the world no rows above the grid are requested; beyond the date line the columns wrap.
		$tiles = geolocation_view_tiles( array( 0.1, 0.1 ), 2, 256, 256 );
		$this->assertSame( array( array( 2, 3, 0 ), array( 2, 0, 0 ) ), $tiles );
	}

	/**
	 * A view is fitted to positions with the highest zoom level showing all of them.
	 *
	 * @return void
	 */
	public function test_fit_view() {
		// Hamburg and Luebeck are about 60 km apart.
		list( $center, $zoom ) = geolocation_fit_view( array( array( 53.5511, 9.9937 ), array( 53.8655, 10.6866 ) ), 448, 198 );
		$this->assertSame( 8, $zoom );
		$north_west = geolocation_tile_position( 53.8655, 9.9937, $zoom );
		$south_east = geolocation_tile_position( 53.5511, 10.6866, $zoom );
		$this->assertLessThanOrEqual( 448 - 40, ( $south_east[0] - $north_west[0] ) * 256 );
		$this->assertLessThanOrEqual( 198 - 40, ( $south_east[1] - $north_west[1] ) * 256 );
		$this->assertEqualsWithDelta( ( $north_west[0] + $south_east[0] ) / 2, $center[0], 1e-9 );
		// One zoom level deeper it would not fit anymore.
		$this->assertGreaterThan( 198 - 40, ( geolocation_tile_position( 53.5511, 10.6866, 9 )[1] - geolocation_tile_position( 53.8655, 9.9937, 9 )[1] ) * 256 );

		// Positions lying at the same place are shown with the highest zoom level.
		$this->assertSame( 18, geolocation_fit_view( array( array( 50.0, 10.0 ), array( 50.0, 10.0 ) ), 448, 198 )[1] );
		// Positions around the world do not fit a small map: the lowest zoom level is used.
		$this->assertSame( 0, geolocation_fit_view( array( array( 60.0, -170.0 ), array( -60.0, 170.0 ) ), 100, 100 )[1] );
	}

	/**
	 * The URL of a tile is built like Leaflet does, and mapped to a file only inside the content folder.
	 *
	 * @return void
	 */
	public function test_tile_url() {
		$this->assertSame( 'https://a.example.org/16/34587/21180.png', geolocation_tile_url( 'https://{s}.example.org/{z}/{x}/{y}.png', array( 16, 34587, 21180 ) ) );
		$this->assertSame( 'https://a.example.org/1/0/0.png', geolocation_tile_url( 'https://{s}.example.org/{z}/{x}/{y}.png', array( 1, 0, 0 ) ) );
		$this->assertSame( 'https://c.example.org/3/1/1.png', geolocation_tile_url( 'https://{s}.example.org/{z}/{x}/{y}.png', array( 3, 1, 1 ) ) );
		$this->assertSame( 'https://example.org/t/2/1/3', geolocation_tile_url( 'https://example.org/t/{z}/{x}/{y}', array( 2, 1, 3 ) ) );
	}

	/**
	 * The tiles of a post are pre-cached when it is saved unless this is switched off.
	 *
	 * @return void
	 */
	public function test_precache_on_save_switch() {
		unset( $GLOBALS['geolocation_test_options']['geolocation_osm_precache_on_save'] );
		$this->assertTrue( geolocation_precache_on_save() );
		$GLOBALS['geolocation_test_options']['geolocation_osm_precache_on_save'] = '1';
		$this->assertTrue( geolocation_precache_on_save() );
		$GLOBALS['geolocation_test_options']['geolocation_osm_precache_on_save'] = '0';
		$this->assertFalse( geolocation_precache_on_save() );
		// A settings form without the field keeps the switch: off stays off, anything else is on.
		$this->assertSame( '0', geolocation_sanitize_precache_switch( null ) );
		$this->assertSame( '1', geolocation_sanitize_precache_switch( '1' ) );
		$this->assertSame( '0', geolocation_sanitize_precache_switch( '0' ) );
		unset( $GLOBALS['geolocation_test_options']['geolocation_osm_precache_on_save'] );
		$this->assertSame( '1', geolocation_sanitize_precache_switch( null ) );
		$GLOBALS['geolocation_test_options']['geolocation_osm_precache_on_save'] = '1';
		$this->assertSame( '1', geolocation_sanitize_precache_switch( null ) );
		unset( $GLOBALS['geolocation_test_options']['geolocation_osm_precache_on_save'] );
	}

	/**
	 * The strict privacy mode only exists with OpenStreetMap.
	 *
	 * @return void
	 */
	public function test_strict_privacy() {
		$GLOBALS['geolocation_test_options']['geolocation_provider']           = 'osm';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '1';
		$this->assertTrue( geolocation_strict_privacy() );
		$GLOBALS['geolocation_test_options']['geolocation_provider'] = 'google';
		$this->assertFalse( geolocation_strict_privacy() );
		$GLOBALS['geolocation_test_options']['geolocation_provider']           = 'osm';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '';
		$this->assertFalse( geolocation_strict_privacy() );
	}

	/**
	 * The link to an external map points to the map service of the provider.
	 *
	 * @return void
	 */
	public function test_external_map_url() {
		$this->assertSame( 'https://www.openstreetmap.org/?mlat=53.5511&mlon=9.9937#map=16/53.5511/9.9937', geolocation_get_external_map_url( 'osm', '53.5511', '9.9937', 16 ) );
		$this->assertSame( 'https://www.google.com/maps/search/?api=1&query=53.5511%2C9.9937', geolocation_get_external_map_url( 'google', 53.5511, 9.9937 ) );
		// Negative and whole numbers, long fractions and zoom levels out of range.
		$this->assertSame( 'https://www.openstreetmap.org/?mlat=-33.8688197&mlon=151#map=19/-33.8688197/151', geolocation_get_external_map_url( 'osm', '-33.86881970001', '151.0', 99 ) );
		$this->assertSame( 'https://www.openstreetmap.org/?mlat=0&mlon=0#map=1/0/0', geolocation_get_external_map_url( 'osm', 'abc', '', 0 ) );
		// Anything which is not a number cannot get into the address.
		$this->assertSame( 'https://www.google.com/maps/search/?api=1&query=0%2C12', geolocation_get_external_map_url( 'google', '"><script>', '12&x=1' ) );
		// An unknown provider is treated as OpenStreetMap.
		$this->assertStringStartsWith( 'https://www.openstreetmap.org/', geolocation_get_external_map_url( 'other', 1, 2 ) );
	}

	/**
	 * The link is off by default; a block can overrule the setting. Strict privacy mode forces the notice.
	 *
	 * @return void
	 */
	public function test_map_link_switches() {
		unset( $GLOBALS['geolocation_test_options']['geolocation_map_link'], $GLOBALS['geolocation_test_options']['geolocation_map_link_notice'] );
		$GLOBALS['geolocation_test_options']['geolocation_provider']           = 'osm';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '';
		$this->assertFalse( geolocation_map_link_shows() );
		$this->assertTrue( geolocation_map_link_shows( 'show' ) );
		$this->assertFalse( geolocation_map_link_notice() );

		$GLOBALS['geolocation_test_options']['geolocation_map_link'] = '1';
		$this->assertTrue( geolocation_map_link_shows() );
		$this->assertFalse( geolocation_map_link_shows( 'hide' ) );

		$GLOBALS['geolocation_test_options']['geolocation_map_link_notice'] = '1';
		$this->assertTrue( geolocation_map_link_notice() );
		$GLOBALS['geolocation_test_options']['geolocation_map_link_notice']    = '';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '1';
		$this->assertTrue( geolocation_map_link_notice() );
		// The strict privacy mode only exists with OpenStreetMap.
		$GLOBALS['geolocation_test_options']['geolocation_provider'] = 'google';
		$this->assertFalse( geolocation_map_link_notice() );

		unset( $GLOBALS['geolocation_test_options']['geolocation_map_link'], $GLOBALS['geolocation_test_options']['geolocation_map_link_notice'] );
		$GLOBALS['geolocation_test_options']['geolocation_provider']           = 'osm';
		$GLOBALS['geolocation_test_options']['geolocation_osm_strict_privacy'] = '';
	}
}
