<?php
/**
 * Bridge to Scriptmedia Core.
 *
 * Optional and off by default. The theme registers its own post types and
 * taxonomies (inc/content-model.php). To let Scriptmedia Core register them
 * instead, define CEMETI_USE_CORE_REGISTRAR as true in wp-config.php: the theme
 * then stops registering and hands the arrays in config/ to Core via these filters.
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
 * Load a config array file from the theme's config/ folder.
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
	$config = is_array( $config ) ? $config : array();

	/**
	 * Filters a config array (post-types, taxonomies, fields, migration-rules, …), so a
	 * child theme or plugin can change it without editing this theme.
	 *
	 * @param array  $config Config array.
	 * @param string $name   File name without extension.
	 */
	return apply_filters( 'cemeti_config', $config, $name );
}

/**
 * Hand client configuration to Scriptmedia Core.
 * Each filter appends or overrides entries; it never edits core directly.
 */
function cemeti_register_core_config() {
	if ( ! defined( 'CEMETI_USE_CORE_REGISTRAR' ) || ! CEMETI_USE_CORE_REGISTRAR ) {
		return;
	}
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
