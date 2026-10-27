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
 * Dates come back as Ymd, images as an int, images as int[], relations as int[],
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

	if ( in_array( $type, array( 'images', 'relation', 'repeater' ), true ) ) {
		return is_array( $value ) ? $value : array();
	}
	if ( 'image' === $type ) {
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
 * Render one input.
 *
 * @param array<string, mixed> $def   Field definition.
 * @param mixed                $value Stored value.
 * @param string               $name  Input name.
 * @param string               $id    Input id.
 */
function cemeti_render_input( array $def, $value, $name, $id ) {
	switch ( $def['type'] ) {
		case 'textarea':
			printf( '<textarea class="large-text" rows="3" id="%s" name="%s">%s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( (string) $value ) );
			break;
		case 'date':
			$display = preg_match( '/^(\d{4})(\d{2})(\d{2})$/', (string) $value, $m ) ? $m[1] . '-' . $m[2] . '-' . $m[3] : '';
			printf( '<input type="date" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $display ) );
			break;
		case 'url':
			printf( '<input type="url" class="regular-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
			break;
		case 'image':
		case 'images':
			$ids   = 'image' === $def['type'] ? array_filter( array( (int) $value ) ) : (array) $value;
			$multi = 'images' === $def['type'] ? '1' : '0';
			echo '<div class="cemeti-media" data-multiple="' . esc_attr( $multi ) . '">';
			printf( '<input type="hidden" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( implode( ',', $ids ) ) );
			echo '<div class="cemeti-media__thumbs">';
			foreach ( $ids as $attachment_id ) {
				echo wp_get_attachment_image( (int) $attachment_id, array( 80, 80 ), false, array( 'data-id' => (int) $attachment_id ) );
			}
			echo '</div>';
			echo '<button type="button" class="button cemeti-media__pick">' . esc_html__( 'Choose', 'scriptmedia-cemeti' ) . '</button> ';
			echo '<button type="button" class="button-link cemeti-media__clear">' . esc_html__( 'Clear', 'scriptmedia-cemeti' ) . '</button>';
			echo '</div>';
			break;
		default:
			printf( '<input type="text" class="regular-text" id="%s" name="%s" value="%s">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
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
	echo '<table class="form-table cemeti-fields" role="presentation">';
	foreach ( cemeti_field_defs( $post->post_type ) as $def ) {
		$key   = $def['key'];
		$id    = 'cemeti_field_' . $key;
		$value = cemeti_get_field( $key, $post->ID );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label></th><td>';

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
	$base = 'cemeti_fields[' . $def['key'] . ']';
	$row  = static function ( $index, $values ) use ( $def, $base ) {
		echo '<div class="cemeti-repeater__row">';
		foreach ( $def['sub'] as $sub ) {
			$value = isset( $values[ $sub['key'] ] ) ? $values[ $sub['key'] ] : '';
			echo '<p><label>' . esc_html( $sub['label'] ) . '<br>';
			cemeti_render_input( $sub, $value, $base . '[' . $index . '][' . $sub['key'] . ']', '' );
			echo '</label></p>';
		}
		echo '<button type="button" class="button-link-delete cemeti-repeater__remove">' . esc_html__( 'Remove', 'scriptmedia-cemeti' ) . '</button></div>';
	};

	echo '<div class="cemeti-repeater" data-next="' . (int) count( $rows ) . '">';
	echo '<div class="cemeti-repeater__rows">';
	foreach ( array_values( $rows ) as $index => $values ) {
		$row( $index, $values );
	}
	echo '</div>';
	echo '<template class="cemeti-repeater__template">';
	$row( '__INDEX__', array() );
	echo '</template>';
	echo '<button type="button" class="button cemeti-repeater__add">' . esc_html__( 'Add', 'scriptmedia-cemeti' ) . '</button>';
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
	if ( ! $screen || ! array_key_exists( $screen->post_type, cemeti_config( 'fields' ) ) ) {
		return;
	}
	wp_enqueue_media();
	$rel = 'assets/js/admin-fields.js';
	wp_enqueue_script( 'cemeti-admin-fields', CEMETI_URI . '/' . $rel, array(), cemeti_asset_version( $rel ), true );
	wp_add_inline_style( 'wp-admin', '.cemeti-media__thumbs img{margin:0 6px 6px 0;vertical-align:top}.cemeti-repeater__row{margin:0 0 12px;padding:8px 12px;border:1px solid #c3c4c7;background:#fff}.cemeti-fields th{width:200px}' );
}
add_action( 'admin_enqueue_scripts', 'cemeti_admin_assets' );
