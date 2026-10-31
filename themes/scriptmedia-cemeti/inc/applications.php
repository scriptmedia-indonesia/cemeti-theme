<?php
/**
 * Open Call applications: the public form, its handler and the admin side.
 *
 * Submissions become private "Application" entries. Uploaded PDFs go to a folder under
 * uploads that the web server is told not to serve; only administrators can download
 * them, through a checked handler.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Documents an application can include: key => label key in cemeti_ui_strings().
 *
 * @return array<string, string>
 */
function cemeti_application_documents() {
	return apply_filters(
		'cemeti_application_documents',
		array(
			'cv'         => 'form_cv',
			'portfolio'  => 'form_portfolio',
			'proposal'   => 'form_proposal',
			'motivation' => 'form_motivation',
		)
	);
}

/**
 * Documents required by one Open Call (field "documents"; empty = all, "none" = no uploads).
 *
 * @param int $open_call_id Open Call post ID.
 * @return string[] Document keys.
 */
function cemeti_open_call_documents( $open_call_id ) {
	$all   = array_keys( cemeti_application_documents() );
	$value = strtolower( trim( (string) cemeti_get_field( 'documents', $open_call_id ) ) );
	if ( '' === $value ) {
		return $all;
	}
	if ( 'none' === $value ) {
		return array();
	}
	return array_values( array_intersect( $all, array_map( 'trim', explode( ',', $value ) ) ) );
}

/**
 * Largest accepted file, in bytes.
 *
 * @return int
 */
function cemeti_application_max_file() {
	return (int) apply_filters( 'cemeti_application_max_file', 5 * 1024 * 1024 );
}

/**
 * Can the Open Call still take applications? Closed after its deadline.
 *
 * @param int $open_call_id Open Call post ID.
 * @return bool
 */
function cemeti_open_call_is_open( $open_call_id ) {
	return 'publish' === get_post_status( $open_call_id ) && 'previous' !== cemeti_get_status( $open_call_id );
}

/**
 * Check one uploaded file. Returns '' when fine, else an error code.
 *
 * @param array<string, mixed> $file    Entry of $_FILES.
 * @param int                  $max     Max size in bytes.
 * @param bool                 $require Whether a file must be present.
 * @return string "", "missing" or "invalid".
 */
function cemeti_validate_upload( array $file, $max, $require ) {
	$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	if ( UPLOAD_ERR_NO_FILE === $error ) {
		return $require ? 'missing' : '';
	}
	if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || (int) $file['size'] <= 0 || (int) $file['size'] > $max ) {
		return 'invalid';
	}
	if ( 'pdf' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
		return 'invalid';
	}
	$head = (string) file_get_contents( $file['tmp_name'], false, null, 0, 5 );
	return '%PDF-' === $head ? '' : 'invalid';
}

/**
 * Signed time token for the form: proves the form was loaded a few seconds ago.
 *
 * @param int $time Unix time.
 * @return string
 */
function cemeti_form_token( $time ) {
	return $time . '.' . hash_hmac( 'sha256', 'cemeti-apply|' . $time, wp_salt( 'auth' ) );
}

/**
 * Is a form token genuine, at least $min seconds and at most $max seconds old?
 *
 * @param string $token Token from the form.
 * @param int    $now   Current Unix time.
 * @param int    $min   Minimum age in seconds.
 * @param int    $max   Maximum age in seconds.
 * @return bool
 */
function cemeti_form_token_valid( $token, $now, $min = 3, $max = 7200 ) {
	$parts = explode( '.', (string) $token, 2 );
	if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
		return false;
	}
	$age = $now - (int) $parts[0];
	return $age >= $min && $age <= $max && hash_equals( cemeti_form_token( (int) $parts[0] ), (string) $token );
}

/**
 * Folder for uploaded applications, created with a deny rule on first use.
 *
 * @return string Absolute path without trailing slash, or '' on failure.
 */
function cemeti_application_dir() {
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return '';
	}
	$dir = trailingslashit( $uploads['basedir'] ) . 'cemeti-applications';
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

/**
 * Move one uploaded file into place. Filterable so tests can stand in for a real HTTP upload.
 *
 * @param string $from Temporary path from $_FILES.
 * @param string $to   Destination path.
 * @return bool
 */
function cemeti_move_upload( $from, $to ) {
	$pre = apply_filters( 'cemeti_pre_move_upload', null, $from, $to );
	return null !== $pre ? (bool) $pre : move_uploaded_file( $from, $to );
}

/**
 * Redirect back to the Open Call with a result code.
 *
 * @param int    $open_call_id Open Call post ID.
 * @param string $code         "sent" or an error code.
 */
function cemeti_application_redirect( $open_call_id, $code ) {
	$url = $open_call_id ? get_permalink( $open_call_id ) : cemeti_home_url();
	wp_safe_redirect( add_query_arg( 'apply', $code, $url ) . '#apply' );
	exit;
}

/**
 * Handle a submitted application.
 */
function cemeti_handle_application() {
	$open_call_id = isset( $_POST['open_call'] ) ? absint( $_POST['open_call'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $open_call_id || 'open_call' !== get_post_type( $open_call_id ) ) {
		cemeti_application_redirect( 0, 'spam' );
	}

	// Anti-spam: nonce, hidden field, time token, rate limit.
	$nonce = isset( $_POST['_cemeti_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_cemeti_nonce'] ) ) : '';
	$token = isset( $_POST['cemeti_ts'] ) ? sanitize_text_field( wp_unslash( $_POST['cemeti_ts'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'cemeti_apply' ) || ! empty( $_POST['cemeti_website'] ) || ! cemeti_form_token_valid( $token, time() ) ) {
		cemeti_application_redirect( $open_call_id, 'spam' );
	}
	$ip_key = 'cemeti_apply_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$tries  = (int) get_transient( $ip_key );
	if ( $tries >= 5 ) {
		cemeti_application_redirect( $open_call_id, 'rate' );
	}
	set_transient( $ip_key, $tries + 1, HOUR_IN_SECONDS );

	if ( ! cemeti_open_call_is_open( $open_call_id ) ) {
		cemeti_application_redirect( $open_call_id, 'closed' );
	}

	// Text fields.
	$name    = isset( $_POST['app_name'] ) ? trim( preg_replace( '/[\r\n]+/', ' ', sanitize_text_field( wp_unslash( $_POST['app_name'] ) ) ) ) : '';
	$typed   = isset( $_POST['app_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['app_email'] ) ) ) : '';
	$email   = sanitize_email( $typed );
	$city    = isset( $_POST['app_city'] ) ? sanitize_text_field( wp_unslash( $_POST['app_city'] ) ) : '';
	$link    = isset( $_POST['app_link'] ) ? esc_url_raw( trim( wp_unslash( $_POST['app_link'] ) ), array( 'http', 'https' ) ) : '';
	$message = isset( $_POST['app_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['app_message'] ) ) : '';
	if ( '' === $name || '' === $typed ) {
		cemeti_application_redirect( $open_call_id, 'required' );
	}
	if ( '' === $email || ! is_email( $email ) ) {
		cemeti_application_redirect( $open_call_id, 'email' );
	}

	// Files: validate all before storing any.
	$required = cemeti_open_call_documents( $open_call_id );
	$max      = cemeti_application_max_file();
	$incoming = array();
	foreach ( array_keys( cemeti_application_documents() ) as $key ) {
		if ( ! in_array( $key, $required, true ) ) {
			continue;
		}
		$file = isset( $_FILES[ 'doc_' . $key ] ) && is_array( $_FILES[ 'doc_' . $key ] ) ? $_FILES[ 'doc_' . $key ] : array(); // phpcs:ignore WordPress.Security
		$code = cemeti_validate_upload( $file, $max, true );
		if ( 'missing' === $code ) {
			cemeti_application_redirect( $open_call_id, 'required' );
		}
		if ( '' !== $code ) {
			cemeti_application_redirect( $open_call_id, 'file' );
		}
		$incoming[ $key ] = $file;
	}

	$dir = $incoming ? cemeti_application_dir() : '';
	if ( $incoming && '' === $dir ) {
		cemeti_application_redirect( $open_call_id, 'server' );
	}

	$stored = array();
	foreach ( $incoming as $key => $file ) {
		$safe = sanitize_file_name( $file['name'] );
		$rel  = gmdate( 'Ym' ) . '-' . wp_generate_password( 16, false ) . '-' . $safe;
		if ( ! cemeti_move_upload( $file['tmp_name'], $dir . '/' . $rel ) ) {
			foreach ( $stored as $done ) {
				wp_delete_file( $dir . '/' . $done['file'] );
			}
			cemeti_application_redirect( $open_call_id, 'server' );
		}
		$stored[ $key ] = array( 'file' => $rel, 'name' => $safe );
	}

	$title  = get_the_title( $open_call_id );
	$app_id = wp_insert_post(
		array(
			'post_type'   => 'application',
			'post_status' => 'publish',
			'post_title'  => $name . ' — ' . $title,
		),
		true
	);
	if ( is_wp_error( $app_id ) ) {
		foreach ( $stored as $done ) {
			wp_delete_file( $dir . '/' . $done['file'] );
		}
		cemeti_application_redirect( $open_call_id, 'server' );
	}
	update_post_meta( $app_id, 'app_open_call', $open_call_id );
	update_post_meta( $app_id, 'app_name', $name );
	update_post_meta( $app_id, 'app_email', $email );
	update_post_meta( $app_id, 'app_city', $city );
	update_post_meta( $app_id, 'app_link', $link );
	update_post_meta( $app_id, 'app_message', $message );
	update_post_meta( $app_id, 'app_files', $stored );
	update_post_meta( $app_id, 'app_lang', cemeti_current_lang() );

	cemeti_send_application_mails( $app_id, $open_call_id, $name, $email, $title );
	cemeti_application_redirect( $open_call_id, 'sent' );
}
add_action( 'admin_post_nopriv_cemeti_apply', 'cemeti_handle_application' );
add_action( 'admin_post_cemeti_apply', 'cemeti_handle_application' );

/**
 * Notify the team and confirm to the applicant, in the site's language.
 *
 * @param int    $app_id       Application ID.
 * @param int    $open_call_id Open Call ID.
 * @param string $name         Applicant name.
 * @param string $email        Applicant email.
 * @param string $title        Open Call title.
 */
function cemeti_send_application_mails( $app_id, $open_call_id, $name, $email, $title ) {
	$team = cemeti_option( 'notify_email' );
	$team = is_email( $team ) ? $team : get_option( 'admin_email' );
	$site = cemeti_option( 'institution_name' );

	wp_mail(
		$team,
		'[' . $site . '] New application: ' . $title,
		"New application for \"$title\"\n\nName: $name\nEmail: $email\nOpen it: " . admin_url( 'post.php?post=' . (int) $app_id . '&action=edit' ),
		array( 'Reply-To: ' . $name . ' <' . $email . '>' )
	);
	wp_mail(
		$email,
		sprintf( cemeti_t( 'mail_subject' ), $title ),
		sprintf( cemeti_t( 'mail_body' ), $name, $title, $site )
	);
}

/**
 * Meta box that shows an application and lets administrators download the files.
 */
function cemeti_application_meta_box() {
	add_meta_box(
		'cemeti_application',
		'Application',
		static function ( $post ) {
			$open_call = (int) get_post_meta( $post->ID, 'app_open_call', true );
			$rows      = array(
				'Open Call' => $open_call ? get_the_title( $open_call ) : '',
				'Name'      => get_post_meta( $post->ID, 'app_name', true ),
				'Email'     => get_post_meta( $post->ID, 'app_email', true ),
				'City'      => get_post_meta( $post->ID, 'app_city', true ),
				'Link'      => get_post_meta( $post->ID, 'app_link', true ),
				'Message'   => get_post_meta( $post->ID, 'app_message', true ),
			);
			echo '<table class="form-table" role="presentation">';
			foreach ( $rows as $label => $value ) {
				echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '<tr><th>Files</th><td>';
			$files = get_post_meta( $post->ID, 'app_files', true );
			foreach ( is_array( $files ) ? $files : array() as $key => $info ) {
				$url = wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_download&app=' . $post->ID . '&doc=' . rawurlencode( $key ) ), 'cemeti_download_' . $post->ID );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( ucfirst( $key ) . ': ' . $info['name'] ) . '</a><br>';
			}
			echo '</td></tr></table>';
		},
		'application',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'cemeti_application_meta_box' );

/**
 * Admin list columns for applications.
 *
 * @param array $columns Columns.
 * @return array
 */
function cemeti_application_columns( $columns ) {
	return array(
		'cb'        => $columns['cb'],
		'title'     => 'Applicant',
		'open_call' => 'Open Call',
		'email'     => 'Email',
		'date'      => $columns['date'],
	);
}
add_filter( 'manage_application_posts_columns', 'cemeti_application_columns' );

/**
 * Fill the application columns.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post ID.
 */
function cemeti_application_column_value( $column, $post_id ) {
	if ( 'open_call' === $column ) {
		echo esc_html( get_the_title( (int) get_post_meta( $post_id, 'app_open_call', true ) ) );
	} elseif ( 'email' === $column ) {
		echo esc_html( (string) get_post_meta( $post_id, 'app_email', true ) );
	}
}
add_action( 'manage_application_posts_custom_column', 'cemeti_application_column_value', 10, 2 );

/**
 * Applications cannot be created by hand.
 */
function cemeti_application_no_new() {
	global $submenu;
	unset( $submenu['edit.php?post_type=application'][10] );
}
add_action( 'admin_menu', 'cemeti_application_no_new', 99 );

/**
 * Absolute path of a stored application file, or false when it is missing or outside the
 * applications folder (so a tampered meta value cannot read other files).
 *
 * @param int    $app Application ID.
 * @param string $doc Document key.
 * @return string|false
 */
function cemeti_application_file_path( $app, $doc ) {
	$files = get_post_meta( $app, 'app_files', true );
	if ( ! is_array( $files ) || ! isset( $files[ $doc ]['file'] ) ) {
		return false;
	}
	$dir  = cemeti_application_dir();
	$root = '' !== $dir ? realpath( $dir ) : false;
	$path = $root ? realpath( $dir . '/' . $files[ $doc ]['file'] ) : false;
	if ( ! $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) ) {
		return false;
	}
	return $path;
}

/**
 * Download an uploaded PDF (administrators only).
 */
function cemeti_download_application() {
	$app = isset( $_GET['app'] ) ? absint( $_GET['app'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$doc = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	check_admin_referer( 'cemeti_download_' . $app );
	if ( ! current_user_can( 'manage_options' ) || 'application' !== get_post_type( $app ) ) {
		wp_die( esc_html__( 'Not allowed.', 'scriptmedia-cemeti' ), '', array( 'response' => 403 ) );
	}
	$path = cemeti_application_file_path( $app, $doc );
	if ( false === $path ) {
		wp_die( esc_html__( 'File not found.', 'scriptmedia-cemeti' ), '', array( 'response' => 404 ) );
	}
	$files = get_post_meta( $app, 'app_files', true );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $files[ $doc ]['name'] ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_post_cemeti_download', 'cemeti_download_application' );

/**
 * Deleting an application deletes its PDFs too (they hold personal data).
 *
 * @param int $post_id Post ID.
 */
function cemeti_delete_application_files( $post_id ) {
	if ( 'application' !== get_post_type( $post_id ) ) {
		return;
	}
	$files = get_post_meta( $post_id, 'app_files', true );
	foreach ( array_keys( is_array( $files ) ? $files : array() ) as $doc ) {
		$path = cemeti_application_file_path( $post_id, $doc );
		if ( false !== $path ) {
			wp_delete_file( $path );
		}
	}
}
add_action( 'before_delete_post', 'cemeti_delete_application_files' );
