<?php
// Run: php tests/flows-pages.php — the fixed pages (About, Open Archive, Support Us, Contact Us): created, kept, rendered.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();

$slugs = array( 'about', 'open-archive', 'support-us', 'patron-of-cemeti', 'friends-of-cemeti', 'annual-benefit', 'collaboration', 'contact-us' );
$by_slug = static function ( $slug ) {
	foreach ( CemetiWP::$posts as $id => $p ) {
		if ( 'page' === $p['post_type'] && $slug === $p['post_name'] ) {
			return $id;
		}
	}
	return 0;
};

/* ---- a fresh install has none of them: every menu link would be "page not found" */
ok( 8 === count( cemeti_missing_pages() ), 'fresh site: all 8 pages are missing (' . count( cemeti_missing_pages() ) . ')' );
foreach ( array( 'about', 'open-archive', 'support-us', 'contact-us' ) as $slug ) {
	ok( false !== strpos( cemeti_default_menu_paths(), "|$slug|" ), "menu links to /$slug/" );
}
function cemeti_default_menu_paths() {
	$paths = '|';
	foreach ( cemeti_default_menu() as $column ) {
		foreach ( $column as $group ) {
			foreach ( $group['items'] as $item ) {
				$paths .= $item['path'] . '|';
			}
		}
	}
	return $paths;
}

/* ---- activation creates them */
do_action( 'after_switch_theme' );
foreach ( $slugs as $slug ) {
	$id = $by_slug( $slug );
	ok( $id && 'publish' === CemetiWP::$posts[ $id ]['post_status'], "activation: page /$slug/ exists and is published" );
	ok( $id && $slug === get_post_meta( $id, "_cemeti_page_key", true ), "page /$slug/ remembers its key" );
}
$support = $by_slug( 'support-us' );
foreach ( array( 'patron-of-cemeti', 'friends-of-cemeti', 'annual-benefit', 'collaboration' ) as $child ) {
	ok( $support === (int) CemetiWP::$posts[ $by_slug( $child ) ]['post_parent'], "$child is a row of Support Us" );
}
ok( array( 1, 2, 3, 4 ) === array_map( static function ( $c ) use ( $by_slug ) { return (int) CemetiWP::$posts[ $by_slug( $c ) ]['menu_order']; }, array( 'patron-of-cemeti', 'friends-of-cemeti', 'annual-benefit', 'collaboration' ) ), 'Support Us rows keep their order' );
ok( 'Open Archive' === CemetiWP::$posts[ $by_slug( 'open-archive' ) ]['post_title'] && 'Contact Us' === CemetiWP::$posts[ $by_slug( 'contact-us' ) ]['post_title'], 'titles are the menu names' );
ok( ! cemeti_missing_pages(), 'nothing is missing afterwards' );

/* ---- running again never duplicates or overwrites */
$about = $by_slug( 'about' );
CemetiWP::$posts[ $about ]['post_title'] = 'Tentang Cemeti';
CemetiWP::$posts[ $about ]['post_status'] = 'draft';
$count = count( CemetiWP::$posts );
ok( 0 === cemeti_create_missing_pages() && $count === count( CemetiWP::$posts ), 'second run creates nothing, a draft counts as existing' );
ok( 'Tentang Cemeti' === CemetiWP::$posts[ $about ]['post_title'], 'an edited page is left alone' );
unset( CemetiWP::$posts[ $by_slug( 'contact-us' ) ] );
ok( 1 === count( cemeti_missing_pages() ) && 1 === cemeti_create_missing_pages() && $by_slug( 'contact-us' ), 'a deleted page is created again' );

/* ---- admin notice and button */
CemetiWP::$caps = true;
CemetiWP::$ctx = array( 'screen_id' => 'dashboard' );
ob_start(); cemeti_missing_pages_notice(); $quiet = ob_get_clean();
ok( '' === $quiet, 'no notice when every page exists' );
unset( CemetiWP::$posts[ $by_slug( 'open-archive' ) ] );
ob_start(); cemeti_missing_pages_notice(); $notice = ob_get_clean();
ok( false !== strpos( $notice, 'open-archive' ) && false !== strpos( $notice, 'cemeti_create_pages' ) && false !== strpos( $notice, '_wpnonce' ), 'notice names the missing page and has a nonce link' );
CemetiWP::$ctx = array( 'screen_id' => 'edit-post' );
ob_start(); cemeti_missing_pages_notice(); ok( '' === ob_get_clean(), 'notice only on Dashboard, Themes and Cemeti settings' );
CemetiWP::$ctx = array( 'screen_id' => 'dashboard' );
CemetiWP::$caps = false;
ob_start(); cemeti_missing_pages_notice(); ok( '' === ob_get_clean(), 'notice is for administrators only' );
try { cemeti_handle_create_pages(); ok( false, 'button: refused without permission' ); } catch ( CemetiDie $e ) { ok( true, 'button: refused without permission' ); }
CemetiWP::$caps = true;
$_GET = $_REQUEST = array();
try { cemeti_handle_create_pages(); ok( false, 'button: needs a nonce' ); } catch ( CemetiDie $e ) { ok( ! $by_slug( 'open-archive' ), 'button: needs a nonce, nothing created' ); }
$_GET = $_REQUEST = array( '_wpnonce' => 'valid' );
try { cemeti_handle_create_pages(); ok( false, 'button: redirects' ); } catch ( CemetiRedirect $r ) { ok( $by_slug( 'open-archive' ) && false !== strpos( $r->url, 'cemeti_pages_created=1' ), 'button: recreates the page and goes back to Settings' ); }

/* ---- the template follows the key, not the slug */
$odd = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about-2', 'meta' => array( '_cemeti_page_key' => 'about' ) ) );
$plain = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Free page', 'post_name' => 'free' ) );
CemetiWP::request( '/about-2/', array( 'singular' => true ) );
$GLOBALS['post'] = CemetiWP::obj( $odd );
ok( CEMETI_THEME_DIR . '/page-about.php' === cemeti_page_template_by_key( '/theme/page.php' ), 'page "about-2" still uses page-about.php' );
$GLOBALS['post'] = CemetiWP::obj( $plain );
ok( '/theme/page.php' === cemeti_page_template_by_key( '/theme/page.php' ), 'a free page keeps WordPress\' own template' );

/* ---- every page renders with real content around */
$ids = qa_seed();
$render = static function ( $slug, $template ) use ( $by_slug ) {
	$id = $by_slug( $slug );
	CemetiWP::$posts[ $id ]['post_status'] = 'publish';
	$html = qa_render( $template, array( 'singular' => true ), array( 'post' => $id, 'path' => "/$slug/" ) );
	$problems = qa_check_html( $html );
	ok( ! $problems, "/$slug/ renders cleanly: " . implode( ' | ', array_slice( $problems, 0, 4 ) ) );
	ok( 1 === preg_match( '/<h1[^>]*>[^<]+<\/h1>/', $html ), "/$slug/ has a heading" );
	return $html;
};
$h = $render( 'open-archive', 'page-open-archive.php' );
ok( false !== strpos( $h, 'cemeti-az__letter' ) && false !== strpos( $h, 'Harta yang Paling Berharga' ), 'Open Archive lists the entries A-Z' );
$h = $render( 'support-us', 'page-support-us.php' );
ok( 4 === substr_count( $h, '<summary>' ) && false !== strpos( $h, 'Patron of Cemeti' ) && false !== strpos( $h, 'Segera hadir' ), 'Support Us shows its four rows' );
$h = $render( 'contact-us', 'page-contact-us.php' );
ok( false !== strpos( $h, 'cemeti-contact' ) && false !== strpos( $h, 'info@cemeti.art' ) && false !== strpos( $h, 'Jl.' ), 'Contact Us shows address, hours and e-mail' );
$h = $render( 'about', 'page-about.php' );
ok( false !== strpos( $h, 'cemeti-single--about' ), 'About renders' );

// An empty site (no entries at all) still renders the archive page.
qa_reset_data();
do_action( 'after_switch_theme' );
$id = $by_slug( 'open-archive' );
$html = qa_render( 'page-open-archive.php', array( 'singular' => true ), array( 'post' => $id, 'path' => '/open-archive/' ) );
ok( ! qa_check_html( $html ) && false !== strpos( $html, 'cemeti-az' ), 'Open Archive on an empty site renders without entries' );

qa_done();
