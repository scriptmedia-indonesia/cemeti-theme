<?php
/**
 * Hardening: less information leaked, fewer attack surfaces, safe response headers.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Themes and plugins are edited in code, not in the dashboard.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Remove version and discovery links from the page head.
 */
function cemeti_clean_head() {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	// Emoji scripts are not needed.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'cemeti_clean_head' );

/**
 * Hide the user list from the REST API for visitors (stops account name harvesting).
 *
 * @param array $endpoints REST endpoints.
 * @return array
 */
function cemeti_rest_hide_users( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'cemeti_rest_hide_users' );

/**
 * Author archives (?author=1) would reveal user names; send them home.
 */
function cemeti_block_author_archives() {
	if ( is_author() ) {
		wp_safe_redirect( cemeti_home_url(), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'cemeti_block_author_archives' );

/**
 * One generic message for every failed login.
 *
 * @return string
 */
function cemeti_login_error() {
	return __( 'The login details are not correct.', 'scriptmedia-cemeti' );
}
add_filter( 'login_errors', 'cemeti_login_error' );

// The site has no comments or pings.
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );

/**
 * Response headers sent with every page.
 *
 * @return array<string, string>
 */
function cemeti_security_headers() {
	return apply_filters(
		'cemeti_security_headers',
		array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=()',
		)
	);
}

/**
 * Send the security headers.
 */
function cemeti_send_security_headers() {
	if ( headers_sent() ) {
		return;
	}
	foreach ( cemeti_security_headers() as $name => $value ) {
		header( $name . ': ' . $value );
	}
}
add_action( 'send_headers', 'cemeti_send_security_headers' );

/**
 * Only administrators see the Applications menu.
 */
function cemeti_restrict_applications() {
	if ( ! current_user_can( 'manage_options' ) ) {
		remove_menu_page( 'edit.php?post_type=application' );
	}
}
add_action( 'admin_menu', 'cemeti_restrict_applications', 99 );

/**
 * Applications (personal data) can be edited or deleted only by administrators.
 *
 * @param string[] $caps    Required capabilities.
 * @param string   $cap     Capability being checked.
 * @param int      $user_id User ID.
 * @param array    $args    Extra arguments (post ID first).
 * @return string[]
 */
function cemeti_application_caps( $caps, $cap, $user_id, $args ) {
	if ( in_array( $cap, array( 'edit_post', 'read_post', 'delete_post' ), true ) && ! empty( $args[0] ) && 'application' === get_post_type( $args[0] ) ) {
		return array( 'manage_options' );
	}
	return $caps;
}
add_filter( 'map_meta_cap', 'cemeti_application_caps', 10, 4 );
