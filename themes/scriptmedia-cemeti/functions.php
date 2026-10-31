<?php
/**
 * Scriptmedia Cemeti child theme bootstrap.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CEMETI_VERSION', '0.5.0' );
define( 'CEMETI_DIR', get_stylesheet_directory() );
define( 'CEMETI_URI', get_stylesheet_directory_uri() );

$cemeti_includes = array(
	'i18n',
	'options',
	'dates',
	'setup',
	'enqueue',
	'navigation',
	'settings',
	'core-bridge',
	'content-model',
	'fields',
	'relations',
	'status',
	'whats-on',
	'templates',
	'queries',
	'redirects',
	'migration-rules',
	'migration',
	'applications',
	'search',
	'seo',
	'security',
);

foreach ( $cemeti_includes as $cemeti_include ) {
	require_once CEMETI_DIR . '/inc/' . $cemeti_include . '.php';
}
unset( $cemeti_includes, $cemeti_include );
