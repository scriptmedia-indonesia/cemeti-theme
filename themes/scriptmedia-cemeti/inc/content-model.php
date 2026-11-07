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
				'public'            => ! isset( $args['public'] ) || $args['public'],
				'show_ui'           => true,
				'hierarchical'      => ! empty( $args['hierarchical'] ),
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => ( ! isset( $args['public'] ) || $args['public'] ) ? array( 'slug' => $args['slug'] ) : false,
			)
		);
	}

	foreach ( cemeti_config( 'post-types' ) as $post_type => $args ) {
		$is_public = ! isset( $args['public'] ) || $args['public'];
		register_post_type(
			$post_type,
			array(
				'labels'       => cemeti_labels( $args['singular'], $args['plural'] ),
				'public'       => $is_public,
				'show_ui'      => true,
				'has_archive'  => $is_public ? $args['slug'] : false,
				'rewrite'      => $is_public ? array( 'slug' => $args['slug'] ) : false,
				'show_in_rest' => true,
				'menu_icon'    => isset( $args['icon'] ) ? $args['icon'] : 'dashicons-admin-post',
				'supports'     => isset( $args['supports'] ) ? $args['supports'] : array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
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
		// A term that was renamed in a later version keeps its entries: rename it instead of adding a second one.
		foreach ( isset( $args['renamed'] ) ? $args['renamed'] : array() as $old => $new ) {
			$found = term_exists( $old, $taxonomy );
			if ( $found && ! term_exists( $new, $taxonomy ) ) {
				wp_update_term( (int) $found['term_id'], $taxonomy, array( 'name' => $new, 'slug' => sanitize_title( $new ) ) );
			}
		}
		$descriptions = isset( $args['descriptions'] ) ? $args['descriptions'] : array();
		foreach ( isset( $args['terms'] ) ? $args['terms'] : array() as $term ) {
			$found = term_exists( $term, $taxonomy );
			if ( ! $found ) {
				wp_insert_term( $term, $taxonomy, isset( $descriptions[ $term ] ) ? array( 'description' => $descriptions[ $term ] ) : array() );
			} elseif ( isset( $descriptions[ $term ] ) ) {
				$existing = get_term( (int) $found['term_id'], $taxonomy );
				if ( $existing && ! is_wp_error( $existing ) && '' === trim( (string) $existing->description ) ) {
					wp_update_term( (int) $found['term_id'], $taxonomy, array( 'description' => $descriptions[ $term ] ) );
				}
			}
		}
	}
	update_option( 'cemeti_terms_seeded', CEMETI_VERSION );
}
add_action( 'init', 'cemeti_seed_terms', 20 );

/**
 * Indonesian (or other-language) description of a default term: the text of descriptions_id for "id", the
 * English text otherwise.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $term     Term name.
 * @param string $lang     Language code ("id", "en" ...).
 * @return string
 */
function cemeti_default_term_description( $taxonomy, $term, $lang ) {
	$args = cemeti_config( 'taxonomies' );
	$args = isset( $args[ $taxonomy ] ) ? $args[ $taxonomy ] : array();
	$key  = 'id' === cemeti_lang_code( $lang ) ? 'descriptions_id' : 'descriptions';
	if ( isset( $args[ $key ][ $term ] ) ) {
		return $args[ $key ][ $term ];
	}
	return isset( $args['descriptions'][ $term ] ) ? $args['descriptions'][ $term ] : '';
}

/**
 * With Polylang, make the second language's copy of every default term (Regular / Research, Solo / Group, News ...),
 * linked as translations. Without it the post editor of an Indonesian entry offers no terms at all, so a Research
 * residency could not be translated. Runs once per version and set of languages, in the admin.
 */
function cemeti_seed_term_translations() {
	if ( ! cemeti_registers_content_model() || ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_set_term_language' ) || ! function_exists( 'pll_save_term_translations' ) ) {
		return;
	}
	$langs = array_values( array_map( 'strval', (array) pll_languages_list() ) );
	if ( count( $langs ) < 2 ) {
		return;
	}
	$sig = CEMETI_VERSION . '|' . implode( ',', $langs );
	if ( get_option( 'cemeti_term_tr_sig' ) === $sig ) {
		return;
	}
	$default = function_exists( 'pll_default_language' ) && pll_default_language() ? (string) pll_default_language() : $langs[0];
	foreach ( cemeti_config( 'taxonomies' ) as $taxonomy => $args ) {
		foreach ( isset( $args['terms'] ) ? $args['terms'] : array() as $term ) {
			$found = term_exists( $term, $taxonomy );
			if ( ! $found ) {
				continue;
			}
			$base         = (int) ( is_array( $found ) ? $found['term_id'] : $found );
			$translations = function_exists( 'pll_get_term_translations' ) ? (array) pll_get_term_translations( $base ) : array();
			if ( function_exists( 'pll_get_term_language' ) && ! pll_get_term_language( $base ) ) {
				pll_set_term_language( $base, $default );
			}
			$group = array_merge( array( $default => $base ), $translations );
			foreach ( $langs as $lang ) {
				if ( ! empty( $group[ $lang ] ) ) {
					continue;
				}
				$new  = array( 'slug' => sanitize_title( $term ) . '-' . $lang, 'description' => cemeti_default_term_description( $taxonomy, $term, $lang ) );
				$made = function_exists( 'pll_insert_term' ) ? pll_insert_term( $term, $taxonomy, $lang, $new ) : wp_insert_term( $term, $taxonomy, $new );
				if ( is_wp_error( $made ) || empty( $made['term_id'] ) ) {
					continue;
				}
				pll_set_term_language( (int) $made['term_id'], $lang );
				$group[ $lang ] = (int) $made['term_id'];
			}
			pll_save_term_translations( $group );
		}
	}
	update_option( 'cemeti_term_tr_sig', $sig );
}
add_action( 'admin_init', 'cemeti_seed_term_translations', 30 );
add_action( 'after_switch_theme', 'cemeti_seed_term_translations', 30 );

/**
 * An entry takes the term of its own language (the Indonesian Research residency carries the Indonesian
 * "Research"), whichever copy was ticked or imported.
 *
 * @param int $post_id Post ID.
 */
function cemeti_sync_terms_to_language( $post_id ) {
	if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_get_term' ) ) {
		return;
	}
	$lang = (string) pll_get_post_language( $post_id );
	if ( '' === $lang ) {
		return;
	}
	foreach ( array_keys( cemeti_config( 'taxonomies' ) ) as $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			continue;
		}
		$ids     = array();
		$changed = false;
		foreach ( $terms as $term ) {
			$mine = (int) pll_get_term( $term->term_id, $lang );
			if ( $mine && $mine !== (int) $term->term_id ) {
				$changed = true;
			}
			$ids[] = $mine ? $mine : (int) $term->term_id;
		}
		if ( $changed ) {
			wp_set_object_terms( $post_id, array_values( array_unique( $ids ) ), $taxonomy );
		}
	}
}
add_action( 'save_post', 'cemeti_sync_terms_to_language', 40 );

/**
 * Which default term a term is, whatever its language: "Reguler"/"Regular" -> Regular, "Research"/"Riset" -> Research.
 *
 * @param WP_Term $term Term.
 * @return string The default term's name, or the term's own name when it is not a default one.
 */
function cemeti_term_key( $term ) {
	$text = strtolower( $term->name . ' ' . $term->slug );
	if ( preg_match( '/regul/', $text ) ) {
		return 'Regular';
	}
	if ( preg_match( '/research|riset|penelitian/', $text ) ) {
		return 'Research';
	}
	return $term->name;
}

/**
 * Terms for the intro of the Residency page: every default term (Regular, Research) exactly once, in order.
 * The term of the current language when there is one, else the other language's term, so "Research" never
 * disappears from the Indonesian page just because its translation was not made yet.
 *
 * @param string $taxonomy Taxonomy.
 * @return WP_Term[]
 */
function cemeti_intro_terms( $taxonomy ) {
	$mine = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'term_id' ) );
	$mine = is_wp_error( $mine ) || ! is_array( $mine ) ? array() : $mine;
	$all  = function_exists( 'pll_languages_list' ) ? get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'term_id', 'lang' => '' ) ) : array();
	$all  = is_wp_error( $all ) || ! is_array( $all ) ? array() : $all;
	$out  = array();
	foreach ( array_merge( $mine, $all ) as $term ) {
		$key = cemeti_term_key( $term );
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = $term; // The current language's terms come first.
		}
	}
	$args  = cemeti_config( 'taxonomies' );
	$order = isset( $args[ $taxonomy ]['terms'] ) ? $args[ $taxonomy ]['terms'] : array();
	$sorted = array();
	foreach ( $order as $name ) {
		if ( isset( $out[ $name ] ) ) {
			$sorted[] = $out[ $name ];
			unset( $out[ $name ] );
		}
	}
	return array_merge( $sorted, array_values( $out ) );
}

/**
 * Text under a term on the Residency page, in the current language: what the editor wrote, else the default text.
 *
 * @param WP_Term $term Term.
 * @return string
 */
function cemeti_term_description( $term ) {
	$text = trim( (string) $term->description );
	$lang = cemeti_current_lang();
	if ( '' === $text || ( 'id' === cemeti_lang_code( $lang ) && $text === cemeti_default_term_description( $term->taxonomy, $term->name, 'en' ) ) ) {
		$default = cemeti_default_term_description( $term->taxonomy, $term->name, $lang );
		return '' !== $default ? $default : $text;
	}
	return $text;
}

/**
 * Flush rewrite rules once after the theme is updated, so new types and slugs work
 * without a visit to Settings → Permalinks.
 */
function cemeti_flush_after_update() {
	if ( get_option( 'cemeti_rewrite_version' ) !== CEMETI_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'cemeti_rewrite_version', CEMETI_VERSION );
	}
}
add_action( 'init', 'cemeti_flush_after_update', 99 );

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
	$translatable = array();
	foreach ( cemeti_config( 'post-types' ) as $type => $args ) {
		if ( 'application' !== $type ) { // Applications are private records, not content.
			// Polylang wants the post type name as key AND value; any other value makes it ignore the type.
			$translatable[ $type ] = $type;
		}
	}
	return array_merge( $types, $translatable );
}
add_filter( 'pll_get_post_types', 'cemeti_polylang_post_types' );

/**
 * Let Polylang translate Cemeti taxonomies.
 *
 * @param array $taxonomies Translatable taxonomies.
 * @return array
 */
function cemeti_polylang_taxonomies( $taxonomies ) {
	$names = array_keys( cemeti_config( 'taxonomies' ) );
	return array_merge( $taxonomies, array_combine( $names, $names ) ); // Key and value are both the taxonomy name.
}
add_filter( 'pll_get_taxonomies', 'cemeti_polylang_taxonomies' );

/**
 * Solo or Group for an exhibition that has no type yet.
 *
 * The old site marks it in the title ("Pameran Tunggal …", "Pameran Kelompok …"); otherwise one artist means
 * Solo and two or more mean Group. Returns '' when nothing tells.
 *
 * @param int $post_id Exhibition ID.
 * @return string "Solo", "Group" or ''.
 */
function cemeti_infer_exhibition_type( $post_id ) {
	$title = (string) get_the_title( $post_id );
	if ( preg_match( '/^\s*(pameran\s+tunggal|solo\s+(exhibition|show))\b/i', $title ) ) {
		return 'Solo';
	}
	if ( preg_match( '/^\s*(pameran\s+kelompok|group\s+(exhibition|show))\b/i', $title ) ) {
		return 'Group';
	}
	$artists = array_filter( array_map( 'trim', preg_split( '/\R/', (string) cemeti_get_field( 'artists', $post_id ) ) ) );
	if ( 1 === count( $artists ) ) {
		return 'Solo';
	}
	return count( $artists ) > 1 ? 'Group' : '';
}

/**
 * Give an exhibition its Solo/Group term when the editor left it empty. A term chosen by hand is never changed.
 *
 * @param int $post_id Exhibition ID.
 * @return bool Whether a term was set.
 */
function cemeti_fill_exhibition_type( $post_id ) {
	if ( 'exhibition' !== get_post_type( $post_id ) || get_the_terms( $post_id, 'exhibition_type' ) ) {
		return false;
	}
	$type = cemeti_infer_exhibition_type( $post_id );
	if ( '' === $type ) {
		return false;
	}
	wp_set_object_terms( $post_id, array( $type ), 'exhibition_type' );
	return true;
}

/**
 * On save: fill the type of an exhibition left without one.
 *
 * @param int $post_id Post ID.
 */
function cemeti_fill_exhibition_type_on_save( $post_id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	cemeti_fill_exhibition_type( $post_id );
}
add_action( 'save_post', 'cemeti_fill_exhibition_type_on_save', 20 );

/**
 * Exhibitions that still have no Solo/Group term.
 *
 * @return int[]
 */
function cemeti_exhibitions_without_type() {
	$ids = get_posts(
		array(
			'post_type'      => 'exhibition',
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return array_values(
		array_filter(
			array_map( 'intval', $ids ),
			static function ( $id ) {
				return ! get_the_terms( $id, 'exhibition_type' );
			}
		)
	);
}

/**
 * Button on Settings → Cemeti: fill Solo/Group for every exhibition that has none.
 */
function cemeti_handle_fill_types() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'cemeti_fill_types' );
	$done = 0;
	foreach ( cemeti_exhibitions_without_type() as $id ) {
		$done += cemeti_fill_exhibition_type( $id ) ? 1 : 0;
	}
	wp_safe_redirect( add_query_arg( 'cemeti_types_filled', $done, admin_url( 'options-general.php?page=cemeti-settings' ) ) );
	exit;
}
add_action( 'admin_post_cemeti_fill_types', 'cemeti_handle_fill_types' );
