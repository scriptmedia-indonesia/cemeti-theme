<?php
// Run: php tests/flows-polylang.php — the theme with Polylang active (language switch, strings, translations).
require __DIR__ . '/harness/bootstrap.php';

$GLOBALS['pll'] = array( 'current' => 'id', 'registered' => array(), 'lang' => array(), 'groups' => array(), 'override' => array(), 'tgroups' => array(), 'tlang' => array() );
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

// ---- menu links lead to the real page of the current language (Indonesian slugs differ: "tentang", "kontak")
$by = static function ( $slug, $lang ) { foreach ( CemetiWP::$posts as $pid => $pp ) { if ( 'page' === $pp['post_type'] && $slug === $pp['post_name'] && ( $GLOBALS['pll']['lang'][ $pid ] ?? '' ) === $lang ) { return $pid; } } return 0; };
$about_id = $by( 'about', 'id' ); $contact_id = $by( 'contact-us', 'id' );
CemetiWP::$posts[ $about_id ]['post_name'] = 'tentang';
CemetiWP::$posts[ $contact_id ]['post_name'] = 'kontak';
$GLOBALS['pll']['current'] = 'id';
ok( false !== strpos( cemeti_menu_url( 'about' ), '/tentang' ) && false !== strpos( cemeti_menu_url( 'contact-us' ), '/kontak' ), 'menu: Indonesian About and Contact Us link to "tentang" and "kontak", not to missing English slugs' );
ok( false !== strpos( cemeti_menu_url( 'support-us' ), 'support-us' ) && false !== strpos( cemeti_menu_url( 'exhibition' ), 'exhibition' ), 'menu: pages that keep their slug and program paths still link normally' );
$GLOBALS['pll']['current'] = 'en';
ok( false !== strpos( cemeti_menu_url( 'about' ), '/about' ), 'menu: the English About link is unchanged' );
ok( 0 === strpos( cemeti_menu_url( 'exhibition', 'en' ), 'http://cemeti.test/en/' ) && 0 === strpos( cemeti_menu_url( 'residency', 'id' ), 'http://cemeti.test/residency' ), 'menu: a language can be asked for explicitly, whatever the request language is' );
CemetiWP::$posts[ $about_id ]['post_name'] = 'about';
CemetiWP::$posts[ $contact_id ]['post_name'] = 'contact-us';

// ---- an updated site (pages made before the Indonesian copies existed) gets them by itself in the admin
foreach ( $GLOBALS['pll']['lang'] as $pid => $plang ) {
	if ( 'id' === $plang && isset( CemetiWP::$posts[ $pid ] ) ) {
		unset( CemetiWP::$posts[ $pid ] );
	}
}
$GLOBALS['pll']['groups'] = array();
CemetiWP::$options = array();
$count_id = static function () { return count( array_filter( CemetiWP::$posts, static function ( $p ) { return 'page' === $p['post_type']; } ) ); };
CemetiWP::$caps = false;
do_action( 'admin_init' );
ok( 8 === $count_id(), 'update: a visitor who is not an administrator creates nothing' );
CemetiWP::$caps = true;
do_action( 'admin_init' );
ok( 16 === $count_id() && 8 === count( $GLOBALS['pll']['groups'] ), 'update: the admin dashboard creates the missing Indonesian Open Archive, Support Us and Contact Us pages and links them' );
$before = $count_id();
foreach ( CemetiWP::$posts as $pid => $pp ) { if ( 'contact-us' === $pp['post_name'] ) { unset( CemetiWP::$posts[ $pid ] ); break; } }
do_action( 'admin_init' );
ok( $before - 1 === $count_id(), 'update: it runs once per version, a page deleted on purpose stays deleted' );

// ---- an update never makes a second Indonesian page: the existing "Tentang" is found, linked, and keeps its text
qa_reset_data();
$GLOBALS['pll']['lang'] = $GLOBALS['pll']['groups'] = array();
CemetiWP::$options = array();
// The site before the update: English pages by the theme, Indonesian ones made by hand.
$en_about = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about', 'post_content' => 'English text' ) );
$GLOBALS['pll']['lang'][ $en_about ] = 'en';
$tentang_real = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Tentang', 'post_name' => 'tentang', 'post_content' => 'Teks Indonesia yang sudah diisi tim.', 'post_date' => '2026-11-07 03:25:00' ) );
$tentang_dup  = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Tentang', 'post_name' => 'tentang-2', 'post_content' => '', 'post_date' => '2026-11-09 05:49:00' ) );
$GLOBALS['pll']['lang'][ $tentang_real ] = $GLOBALS['pll']['lang'][ $tentang_dup ] = 'id';
$contact_id = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Kontak Kami', 'post_name' => 'kontak-kami', 'post_content' => 'Hubungi kami.' ) );
$GLOBALS['pll']['lang'][ $contact_id ] = 'id';
$before_pages = count( array_filter( CemetiWP::$posts, static function ( $p ) { return 'page' === $p['post_type']; } ) );
$id_about_found = cemeti_find_page( 'about', 'id' );
ok( $tentang_real === $id_about_found, 'update: the Indonesian About is the existing "Tentang" that has text, not the empty copy (' . $id_about_found . ')' );
ok( $contact_id === cemeti_find_page( 'contact-us', 'id' ), 'update: "Kontak Kami" is found as the Indonesian Contact Us' );
CemetiWP::$caps = true;
do_action( 'admin_init' );
$ids_about = array_keys( array_filter( CemetiWP::$posts, static function ( $p ) { return 'page' === $p['post_type'] && in_array( $p['post_name'], array( 'about', 'tentang', 'tentang-2' ), true ); } ) );
ok( 3 === count( $ids_about ), 'update: no extra About page was made (' . count( $ids_about ) . ')' );
ok( 'Teks Indonesia yang sudah diisi tim.' === CemetiWP::$posts[ $tentang_real ]['post_content'] && 'English text' === CemetiWP::$posts[ $en_about ]['post_content'], 'update: the text of existing pages is untouched' );
$linked = pll_get_post_translations( $en_about );
ok( isset( $linked['id'] ) && (int) $linked['id'] === $tentang_real, 'update: the English About is linked to the existing Indonesian "Tentang" (the pencil icon comes back)' );
$lc = pll_get_post_translations( $contact_id );
ok( count( $lc ) > 1, 'update: "Kontak Kami" is linked to the English Contact Us' );
$pre = pll_get_post_translations( $en_about );
cemeti_create_missing_pages();
ok( $pre === pll_get_post_translations( $en_about ), 'update: running again rearranges nothing' );

// ---- Fixed pages: the language switch finds the other language's page even when they are not linked ...
$GLOBALS['pll']['groups'] = array();
$GLOBALS['pll']['current'] = 'en';
CemetiWP::request( '/about/', array( 'singular' => true ) );
$GLOBALS['post'] = CemetiWP::obj( $en_about );
$sw = cemeti_language_switcher_items();
$id_item = array_values( array_filter( $sw, static function ( $i ) { return 'id' === $i['code']; } ) )[0];
ok( false !== strpos( $id_item['url'], 'tentang' ) && false === strpos( $id_item['url'], 'halaman' ), 'switch: on the unlinked English About, ID leads to the Indonesian "Tentang" (not the home page) (' . $id_item['url'] . ')' );
// ... and the table of fixed pages links what the team chooses
CemetiWP::$caps = true;
$_POST = array( '_wpnonce' => 'valid', 'cemeti_pair' => array( 'about' => array( 'en' => $en_about, 'id' => $tentang_real ), 'contact-us' => array( 'id' => $contact_id ) ) );
try { do_action( 'admin_post_cemeti_save_page_pairs' ); } catch ( CemetiRedirect $e ) { parse_str( (string) parse_url( $e->url, PHP_URL_QUERY ), $q ); }
$lk = pll_get_post_translations( $en_about );
ok( isset( $q['cemeti_pairs_saved'] ) && 1 === (int) $q['cemeti_pairs_saved'] && (int) ( $lk['id'] ?? 0 ) === $tentang_real, 'fixed pages table: the chosen pages are linked as translations' );
ok( 'about' === get_post_meta( $tentang_real, '_cemeti_page_key', true ), 'fixed pages table: the chosen page is remembered as About (its design and menu link)' );
ob_start(); cemeti_render_page_pairs_box(); $box = ob_get_clean();
ok( false !== strpos( $box, 'cemeti_pair[about][en]' ) && false !== strpos( $box, 'Tentang' ) && false !== strpos( $box, 'Save and link' ), 'fixed pages table: shown in Settings with a dropdown per language' );
// a chosen page that has no language yet is given the column's language, and the dropdown still shows it afterwards
unset( $GLOBALS['pll']['lang'][ $tentang_real ] );
$GLOBALS['pll']['groups'] = array();
ob_start(); cemeti_render_page_pairs_box(); $box = ob_get_clean();
ok( 1 === preg_match( '/name="cemeti_pair\[about\]\[id\]">.*?value="' . $tentang_real . '"/s', $box ), 'fixed pages table: a page without a language is offered in the ID dropdown' );
$_POST = array( '_wpnonce' => 'valid', 'cemeti_pair' => array( 'about' => array( 'en' => $en_about, 'id' => $tentang_real ) ) );
try { do_action( 'admin_post_cemeti_save_page_pairs' ); } catch ( CemetiRedirect $e ) {}
ok( 'id' === ( $GLOBALS['pll']['lang'][ $tentang_real ] ?? '' ) && $tentang_real === cemeti_find_page( 'about', 'id' ), 'fixed pages table: saving gives the page the column\'s language, so it is found for the menu' );
ob_start(); cemeti_render_page_pairs_box(); $box = ob_get_clean();
ok( 1 === preg_match( '/name="cemeti_pair\[about\]\[id\]">.*?value="' . $tentang_real . '" selected/s', $box ), 'fixed pages table: after saving the ID dropdown shows the chosen page, not "none"' );
CemetiWP::$caps = false;
$_POST = array( 'cemeti_pair' => array() );
try { do_action( 'admin_post_cemeti_save_page_pairs' ); ok( false, 'fixed pages table: refused for non-administrators' ); } catch ( CemetiDie $e ) { ok( true, 'fixed pages table: refused for non-administrators' ); }
CemetiWP::$caps = true; $_POST = array();

// ---- Research / Regular (and every default term) exist in the second language too
qa_reset_data();
function pll_set_term_language( $id, $lang ) { $GLOBALS['pll']['tlang'][ $id ] = $lang; }
function pll_get_term_language( $id ) { return isset( $GLOBALS['pll']['tlang'][ $id ] ) ? $GLOBALS['pll']['tlang'][ $id ] : ''; }
function pll_save_term_translations( $arr ) { $GLOBALS['pll']['tgroups'][] = $arr; }
function pll_get_term_translations( $id ) { foreach ( $GLOBALS['pll']['tgroups'] as $g ) { if ( in_array( $id, $g, true ) ) { return $g; } } return array(); }
function pll_get_term( $id, $lang ) { $g = pll_get_term_translations( $id ); return isset( $g[ $lang ] ) ? $g[ $lang ] : 0; }
function pll_get_post_language( $id ) { return isset( $GLOBALS['pll']['lang'][ $id ] ) ? $GLOBALS['pll']['lang'][ $id ] : ''; }
$GLOBALS['pll']['tlang'] = $GLOBALS['pll']['tgroups'] = array();
CemetiWP::$caps = true;
do_action( 'admin_init' );
$research = term_exists( 'Research', 'residency_type' );
$tr = pll_get_term_translations( $research['term_id'] );
ok( 2 === count( $tr ) && ! empty( $tr['en'] ) && ! empty( $tr['id'] ), 'terms: "Research" exists in both languages and they are linked (' . json_encode( $tr ) . ')' );
$en_term = CemetiWP::$terms['residency_type'][ $tr['en'] ];
ok( 'research-en' === $en_term['slug'] && 'Research' === $en_term['name'] && false !== strpos( $en_term['description'], 'facilitated' ), 'terms: the second language copy has its own slug and the default description' );
$n_groups = count( $GLOBALS['pll']['tgroups'] );
do_action( 'admin_init' );
ok( count( $GLOBALS['pll']['tgroups'] ) === $n_groups, 'terms: run once per version and set of languages' );
$GLOBALS['pll']['lang'] = array();
$rid = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Riset' ) );
wp_set_object_terms( $rid, array( $research['term_id'] ), 'residency_type' );
$GLOBALS['pll']['lang'][ $rid ] = 'en';
do_action( 'save_post', $rid );
$have = get_the_terms( $rid, 'residency_type' );
ok( 1 === count( $have ) && (int) $have[0]->term_id === (int) $tr['en'], 'terms: an entry in the second language takes the term of its own language on save' );
$GLOBALS['pll']['current'] = 'id';
$t = term_obj( 'residency_type', $research['term_id'] );
ok( 'Peserta terpilih difasilitasi oleh seorang asisten selama masa residensi.' === cemeti_term_description( (object) array( 'taxonomy' => 'residency_type', 'name' => 'Research', 'description' => $t->description ) ), 'terms: the Residency page shows the Indonesian text when the term still has the English default' );
ok( 'Edited by the team' === cemeti_term_description( (object) array( 'taxonomy' => 'residency_type', 'name' => 'Research', 'description' => 'Edited by the team' ) ), 'terms: a description edited by the team is never replaced' );
$GLOBALS['pll']['current'] = 'en';
ok( false !== strpos( cemeti_term_description( (object) array( 'taxonomy' => 'residency_type', 'name' => 'Research', 'description' => '' ) ), 'facilitated' ), 'terms: English default when the term has no text' );

// ---- "Create EN version": the copy that works when Polylang's "+" only reloads the page
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 1; } }
$GLOBALS['pll']['groups'] = array();
$src = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Residensi Riset', 'post_content' => 'Isi teks', 'thumb' => 501, 'meta' => array( 'cemeti_gallery' => array( 601, 602 ), 'cemeti_start_date' => '20260301', 'cemeti_artists' => "A\nB", 'cemeti_related' => array( 5 ) ) ) );
$GLOBALS['pll']['lang'][ $src ] = 'id';
wp_set_object_terms( $src, array( $research['term_id'] ), 'residency_type' );
$copy = cemeti_translate_create( $src, 'en' );
ok( $copy && $copy !== $src && 'residency' === CemetiWP::$posts[ $copy ]['post_type'] && 'draft' === CemetiWP::$posts[ $copy ]['post_status'] && 'Residensi Riset' === CemetiWP::$posts[ $copy ]['post_title'], 'translate: a draft of the same type with the title and text is made' );
ok( 'en' === $GLOBALS['pll']['lang'][ $copy ] && array( 601, 602 ) === get_post_meta( $copy, 'cemeti_gallery', true ) && '20260301' === get_post_meta( $copy, 'cemeti_start_date', true ) && 501 === (int) get_post_meta( $copy, '_thumbnail_id', true ), 'translate: language set, galleries, dates and picture copied' );
ok( '' === get_post_meta( $copy, 'cemeti_related', true ), 'translate: the related list (other entries) is not copied' );
$ct = get_the_terms( $copy, 'residency_type' );
ok( $ct && (int) $ct[0]->term_id === (int) $tr['en'], 'translate: the copy carries the term of its own language (Research)' );
ok( in_array( array( 'id' => $src, 'en' => $copy ), $GLOBALS['pll']['groups'], true ) || in_array( array( 'en' => $copy, 'id' => $src ), $GLOBALS['pll']['groups'], true ), 'translate: the two are linked as translations' );
ok( $copy === cemeti_translate_create( $src, 'en' ), 'translate: asking again opens the existing translation (no second copy)' );
ok( 0 === cemeti_translate_create( $src, 'xx' ), 'translate: an unknown language makes nothing' );
CemetiWP::$meta_boxes = array();
do_action( 'add_meta_boxes' );
ok( isset( CemetiWP::$meta_boxes['residency']['cemeti_translate'] ), 'translate: the box is on Residency' );
$other = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Lain' ) );
$GLOBALS['pll']['lang'][ $other ] = 'id';
ob_start(); call_user_func( CemetiWP::$meta_boxes['residency']['cemeti_translate'], CemetiWP::obj( $other ) ); $box = ob_get_clean();
ok( false !== strpos( $box, 'action=cemeti_translate' ) && false !== strpos( $box, 'Create EN version' ), 'translate: the box offers the button for the missing language' );
ob_start(); call_user_func( CemetiWP::$meta_boxes['residency']['cemeti_translate'], CemetiWP::obj( $src ) ); $box = ob_get_clean();
ok( false !== strpos( $box, 'edit translation' ) && false === strpos( $box, 'Create EN version' ), 'translate: an existing translation is linked, not offered again' );
ok( in_array( 'cemeti_gallery', apply_filters( 'pll_copy_post_metas', array() ), true ) && in_array( '_thumbnail_id', apply_filters( 'pll_copy_post_metas', array() ), true ), 'translate: Polylang\'s own "+" copies the Cemeti fields too' );

// ---- the Residency page lists Regular AND Research in every language (a missing translation never hides one)
qa_reset_data();
CemetiWP::$terms['residency_type'] = array();
wp_insert_term( 'Research', 'residency_type', array( 'description' => 'Selected participants are facilitated by an assistant during the residency.' ) );
wp_insert_term( 'Reguler', 'residency_type', array( 'slug' => 'reguler', 'description' => 'Program Residensi Reguler ...' ) );
wp_insert_term( 'Regular', 'residency_type', array( 'slug' => 'regular-en' ) );
$intro = cemeti_intro_terms( 'residency_type' );
ok( 2 === count( $intro ) && 'Reguler' === $intro[0]->name && 'Research' === $intro[1]->name, 'residency page: Regular (as "Reguler") first, Research second, each once (' . implode( ',', array_map( static function ( $t ) { return $t->name; }, $intro ) ) . ')' );
ok( 'Regular' === cemeti_term_key( (object) array( 'name' => 'Reguler', 'slug' => 'reguler' ) ) && 'Research' === cemeti_term_key( (object) array( 'name' => 'Riset', 'slug' => 'riset-id' ) ) && 'Custom' === cemeti_term_key( (object) array( 'name' => 'Custom', 'slug' => 'custom' ) ), 'terms: Reguler/Regular and Research/Riset are the same default terms' );
$GLOBALS['pll']['current'] = 'id';
$html = qa_render( 'archive-residency.php', array( 'pta' => 'residency' ), array( 'path' => '/residency/', 'query' => array( 'post_type' => 'residency' ), 'flags' => array( 'pta' => 'residency' ) ) );
ok( false !== strpos( $html, '>Research<' ) && false !== strpos( $html, '>Reguler<' ) && false !== strpos( $html, 'Peserta terpilih difasilitasi' ), 'residency page (Indonesian): Research is shown with its Indonesian text' );
$GLOBALS['pll']['current'] = 'en';

// ---- an Indonesian entry without pictures of its own shows the other language's (sections and contents bar not missing)
$GLOBALS['pll']['groups'] = array();
$en_ex = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'EN show', 'meta' => array( 'cemeti_gallery' => array( 601, 602 ), 'cemeti_documentation' => array( 611 ), 'cemeti_booklet_url' => 'https://example.org/b.pdf' ) ) );
$id_ex = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Pameran ID', 'meta' => array( 'cemeti_documentation' => array( 700 ) ) ) );
$GLOBALS['pll']['groups'][] = array( 'en' => $en_ex, 'id' => $id_ex );
ok( array( 601, 602 ) === cemeti_get_field( 'gallery', $id_ex ) && 'https://example.org/b.pdf' === cemeti_get_field( 'booklet_url', $id_ex ), 'translation: an entry with no gallery or booklet shows the other language\'s' );
ok( array( 700 ) === cemeti_get_field( 'documentation', $id_ex ), 'translation: its own pictures always win' );
ok( array() === cemeti_get_field( 'gallery', CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Alone' ) ) ), 'translation: no translation, no gallery' );

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
