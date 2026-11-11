<?php
/**
 * Site Settings page (Settings → Cemeti), one set of fields per language.
 * Stored in the options "cemeti_settings_id" and "cemeti_settings_en".
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings fields: key => array( label, type ).
 *
 * @return array<string, array{0:string,1:string}>
 */
function cemeti_settings_fields() {
	return array(
		'institution_name' => array( __( 'Institution name', 'scriptmedia-cemeti' ), 'text' ),
		'site_description' => array( __( 'Site description (search results, sharing)', 'scriptmedia-cemeti' ), 'textarea' ),
		'address'          => array( __( 'Address', 'scriptmedia-cemeti' ), 'textarea' ),
		'opening_hours'    => array( __( 'Opening hours', 'scriptmedia-cemeti' ), 'textarea' ),
		'email'            => array( __( 'Email', 'scriptmedia-cemeti' ), 'email' ),
		'notify_email'     => array( __( 'Application notifications go to', 'scriptmedia-cemeti' ), 'email' ),
		'instagram_url'    => array( __( 'Instagram URL', 'scriptmedia-cemeti' ), 'url' ),
		'youtube_url'      => array( __( 'Youtube URL', 'scriptmedia-cemeti' ), 'url' ),
		'facebook_url'     => array( __( 'Facebook URL', 'scriptmedia-cemeti' ), 'url' ),
		'route_url'        => array( __( 'Google Maps route URL', 'scriptmedia-cemeti' ), 'url' ),
	);
}

/**
 * Sanitize one language's settings.
 *
 * @param mixed $input Submitted values.
 * @return array<string, string>
 */
function cemeti_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	$clean = array();
	foreach ( cemeti_settings_fields() as $key => $field ) {
		$raw = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? (string) $input[ $key ] : '';
		switch ( $field[1] ) {
			case 'textarea':
				$clean[ $key ] = sanitize_textarea_field( $raw );
				break;
			case 'email':
				$clean[ $key ] = sanitize_email( $raw );
				break;
			case 'url':
				$clean[ $key ] = esc_url_raw( trim( $raw ), array( 'https', 'http' ) );
				break;
			default:
				$clean[ $key ] = sanitize_text_field( $raw );
		}
	}
	return $clean;
}

/**
 * Register one option per language.
 */
function cemeti_register_settings() {
	foreach ( array( 'id', 'en' ) as $lang ) {
		register_setting(
			'cemeti_settings',
			'cemeti_settings_' . $lang,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'cemeti_sanitize_settings',
				'default'           => array(),
			)
		);
	}
}
add_action( 'admin_init', 'cemeti_register_settings' );

/**
 * Add the settings page.
 */
function cemeti_settings_menu() {
	add_options_page(
		__( 'Cemeti Settings', 'scriptmedia-cemeti' ),
		__( 'Cemeti', 'scriptmedia-cemeti' ),
		'manage_options',
		'cemeti-settings',
		'cemeti_render_settings_page'
	);
}
add_action( 'admin_menu', 'cemeti_settings_menu' );

/**
 * Render the settings page.
 */
function cemeti_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$langs = array(
		'en' => __( 'English (main language)', 'scriptmedia-cemeti' ),
		'id' => __( 'Bahasa Indonesia', 'scriptmedia-cemeti' ),
	);
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Cemeti Settings', 'scriptmedia-cemeti' ); ?></h1>
		<p><?php echo esc_html__( 'Empty fields fall back to the default language, then to built-in values.', 'scriptmedia-cemeti' ); ?></p>
		<?php if ( isset( $_GET['cemeti_types_filled'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( /* translators: %d: number of exhibitions */ _n( 'Solo/Group set on %d exhibition.', 'Solo/Group set on %d exhibitions.', (int) $_GET['cemeti_types_filled'], 'scriptmedia-cemeti' ), (int) $_GET['cemeti_types_filled'] ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['cemeti_thumbs_filled'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( /* translators: %d: number of entries */ _n( 'Featured image set on %d entry.', 'Featured image set on %d entries.', (int) $_GET['cemeti_thumbs_filled'], 'scriptmedia-cemeti' ), (int) $_GET['cemeti_thumbs_filled'] ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
		<?php endif; ?>
		<h2><?php echo esc_html__( 'Maintenance', 'scriptmedia-cemeti' ); ?></h2>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_create_pages' ), 'cemeti_create_pages' ) ); ?>"><?php echo esc_html__( 'Create missing pages', 'scriptmedia-cemeti' ); ?></a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_fill_types' ), 'cemeti_fill_types' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: exhibitions without a type */ __( 'Set Solo/Group on exhibitions without a type (%d)', 'scriptmedia-cemeti' ), count( cemeti_exhibitions_without_type() ) ) ); ?></a>
			<span id="cemeti-media-runner" data-action="cemeti_thumbs_step" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cemeti_fill_thumbs' ) ); ?>">
				<a class="button" data-phase="thumbs" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cemeti_fill_thumbs' ), 'cemeti_fill_thumbs' ) ); ?>"><?php echo esc_html( sprintf( /* translators: %d: entries without a featured image */ __( 'Set missing featured images (%d entries have none)', 'scriptmedia-cemeti' ), count( cemeti_entries_without_thumbnail() ) ) ); ?></a>
				<button type="button" class="button cemeti-media-stop" hidden>Stop</button>
				<span class="cemeti-media-status notice inline" hidden style="display:block;margin-top:8px"></span>
			</span>
		</p>
		<p class="description"><?php echo esc_html__( 'Missing featured images: the entry gets the picture of its other-language copy, else the first picture of its Gallery / Documentation, else of its text. Nothing already set is changed.', 'scriptmedia-cemeti' ); ?></p>
		<?php cemeti_render_page_pairs_box(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'cemeti_settings' ); ?>
			<?php foreach ( $langs as $lang => $lang_label ) : ?>
				<?php $stored = get_option( 'cemeti_settings_' . $lang, array() ); ?>
				<h2><?php echo esc_html( $lang_label ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( cemeti_settings_fields() as $key => $field ) : ?>
						<?php
						$name  = 'cemeti_settings_' . $lang . '[' . $key . ']';
						$id    = 'cemeti_' . $lang . '_' . $key;
						$value = is_array( $stored ) && isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
							<td>
								<?php if ( 'textarea' === $field[1] ) : ?>
									<textarea class="large-text" rows="3" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
								<?php else : ?>
									<input class="regular-text" type="<?php echo esc_attr( $field[1] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
