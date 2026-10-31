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
qa_done();
