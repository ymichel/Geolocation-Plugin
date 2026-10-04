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

	/**
	 * Reset the stubbed WordPress state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['geolocation_test_logged_in'] = false;
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
	 * Visitors only get public locations, logged in users get all enabled ones.
	 *
	 * @return void
	 */
	public function test_meta_query_limits_visitors_to_public_posts() {
		$keys = array_column( geolocation_get_meta_query(), 'key' );
		$this->assertSame( array( 'geo_latitude', 'geo_longitude', 'geo_enabled', 'geo_public' ), $keys );

		$GLOBALS['geolocation_test_logged_in'] = true;
		$keys                                  = array_column( geolocation_get_meta_query(), 'key' );
		$this->assertSame( array( 'geo_latitude', 'geo_longitude', 'geo_enabled' ), $keys );
	}
}
