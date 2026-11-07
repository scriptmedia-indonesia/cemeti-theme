<?php
/**
 * "Move to …" for imported posts, right where they are: the Posts screen.
 *
 * Content exported from the old site arrives as ordinary Posts. The Cemeti Converter (Tools) sorts all of them
 * by rules in one go; this file lets an editor decide for chosen posts: a bulk action in the Posts list and a box
 * on the edit screen of one post. Both use the same conversion as the Converter (dates, artists, redirects,
 * language), but with the type and term the editor picked.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The places a post can be moved to: key "type|Term" (term optional) => label.
 *
 * @return array<string, string>
 */
function cemeti_move_choices() {
	$choices = array();
	$tax_cfg = cemeti_config( 'taxonomies' );
	foreach ( cemeti_convert_targets() as $type => $target ) {
		$taxonomy = $target[1];
		$terms    = $taxonomy && isset( $tax_cfg[ $taxonomy ]['terms'] ) ? $tax_cfg[ $taxonomy ]['terms'] : array();
		if ( ! $terms ) {
			$choices[ $type . '|' ] = $target[0];
			continue;
		}
		foreach ( $terms as $term ) {
			$choices[ $type . '|' . $term ] = $target[0] . ' — ' . $term;
		}
	}
	return $choices;
}

/**
 * Move one post. Returns the converter's result code ("converted", "no_date", "skipped").
 *
 * @param int    $post_id Post ID.
 * @param string $choice  Key from cemeti_move_choices().
 * @return string
 */
function cemeti_move_post( $post_id, $choice ) {
	$choices = cemeti_move_choices();
	if ( ! isset( $choices[ $choice ] ) || 'post' !== get_post_type( $post_id ) ) {
		return 'skipped';
	}
	list( $type, $term ) = array_pad( explode( '|', $choice, 2 ), 2, '' );
	$settings               = cemeti_convert_settings();
	$settings['force_type'] = $type;
	$settings['force_term'] = $term;
	return cemeti_convert_post( (int) $post_id, $settings );
}

/**
 * Bulk actions in Posts → All Posts.
 *
 * @param array $actions Bulk actions.
 * @return array
 */
function cemeti_move_bulk_actions( $actions ) {
	foreach ( cemeti_move_choices() as $key => $label ) {
		$actions[ 'cemeti_move|' . $key ] = sprintf( /* translators: %s: content type */ __( 'Move to %s', 'scriptmedia-cemeti' ), $label );
	}
	return $actions;
}
add_filter( 'bulk_actions-edit-post', 'cemeti_move_bulk_actions' );

/**
 * Run a "Move to …" bulk action.
 *
 * @param string $redirect Redirect URL.
 * @param string $action   Bulk action.
 * @param int[]  $post_ids Selected posts.
 * @return string
 */
function cemeti_move_handle_bulk( $redirect, $action, $post_ids ) {
	if ( 0 !== strpos( (string) $action, 'cemeti_move|' ) || ! current_user_can( 'edit_others_posts' ) ) {
		return $redirect;
	}
	$choice = substr( (string) $action, strlen( 'cemeti_move|' ) );
	$moved  = 0;
	foreach ( (array) $post_ids as $post_id ) {
		if ( current_user_can( 'edit_post', $post_id ) && 'skipped' !== cemeti_move_post( (int) $post_id, $choice ) ) {
			++$moved;
		}
	}
	return add_query_arg( 'cemeti_moved', $moved, $redirect );
}
add_filter( 'handle_bulk_actions-edit-post', 'cemeti_move_handle_bulk', 10, 3 );

/**
 * Notice on the Posts list: how many were moved, or where to sort the imported posts.
 */
function cemeti_move_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-post' !== (string) $screen->id || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	if ( isset( $_GET['cemeti_moved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( /* translators: %d: number of posts */ _n( '%d post moved.', '%d posts moved.', (int) $_GET['cemeti_moved'], 'scriptmedia-cemeti' ), (int) $_GET['cemeti_moved'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
		);
		return;
	}
	printf(
		'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'Imported posts: tick the posts, choose Bulk actions → "Move to …" (Exhibition, Residency, Public Program, Open Studio, Open Call, Journal) and press Apply. To sort every post by the old site\'s categories in one go, use', 'scriptmedia-cemeti' ),
		esc_url( admin_url( 'tools.php?page=cemeti-converter' ) ),
		esc_html__( 'Tools → Cemeti Converter.', 'scriptmedia-cemeti' )
	);
}
add_action( 'admin_notices', 'cemeti_move_notice' );

/**
 * Box on the edit screen of an ordinary post: move this one post.
 *
 * @param WP_Post $post Post being edited.
 */
function cemeti_move_add_box( $post ) {
	if ( 'auto-draft' === $post->post_status || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	add_meta_box( 'cemeti_move', __( 'Cemeti: move this post', 'scriptmedia-cemeti' ), 'cemeti_move_render_box', 'post', 'side', 'high' );
}
add_action( 'add_meta_boxes_post', 'cemeti_move_add_box' );

/**
 * Render the move box.
 *
 * @param WP_Post $post Post being edited.
 */
function cemeti_move_render_box( $post ) {
	echo '<p>' . esc_html__( 'Turn this post into a Cemeti entry. Date, artists and the old address are carried over; the text and pictures stay.', 'scriptmedia-cemeti' ) . '</p>';
	echo '<p><label class="screen-reader-text" for="cemeti_move_choice">' . esc_html__( 'Move to', 'scriptmedia-cemeti' ) . '</label><select id="cemeti_move_choice" name="cemeti_move_choice" class="widefat">';
	foreach ( cemeti_move_choices() as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';
	printf(
		'<p><a class="button button-primary" href="%s" data-base="%s" onclick="this.href=this.dataset.base+encodeURIComponent(document.getElementById(\'cemeti_move_choice\').value);">%s</a></p>',
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_move_post&post=' . (int) $post->ID ), 'cemeti_move_' . (int) $post->ID ) . '&choice=' ),
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_move_post&post=' . (int) $post->ID ), 'cemeti_move_' . (int) $post->ID ) . '&choice=' ),
		esc_html__( 'Move', 'scriptmedia-cemeti' )
	);
}

/**
 * Handle the box's Move button, then open the converted entry.
 */
function cemeti_move_handle_single() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'cemeti_move_' . $post_id );
	$choice = isset( $_GET['choice'] ) ? sanitize_text_field( wp_unslash( $_GET['choice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$result = cemeti_move_post( $post_id, $choice );
	if ( 'skipped' === $result ) {
		wp_safe_redirect( add_query_arg( 'cemeti_moved', 0, admin_url( 'edit.php' ) ) );
		exit;
	}
	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $post_id ) );
	exit;
}
add_action( 'admin_post_cemeti_move_post', 'cemeti_move_handle_single' );
