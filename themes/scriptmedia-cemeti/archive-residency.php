<?php
/**
 * Residency archive: Reguler and Research intros (term descriptions), then the grid.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$cemeti_terms = get_terms( array( 'taxonomy' => 'residency_type', 'hide_empty' => false, 'orderby' => 'term_id' ) );
?>
<div class="cemeti-container">
	<?php cemeti_page_head( post_type_archive_title( '', false ) ); ?>

	<?php if ( ! is_wp_error( $cemeti_terms ) && $cemeti_terms ) : ?>
		<div class="cemeti-residency-intro">
			<?php foreach ( $cemeti_terms as $cemeti_term ) : ?>
				<?php if ( '' !== trim( $cemeti_term->description ) ) : ?>
					<section>
						<h2 class="cemeti-script"><a href="<?php echo esc_url( add_query_arg( 'type', $cemeti_term->slug, get_post_type_archive_link( 'residency' ) ) ); ?>"><?php echo esc_html( $cemeti_term->name ); ?></a></h2>
						<?php echo wp_kses_post( wpautop( $cemeti_term->description ) ); ?>
					</section>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

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
<?php
get_footer();
