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
		'open-archive' => array(
			'title' => 'Open Archive',
			'order' => 20,
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
 * Find a page by slug (and language, with Polylang), whatever its status except the trash.
 *
 * @param string $slug Page slug.
 * @param string $lang Language slug.
 * @return int Page ID, 0 when there is none.
 */
function cemeti_find_page( $slug, $lang ) {
	$args = array(
		'post_type'        => 'page',
		'name'             => $slug,
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
	);
	if ( function_exists( 'pll_languages_list' ) ) {
		$args['lang'] = $lang;
	}
	$found = get_posts( $args );
	return $found ? (int) ( is_object( $found[0] ) ? $found[0]->ID : $found[0] ) : 0;
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
	if ( function_exists( 'pll_save_post_translations' ) && count( cemeti_page_languages() ) > 1 ) {
		foreach ( $translations as $group ) {
			$group = array_filter( $group );
			if ( count( $group ) > 1 ) {
				pll_save_post_translations( $group );
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
