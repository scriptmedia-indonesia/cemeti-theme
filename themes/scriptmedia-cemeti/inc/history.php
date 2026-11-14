<?php
/**
 * History: the chronology of exhibitions and events, one dropdown row per year (like Support Us), on its own page that
 * follows About in the menu. It is edited on the History page itself (and on its Indonesian copy), so a new year is added
 * once a year there. Until a page has a history of its own it shows the chronology supplied with the theme
 * (config/history-*.php), or the one that was saved on the About page before History had a page of its own.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field definition of the history rows (same repeater as the entries' biographies).
 *
 * @return array<string, mixed>
 */
function cemeti_history_def() {
	return array(
		'key'      => 'history',
		'label'    => 'History',
		'type'     => 'repeater',
		'item'     => 'Year',
		'title_by' => 'year',
		'sub'      => array(
			array( 'key' => 'year', 'label' => 'Year', 'type' => 'text' ),
			array( 'key' => 'entries', 'label' => 'Exhibitions and events', 'type' => 'textarea', 'rows' => 14 ),
		),
	);
}

/**
 * The chronology supplied with the theme, for a language.
 *
 * @param string $lang Language code.
 * @return array<int, array{year:string,entries:string}>
 */
function cemeti_history_bundled( $lang ) {
	$file = CEMETI_DIR . '/config/history-' . ( 'id' === $lang ? 'id' : 'en' ) . '.php';
	$rows = is_readable( $file ) ? include $file : array();
	return is_array( $rows ) ? $rows : array();
}

/**
 * Sort rows by year, oldest first.
 *
 * @param array $rows Rows.
 * @return array
 */
function cemeti_history_sort( array $rows ) {
	usort(
		$rows,
		static function ( $a, $b ) {
			return (int) $a['year'] <=> (int) $b['year'];
		}
	);
	return $rows;
}

/**
 * Rows to show for a page: its own, else the bundled chronology of the page's language.
 *
 * @param int $page_id Page ID.
 * @return array<int, array{year:string,entries:string}>
 */
function cemeti_history_rows( $page_id ) {
	$own = get_post_meta( $page_id, 'cemeti_history', true );
	if ( is_array( $own ) && $own ) {
		return cemeti_history_sort( $own );
	}
	// Edited on the About page in 0.9.37 – 0.9.48: that text is the History page's until it has its own.
	$lang_for = function_exists( 'pll_get_post_language' ) && pll_get_post_language( $page_id ) ? (string) pll_get_post_language( $page_id ) : cemeti_current_lang();
	$about    = function_exists( 'cemeti_find_page' ) ? (int) cemeti_find_page( 'about', $lang_for ) : 0;
	$legacy   = $about ? get_post_meta( $about, 'cemeti_history', true ) : array();
	if ( is_array( $legacy ) && $legacy ) {
		return cemeti_history_sort( $legacy );
	}
	$lang = function_exists( 'pll_get_post_language' ) && pll_get_post_language( $page_id ) ? cemeti_lang_code( (string) pll_get_post_language( $page_id ) ) : cemeti_current_lang();
	return cemeti_history_sort( cemeti_history_bundled( $lang ) );
}

/**
 * Split the entries text into entries (blank line between them) and lines.
 *
 * @param string $text Entries text.
 * @return array<int, string[]>
 */
function cemeti_history_entries( $text ) {
	$out = array();
	foreach ( preg_split( '/\R\s*\R/', trim( (string) $text ) ) as $block ) {
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $block ) ), 'strlen' ) );
		if ( $lines ) {
			$out[] = $lines;
		}
	}
	return $out;
}

/**
 * Print the dropdown rows (nothing when there are none).
 *
 * @param int $page_id History page ID.
 */
function cemeti_render_history( $page_id ) {
	$rows = array_filter(
		cemeti_history_rows( $page_id ),
		static function ( $row ) {
			return '' !== trim( (string) $row['year'] ) && '' !== trim( (string) $row['entries'] );
		}
	);
	if ( ! $rows ) {
		return;
	}
	echo '<section class="cemeti-history">';
	echo '<div class="cemeti-accordion">';
	foreach ( $rows as $row ) {
		echo '<details class="cemeti-accordion__row"><summary>' . esc_html( $row['year'] ) . '</summary>';
		echo '<div class="cemeti-accordion__panel cemeti-container cemeti-history__panel">';
		foreach ( cemeti_history_entries( $row['entries'] ) as $lines ) {
			echo '<p class="cemeti-history__item"><strong>' . esc_html( array_shift( $lines ) ) . '</strong>';
			foreach ( $lines as $line ) {
				echo '<span>' . esc_html( $line ) . '</span>';
			}
			echo '</p>';
		}
		echo '</div></details>';
	}
	echo '</div></section>';
}

/**
 * The box on the History page (and only there).
 *
 * @param WP_Post $post Page.
 */
function cemeti_history_add_box( $post ) {
	if ( 'history' !== cemeti_page_key( $post->ID ) ) {
		return;
	}
	add_meta_box( 'cemeti_history', __( 'History (chronology)', 'scriptmedia-cemeti' ), 'cemeti_history_render_box', 'page', 'normal', 'default' );
}
add_action( 'add_meta_boxes_page', 'cemeti_history_add_box', 7 );

/**
 * Render the box.
 *
 * @param WP_Post $post Page.
 */
function cemeti_history_render_box( $post ) {
	wp_nonce_field( 'cemeti_history_save', 'cemeti_history_nonce' );
	$own  = get_post_meta( $post->ID, 'cemeti_history', true );
	$rows = cemeti_history_rows( $post->ID );
	echo '<p class="description">' . esc_html__( 'One block per year, shown on the page as a dropdown row (oldest year first). Inside a block, separate the exhibitions / events with an empty line; the first line of each is its number and date (for example "375. 12 – 30 October 2026"), the lines below are the title, artists, programme and so on. Each year, press "Add Year" and write the new year. Do the same on the other language\'s History page.', 'scriptmedia-cemeti' ) . '</p>';
	if ( ! is_array( $own ) || ! $own ) {
		echo '<p class="description"><strong>' . esc_html__( 'These are the chronology supplied with the theme. Edit them if you wish and press Update: from then on this page keeps its own history.', 'scriptmedia-cemeti' ) . '</strong></p>';
	}
	cemeti_render_repeater( cemeti_history_def(), $rows );
}

/**
 * Save the box.
 *
 * @param int $post_id Page ID.
 */
function cemeti_history_save( $post_id ) {
	if ( ! isset( $_POST['cemeti_history_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cemeti_history_nonce'] ) ), 'cemeti_history_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$submitted = isset( $_POST['cemeti_fields']['history'] ) && is_array( $_POST['cemeti_fields']['history'] ) ? wp_unslash( $_POST['cemeti_fields']['history'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$rows      = cemeti_sanitize_field( cemeti_history_def(), $submitted );
	if ( $rows ) {
		update_post_meta( $post_id, 'cemeti_history', $rows );
	} else {
		delete_post_meta( $post_id, 'cemeti_history' ); // Emptied: the page falls back to the supplied chronology.
	}
}
add_action( 'save_post_page', 'cemeti_history_save' );
