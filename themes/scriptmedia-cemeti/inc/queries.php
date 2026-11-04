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

	if ( $query->is_post_type_archive( 'journal' ) || $query->is_tax( 'journal_category' ) ) {
		$query->set( 'posts_per_page', 20 );
	}

	// Menu links: /exhibition/?type=solo|group and /residency/?type=regular|research.
	if ( ( $query->is_post_type_archive( 'exhibition' ) || $query->is_post_type_archive( 'residency' ) ) && isset( $_GET['type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$taxonomy = $query->is_post_type_archive( 'exhibition' ) ? 'exhibition_type' : 'residency_type';
		$slug     = sanitize_title( wp_unslash( $_GET['type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$slug     = 'reguler' === $slug ? 'regular' : $slug; // The term was called Reguler before 0.8.
		if ( '' !== $slug ) {
			// Polylang names the copy of a term in the second language "solo-en": accept both.
			$query->set( 'tax_query', array( array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => array( $slug, $slug . '-en', $slug . '-id' ) ) ) );
		}
	}

	if ( $query->is_search() ) {
		$query->set( 'post_type', array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'page' ) );
		$query->set( 'posts_per_page', 20 );
	}
}
add_action( 'pre_get_posts', 'cemeti_pre_get_posts' );

/**
 * Title of the entries for the A–Z list (programs only, newest query-free).
 *
 * @return array<int, array{title:string,url:string}>
 */
function cemeti_archive_entries() {
	$cache_key = 'cemeti_az_' . cemeti_current_lang() . '_' . (int) get_option( 'cemeti_content_version', 0 );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$entries = array();
	$posts   = get_posts(
		array(
			'post_type'      => array( 'exhibition', 'residency', 'public_program', 'open_studio' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	foreach ( $posts as $post ) {
		$entries[] = array( 'title' => get_the_title( $post ), 'url' => get_permalink( $post ) );
	}
	set_transient( $cache_key, $entries, DAY_IN_SECONDS );
	return $entries;
}


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
