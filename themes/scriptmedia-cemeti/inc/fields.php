<?php
/**
 * Field framework: a "Details" meta box per post type, driven by config/fields.php.
 * Works without SCF/ACF. Values are stored in post meta "cemeti_{key}".
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field definitions for a post type.
 *
 * @param string $post_type Post type.
 * @return array<int, array<string, mixed>>
 */
function cemeti_field_defs( $post_type ) {
	$all = cemeti_config( 'fields' );
	return isset( $all[ $post_type ] ) ? $all[ $post_type ] : array();
}

/**
 * One field definition by key.
 *
 * @param string $post_type Post type.
 * @param string $key       Field key.
 * @return array<string, mixed>|null
 */
function cemeti_field_def( $post_type, $key ) {
	foreach ( cemeti_field_defs( $post_type ) as $def ) {
		if ( $def['key'] === $key ) {
			return $def;
		}
	}
	return null;
}

/**
 * Read a field value.
 *
 * Dates come back as Ymd, image/file as an attachment id (int), images as int[], relations as int[],
 * repeaters as an array of rows, everything else as a string.
 *
 * @param string   $key     Field key.
 * @param int|null $post_id Post ID. Defaults to the current post.
 * @return mixed
 */
function cemeti_get_field( $key, $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$def     = cemeti_field_def( get_post_type( $post_id ), $key );
	$value   = get_post_meta( $post_id, 'cemeti_' . $key, true );
	$type    = $def ? $def['type'] : 'text';

	// Pictures and the booklet are the same in every language: an entry that has none of its own (an Indonesian
	// copy made before the pictures were added, or imported without them) shows the other language's, so its
	// contents bar and sections are not missing.
	if ( cemeti_field_is_empty( $value ) && in_array( $key, array( 'gallery', 'documentation', 'booklet', 'booklet_url' ), true ) && ! is_admin() && function_exists( 'pll_get_post_translations' ) ) {
		foreach ( (array) pll_get_post_translations( $post_id ) as $other ) {
			$theirs = (int) $other !== $post_id && 'publish' === get_post_status( (int) $other ) ? get_post_meta( (int) $other, 'cemeti_' . $key, true ) : '';
			if ( ! cemeti_field_is_empty( $theirs ) ) {
				$value = $theirs;
				break;
			}
		}
	}

	if ( in_array( $type, array( 'images', 'relation', 'repeater' ), true ) ) {
		return is_array( $value ) ? $value : array();
	}
	if ( in_array( $type, array( 'image', 'file' ), true ) ) {
		return (int) $value;
	}
	return is_scalar( $value ) ? (string) $value : '';
}

/**
 * Sanitize one submitted value according to its field definition.
 *
 * @param array<string, mixed> $def   Field definition.
 * @param mixed                $value Raw value.
 * @return mixed
 */
function cemeti_sanitize_field( array $def, $value ) {
	switch ( $def['type'] ) {
		case 'textarea':
			return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
		case 'checkbox':
			return is_scalar( $value ) && '1' === (string) $value ? '1' : '0';
		case 'list':
			// One entry per row in the admin; stored as lines so every reader keeps working with plain text.
			$lines = is_array( $value ) ? $value : preg_split( '/\R/', is_scalar( $value ) ? (string) $value : '' );
			$lines = array_map( 'sanitize_text_field', array_filter( $lines, 'is_scalar' ) );
			return implode( "\n", array_slice( array_values( array_unique( array_filter( $lines, 'strlen' ) ) ), 0, 40 ) );
		case 'file':
			// A PDF from the media library, stored as its attachment id.
			$id = is_scalar( $value ) ? absint( $value ) : 0;
			return $id && 'application/pdf' === get_post_mime_type( $id ) ? $id : 0;
		case 'date':
			$value = is_scalar( $value ) ? str_replace( '-', '', trim( (string) $value ) ) : '';
			return preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
		case 'url':
			return is_scalar( $value ) ? esc_url_raw( trim( (string) $value ), array( 'https', 'http' ) ) : '';
		case 'image':
			return is_scalar( $value ) ? absint( $value ) : 0;
		case 'images':
			$ids = is_array( $value ) ? $value : explode( ',', is_scalar( $value ) ? (string) $value : '' );
			return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		case 'relation':
			$max = isset( $def['max'] ) ? (int) $def['max'] : 4;
			$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $value ) ? $value : array() ) ) ) );
			return array_slice( $ids, 0, $max );
		case 'repeater':
			$rows = array();
			foreach ( is_array( $value ) ? $value : array() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$clean = array();
				foreach ( $def['sub'] as $sub ) {
					$clean[ $sub['key'] ] = cemeti_sanitize_field( $sub, isset( $row[ $sub['key'] ] ) ? $row[ $sub['key'] ] : '' );
				}
				if ( array_filter( $clean ) ) {
					$rows[] = $clean;
				}
			}
			return $rows;
		default:
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
}

/**
 * Is a sanitized value empty (so the meta row can be deleted)?
 *
 * @param mixed $value Sanitized value.
 * @return bool
 */
function cemeti_field_is_empty( $value ) {
	return '' === $value || 0 === $value || array() === $value;
}

/**
 * Register the meta box for every Cemeti post type that has fields.
 */
function cemeti_add_meta_boxes() {
	foreach ( array_keys( cemeti_config( 'fields' ) ) as $post_type ) {
		add_meta_box( 'cemeti_details', __( 'Details', 'scriptmedia-cemeti' ), 'cemeti_render_meta_box', $post_type, 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'cemeti_add_meta_boxes' );

/**
 * Move up / move down / remove buttons shared by list rows, photos and repeater blocks.
 *
 * @param bool $move Include the move buttons.
 * @return string HTML (labels are escaped here).
 */
function cemeti_row_buttons( $move = true ) {
	$html = '';
	if ( $move ) {
		$html .= '<button type="button" class="button-link cemeti-row__up" aria-label="' . esc_attr__( 'Move up', 'scriptmedia-cemeti' ) . '">&#8593;</button> ';
		$html .= '<button type="button" class="button-link cemeti-row__down" aria-label="' . esc_attr__( 'Move down', 'scriptmedia-cemeti' ) . '">&#8595;</button> ';
	}
	return $html . '<button type="button" class="button-link cemeti-row__remove" aria-label="' . esc_attr__( 'Remove', 'scriptmedia-cemeti' ) . '">&#215;</button>';
}

/**
 * Render one input.
 *
 * @param array<string, mixed> $def   Field definition.
 * @param mixed                $value Stored value.
 * @param string               $name  Input name.
 * @param string               $id    Input id.
 */
function cemeti_render_input( array $def, $value, $name, $id ) {
	$id_attr = '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '';
	switch ( $def['type'] ) {
		case 'textarea':
			printf( '<textarea class="large-text" rows="%d"%s name="%s">%s</textarea>', isset( $def['rows'] ) ? (int) $def['rows'] : 3, $id_attr, esc_attr( $name ), esc_textarea( (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'date':
			$display = preg_match( '/^(\d{4})(\d{2})(\d{2})$/', (string) $value, $m ) ? $m[1] . '-' . $m[2] . '-' . $m[3] : '';
			printf( '<input type="date"%s name="%s" value="%s">', $id_attr, esc_attr( $name ), esc_attr( $display ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'url':
			printf( '<input type="url" class="regular-text"%s name="%s" value="%s">', $id_attr, esc_attr( $name ), esc_attr( (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'checkbox':
			printf( '<input type="hidden" name="%1$s" value="0"><label><input type="checkbox"%2$s name="%1$s" value="1"%3$s> %4$s</label>', esc_attr( $name ), $id_attr, checked( '1', (string) $value, false ), esc_html( isset( $def['check_label'] ) ? $def['check_label'] : __( 'Yes', 'scriptmedia-cemeti' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;
		case 'list':
			$lines = '' === (string) $value ? array() : preg_split( '/\R/', (string) $value );
			$row   = static function ( $line ) use ( $name, $def ) {
				printf(
					'<div class="cemeti-list__row"><input type="text" class="regular-text" aria-label="%s" name="%s[]" value="%s"> %s</div>',
					esc_attr( $def['label'] ),
					esc_attr( $name ),
					esc_attr( (string) $line ),
					cemeti_row_buttons() // phpcs:ignore WordPress.Security.EscapeOutput
				);
			};
			echo '<div class="cemeti-list"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '><div class="cemeti-list__rows">';
			foreach ( $lines as $line ) {
				$row( $line );
			}
			echo '</div><template class="cemeti-list__template">';
			$row( '' );
			echo '</template><button type="button" class="button cemeti-list__add">' . esc_html__( 'Add', 'scriptmedia-cemeti' ) . '</button></div>';
			break;
		case 'file':
			$file_id = (int) $value;
			$label   = $file_id ? wp_basename( (string) get_attached_file( $file_id ) ) : '';
			echo '<div class="cemeti-file" role="group" aria-label="' . esc_attr( $def['label'] ) . '">';
			printf( '<input type="hidden"%s name="%s" value="%s">', $id_attr, esc_attr( $name ), esc_attr( (string) ( $file_id ? $file_id : '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<span class="cemeti-file__name">' . esc_html( $label ) . '</span> ';
			echo '<button type="button" class="button cemeti-file__pick">' . esc_html__( 'Choose PDF', 'scriptmedia-cemeti' ) . '</button> ';
			echo '<button type="button" class="button-link cemeti-file__clear">' . esc_html__( 'Remove', 'scriptmedia-cemeti' ) . '</button>';
			echo '</div>';
			break;
		case 'image':
		case 'images':
			$ids   = 'image' === $def['type'] ? array_filter( array( (int) $value ) ) : (array) $value;
			$multi = 'images' === $def['type'] ? '1' : '0';
			echo '<div class="cemeti-media" role="group" aria-label="' . esc_attr( $def['label'] ) . '" data-multiple="' . esc_attr( $multi ) . '">';
			printf( '<input type="hidden"%s name="%s" value="%s">', $id_attr, esc_attr( $name ), esc_attr( implode( ',', $ids ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<div class="cemeti-media__thumbs">';
			foreach ( $ids as $attachment_id ) {
				echo '<span class="cemeti-media__item" data-id="' . (int) $attachment_id . '">';
				echo wp_get_attachment_image( (int) $attachment_id, array( 80, 80 ) ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo cemeti_row_buttons( 'images' === $def['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</span>';
			}
			echo '</div>';
			echo '<button type="button" class="button cemeti-media__pick">' . esc_html( 'images' === $def['type'] ? __( 'Add photos', 'scriptmedia-cemeti' ) : __( 'Choose', 'scriptmedia-cemeti' ) ) . '</button> ';
			echo '<button type="button" class="button-link cemeti-media__clear">' . esc_html( 'images' === $def['type'] ? __( 'Remove all', 'scriptmedia-cemeti' ) : __( 'Remove', 'scriptmedia-cemeti' ) ) . '</button>';
			echo '</div>';
			break;
		default:
			printf( '<input type="text" class="regular-text"%s name="%s" value="%s">', $id_attr, esc_attr( $name ), esc_attr( (string) $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Posts that can be picked in a relation field.
 *
 * @param array<string, mixed> $def     Field definition.
 * @param int                  $exclude Post ID to leave out.
 * @return WP_Post[]
 */
function cemeti_relation_choices( array $def, $exclude ) {
	return get_posts(
		array(
			'post_type'        => isset( $def['post_types'] ) ? $def['post_types'] : 'any',
			'post_status'      => array( 'publish', 'draft', 'future', 'pending', 'private' ),
			'posts_per_page'   => 300,
			'post__not_in'     => array( $exclude ),
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		)
	);
}

/**
 * Render the meta box.
 *
 * @param WP_Post $post Current post.
 */
function cemeti_render_meta_box( $post ) {
	wp_nonce_field( 'cemeti_save_fields', 'cemeti_fields_nonce' );
	echo '<p class="description cemeti-fields__intro">' . esc_html__( 'Title: the box at the top. Foreword (Kata Pengantar): the main text editor. Poster: Featured image. Everything else is below; a section that is left empty does not appear on the page or in its table of contents.', 'scriptmedia-cemeti' ) . '</p>';
	echo '<table class="form-table cemeti-fields" role="presentation">';
	foreach ( cemeti_field_defs( $post->post_type ) as $def ) {
		if ( ! empty( $def['section'] ) ) {
			echo '<tr class="cemeti-fields__section"><th colspan="2"><h3>' . esc_html( $def['section'] ) . '</h3></th></tr>';
		}
		$key   = $def['key'];
		$id    = 'cemeti_field_' . $key;
		$value = cemeti_get_field( $key, $post->ID );
		if ( 'checkbox' === $def['type'] && '' === $value ) {
			$value = 'active' === $key && 'open_call' === $post->post_type && 'previous' === cemeti_get_status( $post->ID ) ? '0' : '1'; // Never touched: ticked unless its deadline has passed.
		}
		// One field that cannot be shown (unexpected stored data) must not blank the whole box: say which one and go on.
		ob_start();
		try {
			$has_control = ! in_array( $def['type'], array( 'repeater', 'list', 'images', 'file', 'image' ), true );
			echo '<tr><th scope="row">' . ( $has_control ? '<label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label>' : '<span>' . esc_html( $def['label'] ) . '</span>' ) . '</th><td>';

			if ( 'relation' === $def['type'] ) {
				printf( '<select multiple size="8" class="cemeti-relation" id="%s" name="cemeti_fields[%s][]" data-max="%d">', esc_attr( $id ), esc_attr( $key ), (int) $def['max'] );
				foreach ( cemeti_relation_choices( $def, $post->ID ) as $choice ) {
					$type_object = get_post_type_object( $choice->post_type );
					printf(
						'<option value="%d"%s>%s — %s</option>',
						(int) $choice->ID,
						selected( in_array( $choice->ID, $value, true ), true, false ),
						esc_html( $type_object ? $type_object->labels->singular_name : $choice->post_type ),
						esc_html( get_the_title( $choice ) )
					);
				}
				echo '</select>';
			} elseif ( 'repeater' === $def['type'] ) {
				cemeti_render_repeater( $def, $value );
			} else {
				cemeti_render_input( $def, $value, 'cemeti_fields[' . $key . ']', $id );
			}

			if ( ! empty( $def['help'] ) ) {
				echo '<p class="description">' . esc_html( $def['help'] ) . '</p>';
			}
		} catch ( Throwable $e ) {
			ob_end_clean();
			echo '<tr><th scope="row"><span>' . esc_html( $def['label'] ) . '</span></th><td><p><strong>' . esc_html__( 'This field could not be shown:', 'scriptmedia-cemeti' ) . '</strong> <code>' . esc_html( $e->getMessage() ) . '</code></p>';
			$e = null;
			echo '</td></tr>';
			continue;
		}
		echo ob_get_clean();
		echo '</td></tr>';
	}
	echo '</table>';
}

/**
 * Render a repeater with a hidden row template for the add button.
 *
 * @param array<string, mixed> $def  Field definition.
 * @param array                $rows Stored rows.
 */
function cemeti_render_repeater( array $def, array $rows ) {
	$base  = 'cemeti_fields[' . $def['key'] . ']';
	$item  = isset( $def['item'] ) ? $def['item'] : __( 'Item', 'scriptmedia-cemeti' );
	$by    = isset( $def['title_by'] ) ? $def['title_by'] : '';
	$block = static function ( $index, $number, $values ) use ( $def, $base, $item, $by ) {
		$name = '' !== $by && isset( $values[ $by ] ) ? (string) $values[ $by ] : '';
		echo '<div class="cemeti-repeater__row" data-label="' . esc_attr( $item ) . '">';
		echo '<div class="cemeti-repeater__bar"><strong class="cemeti-repeater__title">' . esc_html( $item . ' ' . $number . ( '' !== $name ? ' — ' . $name : '' ) ) . '</strong> ' . cemeti_row_buttons() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $def['sub'] as $sub ) {
			$value = isset( $values[ $sub['key'] ] ) ? $values[ $sub['key'] ] : '';
			$media = in_array( $sub['type'], array( 'image', 'images', 'file' ), true );
			$id    = $media ? '' : 'cemeti_field_' . $def['key'] . '_' . $index . '_' . $sub['key'];
			echo '<div class="cemeti-repeater__field" data-field="' . esc_attr( $sub['key'] ) . '">';
			echo $media ? '<span class="cemeti-repeater__label">' : '<label class="cemeti-repeater__label" for="' . esc_attr( $id ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo esc_html( $sub['label'] ) . ( $media ? '</span>' : '</label>' ) . '<br>'; // phpcs:ignore WordPress.Security.EscapeOutput
			cemeti_render_input( $sub, $value, $base . '[' . $index . '][' . $sub['key'] . ']', $id );
			echo '</div>';
		}
		echo '</div>';
	};

	echo '<div class="cemeti-repeater" data-next="' . (int) count( $rows ) . '"' . ( ! empty( $def['from'] ) ? ' data-from="cemeti_fields[' . esc_attr( $def['from'] ) . ']"' : '' ) . ' data-by="' . esc_attr( $by ) . '">';
	echo '<div class="cemeti-repeater__rows">';
	foreach ( array_values( $rows ) as $index => $values ) {
		$block( $index, $index + 1, $values );
	}
	echo '</div>';
	echo '<template class="cemeti-repeater__template">';
	$block( '__INDEX__', '__NUMBER__', array() );
	echo '</template>';
	echo '<button type="button" class="button cemeti-repeater__add">' . esc_html( sprintf( /* translators: %s: item name, e.g. Artist */ __( 'Add %s', 'scriptmedia-cemeti' ), $item ) ) . '</button> ';
	if ( ! empty( $def['from'] ) ) {
		echo '<button type="button" class="button cemeti-repeater__from">' . esc_html__( 'Add from Artists', 'scriptmedia-cemeti' ) . '</button>';
	}
	echo '</div>';
}

/**
 * Save the fields of a post.
 *
 * @param int $post_id Post ID.
 */
function cemeti_save_fields( $post_id ) {
	if ( ! isset( $_POST['cemeti_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cemeti_fields_nonce'] ) ), 'cemeti_save_fields' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$defs      = cemeti_field_defs( get_post_type( $post_id ) );
	$submitted = isset( $_POST['cemeti_fields'] ) && is_array( $_POST['cemeti_fields'] ) ? wp_unslash( $_POST['cemeti_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$values    = array();

	foreach ( $defs as $def ) {
		$values[ $def['key'] ] = cemeti_sanitize_field( $def, isset( $submitted[ $def['key'] ] ) ? $submitted[ $def['key'] ] : '' );
	}

	// A one-day event has no end date of its own: end = start.
	list( $start_key, $end_key ) = cemeti_date_keys( get_post_type( $post_id ) );
	if ( $start_key && ! empty( $values[ $start_key ] ) ) {
		if ( empty( $values[ $end_key ] ) || $values[ $end_key ] < $values[ $start_key ] ) {
			$values[ $end_key ] = $values[ $start_key ];
		}
	}

	foreach ( $defs as $def ) {
		$key   = $def['key'];
		$value = $values[ $key ];
		if ( 'relation' === $def['type'] ) {
			$value = cemeti_sync_relations( $post_id, $key, $value );
		}
		if ( cemeti_field_is_empty( $value ) ) {
			delete_post_meta( $post_id, 'cemeti_' . $key );
		} else {
			update_post_meta( $post_id, 'cemeti_' . $key, $value );
		}
	}
}
add_action( 'save_post', 'cemeti_save_fields', 5 );

/**
 * Load the media picker and field scripts on Cemeti edit screens.
 *
 * @param string $hook Admin page hook.
 */
function cemeti_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	$about  = $screen && 'page' === $screen->post_type && ! empty( $_GET['post'] ) && 'about' === cemeti_page_key( absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification -- About has the History box.
	if ( ! $screen || ( ! $about && ! array_key_exists( $screen->post_type, cemeti_config( 'fields' ) ) ) ) {
		return;
	}
	wp_enqueue_media();
	$rel = 'assets/js/admin-fields.js';
	wp_enqueue_script( 'cemeti-admin-fields', CEMETI_URI . '/' . $rel, array(), cemeti_asset_version( $rel ), true );
	$css = 'assets/css/admin.css';
	wp_enqueue_style( 'cemeti-admin', CEMETI_URI . '/' . $css, array(), cemeti_asset_version( $css ) );
}
add_action( 'admin_enqueue_scripts', 'cemeti_admin_assets' );
