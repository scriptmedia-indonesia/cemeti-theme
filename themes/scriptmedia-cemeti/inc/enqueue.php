<?php
/**
 * Asset loading: versioned with filemtime, deferred scripts, preloaded fonts.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version string for a theme asset, based on its modification time.
 *
 * @param string $relative Path relative to the child theme root.
 * @return string
 */
function cemeti_asset_version( $relative ) {
	$path = CEMETI_DIR . '/' . ltrim( $relative, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : CEMETI_VERSION;
}

/**
 * Enqueue front-end styles and scripts.
 */
function cemeti_enqueue_assets() {
	$css = array( 'tokens', 'base', 'layout', 'components', 'pages' );
	$prev = array();
	foreach ( $css as $name ) {
		$handle = 'cemeti-' . $name;
		$rel    = 'assets/css/' . $name . '.css';
		wp_enqueue_style( $handle, CEMETI_URI . '/' . $rel, $prev, cemeti_asset_version( $rel ) );
		$prev = array( $handle );
	}

	$rel = 'assets/js/navigation.js';
	wp_enqueue_script(
		'cemeti-navigation',
		CEMETI_URI . '/' . $rel,
		array(),
		cemeti_asset_version( $rel ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	if ( is_singular() ) {
		$rel = 'assets/js/share.js';
		wp_enqueue_script( 'cemeti-share', CEMETI_URI . '/' . $rel, array(), cemeti_asset_version( $rel ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
}
add_action( 'wp_enqueue_scripts', 'cemeti_enqueue_assets' );

/**
 * Preload the two critical fonts.
 */
function cemeti_preload_fonts() {
	$fonts = array( 'hanken-grotesk-latin-wght-normal.woff2', 'vampiro-one-latin-400-normal.woff2' );
	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( CEMETI_URI . '/assets/fonts/' . $font )
		);
	}
}
add_action( 'wp_head', 'cemeti_preload_fonts', 1 );
