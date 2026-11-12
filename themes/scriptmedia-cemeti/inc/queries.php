<?php
/**
 * Main-query tweaks for archives and search.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order dated archives by start date (newest first), keeping entries without a date.
 *
 * @param WP_Query $query Query.
 * @param string   $start Start meta key without prefix ('' = use end key only).
 * @param string   $end   End meta key without prefix.
 */
function cemeti_order_by_dates( $query, $start, $end ) {
	$key = 'cemeti_' . ( $start ? $start : $end );
	$query->set(
		'meta_query',
		array(
			'relation' => 'OR',
			'by_date'  => array( 'key' => $key, 'compare' => 'EXISTS', 'type' => 'NUMERIC' ),
			'no_date'  => array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
		)
	);
	$query->set( 'orderby', array( 'by_date' => 'DESC', 'date' => 'DESC' ) );
}

/**
 * Adjust the main query.
 *
 * @param WP_Query $query Query.
 */
function cemeti_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$dated = array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call' );
	if ( $query->is_post_type_archive( $dated ) ) {
		list( $start, $end ) = cemeti_date_keys( $query->get( 'post_type' ) );
		cemeti_order_by_dates( $query, $start, $end );
		$query->set( 'posts_per_page', 12 );
	}

	// The second language's lists also show the entries that have no version in that language yet (the default
	// language's), so the list is as complete as the English one; entries that do have one are not shown twice.
	$listed = cemeti_listed_post_type( $query );
	if ( $listed && cemeti_current_lang() !== cemeti_default_lang() && function_exists( 'pll_get_post_language' ) ) {
		$shadowed = cemeti_listing_shadowed_ids( $listed, cemeti_current_lang() );
		if ( $shadowed ) {
			$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $shadowed ) );
		}
	}

	// Journal is the collection of writings and essays to read on the web: News posts live in the Open Archive.
	if ( $query->is_post_type_archive( 'journal' ) ) {
		$query->set( 'tax_query', array_merge( (array) $query->get( 'tax_query' ), cemeti_journal_tax_query() ) );
	}

	if ( $query->is_post_type_archive( 'journal' ) || $query->is_tax( 'journal_category' ) ) {
		$query->set( 'posts_per_page', 20 );
	}

	// Menu links: /exhibition/?type=solo|group and /residency/?type=regular|research.
	if ( ( $query->is_post_type_archive( 'exhibition' ) || $query->is_post_type_archive( 'residency' ) ) && isset( $_GET['type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$taxonomy = $query->is_post_type_archive( 'exhibition' ) ? 'exhibition_type' : 'residency_type';
		$slug     = sanitize_title( wp_unslash( $_GET['type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$slug     = 'reguler' === $slug ? 'regular' : $slug; // The term was called Reguler before 0.8.
		if ( '' !== $slug ) {
			// Polylang names the copy of a term in the second language "solo-en": accept both, and every translation
			// of the term, so /id/residency/?type=research finds the Indonesian Research entries.
			$slugs = array( $slug, $slug . '-en', $slug . '-id' );
			$ids   = array();
			foreach ( $slugs as $candidate ) {
				$found = get_term_by( 'slug', $candidate, $taxonomy );
				if ( $found ) {
					$ids[] = (int) $found->term_id;
					if ( function_exists( 'pll_get_term_translations' ) ) {
						$ids = array_merge( $ids, array_map( 'intval', array_values( (array) pll_get_term_translations( $found->term_id ) ) ) );
					}
				}
			}
			$query->set( 'tax_query', array( $ids ? array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array_values( array_unique( $ids ) ) ) : array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slugs ) ) );
		}
	}

	if ( $query->is_search() ) {
		$query->set( 'post_type', array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'page' ) );
		$query->set( 'posts_per_page', 20 );
	}
}
add_action( 'pre_get_posts', 'cemeti_pre_get_posts' );

/**
 * People for the About page, split into Advisory Board and Team.
 *
 * A group is recognised by its term, whatever Polylang made of the slug ("advisory-board-en") or the name
 * ("Dewan Penasihat"). A person with no group, or an unknown one, is shown under Team rather than nowhere.
 * Polylang hides entries that have no language; when the current language has nobody at all, people of every
 * language are shown, so a freshly added person never "disappears".
 *
 * @return array<string, WP_Post[]> Keys "advisory-board" and "team".
 */
function cemeti_people_by_group() {
	$args = array(
		'post_type'      => 'person',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	);
	if ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
		$args['lang'] = pll_current_language();
	}
	$people = get_posts( array_merge( $args, array( 'suppress_filters' => false ) ) );
	if ( ! $people && function_exists( 'pll_languages_list' ) ) {
		$people = get_posts( array_merge( $args, array( 'suppress_filters' => false, 'lang' => '' ) ) );
	}
	$groups = array( 'advisory-board' => array(), 'team' => array() );
	foreach ( $people as $person ) {
		$groups[ cemeti_person_group_key( $person ) ][] = $person;
	}
	return $groups;
}


/**
 * Group of one person: "advisory-board" or "team".
 *
 * The person's own group term decides. A translation often has no term (Polylang does not copy it unless
 * "Group" is synchronised), so then the term of the other-language versions is used, and finally the
 * Title / role text ("Advisory Board", "Dewan Penasihat"). Nothing found = Team.
 *
 * @param WP_Post $person Person.
 * @return string
 */
function cemeti_person_group_key( $person ) {
	$pattern = '/advis|penasihat|penasehat|dewan/i';
	$ids     = array( $person->ID );
	if ( function_exists( 'pll_get_post_translations' ) ) {
		$ids = array_merge( $ids, array_map( 'intval', array_values( (array) pll_get_post_translations( $person->ID ) ) ) );
	}
	foreach ( array_unique( $ids ) as $id ) {
		$terms = get_the_terms( $id, 'person_group' );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( preg_match( $pattern, $term->slug . ' ' . $term->name ) ) {
				return 'advisory-board';
			}
		}
		if ( is_array( $terms ) && $terms && $id === $person->ID ) {
			return 'team'; // an explicit, different group wins over the guesses below
		}
	}
	return preg_match( $pattern, (string) cemeti_get_field( 'role', $person->ID ) ) ? 'advisory-board' : 'team';
}

/**
 * Team members grouped by role, in the order the roles first appear: "Staf Teknis" -> Joko, Rizky.
 * The Team block of the About page writes each role in bold with its names underneath.
 *
 * @param WP_Post[] $people People of the group, already in display order.
 * @return array<int, array{role:string,names:string[]}>
 */
function cemeti_group_people_by_role( array $people ) {
	$roles = array();
	foreach ( $people as $person ) {
		$role = trim( (string) cemeti_get_field( 'role', $person->ID ) );
		$key  = strtolower( $role );
		if ( ! isset( $roles[ $key ] ) ) {
			$roles[ $key ] = array( 'role' => $role, 'names' => array() );
		}
		$roles[ $key ]['names'][] = get_the_title( $person );
	}
	return array_values( $roles );
}


/**
 * People list in the admin: Group, Title / role and Order columns, sorted by Order (the "Order" box in
 * Page Attributes) so the editor sees the order the About page uses. A lower number is shown higher; the
 * divisions of the Team block appear in the order of their first member.
 *
 * @param array $columns Columns.
 * @return array
 */
function cemeti_person_columns( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['cemeti_role']  = __( 'Title / role', 'scriptmedia-cemeti' );
			$out['cemeti_order'] = __( 'Order', 'scriptmedia-cemeti' );
		}
	}
	return $out;
}
add_filter( 'manage_person_posts_columns', 'cemeti_person_columns' );

/**
 * Cells of the People columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cemeti_person_column_value( $column, $post_id ) {
	if ( 'cemeti_role' === $column ) {
		echo esc_html( (string) cemeti_get_field( 'role', $post_id ) );
	} elseif ( 'cemeti_order' === $column ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}
add_action( 'manage_person_posts_custom_column', 'cemeti_person_column_value', 10, 2 );

/**
 * Make the Order column sortable and the default sort of the People list.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function cemeti_person_sortable( $columns ) {
	$columns['cemeti_order'] = 'menu_order';
	return $columns;
}
add_filter( 'manage_edit-person_sortable_columns', 'cemeti_person_sortable' );

/**
 * Default sort of the People list: by Order, then name.
 *
 * @param WP_Query $query Query.
 */
function cemeti_person_default_sort( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'person' !== $query->get( 'post_type' ) || $query->get( 'orderby' ) ) {
		return;
	}
	$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
}
add_action( 'pre_get_posts', 'cemeti_person_default_sort' );

/**
 * Comparable form of a type slug: "Reguler"/"regular", "research-en" and "research" are the same category.
 *
 * @param string $slug Term or query slug.
 * @return string
 */
function cemeti_type_key( $slug ) {
	$slug = preg_replace( '/-(en|id)$/', '', sanitize_title( (string) $slug ) );
	return 'reguler' === $slug ? 'regular' : $slug;
}

/**
 * The Residency category chosen with ?type= ('' for all).
 *
 * @return string
 */
function cemeti_residency_active_type() {
	return isset( $_GET['type'] ) ? cemeti_type_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
}

/**
 * The Cemeti post type a main query lists (archive pages), '' otherwise.
 *
 * @param WP_Query $query Query.
 * @return string
 */
function cemeti_listed_post_type( $query ) {
	foreach ( array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal' ) as $type ) {
		if ( $query->is_post_type_archive( $type ) ) {
			return $type;
		}
	}
	return '';
}

/**
 * Default-language entries of a type that already have a version in $lang: linked as its translation, or the same event
 * (same dates) imported as a separate post. They are left out of that language's list so nothing shows twice.
 *
 * @param string $type Post type.
 * @param string $lang Current (second) language code.
 * @return int[]
 */
function cemeti_listing_shadowed_ids( $type, $lang ) {
	static $cache = array();
	if ( isset( $cache[ $type . $lang ] ) ) {
		return $cache[ $type . $lang ];
	}
	// Worked out once and remembered (every page of the list, and every scroll step, asks for it); any save, trash or
	// delete of an entry makes it be worked out again.
	$key    = 'cemeti_shadow_' . (int) get_option( 'cemeti_list_version', 0 ) . '_' . $type . '_' . $lang;
	$stored = get_transient( $key );
	if ( is_array( $stored ) ) {
		$cache[ $type . $lang ] = array_map( 'intval', $stored );
		return $cache[ $type . $lang ];
	}
	$all = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'lang' => '', 'suppress_filters' => false ) );
	$all = array_map( 'intval', $all );
	if ( function_exists( 'update_meta_cache' ) ) {
		update_meta_cache( 'post', $all );
	}
	$default = cemeti_default_lang();
	$mine    = array();
	$theirs  = array();
	foreach ( $all as $post_id ) {
		$code = cemeti_lang_code( (string) pll_get_post_language( $post_id ) );
		if ( $code === $lang ) {
			$mine[ $post_id ] = get_post_meta( $post_id, 'cemeti_start_date', true ) . '|' . get_post_meta( $post_id, 'cemeti_end_date', true );
		} elseif ( $code === $default ) {
			$theirs[] = $post_id;
		}
	}
	$dates  = array_filter( array_unique( array_values( $mine ) ), static function ( $key ) {
		return '|' !== $key;
	} );
	$result = array();
	foreach ( $theirs as $post_id ) {
		$linked = function_exists( 'pll_get_post_translations' ) ? (array) pll_get_post_translations( $post_id ) : array();
		$key    = get_post_meta( $post_id, 'cemeti_start_date', true ) . '|' . get_post_meta( $post_id, 'cemeti_end_date', true );
		if ( ( ! empty( $linked[ $lang ] ) && isset( $mine[ (int) $linked[ $lang ] ] ) ) || in_array( $key, $dates, true ) ) {
			$result[] = $post_id;
		}
	}
	set_transient( $key, $result, HOUR_IN_SECONDS );
	$cache[ $type . $lang ] = $result;
	return $result;
}

/**
 * Forget the remembered lists (see cemeti_listing_shadowed_ids) when an entry changes.
 */
function cemeti_listing_cache_clear() {
	update_option( 'cemeti_list_version', (int) get_option( 'cemeti_list_version', 0 ) + 1, false );
}
add_action( 'save_post', 'cemeti_listing_cache_clear' );
add_action( 'deleted_post', 'cemeti_listing_cache_clear' );
add_action( 'trashed_post', 'cemeti_listing_cache_clear' );
add_action( 'pll_save_post_translations', 'cemeti_listing_cache_clear' );

/**
 * Polylang: do not filter a second-language list by language (see cemeti_pre_get_posts: the default language's entries
 * without a version there are shown too). Only that Polylang filter exists for this; without it nothing changes.
 *
 * @param bool     $excluded Whether the query is left out of language filtering.
 * @param WP_Query $query    Query.
 * @return bool
 */
function cemeti_polylang_unfilter_lists( $excluded, $query ) {
	if ( $excluded || is_admin() || ! $query->is_main_query() || ! cemeti_listed_post_type( $query ) ) {
		return $excluded;
	}
	return cemeti_current_lang() !== cemeti_default_lang() ? true : $excluded;
}
add_filter( 'pll_filter_query_excluded_query', 'cemeti_polylang_unfilter_lists', 10, 2 );

/**
 * Term IDs of the Journal category "News" and of its copy in the other language.
 *
 * @return int[]
 */
function cemeti_news_term_ids() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$ids = array();
	foreach ( array( 'news', 'news-en', 'news-id' ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'journal_category' );
		if ( $term ) {
			$ids[] = (int) $term->term_id;
			if ( function_exists( 'pll_get_term_translations' ) ) {
				$ids = array_merge( $ids, array_map( 'intval', array_values( (array) pll_get_term_translations( $term->term_id ) ) ) );
			}
		}
	}
	$cache = array_values( array_unique( array_filter( $ids ) ) );
	return $cache;
}

/**
 * tax_query part that leaves News out of Journal lists.
 *
 * @return array
 */
function cemeti_journal_tax_query() {
	$ids = cemeti_news_term_ids();
	return $ids ? array( array( 'taxonomy' => 'journal_category', 'field' => 'term_id', 'terms' => $ids, 'operator' => 'NOT IN' ) ) : array();
}
