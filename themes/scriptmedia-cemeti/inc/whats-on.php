<?php
/**
 * Feeds the "What's on" panel with what is happening now: the current
 * Exhibition, Open Studio and Residency.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the What's on entries.
 *
 * @param array $items Entries so far.
 * @return array<int, array{label:string,title:string,meta:string,url:string}>
 */
function cemeti_whats_on_items( $items ) {
	$cache_key = 'cemeti_whats_on_' . cemeti_current_lang() . '_' . cemeti_today() . '_' . (int) get_option( 'cemeti_content_version', 0 );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return array_merge( $items, $cached );
	}

	$found  = array();
	$labels = array(
		'exhibition'  => 'Exhibition',
		'open_studio' => 'Open Studio',
		'residency'   => 'Residency',
	);
	foreach ( $labels as $post_type => $label ) {
		$query = cemeti_status_query( $post_type, 'current', array( 'posts_per_page' => 1, 'no_found_rows' => true ) );
		foreach ( $query->posts as $post ) {
			$found[] = array(
				'label' => $label,
				'title' => get_the_title( $post ),
				'meta'  => cemeti_get_date_range( $post->ID ),
				'url'   => get_permalink( $post ),
			);
		}
	}
	set_transient( $cache_key, $found, HOUR_IN_SECONDS );
	return array_merge( $items, $found );
}
add_filter( 'cemeti_whats_on_items', 'cemeti_whats_on_items' );

/**
 * Invalidate cached What's on entries when Cemeti content changes.
 *
 * @param int $post_id Post ID.
 */
function cemeti_bump_content_version( $post_id ) {
	if ( array_key_exists( get_post_type( $post_id ), cemeti_config( 'post-types' ) ) ) {
		update_option( 'cemeti_content_version', (int) get_option( 'cemeti_content_version', 0 ) + 1, false );
	}
}
add_action( 'save_post', 'cemeti_bump_content_version' );
add_action( 'deleted_post', 'cemeti_bump_content_version' );
add_action( 'trashed_post', 'cemeti_bump_content_version' );
