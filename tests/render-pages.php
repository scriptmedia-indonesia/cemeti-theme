<?php
// Run: php tests/render-pages.php  — renders every template with realistic data and checks the HTML.
// Output pages go to build/qa/*.html (open them in a browser to look at them).
require __DIR__ . '/harness/bootstrap.php';
qa_boot();
$ids = qa_seed();
$out = dirname( __DIR__ ) . '/build/qa';
@mkdir( $out, 0777, true );

function page( $name, $template, $ctx, $o = array(), $opts = array() ) {
	global $out;
	try {
		$html = qa_render( $template, $ctx, $o );
	} catch ( Throwable $e ) {
		ok( false, "$name: threw " . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		return '';
	}
	file_put_contents( "$out/$name.html", $html );
	$problems = qa_check_html( $html, $opts );
	ok( ! $problems, "$name: " . implode( ' | ', array_slice( $problems, 0, 6 ) ) );
	ok( strlen( $html ) > 500, "$name: rendered something" );
	return $html;
}

// ---- Home
$h = page( 'home', 'front-page.php', array( 'front' => true ) );
ok( false !== strpos( $h, 'Harta yang Paling Berharga' ), 'home: featured current exhibition shown' );
ok( false !== strpos( $h, 'Residency Program 2026' ) && ( false !== strpos( $h, 'Current Residence' ) || false !== strpos( $h, 'Residen saat ini' ) ) && false !== strpos( $h, 'Zulfian Amrullah, Julian Grosmann' ), 'home: current residency with residents' );
ok( false !== strpos( $h, 'Open Studio: Angki Purbandono' ), 'home: open studio shown' );
ok( false !== strpos( $h, 'Previous Journal' ) && 2 === substr_count( $h, 'class="cemeti-row"' ) && false === strpos( $h, 'Rangkaian Perayaan #37thCemeti' ), 'home: the journal rows are the essays only (News is in the Open Archive) and the Previous Journal button' );
ok( false !== strpos( $h, '>Previous Exhibition<' ), 'home: "Previous Exhibition" button' );

// ---- Archives
$h  = page( 'archive-exhibition', 'archive.php', array( 'pta' => 'exhibition' ), array( 'path' => '/exhibition/', 'query' => array( 'post_type' => 'exhibition' ), 'flags' => array( 'pta' => 'exhibition' ) ) );
ok( 4 === substr_count( $h, 'class="cemeti-card"' ), 'archive exhibition: four cards (current, past, future, undated)' );
ok( false !== strpos( $h, '>Exhibition</h1>' ), 'archive exhibition: heading is the singular "Exhibition", as in the design' );
preg_match_all( '/cemeti-card__title">([^<]*)</', $h, $titles );
ok( array( 'Pameran Mendatang &quot;Tanah&quot; &amp; &lt;Air&gt;', 'Harta yang Paling Berharga adalah Keluarga', 'Dalam Gelap, Kugenggam Ingatan', 'Beastly' ) === $titles[1], 'archive exhibition: newest start date first, undated last' );
ok( false !== strpos( $h, 'Pameran Mendatang &quot;Tanah&quot; &amp; &lt;Air&gt;' ), 'archive exhibition: title escaped' );
ok( false !== strpos( $h, '6 March – 18 April 2026' ) || false !== strpos( $h, '6 Maret – 18 April 2026' ), 'archive exhibition: date range in the card' );

$h = page( 'archive-exhibition-solo', 'archive.php', array( 'pta' => 'exhibition' ), array( 'path' => '/exhibition/?type=solo', 'query' => array( 'post_type' => 'exhibition' ), 'flags' => array( 'pta' => 'exhibition' ), 'get' => array( 'type' => 'solo' ) ) );
ok( 1 === substr_count( $h, 'class="cemeti-card"' ) && false !== strpos( $h, 'Dalam Gelap' ), 'archive exhibition ?type=solo: only the solo exhibition' );
$h = page( 'archive-exhibition-bogus', 'archive.php', array( 'pta' => 'exhibition' ), array( 'path' => '/exhibition/?type=zzz', 'query' => array( 'post_type' => 'exhibition' ), 'flags' => array( 'pta' => 'exhibition' ), 'get' => array( 'type' => 'zzz' ) ) );
ok( false !== strpos( $h, 'Nothing found' ) || false !== strpos( $h, 'Tidak ada hasil' ), 'archive exhibition ?type=unknown: empty state, no error' );

$h = page( 'archive-public-program', 'archive.php', array( 'pta' => 'public_program' ), array( 'path' => '/public-program/', 'query' => array( 'post_type' => 'public_program' ), 'flags' => array( 'pta' => 'public_program' ) ) );
ok( false !== strpos( $h, '>Public Program</h1>' ), 'archive public program: singular heading' );
$h = page( 'archive-open-studio', 'archive.php', array( 'pta' => 'open_studio' ), array( 'path' => '/open-studio/', 'query' => array( 'post_type' => 'open_studio' ), 'flags' => array( 'pta' => 'open_studio' ) ) );
ok( false !== strpos( $h, '>Open Studio</h1>' ), 'archive open studio: singular heading' );
$h = page( 'archive-residency', 'archive-residency.php', array( 'pta' => 'residency' ), array( 'path' => '/residency/', 'query' => array( 'post_type' => 'residency' ), 'flags' => array( 'pta' => 'residency' ) ) );
ok( false !== strpos( $h, '>Residency</h1>' ), 'archive residency: heading is "Residency", not "Residencies"' );
$h_all = $h;
$h_res = page( 'archive-residency-research', 'archive-residency.php', array( 'pta' => 'residency' ), array( 'path' => '/residency/?type=research', 'query' => array( 'post_type' => 'residency' ), 'flags' => array( 'pta' => 'residency' ), 'get' => array( 'type' => 'research' ) ) );
$h_reg = page( 'archive-residency-regular', 'archive-residency.php', array( 'pta' => 'residency' ), array( 'path' => '/residency/?type=regular', 'query' => array( 'post_type' => 'residency' ), 'flags' => array( 'pta' => 'residency' ), 'get' => array( 'type' => 'reguler' ) ) );
ok( false === strpos( $h_all, 'class="is-active"' ) && 1 === substr_count( $h_res, 'class="is-active"' ) && 1 === preg_match( '/class="is-active" aria-current="true">Research</', $h_res ), 'residency: the chosen category is marked, nothing is marked on the full list' );
ok( 1 === preg_match( '/class="is-active" aria-current="true">Regular</', $h_reg ), 'residency: the old slug "reguler" still marks Regular' );
ok( 1 === preg_match( '/href="[^"]*\/residency\/"\s+class="is-active"/', $h_res ) && false !== strpos( $h_res, 'id="cemeti-results"' ), 'residency: the chosen category links back to the full list; the list sits in its own results block' );
ok( false !== strpos( $h_reg, 'cemeti-card-grid' ) && false === strpos( $h_res, 'cemeti-card-grid' ) && false !== strpos( $h_res, 'cemeti-empty' ), 'residency: Regular shows its entries, Research (none in the fixture) shows the empty message' );
ok( false !== strpos( $h, 'cemeti-residency-intro' ) && false !== strpos( $h, 'Regular' ) && false !== strpos( $h, 'Research' ), 'archive residency: Regular and Research intros from term descriptions' );
$h = page( 'archive-journal', 'archive-journal.php', array( 'pta' => 'journal' ), array( 'path' => '/journal/', 'query' => array( 'post_type' => 'journal' ), 'flags' => array( 'pta' => 'journal' ) ) );
ok( 2 === substr_count( $h, 'class="cemeti-row"' ) && strpos( $h, '01-07-2024' ) < strpos( $h, '08-11-2023' ) && false === strpos( $h, '15-01-2025' ), 'archive journal: essays only, rows with dd-mm-yyyy, newest first; News is left out' );
ok( false !== strpos( $h, 'Curatorial Essay' ) && false !== strpos( $h, '>Essay<' ), 'archive journal: category label per row' );
$h = page( 'archive-open-call', 'archive-open_call.php', array( 'pta' => 'open_call' ), array( 'path' => '/open-call/', 'query' => array( 'post_type' => 'open_call' ), 'flags' => array( 'pta' => 'open_call' ) ) );
ok( 1 === substr_count( $h, 'cemeti-single--open_call' ), 'archive open call: only one call is shown' );
ok( 1 === substr_count( $h, 'cemeti-btn--blue' ) + substr_count( $h, 'cemeti-apply__notice' ), 'archive open call: it has the blue button (active) or the closed notice' );
ok( false !== strpos( $h, '#apply' ) && false !== strpos( $h, 'Application Submission' ), 'archive open call: the button goes to the form (#apply)' );
ok( false !== strpos( $h, 'cemeti-single__body' ), 'archive open call: the description is shown' );
$h = page( 'archive-project', 'archive.php', array( 'term' => 'Maintenance Works' ), array( 'path' => '/project/maintenance-works/', 'query' => array(), 'flags' => array( 'tax' => 'project' ) ) );
ok( false !== strpos( $h, '>Maintenance Works</h1>' ), 'taxonomy archive: heading is the term name' );

// ---- Single entries
$h = page( 'single-exhibition', 'single.php', array( 'singular' => true ), array( 'post' => $ids['ex_current'], 'path' => '/exhibition/x/' ) );
foreach ( array( 'id="foreword"', 'id="gallery"', 'id="documentation"', 'id="biography"', 'id="booklet"', 'id="related"', 'cemeti-share' ) as $needle ) {
	ok( false !== strpos( $h, $needle ), "single exhibition: section $needle" );
}
ok( false !== strpos( $h, 'Agung Kurniawan<br />' ) && false !== strpos( $h, 'Nindityo Adipurnomo' ), 'single exhibition: artists, one per line' );
ok( false !== strpos( $h, 'id="toc"' ), 'single exhibition: table of contents' );
preg_match_all( '/<nav class="cemeti-toc".*?<\/nav>/s', $h, $toc );
preg_match_all( '/href="#([a-z-]+)"/', $toc[0][0] ?? '', $anchors );
ok( array( 'foreword', 'gallery', 'documentation', 'biography', 'booklet' ) === $anchors[1], 'contents: one link per section, in page order (' . implode( ',', $anchors[1] ) . ')' );
foreach ( $anchors[1] as $anchor ) {
	ok( 1 === substr_count( $h, 'id="' . $anchor . '"' ), "contents: link #$anchor has exactly one target" );
}
ok( false !== strpos( $h, 'aria-labelledby="toc-label"' ) && false !== strpos( $h, '>Contents<' ), 'contents: labelled navigation, "Contents" (English is the main language)' );
ok( false !== strpos( $h, 'href="http://cemeti.test/uploads/booklet-901.pdf"' ), 'booklet: the chosen PDF wins over the external link' );
ok( false !== strpos( $h, 'Pembukaan' ) || false !== strpos( $h, 'Opening' ), 'single exhibition: opening label' );
ok( false !== strpos( $h, 'rel="noopener noreferrer"' ) && false !== strpos( $h, 'wa.me' ), 'single exhibition: share links open safely' );
$res = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Residency Program 2025', 'post_content' => 'Foreword text', 'thumb' => 501, 'meta' => array( 'cemeti_start_date' => '20251001', 'cemeti_end_date' => '20251130', 'cemeti_artists' => "Resident One\nResident Two", 'cemeti_gallery' => array( 601, 602, 603, 604 ), 'cemeti_documentation' => array( 611, 612 ), 'cemeti_biographies' => array( array( 'name' => 'Resident One', 'photo' => 701, 'bio' => 'Born somewhere.' ), array( 'name' => 'Resident Two', 'photo' => 0, 'bio' => 'Lives somewhere.' ) ), 'cemeti_booklet_url' => 'https://example.org/residency.pdf' ) ) );
$h = page( 'single-residency-full', 'single.php', array( 'singular' => true ), array( 'post' => $res, 'path' => '/residency/x/' ) );
foreach ( array( 'id="foreword"', 'id="gallery"', 'id="documentation"', 'id="biography"', 'id="booklet"', 'cemeti-single--residency', 'cemeti-toc__links' ) as $needle ) {
	ok( false !== strpos( $h, $needle ), "single residency: $needle" );
}
ok( 2 === substr_count( $h, 'class="cemeti-bio"' ) && 2 === substr_count( $h, 'cemeti-photos__grid"' ), 'single residency: two biographies, gallery and documentation grids' );
$h = page( 'single-exhibition-bare', 'single.php', array( 'singular' => true ), array( 'post' => $ids['ex_nodate'], 'path' => '/exhibition/beastly/' ) );
ok( false === strpos( $h, 'id="toc"' ) && false === strpos( $h, 'id="gallery"' ) && false === strpos( $h, 'id="biography"' ), 'single exhibition without fields: no empty sections or contents' );

// ---- Scroll-loading fixtures (tests/browser): the archive cut into two pages with a "next" link
$page1 = file_get_contents( "$out/archive-exhibition.html" );
$nav1  = '<nav class="navigation pagination"><div class="nav-links"><span class="page-numbers current">1</span><a class="next page-numbers" href="infinite-2.html">Older</a></div></nav>';
$script = false === strpos( $page1, 'infinite.js' ) ? '<script src="../../themes/scriptmedia-cemeti/assets/js/infinite.js"></script>' : '';
file_put_contents( "$out/infinite-1.html", str_replace( '</ul>', '</ul>' . $nav1, preg_replace( '#</body>#', $script . '</body>', $page1, 1 ) ) ? preg_replace( '#(<ul class="cemeti-card-grid">.*?</ul>)#s', '$1' . $nav1, preg_replace( '#</body>#', $script . '</body>', $page1, 1 ), 1 ) : $page1 );
$more = '<li class="cemeti-card"><a href="#"><span class="cemeti-card__image cemeti-card__image--empty"></span><span class="cemeti-card__body"><span class="cemeti-card__title">Second page card A</span><span class="cemeti-card__date">2020</span></span></a></li><li class="cemeti-card"><a href="#"><span class="cemeti-card__image cemeti-card__image--empty"></span><span class="cemeti-card__body"><span class="cemeti-card__title">Second page card B</span><span class="cemeti-card__date">2019</span></span></a></li>';
file_put_contents( "$out/infinite-2.html", '<!doctype html><html><body><ul class="cemeti-card-grid">' . $more . '</ul><nav class="navigation pagination"><div class="nav-links"><span class="page-numbers current">2</span></div></nav></body></html>' );

// ---- Public Program / Open Studio detail (no contents bar, weekday date, speakers first)
$pp = CemetiWP::post( array( 'post_type' => 'public_program', 'post_title' => 'Public Talk: Power', 'post_content' => 'Foreword', 'thumb' => 501, 'meta' => array( 'cemeti_start_date' => '20260130', 'cemeti_end_date' => '20260130', 'cemeti_speakers' => 'Iola Lenzi', 'cemeti_time_text' => '15.00 – 17.00 WIB', 'cemeti_documentation' => array( 611, 612 ), 'cemeti_biographies' => array( array( 'name' => 'Iola Lenzi', 'photo' => 701, 'bio' => 'Historian.' ) ) ) ) );
$h = page( 'single-public-program', 'single.php', array( 'singular' => true ), array( 'post' => $pp, 'path' => '/public-program/x/' ) );
ok( false === strpos( $h, 'id="toc"' ) && false !== strpos( $h, 'id="documentation"' ) && false !== strpos( $h, 'id="biography"' ), 'public program: no contents bar, sections present' );
ok( false !== strpos( $h, 'href="#toc-top"' ), 'public program: "back to contents" links stay' );
ok( preg_match( '/cemeti-single__people.*Iola Lenzi.*cemeti-single__when.*Friday, 30 January 2026.*15\.00/s', $h ) === 1, 'public program: speaker, then weekday date and time (' . ( preg_match( '/cemeti-single__when">\s*([^<]*)/', $h, $mm ) ? trim( $mm[1] ) : 'none' ) . ')' );
ok( false === strpos( $h, 'cemeti-single__date' ), 'public program: no large date line' );
ok( 'Jumat, 30 Januari 2026' === cemeti_format_event_when( '20260130', '20260130', 'id' ) && '26 September – 15 November 2025' === cemeti_format_event_when( '20250926', '20251115', 'en' ) && '' === cemeti_format_event_when( 'x' ), 'event date: weekday for one day, range otherwise' );
$os = CemetiWP::post( array( 'post_type' => 'open_studio', 'post_title' => 'Open Studio X', 'post_content' => 'Text', 'meta' => array( 'cemeti_start_date' => '20260310', 'cemeti_end_date' => '20260330', 'cemeti_artists' => 'Angki', 'cemeti_documentation' => array( 611 ), 'cemeti_biographies' => array( array( 'name' => 'Angki', 'photo' => 0, 'bio' => 'Artist.' ) ) ) ) );
$h = page( 'single-open-studio', 'single.php', array( 'singular' => true ), array( 'post' => $os, 'path' => '/open-studio/x/' ) );
ok( false === strpos( $h, 'id="toc"' ) && false !== strpos( $h, '10 – 30 March 2026' ), 'open studio: no contents bar, date range shown' );

// ---- Logo: WebP for browsers that have it, PNG for the others (Safari before macOS 11 showed an empty box)
$h = file_get_contents( "$out/home.html" );
ok( 1 === preg_match( '#<picture>\s*<source type="image/webp" srcset="[^"]*logo-480\.webp[^"]*"[^>]*>\s*<img src="[^"]*logo-480\.png"#', $h ), 'logo: a picture with a WebP source and a PNG image to fall back on' );
ok( is_file( dirname( __DIR__ ) . '/themes/scriptmedia-cemeti/assets/img/logo-480.png' ) && is_file( dirname( __DIR__ ) . '/themes/scriptmedia-cemeti/assets/img/logo-960.png' ), 'logo: the PNG files exist' );

// ---- Admin "Details" box, for the browser tests of the editing screen (tests/browser/browser-qa.js)
do_action( 'add_meta_boxes' );
ob_start();
call_user_func( CemetiWP::$meta_boxes['exhibition']['cemeti_details'], CemetiWP::obj( $ids['ex_current'] ) );
$box = ob_get_clean();
$css = file_get_contents( dirname( __DIR__ ) . '/themes/scriptmedia-cemeti/assets/css/admin.css' );
file_put_contents( "$out/admin-exhibition.html", '<!doctype html><html lang="en"><meta charset="utf-8"><title>Edit exhibition</title><style>body{font:13px sans-serif;margin:20px}' . $css . '</style><body><h1>Edit</h1><form id="post" onsubmit="return false"><h2>Details</h2>' . $box . '</form><script src="../../tests/browser/admin-stub.js"></script><script src="../../themes/scriptmedia-cemeti/assets/js/admin-fields.js"></script></body></html>' );

// ---- Media repair panel with its runner (the browser test answers admin-ajax itself)
ob_start();
cemeti_media_panel();
$panel = str_replace( 'id="cemeti-media-runner"', 'id="cemeti-media-runner" data-pause="20" data-retries="3"', ob_get_clean() );
file_put_contents( "$out/admin-media.html", '<!doctype html><html lang="en"><meta charset="utf-8"><title>Media</title><style>body{font:13px sans-serif;margin:20px}.notice{padding:6px 10px;border-left:4px solid #72aee6;background:#fff}.notice[hidden]{display:none}</style><body>' . $panel . '<script src="../../themes/scriptmedia-cemeti/assets/js/admin-media.js"></script></body></html>' );

page( 'single-public-program', 'single.php', array( 'singular' => true ), array( 'post' => $ids['pp_past'], 'path' => '/public-program/x/' ) );
page( 'single-open-studio', 'single.php', array( 'singular' => true ), array( 'post' => $ids['os_current'], 'path' => '/open-studio/x/' ) );
page( 'single-residency', 'single.php', array( 'singular' => true ), array( 'post' => $ids['res_current'], 'path' => '/residency/x/' ) );
update_post_meta( $ids['j_essay'], 'cemeti_documentation', array( 506, 506 ) );
update_post_meta( $ids['j_essay'], 'cemeti_documentation_caption', 'Dinner table' );
$h = page( 'single-journal', 'single-journal.php', array( 'singular' => true ), array( 'post' => $ids['j_essay'], 'path' => '/journal/x/' ) );
ok( false !== strpos( $h, 'Mira Asriningtyas' ) && false === strpos( $h, '01-07-2024' ), 'single journal: author shown, no date line under it (Figma)' );
ok( false !== strpos( $h, 'cemeti-related' ), 'single journal: related publications' );
ok( false !== strpos( $h, 'class="cemeti-single__figure"' ) && false !== strpos( $h, 'Fine dining at Cemeti, 2025.' ), 'single journal: hero picture with its caption (Figma)' );
ok( 2 === substr_count( $h, 'class="cemeti-photos__grid"' ) + substr_count( $h, '<figure class="cemeti-photos">' ) && false !== strpos( $h, 'id="documentation"' ) && false !== strpos( $h, 'Dinner table' ), 'single journal: Documentation section with its photos and caption' );

// ---- Open Call
$h = page( 'single-open-call', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_open'], 'path' => '/open-call/x/' ) );
ok( false !== strpos( $h, 'id="apply"' ) && false !== strpos( $h, 'enctype="multipart/form-data"' ), 'open call: built-in form is shown' );
ok( 4 === substr_count( $h, 'type="file"' ), 'open call: four document uploads by default' );
ok( false !== strpos( $h, 'href="#apply"' ) && false !== strpos( $h, 'Application Submission' ), 'open call: button points at the form' );
ok( false !== strpos( $h, 'name="cemeti_ts"' ) && false !== strpos( $h, 'name="cemeti_website"' ) && false !== strpos( $h, 'name="_cemeti_nonce"' ), 'open call: nonce, time token and trap field present' );
$h = page( 'single-open-call-external', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_external'], 'path' => '/open-call/e/' ) );
ok( false === strpos( $h, 'id="apply"' ) && false !== strpos( $h, 'forms.example.org' ), 'open call with external URL: button goes out, no built-in form' );
$h = page( 'single-open-call-closed', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_closed'], 'path' => '/open-call/c/' ) );
ok( false === strpos( $h, 'cemeti-apply__form' ) && ( false !== strpos( $h, 'closed' ) || false !== strpos( $h, 'ditutup' ) ), 'open call after the deadline: closed notice, no form' );
$h = page( 'single-open-call-nodocs', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_nodocs'], 'path' => '/open-call/n/' ) );
ok( 0 === substr_count( $h, 'type="file"' ) && false !== strpos( $h, 'cemeti-apply__form' ), 'open call with documents = none: form without uploads' );
$h = page( 'single-open-call-sent', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_open'], 'path' => '/open-call/x/?apply=sent', 'get' => array( 'apply' => 'sent' ) ), array() );
ok( false !== strpos( $h, 'cemeti-apply__notice--ok' ) && false === strpos( $h, 'cemeti-apply__form' ), 'open call ?apply=sent: thank-you instead of the form' );
$h = page( 'single-open-call-error', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_open'], 'path' => '/open-call/x/?apply=file', 'get' => array( 'apply' => 'file' ) ) );
ok( false !== strpos( $h, 'role="alert"' ) && false !== strpos( $h, 'cemeti-apply__form' ), 'open call ?apply=file: error message and the form again' );
// Real editor content: a very long link, a wide table, an embed (they widened the page on iPhone).
$long = CemetiWP::post( array( 'post_type' => 'open_call', 'post_title' => 'Rotating AiRs Open Call: Global Artist-in-Residency Programme 2027-2028', 'thumb' => 505, 'post_content' => 'Rotating AiRs adalah platform transnasional. Info: <a href="https://example.org/' . str_repeat( 'very-long-address-segment/', 12 ) . '">https://example.org/' . str_repeat( 'very-long-address-segment/', 12 ) . '</a>' . "\n\n" . '<table><tr><td>' . str_repeat( 'wide cell ', 40 ) . '</td><td>' . str_repeat( 'wide cell ', 40 ) . '</td></tr></table>' . "\n\n" . '<iframe src="https://example.org/embed" width="900" height="500"></iframe>' . "\n\n" . '' . str_repeat( 'Superlongwordwithoutanybreak', 8 ), 'meta' => array( 'cemeti_deadline' => '20260814' ) ) );
page( 'single-open-call-longcontent', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $long, 'path' => '/open-call/long/' ) );
$h = page( 'single-open-call-xss', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_open'], 'path' => '/open-call/x/?apply=%3Cscript%3E', 'get' => array( 'apply' => '<script>alert(1)</script>' ) ) );
ok( false === strpos( $h, '<script>alert' ), 'open call: ?apply= value never echoed raw' );

// ---- Pages
$h = page( 'page-about', 'page-about.php', array( 'singular' => true ), array( 'post' => $ids['p_about'], 'path' => '/about/' ) );
ok( false !== strpos( $h, 'Mella Jaarsma' ) && false !== strpos( $h, 'Dito Yuwono' ) && false !== strpos( $h, 'cemeti-people__heading' ), 'about: advisory board and team listed' );
$h = page( 'page-open-archive', 'page-open-archive.php', array( 'singular' => true ), array( 'post' => $ids['p_archive'], 'path' => '/open-archive/' ) );
ok( false !== strpos( $h, 'cemeti-az__letter' ) && false === strpos( $h, 'cemeti-archive' ), 'open archive: the A-Z design with letter groups (as designed)' );
ok( false === strpos( $h, 'Rangkaian Perayaan #37thCemeti' ) && false === strpos( $h, 'booklet-901.pdf' ) && false === strpos( $h, 'residency.pdf' ), 'open archive: nothing is added by itself (no News, no booklets of entries): the team chooses what is listed' );
ok( 1 === preg_match( '#<h2 class="cemeti-az__letter">K</h2>#', $h ) && 1 === preg_match( '#<h2 class="cemeti-az__letter">Z</h2>#', $h ), 'open archive: grouped by first letter' );
$h = page( 'single-archive-item', 'single-archive_item.php', array( 'singular' => true ), array( 'post' => $ids['ai_catalog'], 'path' => '/archive/katalog/' ) );
ok( 1 === preg_match( '#<a class="cemeti-btn cemeti-btn--inline cemeti-btn--download" href="[^"]*booklet-901\.pdf" download[^>]*>Download Booklet</a>#', $h ) && false !== strpos( $h, 'Tim Cemeti · 2024' ) && false !== strpos( $h, 'Katalog pameran tahun 2024.' ), 'archive item: title, authors and year, the text and the Download Booklet button for its file' );
ok( false !== strpos( $h, 'id="gallery"' ) && false !== strpos( $h, 'id="documentation"' ) && false !== strpos( $h, 'id="profile"' ) && false !== strpos( $h, 'Ibu Editor' ) && false !== strpos( $h, 'class="cemeti-toc"' ) && false !== strpos( $h, 'cemeti-single--exhibition' ), 'archive item: gallery, documentation and profile sections with a contents bar, like an Exhibition' );
ok( 1 === preg_match( '#<object class="cemeti-pdf__frame" data="[^"]*booklet-901\.pdf\#view=FitH" type="application/pdf"#', $h ) && false !== strpos( $h, 'Open the PDF' ), 'archive item: ticked "Show the PDF on the page" shows the reader with a fallback link' );
$h = page( 'single-archive-item-link', 'single-archive_item.php', array( 'singular' => true ), array( 'post' => $ids['ai_link'], 'path' => '/archive/zine/' ) );
ok( false !== strpos( $h, 'href="https://example.org/zine.pdf"' ) && false !== strpos( $h, 'Download Booklet' ), 'archive item: a link to a file elsewhere works too' );
ok( false === strpos( $h, 'cemeti-pdf' ), 'archive item: the reader stays off until it is ticked' );
$h = page( 'single-archive-item-nofile', 'single-archive_item.php', array( 'singular' => true ), array( 'post' => $ids['ai_nofile'], 'path' => '/archive/tulisan/' ) );
ok( false === strpos( $h, 'cemeti-btn--download' ) && false !== strpos( $h, 'Hanya teks.' ) && false === strpos( $h, 'cemeti-pdf' ), 'archive item: without a file there is no button and no reader' );
ok( false === strpos( $h, 'id="gallery"' ) && false === strpos( $h, 'id="profile"' ) && false === strpos( $h, 'class="cemeti-toc"' ), 'archive item: sections without content are not shown' );
$h = page( 'page-history', 'page-history.php', array( 'singular' => true ), array( 'post' => $ids['p_history'], 'path' => '/history/' ) );
ok( 39 <= substr_count( $h, '<details class="cemeti-accordion__row">' ) && false !== strpos( $h, 'cemeti-single--history' ) && false !== strpos( $h, 'Kronologi kegiatan Cemeti.' ), 'history: its own page with the description and one dropdown row per year' );
$h = page( 'page-support-us', 'page-support-us.php', array( 'singular' => true ), array( 'post' => $ids['p_support'], 'path' => '/support-us/' ) );
ok( 4 === substr_count( $h, '<details' ), 'support us: four accordion rows from child pages' );
page( 'page-contact-us', 'page-contact-us.php', array( 'singular' => true ), array( 'post' => $ids['p_contact'], 'path' => '/contact-us/' ) );
page( 'page-plain', 'page.php', array( 'singular' => true ), array( 'post' => $ids['p_plain'], 'path' => '/halaman-biasa/' ) );

// ---- Search, 404, index
$h = page( 'search', 'search.php', array( 'search' => true ), array( 'path' => '/?s=Harta', 'query' => array( 's' => 'Harta', 'post_type' => array( 'exhibition', 'journal' ) ), 'flags' => array( 'search' => true ), 'get' => array( 's' => 'Harta' ) ) );
ok( 1 === substr_count( $h, 'class="cemeti-row"' ), 'search: one matching row' );
$h = page( 'search-empty', 'search.php', array( 'search' => true ), array( 'path' => '/?s=zzzz', 'query' => array( 's' => 'zzzz', 'post_type' => array( 'exhibition' ) ), 'flags' => array( 'search' => true ), 'get' => array( 's' => 'zzzz' ) ) );
ok( false !== strpos( $h, 'cemeti-empty' ), 'search without results: empty state' );
$h = page( 'search-xss', 'search.php', array( 'search' => true ), array( 'path' => '/?s=x', 'query' => array( 's' => 'x' ), 'flags' => array( 'search' => true ), 'get' => array( 's' => '"><script>alert(1)</script>' ) ) );
ok( false === strpos( $h, '<script>alert' ), 'search: query is escaped in heading and form' );
page( 'not-found', '404.php', array( '404' => true ), array( 'path' => '/nope/' ) );
page( 'index', 'index.php', array(), array( 'post' => $ids['p_plain'], 'path' => '/x/' ), array( 'no_h1_check' => false ) );

// ---- A brand-new site: no content at all must still render everything without a warning.
qa_reset_data();
$empty = array(
	'front-page.php'        => array( array( 'front' => true ), array() ),
	'archive.php'           => array( array( 'pta' => 'exhibition' ), array( 'path' => '/exhibition/', 'query' => array( 'post_type' => 'exhibition' ), 'flags' => array( 'pta' => 'exhibition' ) ) ),
	'archive-residency.php' => array( array( 'pta' => 'residency' ), array( 'path' => '/residency/', 'query' => array( 'post_type' => 'residency' ), 'flags' => array( 'pta' => 'residency' ) ) ),
	'archive-journal.php'   => array( array( 'pta' => 'journal' ), array( 'path' => '/journal/', 'query' => array( 'post_type' => 'journal' ), 'flags' => array( 'pta' => 'journal' ) ) ),
	'archive-open_call.php' => array( array( 'pta' => 'open_call' ), array( 'path' => '/open-call/', 'query' => array( 'post_type' => 'open_call' ), 'flags' => array( 'pta' => 'open_call' ) ) ),
	'search.php'            => array( array( 'search' => true ), array( 'path' => '/?s=x', 'query' => array( 's' => 'x' ), 'flags' => array( 'search' => true ), 'get' => array( 's' => 'x' ) ) ),
	'404.php'               => array( array( '404' => true ), array( 'path' => '/x/' ) ),
);
foreach ( $empty as $tpl => $args ) {
	page( 'empty-' . basename( $tpl, '.php' ), $tpl, $args[0], $args[1] );
}
$p1 = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about' ) );
foreach ( array( 'page-about.php', 'page-history.php', 'page-open-archive.php', 'page-support-us.php', 'page-contact-us.php' ) as $tpl ) {
	page( 'empty-' . basename( $tpl, '.php' ), $tpl, array( 'singular' => true ), array( 'post' => $p1, 'path' => '/about/' ) );
}
$ex = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Tanpa isi' ) );
page( 'empty-single', 'single.php', array( 'singular' => true ), array( 'post' => $ex, 'path' => '/exhibition/x/' ) );

qa_done();
