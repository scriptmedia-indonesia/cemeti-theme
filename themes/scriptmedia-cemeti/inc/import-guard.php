<?php
/**
 * Import guard: importing a newer export of the old site (Tools → Import → WordPress) must never create a second copy of
 * a post that is already on the site, and must never leave out a post only because it looks like another one.
 *
 * WordPress' importer decides "already there" by title + exact date + the same post type. That fails twice here:
 *  - a post that the converter already turned into an Exhibition, Journal… has another post type, so the importer
 *    would import it again (a duplicate);
 *  - two posts with the same title and the same minute (the Indonesian and English twins) look like one post to the
 *    importer, which skips the second (a lost post).
 * The guard works from what the converter remembers (the old slug, "_cemeti_old_slug") and from the slug of posts
 * that were never converted. It runs on the importer's own filter "wp_import_posts". It never changes or deletes an
 * existing post.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the post with this old slug already on the site (converted or not, in the trash too)?
 *
 * @param string $slug Slug the post had on the old site.
 * @return bool
 */
function cemeti_import_post_exists( $slug ) {
	$slug = (string) $slug;
	if ( '' === $slug ) {
		return false;
	}
	$base = array(
		'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => true,
		'lang'             => '',
	);
	$converted = get_posts( array_merge( $base, array( 'post_type' => 'any', 'meta_key' => '_cemeti_old_slug', 'meta_value' => $slug ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( $converted ) {
		return true;
	}
	$plain = get_posts( array_merge( $base, array( 'post_type' => 'post', 'name' => $slug ) ) );
	return (bool) $plain;
}

/**
 * Move a date forward by whole seconds.
 *
 * @param string $date  "Y-m-d H:i:s".
 * @param int    $secs  Seconds.
 * @return string
 */
function cemeti_import_shift_date( $date, $secs = 1 ) {
	$time = strtotime( (string) $date . ' UTC' );
	return $time ? gmdate( 'Y-m-d H:i:s', $time + (int) $secs ) : (string) $date;
}

/**
 * The importer's list of posts, without the posts that are already on the site and with twin posts told apart.
 *
 * @param array $posts Posts the importer read from the file (pictures and menus included).
 * @return array
 */
function cemeti_import_guard_posts( $posts ) {
	if ( ! is_array( $posts ) ) {
		return $posts;
	}
	$kept    = array();
	$skipped = array();
	$shifted = 0;
	$seen    = array();
	foreach ( $posts as $post ) {
		if ( ! is_array( $post ) || ! isset( $post['post_type'] ) || 'post' !== $post['post_type'] ) {
			$kept[] = $post; // Pictures, menus: the importer's own check is right for them.
			continue;
		}
		$slug = isset( $post['post_name'] ) ? (string) $post['post_name'] : '';
		if ( '' !== $slug && cemeti_import_post_exists( $slug ) ) {
			$skipped[] = isset( $post['post_title'] ) ? (string) $post['post_title'] : $slug;
			continue;
		}
		$title = isset( $post['post_title'] ) ? (string) $post['post_title'] : '';
		$date  = isset( $post['post_date'] ) ? (string) $post['post_date'] : '';
		$tries = 0;
		while ( $tries < 300 && ( isset( $seen[ $title . '|' . $date ] ) || ( function_exists( 'post_exists' ) && post_exists( $title, '', $date ) ) ) ) {
			$date = cemeti_import_shift_date( $date, 1 );
			if ( ! empty( $post['post_date_gmt'] ) && '0000-00-00 00:00:00' !== $post['post_date_gmt'] ) {
				$post['post_date_gmt'] = cemeti_import_shift_date( $post['post_date_gmt'], 1 );
			}
			++$tries;
		}
		if ( $tries ) {
			$post['post_date'] = $date;
			++$shifted;
		}
		$seen[ $title . '|' . $date ] = true;
		$kept[]                       = $post;
	}
	$report = array( 'time' => time(), 'skipped' => count( $skipped ), 'shifted' => $shifted, 'titles' => array_slice( $skipped, 0, 25 ) );
	update_option( 'cemeti_import_guard_report', $report, false );
	if ( $skipped || $shifted ) {
		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Cemeti import guard:', 'scriptmedia-cemeti' ),
			esc_html( sprintf( /* translators: 1: posts left out, 2: twin posts told apart */ __( '%1$d post(s) already on the site were left out (nothing was changed or imported twice); %2$d twin post(s) with the same title and time were kept apart.', 'scriptmedia-cemeti' ), count( $skipped ), $shifted ) )
		);
	}
	return $kept;
}
add_filter( 'wp_import_posts', 'cemeti_import_guard_posts' );
