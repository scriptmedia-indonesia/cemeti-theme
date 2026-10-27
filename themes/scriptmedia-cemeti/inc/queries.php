<?php
/**
 * Main-query tweaks for archives and search.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order dated archives by start date (newest first), keeping entries without a date.
 *
 * @param WP_Query $query Query.
 * @param string   $start Start meta key without prefix ('' = use end key only).
 * @param string   $end   End meta key without prefix.
 */
function cemeti_order_by_dates( $query, $start, $end ) {
	$key = 'cemeti_' . ( $start ? $start : $end );
	$query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			'by_date'  => array( 'key' => $key, 'compare' => 'EXISTS', 'type' => 'NUMERIC' ),
			'no_date'  => array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
		)
	);
	$query->set( 'orderby', array( 'by_date' => 'DESC', 'date' => 'DESC' ) );
}

/**
 * Adjust the main query.
 *
 * @param WP_Query $query Query.
 */
function cemeti_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$dated = array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call' );
	if ( $query->is_post_type_archive( $dated ) ) {
		list( $start, $end ) = cemeti_date_keys( $query->get( 'post_type' ) );
		cemeti_order_by_dates( $query, $start, $end );
		$query->set( 'posts_per_page', 12 );
	}

	if ( $query->is_post_type_archive( 'journal' ) || $query->is_tax( 'journal_category' ) ) {
		$query->set( 'posts_per_page', 20 );
	}

	// Menu links: /exhibition/?type=solo|group and /residency/?type=reguler|research.
	if ( ( $query->is_post_type_archive( 'exhibition' ) || $query->is_post_type_archive( 'residency' ) ) && isset( $_GET['type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$taxonomy = $query->is_post_type_archive( 'exhibition' ) ? 'exhibition_type' : 'residency_type';
		$slug     = sanitize_title( wp_unslash( $_GET['type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $slug ) {
			$query->set( 'tax_query', array( array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slug ) ) );
		}
	}

	if ( $query->is_search() ) {
		$query->set( 'post_type', array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'page' ) );
		$query->set( 'posts_per_page', 20 );
	}
}
add_action( 'pre_get_posts', 'cemeti_pre_get_posts' );

/**
 * Title of the entries for the A–Z list (programs only, newest query-free).
 *
 * @return array<int, array{title:string,url:string}>
 */
function cemeti_archive_entries() {
	$cache_key = 'cemeti_az_' . cemeti_current_lang() . '_' . (int) get_option( 'cemeti_content_version', 0 );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$entries = array();
	$posts   = get_posts(
		array(
			'post_type'      => array( 'exhibition', 'residency', 'public_program', 'open_studio' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	foreach ( $posts as $post ) {
		$entries[] = array( 'title' => get_the_title( $post ), 'url' => get_permalink( $post ) );
	}
	set_transient( $cache_key, $entries, DAY_IN_SECONDS );
	return $entries;
}
