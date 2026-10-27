<?php
/**
 * Cemeti fields per post type, rendered as a "Details" meta box (inc/fields.php).
 * Poster / cover image is the featured image; body text is the editor.
 *
 * Field types: text, textarea, date, url, image, images, relation, repeater.
 * Dates are stored as Ymd. 'relation' is two-way and limited to 4 entries.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dates = array(
	array( 'key' => 'start_date', 'label' => 'Start date', 'type' => 'date' ),
	array( 'key' => 'end_date', 'label' => 'End date', 'type' => 'date', 'help' => 'Leave empty for a one-day event.' ),
);

$documentation = array(
	array( 'key' => 'documentation', 'label' => 'Documentation (photos)', 'type' => 'images' ),
	array( 'key' => 'documentation_caption', 'label' => 'Documentation caption', 'type' => 'text' ),
);

$biographies = array(
	'key'   => 'biographies',
	'label' => 'Biographies',
	'type'  => 'repeater',
	'sub'   => array(
		array( 'key' => 'name', 'label' => 'Name', 'type' => 'text' ),
		array( 'key' => 'photo', 'label' => 'Photo', 'type' => 'image' ),
		array( 'key' => 'bio', 'label' => 'Biography', 'type' => 'textarea' ),
	),
);

$related = array(
	'key'        => 'related',
	'label'      => 'Related publications',
	'type'       => 'relation',
	'max'        => 4,
	'post_types' => array( 'exhibition', 'public_program', 'open_studio', 'residency', 'journal' ),
	'help'       => 'Up to 4. Links are two-way: the other entry lists this one too.',
);

return array(
	'exhibition'     => array_merge(
		$dates,
		array(
			array( 'key' => 'artists', 'label' => 'Artists (one per line)', 'type' => 'textarea' ),
			array( 'key' => 'opening', 'label' => 'Opening', 'type' => 'text', 'help' => 'e.g. 21 November 2025 | 13.00 WIB' ),
			array( 'key' => 'public_program_info', 'label' => 'Public program info', 'type' => 'textarea' ),
			array( 'key' => 'venue_info', 'label' => 'Venue and hours', 'type' => 'textarea' ),
			array( 'key' => 'gallery', 'label' => 'Gallery (photos)', 'type' => 'images' ),
			array( 'key' => 'gallery_caption', 'label' => 'Gallery caption', 'type' => 'text' ),
		),
		$documentation,
		array( $biographies, $related )
	),
	'residency'      => array_merge(
		$dates,
		array(
			array( 'key' => 'gallery', 'label' => 'Gallery (photos)', 'type' => 'images' ),
		),
		array( $related )
	),
	'public_program' => array_merge(
		$dates,
		array(
			array( 'key' => 'time_text', 'label' => 'Time', 'type' => 'text', 'help' => 'e.g. 15.00 – 17.00 WIB' ),
			array( 'key' => 'speakers', 'label' => 'Speakers', 'type' => 'text' ),
		),
		$documentation,
		array( $biographies, $related )
	),
	'open_studio'    => array_merge(
		$dates,
		array(
			array( 'key' => 'artists', 'label' => 'Artists (one per line)', 'type' => 'textarea' ),
		),
		$documentation,
		array( $biographies, $related )
	),
	'open_call'      => array(
		array( 'key' => 'deadline', 'label' => 'Deadline', 'type' => 'date' ),
		array( 'key' => 'application_url', 'label' => 'Application Submission URL', 'type' => 'url', 'help' => 'Target of the blue "Application Submission" button.' ),
	),
	'journal'        => array(
		array( 'key' => 'author', 'label' => 'Author', 'type' => 'text' ),
		$related,
	),
);
