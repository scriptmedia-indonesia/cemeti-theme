<?php
/**
 * Site Settings access. Values are stored per language (options "id" / "en").
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default values used until an editor fills Site Settings.
 * Taken from the design PDF; they are placeholders, not production content.
 *
 * @return array<string, string>
 */
function cemeti_option_defaults() {
	return array(
		'institution_name' => 'Cemeti – Institut untuk Seni dan Masyarakat',
		'site_description' => '',
		'address'          => "Jl. DI Panjaitan No.41, Mantrijeron, Kec. Mantrijeron\nKota Yogyakarta, Daerah Istimewa Yogyakarta 55143",
		'opening_hours'    => "Selasa – Sabtu\n10:00 – 17:00",
		'email'            => 'info@cemeti.art',
		'notify_email'     => '',
		'instagram_url'    => '',
		'youtube_url'      => '',
		'facebook_url'     => '',
		'route_url'        => '',
	);
}

/**
 * Read a Site Settings value for the active language, falling back to the
 * default language, then to the built-in default.
 *
 * Order per language: the theme's own Site Settings page (option
 * "cemeti_settings_{lang}"), then SCF/ACF get_field() when available.
 *
 * @param string $key Option key.
 * @return string
 */
function cemeti_option( $key ) {
	$langs = array_unique( array( cemeti_current_lang(), cemeti_default_lang() ) );

	foreach ( $langs as $lang ) {
		$stored = get_option( 'cemeti_settings_' . $lang, array() );
		$value  = is_array( $stored ) && isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
		if ( '' === trim( $value ) && function_exists( 'get_field' ) ) {
			$field = get_field( 'cemeti_' . $key, 'cemeti_settings_' . $lang );
			$value = is_scalar( $field ) ? (string) $field : '';
		}
		if ( '' !== trim( $value ) ) {
			return $value;
		}
	}

	$defaults = cemeti_option_defaults();
	return $defaults[ $key ] ?? '';
}
