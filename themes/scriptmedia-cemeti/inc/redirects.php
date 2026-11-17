<?php
/**
 * 301 redirects for URLs of the old site: converted posts (old path saved by the
 * converter) and old category archives.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Path of the current request relative to the site root, without slashes at the ends.
 *
 * @return string
 */
function cemeti_request_path() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = trim( rawurldecode( $path ), '/' );
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home && 0 === strpos( $path, $home ) ) {
		$path = trim( substr( $path, strlen( $home ) ), '/' );
	}
	return $path;
}

/**
 * Where an old category archive should go.
 *
 * @param string $path  Request path.
 * @param array  $paths Legacy map: path => array( type, term slug ).
 * @return string URL, or '' when the path is not a legacy category.
 */
function cemeti_legacy_category_target( $path, array $paths ) {
	if ( ! isset( $paths[ $path ] ) ) {
		return '';
	}
	$url = get_post_type_archive_link( $paths[ $path ]['type'] );
	if ( ! $url ) {
		return '';
	}
	$term = isset( $paths[ $path ]['term'] ) ? (string) $paths[ $path ]['term'] : '';
	return '' !== $term && '__name' !== $term && in_array( $paths[ $path ]['type'], array( 'exhibition', 'residency' ), true ) ? add_query_arg( 'type', $term, $url ) : $url;
}

/**
 * Redirect old URLs that now 404.
 */
function cemeti_legacy_redirect() {
	if ( ! is_404() ) {
		return;
	}
	$path = cemeti_request_path();
	if ( '' === $path ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'        => 'any',
			'post_status'      => 'publish',
			'meta_key'         => '_cemeti_old_path', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $path, // phpcs:ignore WordPress.DB.SlowDBQuery
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);
	if ( $ids ) {
		wp_safe_redirect( get_permalink( $ids[0] ), 301 );
		exit;
	}

	$target = cemeti_legacy_category_target( $path, (array) get_option( 'cemeti_legacy_category_paths', array() ) );
	if ( '' !== $target ) {
		wp_safe_redirect( $target, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'cemeti_legacy_redirect' );
