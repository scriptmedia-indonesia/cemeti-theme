<?php
// Run: php tests/flows-bilingual.php — every kind of page rendered in BOTH languages (English main, Indonesian second),
// checked for the things that went wrong after updates: untranslated interface text, language names used as categories,
// links that stay in the other language, broken markup, PHP notices in the output, missing pictures, empty lists.
require __DIR__ . '/harness/bootstrap.php';

$GLOBALS['pll'] = array( 'current' => 'en', 'registered' => array(), 'lang' => array(), 'groups' => array(), 'override' => array(), 'tgroups' => array(), 'tlang' => array() );
function pll_default_language() { return 'en'; }
function pll_current_language() { return $GLOBALS['pll']['current']; }
function pll_home_url( $lang = null ) { $l = $lang ? $lang : $GLOBALS['pll']['current']; return 'http://cemeti.test/' . ( 'en' === $l ? '' : $l . '/' ); }
function pll__( $s ) { return $s; }
function pll_register_string() {}
function pll_the_languages( $args = array() ) {
	$cur = $GLOBALS['pll']['current'];
	$out = array(
		'en' => array( 'slug' => 'en', 'url' => 'http://cemeti.test/', 'current_lang' => 'en' === $cur ),
		'id' => array( 'slug' => 'id', 'url' => 'http://cemeti.test/id/', 'current_lang' => 'id' === $cur ),
	);
	// Like Polylang: on a post that has a translation, each language links to that translation.
	if ( ! empty( $GLOBALS['post'] ) ) {
		foreach ( pll_get_post_translations( $GLOBALS['post']->ID ) as $lang => $other ) {
			if ( isset( $out[ $lang ] ) && (int) $other !== (int) $GLOBALS['post']->ID ) {
				$out[ $lang ]['url'] = get_permalink( $other );
			}
		}
	}
	return $out;
}
function pll_set_post_language( $id, $lang ) { $GLOBALS['pll']['lang'][ $id ] = $lang; }
function pll_get_post_language( $id ) { return isset( $GLOBALS['pll']['lang'][ $id ] ) ? $GLOBALS['pll']['lang'][ $id ] : ''; }
function pll_save_post_translations( $arr ) { $GLOBALS['pll']['groups'][] = $arr; }
function pll_get_post_translations( $id ) { foreach ( $GLOBALS['pll']['groups'] as $g ) { if ( in_array( $id, $g, true ) ) { return $g; } } return array(); }
function pll_get_post( $id, $lang ) { $t = pll_get_post_translations( $id ); return isset( $t[ $lang ] ) ? $t[ $lang ] : 0; }
function pll_languages_list() { return array( 'en', 'id' ); }

qa_boot();
// Polylang puts the language into archive links; the stub does the same.
add_filter( 'post_type_archive_link', static function ( $link ) {
	$l = $GLOBALS['pll']['current'];
	return 'en' === $l ? $link : str_replace( 'http://cemeti.test/', 'http://cemeti.test/' . $l . '/', $link );
} );
$ids = qa_seed();
CemetiWP::$caps = false;
CemetiWP::$pictures = array( 501, 502, 503, 504, 505, 506, 507, 601, 602, 603, 604, 611, 612, 701, 901 );

/* ---- the site: English entries from the seed, an Indonesian twin for the main ones, Indonesian fixed pages */
foreach ( CemetiWP::$posts as $pid => $p ) {
	if ( in_array( $p['post_type'], array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal', 'page', 'person' ), true ) ) {
		$GLOBALS['pll']['lang'][ $pid ] = 'en';
	}
}
$twin = static function ( $en_id, $title, $with_picture = false ) {
	$src  = CemetiWP::$posts[ $en_id ];
	$meta = isset( CemetiWP::$meta[ $en_id ] ) ? CemetiWP::$meta[ $en_id ] : array();
	unset( $meta['_thumbnail_id'], $meta['cemeti_gallery'], $meta['cemeti_documentation'] );
	$id = CemetiWP::post( array( 'post_type' => $src['post_type'], 'post_title' => $title, 'post_content' => $src['post_content'], 'post_date' => $src['post_date'], 'meta' => $meta, 'thumb' => $with_picture ? 501 : 0 ) );
	$GLOBALS['pll']['lang'][ $id ] = 'id';
	$GLOBALS['pll']['groups'][] = array( 'en' => $en_id, 'id' => $id );
	foreach ( array( 'journal_category', 'exhibition_type', 'residency_type' ) as $tax ) {
		if ( ! empty( CemetiWP::$rel[ $en_id ][ $tax ] ) ) {
			CemetiWP::$rel[ $id ][ $tax ] = CemetiWP::$rel[ $en_id ][ $tax ];
		}
	}
	return $id;
};
$id_ex   = $twin( $ids['ex_current'], 'Harta yang Paling Berharga adalah Keluarga (ID)' );
$id_res  = $twin( $ids['res_current'], 'Program Residensi 2026' );
$id_pp   = $twin( $ids['pp_past'], 'Wicara Publik: Kekuatan, Politik dan Jalanan' );
$id_os   = $twin( $ids['os_current'], 'Studio Terbuka: Angki Purbandono' );
$id_j    = $twin( $ids['j_essay'], 'Esai Menuju Konstelasi Persahabatan' );
$id_oc   = $twin( $ids['oc_open'], 'Program Residensi Seniman Asia Tenggara' );
$id_page = array();
foreach ( array( 'about' => array( 'Tentang', 'tentang', $ids['p_about'] ), 'open-archive' => array( 'Arsip Terbuka', 'arsip-terbuka', $ids['p_archive'] ), 'support-us' => array( 'Dukung Kami', 'dukung-kami', $ids['p_support'] ), 'contact-us' => array( 'Kontak Kami', 'kontak-kami', $ids['p_contact'] ) ) as $key => $def ) {
	$pid = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => $def[0], 'post_name' => $def[1], 'post_content' => 'Teks Indonesia untuk ' . $def[0] ) );
	$GLOBALS['pll']['lang'][ $pid ] = 'id';
	$GLOBALS['pll']['groups'][] = array( 'en' => $def[2], 'id' => $pid );
	update_post_meta( $pid, '_cemeti_page_key', $key );
	$id_page[ $key ] = $pid;
}
foreach ( array( 'Mitra Program', 'Sahabat Cemeti' ) as $i => $t ) {
	$cid = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => $t, 'post_parent' => $id_page['support-us'], 'menu_order' => $i, 'post_content' => "Isi $t." ) );
	$GLOBALS['pll']['lang'][ $cid ] = 'id';
}

/* ---- messy data like the imported site has: no dates, broken picture numbers, odd stored values, empty or odd titles */
$messy = array();
foreach ( array( 'exhibition', 'residency', 'public_program', 'open_studio', 'open_call', 'journal' ) as $type ) {
	foreach ( array( 'id', 'en' ) as $lang ) {
		$a = CemetiWP::post( array( 'post_type' => $type, 'post_title' => 'Tanpa tanggal & "tanda" <b>' . $type . '</b>', 'post_content' => '', 'meta' => array( '_thumbnail_id' => 99999999 ) ) );
		$b = CemetiWP::post( array( 'post_type' => $type, 'post_title' => '', 'post_content' => '<p>Teks</p><img src="https://old.example/x.jpg" class="wp-image-12345">', 'meta' => array( 'cemeti_gallery' => 'bukan array', 'cemeti_documentation' => array( 'x', 0, null ), 'cemeti_artists' => array( 'A', 'B' ), 'cemeti_start_date' => 'abc', 'cemeti_end_date' => '20260101' ) ) );
		foreach ( array( $a, $b ) as $m ) {
			$GLOBALS['pll']['lang'][ $m ] = $lang;
			$messy[] = array( $type, $lang, $m );
		}
	}
}
set_error_handler( static function ( $no, $str, $file, $line ) {
	if ( false !== strpos( $file, 'themes/scriptmedia-cemeti' ) ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
	return false; // The simulated WordPress itself is not under test.
} );

/* ---- helpers */
$strings = cemeti_ui_strings();
$render = static function ( $lang, $name, $template, $ctx, $opts ) {
	$GLOBALS['pll']['current'] = $lang;
	try {
		$html = qa_render( $template, $ctx, $opts );
		if ( 0 !== strpos( $name, 'messy' ) && 0 !== strpos( $name, 'cards' ) && '' !== $html ) { // The pages of both languages also go to the browser sweep.
			$dir = dirname( __DIR__ ) . '/build/qa';
			@mkdir( $dir, 0777, true );
			file_put_contents( $dir . '/bi-' . $lang . '-' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ) . '.html', $html );
		}
		return $html;
	} catch ( Throwable $e ) {
		ok( false, "[$lang] $name: threw " . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		return '';
	}
};
$visible = static function ( $html ) {
	$html = preg_replace( '#<(script|style|template)\b.*?</\1>#s', '', $html );
	return $html;
};
$check = static function ( $lang, $name, $html ) use ( $strings, $visible ) {
	$label = "[$lang] $name";
	if ( '' === $html ) {
		return;
	}
	$problems = qa_check_html( $html );
	$problems = array_filter( $problems, static function ( $p ) { return false === strpos( $p, 'img without alt' ) && 0 !== strpos( $p, 'form control without a label' ); } );
	ok( ! $problems, "$label: markup is valid (" . implode( ' | ', array_slice( $problems, 0, 3 ) ) . ')' );
	$v = $visible( $html );
	ok( ! preg_match( '/(Warning|Notice|Deprecated|Fatal error|Parse error):|\bArray\b(?!\w)|\bundefined\b|%[sd]\b|\{\{/', wp_strip_all_tags( $v ) ), "$label: no PHP messages, placeholders or 'Array' in the text" );
	// The category under a row is a category, never a language name.
	preg_match_all( '#class="cemeti-row__cat">([^<]*)<#', $v, $m );
	foreach ( $m[1] as $cat ) {
		ok( ! in_array( trim( $cat ), array( 'English', 'Bahasa Indonesia', 'Indonesian', 'Indonesia' ), true ), "$label: row category is not a language ($cat)" );
	}
	// Interface text is in the language of the page: an element whose whole text is the other language's string.
	$other = 'id' === $lang ? 'en' : 'id';
	$mine  = $lang;
	foreach ( $strings as $key => $by ) {
		if ( ! isset( $by['id'], $by['en'] ) || $by['id'] === $by['en'] ) {
			continue;
		}
		if ( in_array( $key, array( 'apply_button', 'whats_on', 'public_program', 'contact_us' ), true ) ) {
			continue; // Program names, menu names and buttons that stay English by design.
		}
		ok( 1 !== preg_match( '#>\s*' . preg_quote( esc_html( $by[ $other ] ), '#' ) . '\s*<#', $v ), "$label: interface text '$key' is not left in the other language (" . $by[ $other ] . ')' );
	}
	// Links to the program sections keep the language of the page.
	preg_match_all( '#href="(http://cemeti\.test/(?:id/)?(?:exhibition|residency|public-program|open-studio|open-call|journal)(?:/[^"]*)?)"#', $v, $links );
	foreach ( array_unique( $links[1] ) as $href ) {
		$is_id = 0 === strpos( $href, 'http://cemeti.test/id/' );
		// Entry links are the permalinks of entries (languages differ per entry); only the section links are checked.
		if ( preg_match( '#/(exhibition|residency|public-program|open-studio|open-call|journal)(/\?[^"]*)?/?$#', $href ) ) {
			ok( $is_id === ( 'id' === $lang ), "$label: section link $href is in the page's language" );
		}
	}
};

/* ---- the messy entries open without PHP warnings (an Indonesian list and page of each type) */
foreach ( $messy as $row ) {
	list( $type, $lang, $mid ) = $row;
	$tpl  = 'journal' === $type ? 'single-journal.php' : ( 'open_call' === $type ? 'single-open_call.php' : 'single.php' );
	$html = $render( $lang, "messy $type #$mid", $tpl, array( 'singular' => true ), array( 'post' => $mid, 'path' => '/x/' ) );
	ok( '' !== $html && ! preg_match( '/\b(Warning|Notice|Deprecated):/', $html ), "[$lang] messy $type #$mid: opens without PHP messages" );
}
foreach ( array( 'en', 'id' ) as $lang ) {
	foreach ( array( 'exhibition' => 'archive.php', 'residency' => 'archive-residency.php', 'public_program' => 'archive.php', 'open_studio' => 'archive.php', 'open_call' => 'archive-open_call.php', 'journal' => 'archive-journal.php' ) as $type => $tpl ) {
		$html = $render( $lang, "messy list $type", $tpl, array( 'pta' => $type ), array( 'path' => '/x/', 'query' => array( 'post_type' => $type, 'lang' => $lang ), 'flags' => array( 'pta' => $type ) ) );
		ok( '' !== $html, "[$lang] messy list $type: renders with the odd entries in it" );
	}
}

/* ---- every kind of page, in both languages */
foreach ( array( 'en', 'id' ) as $lang ) {
	$GLOBALS['pll']['current'] = $lang;
	$set = 'en' === $lang
		? array( 'ex' => $ids['ex_current'], 'res' => $ids['res_current'], 'pp' => $ids['pp_past'], 'os' => $ids['os_current'], 'oc' => $ids['oc_open'], 'j' => $ids['j_essay'], 'about' => $ids['p_about'], 'archive' => $ids['p_archive'], 'support' => $ids['p_support'], 'contact' => $ids['p_contact'] )
		: array( 'ex' => $id_ex, 'res' => $id_res, 'pp' => $id_pp, 'os' => $id_os, 'oc' => $id_oc, 'j' => $id_j, 'about' => $id_page['about'], 'archive' => $id_page['open-archive'], 'support' => $id_page['support-us'], 'contact' => $id_page['contact-us'] );

	$check( $lang, 'home', $render( $lang, 'home', 'front-page.php', array( 'front' => true ), array( 'path' => '/' ) ) );
	foreach ( array( 'exhibition' => 'archive.php', 'residency' => 'archive-residency.php', 'public_program' => 'archive.php', 'open_studio' => 'archive.php', 'open_call' => 'archive-open_call.php', 'journal' => 'archive-journal.php' ) as $type => $tpl ) {
		$html = $render( $lang, "archive $type", $tpl, array( 'pta' => $type ), array( 'path' => '/' . str_replace( '_', '-', $type ) . '/', 'query' => array( 'post_type' => $type, 'lang' => $lang ), 'flags' => array( 'pta' => $type ) ) );
		$check( $lang, "archive $type", $html );
		ok( false !== strpos( $html, '<li class="cemeti-card"' ) || false !== strpos( $html, 'cemeti-row' ) || false !== strpos( $html, 'cemeti-opencall' ), "[$lang] archive $type: the list is not empty" );
	}
	foreach ( array( 'ex' => 'single.php', 'res' => 'single.php', 'pp' => 'single.php', 'os' => 'single.php', 'oc' => 'single-open_call.php', 'j' => 'single-journal.php' ) as $k => $tpl ) {
		$check( $lang, "single $k", $render( $lang, "single $k", $tpl, array( 'singular' => true ), array( 'post' => $set[ $k ], 'path' => '/x/' ) ) );
	}
	foreach ( array( 'about' => 'page-about.php', 'archive' => 'page-open-archive.php', 'support' => 'page-support-us.php', 'contact' => 'page-contact-us.php' ) as $k => $tpl ) {
		$check( $lang, "page $k", $render( $lang, "page $k", $tpl, array( 'singular' => true ), array( 'post' => $set[ $k ], 'path' => '/p/' ) ) );
	}
	$check( $lang, 'search', $render( $lang, 'search', 'search.php', array( 'search' => true ), array( 'path' => '/?s=a', 'query' => array( 's' => 'a' ) ) ) );
	$check( $lang, '404', $render( $lang, '404', '404.php', array( '404' => true ), array( 'path' => '/nope/' ) ) );
}

/* ---- the switch, the menu and the strings behave per language */
$GLOBALS['pll']['current'] = 'id';
$home_id = $render( 'id', 'home', 'front-page.php', array( 'front' => true ), array( 'path' => '/id/' ) );
ok( 1 === preg_match( '#<a[^>]*hreflang="id"[^>]*aria-current="true"#', $home_id ) || 1 === preg_match( '#<a[^>]*aria-current="true"[^>]*hreflang="id"#', $home_id ), '[id] header: Indonesian is the marked language' );
ok( false !== strpos( $home_id, cemeti_t( 'search' ) ) && 'Cari' === cemeti_t( 'search' ), '[id] header: the search button is in Indonesian' );
$GLOBALS['pll']['current'] = 'en';
$home_en = $render( 'en', 'home', 'front-page.php', array( 'front' => true ), array( 'path' => '/' ) );
ok( false === strpos( $home_en, '>Cari<' ) && 'Search' === cemeti_t( 'search' ), '[en] header: the search button is in English' );
// The Indonesian entry shows its own title, the English one its own; neither leaks into the other language's list.
$arch_id = $render( 'id', 'archive exhibition', 'archive.php', array( 'pta' => 'exhibition' ), array( 'path' => '/id/exhibition/', 'query' => array( 'post_type' => 'exhibition', 'lang' => 'id' ), 'flags' => array( 'pta' => 'exhibition' ) ) );
ok( false !== strpos( $arch_id, 'Harta yang Paling Berharga adalah Keluarga (ID)' ) && false === strpos( $arch_id, 'Dalam Gelap, Kugenggam Ingatan' ), '[id] Exhibition list: the Indonesian entries only (this fixture has one)' );
// An Indonesian entry made without a picture shows its English twin's picture on the card.
ok( false !== strpos( $arch_id, '<img' ), '[id] Exhibition list: the card has a picture (taken from its English twin)' );


/* ---- things that depend on the two languages being linked */
$single_id = $render( 'id', 'single ex', 'single.php', array( 'singular' => true ), array( 'post' => $id_ex, 'path' => '/id/x/' ) );
ok( false !== strpos( $single_id, 'id="gallery"' ) && false !== strpos( $single_id, 'id="documentation"' ) && false !== strpos( $single_id, 'id="biography"' ), '[id] entry without its own pictures shows the gallery, documentation and biographies of its English twin' );
$sw = cemeti_language_switcher_items();
$GLOBALS['pll']['current'] = 'id';
$GLOBALS['post'] = CemetiWP::obj( $id_ex );
CemetiWP::request( '/id/x/', array( 'singular' => true ) );
$sw = cemeti_language_switcher_items();
ok( count( $sw ) === 2, 'switch: two languages' );
foreach ( array( 'ex' => array( $ids['ex_current'], $id_ex ), 'res' => array( $ids['res_current'], $id_res ), 'pp' => array( $ids['pp_past'], $id_pp ), 'j' => array( $ids['j_essay'], $id_j ) ) as $k => $pair ) {
	foreach ( array( array( 'id', $pair[1], 'en', $pair[0] ), array( 'en', $pair[0], 'id', $pair[1] ) ) as $dir ) {
		$GLOBALS['pll']['current'] = $dir[0];
		$GLOBALS['post'] = CemetiWP::obj( $dir[1] );
		CemetiWP::request( '/x/', array( 'singular' => true ) );
		$items = cemeti_language_switcher_items();
		$to    = array_values( array_filter( $items, static function ( $i ) use ( $dir ) { return $i['code'] === $dir[2]; } ) )[0];
		ok( get_permalink( $dir[3] ) === $to['url'], "switch: {$dir[0]} $k leads to its {$dir[2]} translation (" . $to['url'] . ')' );
	}
}
foreach ( array( 'about' => array( $ids['p_about'], $id_page['about'] ), 'support-us' => array( $ids['p_support'], $id_page['support-us'] ), 'contact-us' => array( $ids['p_contact'], $id_page['contact-us'] ) ) as $k => $pair ) {
	foreach ( array( array( 'id', $pair[1], 'en', $pair[0] ), array( 'en', $pair[0], 'id', $pair[1] ) ) as $dir ) {
		$GLOBALS['pll']['current'] = $dir[0];
		$GLOBALS['post'] = CemetiWP::obj( $dir[1] );
		CemetiWP::request( '/x/', array( 'singular' => true ) );
		$items = cemeti_language_switcher_items();
		$to    = array_values( array_filter( $items, static function ( $i ) use ( $dir ) { return $i['code'] === $dir[2]; } ) )[0];
		ok( get_permalink( $dir[3] ) === $to['url'], "switch: page $k, {$dir[0]} leads to its {$dir[2]} page" );
	}
}
// The overlay menu of each language opens that language's own fixed pages.
$home_id = $render( 'id', 'home', 'front-page.php', array( 'front' => true ), array( 'path' => '/id/' ) );
$home_en = $render( 'en', 'home', 'front-page.php', array( 'front' => true ), array( 'path' => '/' ) );
foreach ( array( 'about', 'open-archive', 'support-us', 'contact-us' ) as $k ) {
	ok( false !== strpos( $home_id, 'href="' . get_permalink( $id_page[ $k ] ) . '"' ), "[id] overlay menu: $k opens the Indonesian page" );
	$en_page = array( 'about' => $ids['p_about'], 'open-archive' => $ids['p_archive'], 'support-us' => $ids['p_support'], 'contact-us' => $ids['p_contact'] )[ $k ];
	ok( false !== strpos( $home_en, 'href="' . get_permalink( $en_page ) . '"' ) && false === strpos( $home_en, 'href="' . get_permalink( $id_page[ $k ] ) . '"' ), "[en] overlay menu: $k opens the English page" );
}
// Support Us: each language shows its own rows.
$sup_id = $render( 'id', 'support', 'page-support-us.php', array( 'singular' => true ), array( 'post' => $id_page['support-us'], 'path' => '/id/dukung-kami/' ) );
$sup_en = $render( 'en', 'support', 'page-support-us.php', array( 'singular' => true ), array( 'post' => $ids['p_support'], 'path' => '/support-us/' ) );
ok( 2 === substr_count( $sup_id, '<details' ) && false !== strpos( $sup_id, 'Mitra Program' ) && false === strpos( $sup_id, 'Patron of Cemeti' ), '[id] Support Us: its own two rows, not the English ones' );
ok( 4 === substr_count( $sup_en, '<details' ) && false === strpos( $sup_en, 'Mitra Program' ), '[en] Support Us: its own four rows' );
// Every card in a list has its picture, in both languages (the Indonesian ones come from the twins).
foreach ( array( 'en', 'id' ) as $lang ) {
	foreach ( array( 'exhibition' => 'archive.php', 'public_program' => 'archive.php', 'open_studio' => 'archive.php' ) as $type => $tpl ) {
		$html = $render( $lang, "cards $type", $tpl, array( 'pta' => $type ), array( 'path' => '/x/', 'query' => array( 'post_type' => $type, 'lang' => $lang ), 'flags' => array( 'pta' => $type ) ) );
		preg_match_all( '#<li class="cemeti-card">.*?</li>#s', $html, $cards );
		$withy = count( array_filter( $cards[0], static function ( $c ) { return false !== strpos( $c, '<img' ); } ) );
		ok( $cards[0] && $withy >= 1, "[$lang] $type list: cards are listed and the ones with pictures show them" );
		if ( 'id' === $lang ) {
			$twin_title = array( 'exhibition' => 'Harta yang Paling Berharga', 'public_program' => 'Wicara Publik', 'open_studio' => 'Studio Terbuka' )[ $type ];
			$mine = array_values( array_filter( $cards[0], static function ( $c ) use ( $twin_title ) { return false !== strpos( $c, $twin_title ); } ) );
			ok( $mine && false !== strpos( $mine[0], '<img' ), "[id] $type list: the card of an Indonesian entry made without a picture shows its English twin's" );
		}
	}
}
$jr_id = $render( 'id', 'journal', 'archive-journal.php', array( 'pta' => 'journal' ), array( 'path' => '/id/journal/', 'query' => array( 'post_type' => 'journal', 'lang' => 'id' ), 'flags' => array( 'pta' => 'journal' ) ) );
ok( false !== strpos( $jr_id, 'class="cemeti-row__cat">Essay<' ), '[id] Journal list: the category of the old site (Essay) is shown, not the language' );
if ( getenv( "QA_DUMP" ) ) { file_put_contents( "/tmp/id-home.html", $home_id ); file_put_contents( "/tmp/id-single.html", $render( "id", "x", "single.php", array( "singular" => true ), array( "post" => $id_ex, "path" => "/x/" ) ) ); }
qa_done();
