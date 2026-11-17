<?php
/**
 * Open Call application form. Included by single-open_call.php.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cemeti_call_id = get_the_ID();
$cemeti_docs    = cemeti_open_call_documents( $cemeti_call_id );
$cemeti_labels  = cemeti_application_documents();
$cemeti_result  = isset( $_GET['apply'] ) ? sanitize_key( wp_unslash( $_GET['apply'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$cemeti_errors  = array( 'required' => 'err_required', 'email' => 'err_email', 'file' => 'err_file', 'closed' => 'err_closed', 'spam' => 'err_spam', 'rate' => 'err_rate', 'server' => 'err_server' );
?>
<section class="cemeti-apply" id="apply">
	<h2 class="cemeti-section-heading cemeti-script"><?php echo esc_html( cemeti_t( 'form_title' ) ); ?></h2>

	<?php if ( 'sent' === $cemeti_result ) : ?>
		<p class="cemeti-apply__notice cemeti-apply__notice--ok" role="status"><?php echo esc_html( cemeti_t( 'form_sent' ) ); ?></p>
	<?php else : ?>
		<?php if ( isset( $cemeti_errors[ $cemeti_result ] ) ) : ?>
			<p class="cemeti-apply__notice cemeti-apply__notice--error" role="alert"><?php echo esc_html( cemeti_t( $cemeti_errors[ $cemeti_result ] ) ); ?></p>
		<?php endif; ?>

		<form class="cemeti-apply__form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cemeti_apply">
			<input type="hidden" name="open_call" value="<?php echo (int) $cemeti_call_id; ?>">
			<input type="hidden" name="cemeti_ts" value="<?php echo esc_attr( cemeti_form_token( time() ) ); ?>">
			<?php wp_nonce_field( 'cemeti_apply', '_cemeti_nonce', false ); ?>
			<p class="cemeti-apply__trap" aria-hidden="true"><label>Website <input type="text" name="cemeti_website" tabindex="-1" autocomplete="off"></label></p>

			<p><label for="app_name"><?php echo esc_html( cemeti_t( 'form_name' ) ); ?> *</label>
				<input type="text" id="app_name" name="app_name" required autocomplete="name"></p>
			<p><label for="app_email"><?php echo esc_html( cemeti_t( 'form_email' ) ); ?> *</label>
				<input type="email" id="app_email" name="app_email" required autocomplete="email"></p>
			<p><label for="app_city"><?php echo esc_html( cemeti_t( 'form_city' ) ); ?></label>
				<input type="text" id="app_city" name="app_city"></p>
			<p><label for="app_link"><?php echo esc_html( cemeti_t( 'form_link' ) ); ?></label>
				<input type="url" id="app_link" name="app_link" placeholder="https://"></p>
			<p><label for="app_message"><?php echo esc_html( cemeti_t( 'form_message' ) ); ?></label>
				<textarea id="app_message" name="app_message" rows="5"></textarea></p>

			<?php foreach ( $cemeti_docs as $cemeti_key ) : ?>
				<p><label for="doc_<?php echo esc_attr( $cemeti_key ); ?>"><?php echo esc_html( cemeti_t( $cemeti_labels[ $cemeti_key ] ) ); ?> * <small>(<?php echo esc_html( cemeti_t( 'form_pdf_hint' ) ); ?>)</small></label>
					<input type="file" id="doc_<?php echo esc_attr( $cemeti_key ); ?>" name="doc_<?php echo esc_attr( $cemeti_key ); ?>" accept="application/pdf,.pdf" required></p>
			<?php endforeach; ?>

			<p><button type="submit" class="cemeti-btn cemeti-btn--blue cemeti-btn--inline"><?php echo esc_html( cemeti_t( 'form_submit' ) ); ?></button></p>
		</form>
	<?php endif; ?>
</section>
