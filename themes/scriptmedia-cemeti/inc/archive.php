<?php
/**
 * Open Archive: a database of the publications that can be read or downloaded freely — the booklets of the entries
 * (Exhibition, Residency, Public Program, Open Studio) and the News posts of the old Journal. Nothing is typed in again:
 * a booklet is the "Booklet (PDF)" / link of an entry, News is a Journal category.
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
		$items[] = array(
			'type'   => 'news',
			'title'  => cemeti_title( $id ),
			'url'    => (string) get_permalink( $id ),
			'action' => 'read',
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

/**
 * Label of an item's type.
 *
 * @param string $type 'booklet' or 'news'.
 * @return string
 */
function cemeti_archive_type_label( $type ) {
	return 'news' === $type ? cemeti_t( 'archive_news' ) : cemeti_t( 'booklet' );
}

/**
 * Print the filters and the list. The list always holds every item; what the filters leave out is only hidden, so the
 * filter works without a reload (archive-filter.js) and, without JavaScript, through the ?type= / ?year= links.
 */
function cemeti_render_archive() {
	$items = cemeti_archive_items();
	$type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$year  = isset( $_GET['year'] ) ? preg_replace( '/\D/', '', wp_unslash( $_GET['year'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$type  = in_array( $type, array( 'booklet', 'news' ), true ) ? $type : '';
	$years = array_values( array_unique( wp_list_pluck( $items, 'year' ) ) );
	$base  = (string) get_permalink( get_queried_object_id() );
	$count = array( 'booklet' => 0, 'news' => 0 );
	foreach ( $items as $item ) {
		++$count[ $item['type'] ];
	}
	echo '<div class="cemeti-archive" data-cemeti-archive>';
	echo '<form class="cemeti-archive__filters" method="get" action="' . esc_url( $base ) . '" aria-label="' . esc_attr( cemeti_t( 'archive_filter' ) ) . '">';
	echo '<ul class="cemeti-archive__types">';
	foreach ( array( '' => cemeti_t( 'archive_all' ), 'booklet' => cemeti_t( 'booklet' ), 'news' => cemeti_t( 'archive_news' ) ) as $key => $label ) {
		$n    = '' === $key ? count( $items ) : $count[ $key ];
		$href = add_query_arg( array_filter( array( 'type' => $key, 'year' => $year ) ), $base );
		echo '<li><a class="cemeti-archive__type' . ( $key === $type ? ' is-active' : '' ) . '" data-type="' . esc_attr( $key ) . '" href="' . esc_url( $href ) . '"' . ( $key === $type ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . ' <span class="cemeti-archive__count">' . (int) $n . '</span></a></li>';
	}
	echo '</ul>';
	echo '<label class="cemeti-archive__year"><span>' . esc_html( cemeti_t( 'archive_year' ) ) . '</span> <select name="year"><option value="">' . esc_html( cemeti_t( 'archive_all' ) ) . '</option>';
	foreach ( $years as $one ) {
		echo '<option value="' . esc_attr( $one ) . '"' . selected( (string) $one, (string) $year, false ) . '>' . esc_html( $one ) . '</option>';
	}
	echo '</select></label>';
	if ( '' !== $type ) {
		echo '<input type="hidden" name="type" value="' . esc_attr( $type ) . '">';
	}
	echo '<noscript><button type="submit" class="cemeti-btn">OK</button></noscript>';
	echo '</form>';
	if ( ! $items ) {
		echo '<p class="cemeti-empty">' . esc_html( cemeti_t( 'archive_empty' ) ) . '</p></div>';
		return;
	}
	echo '<ul class="cemeti-archive__list">';
	foreach ( $items as $item ) {
		$hidden = ( '' !== $type && $item['type'] !== $type ) || ( '' !== $year && $item['year'] !== $year );
		echo '<li class="cemeti-archive__item" data-type="' . esc_attr( $item['type'] ) . '" data-year="' . esc_attr( $item['year'] ) . '"' . ( $hidden ? ' hidden' : '' ) . '>';
		echo '<span class="cemeti-archive__year-label">' . esc_html( $item['year'] ) . '</span>';
		echo '<span class="cemeti-archive__main"><span class="cemeti-archive__title">' . esc_html( $item['title'] ) . '</span>';
		echo '<span class="cemeti-archive__meta">' . esc_html( cemeti_archive_type_label( $item['type'] ) . ( '' !== $item['from'] ? ' · ' . $item['from'] : '' ) ) . '</span></span>';
		$label = 'download' === $item['action'] ? cemeti_t( 'archive_download' ) : cemeti_t( 'archive_read' );
		echo '<a class="cemeti-archive__action" href="' . esc_url( $item['url'] ) . '"' . ( 'download' === $item['action'] ? ' rel="noopener" target="_blank"' : '' ) . '>' . esc_html( $label ) . '<span class="screen-reader-text"> — ' . esc_html( $item['title'] ) . '</span></a>';
		echo '</li>';
	}
	echo '</ul>';
	echo '<p class="cemeti-empty" data-cemeti-archive-empty hidden>' . esc_html( cemeti_t( 'archive_empty' ) ) . '</p>';
	echo '</div>';
}
