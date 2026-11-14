<?php
/**
 * Speed: picture sizes and caches.
 *
 *  - the right picture size for what is shown (and a smaller stand-in when a size was never made for an old picture);
 *  - "Make missing picture sizes" (Settings → Cemeti): makes the sizes of pictures that came with the import;
 *  - cached lists and fragments for visitors who are not logged in, flushed whenever content changes.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------------------------------------ picture sizes */

/**
 * Width a size is shown at (what the theme asks for), by size name.
 *
 * @return array<string,int>
 */
function cemeti_image_widths() {
	return array(
		'cemeti-card'   => 616,
		'cemeti-square' => 600,
		'cemeti-poster' => 1200,
		'cemeti-hero'   => 1600,
	);
}

/**
 * When a picture has no file for one of the theme's sizes (pictures that were imported before the sizes existed),
 * WordPress sends the original, often several megabytes. Send the smallest existing size that is big enough instead.
 *
 * @param array|false  $image Array( url, width, height, is_intermediate ) or false.
 * @param int          $id    Attachment.
 * @param string|int[] $size  Requested size.
 * @return array|false
 */
function cemeti_image_src_fallback( $image, $id, $size ) {
	$widths = cemeti_image_widths();
	if ( ! is_array( $image ) || ! is_string( $size ) || ! isset( $widths[ $size ] ) || ! empty( $image[3] ) ) {
		return $image;
	}
	$meta = wp_get_attachment_metadata( (int) $id );
	if ( ! is_array( $meta ) || empty( $meta['sizes'] ) || empty( $meta['file'] ) || (int) ( $meta['width'] ?? 0 ) <= 1.5 * $widths[ $size ] ) {
		return $image; // Small anyway, or nothing else to use.
	}
	$want = $widths[ $size ];
	$best = null;
	foreach ( $meta['sizes'] as $info ) {
		if ( empty( $info['file'] ) || empty( $info['width'] ) || (int) $info['width'] < 300 ) {
			continue;
		}
		$fits = (int) $info['width'] >= $want;
		if ( null === $best ) {
			$best = $info;
			continue;
		}
		$best_fits = (int) $best['width'] >= $want;
		if ( ( $fits && ! $best_fits ) || ( $fits && $best_fits && (int) $info['width'] < (int) $best['width'] ) || ( ! $fits && ! $best_fits && (int) $info['width'] > (int) $best['width'] ) ) {
			$best = $info;
		}
	}
	if ( ! $best ) {
		return $image;
	}
	$url = (string) wp_get_attachment_url( (int) $id );
	if ( '' === $url ) {
		return $image;
	}
	return array( trailingslashit( dirname( $url ) ) . $best['file'], (int) $best['width'], (int) ( $best['height'] ?? 0 ), true );
}
add_filter( 'wp_get_attachment_image_src', 'cemeti_image_src_fallback', 10, 3 );

/**
 * "sizes" of the pictures that are shown smaller than the page is wide, so the browser takes a smaller file.
 *
 * @param array        $attr  Attributes.
 * @param WP_Post      $post  Attachment.
 * @param string|int[] $size  Size.
 * @return array
 */
function cemeti_image_attributes( $attr, $post = null, $size = '' ) {
	$map = array(
		'cemeti-poster' => '(min-width: 48rem) 50vw, 100vw',
		'cemeti-hero'   => '(min-width: 56rem) 53rem, 100vw',
	);
	if ( is_string( $size ) && isset( $map[ $size ] ) && is_array( $attr ) ) {
		$attr['sizes'] = $map[ $size ];
	}
	if ( is_array( $attr ) && ! isset( $attr['decoding'] ) ) {
		$attr['decoding'] = 'async';
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'cemeti_image_attributes', 10, 3 );

/**
 * Loading attributes of the Nth picture of a list: the first ones are what the visitor sees first, so they are not
 * lazy (and the very first is fetched with priority); the rest wait until they come near the screen.
 *
 * @param int  $index   Position in the list, 0 = first.
 * @param int  $eager   How many are not lazy.
 * @param bool $on_page Whether the list is the first page (later pages are below the fold).
 * @return array<string,string>
 */
function cemeti_image_loading( $index, $eager = 4, $on_page = true ) {
	if ( $on_page && (int) $index < $eager ) {
		return 0 === (int) $index ? array( 'loading' => 'eager', 'fetchpriority' => 'high' ) : array( 'loading' => 'eager' );
	}
	return array( 'loading' => 'lazy' );
}

/**
 * Pictures that need their sizes made: every image of the Media Library, in one list that is worked off in steps.
 *
 * @return int[]
 */
function cemeti_sizes_all_images() {
	$query = new WP_Query(
		array(
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'post_mime_type'   => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		)
	);
	return array_map( 'intval', $query->posts );
}

/**
 * Does this picture lack one of the theme's sizes although it is big enough for it?
 *
 * @param int $id Attachment.
 * @return bool
 */
function cemeti_sizes_missing( $id ) {
	$meta = wp_get_attachment_metadata( (int) $id );
	if ( ! is_array( $meta ) || empty( $meta['file'] ) || empty( $meta['width'] ) ) {
		return false;
	}
	foreach ( array( 'cemeti-card' => 616, 'cemeti-poster' => 1200 ) as $name => $width ) {
		if ( (int) $meta['width'] > $width && ! isset( $meta['sizes'][ $name ] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * One time-boxed step of "Make missing picture sizes". Progress is kept, so a stopped run goes on where it was.
 *
 * @param float $budget Seconds.
 * @param bool  $fresh  Start again from the whole Media Library.
 * @return array{remaining:int,sized:int,failed:int}
 */
function cemeti_sizes_step( $budget = 8.0, $fresh = false ) {
	$state = get_option( 'cemeti_sizes_state', array() );
	if ( $fresh || ! is_array( $state ) || ! isset( $state['ids'] ) ) {
		$state = array( 'ids' => cemeti_sizes_all_images(), 'sized' => 0, 'failed' => 0 );
	}
	$start = microtime( true );
	if ( ! function_exists( 'wp_update_image_subsizes' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	while ( $state['ids'] && microtime( true ) - $start < $budget ) {
		$id = (int) array_shift( $state['ids'] );
		if ( ! cemeti_sizes_missing( $id ) ) {
			continue;
		}
		$made = function_exists( 'wp_update_image_subsizes' ) ? wp_update_image_subsizes( $id ) : false;
		if ( is_wp_error( $made ) || ! $made ) {
			++$state['failed'];
		} else {
			++$state['sized'];
		}
	}
	update_option( 'cemeti_sizes_state', $state, false );
	if ( ! $state['ids'] && $state['sized'] ) {
		cemeti_cache_flush(); // The pictures' files changed: pages built before show the old ones.
	}
	return array( 'remaining' => count( $state['ids'] ), 'sized' => (int) $state['sized'], 'failed' => (int) $state['failed'] );
}

/**
 * The same step for the page's own runner (admin-ajax).
 */
function cemeti_sizes_ajax() {
	check_ajax_referer( 'cemeti_sizes', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
	}
	$result = cemeti_sizes_step( 8.0, ! empty( $_POST['fresh'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	wp_send_json_success(
		array(
			'phase'     => 'sizes',
			'remaining' => $result['remaining'],
			'stopped'   => false,
			'report'    => array( 'sized' => $result['sized'], 'failed' => $result['failed'] ),
		)
	);
}
add_action( 'wp_ajax_cemeti_sizes_step', 'cemeti_sizes_ajax' );

/* ----------------------------------------------------------------------------------------------------- caches */

/**
 * Forget every cached list and fragment (they are built again on the next visit).
 */
function cemeti_cache_flush() {
	update_option( 'cemeti_content_version', (int) get_option( 'cemeti_content_version', 0 ) + 1, false );
	update_option( 'cemeti_list_version', (int) get_option( 'cemeti_list_version', 0 ) + 1, false );
}

/**
 * A featured image set or changed without saving the entry (the bulk tools do this) also changes the pages.
 *
 * @param int|int[] $meta_id Meta ID (unused).
 * @param int       $post_id Post.
 * @param string    $key     Meta key.
 */
function cemeti_cache_on_thumbnail_meta( $meta_id, $post_id, $key ) {
	if ( '_thumbnail_id' === $key ) {
		update_option( 'cemeti_content_version', (int) get_option( 'cemeti_content_version', 0 ) + 1, false );
	}
}
add_action( 'added_post_meta', 'cemeti_cache_on_thumbnail_meta', 10, 3 );
add_action( 'updated_post_meta', 'cemeti_cache_on_thumbnail_meta', 10, 3 );
add_action( 'deleted_post_meta', 'cemeti_cache_on_thumbnail_meta', 10, 3 );
/**
 * Saving an entry forgets the picture that was remembered for it.
 *
 * @param int $post_id Post.
 */
function cemeti_forget_entry_picture( $post_id ) {
	delete_post_meta( $post_id, '_cemeti_pic' );
}
add_action( 'save_post', 'cemeti_forget_entry_picture' );
add_action( 'update_option_cemeti_settings_en', 'cemeti_cache_flush' );
add_action( 'update_option_cemeti_settings_id', 'cemeti_cache_flush' );

/**
 * Key part that changes when content, language or day changes.
 *
 * @param string $name Cache name.
 * @return string
 */
function cemeti_cache_key( $name ) {
	return 'cemeti_c_' . md5( $name . '|' . cemeti_current_lang() . '|' . cemeti_today() . '|' . (int) get_option( 'cemeti_content_version', 0 ) . '|' . (int) get_option( 'cemeti_list_version', 0 ) . '|' . CEMETI_VERSION );
}

/**
 * Print a piece of a page from the cache, or build it (and remember it for six hours). Only for visitors who are not
 * logged in: editors always see the live page. The piece must not hold anything that belongs to one visitor.
 *
 * @param string   $name     Cache name.
 * @param callable $build    Prints the piece.
 * @param int      $lifetime Seconds.
 */
function cemeti_cached_fragment( $name, $build, $lifetime = 21600 ) {
	if ( is_user_logged_in() || ( function_exists( 'is_preview' ) && is_preview() ) || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ) {
		call_user_func( $build );
		return;
	}
	$key  = cemeti_cache_key( $name );
	$html = get_transient( $key );
	if ( is_string( $html ) ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- built by the theme's own templates.
		return;
	}
	ob_start();
	call_user_func( $build );
	$html = (string) ob_get_clean();
	set_transient( $key, $html, $lifetime );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Cache of a list of data (not HTML) that the whole site shares.
 *
 * @param string   $name     Cache name.
 * @param callable $build    Returns an array.
 * @param int      $lifetime Seconds.
 * @return array
 */
function cemeti_cached_data( $name, $build, $lifetime = 21600 ) {
	if ( is_user_logged_in() ) {
		return call_user_func( $build );
	}
	$key  = cemeti_cache_key( $name );
	$data = get_transient( $key );
	if ( is_array( $data ) ) {
		return $data;
	}
	$data = (array) call_user_func( $build );
	set_transient( $key, $data, $lifetime );
	return $data;
}

/**
 * Settings → Cemeti → "Clear cached pages".
 */
function cemeti_handle_cache_clear() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'cemeti_cache_clear' );
	cemeti_cache_flush();
	if ( function_exists( 'delete_post_meta_by_key' ) ) {
		delete_post_meta_by_key( '_cemeti_pic' ); // The remembered stand-in pictures.
	}
	wp_safe_redirect( add_query_arg( 'cemeti_cache_cleared', '1', admin_url( 'options-general.php?page=cemeti-settings' ) ) );
	exit;
}
add_action( 'admin_post_cemeti_cache_clear', 'cemeti_handle_cache_clear' );
