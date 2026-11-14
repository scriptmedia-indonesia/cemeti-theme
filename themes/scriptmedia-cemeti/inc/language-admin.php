<?php
/**
 * Admin side of the languages: tells the team what is missing for ID/EN to work, and explains each fixed page.
 *
 * The site is bilingual through Polylang: every entry and page exists once per language and the header switch
 * moves between the two copies. Without Polylang nothing can be translated, and a content type Polylang does not
 * know shows no language selector. This file reports both, so "no language box on this menu" has a visible reason.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What is not ready for two languages yet, as plain sentences.
 *
 * @return string[] Empty when everything is in order.
 */
function cemeti_language_issues() {
	if ( ! function_exists( 'pll_languages_list' ) ) {
		return array( __( 'Polylang is not active. Install and activate it to get the language selector on every menu and the ID/EN switch on the site.', 'scriptmedia-cemeti' ) );
	}
	$issues = array();
	$codes  = array_map( 'cemeti_lang_code', (array) pll_languages_list() );
	if ( ! in_array( 'en', $codes, true ) || ! in_array( 'id', $codes, true ) ) {
		$issues[] = __( 'Add both English and Indonesian under Languages → Languages (English is the main language).', 'scriptmedia-cemeti' );
	}
	if ( function_exists( 'pll_is_translated_post_type' ) ) {
		$missing = array();
		foreach ( array_keys( cemeti_config( 'post-types' ) ) as $type ) {
			if ( 'application' !== $type && ! pll_is_translated_post_type( $type ) ) {
				$missing[] = $type;
			}
		}
		if ( $missing ) {
			/* translators: %s: content types */
			$issues[] = sprintf( __( 'These menus have no language selector yet: %s. Enable them under Languages → Settings → Custom post types.', 'scriptmedia-cemeti' ), implode( ', ', $missing ) );
		}
	}
	return $issues;
}

/**
 * Show the language issues to administrators on the Dashboard and on Settings → Cemeti.
 */
function cemeti_language_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id     = $screen ? (string) $screen->id : '';
	if ( 'dashboard' !== $id && 'settings_page_cemeti-settings' !== $id ) {
		return;
	}
	foreach ( cemeti_language_issues() as $issue ) {
		printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html( $issue ) );
	}
}
add_action( 'admin_notices', 'cemeti_language_notice' );

/**
 * What a fixed page is made of, shown beside the editor of that page.
 *
 * @return array<string, string> Page key => help text.
 */
function cemeti_page_help_texts() {
	return array(
		'about'        => __( 'Main picture: the Featured image. Text: the editor (one column). Advisory Board and Team: People menu, group by group; each person has a square Featured image, a title and a short quote.', 'scriptmedia-cemeti' ),
		'open-archive' => __( 'The A–Z list fills itself from Exhibition, Residency, Public Program and Open Studio. Text above the list: the editor.', 'scriptmedia-cemeti' ),
		'support-us'   => __( 'Text: the editor. The rows (Patron of Cemeti, Friends of Cemeti …) are the child pages of this page; their order is the Order field.', 'scriptmedia-cemeti' ),
		'contact-us'   => __( 'Address, opening hours, e-mail and route link come from Settings → Cemeti (one set per language). Extra text: the editor.', 'scriptmedia-cemeti' ),
	);
}

/**
 * Register the help box on the fixed pages.
 *
 * @param WP_Post $post Page being edited.
 */
function cemeti_add_page_help_box( $post ) {
	$key   = cemeti_page_key( $post->ID );
	$texts = cemeti_page_help_texts();
	if ( ! isset( $texts[ $key ] ) ) {
		return;
	}
	add_meta_box(
		'cemeti_page_help',
		__( 'Cemeti page', 'scriptmedia-cemeti' ),
		static function () use ( $texts, $key ) {
			echo '<p>' . esc_html( $texts[ $key ] ) . '</p>';
		},
		'page',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_page', 'cemeti_add_page_help_box' );
