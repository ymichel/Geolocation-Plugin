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
 * Turn the submitted or stored elevations of a track into clean values.
 *
 * @param mixed $raw The elevations as JSON or as an array with the keys "profile" (elevations in metres at
 *                   equal distances along the track), "up" and "down" (ascent and descent in metres).
 * @return array The keys "profile", "up", "down", "min" and "max"; empty if there are no valid elevations.
 */
function geolocation_sanitize_elevation( $raw ) {
	$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	if ( ! is_array( $data ) || empty( $data['profile'] ) || ! is_array( $data['profile'] ) ) {
		return array();
	}
	$profile = array();
	foreach ( array_slice( $data['profile'], 0, 400 ) as $value ) {
		if ( ! is_numeric( $value ) ) {
			return array();
		}
		$profile[] = (int) round( max( -500, min( 9000, (float) $value ) ) );
	}
	if ( count( $profile ) < 2 ) {
		return array();
	}
	$clean = array( 'profile' => $profile );
	foreach ( array( 'up', 'down' ) as $key ) {
		$clean[ $key ] = isset( $data[ $key ] ) && is_numeric( $data[ $key ] ) ? (int) round( max( 0, min( 500000, (float) $data[ $key ] ) ) ) : 0;
	}
	$clean['min'] = min( $profile );
	$clean['max'] = max( $profile );
	return $clean;
}

/**
 * Get the elevations of the track of a post.
 *
 * @param int $post_id The posts id.
 * @return array As returned by geolocation_sanitize_elevation().
 */
function geolocation_get_elevation( $post_id ) {
	$stored = get_post_meta( $post_id, 'geo_track_ele', true );
	return empty( $stored ) ? array() : geolocation_sanitize_elevation( $stored );
}

/**
 * Get the key figures of the track of a post as text: length, ascent, descent and highest point.
 *
 * @param int $post_id The posts id.
 * @return string An empty string if the post has no track.
 */
function geolocation_get_track_summary( $post_id ) {
	$length = geolocation_format_track_length( get_post_meta( $post_id, 'geo_track_km', true ) );
	if ( '' === $length ) {
		return '';
	}
	/* translators: %s: the length of the track, e.g. "42 km". */
	$parts     = array( sprintf( __( 'Track: %s', 'geolocation' ), $length ) );
	$elevation = geolocation_get_elevation( $post_id );
	if ( ! empty( $elevation ) ) {
		/* translators: %s: metres of ascent, e.g. "1,200 m". */
		$parts[] = sprintf( __( 'Ascent: %s', 'geolocation' ), number_format_i18n( $elevation['up'] ) . ' m' );
		/* translators: %s: metres of descent, e.g. "1,200 m". */
		$parts[] = sprintf( __( 'Descent: %s', 'geolocation' ), number_format_i18n( $elevation['down'] ) . ' m' );
		/* translators: %s: the elevation of the highest point, e.g. "1,200 m". */
		$parts[] = sprintf( __( 'Highest point: %s', 'geolocation' ), number_format_i18n( $elevation['max'] ) . ' m' );
	}
	return implode( ' · ', $parts );
}

/**
 * Draw the elevation profile of a track as an inline SVG image.
 *
 * @param array  $elevation The elevations as returned by geolocation_sanitize_elevation().
 * @param string $length The formatted length of the track, e.g. "42 km".
 * @param int    $width The width of the image in pixels.
 * @param array  $labels The texts "title", "min" and "max" (formatted elevations).
 * @return string The escaped SVG, or an empty string if there is no profile.
 */
function geolocation_get_elevation_svg( $elevation, $length, $width, $labels ) {
	if ( empty( $elevation['profile'] ) || count( $elevation['profile'] ) < 2 ) {
		return '';
	}
	$width  = max( 200, (int) $width );
	$height = 120;
	$top    = 6;
	$bottom = $height - 18;
	$range  = max( 1, $elevation['max'] - $elevation['min'] );
	$count  = count( $elevation['profile'] );
	$points = array();
	foreach ( $elevation['profile'] as $index => $value ) {
		$x        = round( $index * $width / ( $count - 1 ), 1 );
		$y        = round( $bottom - ( $value - $elevation['min'] ) * ( $bottom - $top ) / $range, 1 );
		$points[] = $x . ',' . $y;
	}
	$line = 'M' . implode( ' L', $points );
	$svg  = '<svg class="geolocation-elevation" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . esc_attr( $labels['title'] ) . '" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">';
	$svg .= '<title>' . esc_html( $labels['title'] ) . '</title>';
	$svg .= '<path d="' . esc_attr( $line . ' L' . $width . ',' . $bottom . ' L0,' . $bottom . ' Z' ) . '" fill="#2b6cb0" fill-opacity="0.2"/>';
	$svg .= '<path d="' . esc_attr( $line ) . '" fill="none" stroke="#2b6cb0" stroke-width="1.5" stroke-linejoin="round"/>';
	$svg .= '<line x1="0" y1="' . $bottom . '" x2="' . $width . '" y2="' . $bottom . '" stroke="currentColor" stroke-opacity="0.4"/>';
	$text = '<text font-size="11" fill="currentColor" paint-order="stroke" stroke="#fff" stroke-width="3" stroke-opacity="0.7" ';
	$svg .= $text . 'x="4" y="' . ( $top + 11 ) . '">' . esc_html( $labels['max'] ) . '</text>';
	$svg .= $text . 'x="4" y="' . ( $bottom - 5 ) . '">' . esc_html( $labels['min'] ) . '</text>';
	$svg .= '<text font-size="11" fill="currentColor" x="0" y="' . ( $height - 4 ) . '">0 km</text>';
	$svg .= '<text font-size="11" fill="currentColor" text-anchor="end" x="' . $width . '" y="' . ( $height - 4 ) . '">' . esc_html( $length ) . '</text>';
	$svg .= '</svg>';
	return $svg;
}

/**
 * Build the key figures and, for a map, the elevation profile of the track of a post.
 *
 * @param int  $post_id The posts id.
 * @param bool $profile Whether the elevation profile is shown as well.
 * @param int  $width The width of the map in pixels the profile belongs to.
 * @return string The HTML, or an empty string if the post has no track.
 */
function geolocation_get_track_details( $post_id, $profile = false, $width = 0 ) {
	$summary = geolocation_get_track_summary( $post_id );
	if ( '' === $summary ) {
		return '';
	}
	$style = $width > 0 ? ' style="width:' . (int) $width . 'px;max-width:100%;"' : '';
	$html  = '<div class="geolocation-track-details"' . $style . '>' . esc_html( $summary );
	if ( $profile ) {
		$elevation = geolocation_get_elevation( $post_id );
		if ( ! empty( $elevation ) ) {
			$html .= geolocation_get_elevation_svg(
				$elevation,
				geolocation_format_track_length( get_post_meta( $post_id, 'geo_track_km', true ) ),
				$width,
				array(
					'title' => __( 'Elevation profile', 'geolocation' ),
					'min'   => number_format_i18n( $elevation['min'] ) . ' m',
					'max'   => number_format_i18n( $elevation['max'] ) . ' m',
				)
			);
		}
	}
	return $html . '</div>';
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
		delete_post_meta( $post_id, 'geo_track_ele' );
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

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by the caller; JSON, cleaned by geolocation_sanitize_elevation().
	$elevation = geolocation_sanitize_elevation( isset( $_POST['geolocation-track-ele'] ) ? wp_unslash( $_POST['geolocation-track-ele'] ) : '' );
	if ( empty( $elevation ) ) {
		// The new track has no elevations, so the ones of a former track do not apply anymore.
		delete_post_meta( $post_id, 'geo_track_ele' );
	} else {
		update_post_meta( $post_id, 'geo_track_ele', wp_json_encode( $elevation ) );
	}
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
