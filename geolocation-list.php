<?php
/**
 * Post list
 *
 * The list of posts in the admin area: a column showing the location and bulk actions to publish it.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Add the column "Location" to the list of posts.
 *
 * @param array $columns The columns of the list.
 * @return array
 */
function geolocation_list_columns( $columns ) {
	$columns['geolocation'] = __( 'Location', 'geolocation' );
	return $columns;
}

/**
 * Print the location of a post and whether it is public into its row of the list.
 *
 * @param string $column The name of the column.
 * @param int    $post_id The id of the post.
 * @return void
 */
function geolocation_list_column( $column, $post_id ) {
	if ( 'geolocation' !== $column ) {
		return;
	}
	$latitude  = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_latitude', true ) );
	$longitude = geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_longitude', true ) );
	if ( '' === $latitude || '' === $longitude ) {
		echo '&mdash;';
		return;
	}
	$address = (string) get_post_meta( $post_id, 'geo_address', true );
	if ( ! get_post_meta( $post_id, 'geo_enabled', true ) ) {
		$state = __( 'Off', 'geolocation' );
	} elseif ( get_post_meta( $post_id, 'geo_public', true ) ) {
		$state = __( 'Public', 'geolocation' );
	} else {
		$state = __( 'Not public', 'geolocation' );
	}
	echo esc_html( '' !== $address ? $address : $latitude . ', ' . $longitude ) . '<br /><span class="geolocation-list-state">' . esc_html( $state ) . '</span>';
}

/**
 * Add the bulk actions which publish the locations of posts or take that back.
 *
 * @param array $actions The bulk actions of the list.
 * @return array
 */
function geolocation_bulk_actions( $actions ) {
	$actions['geolocation_public']     = __( 'Publish location', 'geolocation' );
	$actions['geolocation_not_public'] = __( 'Do not publish location', 'geolocation' );
	return $actions;
}

/**
 * Set the locations of posts to public or not public.
 *
 * Only posts which have a location and which the user may edit are changed. Whether a location is
 * switched on or off stays as it is.
 *
 * @param array $post_ids The ids of the posts.
 * @param bool  $is_public Whether the locations shall be public.
 * @return int The number of posts whose location has been changed.
 */
function geolocation_set_public( $post_ids, $is_public ) {
	$changed = 0;
	foreach ( array_map( 'intval', (array) $post_ids ) as $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			continue;
		}
		if ( '' === geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_latitude', true ) ) || '' === geolocation_clean_coordinate( get_post_meta( $post_id, 'geo_longitude', true ) ) ) {
			continue;
		}
		if ( (bool) get_post_meta( $post_id, 'geo_public', true ) === (bool) $is_public ) {
			continue;
		}
		update_post_meta( $post_id, 'geo_public', $is_public ? 1 : 0 );
		++$changed;
	}
	if ( $changed > 0 ) {
		// The overview maps show other posts now.
		geolocation_flush_page_markers();
	}
	return $changed;
}

/**
 * Run the bulk actions of this plugin. WordPress has verified the nonce of the list before.
 *
 * @param string $redirect The address the browser is sent to afterwards.
 * @param string $action The chosen bulk action.
 * @param array  $post_ids The ids of the selected posts.
 * @return string
 */
function geolocation_handle_bulk_action( $redirect, $action, $post_ids ) {
	if ( ! in_array( $action, array( 'geolocation_public', 'geolocation_not_public' ), true ) ) {
		return $redirect;
	}
	return add_query_arg( 'geolocation_changed', geolocation_set_public( $post_ids, 'geolocation_public' === $action ), $redirect );
}

/**
 * Tell how many locations a bulk action has changed.
 *
 * @return void
 */
function geolocation_bulk_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only a number which is shown.
	if ( ! isset( $_GET['geolocation_changed'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
	$changed = absint( wp_unslash( $_GET['geolocation_changed'] ) );
	/* translators: %d: number of posts. */
	echo '<div id="geolocation-bulk-notice" class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Posts with a changed location: %d', 'geolocation' ), $changed ) ) . '</p></div>';
}

/**
 * Keep the number of changed locations out of the address after the notice has been shown.
 *
 * @param array $args The query arguments WordPress removes from the address.
 * @return array
 */
function geolocation_removable_query_args( $args ) {
	$args[] = 'geolocation_changed';
	return $args;
}
