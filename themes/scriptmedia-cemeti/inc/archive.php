<?php
/**
 * Open Archive: the list of the publications that can be read or downloaded freely — the booklets of the entries
 * (Exhibition, Residency, Public Program, Open Studio) and the News posts of the old Journal, shown A–Z as in the design.
 * Nothing is typed in again: a booklet is the "Booklet (PDF)" / link of an entry, News is a Journal category.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IDs of the entries of some types in the language being viewed. With a second language, the default language's entries
 * that have no version there yet are included (and those that have one are not repeated), like the lists.
 *
 * @param string[] $types Post types.
 * @return int[]
 */
function cemeti_archive_entry_ids( array $types ) {
	$ids = array();
	foreach ( $types as $type ) {
		$args = array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true );
		if ( function_exists( 'pll_get_post_language' ) && cemeti_current_lang() !== cemeti_default_lang() ) {
			$args['lang']         = '';
			$args['post__not_in'] = cemeti_listing_shadowed_ids( $type, cemeti_current_lang() );
		}
		if ( 'journal' === $type ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'journal_category', 'field' => 'term_id', 'terms' => cemeti_news_term_ids() ?: array( 0 ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$found = get_posts( $args );
		$ids   = array_merge( $ids, array_map( 'intval', $found ) );
	}
	if ( $ids && function_exists( 'update_meta_cache' ) ) {
		update_meta_cache( 'post', $ids );
	}
	return $ids;
}

/**
 * The items of the Open Archive, newest first.
 *
 * @return array<int, array{type:string,title:string,url:string,action:string,date:string,year:string,from:string}>
 */
function cemeti_archive_items() {
	$items = array();
	$types = array( 'exhibition', 'residency', 'public_program', 'open_studio' );
	foreach ( cemeti_archive_entry_ids( $types ) as $id ) {
		$url = cemeti_booklet_url( $id );
		if ( '' === $url ) {
			continue;
		}
		$date    = (string) get_post_meta( $id, 'cemeti_start_date', true );
		$date    = preg_match( '/^\d{8}$/', $date ) ? $date : gmdate( 'Ymd', strtotime( (string) get_post_field( 'post_date', $id ) ) );
		$object  = get_post_type_object( get_post_type( $id ) );
		$items[] = array(
			'type'   => 'booklet',
			'title'  => cemeti_title( $id ),
			'url'    => $url,
			'action' => 'download',
			'date'   => $date,
			'year'   => substr( $date, 0, 4 ),
			'from'   => $object ? $object->labels->singular_name : '',
		);
	}
	foreach ( cemeti_archive_entry_ids( array( 'journal' ) ) as $id ) {
		$date    = gmdate( 'Ymd', strtotime( (string) get_post_field( 'post_date', $id ) ) );
		$file    = cemeti_booklet_url( $id ); // The old site's "Booklet …" / "Buklet …" posts: the PDF itself.
		$items[] = array(
			'type'   => '' !== $file ? 'booklet' : 'news',
			'title'  => cemeti_title( $id ),
			'url'    => '' !== $file ? $file : (string) get_permalink( $id ),
			'action' => '' !== $file ? 'download' : 'read',
			'date'   => $date,
			'year'   => substr( $date, 0, 4 ),
			'from'   => '',
		);
	}
	usort(
		$items,
		static function ( $a, $b ) {
			return strcmp( $b['date'], $a['date'] ) ?: strcmp( $a['title'], $b['title'] );
		}
	);
	return $items;
}
