<?php
// Run: php tests/flows-languages.php — every template in English and in Indonesian must be the same design.
// Renders each page twice with Polylang "active" and compares the structure (tags and classes, no words).
// Also writes build/qa/pair-<name>-en.html / -id.html for the browser test that compares sizes and positions.
require __DIR__ . '/harness/bootstrap.php';

$GLOBALS['pll'] = array( 'current' => 'en', 'registered' => array(), 'lang' => array(), 'groups' => array(), 'override' => array() );
function pll_default_language() { return 'en'; }
function pll_current_language() { return $GLOBALS['pll']['current']; }
function pll_home_url( $lang = null ) { $l = $lang ? $lang : $GLOBALS['pll']['current']; return 'http://cemeti.test/' . ( 'en' === $l ? '' : $l . '/' ); }
function pll__( $s ) { return $s; }
function pll_register_string() {}
function pll_the_languages() { $c = $GLOBALS['pll']['current']; return array( 'en' => array( 'slug' => 'en', 'url' => 'http://cemeti.test/', 'current_lang' => 'en' === $c ), 'id' => array( 'slug' => 'id', 'url' => 'http://cemeti.test/id/', 'current_lang' => 'id' === $c ) ); }
function pll_set_post_language( $id, $lang ) { $GLOBALS['pll']['lang'][ $id ] = $lang; }
function pll_get_post_language( $id ) { return $GLOBALS['pll']['lang'][ $id ] ?? ''; }
function pll_save_post_translations( $arr ) { $GLOBALS['pll']['groups'][] = $arr; }
function pll_get_post_translations( $id ) { foreach ( $GLOBALS['pll']['groups'] as $g ) { if ( in_array( $id, $g, true ) ) { return $g; } } return array(); }
function pll_get_post( $id, $lang ) { foreach ( $GLOBALS['pll']['groups'] as $g ) { if ( in_array( $id, $g, true ) && isset( $g[ $lang ] ) ) { return $g[ $lang ]; } } return 0; }
function pll_languages_list() { return array( 'en', 'id' ); }

qa_boot();
$ids = qa_seed();
$out = dirname( __DIR__ ) . '/build/qa';
@mkdir( $out, 0777, true ); // phpcs:ignore

$skeleton = static function ( $html ) {
	$html = preg_replace( '#<(script|style)\b.*?</\1>#s', '', $html );
	$html = preg_replace( '#\s(href|src|srcset|sizes|alt|title|aria-label|aria-labelledby|placeholder|value|data-[a-z-]+|id|for|name|content|aria-current|action|hreflang|lang)="[^"]*"#', '', $html );
	$html = preg_replace( '#>[^<]+<#', '><', $html );
	return preg_replace( '#\s+#', ' ', $html );
};
$compare = static function ( $label, $template, $ctx, $o_en, $o_id = null ) use ( $skeleton, $out ) {
	$o_id = null === $o_id ? $o_en : $o_id;
	try {
		$GLOBALS['pll']['current'] = 'en';
		$en = qa_render( $template, $ctx, $o_en );
		$GLOBALS['pll']['current'] = 'id';
		$id = qa_render( $template, $ctx, $o_id );
	} catch ( Throwable $e ) {
		ok( false, "$label: threw " . $e->getMessage() );
		return;
	}
	$GLOBALS['pll']['current'] = 'en';
	$a = $skeleton( $en );
	$b = $skeleton( $id );
	$at = '';
	if ( $a !== $b ) {
		$i = 0;
		while ( $i < strlen( $a ) && $i < strlen( $b ) && $a[ $i ] === $b[ $i ] ) {
			++$i;
		}
		$at = ' — differs at EN "' . substr( $a, max( 0, $i - 50 ), 110 ) . '" / ID "' . substr( $b, max( 0, $i - 50 ), 110 ) . '"';
	}
	ok( $a === $b, "same design in EN and ID: $label" . $at );
	$name = preg_replace( '/[^a-z0-9]+/', '-', strtolower( $label ) );
	file_put_contents( "$out/pair-$name-en.html", $en );
	file_put_contents( "$out/pair-$name-id.html", $id );
};

$arch = static function ( $type, array $get = array() ) {
	return array( array( 'pta' => $type ), array( 'path' => '/' . $type . '/', 'query' => array( 'post_type' => $type ), 'flags' => array( 'pta' => $type ), 'get' => $get ) );
};

/* ---- the fixed pages: the Indonesian copy has its own slug and is linked as a translation */
do_action( 'after_switch_theme' );
$pair = static function ( $en_slug, $id_slug, $id_title ) {
	$en = 0;
	foreach ( CemetiWP::$posts as $pid => $p ) {
		if ( 'page' === $p['post_type'] && $en_slug === $p['post_name'] ) {
			$en = $pid;
		}
	}
	$idp = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => $id_title, 'post_name' => $id_slug, 'post_content' => CemetiWP::$posts[ $en ]['post_content'] ) );
	pll_save_post_translations( array( 'en' => $en, 'id' => $idp ) );
	return array( $en, $idp );
};
$about = $pair( 'about', 'tentang', 'Tentang' );
$archive = $pair( 'open-archive', 'arsip-terbuka', 'Arsip Terbuka' );
$support = $pair( 'support-us', 'dukung-kami', 'Dukung Kami' );
$contact = $pair( 'contact-us', 'hubungi-kami', 'Hubungi Kami' );
foreach ( array( 'about' => array( 'page-about.php', $about ), 'open archive' => array( 'page-open-archive.php', $archive ), 'support us' => array( 'page-support-us.php', $support ), 'contact us' => array( 'page-contact-us.php', $contact ) ) as $label => $def ) {
	$compare( "page $label", $def[0], array( 'singular' => true ), array( 'post' => $def[1][0], 'path' => '/' . $label . '/' ), array( 'post' => $def[1][1], 'path' => '/id/' . $label . '/' ) );
	// The Indonesian page is recognised as the same design (template chosen by the theme, not by its slug).
	ok( true && '' !== cemeti_page_key( $def[1][1] ), "the Indonesian copy of $label is recognised as a Cemeti page" );
}

/* ---- Home, archives, singles, search, 404 */
$compare( 'home', 'front-page.php', array( 'front' => true ), array() );
foreach ( array( 'exhibition', 'residency', 'public_program', 'open_studio', 'journal', 'open_call' ) as $type ) {
	$tpl = 'residency' === $type ? 'archive-residency.php' : ( 'journal' === $type ? 'archive-journal.php' : ( 'open_call' === $type ? 'archive-open_call.php' : 'archive.php' ) );
	$a   = $arch( $type );
	$compare( "archive $type", $tpl, $a[0], $a[1] );
}
$a = $arch( 'exhibition', array( 'type' => 'solo' ) );
$compare( 'archive exhibition solo', 'archive.php', $a[0], $a[1] );
$a = $arch( 'residency', array( 'type' => 'research' ) );
$compare( 'archive residency research', 'archive-residency.php', $a[0], $a[1] );

$res = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Residency full', 'post_content' => 'Foreword', 'thumb' => 501, 'meta' => array( 'cemeti_start_date' => '20251001', 'cemeti_end_date' => '20251130', 'cemeti_artists' => "R One\nR Two", 'cemeti_gallery' => array( 601, 602, 603, 604 ), 'cemeti_documentation' => array( 611, 612 ), 'cemeti_biographies' => array( array( 'name' => 'R One', 'photo' => 701, 'bio' => 'Bio' ) ), 'cemeti_booklet_url' => 'https://example.org/b.pdf' ) ) );
$pp  = CemetiWP::post( array( 'post_type' => 'public_program', 'post_title' => 'Talk', 'post_content' => 'Foreword', 'thumb' => 501, 'meta' => array( 'cemeti_start_date' => '20260130', 'cemeti_end_date' => '20260130', 'cemeti_speakers' => 'Speaker', 'cemeti_time_text' => '15.00', 'cemeti_documentation' => array( 611, 612 ), 'cemeti_biographies' => array( array( 'name' => 'Speaker', 'photo' => 701, 'bio' => 'Bio' ) ) ) ) );
foreach ( array( 'exhibition detail' => array( 'single.php', $ids['ex_current'], '/exhibition/x/' ), 'residency detail' => array( 'single.php', $res, '/residency/x/' ), 'public program detail' => array( 'single.php', $pp, '/public-program/x/' ), 'open studio detail' => array( 'single.php', $ids['os_current'], '/open-studio/x/' ), 'journal detail' => array( 'single-journal.php', $ids['j_essay'], '/journal/x/' ), 'open call detail' => array( 'single-open_call.php', $ids['oc_external'], '/open-call/x/' ) ) as $label => $d ) {
	$compare( $label, $d[0], array( 'singular' => true ), array( 'post' => $d[1], 'path' => $d[2] ) );
}
$compare( 'search results', 'search.php', array( 'search' => true ), array( 'path' => '/?s=cemeti', 'query' => array( 's' => 'Gelap' ), 'flags' => array( 'search' => true ), 'get' => array( 's' => 'Gelap' ) ) );
$compare( 'search empty', 'search.php', array( 'search' => true ), array( 'path' => '/?s=zzz', 'query' => array( 's' => 'zzzzz' ), 'flags' => array( 'search' => true ), 'get' => array( 's' => 'zzzzz' ) ) );
$compare( 'not found', '404.php', array( '404' => true ), array( 'path' => '/nope/' ) );

/* ---- Support Us rows are the same in both languages */
$GLOBALS['pll']['current'] = 'id';
ok( 4 === count( cemeti_support_rows( $support[1] ) ), 'Support Us: the Indonesian page without rows of its own shows the four rows' );
$GLOBALS['pll']['current'] = 'en';
ok( 4 === count( cemeti_support_rows( $support[0] ) ), 'Support Us: the English page keeps its own rows' );

/* ---- QA round 3: no English UI left on the Indonesian pages (program and menu names stay English by design) */
$idhome = (string) file_get_contents( $out . '/pair-home-id.html' );
ok( false !== strpos( $idhome, 'Exhibition Sebelumnya' ) && false !== strpos( $idhome, 'Journal Sebelumnya' ) && false === strpos( $idhome, 'Previous ' ), 'Indonesian home: the "previous" buttons are translated' );
ok( false !== strpos( (string) file_get_contents( $out . '/pair-archive-exhibition-id.html' ), 'aria-label="Navigasi halaman"' ), 'Indonesian archive: pagination label is translated' );

qa_done();
