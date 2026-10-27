<?php
/**
 * Overlay menu model and rendering helpers.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default overlay menu, as in the design: two columns, each a list of groups.
 * Groups are separated by one blank line. Names stay in English in both
 * languages. Filterable by child/site code.
 *
 * Each entry: label, path, optional children. A group may set 'bottom' => true
 * to sit at the bottom of its column (Contact Us, above the address).
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function cemeti_default_menu() {
	$menu = array(
		'left'  => array(
			array(
				'items' => array(
					array( 'label' => 'Home', 'path' => '' ),
					array( 'label' => 'About', 'path' => 'about' ),
				),
			),
			array(
				'items' => array(
					array(
						'label'    => 'Exhibition',
						'path'     => 'exhibition',
						'children' => array(
							array( 'label' => 'Solo', 'path' => 'exhibition/?type=solo' ),
							array( 'label' => 'Group', 'path' => 'exhibition/?type=group' ),
						),
					),
				),
			),
			array( 'items' => array( array( 'label' => 'Residency', 'path' => 'residency' ) ) ),
			array(
				'items' => array(
					array( 'label' => 'Public Program', 'path' => 'public-program' ),
					array( 'label' => 'Open Studio', 'path' => 'open-studio' ),
				),
			),
			array( 'items' => array( array( 'label' => 'Open Call', 'path' => 'open-call' ) ) ),
		),
		'right' => array(
			array(
				'items' => array(
					array( 'label' => 'Journal', 'path' => 'journal' ),
					array( 'label' => 'Open Archive', 'path' => 'open-archive' ),
				),
			),
			array( 'items' => array( array( 'label' => 'Support Us', 'path' => 'support-us' ) ) ),
			array(
				'bottom' => true,
				'items'  => array( array( 'label' => 'Contact Us', 'path' => 'contact-us' ) ),
			),
		),
	);

	/**
	 * Filters the default Cemeti overlay menu.
	 *
	 * @param array $menu Two-column menu definition.
	 */
	return apply_filters( 'cemeti_default_menu', $menu );
}

/**
 * Is this menu path the page being viewed? Entries with a query string never match.
 *
 * @param string $path Menu path.
 * @return bool
 */
function cemeti_menu_is_current( $path ) {
	if ( false !== strpos( $path, '?' ) ) {
		return false;
	}
	$request = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$target  = (string) wp_parse_url( cemeti_url( $path ), PHP_URL_PATH );
	return trim( $request, '/' ) === trim( $target, '/' );
}

/**
 * Render the groups of one menu column.
 *
 * @param array $groups Menu groups.
 */
function cemeti_render_menu_items( array $groups ) {
	foreach ( $groups as $group ) {
		$class = 'cemeti-menu__list' . ( ! empty( $group['bottom'] ) ? ' cemeti-menu__list--bottom' : '' );
		echo '<ul class="' . esc_attr( $class ) . '">';
		foreach ( $group['items'] as $item ) {
			$current = cemeti_menu_is_current( $item['path'] ) ? ' aria-current="page"' : '';
			echo '<li class="cemeti-menu__item"><a href="' . esc_url( cemeti_url( $item['path'] ) ) . '"' . $current . '>' . esc_html( $item['label'] ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! empty( $item['children'] ) ) {
				echo '<ul class="cemeti-menu__sub">';
				foreach ( $item['children'] as $child ) {
					echo '<li><a href="' . esc_url( cemeti_url( $child['path'] ) ) . '">' . esc_html( $child['label'] ) . '</a></li>';
				}
				echo '</ul>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}

/**
 * Accept only Google Maps embed/share URLs for the map iframe or route link.
 *
 * @param string $url Candidate URL.
 * @return string Empty string when the URL is not an allowed Google Maps URL.
 */
function cemeti_validate_maps_url( $url ) {
	$url   = esc_url_raw( trim( (string) $url ), array( 'https' ) );
	$parts = $url ? wp_parse_url( $url ) : array();
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$path  = $parts['path'] ?? '';

	$allowed_hosts = array( 'www.google.com', 'google.com', 'maps.google.com', 'maps.app.goo.gl', 'goo.gl' );
	if ( ! in_array( $host, $allowed_hosts, true ) ) {
		return '';
	}
	if ( in_array( $host, array( 'www.google.com', 'google.com' ), true ) && 0 !== strpos( $path, '/maps' ) ) {
		return '';
	}
	return $url;
}
