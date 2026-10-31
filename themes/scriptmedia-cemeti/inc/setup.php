<?php
/**
 * Theme supports, menus and image sizes.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register theme supports and image sizes.
 */
function cemeti_setup() {
	load_child_theme_textdomain( 'scriptmedia-cemeti', CEMETI_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	add_theme_support( 'responsive-embeds' );

	register_nav_menus(
		array(
			'cemeti_primary' => __( 'Cemeti overlay menu', 'scriptmedia-cemeti' ),
		)
	);

	// Square card, poster and hero sizes from the design brief.
	add_image_size( 'cemeti-card', 600, 600, true );
	add_image_size( 'cemeti-poster', 800, 0, false );
	add_image_size( 'cemeti-hero', 1600, 0, false );
}
add_action( 'after_setup_theme', 'cemeti_setup' );
