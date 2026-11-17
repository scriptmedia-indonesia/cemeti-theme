<?php
/**
 * Open Call page. One call is shown, in the design layout: picture, title, text, deadline and the blue
 * "Application Submission" button. It is the newest call that is ticked "Active"; when none is active the newest
 * call is shown with its "closed" notice. The team switches a call on and off with the "Active" tick (or in the list).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$cemeti_call = cemeti_current_open_call();
?>
<div class="cemeti-container">
	<?php cemeti_page_head( 'Open Call', 'blue' ); ?>
	<?php
	if ( $cemeti_call ) :
		$GLOBALS['post'] = $cemeti_call; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $cemeti_call );
		$cemeti_deadline = cemeti_get_date_range( $cemeti_call->ID );
		$cemeti_external = (string) cemeti_get_field( 'application_url', $cemeti_call->ID );
		$cemeti_target   = '' !== $cemeti_external ? $cemeti_external : get_permalink( $cemeti_call ) . '#apply';
		?>
		<div class="cemeti-opencalls">
			<article class="cemeti-single cemeti-single--open_call cemeti-opencall">
				<?php if ( has_post_thumbnail( $cemeti_call->ID ) ) : ?>
					<div class="cemeti-opencall__poster"><?php echo get_the_post_thumbnail( $cemeti_call, 'cemeti-poster' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<?php endif; ?>
				<div class="cemeti-opencall__text">
					<h2 class="cemeti-single__title"><?php echo esc_html( get_the_title( $cemeti_call ) ); ?></h2>
					<div class="cemeti-single__body"><?php echo apply_filters( 'the_content', $cemeti_call->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php if ( '' !== $cemeti_deadline ) : ?>
						<p><strong><?php echo esc_html( cemeti_t( 'deadline' ) ); ?>: <?php echo esc_html( $cemeti_deadline ); ?></strong></p>
					<?php endif; ?>
					<?php if ( cemeti_open_call_is_open( $cemeti_call->ID ) ) : ?>
						<a class="cemeti-btn cemeti-btn--blue" href="<?php echo esc_url( $cemeti_target ); ?>"><?php echo esc_html( cemeti_t( 'apply_button' ) ); ?></a>
					<?php else : ?>
						<p class="cemeti-apply__notice"><?php echo esc_html( cemeti_t( 'form_closed' ) ); ?></p>
					<?php endif; ?>
				</div>
			</article>
		</div>
		<?php wp_reset_postdata(); ?>
	<?php else : ?>
		<p class="cemeti-empty"><?php echo esc_html( cemeti_t( 'no_results' ) ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
