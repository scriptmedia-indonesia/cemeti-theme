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
 * Reduce a Polylang language slug to the code used for UI strings and settings ("id" or "en").
 * Polylang slugs can be "en", "en-us", "en_GB", "id", "in" ...; anything unknown stays as given.
 *
 * @param string $slug Language slug.
 * @return string
 */
function cemeti_lang_code( $slug ) {
	$code = strtolower( substr( preg_replace( '/[^A-Za-z]/', '', (string) $slug ), 0, 2 ) );
	if ( 'in' === $code ) {
		return 'id';
	}
	return in_array( $code, array( 'id', 'en' ), true ) ? $code : strtolower( (string) $slug );
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
			return cemeti_lang_code( (string) $lang );
		}
	}
	return 'en'; // The site's main language is English; Indonesian is the second language.
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
			return cemeti_lang_code( (string) $lang );
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
		'toc'                => array( 'id' => 'Daftar Isi', 'en' => 'Contents' ),
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
		'apply_button'      => array( 'id' => 'Application Submission', 'en' => 'Application Submission' ),
		'deadline'           => array( 'id' => 'Batas waktu', 'en' => 'Deadline' ),
		'current_residence'  => array( 'id' => 'Residen saat ini', 'en' => 'Current Residence' ),
		'advisory_board'     => array( 'id' => 'Dewan Penasihat', 'en' => 'Advisory Board' ),
		'team'               => array( 'id' => 'Tim', 'en' => 'Team' ),
		'history'            => array( 'id' => 'Kronologi', 'en' => 'History' ),
		'untitled'           => array( 'id' => 'Tanpa judul', 'en' => 'Untitled' ),
		'search_results'     => array( 'id' => 'Hasil pencarian', 'en' => 'Search results' ),
		'no_results'         => array( 'id' => 'Tidak ada hasil.', 'en' => 'Nothing found.' ),
		'not_found'          => array( 'id' => 'Halaman tidak ditemukan', 'en' => 'Page not found' ),
		'not_found_text'     => array( 'id' => 'Halaman yang Anda cari tidak ada atau sudah dipindahkan.', 'en' => 'The page you are looking for does not exist or has moved.' ),
		'go_home'            => array( 'id' => 'Ke beranda', 'en' => 'Go to home' ),
		'previous_of'        => array( 'id' => '%s Sebelumnya', 'en' => 'Previous %s' ),
		'pagination'         => array( 'id' => 'Navigasi halaman', 'en' => 'Pagination' ),
		'previous_of'        => array( 'id' => '%s Sebelumnya', 'en' => 'Previous %s' ),
		'pagination'         => array( 'id' => 'Navigasi halaman', 'en' => 'Pagination' ),
		'newer'              => array( 'id' => 'Lebih baru', 'en' => 'Newer' ),
		'older'              => array( 'id' => 'Lebih lama', 'en' => 'Older' ),
		'contact_us'         => array( 'id' => 'Hubungi Kami', 'en' => 'Contact Us' ),
		'email'              => array( 'id' => 'Email', 'en' => 'Email' ),
		'form_title'         => array( 'id' => 'Formulir pendaftaran', 'en' => 'Application form' ),
		'form_name'          => array( 'id' => 'Nama lengkap', 'en' => 'Full name' ),
		'form_email'         => array( 'id' => 'Email', 'en' => 'Email' ),
		'form_city'          => array( 'id' => 'Kota, negara', 'en' => 'City, country' ),
		'form_link'          => array( 'id' => 'Tautan portofolio atau situs web', 'en' => 'Portfolio or website link' ),
		'form_message'       => array( 'id' => 'Pesan singkat', 'en' => 'Short message' ),
		'form_cv'            => array( 'id' => 'CV', 'en' => 'CV' ),
		'form_portfolio'     => array( 'id' => 'Portofolio', 'en' => 'Portfolio' ),
		'form_proposal'      => array( 'id' => 'Proposal', 'en' => 'Proposal' ),
		'form_motivation'    => array( 'id' => 'Surat motivasi', 'en' => 'Motivation letter' ),
		'form_pdf_hint'      => array( 'id' => 'PDF, maksimal 5 MB per berkas', 'en' => 'PDF, up to 5 MB per file' ),
		'form_required'      => array( 'id' => 'wajib', 'en' => 'required' ),
		'form_submit'        => array( 'id' => 'Kirim lamaran', 'en' => 'Send application' ),
		'form_sent'          => array( 'id' => 'Terima kasih. Lamaran Anda sudah kami terima dan konfirmasi dikirim ke email Anda.', 'en' => 'Thank you. We have received your application and sent a confirmation to your email.' ),
		'form_closed'        => array( 'id' => 'Pendaftaran sudah ditutup.', 'en' => 'Applications are closed.' ),
		'err_required'       => array( 'id' => 'Lengkapi semua isian yang wajib.', 'en' => 'Please fill in all required fields.' ),
		'err_email'          => array( 'id' => 'Alamat email tidak valid.', 'en' => 'The email address is not valid.' ),
		'err_file'           => array( 'id' => 'Berkas harus berupa PDF dan tidak lebih dari 5 MB.', 'en' => 'Files must be PDF and no larger than 5 MB.' ),
		'err_closed'         => array( 'id' => 'Pendaftaran sudah ditutup.', 'en' => 'Applications are closed.' ),
		'err_spam'           => array( 'id' => 'Formulir tidak dapat dikirim. Muat ulang halaman dan coba lagi.', 'en' => 'The form could not be sent. Reload the page and try again.' ),
		'err_rate'           => array( 'id' => 'Terlalu banyak percobaan. Coba lagi nanti.', 'en' => 'Too many attempts. Please try again later.' ),
		'err_server'         => array( 'id' => 'Terjadi kesalahan. Coba lagi nanti.', 'en' => 'Something went wrong. Please try again later.' ),
		'mail_subject'       => array( 'id' => 'Lamaran Anda untuk %s', 'en' => 'Your application for %s' ),
		'mail_body'          => array( 'id' => "Halo %1\$s,\n\nKami sudah menerima lamaran Anda untuk \"%2\$s\". Tim kami akan menghubungi Anda jika diperlukan.\n\nSalam,\n%3\$s", 'en' => "Hello %1\$s,\n\nWe have received your application for \"%2\$s\". Our team will contact you if needed.\n\nBest regards,\n%3\$s" ),
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
 * Where the language switch leads from an entry that is being read.
 *
 * @param string $lang     Target language slug.
 * @param string $fallback Address Polylang offered.
 * @return string
 */
function cemeti_switch_url_for_entry( $lang, $fallback ) {
	$id = (int) get_queried_object_id();
	if ( $id && function_exists( 'pll_get_post_translations' ) ) {
		$translations = (array) pll_get_post_translations( $id );
		$other        = isset( $translations[ $lang ] ) ? (int) $translations[ $lang ] : 0;
		if ( $other && (int) $other !== $id && 'publish' === get_post_status( $other ) ) {
			return (string) get_permalink( $other );
		}
	}
	$type = $id ? get_post_type( $id ) : '';
	$obj  = $type ? get_post_type_object( $type ) : null;
	$slug = $obj && ! empty( $obj->has_archive ) ? ( is_string( $obj->has_archive ) ? $obj->has_archive : $type ) : '';
	if ( '' !== $slug ) {
		return trailingslashit( cemeti_home_url( $lang ) ) . trim( $slug, '/' ) . '/';
	}
	return $fallback;
}

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
			// On a fixed page (About, Contact …) whose other-language page is not linked as its translation, Polylang
			// offers the home page; the page of that language is found by what it is instead.
			$key = function_exists( 'is_page' ) && is_page() && function_exists( 'cemeti_page_key' ) ? cemeti_page_key( get_queried_object_id() ) : '';
			foreach ( $raw as $language ) {
				$url = (string) $language['url'];
				if ( '' !== $key && empty( $language['current_lang'] ) && function_exists( 'cemeti_find_page' ) ) {
					$other = cemeti_find_page( $key, (string) $language['slug'] );
					if ( $other && 'publish' === get_post_status( $other ) && (int) $other !== (int) get_queried_object_id() ) {
						$linked = function_exists( 'pll_get_post' ) ? (int) pll_get_post( get_queried_object_id(), (string) $language['slug'] ) : 0;
						$url    = $linked ? $url : (string) get_permalink( $other );
					}
				}
				// An entry (exhibition, journal …): the translation when there is one, otherwise the same section in that language
				// (never the other language's home page, and never a link back to the entry being read).
				if ( empty( $language['current_lang'] ) && function_exists( 'is_singular' ) && is_singular() && ! is_page() ) {
					$url = cemeti_switch_url_for_entry( (string) $language['slug'], $url );
				}
				$items[] = array(
					'code'    => (string) $language['slug'],
					'label'   => strtoupper( (string) $language['slug'] ),
					'url'     => $url,
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
