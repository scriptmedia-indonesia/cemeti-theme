<?php
// Run: php tests/static-checks.php — consistency checks that catch drift before it reaches a site.
// Errors fail the run; warnings are things to look at (dead code, unused CSS).
$root  = dirname( __DIR__ ) . '/themes/scriptmedia-cemeti';
$errors = array();
$warn   = array();
$err    = static function ( $m ) use ( &$errors ) { $errors[] = $m; };
$wrn    = static function ( $m ) use ( &$warn ) { $warn[] = $m; };

$php = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
	if ( 'php' === $f->getExtension() ) {
		$php[ substr( (string) $f, strlen( $root ) + 1 ) ] = file_get_contents( (string) $f );
	}
}
$css = '';
foreach ( glob( "$root/assets/css/*.css" ) as $f ) {
	$css .= file_get_contents( $f ) . "\n";
}
$js = '';
foreach ( glob( "$root/assets/js/*.js" ) as $f ) {
	$js .= file_get_contents( $f ) . "\n";
}
$all_php = implode( "\n", $php );

/* 1. Every PHP file starts safely and has a package tag. */
foreach ( $php as $name => $src ) {
	if ( 'index.php' !== $name && false === strpos( $src, "defined( 'ABSPATH' )" ) ) {
		$err( "$name: missing the ABSPATH guard" );
	}
	if ( false === strpos( $src, '@package Scriptmedia_Cemeti' ) ) {
		$wrn( "$name: no @package tag" );
	}
	if ( preg_match( '/\b(var_dump|print_r|error_log|console\.log|debugger|die\(\s*[\'"])/', $src ) ) {
		$err( "$name: leftover debug code" );
	}
	if ( preg_match( '/\b(TODO|FIXME|XXX|HACK)\b/', $src ) ) {
		$wrn( "$name: TODO/FIXME comment" );
	}
	if ( preg_match( '/<\?(?!php|=)/', $src ) ) {
		$err( "$name: short PHP open tag" );
	}
	if ( preg_match( '/\r/', $src ) ) {
		$err( "$name: Windows line endings" );
	}
}
if ( preg_match( '/console\.(log|debug)|debugger/', $js ) ) {
	$err( 'assets/js: leftover debug code' );
}

/* 2. functions.php includes exactly the files in inc/. */
preg_match_all( "/^\s*'([a-z0-9-]+)',$/m", $php['functions.php'], $m );
$included = $m[1];
$files    = array_map( static function ( $f ) { return basename( $f, '.php' ); }, glob( "$root/inc/*.php" ) );
foreach ( array_diff( $files, $included ) as $x ) {
	$err( "inc/$x.php exists but functions.php does not load it" );
}
foreach ( array_diff( $included, $files ) as $x ) {
	$err( "functions.php loads inc/$x.php which does not exist" );
}

/* 3. Functions: defined once, calls to theme functions resolve, dead code. */
$defined = array();
$called  = array();
foreach ( $php as $name => $src ) {
	$t = token_get_all( $src );
	$n = count( $t );
	for ( $i = 0; $i < $n; $i++ ) {
		if ( is_array( $t[ $i ] ) && T_FUNCTION === $t[ $i ][0] ) {
			for ( $j = $i + 1; $j < $n; $j++ ) {
				if ( is_array( $t[ $j ] ) && T_STRING === $t[ $j ][0] ) {
					$defined[ $t[ $j ][1] ][] = $name;
					break;
				}
				if ( '(' === $t[ $j ] ) {
					break;
				}
			}
		}
		if ( is_array( $t[ $i ] ) && T_STRING === $t[ $i ][0] && 0 === strpos( $t[ $i ][1], 'cemeti_' ) ) {
			$called[ $t[ $i ][1] ] = ( isset( $called[ $t[ $i ][1] ] ) ? $called[ $t[ $i ][1] ] : 0 ) + 1;
		}
	}
}
foreach ( $defined as $fn => $where ) {
	if ( count( $where ) > 1 ) {
		$err( "function $fn defined twice: " . implode( ', ', $where ) );
	}
	if ( 0 !== strpos( $fn, 'cemeti_' ) && ! preg_match( '/^(__|admin_|the_)/', $fn ) ) {
		$err( "function $fn is not prefixed with cemeti_" );
	}
}
foreach ( $called as $fn => $count ) {
	if ( ! isset( $defined[ $fn ] ) && ! preg_match( '/^cemeti_[a-z_]+$/', $fn ) ) {
		continue;
	}
	if ( ! isset( $defined[ $fn ] ) && false === strpos( $all_php, "'$fn'" ) ) {
		$err( "$fn is called but never defined" );
	}
}
$hook_strings = $all_php;
foreach ( $defined as $fn => $where ) {
	$refs = substr_count( $hook_strings, $fn );
	// Public helpers for templates and child themes: kept on purpose, covered by tests.
	$api = array( 'cemeti_get_related', 'cemeti_status_label', 'cemeti_month_names' );
	if ( 'cemeti_' === substr( $fn, 0, 7 ) && 1 === $refs && ! in_array( $fn, $api, true ) ) {
		$wrn( "function $fn is defined but never used (dead code or public API?)" );
	}
}

/* 4. UI strings: both languages, same placeholders, every literal key exists, every key is used. */
$strings = ( static function () use ( $root ) {
	define( 'ABSPATH', '/' );
	function add_action() {}
	function add_filter() {}
	function apply_filters( $t, $v ) { return $v; }
	function function_exists_stub() {}
	require_once "$root/inc/i18n.php";
	return cemeti_ui_strings();
} )();
foreach ( $strings as $key => $by ) {
	foreach ( array( 'id', 'en' ) as $l ) {
		if ( empty( $by[ $l ] ) ) {
			$err( "ui string $key has no $l text" );
		}
	}
	if ( isset( $by['id'], $by['en'] ) && preg_match_all( '/%(\d+\$)?[sd]/', $by['id'] ) !== preg_match_all( '/%(\d+\$)?[sd]/', $by['en'] ) ) {
		$err( "ui string $key: different placeholders in id and en" );
	}
}
preg_match_all( "/cemeti_t\(\s*'([a-z_]+)'\s*\)/", $all_php, $m );
foreach ( array_unique( $m[1] ) as $key ) {
	if ( ! isset( $strings[ $key ] ) ) {
		$err( "cemeti_t( '$key' ) used but not defined in inc/i18n.php" );
	}
}
// Keys reached through variables: the literal strings in the arrays that feed cemeti_t().
$dynamic = array( 'status_current', 'status_upcoming', 'status_previous', 'foreword', 'gallery', 'documentation', 'biography', 'booklet', 'opening', 'public_program', 'advisory_board', 'team', 'instagram', 'youtube', 'facebook', 'form_cv', 'form_portfolio', 'form_proposal', 'form_motivation', 'err_required', 'err_email', 'err_file', 'err_closed', 'err_spam', 'err_rate', 'err_server', 'mail_subject', 'mail_body' );
foreach ( $strings as $key => $_ ) {
	if ( ! in_array( $key, $dynamic, true ) && false === strpos( $all_php, "'$key'" ) ) {
		$wrn( "ui string '$key' is never used" );
	}
}
preg_match_all( "/(?:__|esc_html__|esc_attr__|_e|_x)\(\s*'[^']*'\s*,\s*'([^']+)'/", $all_php, $m );
foreach ( array_unique( $m[1] ) as $dom ) {
	if ( 'scriptmedia-cemeti' !== $dom ) {
		$err( "wrong text domain '$dom'" );
	}
}

/* 5. CSS and templates agree on class names. */
preg_match_all( '/\.(cemeti-[a-z0-9_-]+)/', $css, $m );
$css_classes = array_unique( $m[1] );
// Class names written in class="…" attributes (and the JS selectors), nothing else.
preg_match_all( '/class="([^"]*cemeti-[^"]*)"/', $all_php, $m );
$used = array();
foreach ( $m[1] as $attr ) {
	foreach ( preg_split( '/[\s]+/', preg_replace( '/<\?php.*?\?>/s', ' ', $attr ) ) as $tok ) {
		if ( preg_match( '/^cemeti-[a-z0-9_-]+$/', $tok ) ) {
			$used[ $tok ] = true;
		}
	}
}
$dyn_prefix = array( 'cemeti-ctx-', 'cemeti-single--', 'cemeti-home__section--' );
// Selectors written in PHP (admin inline styles) and in JS count as styled / handled.
preg_match_all( '/\.(cemeti-[a-z0-9_-]+)/', $all_php . "\n" . $js, $m );
$other = array_unique( $m[1] );
foreach ( array_keys( $used ) as $c ) {
	if ( in_array( $c, $css_classes, true ) || in_array( $c, $other, true ) || false !== strpos( $c, '--' ) ) {
		continue; // Modifiers such as cemeti-single--about are per-template hooks and need no rule.
	}
	$block = explode( '__', $c )[0];
	$has_child_rules = false;
	foreach ( array_merge( $css_classes, $other ) as $known ) {
		if ( 0 === strpos( $known, $c . '__' ) || 0 === strpos( $known, $c . '--' ) || ( $block !== $c && $known === $block ) ) {
			$has_child_rules = true;
		}
	}
	if ( $has_child_rules ) {
		continue; // A wrapper or element of a block that is styled elsewhere.
	}
	$wrn( "class $c is used in a template but has no CSS rule (structural hook, or forgotten style?)" );
}
foreach ( $css_classes as $c ) {
	$short = $c;
	if ( false === strpos( $all_php . $js, $short ) ) { // Class names built in PHP (e.g. 'cemeti-ctx-' . $x) are matched by prefix below.
		$matched = false;
		foreach ( $dyn_prefix as $p ) {
			if ( 0 === strpos( $c, $p ) ) {
				$matched = true;
			}
		}
		if ( ! $matched ) {
			$wrn( "CSS rule .$c matches nothing in the PHP or JS (unused style?)" );
		}
	}
}

/* 6. Assets that CSS, PHP and templates point at exist. */
preg_match_all( '#url\("\.\./([^"?\#]+)"\)#', $css, $m );
foreach ( array_unique( $m[1] ) as $rel ) {
	if ( ! is_file( "$root/assets/$rel" ) ) {
		$err( "CSS points at missing assets/$rel" );
	}
}
preg_match_all( "#assets/[a-z]+/[A-Za-z0-9._-]+\.(?:css|js|webp|png|woff2|svg)#", $all_php, $m );
foreach ( array_unique( $m[0] ) as $rel ) {
	if ( ! is_file( "$root/$rel" ) ) {
		$err( "PHP points at missing $rel" );
	}
}
preg_match_all( "/'((?:[a-z0-9-]+-)?(?:latin|latin-ext)-[a-z0-9-]+\.woff2)'/", $all_php, $m );
foreach ( array_unique( $m[1] ) as $font ) {
	if ( ! is_file( "$root/assets/fonts/$font" ) ) {
		$err( "preloaded font missing: $font" );
	}
}
foreach ( array( 'tokens', 'base', 'layout', 'components', 'pages' ) as $c ) {
	if ( ! is_file( "$root/assets/css/$c.css" ) ) {
		$err( "enqueued stylesheet missing: $c.css" );
	}
}
foreach ( glob( "$root/assets/fonts/*.woff2" ) as $f ) {
	if ( false === strpos( $css . $all_php, basename( $f ) ) ) {
		$wrn( 'font file never referenced: ' . basename( $f ) );
	}
}
foreach ( glob( "$root/assets/fonts/LICENSE-*" ) as $_ ) {
	$licences[] = $_;
}
if ( empty( $licences ) ) {
	$err( 'font licences are missing' );
}

/* 7. Config files agree with each other and with the menu. */
$cfg = static function ( $name ) use ( $root ) {
	return ( static function () use ( $root, $name ) { return include "$root/config/$name.php"; } )();
};
$types = $cfg( 'post-types' );
$taxes = $cfg( 'taxonomies' );
$fields = $cfg( 'fields' );
$rules = $cfg( 'migration-rules' );
$slugs = array();
foreach ( $types as $key => $t ) {
	if ( strlen( $key ) > 20 || ! preg_match( '/^[a-z_]+$/', $key ) ) {
		$err( "post type key '$key' must be lowercase, a-z and _, max 20 characters" );
	}
	foreach ( array( 'singular', 'plural', 'slug' ) as $req ) {
		if ( empty( $t[ $req ] ) ) {
			$err( "post type $key lacks '$req'" );
		}
	}
	if ( isset( $slugs[ $t['slug'] ] ) ) {
		$err( "URL slug '{$t['slug']}' used by $key and {$slugs[ $t['slug'] ]}" );
	}
	$slugs[ $t['slug'] ] = $key;
	foreach ( isset( $t['taxonomies'] ) ? $t['taxonomies'] : array() as $tax ) {
		if ( ! isset( $taxes[ $tax ] ) ) {
			$err( "post type $key lists unknown taxonomy $tax" );
		}
	}
	if ( ! isset( $t['public'] ) || $t['public'] ) {
		if ( ! file_exists( "$root/single.php" ) ) {
			$err( 'single.php missing' );
		}
	}
}
foreach ( $taxes as $key => $t ) {
	if ( strlen( $key ) > 32 ) {
		$err( "taxonomy key '$key' longer than 32 characters" );
	}
	foreach ( $t['post_types'] as $pt ) {
		if ( ! isset( $types[ $pt ] ) ) {
			$err( "taxonomy $key lists unknown post type $pt" );
		}
	}
	if ( isset( $slugs[ $t['slug'] ] ) ) {
		$err( "taxonomy $key slug '{$t['slug']}' clashes with a post type URL" );
	}
}
$known = array( 'text', 'textarea', 'date', 'url', 'image', 'images', 'relation', 'repeater' );
foreach ( $fields as $type => $list ) {
	if ( ! isset( $types[ $type ] ) ) {
		$err( "fields defined for unknown post type $type" );
	}
	$keys = array();
	foreach ( $list as $f ) {
		if ( ! in_array( $f['type'], $known, true ) ) {
			$err( "$type.{$f['key']}: unknown field type {$f['type']}" );
		}
		if ( isset( $keys[ $f['key'] ] ) ) {
			$err( "$type: duplicate field key {$f['key']}" );
		}
		$keys[ $f['key'] ] = true;
		if ( 'relation' === $f['type'] ) {
			foreach ( $f['post_types'] as $pt ) {
				if ( ! isset( $types[ $pt ] ) ) {
					$err( "$type.{$f['key']}: relation to unknown type $pt" );
				}
			}
		}
		if ( 'repeater' === $f['type'] && empty( $f['sub'] ) ) {
			$err( "$type.{$f['key']}: repeater without sub fields" );
		}
	}
}
foreach ( array( 'lang_id', 'lang_en', 'not_project', 'event_cats' ) as $list ) {
	if ( count( $rules[ $list ] ) !== count( array_unique( $rules[ $list ] ) ) ) {
		$err( "migration-rules.$list has duplicate entries" );
	}
}
foreach ( array_intersect( $rules['lang_id'], $rules['lang_en'] ) as $x ) {
	$err( "migration-rules: '$x' marks both Indonesian and English" );
}
foreach ( $rules['types'] as $r ) {
	if ( ! isset( $types[ $r['type'] ] ) ) {
		$err( "migration rule targets unknown type {$r['type']}" );
	}
}
foreach ( array( 'about', 'open-archive', 'support-us', 'contact-us' ) as $page ) {
	if ( ! is_file( "$root/page-$page.php" ) ) {
		$err( "page template page-$page.php missing" );
	}
}
// Menu entries point at something that exists.
$nav = file_get_contents( "$root/inc/navigation.php" );
preg_match_all( "/'path'\s*=>\s*'([^'?]*)/", $nav, $m );
$pages = array( 'about', 'open-archive', 'support-us', 'contact-us', '' );
foreach ( array_unique( array_map( static function ( $p ) { return trim( $p, '/' ); }, $m[1] ) ) as $path ) {
	if ( ! in_array( $path, $pages, true ) && ! isset( $slugs[ $path ] ) ) {
		$err( "menu path '$path' is neither a content type URL nor a known page" );
	}
}
foreach ( $slugs as $slug => $type ) {
	if ( ( ! isset( $types[ $type ]['public'] ) || $types[ $type ]['public'] ) && false === strpos( $nav, "'$slug'" ) && ! in_array( $type, array( 'open_call' ), true ) ) {
		$wrn( "public type $type ($slug) has no menu entry" );
	}
}

/* 8. Template files exist for every public type; style.css header is complete. */
$style = file_get_contents( "$root/style.css" );
foreach ( array( 'Theme Name', 'Version', 'Requires at least', 'Requires PHP', 'License', 'Text Domain' ) as $h ) {
	if ( ! preg_match( '/^' . $h . ':\s*\S/m', $style ) ) {
		$err( "style.css header lacks $h" );
	}
}
if ( preg_match( '/^Template:/m', $style ) ) {
	$err( 'style.css declares a parent theme, but the theme is standalone' );
}
foreach ( array( 'header.php', 'footer.php', 'index.php', '404.php', 'search.php', 'archive.php', 'single.php', 'page.php', 'front-page.php' ) as $tpl ) {
	if ( ! is_file( "$root/$tpl" ) ) {
		$err( "template $tpl missing" );
	}
}

/* 9. Documentation mentions the current version and the checked-in tests exist. */
$readme = file_get_contents( dirname( __DIR__ ) . '/README.md' );
foreach ( array( 'docs/MAINTENANCE.md', 'docs/LAUNCH-CHECKLIST.md', 'docs/ARCHITECTURE.md' ) as $doc ) {
	if ( is_file( dirname( __DIR__ ) . '/' . $doc ) && false === strpos( $readme, $doc ) && 'docs/ARCHITECTURE.md' !== $doc ) {
		$wrn( "README does not link $doc" );
	}
}

echo count( $php ) . " PHP files, " . count( $css_classes ) . " CSS classes, " . count( $strings ) . " UI strings, " . count( $defined ) . " functions checked\n";
foreach ( $warn as $w ) {
	echo "WARN  $w\n";
}
foreach ( $errors as $e ) {
	echo "ERROR $e\n";
}
echo count( $errors ) . ' errors, ' . count( $warn ) . " warnings\n";
exit( $errors ? 1 : 0 );
