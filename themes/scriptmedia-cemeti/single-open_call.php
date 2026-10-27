<?php
/**
 * Open Call: heading, poster left and text right, deadline and the blue
 * "Application Submission" button.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
$cemeti_deadline = cemeti_get_date_range( get_the_ID() );
$cemeti_apply    = (string) cemeti_get_field( 'application_url', get_the_ID() );
$cemeti_closed   = 'previous' === cemeti_get_status( get_the_ID() );
?>
<article class="cemeti-single cemeti-single--open_call">
	<div class="cemeti-container">
		<?php cemeti_page_head( 'Open Call', 'blue' ); ?>
		<div class="cemeti-opencall">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="cemeti-opencall__poster"><?php the_post_thumbnail( 'cemeti-poster' ); ?></div>
			<?php endif; ?>
			<div class="cemeti-opencall__text">
				<h2 class="cemeti-single__title"><?php the_title(); ?></h2>
				<div class="cemeti-single__body"><?php the_content(); ?></div>
				<?php if ( '' !== $cemeti_deadline ) : ?>
					<p><strong><?php echo esc_html( cemeti_t( 'deadline' ) ); ?>: <?php echo esc_html( $cemeti_deadline ); ?></strong></p>
				<?php endif; ?>
				<?php if ( '' !== $cemeti_apply && ! $cemeti_closed ) : ?>
					<a class="cemeti-btn cemeti-btn--blue cemeti-btn--inline" href="<?php echo esc_url( $cemeti_apply ); ?>">Application Submission</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</article>
<?php
get_footer();
