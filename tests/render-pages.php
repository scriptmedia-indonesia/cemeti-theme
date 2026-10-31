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
ok( false !== strpos( $h, 'Previous Journal' ) && 3 === substr_count( $h, 'class="cemeti-row"' ), 'home: three journal rows and the Previous Journal button' );
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
ok( false !== strpos( $h, 'cemeti-residency-intro' ) && false !== strpos( $h, 'Reguler' ) && false !== strpos( $h, 'Research' ), 'archive residency: Reguler and Research intros from term descriptions' );
$h = page( 'archive-journal', 'archive-journal.php', array( 'pta' => 'journal' ), array( 'path' => '/journal/', 'query' => array( 'post_type' => 'journal' ), 'flags' => array( 'pta' => 'journal' ) ) );
ok( 3 === substr_count( $h, 'class="cemeti-row"' ) && strpos( $h, '15-01-2025' ) < strpos( $h, '01-07-2024' ), 'archive journal: rows with dd-mm-yyyy, newest first' );
ok( false !== strpos( $h, 'Curatorial Essay' ) && false !== strpos( $h, '>Essay<' ), 'archive journal: category label per row' );
$h = page( 'archive-open-call', 'archive-open_call.php', array( 'pta' => 'open_call' ), array( 'path' => '/open-call/', 'query' => array( 'post_type' => 'open_call' ), 'flags' => array( 'pta' => 'open_call' ) ) );
ok( 4 === substr_count( $h, 'class="cemeti-row"' ), 'archive open call: four rows' );
$h = page( 'archive-project', 'archive.php', array( 'term' => 'Maintenance Works' ), array( 'path' => '/project/maintenance-works/', 'query' => array(), 'flags' => array( 'tax' => 'project' ) ) );
ok( false !== strpos( $h, '>Maintenance Works</h1>' ), 'taxonomy archive: heading is the term name' );

// ---- Single entries
$h = page( 'single-exhibition', 'single.php', array( 'singular' => true ), array( 'post' => $ids['ex_current'], 'path' => '/exhibition/x/' ) );
foreach ( array( 'id="foreword"', 'id="gallery"', 'id="documentation"', 'id="biography"', 'id="booklet"', 'id="related"', 'cemeti-share' ) as $needle ) {
	ok( false !== strpos( $h, $needle ), "single exhibition: section $needle" );
}
ok( false !== strpos( $h, 'Agung Kurniawan<br />' ) && false !== strpos( $h, 'Nindityo Adipurnomo' ), 'single exhibition: artists, one per line' );
ok( false !== strpos( $h, 'id="toc"' ), 'single exhibition: table of contents' );
ok( false !== strpos( $h, 'Pembukaan' ) || false !== strpos( $h, 'Opening' ), 'single exhibition: opening label' );
ok( false !== strpos( $h, 'rel="noopener noreferrer"' ) && false !== strpos( $h, 'wa.me' ), 'single exhibition: share links open safely' );
$h = page( 'single-exhibition-bare', 'single.php', array( 'singular' => true ), array( 'post' => $ids['ex_nodate'], 'path' => '/exhibition/beastly/' ) );
ok( false === strpos( $h, 'id="toc"' ) && false === strpos( $h, 'id="gallery"' ) && false === strpos( $h, 'id="biography"' ), 'single exhibition without fields: no empty sections or contents' );
page( 'single-public-program', 'single.php', array( 'singular' => true ), array( 'post' => $ids['pp_past'], 'path' => '/public-program/x/' ) );
page( 'single-open-studio', 'single.php', array( 'singular' => true ), array( 'post' => $ids['os_current'], 'path' => '/open-studio/x/' ) );
page( 'single-residency', 'single.php', array( 'singular' => true ), array( 'post' => $ids['res_current'], 'path' => '/residency/x/' ) );
$h = page( 'single-journal', 'single-journal.php', array( 'singular' => true ), array( 'post' => $ids['j_essay'], 'path' => '/journal/x/' ) );
ok( false !== strpos( $h, 'Mira Asriningtyas' ) && false !== strpos( $h, '01-07-2024' ), 'single journal: author and date' );
ok( false !== strpos( $h, 'cemeti-related' ), 'single journal: related publications' );

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
$h = page( 'single-open-call-xss', 'single-open_call.php', array( 'singular' => true ), array( 'post' => $ids['oc_open'], 'path' => '/open-call/x/?apply=%3Cscript%3E', 'get' => array( 'apply' => '<script>alert(1)</script>' ) ) );
ok( false === strpos( $h, '<script>alert' ), 'open call: ?apply= value never echoed raw' );

// ---- Pages
$h = page( 'page-about', 'page-about.php', array( 'singular' => true ), array( 'post' => $ids['p_about'], 'path' => '/about/' ) );
ok( false !== strpos( $h, 'Mella Jaarsma' ) && false !== strpos( $h, 'Dito Yuwono' ) && false !== strpos( $h, 'cemeti-people__heading' ), 'about: advisory board and team listed' );
$h = page( 'page-open-archive', 'page-open-archive.php', array( 'singular' => true ), array( 'post' => $ids['p_archive'], 'path' => '/open-archive/' ) );
ok( false !== strpos( $h, 'cemeti-az__letter' ), 'open archive: letter groups' );
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
foreach ( array( 'page-about.php', 'page-open-archive.php', 'page-support-us.php', 'page-contact-us.php' ) as $tpl ) {
	page( 'empty-' . basename( $tpl, '.php' ), $tpl, array( 'singular' => true ), array( 'post' => $p1, 'path' => '/about/' ) );
}
$ex = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Tanpa isi' ) );
page( 'empty-single', 'single.php', array( 'singular' => true ), array( 'post' => $ex, 'path' => '/exhibition/x/' ) );

qa_done();
