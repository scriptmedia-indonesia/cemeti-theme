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
		'terms'        => array( 'Regular', 'Research' ),
		// Shown under the page title on the Residency page; editors can change them (Residency Types).
		'descriptions' => array(
			'Regular'  => 'In each residency period we host one Indonesian artist and one or two artists from outside Indonesia.',
			'Research' => 'Selected participants are facilitated by an assistant during the residency.',
		),
		'descriptions_id' => array(
			'Regular'  => 'Pada setiap periode residensi, kami menerima satu seniman Indonesia dan satu atau dua seniman dari luar Indonesia.',
			'Research' => 'Peserta terpilih difasilitasi oleh seorang asisten selama masa residensi.',
		),
		'renamed'      => array( 'Reguler' => 'Regular' ),
	),
	'journal_category' => array(
		'singular'     => 'Journal Category',
		'plural'       => 'Journal Categories',
		'slug'         => 'journal-category',
		'post_types'   => array( 'journal' ),
		'hierarchical' => false,
		'terms'        => array( 'News', 'Essay', 'Curatorial Essay' ),
	),
	'archive_kind'     => array(
		'singular'     => 'Archive kind',
		'plural'       => 'Archive kinds',
		'slug'         => 'archive-kind',
		'post_types'   => array( 'archive_item' ),
		'hierarchical' => false,
		'terms'        => array( 'Writing', 'Booklet', 'Catalog' ),
	),
	'project'          => array(
		'singular'     => 'Project',
		'plural'       => 'Projects',
		'slug'         => 'project',
		'post_types'   => array( 'exhibition', 'residency', 'public_program', 'open_studio', 'journal' ),
		'hierarchical' => false,
	),
	'person_group'     => array(
		'singular'     => 'Group',
		'plural'       => 'Groups',
		'slug'         => 'person-group',
		'post_types'   => array( 'person' ),
		'hierarchical' => false,
		'public'       => false,
		'terms'        => array( 'Advisory Board', 'Team' ),
	),
);
