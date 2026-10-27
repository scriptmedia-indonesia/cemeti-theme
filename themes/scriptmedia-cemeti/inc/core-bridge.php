<?php
/**
 * Bridge to Scriptmedia Core.
 *
 * Optional. When Scriptmedia Core is active it can pick up the client
 * configuration arrays (CPT, taxonomies, options pages) through these filters.
 * The theme is standalone: with Core absent the filters are simply never called
 * and nothing breaks.
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
