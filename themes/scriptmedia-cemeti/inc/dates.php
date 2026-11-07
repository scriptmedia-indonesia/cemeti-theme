<?php
/**
 * Date helpers. Dates are stored as Ymd strings in the site timezone.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Month names per language.
 *
 * @param string $lang Language slug.
 * @return string[] Index 1–12.
 */
function cemeti_month_names( $lang ) {
	$names = array(
		'en' => array( 1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
		'id' => array( 1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember' ),
	);
	return $names[ $lang ] ?? $names['en'];
}

/**
 * Format a date range the way the design shows it.
 *
 * Same day:            "30 January 2026"
 * Same month and year: "8 – 29 January 2026"
 * Same year:           "26 September – 15 November 2025"
 * Different years:     "27 November 2024 – 18 January 2025"
 *
 * @param string      $start Start date, Ymd.
 * @param string|null $end   Optional end date, Ymd. Empty means same as start.
 * @param string|null $lang  Language slug. Defaults to the active language.
 * @return string Empty string when the start date is invalid.
 */
function cemeti_format_date_range( $start, $end = '', $lang = null ) {
	$lang   = $lang ? $lang : cemeti_current_lang();
	$months = cemeti_month_names( $lang );
	$dash   = ' – ';

	$parse = static function ( $ymd ) {
		if ( ! is_string( $ymd ) || ! preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $ymd, $m ) ) {
			return null;
		}
		if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return array( (int) $m[1], (int) $m[2], (int) $m[3] );
	};

	$s = $parse( $start );
	if ( null === $s ) {
		return '';
	}
	$e = $end ? $parse( $end ) : null;

	if ( null === $e || $e === $s || $end < $start ) {
		return sprintf( '%d %s %d', $s[2], $months[ $s[1] ], $s[0] );
	}
	if ( $s[0] === $e[0] && $s[1] === $e[1] ) {
		return sprintf( '%d%s%d %s %d', $s[2], $dash, $e[2], $months[ $e[1] ], $e[0] );
	}
	if ( $s[0] === $e[0] ) {
		return sprintf( '%d %s%s%d %s %d', $s[2], $months[ $s[1] ], $dash, $e[2], $months[ $e[1] ], $e[0] );
	}
	return sprintf( '%d %s %d%s%d %s %d', $s[2], $months[ $s[1] ], $s[0], $dash, $e[2], $months[ $e[1] ], $e[0] );
}

/**
 * Weekday names per language, Sunday first.
 *
 * @param string $lang Language slug.
 * @return string[] Index 0 (Sunday) – 6.
 */
function cemeti_weekday_names( $lang ) {
	$names = array(
		'en' => array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
		'id' => array( 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu' ),
	);
	return $names[ $lang ] ?? $names['en'];
}

/**
 * One day with its weekday, as on Public Program pages: "Jumat, 30 Januari 2026".
 * A range is written as usual (no weekday).
 *
 * @param string      $start Start date, Ymd.
 * @param string|null $end   Optional end date, Ymd.
 * @param string|null $lang  Language slug. Defaults to the active language.
 * @return string
 */
function cemeti_format_event_when( $start, $end = '', $lang = null ) {
	$lang = $lang ? $lang : cemeti_current_lang();
	if ( ! is_string( $start ) || ! preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $start, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return '';
	}
	if ( $end && $end > $start ) {
		return cemeti_format_date_range( $start, $end, $lang );
	}
	$weekday = (int) gmdate( 'w', gmmktime( 0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] ) );
	$names   = cemeti_weekday_names( $lang );
	return $names[ $weekday ] . ', ' . cemeti_format_date_range( $start, '', $lang );
}
