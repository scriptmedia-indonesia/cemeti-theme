<?php
/**
 * Cemeti taxonomies. Registered by inc/content-model.php.
 * 'terms' are created once on first load (editors can add more).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'exhibition_type'  => array(
		'singular'     => 'Exhibition Type',
		'plural'       => 'Exhibition Types',
		'slug'         => 'exhibition-type',
		'post_types'   => array( 'exhibition' ),
		'hierarchical' => false,
		'terms'        => array( 'Solo', 'Group' ),
	),
	'residency_type'   => array(
		'singular'     => 'Residency Type',
		'plural'       => 'Residency Types',
		'slug'         => 'residency-type',
		'post_types'   => array( 'residency' ),
		'hierarchical' => false,
		'terms'        => array( 'Reguler', 'Research' ),
	),
	'journal_category' => array(
		'singular'     => 'Journal Category',
		'plural'       => 'Journal Categories',
		'slug'         => 'journal-category',
		'post_types'   => array( 'journal' ),
		'hierarchical' => false,
		'terms'        => array( 'News', 'Essay', 'Curatorial Essay' ),
	),
);
