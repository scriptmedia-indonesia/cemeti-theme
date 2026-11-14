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
function cemeti_option_defaults( $lang = '' ) {
	$english = 'en' === $lang;
	return array(
		'institution_name' => $english ? 'Cemeti – Institute for Art and Society' : 'Cemeti – Institut untuk Seni dan Masyarakat',
		'site_description' => '',
		'address'          => $english ? "Jl. DI Panjaitan No.41, Mantrijeron, Mantrijeron District\nYogyakarta City, Special Region of Yogyakarta 55143" : "Jl. DI Panjaitan No.41, Mantrijeron, Kec. Mantrijeron\nKota Yogyakarta, Daerah Istimewa Yogyakarta 55143",
		'opening_hours'    => $english ? "Tuesday – Saturday\n10:00 – 17:00" : "Selasa – Sabtu\n10:00 – 17:00",
		'email'            => 'info@cemeti.art',
		'notify_email'     => '',
		'instagram_url'    => 'https://www.instagram.com/cemeti.institute/',
		'youtube_url'      => 'https://www.youtube.com/channel/UCfLOv3JECGPQVkw_ivjE6UQ',
		'facebook_url'     => 'https://www.facebook.com/cemeti.institut',
		'route_url'        => 'https://maps.app.goo.gl/gbRc1JE67wMU1KcL9',
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
	// Words that belong to one language (name, address, hours) never borrow the other language's text: an empty
	// field shows the built-in text of the language being viewed.
	if ( in_array( $key, array( 'institution_name', 'address', 'opening_hours' ), true ) ) {
		$langs = array( cemeti_current_lang() );
	}

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

	$defaults = cemeti_option_defaults( cemeti_current_lang() );
	return $defaults[ $key ] ?? '';
}
