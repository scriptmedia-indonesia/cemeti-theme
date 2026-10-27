<?php
/**
 * Registers Cemeti post types and taxonomies from config/post-types.php and
 * config/taxonomies.php. Skipped when CEMETI_USE_CORE_REGISTRAR is true, in which
 * case Scriptmedia Core registers them from the same arrays (see core-bridge.php).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Should this theme register the content model itself?
 *
 * @return bool
 */
function cemeti_registers_content_model() {
	return ! ( defined( 'CEMETI_USE_CORE_REGISTRAR' ) && CEMETI_USE_CORE_REGISTRAR );
}

/**
 * Label set for a post type or taxonomy.
 *
 * @param string $singular Singular name.
 * @param string $plural   Plural name.
 * @return array<string, string>
 */
function cemeti_labels( $singular, $plural ) {
	return array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'add_new_item'       => 'Add ' . $singular,
		'edit_item'          => 'Edit ' . $singular,
		'new_item'           => 'New ' . $singular,
		'view_item'          => 'View ' . $singular,
		'search_items'       => 'Search ' . $plural,
		'not_found'          => 'No ' . strtolower( $plural ) . ' found',
		'not_found_in_trash' => 'No ' . strtolower( $plural ) . ' found in Trash',
		'all_items'          => $plural,
		'menu_name'          => $plural,
	);
}

/**
 * Register post types and taxonomies.
 */
function cemeti_register_content_model() {
	if ( ! cemeti_registers_content_model() ) {
		return;
	}

	foreach ( cemeti_config( 'taxonomies' ) as $taxonomy => $args ) {
		register_taxonomy(
			$taxonomy,
			isset( $args['post_types'] ) ? $args['post_types'] : array(),
			array(
				'labels'            => cemeti_labels( $args['singular'], $args['plural'] ),
				'public'            => true,
				'hierarchical'      => ! empty( $args['hierarchical'] ),
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => $args['slug'] ),
			)
		);
	}

	foreach ( cemeti_config( 'post-types' ) as $post_type => $args ) {
		register_post_type(
			$post_type,
			array(
				'labels'       => cemeti_labels( $args['singular'], $args['plural'] ),
				'public'       => true,
				'has_archive'  => $args['slug'],
				'rewrite'      => array( 'slug' => $args['slug'] ),
				'show_in_rest' => true,
				'menu_icon'    => isset( $args['icon'] ) ? $args['icon'] : 'dashicons-admin-post',
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'taxonomies'   => isset( $args['taxonomies'] ) ? $args['taxonomies'] : array(),
			)
		);
	}
}
add_action( 'init', 'cemeti_register_content_model' );

/**
 * Create the default terms once (editors can add more later).
 */
function cemeti_seed_terms() {
	if ( ! cemeti_registers_content_model() || get_option( 'cemeti_terms_seeded' ) === CEMETI_VERSION ) {
		return;
	}
	foreach ( cemeti_config( 'taxonomies' ) as $taxonomy => $args ) {
		foreach ( isset( $args['terms'] ) ? $args['terms'] : array() as $term ) {
			if ( ! term_exists( $term, $taxonomy ) ) {
				wp_insert_term( $term, $taxonomy );
			}
		}
	}
	update_option( 'cemeti_terms_seeded', CEMETI_VERSION );
}
add_action( 'init', 'cemeti_seed_terms', 20 );

/**
 * Flush rewrite rules once when the theme is activated.
 */
function cemeti_flush_on_switch() {
	cemeti_register_content_model();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'cemeti_flush_on_switch' );

/**
 * Let Polylang translate Cemeti content (separate ID and EN entries).
 *
 * @param array $types Translatable post types.
 * @return array
 */
function cemeti_polylang_post_types( $types ) {
	return array_merge( $types, array_fill_keys( array_keys( cemeti_config( 'post-types' ) ), 'cemeti' ) );
}
add_filter( 'pll_get_post_types', 'cemeti_polylang_post_types' );

/**
 * Let Polylang translate Cemeti taxonomies.
 *
 * @param array $taxonomies Translatable taxonomies.
 * @return array
 */
function cemeti_polylang_taxonomies( $taxonomies ) {
	return array_merge( $taxonomies, array_fill_keys( array_keys( cemeti_config( 'taxonomies' ) ), 'cemeti' ) );
}
add_filter( 'pll_get_taxonomies', 'cemeti_polylang_taxonomies' );
