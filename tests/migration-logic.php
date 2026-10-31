<?php
// Run: php tests/migration-logic.php  (stubs WordPress; simulates converting and reverting posts).
define( 'ABSPATH', '/' ); define( 'CEMETI_DIR', dirname( __DIR__ ) . '/themes/scriptmedia-cemeti' );
$GLOBALS['db'] = array(); // id => [type, cats, meta, terms, date, title]
function is_admin() { return false; }
function add_action() {} function add_management_page() {}
function sanitize_file_name( $s ) { return $s; }
function wp_get_post_categories( $id ) { return $GLOBALS['db'][ $id ]['cats']; }
function get_permalink( $id ) { return 'https://old.test/' . $GLOBALS['db'][ $id ]['slug'] . '/'; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function update_post_meta( $id, $k, $v ) { $GLOBALS['db'][ $id ]['meta'][ $k ] = $v; }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['db'][ $id ]['meta'][ $k ] ?? ''; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['db'][ $id ]['meta'][ $k ] ); }
function get_post_type( $id ) { return $GLOBALS['db'][ $id ]['type']; }
function set_post_type( $id, $t ) { $GLOBALS['db'][ $id ]['type'] = $t; }
function get_post_time( $f, $g, $id ) { return $GLOBALS['db'][ $id ]['date']; }
function get_cat_name( $c ) { return array( 1 => 'Solo Exhibition', 2 => 'Essay', 3 => 'News', 9 => 'Uncategorized' )[ $c ] ?? ''; }
function wp_set_object_terms( $id, $terms, $tax ) { $GLOBALS['db'][ $id ]['terms'][ $tax ] = $terms; }
function get_object_taxonomies( $t ) { return array( 'exhibition' => array( 'exhibition_type' ), 'journal' => array( 'journal_category' ) )[ $t ] ?? array(); }
function get_post_type_archive_link( $t ) { return 'https://new.test/' . str_replace( '_', '-', $t ) . '/'; }
function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
require CEMETI_DIR . '/inc/core-bridge.php'; require CEMETI_DIR . '/inc/status.php'; require CEMETI_DIR . '/inc/migration.php'; require CEMETI_DIR . '/inc/redirects.php';

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
$settings = array( 'cats' => $map, 'start_key' => 'ev_start', 'end_key' => 'ev_end' );
ok( cemeti_convert_post( 10, $settings ) === 'converted', 'exhibition converted' );
$p = $GLOBALS['db'][10];
ok( $p['type'] === 'exhibition' && $p['meta']['cemeti_start_date'] === '20260306' && $p['meta']['cemeti_end_date'] === '20260418', 'dates from custom fields' );
ok( $p['terms']['exhibition_type'] === array( 'Solo Exhibition' ), 'term from old category name' );
ok( $p['meta']['_cemeti_old_path'] === '2026/03/harta', 'old path saved' );
ok( cemeti_convert_post( 11, $settings ) === 'converted' && $GLOBALS['db'][11]['type'] === 'journal' && ! isset( $GLOBALS['db'][11]['meta']['cemeti_start_date'] ), 'journal gets no dates' );
ok( cemeti_convert_post( 12, $settings ) === 'no_date', 'unreadable start date reported' );
$settings2 = array( 'cats' => $map, 'start_key' => '', 'end_key' => '' );
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
echo $fail ? "\n$fail FAILED\n" : "\nALL OK\n"; exit( $fail ? 1 : 0 );
