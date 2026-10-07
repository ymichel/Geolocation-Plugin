<?php
/**
 * Block
 *
 * The block "Geolocation Map" shows the overview map without typing a shortcode,
 * the block "Post Location" shows the location of a post wherever it is placed.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Register the blocks "Geolocation Map" and "Post Location" and their editor script.
 *
 * @return void
 */
function geolocation_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	wp_register_script(
		'geolocation_block',
		plugins_url( 'js/geolocation-block.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-core-data', 'wp-html-entities' ),
		GEOLOCATION__VERSION,
		true
	);
	wp_add_inline_script( 'geolocation_block', 'var geolocationBlock = ' . wp_json_encode( geolocation_get_block_data() ) . ';', 'before' );
	register_block_type(
		'geolocation/map',
		array(
			'api_version'     => 3,
			'title'           => geolocation_block_text( __( 'Geolocation Map', 'geolocation' ) ),
			'description'     => geolocation_block_text( __( 'Shows the locations of your posts on a map.', 'geolocation' ) ),
			'category'        => 'widgets',
			'icon'            => 'location-alt',
			'keywords'        => array( 'map', 'location', 'route' ),
			'attributes'      => array(
				'categories' => array(
					'type'    => 'array',
					'items'   => array( 'type' => 'integer' ),
					'default' => array(),
				),
				'tags'       => array(
					'type'    => 'array',
					'items'   => array( 'type' => 'integer' ),
					'default' => array(),
				),
				'width'      => array(
					'type'    => 'string',
					'default' => '',
				),
				'height'     => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'zoom'       => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'route'      => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'supports'        => array(
				'html'  => false,
				'align' => array( 'wide', 'full' ),
			),
			'editor_script'   => 'geolocation_block',
			'render_callback' => 'geolocation_render_block',
		)
	);
	register_block_type(
		'geolocation/location',
		array(
			'api_version'     => 3,
			'title'           => geolocation_block_text( __( 'Post Location', 'geolocation' ) ),
			'description'     => geolocation_block_text( __( 'Shows the location of the post.', 'geolocation' ) ),
			'category'        => 'widgets',
			'icon'            => 'location',
			'keywords'        => array( 'geolocation', 'map', 'track' ),
			'attributes'      => array(
				'display' => array(
					'type'    => 'string',
					'enum'    => array( '', 'plain', 'link', 'map' ),
					'default' => '',
				),
				'width'   => array(
					'type'    => 'integer',
					'default' => 0,
				),
				'height'  => array(
					'type'    => 'integer',
					'default' => 0,
				),
				// The details of the track: empty for the plugin settings, or "show" / "hide".
				'figures' => array(
					'type'    => 'string',
					'enum'    => array( '', 'show', 'hide' ),
					'default' => '',
				),
				'profile' => array(
					'type'    => 'string',
					'enum'    => array( '', 'show', 'hide' ),
					'default' => '',
				),
				// The link to the external map: empty for the plugin setting, or "show" / "hide".
				'link'    => array(
					'type'    => 'string',
					'enum'    => array( '', 'show', 'hide' ),
					'default' => '',
				),
			),
			'supports'        => array(
				'html' => false,
			),
			// Inside a query loop the block shows the location of each post.
			'uses_context'    => array( 'postId' ),
			'editor_script'   => 'geolocation_block',
			'render_callback' => 'geolocation_render_location_block',
		)
	);
}

/**
 * Prepare a translated text for the block editor, which shows texts as they are.
 *
 * Some translations of this plugin use HTML entities for special characters.
 *
 * @param string $text The translated text.
 * @return string
 */
function geolocation_block_text( $text ) {
	return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Get the data the editor script of the block needs.
 *
 * @return array
 */
function geolocation_get_block_data() {
	return array(
		'width'   => (int) get_option( 'geolocation_map_width_page' ),
		'height'  => (int) get_option( 'geolocation_map_height_page' ),
		'display' => (string) get_option( 'geolocation_map_display' ),
		// The blocks are previewed by a small page of their own, which runs the scripts of the website.
		'preview' => add_query_arg(
			array(
				'action'   => 'geolocation_preview',
				'_wpnonce' => wp_create_nonce( 'geolocation_preview' ),
			),
			admin_url( 'admin-ajax.php' )
		),
		'i18n'    => array_map(
			'geolocation_block_text',
			array(
				'locationTitle' => __( 'Post Location', 'geolocation' ),
				'previewTitle'  => __( 'Preview', 'geolocation' ),
				'tracks'        => __( 'Tracks', 'geolocation' ),
				'figures'       => __( 'Key figures of the track', 'geolocation' ),
				'profile'       => __( 'Elevation profile', 'geolocation' ),
				'mapLink'       => __( 'Link to the external map', 'geolocation' ),
				'show'          => __( 'Show', 'geolocation' ),
				'hide'          => __( 'Hide', 'geolocation' ),
				'locationHelp'  => __( 'The location of this post is shown here, as set in the Geolocation box below the editor.', 'geolocation' ),
				'display'       => __( 'Display', 'geolocation' ),
				'asSettings'    => __( 'As in the plugin settings', 'geolocation' ),
				'plain'         => __( 'Plain text.', 'geolocation' ),
				'link'          => __( 'Simple link w/hover.', 'geolocation' ),
				'simpleMap'     => __( 'Simple map (static).', 'geolocation' ),
				'heightOnly'    => __( 'Height', 'geolocation' ),
				'title'         => __( 'Geolocation Map', 'geolocation' ),
				'posts'         => __( 'Posts', 'geolocation' ),
				'categories'    => __( 'Categories', 'geolocation' ),
				'tags'          => __( 'Tags', 'geolocation' ),
				'postsHelp'     => __( 'Leave both empty to show all posts with a location.', 'geolocation' ),
				'map'           => __( 'Map', 'geolocation' ),
				'width'         => __( 'Width', 'geolocation' ),
				'widthHelp'     => __( 'In pixels or percent, e.g. 600 or 100%. Empty: the value from the plugin settings.', 'geolocation' ),
				'height'        => __( 'Height in pixels', 'geolocation' ),
				'heightHelp'    => __( 'Empty: the value from the plugin settings.', 'geolocation' ),
				'fit'           => __( 'Fit the map to the markers', 'geolocation' ),
				'zoom'          => __( 'Zoom level', 'geolocation' ),
				'route'         => __( 'Route', 'geolocation' ),
				'routeHelp'     => __( 'Connects the locations with a line, in the order of the post dates.', 'geolocation' ),
				'placeholder'   => __( 'The map is shown on the website. Choose the posts and the size in the block settings.', 'geolocation' ),
			)
		),
	);
}

/**
 * Turn the attributes of the block into the attributes of the shortcode.
 *
 * @param array $attributes The attributes of the block.
 * @return array The attributes as accepted by geolocation_sanitize_map_atts().
 */
function geolocation_block_to_map_atts( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$atts       = array();
	foreach ( array(
		'categories' => 'cat',
		'tags'       => 'tag',
	) as $attribute => $key ) {
		if ( ! empty( $attributes[ $attribute ] ) && is_array( $attributes[ $attribute ] ) ) {
			$atts[ $key ] = implode( ',', array_filter( array_map( 'intval', $attributes[ $attribute ] ) ) );
		}
	}
	if ( isset( $attributes['width'] ) && '' !== trim( (string) $attributes['width'] ) ) {
		$atts['width'] = (string) $attributes['width'];
	} elseif ( isset( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
		// A wide or full aligned block fills its container.
		$atts['width'] = '100%';
	}
	foreach ( array( 'height', 'zoom' ) as $key ) {
		if ( ! empty( $attributes[ $key ] ) ) {
			$atts[ $key ] = (string) (int) $attributes[ $key ];
		}
	}
	if ( ! empty( $attributes['route'] ) ) {
		$atts['route'] = '1';
	}
	return $atts;
}

/**
 * Render the block "Geolocation Map".
 *
 * @param array $attributes The attributes of the block.
 * @return string The HTML of the map, or an empty string if there is nothing to show.
 */
function geolocation_render_block( $attributes ) {
	$atts = geolocation_sanitize_map_atts( geolocation_block_to_map_atts( $attributes ) );
	// The custom field "category" of a page only belongs to the shortcode.
	$result = geolocation_get_page_markers( $atts, false );
	$map    = geolocation_get_page_map( $atts, $result, 0, wp_unique_id( 'geolocation-map-' ) );
	if ( '' === $map ) {
		return '';
	}
	return '<div ' . get_block_wrapper_attributes() . '>' . $map . '</div>';
}

/**
 * Render the block "Post Location".
 *
 * @param array    $attributes The attributes of the block.
 * @param string   $content The content of the block, which is empty.
 * @param WP_Block $block The block, providing the post of a query loop.
 * @return string The HTML of the location, or an empty string if there is nothing to show.
 */
function geolocation_render_location_block( $attributes, $content = '', $block = null ) {
	$post = get_post( $block && ! empty( $block->context['postId'] ) ? (int) $block->context['postId'] : null );
	if ( ! $post ) {
		return '';
	}
	$display = isset( $attributes['display'] ) ? (string) $attributes['display'] : '';
	$width   = isset( $attributes['width'] ) ? (int) $attributes['width'] : 0;
	$height  = isset( $attributes['height'] ) ? (int) $attributes['height'] : 0;
	$html    = geolocation_get_location_html( $post, $display, $width, $height, $attributes );
	if ( '' === $html ) {
		return '';
	}
	return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
}

/**
 * Print the preview of a block for the block editor: a small page which runs the scripts of the website.
 *
 * The editor shows this page inside the block, so the maps of both providers work as on the website.
 *
 * @return void
 */
function geolocation_block_preview() {
	check_ajax_referer( 'geolocation_preview' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( '', '', array( 'response' => 403 ) );
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, the values are cleaned by the functions rendering the blocks.
	$attributes = isset( $_GET['atts'] ) ? json_decode( wp_unslash( $_GET['atts'] ), true ) : array();
	$attributes = is_array( $attributes ) ? $attributes : array();
	$min_height = 0;
	if ( isset( $_GET['block'] ) && 'location' === $_GET['block'] ) {
		$post    = isset( $_GET['post'] ) ? get_post( absint( $_GET['post'] ) ) : null;
		$display = isset( $attributes['display'] ) ? (string) $attributes['display'] : '';
		$html    = '';
		if ( $post && current_user_can( 'edit_post', $post->ID ) ) {
			$html = geolocation_get_location_html( $post, $display, isset( $attributes['width'] ) ? (int) $attributes['width'] : 0, isset( $attributes['height'] ) ? (int) $attributes['height'] : 0, $attributes );
		}
		$empty = __( 'This post has no location to show yet. Set it in the Geolocation box below the editor and save the post.', 'geolocation' );
		if ( 'link' === ( '' === $display ? get_option( 'geolocation_map_display' ) : $display ) ) {
			// Room for the map shown while hovering the link.
			$min_height = (int) get_option( 'geolocation_map_height' ) + 110;
		}
	} else {
		$atts  = geolocation_sanitize_map_atts( geolocation_block_to_map_atts( $attributes ) );
		$html  = geolocation_get_page_map( $atts, geolocation_get_page_markers( $atts, false ), 0, 'geolocation-map-preview' );
		$empty = __( 'No post with a visible location matches these settings.', 'geolocation' );
	}
	if ( '' === $html ) {
		$html = '<p class="geolocation-preview-empty">' . esc_html( $empty ) . '</p>';
	}

	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
	echo '<!DOCTYPE html><html><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '"><meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<style>html,body{margin:0;padding:0;background:transparent;}body{font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e1e1e;}.geolocation-preview-empty{margin:0;padding:16px;border:1px dashed #949494;}</style>';
	// Only the styles and scripts enqueued for the block; the hooks of the website's head and footer do not apply here.
	wp_styles()->do_items();
	echo '</head><body><div id="geolocation-preview" style="min-height:' . (int) $min_height . 'px;">';
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the functions rendering the blocks.
	echo '</div>';
	print_late_styles();
	print_footer_scripts();
	// Tell the editor how high the preview is.
	echo '<script>( function () { function send() { parent.postMessage( { geolocationPreviewHeight: Math.ceil( document.getElementById( "geolocation-preview" ).getBoundingClientRect().height ) }, "*" ); } window.addEventListener( "load", send ); if ( "ResizeObserver" in window ) { new ResizeObserver( send ).observe( document.getElementById( "geolocation-preview" ) ); } send(); }() );</script>';
	echo '</body></html>';
	exit;
}
