<?php
/**
 * Two-way "related" links, limited to 4 per entry. Saving A with B in its list
 * also adds A to B's list; removing B removes A from B. If B is already full
 * (4 entries) the link is dropped from A so both sides always agree.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maximum related entries per post.
 *
 * @return int
 */
function cemeti_relation_max() {
	return 4;
}

/**
 * Raw related list of a post.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Field key.
 * @return int[]
 */
function cemeti_related_ids( $post_id, $key = 'related' ) {
	$value = get_post_meta( $post_id, 'cemeti_' . $key, true );
	return is_array( $value ) ? array_values( array_map( 'intval', $value ) ) : array();
}

/**
 * Write a related list directly (no save_post, so no recursion).
 *
 * @param int    $post_id Post ID.
 * @param string $key     Field key.
 * @param int[]  $ids     New list.
 */
function cemeti_store_related( $post_id, $key, array $ids ) {
	if ( $ids ) {
		update_post_meta( $post_id, 'cemeti_' . $key, array_values( $ids ) );
	} else {
		delete_post_meta( $post_id, 'cemeti_' . $key );
	}
}

/**
 * Mirror the new list of $post_id onto the other side.
 *
 * @param int    $post_id Post being saved.
 * @param string $key     Field key.
 * @param int[]  $new     Sanitized new list.
 * @return int[] The list to store on $post_id (links that could not be mirrored removed).
 */
function cemeti_sync_relations( $post_id, $key, array $new ) {
	$max = cemeti_relation_max();
	$old = cemeti_related_ids( $post_id, $key );
	$new = array_values( array_diff( array_slice( $new, 0, $max ), array( (int) $post_id ) ) );

	foreach ( array_diff( $old, $new ) as $other ) {
		cemeti_store_related( $other, $key, array_values( array_diff( cemeti_related_ids( $other, $key ), array( (int) $post_id ) ) ) );
	}

	$kept = array();
	foreach ( $new as $other ) {
		$theirs = cemeti_related_ids( $other, $key );
		if ( in_array( (int) $post_id, $theirs, true ) ) {
			$kept[] = $other;
		} elseif ( count( $theirs ) < $max ) {
			$theirs[] = (int) $post_id;
			cemeti_store_related( $other, $key, $theirs );
			$kept[] = $other;
		}
	}
	return $kept;
}

/**
 * Remove a deleted post from every list it appears in.
 *
 * @param int $post_id Post ID.
 */
function cemeti_unlink_deleted_post( $post_id ) {
	foreach ( cemeti_related_ids( $post_id ) as $other ) {
		cemeti_store_related( $other, 'related', array_values( array_diff( cemeti_related_ids( $other ), array( (int) $post_id ) ) ) );
	}
}
add_action( 'before_delete_post', 'cemeti_unlink_deleted_post' );

/**
 * Related posts of an entry (published only).
 *
 * @param int|null $post_id Post ID. Defaults to the current post.
 * @return WP_Post[]
 */
function cemeti_get_related( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$ids     = cemeti_related_ids( $post_id );
	if ( ! $ids ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'any',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => cemeti_relation_max(),
			'post_status'    => 'publish',
		)
	);
}
