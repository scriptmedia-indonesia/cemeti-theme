<?php
// Run: php tests/migration-logic.php  (stubs WordPress; simulates converting and reverting posts).
define( 'ABSPATH', '/' ); define( 'CEMETI_DIR', dirname( __DIR__ ) . '/themes/scriptmedia-cemeti' );
$GLOBALS['db'] = array(); // id => [type, cats, meta, terms, date, title]
function is_admin() { return false; }
function add_action() {} function add_management_page() {}
function sanitize_file_name( $s ) { return $s; }
function wp_get_post_categories( $id, $a = array() ) { $c = $GLOBALS['db'][ $id ]['cats']; if ( isset( $a['fields'] ) ) { return array_map( function ( $x ) { return (object) array( 'slug' => $x, 'name' => ucfirst( $x ) ); }, $GLOBALS['db'][ $id ]['catslugs'] ?? array() ); } return $c; }
function get_permalink( $id ) { return 'https://old.test/' . $GLOBALS['db'][ $id ]['slug'] . '/'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function update_post_meta( $id, $k, $v ) { $GLOBALS['db'][ $id ]['meta'][ $k ] = $v; }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['db'][ $id ]['meta'][ $k ] ?? ''; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['db'][ $id ]['meta'][ $k ] ); }
function get_post_type( $id ) { return $GLOBALS['db'][ $id ]['type']; }
function set_post_type( $id, $t ) { $GLOBALS['db'][ $id ]['type'] = $t; }
function get_post_time( $f, $g, $id ) { return $GLOBALS['db'][ $id ]['date']; }
function get_post_field( $f, $id ) { return array( 'post_date' => $GLOBALS['db'][ $id ]['pdate'] ?? '2025-02-10 10:00:00', 'post_name' => $GLOBALS['db'][ $id ]['slug'] )[ $f ]; }
function get_post( $id ) { $d = $GLOBALS['db'][ $id ]; return (object) array( 'ID' => $id, 'post_date' => $d['pdate'] ?? '2025-02-10 10:00:00', 'post_content' => $d['content'] ?? '', 'post_name' => $d['slug'], 'post_title' => $d['title'] ?? '' ); }
function get_the_title( $id ) { return $GLOBALS['db'][ $id ]['title'] ?? ''; }
function wp_get_post_tags( $id, $a ) { return $GLOBALS['db'][ $id ]['tags'] ?? array(); }
function get_cat_name( $c ) { return array( 1 => 'Solo Exhibition', 2 => 'Essay', 3 => 'News', 9 => 'Uncategorized' )[ $c ] ?? ''; }
function wp_set_object_terms( $id, $terms, $tax ) { $GLOBALS['db'][ $id ]['terms'][ $tax ] = $terms; }
function get_object_taxonomies( $t ) { return array( 'exhibition' => array( 'exhibition_type' ), 'journal' => array( 'journal_category' ) )[ $t ] ?? array(); }
function get_post_type_archive_link( $t ) { return 'https://new.test/' . str_replace( '_', '-', $t ) . '/'; }
function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
function wp_get_post_categories_all() {}
require CEMETI_DIR . '/inc/core-bridge.php'; require CEMETI_DIR . '/inc/migration-rules.php'; require CEMETI_DIR . '/inc/status.php'; require CEMETI_DIR . '/inc/migration.php'; require CEMETI_DIR . '/inc/redirects.php';

$fail = 0; function ok( $c, $m ) { global $fail; echo ( $c ? 'PASS ' : 'FAIL ' ) . $m . "\n"; if ( ! $c ) { $fail++; } }

ok( cemeti_parse_ymd( '20260306' ) === '20260306', 'Ymd kept' );
ok( cemeti_parse_ymd( '2026-03-06' ) === '20260306', 'Y-m-d read' );
ok( cemeti_parse_ymd( '6 March 2026' ) === '20260306', 'text date read' );
ok( cemeti_parse_ymd( '20261340' ) === '', 'bad Ymd rejected' );
ok( cemeti_parse_ymd( 'soon' ) === '' && cemeti_parse_ymd( '' ) === '', 'junk and empty rejected' );

$map = array( 1 => array( 'type' => 'exhibition', 'term' => '__name' ), 2 => array( 'type' => 'journal', 'term' => '__name' ), 3 => array( 'type' => 'journal', 'term' => 'news' ) );
ok( cemeti_convert_pick_target( array( 2, 1 ), $map )['type'] === 'exhibition', 'first mapped category in map order wins' );
ok( cemeti_convert_pick_target( array( 9 ), $map ) === null, 'unmapped category -> no target' );

$GLOBALS['db'] = array(
	10 => array( 'type' => 'post', 'cats' => array( 1 ), 'slug' => '2026/03/harta', 'meta' => array( 'ev_start' => '2026-03-06', 'ev_end' => '18 April 2026' ), 'terms' => array(), 'date' => '20250101' ),
	11 => array( 'type' => 'post', 'cats' => array( 2 ), 'slug' => 'towards', 'meta' => array(), 'terms' => array(), 'date' => '20240701' ),
	12 => array( 'type' => 'post', 'cats' => array( 1 ), 'slug' => 'no-date', 'meta' => array( 'ev_start' => 'n/a' ), 'terms' => array(), 'date' => '20250101' ),
);
$settings = array( 'mode' => 'manual', 'cats' => $map, 'start_key' => 'ev_start', 'end_key' => 'ev_end', 'old_permalink' => '', 'old_host' => 'old.test' );
ok( cemeti_convert_post( 10, $settings ) === 'converted', 'exhibition converted' );
$p = $GLOBALS['db'][10];
ok( $p['type'] === 'exhibition' && $p['meta']['cemeti_start_date'] === '20260306' && $p['meta']['cemeti_end_date'] === '20260418', 'dates from custom fields' );
ok( $p['terms']['exhibition_type'] === array( 'Solo Exhibition' ), 'term from old category name' );
ok( $p['meta']['_cemeti_old_path'] === '2026/03/harta', 'old path saved' );
ok( cemeti_convert_post( 11, $settings ) === 'converted' && $GLOBALS['db'][11]['type'] === 'journal' && ! isset( $GLOBALS['db'][11]['meta']['cemeti_start_date'] ), 'journal gets no dates' );
ok( cemeti_convert_post( 12, $settings ) === 'no_date', 'unreadable start date reported' );
$settings2 = array( 'mode' => 'manual', 'cats' => $map, 'start_key' => '', 'end_key' => '', 'old_permalink' => '', 'old_host' => 'old.test' );
$GLOBALS['db'][13] = array( 'type' => 'post', 'cats' => array( 1 ), 'slug' => 'x', 'meta' => array(), 'terms' => array(), 'date' => '20250210' );
cemeti_convert_post( 13, $settings2 );
ok( $GLOBALS['db'][13]['meta']['cemeti_start_date'] === '20250210' && $GLOBALS['db'][13]['meta']['cemeti_end_date'] === '20250210', 'publish date used when no key set' );
cemeti_revert_post( 10 );
$p = $GLOBALS['db'][10];
ok( $p['type'] === 'post' && ! isset( $p['meta']['cemeti_start_date'] ) && ! isset( $p['meta']['_cemeti_old_path'] ) && $p['terms']['exhibition_type'] === array(), 'revert restores type and clears meta' );

$legacy = array( 'category/solo' => array( 'type' => 'exhibition', 'term' => 'solo' ), 'category/news' => array( 'type' => 'journal', 'term' => '__name' ) );
ok( cemeti_legacy_category_target( 'category/solo', $legacy ) === 'https://new.test/exhibition/?type=solo', 'old category -> archive with type filter' );
ok( cemeti_legacy_category_target( 'category/news', $legacy ) === 'https://new.test/journal/', 'journal category -> archive' );
ok( cemeti_legacy_category_target( 'category/other', $legacy ) === '', 'unknown path ignored' );
// ---- Cemeti rules mode ----
$rules = array( 'mode' => 'rules', 'cats' => array(), 'start_key' => '', 'end_key' => '', 'old_permalink' => '%year%/%monthnum%/%day%/%postname%', 'old_host' => 'old.test' );
$GLOBALS['db'] = array(
	20 => array( 'type' => 'post', 'cats' => array(), 'catslugs' => array( 'pameran-tunggal', 'pameran', 'ghost-light' ), 'slug' => 'ghost-light', 'title' => 'Pameran Tunggal Ghost Light', 'meta' => array(), 'terms' => array(), 'date' => '20210609', 'pdate' => '2021-06-09 19:00:00', 'content' => '<p>Kurator : X</p><p>16 Juni - 24 Juli 2021</p><a href="https://old.test/2021/06/09/ghost-light-eng/">English</a>', 'tags' => array( 'Timoteus', 'Manshur' ) ),
	21 => array( 'type' => 'post', 'cats' => array(), 'catslugs' => array( 'esai', 'kabar-teman-rimpang' ), 'slug' => 'esai-1', 'title' => 'Sebuah Esai', 'meta' => array(), 'terms' => array(), 'date' => '20240101', 'pdate' => '2024-01-01 10:00:00', 'content' => 'Tanpa tanggal acara', 'tags' => array() ),
	22 => array( 'type' => 'post', 'cats' => array(), 'catslugs' => array( 'public-program', 'english' ), 'slug' => 'talk', 'title' => 'Public Talk', 'meta' => array(), 'terms' => array(), 'date' => '20251007', 'pdate' => '2025-10-01 10:00:00', 'content' => 'Tuesday, October 7th 2025 14.00 - 16.00', 'tags' => array( 'Nick Yu', 'John Tain' ) ),
	23 => array( 'type' => 'post', 'cats' => array(), 'catslugs' => array( 'pameran' ), 'slug' => 'template', 'title' => 'TEMPLATE IND', 'meta' => array(), 'terms' => array(), 'date' => '20210609', 'pdate' => '2021-06-09 10:00:00', 'content' => '', 'tags' => array() ),
	24 => array( 'type' => 'post', 'cats' => array(), 'catslugs' => array( 'residensi' ), 'slug' => 'residensi-2017', 'title' => 'Residensi 2017 Periode #2', 'meta' => array(), 'terms' => array(), 'date' => '20170901', 'pdate' => '2017-09-01 00:00:00', 'content' => 'Tidak ada tanggal', 'tags' => array() ),
);
ok( cemeti_convert_post( 20, $rules ) === 'converted', 'rules: exhibition converted' );
$p = $GLOBALS['db'][20];
ok( $p['type'] === 'exhibition' && $p['terms']['exhibition_type'] === array( 'Solo' ), 'rules: Pameran Tunggal -> Exhibition / Solo' );
ok( $p['meta']['cemeti_start_date'] === '20210616' && $p['meta']['cemeti_end_date'] === '20210724', 'rules: dates read from the text' );
ok( $p['meta']['cemeti_artists'] === "Timoteus\nManshur", 'rules: tags become artists' );
ok( $p['meta']['_cemeti_old_path'] === '2021/06/09/ghost-light' && $p['meta']['_cemeti_head_slugs'] === array( 'ghost-light-eng' ), 'rules: old path from pattern, twin link kept' );
ok( $p['terms']['project'] === array( 'Ghost-light' ), 'rules: other category becomes a project' );
cemeti_convert_post( 21, $rules ); $p = $GLOBALS['db'][21];
ok( $p['type'] === 'journal' && $p['terms']['journal_category'] === array( 'Essay' ) && ! isset( $p['meta']['cemeti_start_date'] ), 'rules: Esai -> Journal / Essay, no dates' );
ok( $p['meta']['_cemeti_lang'] === 'id', 'rules: language stored' );
cemeti_convert_post( 22, $rules ); $p = $GLOBALS['db'][22];
ok( $p['type'] === 'public_program' && $p['meta']['cemeti_start_date'] === '20251007' && $p['meta']['cemeti_speakers'] === 'Nick Yu, John Tain' && $p['meta']['_cemeti_lang'] === 'en', 'rules: public program, date, speakers, language' );
ok( cemeti_convert_post( 23, $rules ) === 'skipped' && $GLOBALS['db'][23]['type'] === 'post', 'rules: TEMPLATE posts skipped and untouched' );
ok( cemeti_convert_post( 24, $rules ) === 'no_date' && $GLOBALS['db'][24]['type'] === 'residency' && $GLOBALS['db'][24]['meta']['cemeti_start_date'] === '20170901', 'rules: no date in text -> publish date, reported' );
cemeti_revert_post( 20 ); $p = $GLOBALS['db'][20];
ok( $p['type'] === 'post' && ! isset( $p['meta']['cemeti_artists'] ) && ! isset( $p['meta']['_cemeti_lang'] ) && ! isset( $p['meta']['_cemeti_head_slugs'] ), 'rules: revert clears everything added' );

// translation pairing
$pairs = cemeti_pair_translations( array(
	1 => array( 'slug' => 'a', 'lang' => 'id', 'links' => array( 'a-eng' ) ),
	2 => array( 'slug' => 'a-eng', 'lang' => 'en', 'links' => array( 'a' ) ),
	3 => array( 'slug' => 'b', 'lang' => 'id', 'links' => array( 'b-eng' ) ),
	4 => array( 'slug' => 'b-eng', 'lang' => 'en', 'links' => array() ),
	5 => array( 'slug' => 'c', 'lang' => 'id', 'links' => array( 'c-eng', 'd-eng' ) ),
	6 => array( 'slug' => 'c-eng', 'lang' => 'en', 'links' => array() ),
	7 => array( 'slug' => 'd-eng', 'lang' => 'en', 'links' => array() ),
	8 => array( 'slug' => 'e', 'lang' => 'id', 'links' => array( 'e2' ) ),
	9 => array( 'slug' => 'e2', 'lang' => 'id', 'links' => array() ),
) );
ok( in_array( array( 1, 2 ), $pairs, true ) && in_array( array( 3, 4 ), $pairs, true ), 'pairs: mutual and unambiguous one-way links' );
ok( ! in_array( 5, array_merge( ...$pairs ), true ), 'pairs: ambiguous one-way link not guessed' );
ok( ! in_array( 8, array_merge( ...$pairs ), true ), 'pairs: same language never paired' );

// date reader
foreach ( array(
	array( '07 Juli - 07 Agustus 2011', '20110707', '20110807' ),
	array( 'Tanggal : 21 Juni - 26 Juli 2014 Tempat', '20140621', '20140726' ),
	array( '<p>8 – 29 January 2026</p>', '20260108', '20260129' ),
	array( '27 November 2024 – 18 January 2025', '20241127', '20250118' ),
	array( 'June 16th - July 24th 2021', '20210616', '20210724' ),
	array( 'Tuesday, October 7th 2025 14.00 - 16.00 WIB', '20251007', '20251007' ),
	array( '25 Januari 2024 14.00 - 16.00 WIB', '20240125', '20240125' ),
	array( '6 Maret - 18 April 2026', '20260306', '20260418' ),
) as $c ) {
	$r = cemeti_extract_date_range( $c[0], '' );
	ok( $r === array( $c[1], $c[2] ), 'dates: ' . substr( strip_tags( $c[0] ), 0, 40 ) . ' => ' . ( $r ? $r[0] . '..' . $r[1] : 'null' ) );
}
ok( cemeti_extract_date_range( 'tahun 2414 dan 24 Januari 2414', '2020-01-01' ) === null, 'dates: absurd year rejected' );
ok( cemeti_extract_date_range( 'Tidak ada tanggal di sini', '' ) === null, 'dates: none found' );
ok( cemeti_extract_deadline( 'Deadline: 14 August 2025. Residency 1 October - 30 November 2025', '2025-05-01' ) === '20250814', 'deadline: word "Deadline" wins over the period' );
echo $fail ? "\n$fail FAILED\n" : "\nALL OK\n"; exit( $fail ? 1 : 0 );
