<?php
/**
 * Convert
 *
 * Replaces the shortcode of existing pages and posts by the blocks, as the blocks are the way to place maps.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Get the pages and posts whose content contains the shortcode.
 *
 * @return array<int> The ids, oldest first.
 */
function geolocation_convert_candidates(): array {
	global $wpdb;
	$shortcode = trim( (string) get_option( 'geolocation_shortcode' ) );
	if ( '' === $shortcode ) {
		return array();
	}
	// The tag without its closing bracket, so shortcodes with attributes are found as well.
	$needle = preg_match( '/^\[([^\[\]\s]+)\]$/', $shortcode, $matches ) ? '[' . $matches[1] : $shortcode;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- runs on the settings page only; WP_Query cannot search the content for a text.
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type IN ( 'page', 'post' ) AND post_status IN ( 'publish', 'future', 'draft', 'pending', 'private' ) AND post_content LIKE %s ORDER BY ID ASC",
			'%' . $wpdb->esc_like( $needle ) . '%'
		)
	);
	return array_map( 'intval', $ids );
}

/**
 * Turn the attributes of the shortcode into the attributes of the block "Geolocation Map".
 *
 * Categories and tags are given by slug, name or id in the shortcode and by id in the block. Like the shortcode,
 * the block uses the terms which exist and leaves out the others. If none of the named terms exists, the shortcode
 * shows no posts; the block could not express that, so the shortcode is kept.
 *
 * @param array<string, string> $atts The attributes as cleaned by geolocation_sanitize_map_atts().
 * @param int                   $page_id The page, whose custom field "category" counts if the shortcode names no category.
 * @return array<string, mixed>|null The attributes of the block, or null if the shortcode cannot be converted.
 */
function geolocation_convert_atts( array $atts, int $page_id ): ?array {
	$attributes = array();
	$categories = (string) $atts['cat'];
	$legacy     = '' === $categories;
	if ( $legacy ) {
		$categories = (string) get_post_meta( $page_id, 'category', true );
	}
	foreach ( array(
		'categories' => array( $categories, 'category' ),
		'tags'       => array( (string) $atts['tag'], 'post_tag' ),
	) as $attribute => $source ) {
		if ( '' === $source[0] ) {
			continue;
		}
		$ids = geolocation_get_term_ids( $source[0], $source[1] );
		if ( empty( $ids ) ) {
			// An unknown category in the custom field showed all posts, as the block does without categories.
			if ( 'categories' === $attribute && $legacy ) {
				continue;
			}
			return null;
		}
		$attributes[ $attribute ] = $ids;
	}
	if ( '' !== $atts['width'] ) {
		$attributes['width'] = str_ends_with( $atts['width'], 'px' ) ? substr( $atts['width'], 0, -2 ) : $atts['width'];
	}
	if ( '' !== $atts['height'] ) {
		$attributes['height'] = (int) $atts['height'];
	}
	if ( '' !== $atts['zoom'] ) {
		$attributes['zoom'] = (int) $atts['zoom'];
	}
	if ( '' !== $atts['route'] ) {
		$attributes['route'] = true;
	}
	return $attributes;
}

/**
 * Convert the shortcodes of one page or post.
 *
 * Only a shortcode which forms a block of its own is converted: a paragraph, a shortcode block or classic
 * content which contains nothing else. On a page it becomes the block "Geolocation Map". In a post it marks
 * where the location is shown, so it becomes the block "Post Location" if the location is set to appear there.
 *
 * @param int $post_id The page or post.
 * @return array{converted: int, kept: int} How many shortcodes have been converted and how many are left.
 */
function geolocation_convert_post( int $post_id ): array {
	$result = array(
		'converted' => 0,
		'kept'      => 0,
	);
	$post   = get_post( $post_id );
	$regex  = geolocation_shortcode_regex();
	if ( ! $post || '' === $regex ) {
		return $result;
	}
	$is_page = 'page' === $post->post_type;
	$blocks  = geolocation_convert_blocks( parse_blocks( $post->post_content ), $regex, $is_page, $post_id, $result );
	if ( $result['converted'] > 0 ) {
		// The previous content stays available as a revision.
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => serialize_blocks( $blocks ),
				)
			)
		);
	}
	return $result;
}

/**
 * Replace the blocks which consist of nothing but the shortcode, also inside other blocks.
 *
 * @param array<int, array<string, mixed>> $blocks The parsed blocks.
 * @param string                           $regex The pattern of the shortcode.
 * @param bool                             $is_page Whether the content belongs to a page.
 * @param int                              $post_id The page or post.
 * @param array{converted: int, kept: int} $result Counts of converted and kept shortcodes, updated by this function.
 * @return array<int, array<string, mixed>> The blocks.
 */
function geolocation_convert_blocks( array $blocks, string $regex, bool $is_page, int $post_id, array &$result ): array {
	foreach ( $blocks as $index => $block ) {
		if ( ! empty( $block['innerBlocks'] ) ) {
			$blocks[ $index ]['innerBlocks'] = geolocation_convert_blocks( $block['innerBlocks'], $regex, $is_page, $post_id, $result );
		}
		$html  = (string) $block['innerHTML'];
		$found = preg_match_all( $regex, $html, $matches );
		if ( ! $found ) {
			continue;
		}
		$alone = 1 === $found && in_array( $block['blockName'], array( null, 'core/paragraph', 'core/shortcode' ), true ) && trim( wp_strip_all_tags( str_replace( $matches[0][0], '', $html ) ) ) === '';
		$new   = $alone ? geolocation_convert_block( $matches, $is_page, $post_id ) : null;
		if ( null === $new ) {
			$result['kept'] += $found;
			continue;
		}
		$blocks[ $index ] = $new;
		++$result['converted'];
	}
	return $blocks;
}

/**
 * Build the block which replaces a shortcode.
 *
 * @param array<int, array<int, string>> $matches The match of the shortcode, with its attributes as first group.
 * @param bool                           $is_page Whether the shortcode is on a page.
 * @param int                            $post_id The page or post.
 * @return array<string, mixed>|null The block, or null if the shortcode stays.
 */
function geolocation_convert_block( array $matches, bool $is_page, int $post_id ): ?array {
	if ( ! $is_page ) {
		// In a post the shortcode only shows the location if it is set to appear where the shortcode is.
		if ( 'shortcode' !== get_option( 'geolocation_map_position' ) ) {
			return null;
		}
		return array(
			'blockName'    => 'geolocation/location',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
	// Editors may turn straight quotes into typographic ones.
	$raw        = isset( $matches[1][0] ) ? str_replace( array( '&#8220;', '&#8221;', '&#8243;', '&quot;', "\u{201C}", "\u{201D}", "\u{2033}" ), '"', $matches[1][0] ) : '';
	$attributes = geolocation_convert_atts( geolocation_sanitize_map_atts( '' === $raw ? array() : shortcode_parse_atts( $raw ) ), $post_id );
	if ( null === $attributes ) {
		return null;
	}
	return array(
		'blockName'    => 'geolocation/map',
		'attrs'        => $attributes,
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
}

/**
 * Convert the shortcodes of all pages and posts and remember the result for the settings page.
 *
 * @return void
 */
function geolocation_convert_all(): void {
	$status = array(
		'converted' => 0,
		'kept'      => array(),
		'finished'  => time(),
	);
	foreach ( geolocation_convert_candidates() as $post_id ) {
		$result               = geolocation_convert_post( $post_id );
		$status['converted'] += $result['converted'];
		if ( $result['kept'] > 0 ) {
			$status['kept'][] = $post_id;
		}
	}
	update_option( 'geolocation_convert_status', $status, false );
	if ( $status['converted'] > 0 ) {
		geolocation_flush_page_markers();
	}
}

/**
 * Describe the shortcodes which are left and the result of the last conversion, for the settings page.
 *
 * @return string The HTML, escaped.
 */
function geolocation_convert_status_html(): string {
	$html   = '';
	$status = get_option( 'geolocation_convert_status' );
	if ( is_array( $status ) && isset( $status['converted'], $status['kept'] ) ) {
		/* translators: %d: number of shortcodes. */
		$html .= '<p class="description">' . esc_html( sprintf( __( 'Last conversion. Shortcodes converted: %d.', 'geolocation' ), $status['converted'] ) ) . '</p>';
	}
	$left = geolocation_convert_candidates();
	if ( empty( $left ) ) {
		return $html . '<p class="description">' . esc_html__( 'No page or post contains the shortcode.', 'geolocation' ) . '</p>';
	}
	$links = array();
	foreach ( $left as $post_id ) {
		$links[] = '<a href="' . esc_url( (string) get_edit_post_link( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a>';
	}
	/* translators: %s: list of linked titles. */
	return $html . '<p class="description">' . sprintf( esc_html__( 'Pages and posts with the shortcode: %s', 'geolocation' ), implode( ', ', $links ) ) . '</p>';
}
