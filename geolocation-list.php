<?php
/**
 * Post list
 *
 * The list of posts in the admin area: a column showing the location, and a field which publishes the
 * locations of several posts at once.
 *
 * @category Components
 * @package geolocation
 * @author Yann Michel <yann@michelpunkt.de>
 * @license GPLv2+
 */

/**
 * Add the column "Location" to the list of posts.
 *
 * @param array<string, string> $columns The columns of the list.
 * @return array<string, string>
 */
function geolocation_list_columns( array $columns ): array {
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
function geolocation_list_column( string $column, int $post_id ): void {
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
 * Add the field "Location" to the form which edits several posts at once.
 *
 * @param string $column The name of the column the form is asked for.
 * @param string $post_type The post type of the list.
 * @return void
 */
function geolocation_bulk_edit_field( string $column, string $post_type ): void {
	if ( 'geolocation' !== $column || 'post' !== $post_type ) {
		return;
	}
	?>
	<fieldset class="inline-edit-col-right">
		<div class="inline-edit-col">
			<label class="inline-edit-group">
				<span class="title"><?php esc_html_e( 'Location', 'geolocation' ); ?></span>
				<select name="geolocation_public" id="geolocation-bulk-public">
					<option value="">&mdash; <?php esc_html_e( 'No change', 'geolocation' ); ?> &mdash;</option>
					<option value="1"><?php esc_html_e( 'Public', 'geolocation' ); ?></option>
					<option value="0"><?php esc_html_e( 'Not public', 'geolocation' ); ?></option>
				</select>
			</label>
		</div>
	</fieldset>
	<?php
}

/**
 * Set the locations of posts to public or not public.
 *
 * Only posts which have a location and which the user may edit are changed. Whether a location is
 * switched on or off stays as it is.
 *
 * @param array<int, int|string> $post_ids The ids of the posts.
 * @param bool                   $is_public Whether the locations shall be public.
 * @return int The number of posts whose location has been changed.
 */
function geolocation_set_public( array $post_ids, bool $is_public ): int {
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
 * Apply the field "Location" of the form which edits several posts at once.
 *
 * WordPress saves every selected post the user may edit, which calls this function for each of them.
 *
 * @param int $post_id The id of the saved post.
 * @return void
 */
function geolocation_bulk_edit_save( int $post_id ): void {
	if ( ! isset( $_REQUEST['bulk_edit'], $_REQUEST['geolocation_public'], $_REQUEST['_wpnonce'] ) ) {
		return;
	}
	$choice = sanitize_text_field( wp_unslash( $_REQUEST['geolocation_public'] ) );
	if ( ! in_array( $choice, array( '0', '1' ), true ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-posts' ) ) {
		// No change has been chosen, or the request does not come from the list of posts.
		return;
	}
	geolocation_set_public( array( $post_id ), '1' === $choice );
}
