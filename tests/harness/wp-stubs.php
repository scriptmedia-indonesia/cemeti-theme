<?php
/**
 * A small in-memory imitation of WordPress, enough to load the Cemeti theme and run its
 * templates, hooks, queries and request handlers without a database or web server.
 *
 * It is not WordPress: SQL is replaced by PHP filtering, so it proves the theme builds correct
 * queries and templates, not that MySQL returns the same rows. Keep it small; add a function
 * here only when a theme file starts calling it.
 *
 * Usage: require this file, call CemetiWP::reset(), load the theme's functions.php, fire
 * do_action( 'after_setup_theme' ) and do_action( 'init' ), seed data, then render.
 */

define( 'ABSPATH', '/wp/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/** Thrown by wp_safe_redirect() so the code after it (exit) never runs. */
class CemetiRedirect extends Exception {
	public $url;
	public $status;
	public function __construct( $url, $status ) {
		parent::__construct( "redirect to $url" );
		$this->url    = $url;
		$this->status = $status;
	}
}

/** Thrown by wp_die(). */
class CemetiDie extends Exception {}

/** Global state of the simulation. */
class CemetiWP {
	public static $posts = array();
	public static $meta = array();
	public static $terms = array();      // taxonomy => [ term_id => [name, slug, description] ].
	public static $rel = array();        // post id => taxonomy => [ term_id ].
	public static $options = array();
	public static $transients = array();
	public static $filters = array();    // tag => priority => [ [callable, accepted_args] ].
	public static $post_types = array(); // slug => args.
	public static $taxonomies = array(); // slug => [ object_types, args ].
	public static $styles = array();
	public static $scripts = array();
	public static $mails = array();
	public static $ctx = array();        // is_* flags.
	public static $today = '20260315';
	public static $next_id = 1000;
	public static $next_term = 1;
	public static $caps = true;
	public static $logged_in = false;
	public static $locale = 'id_ID';
	public static $uploads = '';
	public static $meta_boxes = array();

	public static function reset() {
		self::$posts = self::$meta = self::$terms = self::$rel = self::$options = self::$transients = array();
		self::$filters = self::$post_types = self::$taxonomies = self::$styles = self::$scripts = array();
		self::$mails = self::$ctx = self::$meta_boxes = array();
		self::$today    = '20260315';
		self::$next_id  = 1000;
		self::$next_term = 1;
		self::$caps     = true;
		self::$logged_in = false;
		self::$uploads  = sys_get_temp_dir() . '/cemeti-uploads-' . getmypid();
		$_GET = $_POST = $_FILES = array();
		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$GLOBALS['post'] = null;
		$GLOBALS['wp_query'] = new WP_Query();
		$GLOBALS['wp'] = (object) array( 'request' => '' );
		add_filter( 'the_content', 'wpautop' );
	}

	/** Create a post and return its ID. */
	public static function post( array $a ) {
		$id = isset( $a['ID'] ) ? $a['ID'] : self::$next_id++;
		$d  = array_merge(
			array(
				'ID' => $id, 'post_type' => 'post', 'post_title' => 'Untitled', 'post_content' => '', 'post_excerpt' => '', 'post_status' => 'publish',
				'post_date' => '2026-01-10 10:00:00', 'post_name' => '', 'menu_order' => 0, 'post_parent' => 0, 'meta' => array(), 'thumb' => 0,
			),
			$a
		);
		$d['ID'] = $id;
		if ( '' === $d['post_name'] ) {
			$d['post_name'] = sanitize_title( $d['post_title'] ) . '-' . $id;
		}
		foreach ( $d['meta'] as $k => $v ) {
			self::$meta[ $id ][ $k ] = $v;
		}
		if ( $d['thumb'] ) {
			self::$meta[ $id ]['_thumbnail_id'] = $d['thumb'];
		}
		unset( $d['meta'], $d['thumb'] );
		self::$posts[ $id ] = $d;
		return $id;
	}

	public static function obj( $id ) {
		return isset( self::$posts[ $id ] ) ? new WP_Post( self::$posts[ $id ] ) : null;
	}

	public static function request( $path, array $ctx = array() ) {
		$_SERVER['REQUEST_URI'] = $path;
		$GLOBALS['wp']->request = trim( (string) parse_url( $path, PHP_URL_PATH ), '/' );
		self::$ctx = $ctx;
	}
}

class WP_Post {
	public $ID, $post_type, $post_title, $post_content, $post_excerpt, $post_status, $post_date, $post_name, $menu_order, $post_parent;
	public function __construct( array $d ) {
		foreach ( $d as $k => $v ) {
			$this->$k = $v;
		}
	}
}

class WP_Error {
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}
}

class WP_Query {
	public $posts = array();
	public $query_vars = array();
	public $found_posts = 0;
	public $post_count = 0;
	public $flags = array();
	private $i = -1;

	public function __construct( $args = array() ) {
		$this->query_vars = $args;
		if ( $args ) {
			$this->run();
		}
	}
	public function get( $k, $d = '' ) {
		return isset( $this->query_vars[ $k ] ) ? $this->query_vars[ $k ] : $d;
	}
	public function set( $k, $v ) {
		$this->query_vars[ $k ] = $v;
	}
	public function is_main_query() {
		return ! empty( $this->flags['main'] );
	}
	public function is_search() {
		return ! empty( $this->flags['search'] );
	}
	public function is_post_type_archive( $t = '' ) {
		return ! empty( $this->flags['pta'] ) && ( '' === $t || in_array( $this->flags['pta'], (array) $t, true ) );
	}
	public function is_tax( $tax = '' ) {
		return ! empty( $this->flags['tax'] ) && ( '' === $tax || in_array( $this->flags['tax'], (array) $tax, true ) );
	}
	public function have_posts() {
		return $this->i + 1 < count( $this->posts );
	}
	public function the_post() {
		++$this->i;
		$GLOBALS['post'] = $this->posts[ $this->i ];
		if ( is_int( $GLOBALS['post'] ) ) {
			$GLOBALS['post'] = CemetiWP::obj( $GLOBALS['post'] );
		}
	}
	public function rewind_posts() {
		$this->i = -1;
	}

	/** Meta clause matcher (supports nested relation groups). */
	private function meta_ok( $id, $group ) {
		$rel = isset( $group['relation'] ) ? strtoupper( $group['relation'] ) : 'AND';
		$res = array();
		foreach ( $group as $k => $c ) {
			if ( 'relation' === $k || ! is_array( $c ) ) {
				continue;
			}
			$res[] = isset( $c['key'] ) ? $this->clause_ok( $id, $c ) : $this->meta_ok( $id, $c );
		}
		if ( ! $res ) {
			return true;
		}
		return 'OR' === $rel ? in_array( true, $res, true ) : ! in_array( false, $res, true );
	}
	private function clause_ok( $id, $c ) {
		$has = isset( CemetiWP::$meta[ $id ] ) && array_key_exists( $c['key'], CemetiWP::$meta[ $id ] );
		$cmp = isset( $c['compare'] ) ? strtoupper( $c['compare'] ) : '=';
		if ( 'EXISTS' === $cmp ) {
			return $has;
		}
		if ( 'NOT EXISTS' === $cmp ) {
			return ! $has;
		}
		if ( ! $has ) {
			return false;
		}
		$v = CemetiWP::$meta[ $id ][ $c['key'] ];
		$w = $c['value'];
		if ( isset( $c['type'] ) && 'NUMERIC' === $c['type'] ) {
			$v = (float) $v;
			$w = (float) $w;
		}
		switch ( $cmp ) {
			case '>':
				return $v > $w;
			case '<':
				return $v < $w;
			case '>=':
				return $v >= $w;
			case '<=':
				return $v <= $w;
			case '!=':
				return $v != $w; // phpcs:ignore
			default:
				return $v == $w; // phpcs:ignore
		}
	}
	private function named_clauses( $group, &$out ) {
		foreach ( (array) $group as $k => $c ) {
			if ( is_array( $c ) && isset( $c['key'] ) && is_string( $k ) ) {
				$out[ $k ] = $c['key'];
			} elseif ( is_array( $c ) && 'relation' !== $k ) {
				$this->named_clauses( $c, $out );
			}
		}
	}

	public function run() {
		$a      = $this->query_vars;
		$types  = isset( $a['post_type'] ) ? (array) $a['post_type'] : array( 'post' );
		$status = isset( $a['post_status'] ) ? (array) $a['post_status'] : array( 'publish' );
		$ids    = array();
		foreach ( CemetiWP::$posts as $id => $p ) {
			if ( ! in_array( 'any', $types, true ) && ! in_array( $p['post_type'], $types, true ) ) {
				continue;
			}
			if ( in_array( 'any', $types, true ) && isset( CemetiWP::$post_types[ $p['post_type'] ]['exclude_from_search'] ) ) {
				continue;
			}
			if ( ! in_array( 'any', $status, true ) && ! in_array( $p['post_status'], $status, true ) ) {
				continue;
			}
			if ( in_array( 'any', $status, true ) && in_array( $p['post_status'], array( 'trash', 'auto-draft' ), true ) ) {
				continue;
			}
			if ( ! empty( $a['post__in'] ) && ! in_array( $id, $a['post__in'], true ) ) {
				continue;
			}
			if ( ! empty( $a['post__not_in'] ) && in_array( $id, $a['post__not_in'], true ) ) {
				continue;
			}
			if ( isset( $a['post_parent'] ) && (int) $p['post_parent'] !== (int) $a['post_parent'] ) {
				continue;
			}
			if ( ! empty( $a['category__in'] ) ) {
				$have = isset( CemetiWP::$rel[ $id ]['category'] ) ? CemetiWP::$rel[ $id ]['category'] : array();
				if ( ! array_intersect( $a['category__in'], $have ) ) {
					continue;
				}
			}
			if ( ! empty( $a['tax_query'] ) ) {
				foreach ( $a['tax_query'] as $tq ) {
					if ( ! is_array( $tq ) || ! isset( $tq['taxonomy'] ) ) {
						continue;
					}
					$ok = false;
					foreach ( isset( CemetiWP::$rel[ $id ][ $tq['taxonomy'] ] ) ? CemetiWP::$rel[ $id ][ $tq['taxonomy'] ] : array() as $tid ) {
						$t     = CemetiWP::$terms[ $tq['taxonomy'] ][ $tid ];
						$field = isset( $tq['field'] ) && 'term_id' === $tq['field'] ? $tid : $t['slug'];
						if ( in_array( $field, (array) $tq['terms'], true ) ) {
							$ok = true;
						}
					}
					if ( ! $ok ) {
						continue 2;
					}
				}
			}
			if ( ! empty( $a['meta_query'] ) && ! $this->meta_ok( $id, $a['meta_query'] ) ) {
				continue;
			}
			if ( isset( $a['meta_key'], $a['meta_value'] ) && ( ! isset( CemetiWP::$meta[ $id ][ $a['meta_key'] ] ) || (string) CemetiWP::$meta[ $id ][ $a['meta_key'] ] !== (string) $a['meta_value'] ) ) {
				continue;
			}
			if ( isset( $a['meta_key'] ) && is_string( $a['meta_key'] ) && '' !== $a['meta_key'] ) {
				// WordPress joins on the key whenever meta_key is set: entries without it disappear.
				if ( ! isset( CemetiWP::$meta[ $id ] ) || ! array_key_exists( $a['meta_key'], CemetiWP::$meta[ $id ] ) ) {
					continue;
				}
			}
			if ( ! empty( $a['s'] ) && false === stripos( $p['post_title'] . ' ' . $p['post_content'] . ' ' . $p['post_excerpt'], $a['s'] ) ) {
				continue;
			}
			$ids[] = $id;
		}

		// Ordering.
		$named = array();
		$this->named_clauses( isset( $a['meta_query'] ) ? $a['meta_query'] : array(), $named );
		$orderby = isset( $a['orderby'] ) ? $a['orderby'] : 'date';
		if ( ! is_array( $orderby ) ) {
			$orderby = array( $orderby => isset( $a['order'] ) ? $a['order'] : 'DESC' );
		}
		$value = static function ( $id, $key ) use ( $named, $a ) {
			$p = CemetiWP::$posts[ $id ];
			switch ( $key ) {
				case 'date':
					return $p['post_date'];
				case 'title':
					return strtolower( $p['post_title'] );
				case 'ID':
					return $id;
				case 'menu_order':
					return (int) $p['menu_order'];
				case 'meta_value_num':
					return (float) ( isset( CemetiWP::$meta[ $id ][ $a['meta_key'] ] ) ? CemetiWP::$meta[ $id ][ $a['meta_key'] ] : 0 );
				case 'post__in':
					return array_search( $id, $a['post__in'], true );
			}
			if ( isset( $named[ $key ] ) ) {
				return (float) ( isset( CemetiWP::$meta[ $id ][ $named[ $key ] ] ) ? CemetiWP::$meta[ $id ][ $named[ $key ] ] : 0 );
			}
			return 0;
		};
		usort(
			$ids,
			static function ( $x, $y ) use ( $orderby, $value ) {
				foreach ( $orderby as $key => $dir ) {
					$cmp = $value( $x, $key ) <=> $value( $y, $key );
					if ( 0 !== $cmp ) {
						return 'ASC' === strtoupper( $dir ) ? $cmp : -$cmp;
					}
				}
				return $x <=> $y;
			}
		);

		$this->found_posts = count( $ids );
		$per               = isset( $a['posts_per_page'] ) ? (int) $a['posts_per_page'] : 10;
		if ( $per > 0 ) {
			$ids = array_slice( $ids, 0, $per );
		}
		$this->post_count = count( $ids );
		$this->posts      = isset( $a['fields'] ) && 'ids' === $a['fields'] ? $ids : array_map( array( 'CemetiWP', 'obj' ), $ids );
	}
}

/* ---------------------------------------------------------------- hooks */

function add_filter( $tag, $fn, $priority = 10, $args = 1 ) {
	CemetiWP::$filters[ $tag ][ $priority ][] = array( $fn, $args );
	ksort( CemetiWP::$filters[ $tag ] );
	return true;
}
function add_action( $tag, $fn, $priority = 10, $args = 1 ) {
	return add_filter( $tag, $fn, $priority, $args );
}
function has_filter( $tag, $fn = false ) {
	return ! empty( CemetiWP::$filters[ $tag ] );
}
function remove_action( $tag, $fn, $priority = 10 ) {
	if ( isset( CemetiWP::$filters[ $tag ][ $priority ] ) ) {
		foreach ( CemetiWP::$filters[ $tag ][ $priority ] as $k => $h ) {
			if ( $h[0] === $fn ) {
				unset( CemetiWP::$filters[ $tag ][ $priority ][ $k ] );
			}
		}
	}
	return true;
}
function apply_filters( $tag, $value, ...$args ) {
	foreach ( isset( CemetiWP::$filters[ $tag ] ) ? CemetiWP::$filters[ $tag ] : array() as $handlers ) {
		foreach ( $handlers as $h ) {
			$value = call_user_func_array( $h[0], array_slice( array_merge( array( $value ), $args ), 0, max( 1, $h[1] ) ) );
		}
	}
	return $value;
}
function do_action( $tag, ...$args ) {
	foreach ( isset( CemetiWP::$filters[ $tag ] ) ? CemetiWP::$filters[ $tag ] : array() as $handlers ) {
		foreach ( $handlers as $h ) {
			call_user_func_array( $h[0], array_slice( $args, 0, $h[1] ) );
		}
	}
}

/* ------------------------------------------------------------- escaping */

function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return esc_html( $s );
}
function esc_textarea( $s ) {
	return esc_html( $s );
}
function esc_html__( $s ) {
	return esc_html( $s );
}
function __( $s ) {
	return $s;
}
function esc_url( $u, $protocols = null ) {
	$u = trim( (string) $u );
	if ( '' === $u ) {
		return '';
	}
	if ( preg_match( '/^(?:https?:|mailto:|tel:|file:|\/|#|\?)/i', $u ) || ! preg_match( '/^[a-z][a-z0-9+.-]*:/i', $u ) ) {
		return esc_html( $u );
	}
	return '';
}
function esc_url_raw( $u, $protocols = null ) {
	$u = trim( (string) $u );
	if ( '' === $u ) {
		return '';
	}
	if ( preg_match( '/^([a-z][a-z0-9+.-]*):/i', $u, $m ) ) {
		$allowed = $protocols ? $protocols : array( 'http', 'https', 'mailto', 'tel' );
		return in_array( strtolower( $m[1] ), $allowed, true ) ? $u : '';
	}
	return $u;
}
function wp_kses( $s, $allowed = array() ) {
	$tags = '';
	foreach ( array_keys( $allowed ) as $t ) {
		$tags .= '<' . $t . '>';
	}
	return strip_tags( (string) $s, $tags );
}
function wp_kses_post( $s ) {
	$s = preg_replace( '#<(script|style|iframe|object)\b.*?</\1>#is', '', (string) $s );
	return preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $s );
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function sanitize_textarea_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_email( $s ) {
	$s = trim( (string) $s );
	return preg_match( '/^[^@\s<>]+@[^@\s<>]+\.[^@\s<>]+$/', $s ) ? $s : '';
}
function is_email( $s ) {
	return preg_match( '/^[^@\s<>]+@[^@\s<>]+\.[^@\s<>]+$/', (string) $s ) ? $s : false;
}
function sanitize_key( $s ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) );
}
function sanitize_title( $s ) {
	// WordPress keeps underscores in slugs (the old site's "maintenance-works_" depends on it).
	return strtolower( trim( preg_replace( '/[^a-z0-9_]+/i', '-', remove_accents( html_entity_decode( (string) $s ) ) ), '-' ) );
}
function sanitize_file_name( $s ) {
	return preg_replace( '/[^A-Za-z0-9._-]+/', '-', basename( (string) $s ) );
}
function remove_accents( $s ) {
	return function_exists( 'iconv' ) ? (string) iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s ) : $s;
}
function absint( $v ) {
	return abs( (int) $v );
}
function wp_unslash( $v ) {
	return $v;
}
function wpautop( $s ) {
	$s = trim( (string) $s );
	if ( '' === $s ) {
		return '';
	}
	return '<p>' . implode( "</p>\n<p>", preg_split( '/\n\s*\n/', $s ) ) . '</p>';
}
function wp_trim_words( $s, $n = 55, $more = '…' ) {
	$w = preg_split( '/\s+/', trim( strip_tags( (string) $s ) ) );
	return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . $more : implode( ' ', $w );
}
function wp_json_encode( $d, $flags = 0 ) {
	return json_encode( $d, $flags );
}
function __return_false() {
	return false;
}
function __return_true() {
	return true;
}
function __return_empty_string() {
	return '';
}
function is_wp_error( $v ) {
	return $v instanceof WP_Error;
}
function wp_parse_url( $u, $c = -1 ) {
	return parse_url( $u, $c );
}
function trailingslashit( $s ) {
	return rtrim( (string) $s, '/' ) . '/';
}
function add_query_arg( $k, $v = null, $url = null ) {
	if ( is_array( $k ) ) {
		$url = null === $v ? ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) : $v;
		$arr = $k;
	} else {
		$arr = array( $k => $v );
		$url = null === $url ? $_SERVER['REQUEST_URI'] : $url;
	}
	$frag = '';
	if ( false !== strpos( $url, '#' ) ) {
		list( $url, $frag ) = explode( '#', $url, 2 );
		$frag = '#' . $frag;
	}
	$base = explode( '?', $url, 2 );
	$q    = array();
	if ( isset( $base[1] ) ) {
		parse_str( $base[1], $q );
	}
	return $base[0] . ( $q || $arr ? '?' . http_build_query( array_merge( $q, $arr ) ) : '' ) . $frag;
}
function home_url( $path = '' ) {
	return 'http://cemeti.test' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) );
}
function admin_url( $path = '' ) {
	return 'http://cemeti.test/wp-admin/' . ltrim( $path, '/' );
}
function wp_safe_redirect( $url, $status = 302 ) {
	throw new CemetiRedirect( $url, $status );
}
function wp_die( $msg = '', $title = '', $args = array() ) {
	throw new CemetiDie( is_string( $msg ) ? $msg : 'die' );
}
function nocache_headers() {}
function wp_salt( $s = 'auth' ) {
	return 'test-salt-' . $s;
}
function wp_generate_password( $n = 12, $special = true ) {
	return substr( bin2hex( random_bytes( $n ) ), 0, $n );
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
	$html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="valid">';
	if ( $echo ) {
		echo $html; // phpcs:ignore
	}
	return $html;
}
function wp_nonce_url( $url, $action = -1 ) {
	return add_query_arg( '_wpnonce', 'valid', $url );
}
function wp_verify_nonce( $nonce, $action = -1 ) {
	return 'valid' === $nonce ? 1 : false;
}
function check_admin_referer( $action = -1 ) {
	if ( ! isset( $_REQUEST['_wpnonce'] ) && ! isset( $_POST['_wpnonce'] ) && ! isset( $_GET['_wpnonce'] ) ) {
		throw new CemetiDie( 'bad nonce' );
	}
	return 1;
}
function current_user_can( $cap, ...$args ) {
	return CemetiWP::$caps;
}
function is_user_logged_in() {
	return CemetiWP::$logged_in;
}
function is_admin() {
	return ! empty( CemetiWP::$ctx['admin'] );
}
function selected( $a, $b = true, $echo = true ) {
	$r = (string) $a === (string) $b ? ' selected="selected"' : '';
	if ( $echo ) {
		echo $r; // phpcs:ignore
	}
	return $r;
}
function checked( $a, $b = true, $echo = true ) {
	$r = (string) $a === (string) $b ? ' checked="checked"' : '';
	if ( $echo ) {
		echo $r; // phpcs:ignore
	}
	return $r;
}
function wp_mail( $to, $subject, $body, $headers = array() ) {
	CemetiWP::$mails[] = compact( 'to', 'subject', 'body', 'headers' );
	return true;
}
function wp_upload_dir() {
	@mkdir( CemetiWP::$uploads, 0777, true ); // phpcs:ignore
	return array( 'basedir' => CemetiWP::$uploads, 'baseurl' => 'http://cemeti.test/uploads', 'error' => false );
}
function wp_mkdir_p( $d ) {
	return is_dir( $d ) || mkdir( $d, 0777, true ); // phpcs:ignore
}
function wp_delete_file( $f ) {
	@unlink( $f ); // phpcs:ignore
}
function get_locale() {
	return CemetiWP::$locale;
}
function wp_date( $fmt, $ts = null ) {
	if ( null === $ts && 'Ymd' === $fmt ) {
		return CemetiWP::$today;
	}
	return date( $fmt, null === $ts ? strtotime( CemetiWP::$today ) : $ts ); // phpcs:ignore
}

/* ---------------------------------------------------- options, transients */

function get_option( $k, $d = false ) {
	return array_key_exists( $k, CemetiWP::$options ) ? CemetiWP::$options[ $k ] : $d;
}
function update_option( $k, $v, $autoload = null ) {
	CemetiWP::$options[ $k ] = $v;
	return true;
}
function get_transient( $k ) {
	return array_key_exists( $k, CemetiWP::$transients ) ? CemetiWP::$transients[ $k ] : false;
}
function set_transient( $k, $v, $ttl = 0 ) {
	CemetiWP::$transients[ $k ] = $v;
	return true;
}
function register_setting() {}
function settings_fields( $g ) {
	echo '<input type="hidden" name="option_page" value="' . esc_attr( $g ) . '">'; // phpcs:ignore
}
function submit_button( $text = 'Save', $type = 'primary', $name = 'submit', $wrap = true ) {
	echo '<button type="submit" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>'; // phpcs:ignore
}
function add_options_page() {}
function add_management_page() {}
function add_meta_box( $id, $title, $cb, $screen = null, $ctx = 'advanced', $prio = 'default' ) {
	CemetiWP::$meta_boxes[ $screen ][ $id ] = $cb;
}
function remove_menu_page() {}
function wp_add_inline_style() {}
function wp_enqueue_media() {}
function get_current_screen() {
	return isset( CemetiWP::$ctx['screen'] ) ? (object) array( 'post_type' => CemetiWP::$ctx['screen'] ) : null;
}
function load_child_theme_textdomain() {}
function register_nav_menus() {}
function add_theme_support() {}
function add_image_size() {}
function flush_rewrite_rules( $hard = true ) {
	CemetiWP::$options['_flushed'] = ( isset( CemetiWP::$options['_flushed'] ) ? CemetiWP::$options['_flushed'] : 0 ) + 1;
}
function wp_get_theme( $s = '' ) {
	return new class() {
		public function get( $k ) {
			preg_match( '/^' . $k . ':\s*(.+)$/mi', file_get_contents( CEMETI_THEME_DIR . '/style.css' ), $m );
			return isset( $m[1] ) ? trim( $m[1] ) : '';
		}
	};
}
function get_stylesheet() {
	return 'scriptmedia-cemeti';
}
function get_stylesheet_directory() {
	return CEMETI_THEME_DIR;
}
function get_stylesheet_directory_uri() {
	// Served by tests/browser/serve.js (repository root), so rendered pages load their CSS, fonts and JS over http.
	return 'http://127.0.0.1:8765/themes/scriptmedia-cemeti';
}

/* --------------------------------------------- types, taxonomies, terms */

function register_post_type( $slug, $args = array() ) {
	CemetiWP::$post_types[ $slug ] = $args;
}
function register_taxonomy( $slug, $types = array(), $args = array() ) {
	CemetiWP::$taxonomies[ $slug ] = array( (array) $types, $args );
}
function get_post_type_object( $t ) {
	if ( ! isset( CemetiWP::$post_types[ $t ] ) ) {
		return null;
	}
	$a = CemetiWP::$post_types[ $t ];
	return (object) array( 'name' => $t, 'labels' => (object) $a['labels'], 'public' => ! empty( $a['public'] ), 'has_archive' => $a['has_archive'] );
}
function get_post_type_archive_link( $t ) {
	$a = isset( CemetiWP::$post_types[ $t ] ) ? CemetiWP::$post_types[ $t ] : null;
	return $a && ! empty( $a['has_archive'] ) ? home_url( $a['has_archive'] ) . '/' : false;
}
function get_object_taxonomies( $t ) {
	$out = array();
	foreach ( CemetiWP::$taxonomies as $slug => $d ) {
		if ( in_array( $t, $d[0], true ) ) {
			$out[] = $slug;
		}
	}
	if ( 'post' === $t ) {
		$out = array( 'category', 'post_tag' );
	}
	return $out;
}
function term_exists( $term, $tax = '' ) {
	foreach ( isset( CemetiWP::$terms[ $tax ] ) ? CemetiWP::$terms[ $tax ] : array() as $id => $t ) {
		if ( $t['slug'] === sanitize_title( $term ) || $t['name'] === $term ) {
			return array( 'term_id' => $id );
		}
	}
	return null;
}
function wp_insert_term( $name, $tax, $args = array() ) {
	$id                           = CemetiWP::$next_term++;
	CemetiWP::$terms[ $tax ][ $id ] = array( 'name' => $name, 'slug' => sanitize_title( $name ), 'description' => isset( $args['description'] ) ? $args['description'] : '' );
	return array( 'term_id' => $id );
}
function wp_set_object_terms( $id, $terms, $tax, $append = false ) {
	$ids = array();
	foreach ( (array) $terms as $t ) {
		$e     = term_exists( $t, $tax );
		$ids[] = $e ? $e['term_id'] : wp_insert_term( $t, $tax )['term_id'];
	}
	CemetiWP::$rel[ $id ][ $tax ] = $append ? array_values( array_unique( array_merge( isset( CemetiWP::$rel[ $id ][ $tax ] ) ? CemetiWP::$rel[ $id ][ $tax ] : array(), $ids ) ) ) : $ids;
	return $ids;
}
function term_obj( $tax, $id ) {
	$t = CemetiWP::$terms[ $tax ][ $id ];
	return (object) array( 'term_id' => $id, 'name' => $t['name'], 'slug' => $t['slug'], 'description' => $t['description'], 'taxonomy' => $tax, 'count' => 0 );
}
function get_the_terms( $post, $tax ) {
	$id = is_object( $post ) ? $post->ID : $post;
	if ( empty( CemetiWP::$rel[ $id ][ $tax ] ) ) {
		return false;
	}
	return array_map(
		static function ( $tid ) use ( $tax ) {
			return term_obj( $tax, $tid );
		},
		CemetiWP::$rel[ $id ][ $tax ]
	);
}
function get_terms( $args ) {
	$tax = $args['taxonomy'];
	return array_map(
		static function ( $tid ) use ( $tax ) {
			return term_obj( $tax, $tid );
		},
		array_keys( isset( CemetiWP::$terms[ $tax ] ) ? CemetiWP::$terms[ $tax ] : array() )
	);
}
function get_categories( $args = array() ) {
	return get_terms( array( 'taxonomy' => 'category' ) );
}
function get_category_link( $id ) {
	return home_url( 'category/' . CemetiWP::$terms['category'][ $id ]['slug'] . '/' );
}
function get_cat_name( $id ) {
	return isset( CemetiWP::$terms['category'][ $id ] ) ? CemetiWP::$terms['category'][ $id ]['name'] : '';
}
function wp_get_post_categories( $id, $args = array() ) {
	$ids = isset( CemetiWP::$rel[ $id ]['category'] ) ? CemetiWP::$rel[ $id ]['category'] : array();
	return isset( $args['fields'] ) && 'all' === $args['fields'] ? array_map(
		static function ( $t ) {
			return term_obj( 'category', $t );
		},
		$ids
	) : $ids;
}
function wp_get_post_tags( $id, $args = array() ) {
	$ids = isset( CemetiWP::$rel[ $id ]['post_tag'] ) ? CemetiWP::$rel[ $id ]['post_tag'] : array();
	return array_map(
		static function ( $t ) {
			return CemetiWP::$terms['post_tag'][ $t ]['name'];
		},
		$ids
	);
}

/* ---------------------------------------------------------------- posts */

function get_post( $id = null ) {
	if ( null === $id ) {
		return $GLOBALS['post'];
	}
	return is_object( $id ) ? $id : CemetiWP::obj( $id );
}
function get_post_field( $f, $id ) {
	return isset( CemetiWP::$posts[ $id ][ $f ] ) ? CemetiWP::$posts[ $id ][ $f ] : '';
}
function get_post_type( $id = null ) {
	$id = $id ? ( is_object( $id ) ? $id->ID : $id ) : get_the_ID();
	return isset( CemetiWP::$posts[ $id ] ) ? CemetiWP::$posts[ $id ]['post_type'] : false;
}
function get_post_status( $id ) {
	return isset( CemetiWP::$posts[ $id ] ) ? CemetiWP::$posts[ $id ]['post_status'] : false;
}
function set_post_type( $id, $type ) {
	CemetiWP::$posts[ $id ]['post_type'] = $type;
	return 1;
}
function get_the_ID() {
	return isset( $GLOBALS['post']->ID ) ? $GLOBALS['post']->ID : false;
}
function get_the_title( $p = 0 ) {
	$id = is_object( $p ) ? $p->ID : ( $p ? $p : get_the_ID() );
	return isset( CemetiWP::$posts[ $id ] ) ? CemetiWP::$posts[ $id ]['post_title'] : '';
}
function the_title( $before = '', $after = '' ) {
	echo $before . esc_html( get_the_title() ) . $after; // phpcs:ignore
}
function get_permalink( $p = 0 ) {
	$id = is_object( $p ) ? $p->ID : ( $p ? $p : get_the_ID() );
	if ( ! isset( CemetiWP::$posts[ $id ] ) ) {
		return false;
	}
	$a    = CemetiWP::$posts[ $id ];
	$base = isset( CemetiWP::$post_types[ $a['post_type'] ]['rewrite']['slug'] ) ? CemetiWP::$post_types[ $a['post_type'] ]['rewrite']['slug'] : '';
	return home_url( ( '' !== $base ? $base . '/' : '' ) . $a['post_name'] ) . '/';
}
function the_permalink() {
	echo esc_url( get_permalink() ); // phpcs:ignore
}
function get_the_content() {
	return isset( $GLOBALS['post']->post_content ) ? $GLOBALS['post']->post_content : '';
}
function the_content() {
	echo apply_filters( 'the_content', get_the_content() ); // phpcs:ignore
}
function has_excerpt( $id = 0 ) {
	return '' !== get_post_field( 'post_excerpt', $id ? $id : get_the_ID() );
}
function get_the_excerpt( $p = 0 ) {
	$id = is_object( $p ) ? $p->ID : ( $p ? $p : get_the_ID() );
	$e  = get_post_field( 'post_excerpt', $id );
	return '' !== $e ? $e : wp_trim_words( get_post_field( 'post_content', $id ), 55 );
}
function get_the_date( $fmt = 'Y-m-d', $id = 0 ) {
	return date( $fmt, strtotime( get_post_field( 'post_date', $id ? $id : get_the_ID() ) ) ); // phpcs:ignore
}
function get_the_modified_date( $fmt = 'Y-m-d', $id = 0 ) {
	return get_the_date( $fmt, $id );
}
function get_post_time( $fmt = 'U', $gmt = false, $id = 0 ) {
	return date( $fmt, strtotime( get_post_field( 'post_date', $id ? $id : get_the_ID() ) ) ); // phpcs:ignore
}
function post_class( $c = '' ) {
	echo 'class="post-' . (int) get_the_ID() . ' type-' . esc_attr( get_post_type() ) . '"'; // phpcs:ignore
}
function body_class( $c = '' ) {
	echo 'class="' . esc_attr( implode( ' ', array_keys( array_filter( CemetiWP::$ctx ) ) ) ) . '"'; // phpcs:ignore
}
function get_post_meta( $id, $k = '', $single = false ) {
	if ( '' === $k ) {
		return isset( CemetiWP::$meta[ $id ] ) ? CemetiWP::$meta[ $id ] : array();
	}
	return isset( CemetiWP::$meta[ $id ] ) && array_key_exists( $k, CemetiWP::$meta[ $id ] ) ? CemetiWP::$meta[ $id ][ $k ] : ( $single ? '' : array() );
}
function update_post_meta( $id, $k, $v ) {
	CemetiWP::$meta[ $id ][ $k ] = $v;
	return true;
}
function delete_post_meta( $id, $k ) {
	unset( CemetiWP::$meta[ $id ][ $k ] );
	return true;
}
function wp_insert_post( $a, $err = false ) {
	return CemetiWP::post( array_merge( array( 'post_status' => 'draft' ), $a ) );
}
function wp_is_post_revision( $id ) {
	return false;
}
function get_posts( $args = array() ) {
	if ( ! isset( $args['posts_per_page'] ) ) {
		$args['posts_per_page'] = isset( $args['numberposts'] ) ? $args['numberposts'] : 5;
	}
	$q = new WP_Query( $args );
	return $q->posts;
}
function get_pages( $args = array() ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'page',
			'post_parent'    => isset( $args['parent'] ) ? $args['parent'] : 0,
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	return $q->posts;
}
function wp_reset_postdata() {
	$GLOBALS['post'] = isset( $GLOBALS['wp_query']->posts[0] ) && is_object( $GLOBALS['wp_query']->posts[0] ) ? $GLOBALS['wp_query']->posts[0] : $GLOBALS['post'];
}
function have_posts() {
	return $GLOBALS['wp_query']->have_posts();
}
function the_post() {
	$GLOBALS['wp_query']->the_post();
}
function has_post_thumbnail( $id = 0 ) {
	return (bool) get_post_meta( $id ? $id : get_the_ID(), '_thumbnail_id', true );
}
function get_the_post_thumbnail_url( $id = 0, $size = 'full' ) {
	$t = get_post_meta( $id ? $id : get_the_ID(), '_thumbnail_id', true );
	return $t ? 'http://cemeti.test/img/' . $t . '-' . ( is_array( $size ) ? 'x' : $size ) . '.jpg' : false;
}
function get_the_post_thumbnail( $post = null, $size = 'post-thumbnail', $attr = array() ) {
	$id = is_object( $post ) ? $post->ID : ( $post ? $post : get_the_ID() );
	return has_post_thumbnail( $id ) ? wp_get_attachment_image( get_post_meta( $id, '_thumbnail_id', true ), $size, false, $attr ) : '';
}
function the_post_thumbnail( $size = 'post-thumbnail', $attr = array() ) {
	echo get_the_post_thumbnail( null, $size, $attr ); // phpcs:ignore
}
function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) {
	$attr = array_merge( array( 'src' => 'http://cemeti.test/img/' . $id . '-' . ( is_array( $size ) ? 'x' : $size ) . '.jpg', 'alt' => 'Image ' . $id ), $attr );
	$html = '<img';
	foreach ( $attr as $k => $v ) {
		$html .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	return $html . '>';
}
function the_posts_pagination( $args = array() ) {
	echo '<nav class="navigation pagination" aria-label="Pagination"><div class="nav-links"></div></nav>'; // phpcs:ignore
}
function post_type_archive_title( $prefix = '', $display = true ) {
	$t = isset( CemetiWP::$ctx['pta'] ) ? CemetiWP::$ctx['pta'] : '';
	$l = $t && isset( CemetiWP::$post_types[ $t ] ) ? CemetiWP::$post_types[ $t ]['labels']['name'] : '';
	if ( $display ) {
		echo esc_html( $l ); // phpcs:ignore
	}
	return $l;
}
function single_term_title( $prefix = '', $display = true ) {
	$l = isset( CemetiWP::$ctx['term'] ) ? CemetiWP::$ctx['term'] : '';
	if ( $display ) {
		echo esc_html( $l ); // phpcs:ignore
	}
	return $l;
}
function get_query_var( $k, $d = '' ) {
	return $GLOBALS['wp_query']->get( $k, $d );
}
function get_search_query( $escaped = true ) {
	$q = isset( $_GET['s'] ) ? (string) $_GET['s'] : '';
	return $escaped ? esc_html( $q ) : $q;
}

/* ---------------------------------------------------------- conditionals */

function is_front_page() {
	return ! empty( CemetiWP::$ctx['front'] );
}
function is_singular() {
	return ! empty( CemetiWP::$ctx['singular'] );
}
function is_page() {
	return ! empty( CemetiWP::$ctx['singular'] ) && 'page' === get_post_type();
}
function is_search() {
	return ! empty( CemetiWP::$ctx['search'] );
}
function is_404() {
	return ! empty( CemetiWP::$ctx['404'] );
}
function is_author() {
	return ! empty( CemetiWP::$ctx['author'] );
}

/* --------------------------------------------------------- template tags */

function bloginfo( $s ) {
	echo 'charset' === $s ? 'UTF-8' : 'Cemeti'; // phpcs:ignore
}
function language_attributes() {
	echo 'lang="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . '"'; // phpcs:ignore
}
function wp_body_open() {}
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) {
	CemetiWP::$styles[ $h ] = $src;
}
function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $args = array() ) {
	CemetiWP::$scripts[ $h ] = $src;
}
function wp_get_document_title() {
	$parts = apply_filters( 'document_title_parts', array( 'title' => is_singular() ? get_the_title() : 'Cemeti', 'site' => 'Site' ) );
	$sep   = apply_filters( 'document_title_separator', '-' );
	return implode( ' ' . $sep . ' ', array_filter( $parts ) );
}
function wp_head() {
	foreach ( CemetiWP::$styles as $src ) {
		echo '<link rel="stylesheet" href="' . esc_url( $src ) . '">' . "\n"; // phpcs:ignore
	}
	do_action( 'wp_head' );
}
function wp_footer() {
	foreach ( CemetiWP::$scripts as $src ) {
		echo '<script src="' . esc_url( $src ) . '" defer></script>' . "\n"; // phpcs:ignore
	}
	do_action( 'wp_footer' );
}
function get_template_part( $slug, $name = null ) {
	include CEMETI_THEME_DIR . '/' . $slug . '.php';
}
function get_header() {
	include CEMETI_THEME_DIR . '/header.php';
}
function get_footer() {
	include CEMETI_THEME_DIR . '/footer.php';
}
