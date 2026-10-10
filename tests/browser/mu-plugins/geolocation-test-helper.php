<?php
/**
 * Test helper of the Geolocation plugin for a local test instance only (not part of the plugin).
 *
 * It is a must-use plugin: tests/browser/start.js mounts this folder as wp-content/mu-plugins of the instance.
 *
 * admin-ajax.php?action=geolocation_test&do=... for administrators:
 * seed, classic_on / classic_off, loopback_off / loopback_on, proxy_max_zoom (&value=), language (&locale=), tiles (&post=), run_cron, state, backup, restore, simulate_old, uninstall_test.
 *
 * "seed" creates the demo content of geolocation-test-content.json and resets posts and pages with the same slug.
 *
 * The backup of the plugin's options and geo data is kept in the database (option geolocation_test_backup).
 * A backup in a file turned out to be unreliable in Playground: its PHP workers do not share the state of files.
 *
 * @package geolocation
 */

add_filter(
	'use_block_editor_for_post',
	function ( $use ) {
		return get_option( 'geolocation_test_classic' ) ? false : $use;
	},
	100
);

foreach ( array( 'locale', 'pre_determine_locale' ) as $geolocation_test_hook ) {
	add_filter(
		$geolocation_test_hook,
		function ( $locale ) {
			$test = get_option( 'geolocation_test_locale' );
			return $test ? $test : $locale;
		}
	);
}

// Playground answers every request without cookies by a redirect which logs in ("--login"). Requests of the site to
// itself carry no cookies and would be redirected forever, so they get the cookie telling that this has happened.
add_filter(
	'http_request_args',
	function ( $args, $url ) {
		// Switched off by the action "loopback_off", to see how the plugin reports tiles it cannot fetch.
		// Only the requests for tiles, so the background tasks of WordPress still start.
		if ( 0 === strpos( $url, home_url() ) && ! ( get_option( 'geolocation_test_no_loopback' ) && false !== strpos( $url, 'osm-tiles' ) ) ) {
			$args['cookies']   = isset( $args['cookies'] ) && is_array( $args['cookies'] ) ? $args['cookies'] : array();
			$args['cookies'][] = new WP_Http_Cookie(
				array(
					'name'  => 'playground_auto_login_already_happened',
					'value' => '1',
				)
			);
		}
		return $args;
	},
	10,
	2
);

/**
 * Create or reset the demo content: posts with locations and tracks, pages with overview maps, categories and tags.
 *
 * @return array What has been created or updated.
 */
function geolocation_test_seed() {
	global $wp_rewrite;
	$data = json_decode( (string) file_get_contents( __DIR__ . '/geolocation-test-content.json' ), true );
	if ( ! is_array( $data ) || empty( $data['posts'] ) ) {
		return array( 'error' => 'geolocation-test-content.json could not be read' );
	}
	$result = array(
		'created' => array(),
		'updated' => array(),
	);

	// The checks use the addresses of the posts, so the permalinks have to contain the date.
	$structure = '/%year%/%monthnum%/%day%/%postname%/';
	if ( get_option( 'permalink_structure' ) !== $structure ) {
		$wp_rewrite->set_permalink_structure( $structure );
		flush_rewrite_rules( false );
		$result['permalinks'] = $structure;
	}

	// Saving a post must not start pre-caching: the seed shall not request tiles.
	remove_action( 'save_post_post', 'geolocation_precache_saved_post', 20 );
	remove_action( 'save_post_page', 'geolocation_precache_saved_post', 20 );

	$term_id = function ( $name, $taxonomy ) {
		$term = term_exists( $name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy );
		}
		return is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	};

	foreach ( $data['posts'] as $item ) {
		$existing = get_posts(
			array(
				'name'        => $item['slug'],
				'post_type'   => $item['type'],
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);
		$content  = preg_replace_callback(
			'/\{\{category:([^}]+)\}\}/',
			function ( $match ) use ( $term_id ) {
				return (string) $term_id( $match[1], 'category' );
			},
			$item['content']
		);
		$post_id  = wp_insert_post(
			wp_slash(
				array(
					'ID'            => $existing ? $existing[0]->ID : 0,
					'post_type'     => $item['type'],
					'post_status'   => 'publish',
					'post_name'     => $item['slug'],
					'post_title'    => $item['title'],
					'post_content'  => $content,
					'post_excerpt'  => $item['excerpt'],
					'post_date'     => $item['date'],
					'post_date_gmt' => get_gmt_from_date( $item['date'] ),
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			$result['errors'][] = $item['slug'] . ': ' . $post_id->get_error_message();
			continue;
		}
		$result[ $existing ? 'updated' : 'created' ][] = $item['slug'];

		if ( 'post' === $item['type'] ) {
			wp_set_post_categories(
				$post_id,
				array_filter(
					array_map(
						function ( $name ) use ( $term_id ) {
							return $term_id( $name, 'category' );
						},
						$item['categories']
					)
				)
			);
			wp_set_post_tags( $post_id, $item['tags'] );
		}
		foreach ( array_keys( get_post_meta( $post_id ) ) as $key ) {
			if ( 0 === strpos( $key, 'geo_' ) || 'category' === $key ) {
				delete_post_meta( $post_id, $key );
			}
		}
		foreach ( (array) $item['meta'] as $key => $value ) {
			update_post_meta( $post_id, $key, wp_slash( $value ) );
		}
		if ( '' !== $item['image'] && ! has_post_thumbnail( $post_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$upload = wp_upload_bits( $item['image'], null, (string) file_get_contents( __DIR__ . '/' . $item['image'] ), substr( $item['date'], 0, 7 ) );
			if ( empty( $upload['error'] ) ) {
				$attachment = wp_insert_attachment(
					array(
						'post_mime_type' => 'image/png',
						'post_title'     => $item['title'],
						'post_status'    => 'inherit',
					),
					$upload['file'],
					$post_id
				);
				wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $upload['file'] ) );
				set_post_thumbnail( $post_id, $attachment );
			}
		}
	}

	// The settings the checks start from. The proxy is used if its plugin is active.
	foreach ( array_keys( geolocation_get_settings_definition() ) as $name ) {
		if ( 'geolocation_google_maps_api_key' !== $name ) {
			delete_option( $name );
		}
	}
	geolocation_default_settings();
	update_option( 'geolocation_osm_use_proxy', false !== has_filter( 'osm_tiles_proxy_get_proxy_url' ) ? '1' : '' );
	geolocation_flush_page_markers();
	$result['proxy'] = false !== has_filter( 'osm_tiles_proxy_get_proxy_url' );
	return $result;
}

/** The plugin's options and geo data, as rows of the database. */
function geolocation_test_read() {
	global $wpdb;
	return array(
		'options' => $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'geolocation\_%' AND option_name NOT LIKE 'geolocation\_test\_%' ORDER BY option_name", ARRAY_A ),
		'meta'    => $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE 'geo\_%' ORDER BY post_id, meta_key", ARRAY_A ),
	);
}

/**
 * Replace the plugin's options and geo data by the given rows.
 *
 * @param array $data The rows, as returned by geolocation_test_read().
 */
function geolocation_test_write( $data ) {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'geolocation\_%' AND option_name NOT LIKE 'geolocation\_test\_%'" );
	foreach ( $data['options'] as $row ) {
		$wpdb->insert( $wpdb->options, $row );
	}
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'geo\_%'" );
	foreach ( $data['meta'] as $row ) {
		$wpdb->insert( $wpdb->postmeta, $row );
	}
	wp_cache_flush();
}

/** Names and counts only, never values: the options hold an API key. */
function geolocation_test_state() {
	global $wpdb;
	$data = geolocation_test_read();
	$meta = array();
	foreach ( $data['meta'] as $row ) {
		$meta[ $row['meta_key'] ] = isset( $meta[ $row['meta_key'] ] ) ? $meta[ $row['meta_key'] ] + 1 : 1;
	}
	$names = array( 'geolocation_map_width', 'geolocation_map_width_page', 'geolocation_provider', 'geolocation_shortcode', 'geolocation_map_display', 'geolocation_map_position', 'geolocation_osm_use_proxy', 'geolocation_track_trim', 'geolocation_osm_leaflet_js_url' );
	return array(
		'options'    => wp_list_pluck( $data['options'], 'option_name' ),
		'version'    => get_option( 'geolocation_version' ),
		'values'     => array_combine( $names, array_map( 'get_option', $names ) ),
		'apiKey'     => '' !== (string) get_option( 'geolocation_google_maps_api_key' ),
		'meta'       => $meta,
		'transients' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_geolocation\_%'" ),
		'cron'       => (bool) wp_next_scheduled( 'geolocation_update_addresses_batch' ),
		'backup'     => false !== get_option( 'geolocation_test_backup' ),
		'locale'     => (string) get_option( 'geolocation_test_locale' ),
		'checksum'   => md5( wp_json_encode( $data ) ),
	);
}

add_action(
	'wp_ajax_geolocation_test',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		$do     = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		$result = array();
		// Read the stored backup; null if there is none or it is damaged.
		$stored = function () {
			$data = json_decode( (string) get_option( 'geolocation_test_backup' ), true );
			return is_array( $data ) && isset( $data['options'], $data['meta'] ) ? $data : null;
		};
		switch ( $do ) {
			case 'seed':
				$result = geolocation_test_seed();
				break;
			case 'classic_on':
				update_option( 'geolocation_test_classic', 1 );
				break;
			case 'tiles':
				// The tiles the plugin would pre-cache for a post, and what it knows about pre-caching.
				$post_id            = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
				$url                = function_exists( 'geolocation_precache_tiles_url' ) ? geolocation_precache_tiles_url() : '';
				$result['tilesUrl'] = $url;
				$result['tiles']    = array();
				foreach ( geolocation_get_post_tiles( $post_id ) as $tile ) {
					$tile_url          = geolocation_tile_url( '' !== $url ? $url : '{z}/{x}/{y}', $tile );
					$result['tiles'][] = array(
						'tile'   => implode( '/', $tile ),
						'url'    => $tile_url,
						'stored' => '' !== $url && file_exists( geolocation_tile_file( $tile_url ) ),
					);
				}
				$result['precacheStatus'] = get_option( 'geolocation_precache_status' );
				// Scheduled tasks of pre-caching, whatever their arguments are.
				$result['cron'] = array( false, false );
				foreach ( (array) _get_cron_array() as $hooks ) {
					$result['cron'][0] = $result['cron'][0] || isset( $hooks['geolocation_precache_batch'] );
					$result['cron'][1] = $result['cron'][1] || isset( $hooks['geolocation_precache_post'] );
				}
				break;
			case 'run_cron':
				// Run the due background tasks of the plugin now.
				foreach ( (array) _get_cron_array() as $time => $hooks ) {
					foreach ( $hooks as $hook => $events ) {
						if ( 0 !== strpos( $hook, 'geolocation_precache' ) ) {
							continue;
						}
						foreach ( $events as $event ) {
							wp_unschedule_event( $time, $hook, $event['args'] );
							do_action_ref_array( $hook, $event['args'] );
							$result['ran'][] = $hook;
						}
					}
				}
				break;
			case 'probe':
				// Can PHP of this instance reach the tile server and the site itself?
				foreach ( array(
					'osm'      => 'https://tile.openstreetmap.org/0/0/0.png',
					'self'     => home_url( '/wp-login.php' ),
					'selfTile' => content_url( '/cache/osm-tiles/a/3/4/2.png' ),
				) as $name => $probe_url ) {
					$response        = wp_remote_get(
						$probe_url,
						array(
							'timeout'   => 20,
							'sslverify' => false,
						)
					);
					$result[ $name ] = is_wp_error( $response ) ? 'error: ' . $response->get_error_message() : wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_header( $response, 'content-type' ) . ' ' . strlen( wp_remote_retrieve_body( $response ) ) . ' bytes';
				}
				break;
			case 'loopback_off':
				update_option( 'geolocation_test_no_loopback', 1 );
				break;
			case 'loopback_on':
				delete_option( 'geolocation_test_no_loopback' );
				break;
			case 'proxy_max_zoom':
				// Limit the zoom levels the proxy plugin delivers, to see how the plugin reports tiles it cannot fetch.
				if ( isset( $_GET['value'] ) && '' !== $_GET['value'] ) {
					update_option( 'osm_tiles_proxy_max_zoom', absint( $_GET['value'] ) );
				} else {
					delete_option( 'osm_tiles_proxy_max_zoom' );
				}
				break;
			case 'classic_off':
				delete_option( 'geolocation_test_classic' );
				break;
			case 'language':
				// Also languages without an installed language pack of WordPress, to check the texts of the plugin.
				$locale = isset( $_GET['locale'] ) ? preg_replace( '/[^A-Za-z_]/', '', sanitize_text_field( wp_unslash( $_GET['locale'] ) ) ) : '';
				if ( '' === $locale ) {
					delete_option( 'geolocation_test_locale' );
				} else {
					update_option( 'geolocation_test_locale', $locale );
				}
				break;
			case 'backup':
				if ( null !== $stored() ) {
					wp_send_json_error( 'there is a backup already: restore it first' );
				}
				$data = geolocation_test_read();
				update_option( 'geolocation_test_backup', wp_json_encode( $data ), false );
				wp_cache_flush();
				if ( $stored() !== $data ) {
					delete_option( 'geolocation_test_backup' );
					wp_send_json_error( 'the backup could not be read back' );
				}
				break;
			case 'restore':
				$data = $stored();
				if ( null === $data ) {
					wp_send_json_error( 'no backup' );
				}
				geolocation_test_write( $data );
				if ( geolocation_test_read() !== $data ) {
					wp_send_json_error( 'the restored data differs from the backup; the backup is kept' );
				}
				delete_option( 'geolocation_test_backup' );
				break;
			case 'simulate_old':
				// The state an installation of version 1.9.9 leaves behind. Needs a backup.
				if ( null === $stored() ) {
					wp_send_json_error( 'backup first' );
				}
				foreach ( array( 'geolocation_version', 'geolocation_markers_version', 'geolocation_track_trim', 'geolocation_osm_strict_privacy', 'geolocation_shortcode', 'geolocation_map_width_page', 'geolocation_map_height_page' ) as $name ) {
					delete_option( $name );
				}
				update_option( 'geolocation_osm_leaflet_js_url', 'https://unpkg.com/leaflet@1.9.3/dist/leaflet.js' );
				update_option( 'geolocation_osm_leaflet_css_url', 'https://unpkg.com/leaflet@1.9.3/dist/leaflet.css' );
				wp_cache_flush();
				break;
			case 'uninstall_test':
				// Everything in one request: the data is held in memory and, as a safety copy, in the database.
				if ( null !== $stored() ) {
					wp_send_json_error( 'there is a backup already: restore it first' );
				}
				$data = geolocation_test_read();
				update_option( 'geolocation_test_backup', wp_json_encode( $data ), false );
				wp_cache_flush();
				if ( $stored() !== $data ) {
					delete_option( 'geolocation_test_backup' );
					wp_send_json_error( 'the safety copy could not be read back; nothing was changed' );
				}
				$result['before'] = geolocation_test_state();
				geolocation_uninstall();
				wp_cache_flush();
				$result['uninstalled'] = geolocation_test_state();
				geolocation_test_write( $data );
				$result['identical'] = geolocation_test_read() === $data;
				if ( $result['identical'] ) {
					delete_option( 'geolocation_test_backup' );
				}
				break;
		}
		wp_send_json_success( array_merge( $result, array( 'state' => geolocation_test_state() ) ) );
	}
);
