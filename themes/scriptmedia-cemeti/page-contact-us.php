<?php
/**
 * Contact Us: picture and text like About, then the contact details. Everything is edited on the page (Pages →
 * Contact Us and its translation); a detail left empty uses Settings → Cemeti.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
$cemeti_page_id = get_the_ID();
$cemeti_route   = cemeti_validate_maps_url( cemeti_contact_value( 'route_url', $cemeti_page_id ) );
$cemeti_email   = cemeti_contact_value( 'email', $cemeti_page_id );
$cemeti_phone   = cemeti_contact_value( 'phone', $cemeti_page_id );
$cemeti_address = cemeti_contact_value( 'address', $cemeti_page_id );
$cemeti_hours   = cemeti_contact_value( 'opening_hours', $cemeti_page_id );
// Same picture size in every language: the featured image, else the picture that opens the text (as About).
$cemeti_text = get_the_content();
$cemeti_hero = has_post_thumbnail() ? (int) get_post_thumbnail_id() : 0;
if ( ! $cemeti_hero ) {
	list( $cemeti_hero, $cemeti_text ) = cemeti_take_leading_image( $cemeti_text );
}
?>
<article class="cemeti-single cemeti-single--contact">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title(), 'blue' ); ?>
		<?php if ( $cemeti_hero ) : ?>
			<?php echo wp_get_attachment_image( $cemeti_hero, 'cemeti-hero', false, array( 'class' => 'cemeti-single__hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php endif; ?>
		<div class="cemeti-single__body"><?php echo apply_filters( 'the_content', $cemeti_text ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<address class="cemeti-contact">
			<p><strong><?php echo esc_html( cemeti_option( 'institution_name' ) ); ?></strong></p>
			<?php if ( '' !== $cemeti_address ) : ?>
				<p><?php echo cemeti_multiline( $cemeti_address ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<?php endif; ?>
			<?php if ( '' !== $cemeti_hours ) : ?>
				<p><?php echo cemeti_multiline( $cemeti_hours ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<?php endif; ?>
			<?php if ( is_email( $cemeti_email ) ) : ?>
				<p><a href="<?php echo esc_url( 'mailto:' . $cemeti_email ); ?>"><?php echo esc_html( $cemeti_email ); ?></a></p>
			<?php endif; ?>
			<?php if ( '' !== $cemeti_phone ) : ?>
				<p><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $cemeti_phone ) ); ?>"><?php echo esc_html( $cemeti_phone ); ?></a></p>
			<?php endif; ?>
			<?php if ( $cemeti_route ) : ?>
				<p><a href="<?php echo esc_url( $cemeti_route ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( cemeti_t( 'plan_route' ) ); ?> &gt;&gt;</a></p>
			<?php endif; ?>
		</address>
	</div>
</article>
<?php
get_footer();
