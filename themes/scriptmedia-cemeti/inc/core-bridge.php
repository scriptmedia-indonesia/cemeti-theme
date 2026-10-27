<?php
/**
 * Bridge to Scriptmedia Core.
 *
 * Content model (CPT, taxonomies, field groups, options pages) belongs to the
 * Scriptmedia Core plugin, not to this theme. The child theme only supplies
 * client configuration arrays through filters, and shows an admin notice when
 * required dependencies are missing instead of failing.
 *
 * NOTE: the filter names below (scriptmedia_post_types, …) follow the pattern
 * described in the architecture brief but could not be checked against the real
 * Scriptmedia Core source, which was not available. Verify them before M2.
 * See docs/ARCHITECTURE.md ("Open assumptions").
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is Scriptmedia Core active?
 *
 * @return bool
 */
function cemeti_core_active() {
	return defined( 'SCRIPTMEDIA_CORE_VERSION' ) || class_exists( '\\Scriptmedia\\Core\\Plugin' );
}

/**
 * Load a config array file from the child theme.
 *
 * @param string $name File name without extension.
 * @return array
 */
function cemeti_config( $name ) {
	$file = CEMETI_DIR . '/config/' . sanitize_file_name( $name ) . '.php';
	if ( ! is_readable( $file ) ) {
		return array();
	}
	$config = include $file;
	return is_array( $config ) ? $config : array();
}

/**
 * Hand client configuration to Scriptmedia Core.
 * Each filter appends or overrides entries; it never edits core directly.
 */
function cemeti_register_core_config() {
	$map = array(
		'scriptmedia_post_types'   => 'post-types',
		'scriptmedia_taxonomies'   => 'taxonomies',
		'scriptmedia_options_pages' => 'options-pages',
	);
	foreach ( $map as $hook => $file ) {
		add_filter(
			$hook,
			static function ( $existing ) use ( $file ) {
				return array_merge( is_array( $existing ) ? $existing : array(), cemeti_config( $file ) );
			}
		);
	}
}
add_action( 'after_setup_theme', 'cemeti_register_core_config', 20 );

/**
 * Admin notice for missing dependencies.
 */
function cemeti_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$missing = array();
	if ( ! cemeti_core_active() ) {
		$missing[] = 'Scriptmedia Core';
	}
	if ( ! function_exists( 'get_field' ) ) {
		$missing[] = 'Secure Custom Fields';
	}
	if ( ! $missing ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: comma separated plugin names. */
				__( 'Scriptmedia Cemeti needs these plugins for its content types and settings: %s. Public pages still render, but Cemeti content will be unavailable.', 'scriptmedia-cemeti' ),
				implode( ', ', $missing )
			)
		)
	);
}
add_action( 'admin_notices', 'cemeti_dependency_notice' );
