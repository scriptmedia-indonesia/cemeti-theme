<?php
/**
 * Cemeti custom post types. Registered by inc/content-model.php.
 * Keys are post type slugs (max 20 chars); 'slug' is the URL base used by the menu.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'exhibition'     => array(
		'singular'   => 'Exhibition',
		'plural'     => 'Exhibitions',
		'slug'       => 'exhibition',
		'icon'       => 'dashicons-format-gallery',
		'taxonomies' => array( 'exhibition_type' ),
	),
	'residency'      => array(
		'singular'   => 'Residency',
		'plural'     => 'Residencies',
		'slug'       => 'residency',
		'icon'       => 'dashicons-admin-home',
		'taxonomies' => array( 'residency_type' ),
	),
	'public_program' => array(
		'singular' => 'Public Program',
		'plural'   => 'Public Programs',
		'slug'     => 'public-program',
		'icon'     => 'dashicons-megaphone',
	),
	'open_studio'    => array(
		'singular' => 'Open Studio',
		'plural'   => 'Open Studios',
		'slug'     => 'open-studio',
		'icon'     => 'dashicons-art',
	),
	'open_call'      => array(
		'singular' => 'Open Call',
		'plural'   => 'Open Calls',
		'slug'     => 'open-call',
		'icon'     => 'dashicons-email-alt',
	),
	'journal'        => array(
		'singular'   => 'Journal',
		'plural'     => 'Journal',
		'slug'       => 'journal',
		'icon'       => 'dashicons-edit-page',
		'taxonomies' => array( 'journal_category' ),
	),
	'person'         => array(
		'singular'   => 'Person',
		'plural'     => 'People',
		'slug'       => 'person',
		'icon'       => 'dashicons-groups',
		'taxonomies' => array( 'person_group' ),
		'public'     => false,
		'supports'   => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
	),
	'application'    => array(
		'singular' => 'Application',
		'plural'   => 'Applications',
		'slug'     => 'application',
		'icon'     => 'dashicons-clipboard',
		'public'   => false,
		'supports' => array( 'title' ),
	),
);
