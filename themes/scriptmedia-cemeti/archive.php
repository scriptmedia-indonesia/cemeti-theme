<?php
/**
 * Archive with the card grid (Exhibition, Public Program, Open Studio, taxonomies).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="cemeti-container">
	<?php cemeti_page_head( cemeti_archive_heading() ); ?>
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
