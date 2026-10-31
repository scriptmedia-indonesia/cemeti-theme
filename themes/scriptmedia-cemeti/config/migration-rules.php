<?php
/**
 * Rules used by Tools → Cemeti Converter ("Cemeti rules" mode) to sort the posts of the
 * old cemeti.art site into content types. Derived from the 2026-10-31 export:
 * 578 posts, 221 categories, every post in 1–7 categories, Indonesian and English twins
 * as separate posts, English project categories marked with a trailing "_" in the slug.
 *
 * Slugs are compared without a trailing "_". First matching rule wins.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	// Posts that are not content (draft templates and edit notes).
	'skip_title' => '/^\s*(TEMPLATE\b|!!!)/i',

	// Language markers: category slugs that only occur on one language of a twin pair.
	'lang_id'    => array( 'pameran', 'diskusi', 'esai', 'lokakarya', 'program-publik', 'kabar-proyek', 'kabar-teman-rimpang', 'rimpang-nusantara', 'indonesia', 'acara', 'program', 'pengumuman', 'pertunjukan', 'presentasi', 'presentasi-khusus', 'katalog', 'buklet', 'pengantar-proyek', 'pengantar-program', 'pengantar-subprogram', 'pengantar', 'kabar-program', 'kabar', 'pameran-tunggal', 'pameran-kelompok', 'simposium', 'pemutaran', 'residensi', 'residensi-reguler', 'residensi-independen', 'artikel', 'publikasi', 'eksperimen', 'draft-2-berbagi', 'draft-1-proposisi', 'proyek-mustahil' ),
	'lang_en'    => array( 'exhibition', 'discussion', 'essay', 'workshop', 'public-program', 'rhizomatic-archipelago', 'rimpangs-stories', 'english', 'event', 'programme', 'announcement', 'performance', 'public-presentation', 'special-presentation', 'catalog', 'booklet', 'programme-introduction', 'project-introduction', 'program-updates', 'project-updates', 'news', 'group-exhibition', 'solo-exhibition', 'symposium', 'screening', 'residency', 'regular-residency', 'independent-residency', 'article', 'publication', 'open-studio-programme', '2nd-draft-berbagi', '1st-draft-propositions' ),

	// Content type rules, in priority order. 'cats' are slugs, 'title' an optional regex on the title.
	'types'      => array(
		array( 'type' => 'open_call', 'title' => '/open call|panggilan terbuka|lamaran terbuka/i' ),
		array( 'type' => 'open_studio', 'cats' => array( 'open-studio', 'open-studio-programme' ), 'title' => '/^\s*open studio\b/i' ),
		array( 'type' => 'exhibition', 'term' => 'Solo', 'cats' => array( 'pameran-tunggal', 'solo-exhibition' ) ),
		array( 'type' => 'exhibition', 'term' => 'Group', 'cats' => array( 'pameran-kelompok', 'group-exhibition' ) ),
		array( 'type' => 'exhibition', 'cats' => array( 'pameran', 'exhibition' ), 'term_from_title' => array( '/\b(tunggal|solo)\b/i' => 'Solo', '/\b(kelompok|group)\b/i' => 'Group' ) ),
		array( 'type' => 'public_program', 'cats' => array( 'program-publik', 'public-program', 'belajar-dari-yang-dekat', 'learning-nearby' ) ),
		array( 'type' => 'residency', 'term' => 'Reguler', 'cats' => array( 'residensi-reguler', 'regular-residency' ) ),
		array( 'type' => 'residency', 'cat_regex' => '/^(residensi|residency)/' ),
	),

	// Journal (default type): category term by old category.
	'journal'    => array(
		'Essay'            => array( 'esai', 'essay' ),
		'Curatorial Essay' => array( 'pengantar-proyek', 'pengantar-program', 'pengantar-subprogram', 'pengantar', 'programme-introduction', 'project-introduction' ),
		'default'          => 'News',
	),

	// Categories that describe the kind of post or the language, not a project.
	'not_project' => array( 'pameran', 'exhibition', 'pameran-tunggal', 'solo-exhibition', 'pameran-kelompok', 'group-exhibition', 'diskusi', 'discussion', 'esai', 'essay', 'lokakarya', 'workshop', 'program-publik', 'public-program', 'kabar-proyek', 'project-updates', 'kabar-teman-rimpang', 'rimpangs-stories', 'kabar-program', 'program-updates', 'kabar', 'news', 'pengumuman', 'announcement', 'english', 'indonesia', 'event', 'acara', 'program', 'programme', 'presentasi', 'public-presentation', 'presentasi-khusus', 'special-presentation', 'pertunjukan', 'performance', 'katalog', 'catalog', 'buklet', 'booklet', 'publikasi', 'publication', 'artikel', 'article', 'simposium', 'symposium', 'pemutaran', 'screening', 'eksperimen', 'forum', 'pengantar-proyek', 'project-introduction', 'pengantar-program', 'programme-introduction', 'pengantar-subprogram', 'pengantar', 'open-studio', 'open-studio-programme', 'residensi', 'residency', 'residensi-reguler', 'regular-residency', 'residensi-independen', 'independent-residency' ),
);
