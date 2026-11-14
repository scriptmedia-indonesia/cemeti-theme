<?php
// Run: php tests/flows-performance.php — picture sizes, the remembered pictures, the page caches.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
qa_reset_data();
$ids = qa_seed();
CemetiWP::$caps = true;

/* ---- picture sizes: a size that was never made falls back to a smaller existing one, not the 3 MB original */
$GLOBALS['att_meta'] = array(
	5001 => array( 'file' => '2022/01/big.jpg', 'width' => 3000, 'height' => 2000, 'sizes' => array(
		'thumbnail' => array( 'file' => 'big-150x150.jpg', 'width' => 150, 'height' => 150 ),
		'medium' => array( 'file' => 'big-300x200.jpg', 'width' => 300, 'height' => 200 ),
		'medium_large' => array( 'file' => 'big-768x512.jpg', 'width' => 768, 'height' => 512 ),
		'large' => array( 'file' => 'big-1024x683.jpg', 'width' => 1024, 'height' => 683 ),
	) ),
	5002 => array( 'file' => '2022/01/small.jpg', 'width' => 500, 'height' => 400, 'sizes' => array( 'medium' => array( 'file' => 's-300x225.jpg', 'width' => 300, 'height' => 225 ) ) ),
);
if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
	function wp_get_attachment_metadata( $id ) { return isset( $GLOBALS['att_meta'][ $id ] ) ? $GLOBALS['att_meta'][ $id ] : false; }
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
}
$orig = array( 'http://cemeti.test/uploads/2022/01/big.jpg', 3000, 2000, false );
$got  = cemeti_image_src_fallback( $orig, 5001, 'cemeti-card' );
ok( 768 === $got[1] && 'big-768x512.jpg' === basename( $got[0] ) && true === $got[3], 'sizes: a missing card size falls back to the smallest existing size that is big enough, not the original' );
$got = cemeti_image_src_fallback( $orig, 5001, 'cemeti-poster' );
ok( 1024 === $got[1], 'sizes: when none is big enough the biggest existing one is used (never the 3000 px original)' );
$real = array( 'http://cemeti.test/uploads/x-616x772.jpg', 616, 772, true );
ok( $real === cemeti_image_src_fallback( $real, 5001, 'cemeti-card' ), 'sizes: a size that exists is left alone' );
ok( array( 'u', 500, 400, false ) === cemeti_image_src_fallback( array( 'u', 500, 400, false ), 5002, 'cemeti-card' ), 'sizes: a small original is left alone' );
ok( $orig === cemeti_image_src_fallback( $orig, 5001, 'large' ), "sizes: other themes' and WordPress' sizes are not touched" );
$a = cemeti_image_attributes( array( 'sizes' => '(max-width: 1200px) 100vw, 1200px' ), null, 'cemeti-poster' );
ok( '(min-width: 48rem) 50vw, 100vw' === $a['sizes'] && 'async' === $a['decoding'], 'sizes: the poster is not fetched larger than it is shown' );
ok( array( 'loading' => 'eager', 'fetchpriority' => 'high' ) === cemeti_image_loading( 0 ) && array( 'loading' => 'eager' ) === cemeti_image_loading( 3 ) && array( 'loading' => 'lazy' ) === cemeti_image_loading( 4 ) && array( 'loading' => 'lazy' ) === cemeti_image_loading( 0, 4, false ), 'loading: the first pictures are eager (the first with priority), later ones and later pages are lazy' );

/* ---- "Make missing picture sizes" */
$GLOBALS['att_meta'][5003] = array( 'file' => '2022/01/has.jpg', 'width' => 3000, 'height' => 2000, 'sizes' => array( 'cemeti-card' => array( 'file' => 'h.jpg', 'width' => 616, 'height' => 772 ), 'cemeti-poster' => array( 'file' => 'h2.jpg', 'width' => 1200, 'height' => 800 ) ) );
$GLOBALS['made'] = array();
if ( ! function_exists( 'wp_update_image_subsizes' ) ) {
	function wp_update_image_subsizes( $id ) { $GLOBALS['made'][] = $id; return array( 'file' => 'x' ); }
}
ok( cemeti_sizes_missing( 5001 ) && ! cemeti_sizes_missing( 5002 ) && ! cemeti_sizes_missing( 5003 ), 'make sizes: only a big picture without the theme sizes counts as missing' );
$before = (int) get_option( 'cemeti_content_version', 0 );
update_option( 'cemeti_sizes_state', array( 'ids' => array( 5001, 5002, 5003 ), 'sized' => 0, 'failed' => 0 ) );
$r = cemeti_sizes_step( 0.0 );
ok( 3 === $r['remaining'] && array() === $GLOBALS['made'], 'make sizes: the time box is respected (nothing made when the time is up, progress kept)' );
$r = cemeti_sizes_step( 5.0 );
ok( 0 === $r['remaining'] && 1 === $r['sized'] && array( 5001 ) === $GLOBALS['made'], 'make sizes: only the picture that lacks sizes is processed' );
ok( (int) get_option( 'cemeti_content_version', 0 ) > $before, 'make sizes: finished work makes the cached pages be built again' );

/* ---- the remembered stand-in picture lives in the entry's meta and is forgotten on save */
$e = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Tanpa gambar', 'meta' => array( 'cemeti_gallery' => array( 601 ) ) ) );
ok( 601 === get_post_thumbnail_id( $e ), 'stand-in picture: the first picture of the gallery' );
ok( 0 === strpos( (string) get_post_meta( $e, '_cemeti_pic', true ), '601:' ), 'stand-in picture: remembered in the entry (no search at the next visit)' );
update_post_meta( $e, 'cemeti_gallery', array( 602 ) );
ok( 601 === get_post_thumbnail_id( $e ), 'stand-in picture: the remembered one is used until the entry is saved' );
do_action( 'save_post', $e );
ok( '' === (string) get_post_meta( $e, '_cemeti_pic', true ) && 602 === get_post_thumbnail_id( $e ), 'stand-in picture: saving the entry forgets it and looks again' );
$n = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Tanpa apa-apa' ) );
ok( 0 === get_post_thumbnail_id( $n ) && 0 === strpos( (string) get_post_meta( $n, '_cemeti_pic', true ), '0:' ), 'stand-in picture: "no picture" is remembered too' );
update_post_meta( $n, 'cemeti_gallery', array( 603 ) );
ok( 0 === get_post_thumbnail_id( $n ), 'stand-in picture: "no picture" is not searched again within the hour' );
update_post_meta( $n, '_cemeti_pic', '0:' . ( time() - 4000 ) );
ok( 603 === get_post_thumbnail_id( $n ), 'stand-in picture: after the hour it is looked for again' );

/* ---- fragments and lists are kept for visitors, never for editors, and flushed by content changes */
CemetiWP::$logged_in = false;
$builds = 0;
$print  = static function () use ( &$builds ) { ++$builds; echo 'built-' . $builds; };
ob_start(); cemeti_cached_fragment( 'x', $print ); $one = ob_get_clean();
ob_start(); cemeti_cached_fragment( 'x', $print ); $two = ob_get_clean();
ok( 'built-1' === $one && 'built-1' === $two && 1 === $builds, 'cache: a visitor gets the kept piece; it is built once' );
do_action( 'save_post', $e );
ob_start(); cemeti_cached_fragment( 'x', $print ); $three = ob_get_clean();
ok( 'built-2' === $three, 'cache: saving content makes it be built again' );
CemetiWP::$logged_in = true;
ob_start(); cemeti_cached_fragment( 'x', $print ); $four = ob_get_clean();
ob_start(); cemeti_cached_fragment( 'x', $print ); $five = ob_get_clean();
ok( 'built-3' === $four && 'built-4' === $five, 'cache: an editor always sees the live page' );
CemetiWP::$logged_in = false;
$calls = 0;
$data  = static function () use ( &$calls ) { ++$calls; return array( 'a' => $calls ); };
cemeti_cached_data( 'd', $data ); $d2 = cemeti_cached_data( 'd', $data );
ok( 1 === $calls && array( 'a' => 1 ) === $d2, 'cache: a list of data is built once too' );
cemeti_cache_flush();
cemeti_cached_data( 'd', $data );
ok( 2 === $calls, 'cache: "Clear cached pages" forgets it' );
update_option( 'cemeti_content_version', 0 );
$v = (int) get_option( 'cemeti_content_version', 0 );
do_action( 'updated_post_meta', 1, $e, '_thumbnail_id' );
ok( (int) get_option( 'cemeti_content_version', 0 ) > $v, 'cache: a featured image set without saving (the bulk tools) also refreshes the pages' );
$v = (int) get_option( 'cemeti_content_version', 0 );
do_action( 'updated_post_meta', 1, $e, 'cemeti_year' );
ok( (int) get_option( 'cemeti_content_version', 0 ) === $v, 'cache: other meta changes do not' );

/* ---- the home page: the same for visitors (kept), live for editors */
$home = static function () { return qa_render( 'front-page.php', array( 'front' => true ) ); };
CemetiWP::$logged_in = false;
cemeti_cache_flush();
$h1 = $home();
CemetiWP::$posts[ $ids['ex_current'] ]['post_title'] = 'Judul Baru Sekali';
$h2 = $home();
ok( false === strpos( $h1, 'Judul Baru Sekali' ) && false === strpos( $h2, 'Judul Baru Sekali' ), 'home: visitors get the kept sections' );
do_action( 'save_post', $ids['ex_current'] );
ok( false !== strpos( $home(), 'Judul Baru Sekali' ), 'home: after a save the new title is there' );
CemetiWP::$posts[ $ids['ex_current'] ]['post_title'] = 'Judul Lain Lagi';
CemetiWP::$logged_in = true;
ok( false !== strpos( $home(), 'Judul Lain Lagi' ), 'home: an editor sees the live page' );
CemetiWP::$logged_in = false;
$h = $home();
ok( 1 === substr_count( $h, 'fetchpriority="high"' ) && 1 === substr_count( $h, 'loading="eager"' ) && substr_count( $h, 'loading="lazy"' ) >= 1, 'home: the logo has the priority, the first poster is not lazy, the others are' );

qa_done();
