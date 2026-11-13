<?php
/**
 * Cemeti fields per post type, rendered as a "Details" meta box (inc/fields.php).
 * Poster / cover image is the featured image; body text is the editor.
 *
 * Field types: text, textarea, checkbox, list (one line per entry, stored newline-separated), date, url, image,
 * images, file (a PDF from the media library), relation, repeater. A 'section' key on a field starts a new
 * heading in the "Details" box.
 * Dates are stored as Ymd. 'relation' is two-way and limited to 4 entries.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dates = array(
	array( 'key' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'section' => 'Dates' ),
	array( 'key' => 'end_date', 'label' => 'End date', 'type' => 'date', 'help' => 'Leave empty for a one-day event.' ),
);

$documentation = array(
	array( 'key' => 'documentation', 'label' => 'Documentation (photos)', 'type' => 'images', 'section' => 'Documentation', 'help' => 'Shown as "Dokumentasi" on the page. Add photos in the order they should appear.' ),
	array( 'key' => 'documentation_caption', 'label' => 'Documentation caption', 'type' => 'text' ),
);

$biographies = array(
	'key'      => 'biographies',
	'label'    => 'Biographies',
	'type'     => 'repeater',
	'section'  => 'Biographies',
	'item'     => 'Artist',
	'title_by' => 'name',
	'from'     => 'artists',
	'help'     => 'One block per artist, in the order shown on the page.',
	'sub'      => array(
		array( 'key' => 'name', 'label' => 'Name', 'type' => 'text' ),
		array( 'key' => 'photo', 'label' => 'Photo', 'type' => 'image' ),
		array( 'key' => 'bio', 'label' => 'Biography', 'type' => 'textarea', 'rows' => 8 ),
	),
);

$related = array(
	'key'        => 'related',
	'label'      => 'Related publications',
	'type'       => 'relation',
	'section'    => 'Related publications',
	'max'        => 4,
	'post_types' => array( 'exhibition', 'public_program', 'open_studio', 'residency', 'journal' ),
	'help'       => 'Up to 4. Links are two-way: the other entry lists this one too.',
);

$fields = array(
	'exhibition'     => array_merge(
		$dates,
		array(
			array( 'key' => 'artists', 'label' => 'Artists', 'type' => 'list', 'section' => 'Artists and information', 'help' => 'One name per row. Shown under the dates. Use "Add from Artists" in Biographies to create their biography blocks.' ),
			array( 'key' => 'opening', 'label' => 'Opening', 'type' => 'text', 'help' => 'e.g. 21 November 2025 | 13.00 WIB' ),
			array( 'key' => 'public_program_info', 'label' => 'Public program info', 'type' => 'textarea' ),
			array( 'key' => 'venue_info', 'label' => 'Venue and hours', 'type' => 'textarea' ),
			array( 'key' => 'gallery', 'label' => 'Gallery (photos)', 'type' => 'images', 'section' => 'Gallery', 'help' => 'Shown as "Galeri" on the page, two photos per row.' ),
			array( 'key' => 'gallery_caption', 'label' => 'Gallery caption', 'type' => 'text' ),
		),
		$documentation,
		array(
			$biographies,
			array( 'key' => 'booklet', 'label' => 'Booklet (PDF)', 'type' => 'file', 'section' => 'Booklet', 'help' => 'Upload or choose a PDF from the media library. Shown as "Buklet" on the page.' ),
			array( 'key' => 'booklet_url', 'label' => 'or link to a booklet elsewhere', 'type' => 'url', 'help' => 'Only used when no PDF is chosen above.' ),
			$related,
		)
	),
	'residency'      => array_merge(
		$dates,
		array(
			array( 'key' => 'artists', 'label' => 'Residents', 'type' => 'list', 'section' => 'Residents and information', 'help' => 'One name per row. Shown under the dates.' ),
			array( 'key' => 'opening', 'label' => 'Opening', 'type' => 'text', 'help' => 'e.g. 21 November 2025 | 13.00 WIB' ),
			array( 'key' => 'public_program_info', 'label' => 'Public program info', 'type' => 'textarea' ),
			array( 'key' => 'venue_info', 'label' => 'Venue and hours', 'type' => 'textarea' ),
			array( 'key' => 'gallery', 'label' => 'Gallery (photos)', 'type' => 'images', 'section' => 'Gallery', 'help' => 'Shown as "Gallery" on the page, two photos per row.' ),
			array( 'key' => 'gallery_caption', 'label' => 'Gallery caption', 'type' => 'text' ),
		),
		$documentation,
		array(
			$biographies,
			array( 'key' => 'booklet', 'label' => 'Booklet (PDF)', 'type' => 'file', 'section' => 'Booklet', 'help' => 'Upload or choose a PDF from the media library.' ),
			array( 'key' => 'booklet_url', 'label' => 'or link to a booklet elsewhere', 'type' => 'url', 'help' => 'Only used when no PDF is chosen above.' ),
			$related,
		)
	),
	'public_program' => array_merge(
		$dates,
		array(
			array( 'key' => 'time_text', 'label' => 'Time', 'type' => 'text', 'section' => 'Information', 'help' => 'e.g. 15.00 – 17.00 WIB' ),
			array( 'key' => 'speakers', 'label' => 'Speakers', 'type' => 'text' ),
		),
		$documentation,
		array( $biographies, $related )
	),
	'open_studio'    => array_merge(
		$dates,
		array(
			array( 'key' => 'artists', 'label' => 'Artists', 'type' => 'list', 'section' => 'Artists', 'help' => 'One name per row.' ),
		),
		$documentation,
		array( $biographies, $related )
	),
	'open_call'      => array(
		array( 'key' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'section' => 'Status', 'help' => 'Tick = this call is the one shown on the Open Call page and it accepts applications. Untick to close it at once. If you never touch it, the call closes by itself after its deadline. The Open Call page shows one call: the newest active one.' ),
		array( 'key' => 'deadline', 'label' => 'Deadline', 'type' => 'date' ),
		array( 'key' => 'application_url', 'label' => 'External application URL (optional)', 'type' => 'url', 'help' => 'Leave empty to use the built-in form below the text. If set, the blue button goes to this address instead.' ),
		array( 'key' => 'documents', 'label' => 'Required documents', 'type' => 'text', 'help' => 'Comma separated: cv, portfolio, proposal, motivation. Empty = all four. Use "none" for a form without uploads.' ),
	),
	'journal'        => array(
		array( 'key' => 'author', 'label' => 'Author', 'type' => 'text' ),
		array( 'key' => 'documentation', 'label' => 'Documentation (photos)', 'type' => 'images', 'section' => 'Documentation', 'help' => 'Photos shown after the article, under the heading "Documentation". Add them in the order they should appear.' ),
		array( 'key' => 'documentation_caption', 'label' => 'Documentation caption', 'type' => 'text' ),
		$related,
	),
	'archive_item'   => array_merge( array(
		array( 'key' => 'file', 'label' => 'File to download (PDF)', 'type' => 'file', 'section' => 'Download', 'help' => 'Upload or choose a PDF from the media library: the booklet, catalogue or writing. The page shows a "Download Booklet" ("Unduh Booklet") button for it.' ),
		array( 'key' => 'file_url', 'label' => 'or link to the file elsewhere', 'type' => 'url', 'help' => 'Only used when no PDF is chosen above.' ),
		array( 'key' => 'authors', 'label' => 'Authors / editors', 'type' => 'text', 'section' => 'About the item' ),
		array( 'key' => 'year', 'label' => 'Year', 'type' => 'text', 'help' => 'For example 2024.' ),
		array( 'key' => 'gallery', 'label' => 'Gallery (photos)', 'type' => 'images', 'section' => 'Gallery', 'help' => 'Shown as "Galeri" on the page, two photos per row.' ),
		array( 'key' => 'gallery_caption', 'label' => 'Gallery caption', 'type' => 'text' ),
	),
		$documentation,
		array(
			array_merge(
				$biographies,
				array(
					'label'   => 'Profile',
					'section' => 'Profile',
					'item'    => 'Profile',
					'from'    => '',
					'help'    => 'One block per person (photo, name, text), in the order shown on the page. Shown as "Profil".',
				)
			),
		)
	),
	'person'         => array(
		array( 'key' => 'role', 'label' => 'Title / role', 'type' => 'text', 'section' => 'Shown on the About page' ),
		array( 'key' => 'quote', 'label' => 'Short quote', 'type' => 'textarea', 'help' => 'Shown under the title of an Advisory Board member. The photo is the Featured image (square).' ),
	),
);

// Search / sharing description, on every public type.
foreach ( array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal' ) as $seo_type ) {
	$fields[ $seo_type ][] = array( 'key' => 'seo_description', 'label' => 'Search description', 'type' => 'textarea', 'section' => 'Search and sharing', 'help' => 'Shown in search results and when shared. About 150 characters. Empty = taken from the text.' );
}

return $fields;
