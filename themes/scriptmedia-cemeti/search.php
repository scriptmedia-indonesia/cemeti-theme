<?php
/**
 * Search results as ruled rows (derived: not in the design PDF).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="cemeti-container cemeti-container--wide">
	<?php cemeti_page_head( cemeti_t( 'search_results' ) . ': ' . get_search_query() ); ?>
	<?php if ( have_posts() ) : ?>
		<ul class="cemeti-row-list">
			<?php
			while ( have_posts() ) {
				the_post();
				get_template_part( 'template-parts/journal-row' );
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
