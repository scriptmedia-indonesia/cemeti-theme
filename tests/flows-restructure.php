<?php
// Run: php tests/flows-restructure.php — the old text split into gallery / documentation / biographies / booklet.
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
qa_reset_data();
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
// In the real importer the address finds the attachment; here the wp-image-ID class stands in for it.
add_filter( 'cemeti_restructure_image_id', static function ( $id, $img ) { return $id ? $id : (int) preg_replace( '/\D/', '', (string) $img->getAttribute( 'class' ) ); }, 10, 2 );
CemetiWP::$caps = true;

$img = static function ( $n ) { return '<figure class="wp-block-image"><img src="https://cemeti.art/wp-content/uploads/2021/06/p' . $n . '.jpg?w=1024" class="wp-image-' . $n . '" alt=""></figure>'; };
$old = '<!-- wp:paragraph --><p class="has-text-align-right"><a href="https://cemeti.art/en/">English</a> | <a href="https://cemeti.art/id/">Indonesia</a></p><!-- /wp:paragraph -->'
	. '<h6>Daftar Isi</h6><p><u>DAFTAR ISI</u><br><a href="#a">Pernyataan Kuratorial</a> | <a href="#b">Foto Karya</a> | <a href="#c">Dokumentasi</a></p>'
	. $img( 12542 ) . '<div class="wp-block-spacer" style="height:10px"></div>'
	. '<h1>Ghost Light</h1><p>Pameran Tunggal Timoteus 16 Juni 2021.</p>'
	. '<h2 id="a">Pernyataan Kuratorial</h2><p>Statement text, long enough to be the foreword.</p><p>Panduan: <a href="https://cemeti.art/wp-content/uploads/guide.pdf">Unduh buklet</a></p><p class="has-text-align-right"><a href="#daftar-isi">Kembali ke Daftar Isi</a></p>'
	. '<h2 id="b">Foto Karya</h2>' . $img( 21 ) . $img( 22 ) . '<figure class="wp-block-gallery"><ul><li>' . $img( 23 ) . '</li><li>' . $img( 24 ) . '</li></ul></figure><p>Tampak karya, 2021.</p><p class="has-text-align-right"><a href="#x">⇪</a></p>'
	. '<h2 id="c">Dokumentasi Pameran</h2>' . $img( 31 ) . $img( 32 ) . '<p>Dokumentasi pembukaan.</p>'
	. '<h2>Biografi Seniman</h2>' . $img( 41 ) . '<p><strong>TIMOTEUS ANGGAWAN KUSNO</strong> (lahir 1989) adalah seorang seniman.</p><p>Dia tinggal di Yogyakarta.</p>' . $img( 42 ) . '<p><strong>Second Artist</strong> lives in Jakarta.</p>'
	. '<h2>Terbitan Terkait</h2><p><a href="/x/">Related thing</a></p>';

/* ---- the parser on its own */
$s = cemeti_restructure_content( $old, 12542 );
ok( null !== $s && array( 'toc', 'gallery', 'documentation', 'biography', 'related' ) === array_values( array_unique( array( 'toc', 'gallery', 'documentation', 'biography', 'related' ) ) ) && in_array( 'biography', $s['found'], true ), 'sections are recognised (' . implode( ',', $s['found'] ) . ')' );
ok( array( 21, 22, 23, 24 ) === $s['gallery'], 'gallery: plain pictures and a gallery block, in order (' . implode( ',', $s['gallery'] ) . ')' );
ok( array( 31, 32 ) === $s['documentation'] && 'Dokumentasi pembukaan.' === $s['documentation_caption'], 'documentation: pictures and the one-line caption' );
ok( 'Tampak karya, 2021.' === $s['gallery_caption'], 'gallery: caption' );
ok( 2 === count( $s['biographies'] ) && 'Timoteus Anggawan Kusno' === $s['biographies'][0]['name'] && 41 === $s['biographies'][0]['photo'] && 42 === $s['biographies'][1]['photo'], 'biographies: two artists, names in normal case, each with its photo' );
ok( false !== strpos( $s['biographies'][0]['bio'], 'seniman' ) && false !== strpos( $s['biographies'][0]['bio'], 'Yogyakarta' ) && 'Second Artist' === $s['biographies'][1]['name'], 'biographies: following paragraphs join the same artist' );
ok( 'https://cemeti.art/wp-content/uploads/guide.pdf' === $s['booklet'], 'booklet: the first PDF link of the text' );
ok( false !== strpos( $s['foreword'], 'Statement text' ) && false !== strpos( $s['foreword'], 'Pameran Tunggal Timoteus' ) && false !== strpos( $s['foreword'], '<h1>Ghost Light</h1>' ), 'foreword: the statement and the opening lines stay' );
foreach ( array( 'Daftar Isi', 'English', 'Foto Karya', 'Terbitan Terkait', 'Related thing', 'Kembali ke', 'wp-image-12542', 'wp-block-spacer', 'Biografi' ) as $gone ) {
	ok( false === strpos( $s['foreword'], $gone ), "foreword: no \"$gone\"" );
}
ok( null === cemeti_restructure_content( '<p>Just text without any section headings.</p>' ), 'a post without section headings is left untouched' );
ok( null === cemeti_restructure_content( '' ), 'empty text is left untouched' );
ok( 'biography' === cemeti_restructure_heading_kind( "Artist's Biography" ) && 'documentation' === cemeti_restructure_heading_kind( 'Opening Documentation' ) && 'gallery' === cemeti_restructure_heading_kind( 'Artwork Images' ) && 'related' === cemeti_restructure_heading_kind( 'Related Publications' ) && 'toc' === cemeti_restructure_heading_kind( 'Daftar Isi' ) && '' === cemeti_restructure_heading_kind( 'Pernyataan Kuratorial' ), 'heading vocabulary: English and Indonesian' );

/* ---- through the converter: Move to … (rules mode) ---- */
$post = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Ghost Light', 'post_content' => $old, 'post_date' => '2021-06-09 10:00:00', 'meta' => array( '_thumbnail_id' => 12542 ) ) );
CemetiWP::$rel[ $post ]['category'] = array();
ok( 'skipped' !== cemeti_move_post( $post, 'exhibition|Solo' ), 'the post is moved' );
ok( array( 21, 22, 23, 24 ) === get_post_meta( $post, 'cemeti_gallery', true ) && array( 31, 32 ) === get_post_meta( $post, 'cemeti_documentation', true ), 'moved: gallery and documentation fields are filled' );
$bios = get_post_meta( $post, 'cemeti_biographies', true );
ok( 2 === count( $bios ) && 'Second Artist' === $bios[1]['name'], 'moved: biographies are filled' );
ok( 'https://cemeti.art/wp-content/uploads/guide.pdf' === get_post_meta( $post, 'cemeti_booklet_url', true ), 'moved: booklet link' );
$content = CemetiWP::$posts[ $post ]['post_content'];
ok( false !== strpos( $content, 'Statement text' ) && false === strpos( $content, 'Foto Karya' ) && false === strpos( $content, 'Daftar Isi' ), 'moved: the body keeps only the foreword' );
ok( $old === get_post_meta( $post, '_cemeti_original_content', true ), 'moved: the original text is kept for a revert' );
cemeti_revert_post( $post );
ok( 'post' === get_post_type( $post ) && $old === CemetiWP::$posts[ $post ]['post_content'] && '' === (string) get_post_meta( $post, 'cemeti_gallery', true ), 'revert: the original text and a clean post come back' );

/* ---- a type without a gallery field: pictures go to the documentation ---- */
$pp = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Talk', 'post_content' => $old, 'post_date' => '2026-01-30 10:00:00' ) );
cemeti_move_post( $pp, 'public_program|' );
ok( array( 21, 22, 23, 24, 31, 32 ) === get_post_meta( $pp, 'cemeti_documentation', true ) && '' === (string) get_post_meta( $pp, 'cemeti_gallery', true ), 'Public Program: gallery pictures join the documentation' );

/* ---- switched off ---- */
update_option( 'cemeti_convert_map', array( 'mode' => 'rules', 'restructure' => 0 ) );
$off = CemetiWP::post( array( 'post_type' => 'post', 'post_title' => 'Plain', 'post_content' => $old, 'post_date' => '2021-06-09 10:00:00' ) );
cemeti_move_post( $off, 'exhibition|Solo' );
ok( $old === CemetiWP::$posts[ $off ]['post_content'] && '' === (string) get_post_meta( $off, 'cemeti_gallery', true ), 'switched off: the text is moved as it is' );

/* ---- QA round 5: shapes found in the real export (full text of all 561 posts) */
$G = static function ( $n ) { return '<figure class="wp-block-image"><img src="https://cemeti.art/wp-content/uploads/2022/01/g' . $n . '.jpg" class="wp-image-' . $n . '"></figure>'; };
// Headings inside block groups / columns used to be invisible to the split.
$nested = '<div class="wp-block-group"><div class="wp-block-group__inner-container"><h1>Re-reading</h1><p>Opening text of the show.</p><h2>Dokumentasi</h2>' . $G( 51 ) . $G( 52 ) . '</div></div>';
$r = cemeti_restructure_content( $nested, 0 );
ok( $r && array( 51, 52 ) === $r['documentation'] && false !== strpos( $r['foreword'], 'Opening text' ), 'nested: headings inside block groups are found and their pictures placed' );
// "Fota Karya" (a typo of the old site), curly apostrophe, a heading that is the back-to-top arrow.
ok( 'gallery' === cemeti_restructure_heading_kind( 'Fota Karya' ) && 'biography' === cemeti_restructure_heading_kind( "Artists\u{2019} Profile (in alphabetical order)" ) && 'toc' === cemeti_restructure_heading_kind( 'Daftar Isi RN x RK' ), 'vocabulary: "Fota Karya", curly apostrophes, "Daftar Isi <name>"' );
$r = cemeti_restructure_content( '<h2>Foto Karya</h2>' . $G( 61 ) . '<h2>&#x21EA;</h2>' . $G( 62 ) . '<h2>Dokumentasi</h2>' . $G( 63 ), 0 );
ok( $r && array( 61, 62 ) === $r['gallery'] && array( 63 ) === $r['documentation'] && false === strpos( $r['foreword'], '21EA' ) && false === strpos( $r['foreword'], '⇪' ), 'a back-to-top arrow written as a heading is dropped' );
// Biographies whose artist names are headings.
$r = cemeti_restructure_content( '<h2>Biografi Seniman</h2><h3>MANSHUR ZIKRI</h3>' . $G( 71 ) . '<p>Lahir di Bandung.</p><h3>Arum Dayu (Indonesia)</h3><p>Tinggal di Solo.</p><h2>Dokumentasi</h2>' . $G( 72 ) . '<p>Practical info stays.</p>', 0 );
ok( $r && 2 === count( $r['biographies'] ) && 'Manshur Zikri' === $r['biographies'][0]['name'] && 71 === $r['biographies'][0]['photo'] && false !== strpos( $r['biographies'][0]['bio'], 'Bandung' ) && 'Arum Dayu (Indonesia)' === $r['biographies'][1]['name'], 'biographies: artist names written as headings start each biography (' . json_encode( $r['biographies'] ) . ')' );
ok( array( 72 ) === $r['documentation'] && 'Practical info stays.' === $r['documentation_caption'], 'biographies: a later section heading ends the biographies' );
$r = cemeti_restructure_content( '<h2>Biografi Seniman</h2><p><strong>A Artist</strong> bio.</p><h3>Cemeti - Institut untuk Seni dan Masyarakat</h3><p>Jl. D.I. Panjaitan 41</p>', 0 );
ok( 1 === count( $r['biographies'] ) && false !== strpos( $r['foreword'], 'Panjaitan' ), 'biographies: the address heading at the end is not taken for an artist' );
// Artwork titles between pictures keep the section going.
$r = cemeti_restructure_content( '<h2>Foto Karya</h2><h4>Physis - 01</h4>' . $G( 81 ) . '<h4>Physis - 02</h4>' . $G( 82 ) . '<h2>Dokumentasi</h2>' . $G( 83 ), 0 );
ok( $r && array( 81, 82 ) === $r['gallery'], 'gallery: artwork titles written as headings between the pictures do not end the section' );
// Nothing is dropped silently: descriptions of the works and videos stay in the text.
$r = cemeti_restructure_content( '<h2>Foto Karya</h2>' . $G( 91 ) . '<p>This is a long description of the work that is certainly more than two hundred and twenty characters, because the old site wrote the essay of the exhibition under the pictures, and the new gallery has no place for it, so it has to stay in the text. ' . str_repeat( 'More words. ', 6 ) . '</p><figure class="wp-block-embed"><iframe src="https://www.youtube.com/embed/abc"></iframe></figure><h2>Dokumentasi</h2>' . $G( 92 ), 0 );
ok( $r && array( 91 ) === $r['gallery'] && false !== strpos( $r['foreword'], 'long description of the work' ) && false !== strpos( $r['foreword'], 'youtube.com/embed/abc' ), 'long text and embeds inside picture sections stay in the text (nothing is lost)' );

qa_done();
