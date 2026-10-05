<?php
/**
 * Track
 *
 * A post can carry the track of a GPX file. The file is read by the browser of the author,
 * only the simplified line is sent to the server and stored with the post.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/** The number of points a track is stored with. */
define( 'GEOLOCATION__TRACK_POINTS', 500 );
/** The number of points all tracks of an overview map share, as it may show many tracks. */
define( 'GEOLOCATION__TRACK_POINTS_PAGE', 3000 );

/**
 * Turn a submitted or stored track into a clean list of positions.
 *
 * @param mixed $raw The track as JSON or as an array of [ latitude, longitude ] pairs.
 * @return array The positions as [ latitude, longitude ] pairs, empty if there is no valid track.
 */
function geolocation_sanitize_track( $raw ) {
	$points = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( ! is_array( $points ) ) {
		return array();
	}
	$clean = array();
	$last  = null;
	foreach ( $points as $point ) {
		if ( ! is_array( $point ) || ! isset( $point[0], $point[1] ) || ! is_numeric( $point[0] ) || ! is_numeric( $point[1] ) ) {
			continue;
		}
		$position = array( round( (float) $point[0], 5 ), round( (float) $point[1], 5 ) );
		if ( abs( $position[0] ) > 90 || abs( $position[1] ) > 180 || $position === $last ) {
			continue;
		}
		$clean[] = $position;
		$last    = $position;
	}
	return count( $clean ) < 2 ? array() : $clean;
}

/**
 * Get the distance between two positions in kilometres.
 *
 * @param array $from The first position as [ latitude, longitude ].
 * @param array $to The second position as [ latitude, longitude ].
 * @return float
 */
function geolocation_distance( $from, $to ) {
	$lat1 = deg2rad( $from[0] );
	$lat2 = deg2rad( $to[0] );
	$half = sin( ( $lat2 - $lat1 ) / 2 ) ** 2 + cos( $lat1 ) * cos( $lat2 ) * sin( deg2rad( $to[1] - $from[1] ) / 2 ) ** 2;
	return 2 * 6371.0088 * asin( min( 1, sqrt( $half ) ) );
}

/**
 * Get the length of a track in kilometres.
 *
 * @param array $points The positions of the track.
 * @return float
 */
function geolocation_track_length( $points ) {
	$length = 0.0;
	$count  = count( $points );
	for ( $i = 1; $i < $count; $i++ ) {
		$length += geolocation_distance( $points[ $i - 1 ], $points[ $i ] );
	}
	return $length;
}

/**
 * Drop the positions of a track which are not needed to keep its shape (Douglas-Peucker).
 *
 * @param array $points The positions of the track.
 * @param float $tolerance The allowed deviation in degrees of latitude.
 * @return array
 */
function geolocation_simplify_track_by( $points, $tolerance ) {
	$count = count( $points );
	if ( $count < 3 ) {
		return $points;
	}
	// Degrees of longitude are shorter than degrees of latitude away from the equator.
	$scale = cos( deg2rad( $points[0][0] ) );
	$keep  = array(
		0          => true,
		$count - 1 => true,
	);
	$stack = array( array( 0, $count - 1 ) );
	while ( $stack ) {
		list( $first, $last ) = array_pop( $stack );
		$ax                   = $points[ $first ][1] * $scale;
		$ay                   = $points[ $first ][0];
		$dx                   = $points[ $last ][1] * $scale - $ax;
		$dy                   = $points[ $last ][0] - $ay;
		$length               = $dx * $dx + $dy * $dy;
		$max                  = 0.0;
		$index                = 0;
		for ( $i = $first + 1; $i < $last; $i++ ) {
			$px = $points[ $i ][1] * $scale - $ax;
			$py = $points[ $i ][0] - $ay;
			$t  = $length > 0 ? max( 0, min( 1, ( $px * $dx + $py * $dy ) / $length ) ) : 0;
			$d  = ( $px - $t * $dx ) ** 2 + ( $py - $t * $dy ) ** 2;
			if ( $d > $max ) {
				$max   = $d;
				$index = $i;
			}
		}
		if ( $max > $tolerance * $tolerance ) {
			$keep[ $index ] = true;
			$stack[]        = array( $first, $index );
			$stack[]        = array( $index, $last );
		}
	}
	ksort( $keep );
	$result = array();
	foreach ( array_keys( $keep ) as $i ) {
		$result[] = $points[ $i ];
	}
	return $result;
}

/**
 * Reduce a track to a maximum number of positions while keeping its shape.
 *
 * @param array $points The positions of the track.
 * @param int   $max The maximum number of positions.
 * @return array
 */
function geolocation_simplify_track( $points, $max ) {
	$max       = max( 2, (int) $max );
	$tolerance = 0.00002; // About two metres.
	$count     = count( $points );
	while ( $count > $max ) {
		$points     = geolocation_simplify_track_by( $points, $tolerance );
		$count      = count( $points );
		$tolerance *= 1.3;
	}
	return $points;
}

/**
 * Cut the given distance off both ends of a track, e.g. to keep a home address private.
 *
 * @param array $points The positions of the track.
 * @param int   $metres The distance to cut off at the start and at the end.
 * @return array The shortened track, empty if nothing is left of it.
 */
function geolocation_trim_track( $points, $metres ) {
	$cut = (int) $metres / 1000;
	if ( $cut <= 0 ) {
		return $points;
	}
	if ( geolocation_track_length( $points ) <= 2 * $cut ) {
		return array();
	}
	foreach ( array( 0, 1 ) as $pass ) {
		$distance = 0.0;
		$count    = count( $points );
		$start    = 0;
		for ( $i = 1; $i < $count && $distance < $cut; $i++ ) {
			$distance += geolocation_distance( $points[ $i - 1 ], $points[ $i ] );
			$start     = $i;
		}
		// The second pass cuts the other end.
		$points = array_reverse( array_slice( $points, $start ) );
	}
	return count( $points ) < 2 ? array() : $points;
}

/**
 * Format the length of a track for visitors, e.g. "42 km".
 *
 * @param float $km The length in kilometres.
 * @return string An empty string if there is no length.
 */
function geolocation_format_track_length( $km ) {
	$km = (float) $km;
	if ( $km <= 0 ) {
		return '';
	}
	return number_format_i18n( $km, $km < 10 ? 1 : 0 ) . ' km';
}

/**
 * Store or remove the track submitted with the post editor's meta box.
 *
 * The caller has to check the nonce and the permissions.
 *
 * @param int $post_id The posts id.
 * @return void
 */
function geolocation_save_track( $post_id ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by geolocation_save_postdata().
	if ( '1' === geolocation_get_posted_value( 'geolocation-track-remove' ) ) {
		delete_post_meta( $post_id, 'geo_track' );
		delete_post_meta( $post_id, 'geo_track_km' );
		return;
	}
	if ( empty( $_POST['geolocation-track'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, cleaned by geolocation_sanitize_track().
	$points = geolocation_sanitize_track( wp_unslash( $_POST['geolocation-track'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( empty( $points ) ) {
		return;
	}
	$points = geolocation_simplify_track( $points, GEOLOCATION__TRACK_POINTS );
	$length = geolocation_track_length( $points );
	// The browser measures the recorded track, which is a bit longer than its simplified line.
	$measured = (float) geolocation_get_posted_value( 'geolocation-track-km' );
	if ( $measured > $length && $measured < $length * 1.5 ) {
		$length = $measured;
	}
	update_post_meta( $post_id, 'geo_track', wp_json_encode( $points ) );
	update_post_meta( $post_id, 'geo_track_km', round( $length, 2 ) );
}

/**
 * Get the track of a post as it is shown to visitors.
 *
 * @param int $post_id The posts id.
 * @param int $max The maximum number of positions.
 * @return array The positions as [ latitude, longitude ] pairs, empty if the post has no track.
 */
function geolocation_get_track( $post_id, $max = GEOLOCATION__TRACK_POINTS ) {
	$stored = get_post_meta( $post_id, 'geo_track', true );
	if ( empty( $stored ) ) {
		return array();
	}
	$points = geolocation_trim_track( geolocation_sanitize_track( $stored ), (int) get_option( 'geolocation_track_trim' ) );
	return geolocation_simplify_track( $points, $max );
}
