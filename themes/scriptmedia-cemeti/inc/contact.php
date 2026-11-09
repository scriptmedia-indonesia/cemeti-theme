<?php
/**
 * Contact Us page: text, picture and the contact details are edited on the page itself (Pages → Contact Us, and its
 * Indonesian copy), like About. A detail left empty there falls back to Settings → Cemeti.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The details of the Contact Us page: key => array( label, kind, option key to fall back on, help ).
 *
 * @return array<string, array{0:string,1:string,2:string,3:string}>
 */
function cemeti_contact_fields() {
	return array(
		'address'       => array( 'Address', 'textarea', 'address', 'One line per row.' ),
		'opening_hours' => array( 'Opening hours', 'textarea', 'opening_hours', 'For example: Selasa – Sabtu, then 10:00 – 17:00 on the next line.' ),
		'email'         => array( 'Email', 'text', 'email', '' ),
		'phone'         => array( 'Phone / WhatsApp', 'text', '', 'Shown as a call link, for example +62 274 371015.' ),
		'route_url'     => array( 'Google Maps link', 'text', 'route_url', 'The "Plan your Route" button. Only Google Maps addresses are accepted.' ),
	);
}

/**
 * One detail of a Contact Us page: what the page says, else the site setting.
 *
 * @param string   $key     Detail key.
 * @param int|null $page_id Page ID (default: the current page).
 * @return string
 */
function cemeti_contact_value( $key, $page_id = null ) {
	$page_id = $page_id ? (int) $page_id : (int) get_the_ID();
	$fields  = cemeti_contact_fields();
	$own     = trim( (string) get_post_meta( $page_id, 'cemeti_contact_' . $key, true ) );
	if ( '' !== $own ) {
		return $own;
	}
	return isset( $fields[ $key ] ) && '' !== $fields[ $key ][2] ? (string) cemeti_option( $fields[ $key ][2] ) : '';
}

/**
 * The box on the Contact Us page (and only there).
 *
 * @param WP_Post $post Page.
 */
function cemeti_contact_add_box( $post ) {
	if ( 'contact-us' !== cemeti_page_key( $post->ID ) ) {
		return;
	}
	add_meta_box( 'cemeti_contact', __( 'Contact details', 'scriptmedia-cemeti' ), 'cemeti_contact_render_box', 'page', 'normal', 'high' );
}
add_action( 'add_meta_boxes_page', 'cemeti_contact_add_box', 6 );

/**
 * Fields of the box.
 *
 * @param WP_Post $post Page.
 */
function cemeti_contact_render_box( $post ) {
	wp_nonce_field( 'cemeti_contact_save', 'cemeti_contact_nonce' );
	echo '<p class="description">' . esc_html__( 'The text and the picture are the page\'s own editor and Featured image above. Leave a detail empty to use Settings → Cemeti. Do the same on the other language\'s copy.', 'scriptmedia-cemeti' ) . '</p>';
	foreach ( cemeti_contact_fields() as $key => $def ) {
		$value = (string) get_post_meta( $post->ID, 'cemeti_contact_' . $key, true );
		$name  = 'cemeti_contact[' . $key . ']';
		$id    = 'cemeti_contact_' . $key;
		echo '<p><label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $def[0] ) . '</strong></label><br>';
		if ( 'textarea' === $def[1] ) {
			echo '<textarea class="widefat" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input type="text" class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		if ( '' !== $def[2] ) {
			$fallback = (string) cemeti_option( $def[2] );
			echo '<br><span class="description">' . esc_html( $def[3] ) . ' ' . esc_html( sprintf( /* translators: %s: current site setting */ __( 'Empty = "%s" from Settings.', 'scriptmedia-cemeti' ), str_replace( "\n", ' / ', $fallback ) ) ) . '</span>';
		} elseif ( '' !== $def[3] ) {
			echo '<br><span class="description">' . esc_html( $def[3] ) . '</span>';
		}
		echo '</p>';
	}
}

/**
 * Save the box.
 *
 * @param int $post_id Page ID.
 */
function cemeti_contact_save( $post_id ) {
	if ( ! isset( $_POST['cemeti_contact_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cemeti_contact_nonce'] ) ), 'cemeti_contact_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$submitted = isset( $_POST['cemeti_contact'] ) && is_array( $_POST['cemeti_contact'] ) ? wp_unslash( $_POST['cemeti_contact'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( cemeti_contact_fields() as $key => $def ) {
		$raw = isset( $submitted[ $key ] ) && is_scalar( $submitted[ $key ] ) ? (string) $submitted[ $key ] : '';
		if ( 'textarea' === $def[1] ) {
			$value = sanitize_textarea_field( $raw );
		} elseif ( 'email' === $key ) {
			$value = is_email( trim( $raw ) ) ? trim( $raw ) : '';
		} elseif ( 'route_url' === $key ) {
			$value = cemeti_validate_maps_url( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}
		if ( '' === $value ) {
			delete_post_meta( $post_id, 'cemeti_contact_' . $key );
		} else {
			update_post_meta( $post_id, 'cemeti_contact_' . $key, $value );
		}
	}
}
add_action( 'save_post_page', 'cemeti_contact_save' );
