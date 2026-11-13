<?php
/**
 * "Cemeti: translation" box on every entry (needs Polylang).
 *
 * Polylang's own "+" opens post-new.php?from_post=…&new_lang=…. When that does not lead to a new screen (the page
 * only reloads), this box makes the other-language copy itself: a draft with the title, text, picture, dates,
 * galleries, biographies and terms of the original, linked as its translation, and opens it. Polylang's "+" also
 * copies the Cemeti fields now (pll_copy_post_metas).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that can have a translation here: the Cemeti content types and pages.
 *
 * @return string[]
 */
function cemeti_translatable_types() {
	$types = array();
	foreach ( cemeti_config( 'post-types' ) as $type => $args ) {
		if ( 'application' !== $type ) {
			$types[] = $type;
		}
	}
	$types[] = 'page';
	return $types;
}

/**
 * Let Polylang copy the Cemeti fields (dates, artists, galleries, biographies, booklet, …) into a new translation.
 *
 * @param string[] $keys Meta keys Polylang copies.
 * @return string[]
 */
function cemeti_polylang_copy_metas( $keys, $sync = false ) {
	// Per-language fields: copied once when a translation is made, but never kept in sync afterwards,
	// so each language can hold its own PDF, text and credits.
	$per_language = array( 'file', 'file_url', 'show_reader', 'booklet', 'booklet_url', 'seo_description', 'opening', 'public_program_info', 'venue_info', 'time_text', 'gallery_caption', 'documentation_caption', 'biographies', 'authors', 'year', 'role', 'quote' );
	$extra        = array( '_thumbnail_id' );
	foreach ( cemeti_config( 'fields' ) as $defs ) {
		foreach ( (array) $defs as $def ) {
			if ( empty( $def['key'] ) ) {
				continue;
			}
			if ( $sync && in_array( $def['key'], $per_language, true ) ) {
				continue;
			}
			$extra[] = 'cemeti_' . $def['key'];
		}
	}
	$keys = (array) $keys;
	if ( $sync ) {
		foreach ( $per_language as $k ) {
			$keys = array_diff( $keys, array( 'cemeti_' . $k ) );
		}
	}
	return array_values( array_unique( array_merge( $keys, $extra ) ) );
}
add_filter( 'pll_copy_post_metas', 'cemeti_polylang_copy_metas', 10, 2 );

/**
 * Make the other-language copy of an entry.
 *
 * @param int    $post_id Original.
 * @param string $lang    Language slug of the copy.
 * @return int ID of the copy (the existing translation when there is one), 0 when it could not be made.
 */
function cemeti_translate_create( $post_id, $lang ) {
	if ( ! function_exists( 'pll_get_post_translations' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_save_post_translations' ) ) {
		return 0;
	}
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $lang, array_map( 'strval', (array) pll_languages_list() ), true ) ) {
		return 0;
	}
	$have = (array) pll_get_post_translations( $post_id );
	$mine = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post_id ) : '';
	if ( '' !== $mine && empty( $have[ $mine ] ) ) {
		$have[ $mine ] = (int) $post_id; // The original is part of the group.
	}
	if ( ! empty( $have[ $lang ] ) ) {
		return (int) $have[ $lang ];
	}
	$new = wp_insert_post(
		array(
			'post_type'    => $post->post_type,
			'post_status'  => 'draft',
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_author'  => (int) get_current_user_id() ? (int) get_current_user_id() : (int) $post->post_author,
			'menu_order'   => (int) $post->menu_order,
		)
	);
	if ( ! $new || is_wp_error( $new ) ) {
		return 0;
	}
	pll_set_post_language( $new, $lang );
	// Pictures, dates, artists, galleries, biographies, booklet… (not the two-way "related" list: those are other entries).
	foreach ( cemeti_polylang_copy_metas( array() ) as $key ) {
		if ( 'cemeti_related' === $key ) {
			continue;
		}
		$value = get_post_meta( $post_id, $key, true );
		if ( '' !== $value && array() !== $value && false !== $value && null !== $value ) {
			update_post_meta( $new, $key, $value );
		}
	}
	foreach ( array_keys( cemeti_config( 'taxonomies' ) ) as $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_array( $terms ) && $terms ) {
			wp_set_object_terms( $new, array_map( static function ( $t ) {
				return (int) $t->term_id;
			}, $terms ), $taxonomy );
		}
	}
	if ( function_exists( 'cemeti_sync_terms_to_language' ) ) {
		cemeti_sync_terms_to_language( $new ); // The terms of the new language.
	}
	pll_save_post_translations( array_merge( $have, array( $lang => (int) $new ) ) );
	return (int) $new;
}

/**
 * The button of the box.
 */
function cemeti_translate_handle() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$lang    = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'cemeti_translate_' . $post_id . '_' . $lang );
	$new = cemeti_translate_create( $post_id, $lang );
	if ( ! $new ) {
		wp_safe_redirect( add_query_arg( 'cemeti_translate_failed', 1, admin_url( 'post.php?action=edit&post=' . $post_id ) ) );
		exit;
	}
	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new ) );
	exit;
}
add_action( 'admin_post_cemeti_translate', 'cemeti_translate_handle' );

/**
 * Add the box on the types that have a translation.
 */
function cemeti_translate_add_box() {
	if ( ! function_exists( 'pll_languages_list' ) || count( (array) pll_languages_list() ) < 2 ) {
		return;
	}
	foreach ( cemeti_translatable_types() as $type ) {
		add_meta_box( 'cemeti_translate', __( 'Cemeti: translation', 'scriptmedia-cemeti' ), 'cemeti_translate_render_box', $type, 'side', 'high' );
	}
}
add_action( 'add_meta_boxes', 'cemeti_translate_add_box' );

/**
 * Content of the box: the existing translations, and a button for each missing language.
 *
 * @param WP_Post $post Post.
 */
function cemeti_translate_render_box( $post ) {
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p>' . esc_html__( 'Save the entry first, then create its translation here.', 'scriptmedia-cemeti' ) . '</p>';
		return;
	}
	if ( isset( $_GET['cemeti_translate_failed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<p><strong>' . esc_html__( 'The translation could not be created.', 'scriptmedia-cemeti' ) . '</strong></p>';
	}
	$mine = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post->ID ) : '';
	$have = (array) pll_get_post_translations( $post->ID );
	foreach ( array_map( 'strval', (array) pll_languages_list() ) as $lang ) {
		if ( $lang === $mine ) {
			continue;
		}
		echo '<p>';
		if ( ! empty( $have[ $lang ] ) ) {
			printf( '%s: <a href="%s">%s</a>', esc_html( strtoupper( $lang ) ), esc_url( get_edit_post_link( (int) $have[ $lang ] ) ), esc_html__( 'edit translation', 'scriptmedia-cemeti' ) );
		} else {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_translate&post=' . (int) $post->ID . '&lang=' . rawurlencode( $lang ) ), 'cemeti_translate_' . $post->ID . '_' . $lang );
			printf( '<a class="button" href="%s">%s</a>', esc_url( $url ), esc_html( sprintf( /* translators: %s: language code */ __( 'Create %s version (copy)', 'scriptmedia-cemeti' ), strtoupper( $lang ) ) ) );
		}
		echo '</p>';
	}
	echo '<p class="description">' . esc_html__( 'Makes a draft with the same title, text, picture, dates, galleries and terms, linked as the translation. Translate the text, then publish.', 'scriptmedia-cemeti' ) . '</p>';
}
