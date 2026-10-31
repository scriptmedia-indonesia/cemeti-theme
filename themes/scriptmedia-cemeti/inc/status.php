<?php
/**
 * Current / upcoming / previous status, computed from the date fields at read
 * time in the site timezone. Nothing is stored, so there is no cron to drift.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Today as Ymd in the site timezone.
 *
 * @return string
 */
function cemeti_today() {
	return wp_date( 'Ymd' );
}

/**
 * Meta keys that hold a post type's date window: array( start, end ).
 * Open Call has only a deadline. Types without dates return empty strings.
 *
 * @param string $post_type Post type.
 * @return string[]
 */
function cemeti_date_keys( $post_type ) {
	if ( 'open_call' === $post_type ) {
		return array( '', 'deadline' );
	}
	if ( in_array( $post_type, array( 'exhibition', 'residency', 'public_program', 'open_studio' ), true ) ) {
		return array( 'start_date', 'end_date' );
	}
	return array( '', '' );
}

/**
 * Status of one entry.
 *
 * @param int|null $post_id Post ID. Defaults to the current post.
 * @return string "current", "upcoming", "previous", or "" when there are no dates.
 */
function cemeti_get_status( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	list( $start_key, $end_key ) = cemeti_date_keys( get_post_type( $post_id ) );

	$start = $start_key ? (string) cemeti_get_field( $start_key, $post_id ) : '';
	$end   = $end_key ? (string) cemeti_get_field( $end_key, $post_id ) : '';
	$end   = '' !== $end ? $end : $start;
	if ( '' === $end ) {
		return '';
	}

	$today = cemeti_today();
	if ( '' !== $start && $today < $start ) {
		return 'upcoming';
	}
	return $today <= $end ? 'current' : 'previous';
}

/**
 * Meta query selecting entries of a post type with the given status.
 *
 * @param string $status    "current", "upcoming" or "previous".
 * @param string $post_type Post type.
 * @return array Meta query, or an empty array for an unknown status or dateless type.
 */
function cemeti_status_meta_query( $status, $post_type ) {
	list( $start_key, $end_key ) = cemeti_date_keys( $post_type );
	if ( ! $end_key ) {
		return array();
	}
	$today = cemeti_today();
	$end   = array(
		'key'     => 'cemeti_' . $end_key,
		'type'    => 'NUMERIC',
	);
	$start = array(
		'key'  => 'cemeti_' . $start_key,
		'type' => 'NUMERIC',
	);

	switch ( $status ) {
		case 'previous':
			return array( array_merge( $end, array( 'value' => $today, 'compare' => '<' ) ) );
		case 'upcoming':
			return $start_key ? array( array_merge( $start, array( 'value' => $today, 'compare' => '>' ) ) ) : array();
		case 'current':
			$query = array( array_merge( $end, array( 'value' => $today, 'compare' => '>=' ) ) );
			if ( $start_key ) {
				$query[] = array_merge( $start, array( 'value' => $today, 'compare' => '<=' ) );
				$query['relation'] = 'AND';
			}
			return $query;
	}
	return array();
}

/**
 * Entries of a post type with a given status, newest dates first.
 *
 * @param string $post_type Post type.
 * @param string $status    "current", "upcoming" or "previous".
 * @param array  $args      Extra WP_Query args.
 * @return WP_Query
 */
function cemeti_status_query( $post_type, $status, array $args = array() ) {
	list( , $end_key ) = cemeti_date_keys( $post_type );
	return new WP_Query(
		array_merge(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => cemeti_status_meta_query( $status, $post_type ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_key'       => 'cemeti_' . $end_key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'        => 'meta_value_num',
				'order'          => 'previous' === $status ? 'DESC' : 'ASC',
			),
			$args
		)
	);
}

/**
 * Translated label of a status.
 *
 * @param string $status Status slug.
 * @return string
 */
function cemeti_status_label( $status ) {
	return in_array( $status, array( 'current', 'upcoming', 'previous' ), true ) ? cemeti_t( 'status_' . $status ) : '';
}

/**
 * Formatted date range of an entry, e.g. "8 – 29 January 2026".
 *
 * @param int|null $post_id Post ID. Defaults to the current post.
 * @return string
 */
function cemeti_get_date_range( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	list( $start_key, $end_key ) = cemeti_date_keys( get_post_type( $post_id ) );
	$start = $start_key ? cemeti_get_field( $start_key, $post_id ) : cemeti_get_field( $end_key, $post_id );
	$end   = $end_key ? cemeti_get_field( $end_key, $post_id ) : '';
	return cemeti_format_date_range( (string) $start, (string) $end );
}
