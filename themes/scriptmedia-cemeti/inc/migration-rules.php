<?php
/**
 * Pure helpers for the "Cemeti rules" converter mode: sort a post by its old
 * categories, detect its language, and read event dates from its text.
 * No WordPress calls except cemeti_config(), so they are easy to test.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category slug without a trailing "_" (the old site's marker for English twins).
 *
 * @param string $slug Slug.
 * @return string
 */
function cemeti_rule_slug( $slug ) {
	return rtrim( strtolower( rawurldecode( (string) $slug ) ), '_' );
}

/**
 * Language of a post from its category slugs.
 *
 * @param string[] $slugs Category slugs.
 * @return string "id", "en" or "" when it cannot be told.
 */
function cemeti_rules_language( array $slugs ) {
	$rules = cemeti_config( 'migration-rules' );
	$id    = 0;
	$en    = 0;
	foreach ( $slugs as $slug ) {
		$key = cemeti_rule_slug( $slug );
		if ( in_array( $key, $rules['lang_id'], true ) ) {
			++$id;
		}
		if ( in_array( $key, $rules['lang_en'], true ) || '_' === substr( (string) $slug, -1 ) ) {
			++$en;
		}
	}
	if ( $id === $en ) {
		return '';
	}
	return $id > $en ? 'id' : 'en';
}

/**
 * Decide what a post becomes.
 *
 * @param array{title:string,cats:array<int,array{0:string,1:string}>} $post Title and categories as [slug, name].
 * @return array{skip:bool,type:string,term:string,lang:string,projects:string[]}
 */
function cemeti_rules_classify( array $post ) {
	$rules = cemeti_config( 'migration-rules' );
	$slugs = array();
	foreach ( $post['cats'] as $cat ) {
		$slugs[] = (string) $cat[0];
	}
	$keys   = array_map( 'cemeti_rule_slug', $slugs );
	$result = array( 'skip' => false, 'type' => 'journal', 'term' => '', 'lang' => cemeti_rules_language( $slugs ), 'projects' => array() );

	if ( preg_match( $rules['skip_title'], $post['title'] ) ) {
		$result['skip'] = true;
		return $result;
	}

	$matched = false;
	foreach ( $rules['types'] as $rule ) {
		if ( ! empty( $rule['flag'] ) && empty( $rules[ $rule['flag'] ] ) ) {
			continue;
		}
		if ( ! empty( $rule['cats_from'] ) ) {
			$rule['cats'] = $rules[ $rule['cats_from'] ];
		}
		$hit = ! empty( $rule['cats'] ) && array_intersect( $rule['cats'], $keys );
		if ( ! $hit && ! empty( $rule['title'] ) ) {
			$hit = (bool) preg_match( $rule['title'], $post['title'] );
		}
		if ( ! $hit && ! empty( $rule['cat_regex'] ) ) {
			foreach ( $keys as $key ) {
				if ( preg_match( $rule['cat_regex'], $key ) ) {
					$hit = true;
					break;
				}
			}
		}
		if ( ! $hit ) {
			continue;
		}
		$result['type'] = $rule['type'];
		$result['term'] = isset( $rule['term'] ) ? $rule['term'] : '';
		if ( '' === $result['term'] && ! empty( $rule['term_from_title'] ) ) {
			foreach ( $rule['term_from_title'] as $regex => $term ) {
				if ( preg_match( $regex, $post['title'] ) ) {
					$result['term'] = $term;
					break;
				}
			}
		}
		$matched = true;
		break;
	}

	if ( ! $matched ) {
		$result['term'] = $rules['journal']['default'];
		foreach ( $rules['journal'] as $term => $list ) {
			if ( is_array( $list ) && array_intersect( $list, $keys ) ) {
				$result['term'] = $term;
				break;
			}
		}
	}

	foreach ( $post['cats'] as $cat ) {
		if ( ! in_array( cemeti_rule_slug( $cat[0] ), $rules['not_project'], true ) ) {
			$name = trim( html_entity_decode( rtrim( (string) $cat[1], '_' ), ENT_QUOTES, 'UTF-8' ), " \t\"" );
			if ( '' !== $name ) {
				$result['projects'][] = $name;
			}
		}
	}
	return $result;
}

/**
 * Month number from an Indonesian or English month name or abbreviation.
 *
 * @param string $name Month text.
 * @return int 1–12, or 0.
 */
function cemeti_month_number( $name ) {
	static $map = array(
		'jan' => 1, 'januari' => 1, 'january' => 1, 'feb' => 2, 'februari' => 2, 'february' => 2, 'mar' => 3, 'maret' => 3, 'march' => 3,
		'apr' => 4, 'april' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6, 'juni' => 6, 'june' => 6, 'jul' => 7, 'juli' => 7, 'july' => 7,
		'agu' => 8, 'agt' => 8, 'agustus' => 8, 'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9,
		'okt' => 10, 'oktober' => 10, 'oct' => 10, 'october' => 10, 'nov' => 11, 'november' => 11, 'des' => 12, 'desember' => 12, 'dec' => 12, 'december' => 12,
	);
	return isset( $map[ strtolower( trim( $name, '. ' ) ) ] ) ? $map[ strtolower( trim( $name, '. ' ) ) ] : 0;
}

/**
 * Read the first date or date range in a text (Indonesian or English month names).
 * Handles "07 Juli - 07 Agustus 2011", "8 – 29 January 2026", "27 November 2024 – 18 January 2025",
 * "June 16th - July 24th 2021" and single dates like "30 Januari 2026".
 *
 * @param string $text      Post text or HTML; only the first 1500 characters are read.
 * @param string $reference Publish date (Ymd or Y-m-d …); years later than two years after it are rejected.
 * @return array{0:string,1:string}|null Start and end as Ymd, or null.
 */
function cemeti_extract_date_range( $text, $reference = '' ) {
	$text = html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $text ), ENT_QUOTES, 'UTF-8' );
	$text = substr( trim( preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) ), 0, 1500 );
	$max  = ( preg_match( '/^(\d{4})/', (string) $reference, $ref ) ? (int) $ref[1] : 2038 ) + ( '' === (string) $reference ? 0 : 2 );
	$mon  = '(?:jan(?:uari|uary)?|feb(?:ruari|ruary)?|mar(?:et|ch)?|apr(?:il)?|mei|may|jun[ei]?|jul[iy]?|agu(?:stus)?|agt|aug(?:ust)?|sep(?:t(?:ember)?|tember)?|okt(?:ober)?|oct(?:ober)?|nov(?:ember)?|des(?:ember)?|dec(?:ember)?)\.?';
	$dash = '(?:-|–|—|s\/d|s\.d\.?|sd|sampai|hingga|to|until|through)';
	$ord  = '(?:st|nd|rd|th)?';

	$build = static function ( $y1, $m1, $d1, $y2, $m2, $d2 ) use ( $max ) {
		$y1 = (int) $y1; $y2 = (int) $y2; $m1 = (int) $m1; $m2 = (int) $m2; $d1 = (int) $d1; $d2 = (int) $d2;
		if ( $y1 < 1988 || $y2 < 1988 || $y1 > $max || $y2 > $max || ! $m1 || ! $m2 || ! checkdate( $m1, $d1, $y1 ) || ! checkdate( $m2, $d2, $y2 ) ) {
			return null;
		}
		$start = sprintf( '%04d%02d%02d', $y1, $m1, $d1 );
		$end   = sprintf( '%04d%02d%02d', $y2, $m2, $d2 );
		return $end < $start ? array( $start, $start ) : array( $start, $end );
	};

	// "D [Mon] [YYYY] – D Mon YYYY" (day first).
	$re = '/\b(\d{1,2})' . $ord . '\s*(' . $mon . ')?\s*(\d{4})?\s*' . $dash . '\s*(\d{1,2})' . $ord . '\s*(' . $mon . ')\s*,?\s*(\d{4})\b/iu';
	if ( preg_match( $re, $text, $m ) ) {
		$m2 = cemeti_month_number( $m[5] );
		$m1 = '' !== $m[2] ? cemeti_month_number( $m[2] ) : $m2;
		$y2 = (int) $m[6];
		$y1 = '' !== $m[3] ? (int) $m[3] : $y2;
		if ( $m1 && $m2 ) {
			// A start month after the end month with no explicit year means the previous year.
			if ( '' === $m[3] && $m1 > $m2 ) {
				$y1 = $y2 - 1;
			}
			$range = $build( $y1, $m1, $m[1], $y2, $m2, $m[4] );
			if ( $range ) {
				return $range;
			}
		}
	}

	// "Mon D – [Mon] D, YYYY" (month first, English).
	$re = '/\b(' . $mon . ')\s+(\d{1,2})' . $ord . '\s*,?\s*(\d{4})?\s*' . $dash . '\s*(?:(' . $mon . ')\s+)?(\d{1,2})' . $ord . '\s*,?\s*(\d{4})\b/iu';
	if ( preg_match( $re, $text, $m ) ) {
		$m1 = cemeti_month_number( $m[1] );
		$m2 = '' !== $m[4] ? cemeti_month_number( $m[4] ) : $m1;
		$y2 = (int) $m[6];
		$y1 = '' !== $m[3] ? (int) $m[3] : $y2;
		$range = $m1 && $m2 ? $build( $y1, $m1, $m[2], $y2, $m2, $m[5] ) : null;
		if ( $range ) {
			return $range;
		}
	}

	// Single date: "30 Januari 2026" or "January 30th, 2026".
	if ( preg_match( '/\b(\d{1,2})' . $ord . '\s+(' . $mon . ')\s*,?\s*(\d{4})\b/iu', $text, $m ) ) {
		$range = $build( $m[3], cemeti_month_number( $m[2] ), $m[1], $m[3], cemeti_month_number( $m[2] ), $m[1] );
		if ( $range ) {
			return $range;
		}
	}
	if ( preg_match( '/\b(' . $mon . ')\s+(\d{1,2})' . $ord . '\s*,?\s*(\d{4})\b/iu', $text, $m ) ) {
		$range = $build( $m[3], cemeti_month_number( $m[1] ), $m[2], $m[3], cemeti_month_number( $m[1] ), $m[2] );
		if ( $range ) {
			return $range;
		}
	}
	return null;
}

/**
 * Read an application deadline from a text: the date after "deadline", "batas waktu", "batas akhir"
 * or "tenggat", else the end of the first date range.
 *
 * @param string $text      Post text or HTML.
 * @param string $reference Publish date, to reject far-future years.
 * @return string Ymd or ''.
 */
function cemeti_extract_deadline( $text, $reference = '' ) {
	$plain = preg_replace( '/[\s\x{00A0}]+/u', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $text ), ENT_QUOTES, 'UTF-8' ) );
	if ( preg_match( '/(?:deadline|batas waktu|batas akhir|batas pendaftaran|tenggat(?: waktu)?)\s*[:\-–]?\s*(.{0,60})/iu', $plain, $m ) ) {
		$snippet = preg_split( '/(?<=\d)\.\s|;|\n/u', $m[1] );
		$range   = cemeti_extract_date_range( $snippet[0], $reference );
		if ( $range ) {
			return $range[1];
		}
	}
	$range = cemeti_extract_date_range( $plain, $reference );
	return $range ? $range[1] : '';
}

/**
 * Internal links near the top of a post: the old site put an "English | Indonesia" switch there.
 *
 * @param string $html Post content.
 * @param string $host Old site host, e.g. "cemeti.art".
 * @return string[] Last path segment (slug) of each link.
 */
function cemeti_head_slugs( $html, $host ) {
	$slugs = array();
	if ( preg_match_all( '#href=["\']https?://(?:www\.)?' . preg_quote( $host, '#' ) . '/([^"\'\#?]+)#i', substr( (string) $html, 0, 1500 ), $m ) ) {
		foreach ( $m[1] as $path ) {
			$parts = explode( '/', trim( $path, '/' ) );
			$slug  = rawurldecode( (string) end( $parts ) );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * Find Indonesian/English twins: A and B are twins when their languages differ and each links
 * to the other near the top. One-way links count only when the target links to nothing else of
 * the opposite language. Each post gets at most one partner.
 *
 * @param array<int, array{slug:string,lang:string,links:string[]}> $items Post ID => data.
 * @return array<int, array{0:int,1:int}> Pairs of post IDs.
 */
function cemeti_pair_translations( array $items ) {
	$by_slug = array();
	foreach ( $items as $id => $item ) {
		$by_slug[ $item['slug'] ][] = $id;
	}
	$wants = array();
	foreach ( $items as $id => $item ) {
		foreach ( $item['links'] as $slug ) {
			foreach ( isset( $by_slug[ $slug ] ) ? $by_slug[ $slug ] : array() as $other ) {
				if ( $other !== $id && '' !== $item['lang'] && '' !== $items[ $other ]['lang'] && $item['lang'] !== $items[ $other ]['lang'] ) {
					$wants[ $id ][] = $other;
				}
			}
		}
	}
	$pairs = array();
	$taken = array();
	// Mutual links first, then unambiguous one-way links.
	foreach ( array( true, false ) as $mutual ) {
		foreach ( $wants as $id => $targets ) {
			if ( isset( $taken[ $id ] ) ) {
				continue;
			}
			$targets = array_values( array_filter( array_unique( $targets ), static function ( $t ) use ( $taken ) {
				return ! isset( $taken[ $t ] );
			} ) );
			if ( $mutual ) {
				$targets = array_values( array_filter( $targets, static function ( $t ) use ( $wants, $id ) {
					return isset( $wants[ $t ] ) && in_array( $id, $wants[ $t ], true );
				} ) );
			}
			if ( 1 === count( $targets ) ) {
				$pairs[]            = array( $id, $targets[0] );
				$taken[ $id ]       = true;
				$taken[ $targets[0] ] = true;
			}
		}
	}
	return $pairs;
}
