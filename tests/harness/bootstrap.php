<?php
/**
 * Loads the simulated WordPress and the Cemeti theme, and offers fixtures and a renderer.
 * Any PHP warning, notice or deprecation raised by theme code becomes an exception.
 */

error_reporting( E_ALL );
set_error_handler(
	static function ( $no, $str, $file, $line ) {
		if ( ! ( error_reporting() & $no ) ) {
			return false; // Silenced with @ in the code under test.
		}
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

define( 'CEMETI_THEME_DIR', dirname( __DIR__, 2 ) . '/themes/scriptmedia-cemeti' );
require __DIR__ . '/wp-stubs.php';

/** Load the theme once per process (its hooks are registered by require_once). */
function qa_boot() {
	CemetiWP::reset();
	require_once CEMETI_THEME_DIR . '/functions.php';
	do_action( 'after_setup_theme' );
	do_action( 'init' );
}

/** Empty the data but keep the theme's hooks and registered types. */
function qa_reset_data() {
	CemetiWP::$posts = CemetiWP::$meta = CemetiWP::$terms = CemetiWP::$rel = CemetiWP::$options = CemetiWP::$transients = array();
	CemetiWP::$mails = array();
	CemetiWP::$next_id = 1000;
	CemetiWP::$next_term = 1;
	CemetiWP::$today = '20260315';
	do_action( 'init' ); // Seeds the default terms again.
}

/** Tiny assertion helpers. */
$GLOBALS['qa'] = array( 'pass' => 0, 'fail' => array() );
function ok( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['qa']['pass'];
	} else {
		$GLOBALS['qa']['fail'][] = $msg;
		echo "FAIL $msg\n";
	}
}
function qa_done() {
	$f = count( $GLOBALS['qa']['fail'] );
	echo "\n{$GLOBALS['qa']['pass']} checks passed, $f failed\n";
	exit( $f ? 1 : 0 );
}

/** Seed a realistic site. Returns named IDs. */
function qa_seed() {
	$ids = array();
	$ids['thumb'] = 501;
	$term = static function ( $tax, $name, $desc = '' ) {
		$e = term_exists( $name, $tax );
		if ( $e && $desc ) {
			CemetiWP::$terms[ $tax ][ $e['term_id'] ]['description'] = $desc;
		}
	};
	$term( 'residency_type', 'Reguler', 'In each residency period we host one Indonesian artist and one or two artists from outside Indonesia.' );
	$term( 'residency_type', 'Research', 'Selected participants are facilitated by an assistant during the residency.' );

	$ex = static function ( $title, $start, $end, $extra = array() ) {
		return CemetiWP::post(
			array_merge(
				array(
					'post_type'    => 'exhibition',
					'post_title'   => $title,
					'post_content' => "Paragraf pertama pengantar.\n\nParagraf kedua pengantar.",
					'thumb'        => 501,
					'meta'         => array( 'cemeti_start_date' => $start, 'cemeti_end_date' => $end ),
				),
				$extra
			)
		);
	};
	$ids['ex_past']    = $ex( 'Dalam Gelap, Kugenggam Ingatan', '20251121', '20251228', array( 'post_date' => '2025-11-01 10:00:00' ) );
	$ids['ex_current'] = $ex(
		'Harta yang Paling Berharga adalah Keluarga',
		'20260306',
		'20260418',
		array(
			'post_date' => '2026-03-01 10:00:00',
			'meta'      => array(
				'cemeti_start_date'          => '20260306',
				'cemeti_end_date'            => '20260418',
				'cemeti_artists'             => "Agung Kurniawan\nNindityo Adipurnomo",
				'cemeti_opening'             => '6 March 2026 | 13.00 WIB',
				'cemeti_public_program_info' => "26 Maret 2026\nWicara Seniman",
				'cemeti_venue_info'          => "Cemeti – Institut untuk Seni dan Masyarakat\nSelasa – Sabtu | 10:00 – 17:00",
				'cemeti_gallery'             => array( 601, 602, 603, 604 ),
				'cemeti_gallery_caption'     => 'Tampilan pameran',
				'cemeti_documentation'       => array( 611, 612 ),
				'cemeti_documentation_caption' => 'Dokumentasi pembukaan',
				'cemeti_biographies'         => array( array( 'name' => 'Agung Kurniawan', 'photo' => 701, 'bio' => "Lahir di Jember.\n\nTinggal di Yogyakarta." ), array( 'name' => 'Nindityo', 'photo' => 0, 'bio' => 'Seniman.' ) ),
				'cemeti_booklet_url'         => 'https://example.org/booklet.pdf',
				'cemeti_seo_description'     => 'Pameran kelompok tentang keluarga dan ingatan.',
			),
		)
	);
	$ids['ex_future']  = $ex( 'Pameran Mendatang "Tanah" & <Air>', '20260901', '20261001', array( 'post_date' => '2026-02-01 10:00:00' ) );
	$ids['ex_nodate']  = CemetiWP::post( array( 'post_type' => 'exhibition', 'post_title' => 'Beastly', 'post_date' => '2011-07-07 19:00:00' ) );
	wp_set_object_terms( $ids['ex_current'], array( 'Group' ), 'exhibition_type' );
	wp_set_object_terms( $ids['ex_past'], array( 'Solo' ), 'exhibition_type' );

	$ids['res_current'] = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Residency Program 2026', 'post_excerpt' => 'Program residensi untuk seniman pasca studio.', 'thumb' => 502, 'meta' => array( 'cemeti_start_date' => '20260301', 'cemeti_end_date' => '20260531', 'cemeti_artists' => "Zulfian Amrullah\nJulian Grosmann" ) ) );
	$ids['res_past']    = CemetiWP::post( array( 'post_type' => 'residency', 'post_title' => 'Residency Program 2025', 'meta' => array( 'cemeti_start_date' => '20251001', 'cemeti_end_date' => '20251130' ) ) );
	wp_set_object_terms( $ids['res_current'], array( 'Reguler' ), 'residency_type' );

	$ids['pp_past']    = CemetiWP::post( array( 'post_type' => 'public_program', 'post_title' => 'Public Talk: Power, Politics and the Street', 'thumb' => 503, 'meta' => array( 'cemeti_start_date' => '20260130', 'cemeti_end_date' => '20260130', 'cemeti_time_text' => '15.00 – 17.00 WIB', 'cemeti_speakers' => 'Iola Lenzi', 'cemeti_documentation' => array( 621 ), 'cemeti_biographies' => array( array( 'name' => 'Iola Lenzi', 'photo' => 702, 'bio' => 'Kurator.' ) ) ) ) );
	$ids['pp_future']  = CemetiWP::post( array( 'post_type' => 'public_program', 'post_title' => 'Screening', 'meta' => array( 'cemeti_start_date' => '20260501', 'cemeti_end_date' => '20260501' ) ) );
	$ids['os_current'] = CemetiWP::post( array( 'post_type' => 'open_studio', 'post_title' => 'Open Studio: Angki Purbandono', 'thumb' => 504, 'meta' => array( 'cemeti_start_date' => '20260310', 'cemeti_end_date' => '20260330', 'cemeti_artists' => 'Angki Purbandono' ) ) );

	$ids['oc_open']     = CemetiWP::post( array( 'post_type' => 'open_call', 'post_title' => 'SEA Artist Residency Program', 'thumb' => 505, 'post_content' => 'Deadline di bawah.', 'meta' => array( 'cemeti_deadline' => '20260814' ) ) );
	$ids['oc_external'] = CemetiWP::post( array( 'post_type' => 'open_call', 'post_title' => 'Open Call Eksternal', 'meta' => array( 'cemeti_deadline' => '20260820', 'cemeti_application_url' => 'https://forms.example.org/apply' ) ) );
	$ids['oc_closed']   = CemetiWP::post( array( 'post_type' => 'open_call', 'post_title' => 'Panggilan Terbuka Kurator', 'meta' => array( 'cemeti_deadline' => '20221020' ) ) );
	$ids['oc_nodocs']   = CemetiWP::post( array( 'post_type' => 'open_call', 'post_title' => 'Open Call tanpa berkas', 'meta' => array( 'cemeti_deadline' => '20260901', 'cemeti_documents' => 'none' ) ) );

	$ids['j_news']  = CemetiWP::post( array( 'post_type' => 'journal', 'post_title' => 'Rangkaian Perayaan #37thCemeti', 'post_date' => '2025-01-15 09:00:00', 'post_content' => 'Kabar terbaru.' ) );
	$ids['j_essay'] = CemetiWP::post( array( 'post_type' => 'journal', 'post_title' => 'Towards a Constellation of Friendship', 'post_date' => '2024-07-01 09:00:00', 'thumb' => 506, 'post_content' => "Esai panjang.\n\nParagraf dua.", 'meta' => array( 'cemeti_author' => 'Mira Asriningtyas', 'cemeti_related' => array( $ids['ex_current'] ) ) ) );
	$ids['j_cur']   = CemetiWP::post( array( 'post_type' => 'journal', 'post_title' => 'TAKAR-TELUSUR', 'post_date' => '2023-11-08 09:00:00' ) );
	wp_set_object_terms( $ids['j_news'], array( 'News' ), 'journal_category' );
	wp_set_object_terms( $ids['j_essay'], array( 'Essay' ), 'journal_category' );
	wp_set_object_terms( $ids['j_cur'], array( 'Curatorial Essay' ), 'journal_category' );
	update_post_meta( $ids['ex_current'], 'cemeti_related', array( $ids['j_essay'] ) );
	wp_set_object_terms( $ids['ex_current'], array( 'Maintenance Works' ), 'project' );

	$ids['p_about']   = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'About', 'post_name' => 'about', 'thumb' => 507, 'post_content' => "Cemeti adalah platform seni kontemporer.\n\nDidirikan 1988." ) );
	$ids['p_archive'] = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Open Archive', 'post_name' => 'open-archive', 'post_content' => 'Daftar karya dan program.' ) );
	$ids['p_support'] = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Support Us', 'post_name' => 'support-us', 'post_content' => 'Misi kami.' ) );
	foreach ( array( 'Patron of Cemeti', 'Friends of Cemeti', 'Annual Benefit', 'Collaboration' ) as $i => $t ) {
		CemetiWP::post( array( 'post_type' => 'page', 'post_title' => $t, 'post_parent' => $ids['p_support'], 'menu_order' => $i, 'post_content' => "Isi $t." ) );
	}
	$ids['p_contact'] = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Contact Us', 'post_name' => 'contact-us', 'post_content' => 'Hubungi kami.' ) );
	$ids['p_plain']   = CemetiWP::post( array( 'post_type' => 'page', 'post_title' => 'Halaman Biasa', 'post_content' => 'Isi biasa.' ) );

	foreach ( array( array( 'Mella Jaarsma', 'Advisory Board', 'Seniman' ), array( 'Nindityo Adipurnomo', 'Advisory Board', 'Seniman' ), array( 'Dito Yuwono', 'Team', 'Direktur' ), array( 'Mira Asriningtyas', 'Team', 'Direktur' ) ) as $i => $pp ) {
		$id = CemetiWP::post( array( 'post_type' => 'person', 'post_title' => $pp[0], 'menu_order' => $i, 'thumb' => 510 + $i, 'meta' => array( 'cemeti_role' => $pp[2] ) ) );
		wp_set_object_terms( $id, array( $pp[1] ), 'person_group' );
	}
	return $ids;
}

/**
 * Render a template file and return its HTML.
 *
 * @param string $template Template file relative to the theme (e.g. "single.php").
 * @param array  $ctx      Conditional flags and path, see CemetiWP::request().
 * @param array  $o        Options: post (ID for singular), query (WP_Query args), flags (WP_Query flags), get (array for $_GET).
 */
function qa_render( $template, $ctx = array(), $o = array() ) {
	$path = isset( $o['path'] ) ? $o['path'] : '/';
	CemetiWP::request( $path, $ctx );
	CemetiWP::$styles  = array();
	CemetiWP::$scripts = array();
	$_GET              = isset( $o['get'] ) ? $o['get'] : array();
	do_action( 'wp_enqueue_scripts' );
	if ( ! empty( $o['post'] ) ) {
		$GLOBALS['post']     = CemetiWP::obj( $o['post'] );
		$GLOBALS['wp_query'] = new WP_Query();
		$GLOBALS['wp_query']->posts = array( CemetiWP::obj( $o['post'] ) );
	} else {
		$GLOBALS['post'] = null;
		$q               = new WP_Query();
		$q->query_vars   = isset( $o['query'] ) ? $o['query'] : array();
		$q->flags        = array_merge( array( 'main' => true ), isset( $o['flags'] ) ? $o['flags'] : array() );
		$ref             = $q;
		do_action( 'pre_get_posts', $ref );
		$ref->run();
		$GLOBALS['wp_query'] = $ref;
	}
	ob_start();
	try {
		include CEMETI_THEME_DIR . '/' . $template;
	} catch ( Throwable $e ) {
		ob_end_clean();
		throw $e;
	}
	return ob_get_clean();
}

/** Structural checks on rendered HTML. Returns a list of problems. */
function qa_check_html( $html, $opts = array() ) {
	$problems = array();
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	foreach ( libxml_get_errors() as $err ) {
		if ( 801 === $err->code ) {
			continue; // Unknown HTML5 tag name (nav, template, ...): libxml2 predates HTML5.
		}
		$problems[] = 'markup: ' . trim( $err->message ) . ' (line ' . $err->line . ')';
	}
	libxml_clear_errors();
	$xp = new DOMXPath( $dom );

	$seen = array();
	foreach ( $xp->query( '//*[@id]' ) as $n ) {
		$id = $n->getAttribute( 'id' );
		if ( isset( $seen[ $id ] ) ) {
			$problems[] = "duplicate id #$id";
		}
		$seen[ $id ] = true;
	}
	foreach ( $xp->query( '//*[@aria-controls]|//*[@aria-labelledby]|//a[starts-with(@href,"#") and string-length(@href)>1]|//label[@for]' ) as $n ) {
		foreach ( array( 'aria-controls', 'aria-labelledby', 'for' ) as $attr ) {
			if ( $n->hasAttribute( $attr ) && ! isset( $seen[ $n->getAttribute( $attr ) ] ) ) {
				$problems[] = "$attr points to missing #" . $n->getAttribute( $attr );
			}
		}
		if ( 'a' === $n->nodeName ) {
			$target = substr( $n->getAttribute( 'href' ), 1 );
			if ( ! isset( $seen[ $target ] ) ) {
				$problems[] = "anchor link to missing #$target";
			}
		}
	}
	foreach ( $xp->query( '//img' ) as $n ) {
		if ( ! $n->hasAttribute( 'alt' ) ) {
			$problems[] = 'img without alt: ' . $n->getAttribute( 'src' );
		}
	}
	foreach ( $xp->query( '//a' ) as $n ) {
		$h = trim( $n->getAttribute( 'href' ) );
		if ( $n->hasAttribute( 'href' ) && ( '' === $h || '#' === $h || 0 === stripos( $h, 'javascript:' ) ) ) {
			$problems[] = 'bad href "' . $h . '" on <a>' . substr( trim( $n->textContent ), 0, 30 );
		}
		if ( '_blank' === $n->getAttribute( 'target' ) && false === strpos( $n->getAttribute( 'rel' ), 'noopener' ) ) {
			$problems[] = 'target=_blank without noopener: ' . $h;
		}
		$name = trim( $n->textContent ) . $n->getAttribute( 'aria-label' );
		if ( '' === $name && ! $xp->query( './/img[@alt!=""]', $n )->length && 'true' !== $n->getAttribute( 'aria-hidden' ) ) {
			$problems[] = 'link without accessible name: ' . $h;
		}
	}
	foreach ( $xp->query( '//button' ) as $n ) {
		if ( '' === trim( $n->textContent ) . $n->getAttribute( 'aria-label' ) && ! $xp->query( './/svg', $n )->length ) {
			$problems[] = 'button without accessible name';
		}
	}
	foreach ( $xp->query( '//input[not(@type="hidden") and not(@type="submit")]|//textarea|//select' ) as $n ) {
		$id = $n->getAttribute( 'id' );
		$has_label = $n->hasAttribute( 'aria-label' ) || ( $id && $xp->query( '//label[@for="' . $id . '"]' )->length ) || $xp->query( 'ancestor::label', $n )->length;
		if ( ! $has_label ) {
			$problems[] = 'form control without a label: ' . ( $n->getAttribute( 'name' ) ?: $n->nodeName );
		}
	}
	foreach ( $xp->query( '//form' ) as $n ) {
		if ( '' === $n->getAttribute( 'action' ) ) {
			$problems[] = 'form without action';
		}
	}
	$h1 = $xp->query( '//h1' )->length;
	if ( empty( $opts['no_h1_check'] ) && 1 !== $h1 ) {
		$problems[] = "expected exactly one <h1>, found $h1";
	}
	$last = 0;
	foreach ( $xp->query( '//h1|//h2|//h3|//h4' ) as $n ) {
		$lvl = (int) substr( $n->nodeName, 1 );
		if ( $last && $lvl > $last + 1 ) {
			$problems[] = "heading level jumps from h$last to h$lvl (" . substr( trim( $n->textContent ), 0, 30 ) . ')';
		}
		$last = $lvl;
	}
	if ( false !== strpos( $html, '<?php' ) || false !== strpos( $html, '&amp;amp;' ) ) {
		$problems[] = 'raw PHP or double-escaped entities in output';
	}
	if ( preg_match( '/(Warning|Notice|Fatal error|Deprecated):/', strip_tags( $html ) ) ) {
		$problems[] = 'PHP message in output';
	}
	return $problems;
}
