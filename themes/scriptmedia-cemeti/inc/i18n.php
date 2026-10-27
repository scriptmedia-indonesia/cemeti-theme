<?php
/**
 * Language helpers. Every Polylang call is guarded so the theme works without it.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default site language slug (the language without a URL prefix).
 *
 * @return string
 */
function cemeti_default_lang() {
	if ( function_exists( 'pll_default_language' ) ) {
		$lang = pll_default_language();
		if ( $lang ) {
			return (string) $lang;
		}
	}
	return 'id';
}

/**
 * Active language slug, falling back to the default language.
 *
 * @return string
 */
function cemeti_current_lang() {
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language();
		if ( $lang ) {
			return (string) $lang;
		}
	}
	return cemeti_default_lang();
}

/**
 * Home URL for a language (or the active one).
 *
 * @param string $lang Optional language slug.
 * @return string
 */
function cemeti_home_url( $lang = '' ) {
	if ( function_exists( 'pll_home_url' ) ) {
		return pll_home_url( $lang ? $lang : null );
	}
	return home_url( '/' );
}

/**
 * Build a URL for a program path in the active language.
 *
 * @param string $path Path such as "exhibition" or "exhibition/?type=solo".
 * @return string
 */
function cemeti_url( $path ) {
	return trailingslashit( cemeti_home_url() ) . ltrim( $path, '/' );
}

/**
 * UI strings that exist in Indonesian and English.
 * Program and menu names (Exhibition, Residency…) stay English and are not listed here.
 *
 * @return array<string, array<string, string>> Keyed by string key, then language.
 */
function cemeti_ui_strings() {
	return array(
		'menu'               => array( 'id' => 'Menu', 'en' => 'Menu' ),
		'close'              => array( 'id' => 'Tutup', 'en' => 'Close' ),
		'back'               => array( 'id' => 'Kembali', 'en' => 'Back' ),
		'search'             => array( 'id' => 'Cari', 'en' => 'Search' ),
		'search_placeholder' => array( 'id' => 'Ketik untuk mencari…', 'en' => 'Type to search…' ),
		'search_label'       => array( 'id' => 'Cari di situs', 'en' => 'Search the site' ),
		'whats_on'           => array( 'id' => 'Sedang berlangsung', 'en' => "What's on" ),
		'plan_route'         => array( 'id' => 'Rencanakan rute', 'en' => 'Plan your Route' ),
		'skip_to_content'    => array( 'id' => 'Lewati ke konten', 'en' => 'Skip to content' ),
		'language'           => array( 'id' => 'Bahasa', 'en' => 'Language' ),
		'main_navigation'    => array( 'id' => 'Navigasi utama', 'en' => 'Main navigation' ),
		'instagram'          => array( 'id' => 'Instagram', 'en' => 'Instagram' ),
		'youtube'            => array( 'id' => 'Youtube', 'en' => 'Youtube' ),
		'facebook'           => array( 'id' => 'Facebook', 'en' => 'Facebook' ),
		'toc'                => array( 'id' => 'Daftar isi', 'en' => 'Contents' ),
		'foreword'           => array( 'id' => 'Kata Pengantar', 'en' => 'Foreword' ),
		'gallery'            => array( 'id' => 'Galeri', 'en' => 'Gallery' ),
		'documentation'      => array( 'id' => 'Dokumentasi', 'en' => 'Documentation' ),
		'biography'          => array( 'id' => 'Biografi', 'en' => 'Biography' ),
		'booklet'            => array( 'id' => 'Buklet', 'en' => 'Booklet' ),
		'related'            => array( 'id' => 'Publikasi Terkait', 'en' => 'Related Publications' ),
		'back_to_toc'        => array( 'id' => 'Kembali ke Daftar Isi', 'en' => 'Back to Contents' ),
		'share'              => array( 'id' => 'Bagikan', 'en' => 'Share' ),
		'share_more'         => array( 'id' => 'Lainnya', 'en' => 'More' ),
		'opening'            => array( 'id' => 'Pembukaan', 'en' => 'Opening' ),
		'public_program'     => array( 'id' => 'Program Publik', 'en' => 'Public Program' ),
		'deadline'           => array( 'id' => 'Batas waktu', 'en' => 'Deadline' ),
		'current_residence'  => array( 'id' => 'Residen saat ini', 'en' => 'Current Residence' ),
		'advisory_board'     => array( 'id' => 'Dewan Penasihat', 'en' => 'Advisory Board' ),
		'team'               => array( 'id' => 'Tim', 'en' => 'Team' ),
		'search_results'     => array( 'id' => 'Hasil pencarian', 'en' => 'Search results' ),
		'no_results'         => array( 'id' => 'Tidak ada hasil.', 'en' => 'Nothing found.' ),
		'not_found'          => array( 'id' => 'Halaman tidak ditemukan', 'en' => 'Page not found' ),
		'not_found_text'     => array( 'id' => 'Halaman yang Anda cari tidak ada atau sudah dipindahkan.', 'en' => 'The page you are looking for does not exist or has moved.' ),
		'go_home'            => array( 'id' => 'Ke beranda', 'en' => 'Go to home' ),
		'newer'              => array( 'id' => 'Lebih baru', 'en' => 'Newer' ),
		'older'              => array( 'id' => 'Lebih lama', 'en' => 'Older' ),
		'contact_us'         => array( 'id' => 'Hubungi Kami', 'en' => 'Contact Us' ),
		'email'              => array( 'id' => 'Email', 'en' => 'Email' ),
		'status_current'     => array( 'id' => 'Sedang berlangsung', 'en' => 'Current' ),
		'status_upcoming'    => array( 'id' => 'Mendatang', 'en' => 'Upcoming' ),
		'status_previous'    => array( 'id' => 'Sebelumnya', 'en' => 'Previous' ),
	);
}

/**
 * Translate a UI string key for the active language.
 *
 * @param string $key String key from cemeti_ui_strings().
 * @return string
 */
function cemeti_t( $key ) {
	$strings = cemeti_ui_strings();
	if ( ! isset( $strings[ $key ] ) ) {
		return $key;
	}
	$lang = cemeti_current_lang();
	$text = $strings[ $key ][ $lang ] ?? $strings[ $key ]['en'];

	if ( function_exists( 'pll__' ) ) {
		$text = pll__( $text );
	}
	return $text;
}

/**
 * Register UI strings with Polylang so editors can override them.
 */
function cemeti_register_strings() {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}
	foreach ( cemeti_ui_strings() as $key => $by_lang ) {
		pll_register_string( 'cemeti_' . $key, $by_lang['en'], 'Cemeti' );
	}
}
add_action( 'init', 'cemeti_register_strings' );

/**
 * Items for the ID/EN switcher.
 * With Polylang each item links to the translation of the current page, or to
 * the target language home when no translation exists.
 *
 * @return array<int, array{code:string,label:string,url:string,current:bool}>
 */
function cemeti_language_switcher_items() {
	if ( function_exists( 'pll_the_languages' ) ) {
		$raw = pll_the_languages(
			array(
				'raw'                    => 1,
				'hide_if_no_translation' => 0,
				'hide_current'           => 0,
			)
		);
		if ( is_array( $raw ) && $raw ) {
			$items = array();
			foreach ( $raw as $language ) {
				$items[] = array(
					'code'    => (string) $language['slug'],
					'label'   => strtoupper( (string) $language['slug'] ),
					'url'     => (string) $language['url'],
					'current' => ! empty( $language['current_lang'] ),
				);
			}
			return $items;
		}
	}

	return array(
		array(
			'code'    => 'id',
			'label'   => 'ID',
			'url'     => home_url( '/' ),
			'current' => true,
		),
	);
}
