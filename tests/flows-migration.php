<?php
// Run: php tests/flows-migration.php — the converter end to end on a synthetic copy of the old site
// (same patterns as the real export: Indonesian/English twins, "_" category suffix, dates in the text).
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
qa_reset_data();

$cat = static function ( $slug, $name ) {
	$e = term_exists( $slug, 'category' );
	if ( $e ) {
		return $e['term_id'];
	}
	CemetiWP::$next_term++;
	$id = CemetiWP::$next_term + 5000;
	CemetiWP::$terms['category'][ $id ] = array( 'name' => $name, 'slug' => $slug, 'description' => '' );
	return $id;
};
$old = static function ( $title, $slug, $date, array $cats, $body = '', array $tags = array(), $status = 'publish' ) use ( $cat ) {
	$id = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => $title, 'post_name' => $slug, 'post_date' => $date . ' 10:00:00', 'post_content' => $body, 'post_status' => $status ) );
	$ids = array();
	foreach ( $cats as $c ) {
		$ids[] = $cat( $c, ucwords( str_replace( '-', ' ', rtrim( $c, '_' ) ) ) . ( '_' === substr( $c, -1 ) ? '_' : '' ) );
	}
	CemetiWP::$rel[ $id ]['category'] = $ids;
	if ( $tags ) {
		wp_set_object_terms( $id, $tags, 'post_tag' );
	}
	return $id;
};
$site = 'https://cemeti.art';
$p = array();
$p['ex_id']  = $old( 'Ghost Light', 'ghost-light', '2021-06-09', array( 'pameran-tunggal', 'pameran', 'ghost-light' ), "English | <a href=\"$site/2021/06/09/ghost-light-eng/\">Indonesia</a> Pameran Tunggal Timoteus 16 Juni - 24 Juli 2021", array( 'Timoteus Anggawan Kusno', 'Manshur Zikri' ) );
$p['ex_en']  = $old( 'Ghost Light', 'ghost-light-eng', '2021-06-09', array( 'solo-exhibition', 'exhibition', 'ghost-light_' ), "<a href=\"$site/2021/06/09/ghost-light/\">Indonesia</a> | English Solo Exhibition June 16th - July 24th 2021", array( 'Timoteus Anggawan Kusno' ) );
$p['os']     = $old( 'Open Studio: Nindityo Adipurnomo', 'open-studio-nindityo', '2025-04-01', array( 'open-studio', 'program-publik' ), '8 – 29 January 2026', array( 'Nindityo Adipurnomo' ) );
$p['pp']     = $old( 'Belajar dari yang Dekat: Christine Toelle', 'belajar-christine', '2026-01-20', array( 'program-publik', 'belajar-dari-yang-dekat' ), 'Jumat, 30 Januari 2026 15.00 WIB', array( 'Christine Toelle' ) );
$p['talk']   = $old( 'Desa Bercerita', 'desa-bercerita', '2022-05-01', array( 'diskusi', 'kabar-proyek', 'akar-rimpang' ), 'Diskusi 12 Mei 2022' );
$p['essay']  = $old( 'Memandang Mereka', 'memandang-mereka', '2011-07-07', array( 'esai', 'kabar-teman-rimpang', 'rimpang-nusantara', 'polewali-mandar' ), 'Esai.' );
$p['essay_en'] = $old( 'Looking at Them', 'looking-at-them', '2011-07-07', array( 'essay', 'rimpangs-stories', 'rhizomatic-archipelago', 'polewali-mandar_' ), 'Essay.' );
$p['intro']  = $old( 'Maintenance Works - Pengantar', 'mw-pengantar', '2019-03-01', array( 'pengantar-proyek', 'maintenance-works' ), 'Teks pengantar.' );
$p['call']   = $old( 'Panggilan Terbuka Seniman', 'panggilan-seniman', '2022-10-13', array( 'residensi', 'kabar-program' ), 'Batas waktu: 30 November 2022. Residensi 1 Maret - 30 Mei 2023.' );
$p['res']    = $old( 'Residensi 2018 Periode #1', 'residensi-2018-1', '2018-03-01', array( 'residensi', 'residensi-2018-1' ), 'Tidak ada tanggal acara di teks ini.' );
$p['news']   = $old( 'Pengumuman Libur', 'pengumuman-libur', '2024-12-20', array( 'pengumuman' ), 'Kami libur.' );
$p['plain']  = $old( 'Tanpa Kategori Khusus', 'tanpa-kategori', '2020-01-01', array( 'uncategorized' ), 'Isi.' );
$p['tpl']    = $old( 'TEMPLATE IND', 'template-ind', '2021-06-09', array( 'pameran' ), '', array(), 'draft' );
$p['priv']   = $old( 'Direktur Baru Cemeti', 'direktur-baru', '2023-01-01', array( 'pengumuman' ), 'Private.', array(), 'private' );
for ( $i = 0; $i < 120; $i++ ) {
	$p[ "f$i" ] = $old( "Kabar $i", "kabar-$i", '2023-02-' . sprintf( '%02d', 1 + $i % 27 ), array( 'kabar', 'news_' ), "Kabar nomor $i." );
}
$total = count( $p );

$settings = cemeti_convert_settings();
ok( 'rules' === $settings['mode'] && '%year%/%monthnum%/%day%/%postname%' === $settings['old_permalink'] && 'cemeti.art' === $settings['old_host'], 'defaults: rules mode, cemeti.art permalink pattern and host' );

// ---- preview changes nothing
$preview = cemeti_convert_preview( $settings );
ok( $total === $preview['total'], "preview: counts every post ($total)" );
ok( 1 === $preview['skipped'], 'preview: the TEMPLATE draft is skipped' );
ok( 'post' === CemetiWP::$posts[ $p['ex_id'] ]['post_type'], 'preview: nothing was converted' );
ob_start();
cemeti_convert_page();
$page = ob_get_clean();
ok( false !== strpos( $page, 'Cemeti rules' ) && false !== strpos( $page, 'Preview' ) && ! preg_match( '/(Warning|Notice|Fatal error):/', $page ), 'admin page renders with a preview and no PHP messages' );

// ---- convert in batches through the real admin-post handler
$run = static function ( $mode ) {
	$_GET = array( 'mode' => $mode, '_wpnonce' => 'valid' );
	try {
		do_action( 'admin_post_cemeti_convert_run' );
	} catch ( CemetiRedirect $e ) {
		parse_str( (string) parse_url( $e->url, PHP_URL_QUERY ), $q );
		return $q;
	}
	return array();
};
$steps = 0;
do {
	$q = $run( 'convert' );
	++$steps;
	ok( isset( $q['remaining'] ), 'step ' . $steps . ' reports what remains' );
} while ( (int) $q['remaining'] > 0 && $steps < 20 );
ok( 3 === $steps, "batches of 50: $total posts need three steps (took $steps)" );
ok( array() === cemeti_convert_pending_ids( cemeti_convert_settings() ), 'nothing is left to convert' );
$q = $run( 'convert' );
ok( 0 === (int) $q['done'] && 0 === (int) $q['remaining'], 'running again changes nothing' );

$type = static function ( $id ) { return CemetiWP::$posts[ $id ]['post_type']; };
$term = static function ( $id, $tax ) { $t = get_the_terms( $id, $tax ); return $t ? array_map( static function ( $x ) { return $x->name; }, $t ) : array(); };
$m = static function ( $id, $k ) { return get_post_meta( $id, $k, true ); };
ok( 'exhibition' === $type( $p['ex_id'] ) && array( 'Solo' ) === $term( $p['ex_id'], 'exhibition_type' ), 'Pameran Tunggal becomes Exhibition / Solo' );
ok( 'exhibition' === $type( $p['ex_en'] ) && array( 'Solo' ) === $term( $p['ex_en'], 'exhibition_type' ), 'English twin becomes Exhibition / Solo too' );
ok( '20210616' === $m( $p['ex_id'], 'cemeti_start_date' ) && '20210724' === $m( $p['ex_id'], 'cemeti_end_date' ), 'Indonesian dates read from the text' );
ok( '20210616' === $m( $p['ex_en'], 'cemeti_start_date' ) && '20210724' === $m( $p['ex_en'], 'cemeti_end_date' ), 'English dates read from the text' );
ok( "Timoteus Anggawan Kusno\nManshur Zikri" === $m( $p['ex_id'], 'cemeti_artists' ), 'tags become artists' );
ok( 'id' === $m( $p['ex_id'], '_cemeti_lang' ) && 'en' === $m( $p['ex_en'], '_cemeti_lang' ), 'language detected (Indonesian / English)' );
ok( '2021/06/09/ghost-light' === $m( $p['ex_id'], '_cemeti_old_path' ) && '2021/06/09/ghost-light-eng' === $m( $p['ex_en'], '_cemeti_old_path' ), 'old URL path rebuilt from the date pattern' );
ok( array( 'ghost-light-eng' ) === $m( $p['ex_id'], '_cemeti_head_slugs' ), 'switch link to the twin remembered' );
ok( array( 'Ghost Light' ) === $term( $p['ex_id'], 'project' ), 'other category becomes a Project' );
ok( 'open_studio' === $type( $p['os'] ) && '20260108' === $m( $p['os'], 'cemeti_start_date' ), 'Open Studio wins over Public Program' );
ok( 'public_program' === $type( $p['pp'] ) && '20260130' === $m( $p['pp'], 'cemeti_start_date' ) && 'Christine Toelle' === $m( $p['pp'], 'cemeti_speakers' ), 'Public Program with date and speakers' );
ok( 'public_program' === $type( $p['talk'] ), 'a "Diskusi" post becomes a Public Program (events_as_public_program)' );
ok( 'journal' === $type( $p['essay'] ) && array( 'Essay' ) === $term( $p['essay'], 'journal_category' ) && array( 'Essay' ) === $term( $p['essay_en'], 'journal_category' ), 'Esai / Essay become Journal / Essay' );
ok( array( 'Rimpang Nusantara', 'Polewali Mandar' ) === $term( $p['essay'], 'project' ), 'project categories kept; type and news categories left out: ' . implode( ',', $term( $p['essay'], 'project' ) ) );
ok( ! in_array( 'Polewali Mandar_', $term( $p['essay_en'], 'project' ), true ) && in_array( 'Polewali Mandar', $term( $p['essay_en'], 'project' ), true ), 'the "_" marker is removed from English project names' );
ok( array( 'Curatorial Essay' ) === $term( $p['intro'], 'journal_category' ), 'Pengantar Proyek becomes Curatorial Essay' );
ok( 'open_call' === $type( $p['call'] ) && '20221130' === $m( $p['call'], 'cemeti_deadline' ) && ! isset( CemetiWP::$meta[ $p['call'] ]['cemeti_start_date'] ), 'Open Call: deadline from "Batas waktu", not the residency period' );
ok( 'residency' === $type( $p['res'] ) && '20180301' === $m( $p['res'], 'cemeti_start_date' ), 'Residency without a date in the text falls back to the publish date' );
ok( 'journal' === $type( $p['news'] ) && array( 'News' ) === $term( $p['news'], 'journal_category' ), 'announcement becomes Journal / News' );
ok( 'journal' === $type( $p['plain'] ) && array( 'News' ) === $term( $p['plain'], 'journal_category' ), 'uncategorised post becomes Journal / News' );
ok( 'post' === $type( $p['tpl'] ) && 'draft' === CemetiWP::$posts[ $p['tpl'] ]['post_status'], 'TEMPLATE draft is left alone' );
ok( 'journal' === $type( $p['priv'] ) && 'private' === CemetiWP::$posts[ $p['priv'] ]['post_status'], 'a private post stays private' );
ok( 'journal' === $type( $p['f5'] ) && '' === (string) $m( $p['f5'], 'cemeti_start_date' ), 'journal posts get no event dates' );
$types = array_count_values( array_column( array_map( static function ( $x ) { return array( 't' => $x['post_type'] ); }, CemetiWP::$posts ), 't' ) );
ok( 2 === ( isset( $types['exhibition'] ) ? $types['exhibition'] : 0 ) && 1 === $types['open_studio'] && 2 === $types['public_program'] && 1 === $types['open_call'] && 1 === $types['residency'], 'totals by type: ' . json_encode( $types ) );

// ---- the old URLs keep working
$go = static function ( $uri ) {
	CemetiWP::request( $uri, array( '404' => true ) );
	try {
		do_action( 'template_redirect' );
		return null;
	} catch ( CemetiRedirect $e ) {
		return $e->url;
	}
};
$url = $go( '/2021/06/09/ghost-light-eng/' );
ok( $url && false !== strpos( $url, '/exhibition/ghost-light-eng' ), 'old English post URL → new Exhibition' );
$url = $go( '/2011/07/07/memandang-mereka/' );
ok( $url && false !== strpos( $url, '/journal/memandang-mereka' ), 'old essay URL → new Journal entry' );

// ---- the rules can be changed without editing the theme
add_filter(
	'cemeti_config',
	static function ( $config, $name ) {
		if ( 'migration-rules' === $name ) {
			$config['events_as_public_program'] = false;
		}
		return $config;
	},
	10,
	2
);
$r = cemeti_rules_classify( array( 'title' => 'Desa Bercerita', 'cats' => array( array( 'diskusi', 'Diskusi' ), array( 'kabar-proyek', 'Kabar Proyek' ) ) ) );
ok( 'journal' === $r['type'], 'filter cemeti_config: events can be kept in the Journal without touching the theme' );

// ---- revert
$steps = 0;
do {
	$q = $run( 'revert' );
	++$steps;
} while ( (int) $q['remaining'] > 0 && $steps < 20 );
ok( 'post' === $type( $p['ex_id'] ) && 'post' === $type( $p['f100'] ), 'revert: every post is a normal post again' );
ok( ! isset( CemetiWP::$meta[ $p['ex_id'] ]['cemeti_start_date'] ) && ! isset( CemetiWP::$meta[ $p['ex_id'] ]['_cemeti_old_path'] ) && ! isset( CemetiWP::$meta[ $p['ex_id'] ]['_cemeti_lang'] ) && ! isset( CemetiWP::$meta[ $p['ex_id'] ]['cemeti_artists'] ), 'revert: dates, artists, language and old path are removed' );
ok( 3 === count( CemetiWP::$rel[ $p['ex_id'] ]['category'] ), 'revert: the old categories are untouched' );
ok( array() === $term( $p['ex_id'], 'exhibition_type' ) && array() === $term( $p['ex_id'], 'project' ), 'revert: new terms are detached' );
$again = cemeti_convert_pending_ids( cemeti_convert_settings() );
ok( $total === count( $again ), 'revert: all posts can be converted again' );

// ---- manual mode still works
update_option( 'cemeti_convert_map', array( 'mode' => 'manual', 'cats' => array( $cat( 'esai', 'Esai' ) => array( 'type' => 'journal', 'term' => '__name' ) ), 'start_key' => '', 'end_key' => '', 'old_permalink' => '', 'old_host' => '' ) );
$s = cemeti_convert_settings();
ok( array( $p['essay'] ) === cemeti_convert_pending_ids( $s ), 'manual mode: only posts of the mapped category are picked' );
ok( 'converted' === cemeti_convert_post( $p['essay'], $s ) && 'journal' === $type( $p['essay'] ) && array( 'Esai' ) === $term( $p['essay'], 'journal_category' ), 'manual mode: category name used as the term' );

// ---- saving the settings
$_POST = array( '_wpnonce' => 'valid', 'mode' => 'rules', 'old_permalink' => '%year%/%postname%<script>', 'old_host' => 'cemeti.art/<x>', 'start_key' => 'Start Date!', 'end_key' => '', 'cats' => array( 5001 => array( 'type' => 'bogus', 'term' => 'x' ) ) );
try {
	do_action( 'admin_post_cemeti_convert_save' );
} catch ( CemetiRedirect $e ) {
	ok( true, 'settings: saved and redirected' );
}
$s = cemeti_convert_settings();
ok( 0 === strpos( $s['old_permalink'], '%year%/%postname%' ) && ! preg_match( '/[<>]/', $s['old_permalink'] . $s['old_host'] ) && 'cemeti.art' === $s['old_host'] && 'startdate' === $s['start_key'] && array() === $s['cats'], 'settings: sanitized (characters, unknown type dropped): ' . $s['old_permalink'] . ' | ' . $s['old_host'] );
CemetiWP::$caps = false;
try {
	do_action( 'admin_post_cemeti_convert_save' );
	ok( false, 'settings: non-administrators are refused' );
} catch ( CemetiDie $e ) {
	ok( true, 'settings: non-administrators are refused' );
}
CemetiWP::$caps = true;
$_POST = $_GET = array();
$legacy = get_option( 'cemeti_legacy_category_paths' );
ok( isset( $legacy['category/esai'] ) && 'journal' === $legacy['category/esai']['type'], 'settings: old category URLs recorded for redirects' );
qa_done();
