<?php
/**
 * Contact Us (derived: not in the design PDF). Content plus the Site Settings contact details.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
$cemeti_route = cemeti_validate_maps_url( cemeti_option( 'route_url' ) );
$cemeti_email = cemeti_option( 'email' );
?>
<article class="cemeti-single cemeti-single--contact">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title(), 'blue' ); ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>
		<address class="cemeti-contact">
			<p><strong><?php echo esc_html( cemeti_option( 'institution_name' ) ); ?></strong></p>
			<p><?php echo cemeti_multiline( cemeti_option( 'address' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<p><?php echo cemeti_multiline( cemeti_option( 'opening_hours' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<?php if ( is_email( $cemeti_email ) ) : ?>
				<p><a href="<?php echo esc_url( 'mailto:' . $cemeti_email ); ?>"><?php echo esc_html( $cemeti_email ); ?></a></p>
			<?php endif; ?>
			<?php if ( $cemeti_route ) : ?>
				<p><a href="<?php echo esc_url( $cemeti_route ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( cemeti_t( 'plan_route' ) ); ?> &gt;&gt;</a></p>
			<?php endif; ?>
		</address>
	</div>
</article>
<?php
get_footer();
