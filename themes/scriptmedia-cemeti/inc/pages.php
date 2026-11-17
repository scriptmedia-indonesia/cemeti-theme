<?php
/**
 * The fixed pages of the site (About, Open Archive, Support Us, Contact Us and the Support Us rows).
 *
 * The page templates (page-about.php …) only apply to a page with that slug, so a fresh install would show
 * "page not found" for every menu link until someone created them. This file creates the missing pages once
 * on activation, offers a button (admin notice and Settings → Cemeti) when something is missing later, and
 * lets a page keep its template even if its slug was changed (post meta _cemeti_page_key).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The pages the theme expects.
 *
 * Titles are the names used in the menu (English in both languages, as in the design). Rows of Support Us are
 * child pages: the template shows each one as an accordion row.
 *
 * @return array<string, array<string, mixed>> Keyed by slug.
 */
function cemeti_default_pages() {
	$pages = array(
		'about'        => array(
			'title' => 'About',
			'order' => 10,
		),
		'history'      => array(
			'title'   => 'History',
			'order'   => 15,
			// Opening text; the team replaces it under Pages → History (the years are in the box "History (chronology)").
			'content' => array(
				'en' => 'A chronology of the exhibitions, residencies, public programs and other events at Rumah Seni Cemeti since 1988. Choose a year to see what took place.',
				'id' => 'Kronologi pameran, residensi, program publik, dan kegiatan lain di Rumah Seni Cemeti sejak 1988. Pilih tahun untuk melihat apa yang berlangsung.',
			),
		),
		'open-archive' => array(
			'title'   => 'Open Archive',
			'order'   => 20,
			// Opening text; the list below it is made by itself from the booklets of the entries and the News posts.
			'content' => array(
				'en' => 'A database of the booklets and writings published by Cemeti, free to read and download.',
				'id' => 'Basis data buklet dan tulisan yang diterbitkan Cemeti, bebas dibaca dan diunduh.',
			),
		),
		'support-us'   => array(
			'title'    => 'Support Us',
			'order'    => 30,
			// Opening text; the team replaces it under Pages → Support Us.
			'content'  => array(
				'en' => "Support the work of Cemeti – Institut untuk Seni dan Masyarakat. Choose how you would like to take part below.",
				'id' => "Dukung kerja Cemeti – Institut untuk Seni dan Masyarakat. Pilih cara Anda ingin terlibat di bawah ini.",
			),
			'children' => array(
				'patron-of-cemeti'  => 'Patron of Cemeti',
				'friends-of-cemeti' => 'Friends of Cemeti',
				'annual-benefit'    => 'Annual Benefit',
				'collaboration'     => 'Collaboration',
			),
		),
		'contact-us'   => array(
			'title' => 'Contact Us',
			'order' => 40,
		),
	);

	/**
	 * Filters the pages created by the theme.
	 *
	 * @param array $pages Pages keyed by slug.
	 */
	return apply_filters( 'cemeti_default_pages', $pages );
}

/**
 * Language slugs that need their own copy of each page.
 *
 * @return string[]
 */
function cemeti_page_languages() {
	if ( function_exists( 'pll_languages_list' ) ) {
		$list = pll_languages_list();
		if ( is_array( $list ) && $list ) {
			return array_values( array_map( 'strval', $list ) );
		}
	}
	return array( cemeti_default_lang() );
}

/**
 * Find the page of a fixed Cemeti page in a language, whatever its status except the trash.
 *
 * First by slug. When there is none, by what the page IS: the design chosen in the editor, the slug the Indonesian
 * copy usually has ("tentang", "kontak-kami", "dukung-kami"), "about-2" or a page titled like it. So an existing
 * Indonesian page is never made a second time (and never lost: its text is untouched). With several candidates the
 * one that has text wins, then the oldest.
 *
 * @param string $slug Page slug (the English slug / page key).
 * @param string $lang Language slug.
 * @return int Page ID, 0 when there is none.
 */
function cemeti_find_page( $slug, $lang ) {
	$base = array(
		'post_type'        => 'page',
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
		'posts_per_page'   => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
	);
	if ( function_exists( 'pll_languages_list' ) ) {
		// Ask for every language and check the language of each page ourselves: Polylang's own "lang" filter is not applied
		// everywhere (the admin screens), and then the English page would be returned for the Indonesian one.
		$base['lang'] = '';
	}
	$in_lang = static function ( $page_id ) use ( $lang ) {
		return cemeti_page_in_lang( (int) ( is_object( $page_id ) ? $page_id->ID : $page_id ), $lang );
	};
	foreach ( get_posts( array_merge( $base, array( 'name' => $slug ) ) ) as $found ) {
		if ( $in_lang( $found ) ) {
			return (int) ( is_object( $found ) ? $found->ID : $found );
		}
	}
	if ( ! array_key_exists( $slug, cemeti_default_pages() ) ) {
		return 0; // A row of Support Us: found by its slug only.
	}
	$best     = 0;
	$best_len = -1;
	foreach ( get_posts( $base ) as $candidate ) {
		$candidate = (int) ( is_object( $candidate ) ? $candidate->ID : $candidate );
		if ( ! $in_lang( $candidate ) || cemeti_page_key( $candidate ) !== $slug ) {
			continue;
		}
		$len = strlen( trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $candidate ) ) ) );
		if ( $len > $best_len ) {
			$best     = $candidate;
			$best_len = $len;
		}
	}
	return $best;
}

/**
 * Is this page of the language? Without Polylang every page is. A page that has no language yet counts as the default one.
 *
 * @param int    $page_id Page ID.
 * @param string $lang    Language slug.
 * @return bool
 */
function cemeti_page_in_lang( $page_id, $lang ) {
	if ( ! function_exists( 'pll_get_post_language' ) ) {
		return true;
	}
	$own = (string) pll_get_post_language( $page_id );
	return '' === $own ? cemeti_lang_code( $lang ) === cemeti_default_lang() : cemeti_lang_code( $own ) === cemeti_lang_code( $lang );
}

/**
 * Every (slug, language) pair that has no page yet.
 *
 * @return array<int, array{0:string,1:string}>
 */
function cemeti_missing_pages() {
	$missing = array();
	foreach ( cemeti_default_pages() as $slug => $page ) {
		$slugs = array_merge( array( $slug ), isset( $page['children'] ) ? array_keys( $page['children'] ) : array() );
		foreach ( cemeti_page_languages() as $lang ) {
			foreach ( $slugs as $one ) {
				if ( ! cemeti_find_page( $one, $lang ) ) {
					$missing[] = array( $one, $lang );
				}
			}
		}
	}
	return $missing;
}

/**
 * Create one page, set its language and remember which theme page it is.
 *
 * @param string $slug      Slug.
 * @param string $title     Title.
 * @param string $lang      Language slug.
 * @param int    $parent_id Parent page.
 * @param int    $order     Menu order.
 * @param string $content   Content.
 * @return int Page ID or 0.
 */
function cemeti_create_page( $slug, $title, $lang, $parent_id, $order, $content ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_parent'  => $parent_id,
			'menu_order'   => $order,
			'post_content' => $content,
		)
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	update_post_meta( $id, '_cemeti_page_key', $slug );
	if ( function_exists( 'pll_set_post_language' ) ) {
		pll_set_post_language( $id, $lang );
		// The slug may have been made unique before the language was known ("about-2"): set it again.
		wp_update_post(
			array(
				'ID'        => $id,
				'post_name' => $slug,
			)
		);
	}
	return (int) $id;
}

/**
 * Create every missing page. Existing pages are never touched.
 *
 * @return int Number of pages created.
 */
function cemeti_create_missing_pages() {
	$created      = 0;
	$translations = array();
	foreach ( cemeti_default_pages() as $slug => $page ) {
		foreach ( cemeti_page_languages() as $lang ) {
			$parent = cemeti_find_page( $slug, $lang );
			if ( ! $parent ) {
				$text   = isset( $page['content'][ cemeti_lang_code( $lang ) ] ) ? $page['content'][ cemeti_lang_code( $lang ) ] : '';
				$parent = cemeti_create_page( $slug, $page['title'], $lang, 0, (int) $page['order'], $text );
				$created += $parent ? 1 : 0;
			}
			$translations[ $slug ][ $lang ] = $parent;
			$order = 0;
			foreach ( isset( $page['children'] ) ? $page['children'] : array() as $child_slug => $child_title ) {
				++$order;
				$child = cemeti_find_page( $child_slug, $lang );
				if ( ! $child && $parent ) {
					$child    = cemeti_create_page( $child_slug, $child_title, $lang, $parent, $order, 'id' === cemeti_lang_code( $lang ) ? 'Segera hadir.' : 'Coming soon.' );
					$created += $child ? 1 : 0;
				}
				$translations[ $child_slug ][ $lang ] = $child;
			}
		}
	}
	if ( function_exists( 'pll_save_post_translations' ) && function_exists( 'pll_get_post_translations' ) && count( cemeti_page_languages() ) > 1 ) {
		foreach ( $translations as $group ) {
			$group = array_filter( $group );
			if ( count( $group ) < 2 ) {
				continue;
			}
			// Link only what is not linked yet: a pair the team linked by hand is never rearranged.
			$default = cemeti_default_lang();
			$base_id = isset( $group[ $default ] ) ? (int) $group[ $default ] : (int) reset( $group );
			$linked  = (array) pll_get_post_translations( $base_id );
			$changed = false;
			foreach ( $group as $lang => $page_id ) {
				$page_id = (int) $page_id;
				if ( $page_id === $base_id || ! empty( $linked[ $lang ] ) ) {
					continue;
				}
				if ( count( (array) pll_get_post_translations( $page_id ) ) > 1 ) {
					continue; // That page already belongs to another pair.
				}
				$linked[ $lang ] = $page_id;
				$changed         = true;
			}
			if ( $changed ) {
				$own = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $base_id ) : '';
				if ( '' !== $own && empty( $linked[ $own ] ) ) {
					$linked[ $own ] = $base_id;
				}
				pll_save_post_translations( $linked );
			}
		}
	}
	if ( $created ) {
		delete_transient( 'cemeti_pages_missing' );
	}
	return $created;
}

/**
 * Create the pages when the theme is activated.
 */
function cemeti_create_pages_on_switch() {
	cemeti_create_missing_pages();
}
add_action( 'after_switch_theme', 'cemeti_create_pages_on_switch', 20 );

/**
 * Create the missing pages by itself when an administrator opens the dashboard after the theme was updated
 * or a language was added (QA round 3: the Indonesian Open Archive, Support Us and Contact Us pages did not
 * exist because the theme was updated in place, and "after_switch_theme" only runs on activation).
 * It runs once per theme version and set of languages; pages deleted on purpose afterwards stay deleted
 * (the "Create them" button on the notice still brings them back).
 */
function cemeti_create_pages_after_update() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$signature = CEMETI_VERSION . '|' . implode( ',', cemeti_page_languages() );
	if ( get_option( 'cemeti_pages_signature' ) === $signature ) {
		return;
	}
	cemeti_create_missing_pages();
	update_option( 'cemeti_pages_signature', $signature );
}
add_action( 'admin_init', 'cemeti_create_pages_after_update' );

/**
 * The button on the notice and on Settings → Cemeti.
 */
function cemeti_handle_create_pages() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'cemeti_create_pages' );
	$created = cemeti_create_missing_pages();
	wp_safe_redirect( add_query_arg( 'cemeti_pages_created', (int) $created, admin_url( 'options-general.php?page=cemeti-settings' ) ) );
	exit;
}
add_action( 'admin_post_cemeti_create_pages', 'cemeti_handle_create_pages' );

/**
 * Tell administrators when a menu page does not exist, with a one-click fix.
 */
function cemeti_missing_pages_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id     = $screen ? (string) $screen->id : '';
	if ( 'dashboard' !== $id && 'themes' !== $id && 'settings_page_cemeti-settings' !== $id ) {
		return;
	}
	if ( isset( $_GET['cemeti_pages_created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( /* translators: %d: number of pages */ _n( '%d page created.', '%d pages created.', (int) $_GET['cemeti_pages_created'], 'scriptmedia-cemeti' ), (int) $_GET['cemeti_pages_created'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
		);
		return;
	}
	$missing = cemeti_missing_pages();
	if ( ! $missing ) {
		return;
	}
	$names = array_unique( array_map( static function ( $pair ) {
		return $pair[0];
	}, $missing ) );
	printf(
		'<div class="notice notice-warning"><p>%s <a class="button button-primary" href="%s">%s</a></p></div>',
		esc_html( sprintf( /* translators: %s: page slugs */ __( 'These pages are linked from the menu but do not exist yet: %s.', 'scriptmedia-cemeti' ), implode( ', ', $names ) ) ),
		esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_create_pages' ), 'cemeti_create_pages' ) ),
		esc_html__( 'Create them', 'scriptmedia-cemeti' )
	);
}
add_action( 'admin_notices', 'cemeti_missing_pages_notice' );

/**
 * Slugs people give these pages in Indonesian (or by habit): "tentang" is the About page.
 *
 * @return array<string, string> Slug => page key.
 */
function cemeti_page_slug_aliases() {
	return array(
		'tentang'         => 'about',
		'tentang-cemeti'  => 'about',
		'about-us'        => 'about',
		'sejarah'         => 'history',
		'kronologi'       => 'history',
		'riwayat'         => 'history',
		'arsip-terbuka'   => 'open-archive',
		'dukung-kami'     => 'support-us',
		'dukung'          => 'support-us',
		'support'         => 'support-us',
		'hubungi-kami'    => 'contact-us',
		'kontak'          => 'contact-us',
		'kontak-kami'     => 'contact-us',
		'contact'         => 'contact-us',
	);
}

/**
 * Which Cemeti page a WordPress page is: the key chosen in the editor, else the key of its translation (so the
 * Indonesian "Tentang" looks like the English "About"), else a well-known slug. '' for an ordinary page.
 *
 * @param int $post_id Page ID.
 * @return string
 */
function cemeti_page_key( $post_id ) {
	$known = cemeti_default_pages();
	$own   = (string) get_post_meta( $post_id, '_cemeti_page_key', true );
	if ( '' !== $own ) {
		return array_key_exists( $own, $known ) ? $own : '';
	}
	if ( function_exists( 'pll_get_post_translations' ) ) {
		foreach ( (array) pll_get_post_translations( $post_id ) as $other ) {
			$key = (string) get_post_meta( (int) $other, '_cemeti_page_key', true );
			if ( (int) $other !== (int) $post_id && '' !== $key && array_key_exists( $key, $known ) ) {
				return $key;
			}
		}
	}
	// A sub-page (a row of Support Us) is never one of the fixed pages itself, whatever its title begins with.
	if ( (int) get_post_field( 'post_parent', $post_id ) ) {
		return '';
	}
	$aliases = cemeti_page_slug_aliases();
	// A translation made by Polylang's "+" or by hand often has a slug like "kontak-kami-2" or "contact-us-id":
	// try the slug as it is, without a trailing number/language, and the title.
	$slug       = (string) get_post_field( 'post_name', $post_id );
	$candidates = array( $slug, preg_replace( '/-(\d+|id|en)$/', '', $slug ), sanitize_title( (string) get_post_field( 'post_title', $post_id ) ) );
	foreach ( $candidates as $candidate ) {
		if ( '' === $candidate ) {
			continue;
		}
		if ( isset( $aliases[ $candidate ] ) && array_key_exists( $aliases[ $candidate ], $known ) ) {
			return $aliases[ $candidate ];
		}
		if ( array_key_exists( $candidate, $known ) ) {
			return $candidate;
		}
	}
	// Indonesian titles that begin like the page: "Tentang Kami", "Tentang Rumah Seni Cemeti", "Hubungi Cemeti" …
	$prefixes = array( 'tentang' => 'about', 'sejarah' => 'history', 'kronologi' => 'history', 'arsip-terbuka' => 'open-archive', 'dukung' => 'support-us', 'dukungan' => 'support-us', 'hubungi' => 'contact-us', 'kontak' => 'contact-us' );
	foreach ( $candidates as $candidate ) {
		foreach ( $prefixes as $start => $page_key ) {
			if ( '' !== $candidate && ( $candidate === $start || 0 === strpos( $candidate, $start . '-' ) ) ) {
				return $page_key;
			}
		}
	}
	return '';
}

/**
 * A page keeps its Cemeti template even if its slug was changed, translated or chosen by hand.
 *
 * @param string $template Template file chosen by WordPress.
 * @return string
 */
function cemeti_page_template_by_key( $template ) {
	if ( ! is_page() ) {
		return $template;
	}
	$key = cemeti_page_key( get_queried_object_id() );
	if ( '' === $key ) {
		return $template;
	}
	$found = locate_template( array( 'page-' . $key . '.php' ) );
	return $found ? $found : $template;
}
add_filter( 'template_include', 'cemeti_page_template_by_key' );

/**
 * "Cemeti page" selector on every page: tells the theme which design the page uses.
 *
 * @param WP_Post $post Page being edited.
 */
function cemeti_add_page_type_box( $post ) {
	add_meta_box( 'cemeti_page_type', __( 'Cemeti page design', 'scriptmedia-cemeti' ), 'cemeti_render_page_type_box', 'page', 'side', 'high' );
}
add_action( 'add_meta_boxes_page', 'cemeti_add_page_type_box', 5 );

/**
 * Render the selector.
 *
 * @param WP_Post $post Page being edited.
 */
function cemeti_render_page_type_box( $post ) {
	wp_nonce_field( 'cemeti_page_type', 'cemeti_page_type_nonce' );
	$current = (string) get_post_meta( $post->ID, '_cemeti_page_key', true );
	echo '<p><label for="cemeti_page_type">' . esc_html__( 'This page uses the design of', 'scriptmedia-cemeti' ) . '</label></p>';
	echo '<select id="cemeti_page_type" name="cemeti_page_type" class="widefat"><option value="">' . esc_html__( 'Automatic (by slug / translation)', 'scriptmedia-cemeti' ) . '</option>';
	foreach ( cemeti_default_pages() as $key => $page ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $page['title'] ) );
	}
	echo '</select><p class="description">' . esc_html__( 'Set it on the Indonesian copy too (for example "Tentang" = About) so both languages look the same.', 'scriptmedia-cemeti' ) . '</p>';
}

/**
 * Save the selector.
 *
 * @param int $post_id Page ID.
 */
function cemeti_save_page_type( $post_id ) {
	if ( ! isset( $_POST['cemeti_page_type_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cemeti_page_type_nonce'] ) ), 'cemeti_page_type' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}
	$key = isset( $_POST['cemeti_page_type'] ) ? sanitize_key( wp_unslash( $_POST['cemeti_page_type'] ) ) : '';
	if ( '' !== $key && array_key_exists( $key, cemeti_default_pages() ) ) {
		update_post_meta( $post_id, '_cemeti_page_key', $key );
	} else {
		delete_post_meta( $post_id, '_cemeti_page_key' );
	}
}
add_action( 'save_post_page', 'cemeti_save_page_type' );


/**
 * The rows of Support Us: the child pages of this page. A translated copy that has no children of its own shows
 * the rows of the other language's page (each one in the current language when it has a translation), so English
 * and Indonesian always show the same rows.
 *
 * @param int $page_id Support Us page ID.
 * @return WP_Post[]
 */
function cemeti_support_rows( $page_id ) {
	$args = array( 'parent' => $page_id, 'sort_column' => 'menu_order,post_title', 'post_status' => 'publish' );
	$rows = get_pages( $args );
	if ( $rows || ! function_exists( 'pll_get_post_translations' ) ) {
		return $rows;
	}
	$lang = function_exists( 'pll_current_language' ) ? (string) pll_current_language() : '';
	foreach ( (array) pll_get_post_translations( $page_id ) as $other ) {
		if ( (int) $other === (int) $page_id ) {
			continue;
		}
		foreach ( get_pages( array_merge( $args, array( 'parent' => (int) $other ) ) ) as $row ) {
			$mine   = $lang && function_exists( 'pll_get_post' ) ? (int) pll_get_post( $row->ID, $lang ) : 0;
			$rows[] = $mine ? get_post( $mine ) : $row;
		}
		if ( $rows ) {
			break;
		}
	}
	return $rows;
}

/* ------------------------------------------------------------------------------------------------------------
 * Fixed pages in both languages: link them by hand when they could not be matched by themselves
 * ---------------------------------------------------------------------------------------------------------- */

/**
 * Link the chosen page of each language as translations of one another and remember which Cemeti page they are.
 * An explicit choice replaces whatever link those pages had.
 *
 * @param string          $key     Page key ("about", "open-archive" …).
 * @param array<string,int> $by_lang Language slug => page ID (0 = none).
 * @return bool Whether a link was saved.
 */
function cemeti_pair_pages( $key, array $by_lang ) {
	$by_lang = array_filter( array_map( 'intval', $by_lang ) );
	if ( ! array_key_exists( $key, cemeti_default_pages() ) ) {
		return false;
	}
	foreach ( $by_lang as $lang => $page_id ) {
		if ( 'page' === get_post_type( $page_id ) ) {
			update_post_meta( $page_id, '_cemeti_page_key', $key );
			// The language chosen in the table is the page's language: a page that had none (or another one) would otherwise
			// be linked but never found for that language, and the menu / language switch would not use it.
			if ( function_exists( 'pll_set_post_language' ) && is_string( $lang ) ) {
				pll_set_post_language( $page_id, $lang );
			}
		}
	}
	if ( count( $by_lang ) > 1 && function_exists( 'pll_save_post_translations' ) ) {
		pll_save_post_translations( $by_lang );
		return true;
	}
	return false;
}

/**
 * Save the choices of the "Fixed pages" table.
 */
function cemeti_handle_save_page_pairs() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'cemeti_save_page_pairs' );
	$posted = isset( $_POST['cemeti_pair'] ) && is_array( $_POST['cemeti_pair'] ) ? wp_unslash( $_POST['cemeti_pair'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$linked = 0;
	foreach ( cemeti_default_pages() as $key => $page ) {
		if ( ! isset( $posted[ $key ] ) || ! is_array( $posted[ $key ] ) ) {
			continue;
		}
		$by_lang = array();
		foreach ( cemeti_page_languages() as $lang ) {
			$by_lang[ $lang ] = isset( $posted[ $key ][ $lang ] ) ? absint( $posted[ $key ][ $lang ] ) : 0;
		}
		$linked += cemeti_pair_pages( $key, $by_lang ) ? 1 : 0;
	}
	wp_safe_redirect( add_query_arg( 'cemeti_pairs_saved', $linked, admin_url( 'options-general.php?page=cemeti-settings' ) ) );
	exit;
}
add_action( 'admin_post_cemeti_save_page_pairs', 'cemeti_handle_save_page_pairs' );

/**
 * The "Fixed pages" table (inside Settings → Cemeti).
 */
function cemeti_render_page_pairs_box() {
	$languages = cemeti_page_languages();
	if ( count( $languages ) < 2 ) {
		return;
	}
	if ( isset( $_GET['cemeti_pairs_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( /* translators: %d: number of pages */ _n( '%d page linked.', '%d pages linked.', (int) $_GET['cemeti_pairs_saved'], 'scriptmedia-cemeti' ), (int) $_GET['cemeti_pairs_saved'] ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	echo '<h2>' . esc_html__( 'Fixed pages in both languages', 'scriptmedia-cemeti' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'Choose which page is About / Open Archive / Support Us / Contact Us in each language and press Save: they are linked as translations (language switch, pencil icon) and the language\'s menu opens the page you chose. The text of the pages is never changed.', 'scriptmedia-cemeti' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="cemeti_save_page_pairs">';
	wp_nonce_field( 'cemeti_save_page_pairs' );
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>' . esc_html__( 'Page', 'scriptmedia-cemeti' ) . '</th>';
	foreach ( $languages as $lang ) {
		echo '<th>' . esc_html( strtoupper( $lang ) ) . '</th>';
	}
	echo '<th>' . esc_html__( 'Linked', 'scriptmedia-cemeti' ) . '</th></tr></thead><tbody>';
	foreach ( cemeti_default_pages() as $key => $page ) {
		$current = array();
		echo '<tr><td><strong>' . esc_html( $page['title'] ) . '</strong></td>';
		foreach ( $languages as $lang ) {
			$current[ $lang ] = cemeti_find_page( $key, $lang );
			// Pages of this language and pages without a language (they are given this one when saved); never the other language's.
			$choices = array_filter(
				get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false, 'lang' => '' ) ), // phpcs:ignore WordPressVIPMinimum
				static function ( $candidate ) use ( $lang ) {
					$own = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $candidate->ID ) : '';
					return '' === $own || cemeti_lang_code( $own ) === $lang;
				}
			);
			echo '<td><select name="cemeti_pair[' . esc_attr( $key ) . '][' . esc_attr( $lang ) . ']"><option value="0">' . esc_html__( '— none —', 'scriptmedia-cemeti' ) . '</option>';
			foreach ( $choices as $choice ) {
				printf( '<option value="%d"%s>%s (#%d)</option>', (int) $choice->ID, selected( (int) $choice->ID, $current[ $lang ], false ), esc_html( get_the_title( $choice ) ), (int) $choice->ID );
			}
			echo '</select></td>';
		}
		$linked = true;
		$first  = reset( $current );
		if ( $first && function_exists( 'pll_get_post_translations' ) ) {
			$have = (array) pll_get_post_translations( $first );
			foreach ( $current as $lang => $page_id ) {
				if ( $page_id && ( empty( $have[ $lang ] ) || (int) $have[ $lang ] !== (int) $page_id ) ) {
					$linked = false;
				}
			}
		}
		echo '<td>' . ( $linked && $first ? '✔' : '✘' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	submit_button( __( 'Save and link', 'scriptmedia-cemeti' ), 'secondary', 'submit', true );
	echo '</form>';
	echo '<details style="margin:1em 0"><summary>' . esc_html__( 'Where the overlay menu links go (per language)', 'scriptmedia-cemeti' ) . '</summary>';
	foreach ( $languages as $lang ) {
		echo '<h4>' . esc_html( strtoupper( $lang ) ) . '</h4><ul>';
		foreach ( cemeti_default_menu() as $column ) {
			foreach ( $column as $group ) {
				foreach ( $group['items'] as $item ) {
					$url = cemeti_menu_url( $item['path'], $lang );
					echo '<li>' . esc_html( $item['label'] ) . ' → <code>' . esc_html( $url ) . '</code></li>';
				}
			}
		}
		echo '</ul>';
	}
	echo '</details>';
}

/**
 * A row of Support Us is read on the Support Us page (as its dropdown), not on a page of its own: its address leads
 * there and opens that row.
 */
function cemeti_support_row_redirect() {
	if ( ! is_page() ) {
		return;
	}
	$page = get_queried_object();
	if ( ! $page || empty( $page->post_parent ) || 'support-us' !== cemeti_page_key( (int) $page->post_parent ) ) {
		return;
	}
	$parent = get_permalink( (int) $page->post_parent );
	if ( $parent ) {
		wp_safe_redirect( $parent . '#' . $page->post_name );
		exit;
	}
}
add_action( 'template_redirect', 'cemeti_support_row_redirect' );
