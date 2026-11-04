<?php
// Run: php tests/flows-polylang.php — the theme with Polylang active (language switch, strings, translations).
require __DIR__ . '/harness/bootstrap.php';

$GLOBALS['pll'] = array( 'current' => 'id', 'registered' => array(), 'lang' => array(), 'groups' => array(), 'override' => array() );
function pll_default_language() { return 'id'; }
function pll_current_language() { return $GLOBALS['pll']['current']; }
function pll_home_url( $lang = null ) { $l = $lang ? $lang : $GLOBALS['pll']['current']; return 'http://cemeti.test/' . ( 'id' === $l ? '' : $l . '/' ); }
function pll__( $s ) { return isset( $GLOBALS['pll']['override'][ $s ] ) ? $GLOBALS['pll']['override'][ $s ] : $s; }
function pll_register_string( $name, $string, $group = 'polylang' ) { $GLOBALS['pll']['registered'][ $name ] = array( $string, $group ); }
function pll_the_languages( $args = array() ) {
	$cur = $GLOBALS['pll']['current'];
	return array(
		'id' => array( 'slug' => 'id', 'url' => 'http://cemeti.test/halaman/', 'current_lang' => 'id' === $cur ),
		'en' => array( 'slug' => 'en', 'url' => 'http://cemeti.test/en/page/', 'current_lang' => 'en' === $cur ),
	);
}
function pll_set_post_language( $id, $lang ) { $GLOBALS['pll']['lang'][ $id ] = $lang; }
function pll_save_post_translations( $arr ) { $GLOBALS['pll']['groups'][] = $arr; }
function pll_get_post_translations( $id ) { foreach ( $GLOBALS['pll']['groups'] as $g ) { if ( in_array( $id, $g, true ) ) { return $g; } } return array(); }
function pll_languages_list() { return array( 'id', 'en' ); }

qa_boot();
$ids = qa_seed();

// ---- language helpers
ok( 'id' === cemeti_default_lang() && 'id' === cemeti_current_lang(), 'default and current language from Polylang' );
ok( 'http://cemeti.test/' === cemeti_home_url() && 'http://cemeti.test/en/' === cemeti_home_url( 'en' ), 'home URL per language' );
ok( 'http://cemeti.test/about' === cemeti_url( 'about' ), 'program URL in the default language has no prefix' );
$GLOBALS['pll']['current'] = 'en';
ok( 'http://cemeti.test/en/about' === cemeti_url( 'about' ) && 'http://cemeti.test/en/exhibition/?type=solo' === cemeti_url( 'exhibition/?type=solo' ), 'program URL in English gets /en/' );
ok( 'Close' === cemeti_t( 'close' ) && 'Search' === cemeti_t( 'search' ), 'UI strings in English' );
$GLOBALS['pll']['override']["What's on"] = "What's showing";
ok( "What's showing" === cemeti_t( 'whats_on' ), 'an editor override from Polylang → Strings wins' );
$GLOBALS['pll']['current'] = 'id';
ok( 'Tutup' === cemeti_t( 'close' ), 'UI strings in Indonesian' );
ok( 'Menu' === cemeti_t( 'menu' ) && 'zzz_unknown' === cemeti_t( 'zzz_unknown' ), 'unknown key is returned as is' );

// ---- strings are registered for translators
ok( count( $GLOBALS['pll']['registered'] ) === count( cemeti_ui_strings() ), 'every UI string registered with Polylang (' . count( $GLOBALS['pll']['registered'] ) . ')' );
ok( 'Cemeti' === $GLOBALS['pll']['registered']['cemeti_close'][1] && 'Close' === $GLOBALS['pll']['registered']['cemeti_close'][0], 'strings grouped under "Cemeti", English source text' );

// ---- switcher
$sw = cemeti_language_switcher_items();
ok( 2 === count( $sw ) && 'ID' === $sw[0]['label'] && $sw[0]['current'] && ! $sw[1]['current'] && 'http://cemeti.test/en/page/' === $sw[1]['url'], 'switcher: both languages, the translation URL of this page, current marked' );
$html = qa_render( 'index.php', array(), array( 'post' => $ids['p_plain'], 'path' => '/x/' ) );
ok( false !== strpos( $html, 'hreflang="en"' ) && false !== strpos( $html, 'aria-current="true"' ) && false !== strpos( $html, 'href="http://cemeti.test/en/page/"' ), 'header: switcher markup with hreflang and aria-current' );
ok( ! qa_check_html( $html, array( 'no_h1_check' => false ) ), 'header: markup valid with Polylang' );

// ---- settings per language with fallback to the default language
update_option( 'cemeti_settings_id', array( 'institution_name' => 'Cemeti ID', 'opening_hours' => 'Selasa – Sabtu' ) );
update_option( 'cemeti_settings_en', array( 'institution_name' => 'Cemeti EN' ) );
$GLOBALS['pll']['current'] = 'en';
ok( 'Cemeti EN' === cemeti_option( 'institution_name' ), 'settings: English value when present' );
ok( 'Selasa – Sabtu' === cemeti_option( 'opening_hours' ), 'settings: empty English value falls back to Indonesian' );
$GLOBALS['pll']['current'] = 'id';

// ---- dates follow the language
$GLOBALS['pll']['current'] = 'en';
ok( '8 – 29 January 2026' === cemeti_format_date_range( '20260108', '20260129' ), 'dates in English' );
$GLOBALS['pll']['current'] = 'id';
ok( '8 – 29 Januari 2026' === cemeti_format_date_range( '20260108', '20260129' ), 'dates in Indonesian' );
ok( '30 Januari 2026' === cemeti_format_date_range( '20260130', '' ) && '30 Januari 2026' === cemeti_format_date_range( '20260130', '20260130' ) && '30 Januari 2026' === cemeti_format_date_range( '20260130', '20260101' ), 'one-day and reversed ranges' );
ok( '26 September – 15 November 2025' === cemeti_format_date_range( '20250926', '20251115', 'id' ) && '27 November 2024 – 18 Januari 2025' === cemeti_format_date_range( '20241127', '20250118', 'id' ), 'ranges across months and years' );
ok( '' === cemeti_format_date_range( '2026-01-08' ) && '' === cemeti_format_date_range( '20260230' ), 'invalid dates give an empty string' );

// ---- converter with Polylang: language set, twins linked
qa_reset_data();
$mk = static function ( $title, $slug, $cats, $body ) {
	$id = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $body, 'post_date' => '2021-06-09 10:00:00' ) );
	$tids = array();
	foreach ( $cats as $c ) {
		$e = term_exists( $c, 'category' );
		$tid = $e ? $e['term_id'] : ( 7000 + count( CemetiWP::$terms['category'] ?? array() ) );
		CemetiWP::$terms['category'][ $tid ] = array( 'name' => ucfirst( $c ), 'slug' => $c, 'description' => '' );
		$tids[] = $tid;
	}
	CemetiWP::$rel[ $id ]['category'] = $tids;
	return $id;
};
$a = $mk( 'Ghost Light', 'ghost-light', array( 'pameran', 'ghost-light' ), '<a href="https://cemeti.art/2021/06/09/ghost-light-eng/">English</a> 16 Juni - 24 Juli 2021' );
$b = $mk( 'Ghost Light', 'ghost-light-eng', array( 'exhibition', 'ghost-light_' ), '<a href="https://cemeti.art/2021/06/09/ghost-light/">Indonesia</a> June 16th - July 24th 2021' );
$c = $mk( 'Kabar', 'kabar', array( 'kabar' ), 'x' );
$s = cemeti_convert_settings();
foreach ( array( $a, $b, $c ) as $id ) {
	cemeti_convert_post( $id, $s );
}
ok( 'id' === $GLOBALS['pll']['lang'][ $a ] && 'en' === $GLOBALS['pll']['lang'][ $b ] && 'id' === $GLOBALS['pll']['lang'][ $c ], 'converter: language set through Polylang' );
$n = cemeti_link_translations();
$group = $GLOBALS['pll']['groups'][0] ?? array();
ksort( $group );
ok( 1 === $n && 1 === count( $GLOBALS['pll']['groups'] ) && array( 'en' => $b, 'id' => $a ) === $group, 'converter: the twins are linked as translations (Indonesian ↔ English)' );
ok( 'exhibition' === get_post_type( $a ) && 'exhibition' === get_post_type( $b ), 'converter: both twins became exhibitions' );
// ---- every Cemeti type and taxonomy gets Polylang's language selector (key AND value must be the name)
$pt = apply_filters( 'pll_get_post_types', array( 'post' => 'post', 'page' => 'page' ) );
$bad = array_keys( array_filter( $pt, static function ( $v, $k ) { return $v !== $k; }, ARRAY_FILTER_USE_BOTH ) );
ok( ! $bad, 'Polylang: post types are listed as name => name (otherwise Polylang ignores them): ' . implode( ',', $bad ) );
foreach ( array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'person' ) as $t ) {
	ok( isset( $pt[ $t ] ), "Polylang: $t has a language selector" );
}
ok( ! isset( $pt['application'] ) && isset( $pt['page'] ), 'Polylang: applications stay private, pages keep theirs' );
$tx = apply_filters( 'pll_get_taxonomies', array( 'category' => 'category' ) );
$bad = array_keys( array_filter( $tx, static function ( $v, $k ) { return $v !== $k; }, ARRAY_FILTER_USE_BOTH ) );
ok( ! $bad && isset( $tx['exhibition_type'], $tx['residency_type'], $tx['journal_category'] ), 'Polylang: taxonomies listed as name => name' );
ok( 'en' === cemeti_lang_code( 'en-US' ) && 'en' === cemeti_lang_code( 'en_GB' ) && 'id' === cemeti_lang_code( 'in' ) && 'id' === cemeti_lang_code( 'id_ID' ) && 'fr' === cemeti_lang_code( 'fr' ), 'language slugs such as en-us, in, id_ID map to en / id' );

// ---- admin: which menus still lack a language selector
$GLOBALS['pll']['translated'] = array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'person', 'page' );
function pll_is_translated_post_type( $t ) { return in_array( $t, $GLOBALS['pll']['translated'], true ); }
ok( array() === cemeti_language_issues(), 'Polylang ready: no language warnings' );
$GLOBALS['pll']['translated'] = array( 'page', 'exhibition' );
$issues = cemeti_language_issues();
ok( 1 === count( $issues ) && false !== strpos( $issues[0], 'residency' ) && false !== strpos( $issues[0], 'journal' ) && false === strpos( $issues[0], 'application' ), 'a type without a language selector is named in the warning' );
$GLOBALS['pll']['translated'] = array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'person', 'page' );

// ---- fixed pages: one copy per language, same slug, linked as translations
qa_reset_data();
$GLOBALS['pll']['lang'] = $GLOBALS['pll']['groups'] = array();
do_action( 'after_switch_theme' );
$pages = array_filter( CemetiWP::$posts, static function ( $p ) { return 'page' === $p['post_type']; } );
ok( 16 === count( $pages ), 'Polylang: 8 pages in each of two languages (' . count( $pages ) . ')' );
$about = array_keys( array_filter( $pages, static function ( $p ) { return 'about' === $p['post_name']; } ) );
ok( 2 === count( $about ) && array( 'en', 'id' ) === ( function ( $ids ) { $l = array_map( static function ( $i ) { return $GLOBALS['pll']['lang'][ $i ]; }, $ids ); sort( $l ); return $l; } )( $about ), 'Polylang: "about" exists in id and en with the same slug' );
ok( 8 === count( $GLOBALS['pll']['groups'] ), 'Polylang: each page is linked to its translation (' . count( $GLOBALS['pll']['groups'] ) . ' groups)' );
$support_ids = array_keys( array_filter( $pages, static function ( $p ) { return 'support-us' === $p['post_name']; } ) );
$kids = array_filter( $pages, static function ( $p ) { return 'collaboration' === $p['post_name']; } );
foreach ( $kids as $kid_id => $kid ) {
	ok( (int) $GLOBALS['pll']['lang'][ $kid_id ] === (int) $GLOBALS['pll']['lang'][ $kid['post_parent'] ] || $GLOBALS['pll']['lang'][ $kid_id ] === $GLOBALS['pll']['lang'][ $kid['post_parent'] ], 'Polylang: a Support Us row sits under the Support Us page of its own language' );
}
ok( 0 === cemeti_create_missing_pages() && 16 === count( array_filter( CemetiWP::$posts, static function ( $p ) { return 'page' === $p['post_type']; } ) ), 'Polylang: running again creates nothing' );

// ---- a person without a language is not lost on the About page
qa_reset_data();
$GLOBALS['pll']['lang'] = array();
$nolang = CemetiWP::post( array( 'post_type' => 'person', 'post_title' => 'Added before languages', 'menu_order' => 1 ) );
$GLOBALS['pll']['current'] = 'en';
$g = cemeti_people_by_group();
ok( 1 === count( $g['team'] ) && 'Added before languages' === $g['team'][0]->post_title, 'Polylang: a person with no language still appears (all languages are tried when the current one has nobody)' );
$withlang = CemetiWP::post( array( 'post_type' => 'person', 'post_title' => 'English person', 'menu_order' => 2 ) );
$GLOBALS['pll']['lang'][ $withlang ] = 'en';
$g = cemeti_people_by_group();
ok( 1 === count( $g['team'] ) && 'English person' === $g['team'][0]->post_title, 'Polylang: once the language has people, only that language is shown' );
$GLOBALS['pll']['current'] = 'id';

// ---- both languages of a page / entry look the same
qa_reset_data();
$GLOBALS['pll']['lang'] = $GLOBALS['pll']['groups'] = array();
$en  = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about', 'thumb' => 55, 'meta' => array( '_cemeti_page_key' => 'about' ) ) );
$idp = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Halaman kami', 'post_name' => 'halaman-kami' ) );
ok( '' === cemeti_page_key( $idp ), 'not linked yet: an ordinary page' );
pll_save_post_translations( array( 'en' => $en, 'id' => $idp ) );
ok( 'about' === cemeti_page_key( $idp ), 'Polylang: the Indonesian copy takes the design of the English page' );
ok( 55 === get_post_thumbnail_id( $idp ), 'Polylang: the Indonesian copy shows the same featured image as the English one' );
$free_en = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Free', 'thumb' => 66 ) );
$free_id = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Bebas' ) );
pll_save_post_translations( array( 'en' => $free_en, 'id' => $free_id ) );
ok( ! has_post_thumbnail( $free_id ), 'an ordinary page does not borrow pictures' );
$ex_en = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Show', 'thumb' => 77 ) );
$ex_id = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Pameran' ) );
pll_save_post_translations( array( 'en' => $ex_en, 'id' => $ex_id ) );
ok( 77 === get_post_thumbnail_id( $ex_id ), 'Polylang: an entry without a poster uses the poster of its translation' );

// ---- parity: the same page in English and in Indonesian has the same structure (only the words differ)
qa_reset_data();
$GLOBALS['pll']['lang'] = $GLOBALS['pll']['groups'] = array();
$ids = qa_seed();
$skeleton = static function ( $html ) {
	$html = preg_replace( '#<(script|style)\b.*?</\1>#s', '', $html );
	$html = preg_replace( '#\s(href|src|srcset|alt|title|aria-label|aria-labelledby|placeholder|value|data-[a-z-]+|id|for|name|content|aria-current|action)="[^"]*"#', '', $html );
	$html = preg_replace( '#>[^<]+<#', '><', $html );
	return preg_replace( '#\s+#', ' ', $html );
};
$pages = array(
	'home'               => array( 'front-page.php', array( 'front' => true ), array() ),
	'archive exhibition' => array( 'archive.php', array( 'pta' => 'exhibition' ), array( 'path' => '/exhibition/', 'query' => array( 'post_type' => 'exhibition' ), 'flags' => array( 'pta' => 'exhibition' ) ) ),
	'archive residency'  => array( 'archive-residency.php', array( 'pta' => 'residency' ), array( 'path' => '/residency/', 'query' => array( 'post_type' => 'residency' ), 'flags' => array( 'pta' => 'residency' ) ) ),
	'archive journal'    => array( 'archive-journal.php', array( 'pta' => 'journal' ), array( 'path' => '/journal/', 'query' => array( 'post_type' => 'journal' ), 'flags' => array( 'pta' => 'journal' ) ) ),
	'exhibition detail'  => array( 'single.php', array( 'singular' => true ), array( 'post' => $ids['ex_current'], 'path' => '/exhibition/x/' ) ),
	'journal detail'     => array( 'single-journal.php', array( 'singular' => true ), array( 'post' => $ids['j_essay'], 'path' => '/journal/x/' ) ),
);
foreach ( $pages as $label => $def ) {
	$GLOBALS['pll']['current'] = 'en';
	$english = qa_render( $def[0], $def[1], $def[2] );
	$GLOBALS['pll']['current'] = 'id';
	$indonesian = qa_render( $def[0], $def[1], $def[2] );
	$a = $skeleton( $english );
	$b = $skeleton( $indonesian );
	$at = '';
	if ( $a !== $b ) {
		$i = 0;
		while ( $i < strlen( $a ) && $i < strlen( $b ) && $a[ $i ] === $b[ $i ] ) {
			++$i;
		}
		$at = ' — differs at: EN "' . substr( $a, max( 0, $i - 40 ), 90 ) . '" / ID "' . substr( $b, max( 0, $i - 40 ), 90 ) . '"';
	}
	ok( $a === $b, "parity: $label has the same structure in English and Indonesian" . $at );
	ok( $english !== $indonesian, "parity: $label really changes words between the languages" );
}
$GLOBALS['pll']['current'] = 'id';

qa_done();
