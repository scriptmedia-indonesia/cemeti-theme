<?php
/**
 * Search: besides title and text, also match artists, speakers and authors stored in
 * fields, and show matches in the title first.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widen a WordPress search clause with an extra "or ID in (…)" condition.
 *
 * WordPress builds " AND ((title LIKE …) OR (content LIKE …))" and, for visitors,
 * appends " AND (posts.post_password = '')". The extra condition goes inside the
 * first group and the password check stays outside it.
 *
 * @param string $search Clause from WordPress.
 * @param string $extra  SQL boolean expression, e.g. "wp_posts.ID IN (1,2)".
 * @return string
 */
function cemeti_widen_search( $search, $extra ) {
	if ( '' === trim( $search ) || '' === $extra ) {
		return $search;
	}
	$tail = '';
	if ( preg_match( "/\s+AND\s+\(\s*[\w.`]+\.post_password\s*=\s*''\s*\)\s*$/", $search, $m, PREG_OFFSET_CAPTURE ) ) {
		$tail   = $m[0][0];
		$search = substr( $search, 0, $m[0][1] );
	}
	$core = preg_replace( '/^\s*AND\s*/', '', $search, 1 );
	return ' AND ( ' . $extra . ' OR ( ' . $core . ' ) )' . $tail;
}

/**
 * Add matches on people fields to the main search.
 *
 * @param string   $search Search clause.
 * @param WP_Query $query  Query.
 * @return string
 */
function cemeti_search_people( $search, $query ) {
	global $wpdb;
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return $search;
	}
	$term = trim( (string) $query->get( 's' ) );
	if ( '' === $term ) {
		return $search;
	}
	$like  = '%' . $wpdb->esc_like( $term ) . '%';
	$extra = $wpdb->prepare(
		"{$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('cemeti_artists','cemeti_speakers','cemeti_author') AND meta_value LIKE %s)", // phpcs:ignore WordPress.DB.PreparedSQL
		$like
	);
	return cemeti_widen_search( $search, $extra );
}
add_filter( 'posts_search', 'cemeti_search_people', 10, 2 );

/**
 * Title matches first, then newest.
 *
 * @param string   $orderby ORDER BY clause.
 * @param WP_Query $query   Query.
 * @return string
 */
function cemeti_search_order( $orderby, $query ) {
	global $wpdb;
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() || '' === trim( (string) $query->get( 's' ) ) ) {
		return $orderby;
	}
	$like = '%' . $wpdb->esc_like( trim( (string) $query->get( 's' ) ) ) . '%';
	return $wpdb->prepare( "({$wpdb->posts}.post_title LIKE %s) DESC, {$wpdb->posts}.post_date DESC", $like ); // phpcs:ignore WordPress.DB.PreparedSQL
}
add_filter( 'posts_orderby', 'cemeti_search_order', 10, 2 );
