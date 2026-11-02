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
 * A page keeps its Cemeti template even if its slug was changed or Polylang made it "about-2".
 *
 * @param string $template Template file chosen by WordPress.
 * @return string
 */
function cemeti_page_template_by_key( $template ) {
	if ( ! is_page() ) {
		return $template;
	}
	$key = (string) get_post_meta( get_queried_object_id(), '_cemeti_page_key', true );
	if ( '' === $key || ! array_key_exists( $key, cemeti_default_pages() ) ) {
		return $template;
	}
	$found = locate_template( array( 'page-' . $key . '.php' ) );
	return $found ? $found : $template;
}
add_filter( 'template_include', 'cemeti_page_template_by_key' );
