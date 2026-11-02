<?php
/**
 * Open Call list. The design has no detail page for an Open Call: each call is shown in full here
 * (picture, title, text, deadline) with the blue "Application Submission" button, which goes to the form.
 * More than one call can be open at the same time.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="cemeti-container">
	<?php cemeti_page_head( 'Open Call', 'blue' ); ?>
	<?php if ( have_posts() ) : ?>
		<div class="cemeti-opencalls">
			<?php
			while ( have_posts() ) :
				the_post();
				$cemeti_deadline = cemeti_get_date_range( get_the_ID() );
				$cemeti_external = (string) cemeti_get_field( 'application_url', get_the_ID() );
				$cemeti_target   = '' !== $cemeti_external ? $cemeti_external : get_permalink() . '#apply';
				?>
				<article class="cemeti-single cemeti-single--open_call cemeti-opencall">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="cemeti-opencall__poster"><?php the_post_thumbnail( 'cemeti-poster' ); ?></div>
					<?php endif; ?>
					<div class="cemeti-opencall__text">
						<h2 class="cemeti-single__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div class="cemeti-single__body"><?php the_content(); ?></div>
						<?php if ( '' !== $cemeti_deadline ) : ?>
							<p><strong><?php echo esc_html( cemeti_t( 'deadline' ) ); ?>: <?php echo esc_html( $cemeti_deadline ); ?></strong></p>
						<?php endif; ?>
						<?php if ( cemeti_open_call_is_open( get_the_ID() ) ) : ?>
							<a class="cemeti-btn cemeti-btn--blue" href="<?php echo esc_url( $cemeti_target ); ?>"><?php echo esc_html( cemeti_t( 'apply_button' ) ); ?></a>
						<?php else : ?>
							<p class="cemeti-apply__notice"><?php echo esc_html( cemeti_t( 'form_closed' ) ); ?></p>
						<?php endif; ?>
					</div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php get_template_part( 'template-parts/pagination' ); ?>
	<?php else : ?>
		<p class="cemeti-empty"><?php echo esc_html( cemeti_t( 'no_results' ) ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
