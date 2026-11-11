<?php
/**
 * Residency archive: Regular and Research intros (term descriptions), then the grid.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$cemeti_terms  = cemeti_intro_terms( 'residency_type' );
$cemeti_active = cemeti_residency_active_type();
$cemeti_base   = get_post_type_archive_link( 'residency' );
?>
<div class="cemeti-container">
	<?php cemeti_page_head( cemeti_archive_heading() ); ?>

	<?php if ( $cemeti_terms ) : ?>
		<div class="cemeti-residency-intro">
			<?php foreach ( $cemeti_terms as $cemeti_term ) : ?>
				<section>
					<?php $cemeti_is_active = '' !== $cemeti_active && cemeti_type_key( $cemeti_term->slug ) === $cemeti_active; ?>
					<?php /* A second press on the chosen category shows everything again. */ ?>
					<h2 class="cemeti-script"><a href="<?php echo esc_url( $cemeti_is_active ? $cemeti_base : add_query_arg( 'type', $cemeti_term->slug, $cemeti_base ) ); ?>"<?php echo $cemeti_is_active ? ' class="is-active" aria-current="true"' : ''; ?>><?php echo esc_html( $cemeti_term->name ); ?></a></h2>
					<?php echo wp_kses_post( wpautop( cemeti_term_description( $cemeti_term ) ) ); ?>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div id="cemeti-results" class="cemeti-results" aria-live="polite">
	<?php if ( have_posts() ) : ?>
		<ul class="cemeti-card-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/card' );
			}
			?>
		</ul>
		<?php get_template_part( 'template-parts/pagination' ); ?>
	<?php else : ?>
		<p class="cemeti-empty"><?php echo esc_html( cemeti_t( 'no_results' ) ); ?></p>
	<?php endif; ?>
	</div>
</div>
<?php
get_footer();
