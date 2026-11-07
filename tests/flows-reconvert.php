<?php
// Run: php tests/flows-reconvert.php — "Revert all" then "Convert" again is safe: the text comes back exactly, nothing is
// repeated, and the second conversion equals the first.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
qa_reset_data();
CemetiWP::$caps = true;
add_filter( 'cemeti_restructure_image_id', static function ( $id, $img ) { return $id ? $id : (int) preg_replace( '/\D/', '', (string) $img->getAttribute( 'class' ) ); }, 10, 2 );

$cat = static function ( $slug, $name ) {
	$e = term_exists( $slug, 'category' );
	if ( $e ) { return $e['term_id']; }
	CemetiWP::$next_term++;
	$id = CemetiWP::$next_term + 5000;
	CemetiWP::$terms['category'][ $id ] = array( 'name' => $name, 'slug' => $slug, 'description' => '' );
	return $id;
};
$old = static function ( $title, $slug, $date, array $cats, $body, array $tags = array() ) use ( $cat ) {
	$id = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => $title, 'post_name' => $slug, 'post_date' => $date . ' 10:00:00', 'post_content' => $body ) );
	CemetiWP::$rel[ $id ]['category'] = array_map( static function ( $c ) use ( $cat ) { return $cat( $c, ucwords( str_replace( '-', ' ', $c ) ) ); }, $cats );
	if ( $tags ) { wp_set_object_terms( $id, $tags, 'post_tag' ); }
	return $id;
};
$G = static function ( $n ) { return '<figure class="wp-block-image"><img src="https://cemeti.art/wp-content/uploads/2022/01/g' . $n . '.jpg" class="wp-image-' . $n . '"></figure>'; };
$body = '<div class="wp-block-group"><h1>Ghost Light</h1><p>Pameran Tunggal 16 Juni 2021. Teks pengantar yang panjang.</p><h2>Foto Karya</h2>' . $G( 21 ) . $G( 22 ) . '<p>Tampak karya.</p><h2>Dokumentasi</h2>' . $G( 31 ) . '<figure class="wp-block-embed"><iframe src="https://www.youtube.com/embed/x"></iframe></figure><h2>Biografi Seniman</h2><h3>TIMOTEUS KUSNO</h3>' . $G( 41 ) . '<p>Lahir 1989.</p><h2>Terbitan Terkait</h2><p><a href="/x/">x</a></p></div><p><a href="https://cemeti.art/wp-content/uploads/b.pdf">Unduh buklet</a></p>';
$a = $old( 'Ghost Light', 'ghost-light', '2021-06-09', array( 'pameran-tunggal', 'pameran' ), $body, array( 'Timoteus Kusno' ) );
$b = $old( 'Open Studio: A', 'open-studio-a', '2025-04-01', array( 'open-studio' ), '<p>8 - 29 Januari 2026</p><h2>Dokumentasi</h2>' . $G( 51 ), array( 'A' ) );
$c = $old( 'Pengumuman', 'pengumuman', '2024-12-20', array( 'pengumuman' ), '<p>Kami libur.</p>' );
$d = $old( 'Tanpa foto', 'tanpa', '2020-01-01', array( 'uncategorized' ), '<p>Isi.</p>' );
$ids = array( $a, $b, $c, $d );

$snap = static function () use ( $ids ) {
	$out = array();
	foreach ( $ids as $id ) {
		$meta = isset( CemetiWP::$meta[ $id ] ) ? CemetiWP::$meta[ $id ] : array();
		unset( $meta['_cemeti_converted_at'] );
		ksort( $meta );
		$rel = isset( CemetiWP::$rel[ $id ] ) ? CemetiWP::$rel[ $id ] : array();
		$rel = array_filter( $rel ); // An emptied list and no list are the same.
		ksort( $rel );
		$out[ $id ] = array( 'type' => CemetiWP::$posts[ $id ]['post_type'], 'content' => CemetiWP::$posts[ $id ]['post_content'], 'title' => CemetiWP::$posts[ $id ]['post_title'], 'slug' => CemetiWP::$posts[ $id ]['post_name'], 'meta' => $meta, 'rel' => $rel );
	}
	return $out;
};
$run = static function ( $mode ) {
	$_GET = array( 'mode' => $mode, '_wpnonce' => 'valid' );
	for ( $i = 0; $i < 10; $i++ ) {
		try { do_action( 'admin_post_cemeti_convert_run' ); } catch ( CemetiRedirect $e ) {
			parse_str( (string) parse_url( $e->url, PHP_URL_QUERY ), $q );
			if ( (int) $q['remaining'] < 1 ) { return $q; }
		}
	}
	return array();
};

$before = $snap();
$run( 'convert' );
$first = $snap();
ok( 'exhibition' === $first[ $a ]['type'] && ! empty( $first[ $a ]['meta']['cemeti_gallery'] ) && ! empty( $first[ $a ]['meta']['cemeti_biographies'] ), 'converted once: exhibition with gallery and biographies' );
ok( false === strpos( $first[ $a ]['content'], 'Terbitan Terkait' ) && false === strpos( $first[ $a ]['content'], 'Foto Karya' ) && 1 === substr_count( $first[ $a ]['content'], 'Teks pengantar' ), 'converted once: the text is not repeated' );

// Pressing Convert again (without Revert) changes nothing.
$run( 'convert' );
ok( $first === $snap(), 'Convert pressed again: nothing changes, nothing repeats' );

// Revert all: every post is exactly as imported.
$run( 'revert' );
$after_revert = $snap();
foreach ( $ids as $id ) {
	ok( $before[ $id ] === $after_revert[ $id ], 'Revert all: "' . $before[ $id ]['title'] . '" is exactly as imported' . ( $before[ $id ] === $after_revert[ $id ] ? '' : ' ' . json_encode( array_diff_assoc( array_map( 'json_encode', $after_revert[ $id ] ), array_map( 'json_encode', $before[ $id ] ) ) ) ) );
}

// Convert again: the same result as the first time.
$run( 'convert' );
$second = $snap();
foreach ( $ids as $id ) {
	ok( $first[ $id ] === $second[ $id ], 'Convert after Revert: "' . $first[ $id ]['title'] . '" equals the first conversion' . ( $first[ $id ] === $second[ $id ] ? '' : ' ' . json_encode( array_diff_assoc( array_map( 'json_encode', $second[ $id ] ), array_map( 'json_encode', $first[ $id ] ) ) ) ) );
}

// Many times over: still one copy of everything.
for ( $i = 0; $i < 3; $i++ ) { $run( 'revert' ); $run( 'convert' ); }
ok( $first === $snap(), 'three more Revert + Convert rounds: identical, no growth' );

// A post whose text was already shortened must not lose its original when the split runs a second time.
$orig = CemetiWP::$meta[ $a ]['_cemeti_original_content'];
CemetiWP::$posts[ $a ]['post_content'] = '<h2>Dokumentasi</h2>' . $G( 99 ) . '<p>Added later by hand.</p>';
cemeti_restructure_post( $a, 'exhibition' );
ok( $orig === CemetiWP::$meta[ $a ]['_cemeti_original_content'], 'splitting an already split post never overwrites the saved original text' );
qa_done();
