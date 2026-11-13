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
		} elseif ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
			$args['lang'] = (string) pll_current_language(); // Say it, instead of relying on the language filter being on.
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

	// 1. What the team put there on purpose: Archive items (they have their own page with the download button) and any
	// other content with "Show in the Open Archive" ticked (listed with a link to its page).
	$flagged = array();
	foreach ( cemeti_archive_entry_ids( array( 'archive_item' ) ) as $id ) {
		$flagged[ $id ] = 'item';
	}
	foreach ( cemeti_archive_flagged_ids() as $id ) {
		$flagged[ $id ] = 'flag';
	}
	foreach ( $flagged as $id => $how ) {
		$date    = gmdate( 'Ymd', strtotime( (string) get_post_field( 'post_date', $id ) ) );
		$items[] = array(
			'type'   => $how,
			'title'  => cemeti_title( $id ),
			'url'    => (string) get_permalink( $id ),
			'action' => 'read',
			'date'   => $date,
			'year'   => substr( $date, 0, 4 ),
			'from'   => '',
		);
	}

	// 2. Made by itself: the booklets of the entries, and the News posts.
	foreach ( cemeti_archive_entry_ids( $types ) as $id ) {
		if ( isset( $flagged[ $id ] ) ) {
			continue; // Already listed, with its page.
		}
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
		if ( isset( $flagged[ $id ] ) ) {
			continue;
		}
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

/**
 * Other content (entries, Journal, Open Call) that the team ticked "Show in the Open Archive" for, in the language
 * being viewed (with the second language's fallback to the default language's entries without a version there).
 *
 * @return int[]
 */
function cemeti_archive_flagged_ids() {
	$ids = array();
	foreach ( cemeti_archive_flag_types() as $type ) {
		$args = array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => 'cemeti_in_archive', 'meta_value' => '1' ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( function_exists( 'pll_get_post_language' ) && cemeti_current_lang() !== cemeti_default_lang() ) {
			$args['lang']         = '';
			$args['post__not_in'] = cemeti_listing_shadowed_ids( $type, cemeti_current_lang() );
		} elseif ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
			$args['lang'] = (string) pll_current_language();
		}
		$ids = array_merge( $ids, array_map( 'intval', get_posts( $args ) ) );
	}
	return $ids;
}

/**
 * Types that have the "Show in the Open Archive" box.
 *
 * @return string[]
 */
function cemeti_archive_flag_types() {
	return array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal' );
}

/**
 * The file of an Archive item: the PDF chosen in the Media Library, else the link.
 *
 * @param int $post_id Archive item.
 * @return string Address, '' when none.
 */
function cemeti_archive_file_url( $post_id ) {
	$file = (int) cemeti_get_field( 'file', $post_id );
	$url  = $file ? (string) wp_get_attachment_url( $file ) : '';
	return '' !== $url ? $url : (string) cemeti_get_field( 'file_url', $post_id );
}

/**
 * Side box on the other content: one tick to list it in the Open Archive.
 */
function cemeti_archive_flag_add_box() {
	foreach ( cemeti_archive_flag_types() as $type ) {
		add_meta_box( 'cemeti_archive_flag', __( 'Open Archive', 'scriptmedia-cemeti' ), 'cemeti_archive_flag_render', $type, 'side', 'high' );
	}
}
add_action( 'add_meta_boxes', 'cemeti_archive_flag_add_box' );

/**
 * Render the side box.
 *
 * @param WP_Post $post Post.
 */
function cemeti_archive_flag_render( $post ) {
	wp_nonce_field( 'cemeti_archive_flag', 'cemeti_archive_flag_nonce' );
	echo '<p><label><input type="checkbox" name="cemeti_in_archive" value="1"' . checked( '1', (string) get_post_meta( $post->ID, 'cemeti_in_archive', true ), false ) . '> <strong>' . esc_html__( 'Show in the Open Archive', 'scriptmedia-cemeti' ) . '</strong></label></p>';
	echo '<p class="description">' . esc_html__( 'Lists this page, by its title, in the Open Archive (A–Z). Do the same on the other language\'s copy.', 'scriptmedia-cemeti' ) . '</p>';
}

/**
 * Save the side box.
 *
 * @param int $post_id Post ID.
 */
function cemeti_archive_flag_save( $post_id ) {
	if ( ! isset( $_POST['cemeti_archive_flag_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cemeti_archive_flag_nonce'] ) ), 'cemeti_archive_flag' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! empty( $_POST['cemeti_in_archive'] ) ) {
		update_post_meta( $post_id, 'cemeti_in_archive', '1' );
	} else {
		delete_post_meta( $post_id, 'cemeti_in_archive' );
	}
	if ( function_exists( 'cemeti_listing_cache_clear' ) ) {
		cemeti_listing_cache_clear();
	}
}
add_action( 'save_post', 'cemeti_archive_flag_save' );
