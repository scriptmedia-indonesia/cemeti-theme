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
 * Default overlay menu, grouped in two columns as in the design.
 * Names stay in English in both languages. Filterable by child/site code.
 *
 * Each entry: label, path, optional children.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function cemeti_default_menu() {
	$menu = array(
		'left'  => array(
			array( 'label' => 'Home', 'path' => '' ),
			array( 'label' => 'About', 'path' => 'about' ),
			array(
				'label'    => 'Exhibition',
				'path'     => 'exhibition',
				'children' => array(
					array( 'label' => 'Solo', 'path' => 'exhibition/?type=solo' ),
					array( 'label' => 'Group', 'path' => 'exhibition/?type=group' ),
				),
			),
			array( 'label' => 'Residency', 'path' => 'residency' ),
			array( 'label' => 'Public Program', 'path' => 'public-program' ),
			array( 'label' => 'Open Studio', 'path' => 'open-studio' ),
			array( 'label' => 'Open Call', 'path' => 'open-call' ),
		),
		'right' => array(
			array( 'label' => 'Journal', 'path' => 'journal' ),
			array( 'label' => 'Open Archive', 'path' => 'open-archive' ),
			array( 'label' => 'Support Us', 'path' => 'support-us' ),
			array( 'label' => 'Contact Us', 'path' => 'contact-us' ),
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
 * Render a list of menu entries.
 *
 * @param array $items Menu entries.
 */
function cemeti_render_menu_items( array $items ) {
	echo '<ul class="cemeti-menu__list">';
	foreach ( $items as $item ) {
		echo '<li class="cemeti-menu__item"><a href="' . esc_url( cemeti_url( $item['path'] ) ) . '">' . esc_html( $item['label'] ) . '</a>';
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
