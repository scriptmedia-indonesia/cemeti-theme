<?php
/**
 * Support Us: mission text, then one accordion row per child page
 * (Patron of Cemeti, Friends of Cemeti, Annual Benefit, Collaboration).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
$cemeti_rows = get_pages(
	array(
		'parent'      => get_the_ID(),
		'sort_column' => 'menu_order,post_title',
		'post_status' => 'publish',
	)
);
?>
<article class="cemeti-single cemeti-single--support">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title(), 'blue' ); ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>
	</div>
	<?php if ( $cemeti_rows ) : ?>
		<div class="cemeti-accordion">
			<?php foreach ( $cemeti_rows as $cemeti_row ) : ?>
				<details class="cemeti-accordion__row">
					<summary><?php echo esc_html( get_the_title( $cemeti_row ) ); ?></summary>
					<div class="cemeti-accordion__panel cemeti-container">
						<?php echo apply_filters( 'the_content', $cemeti_row->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</article>
<?php
get_footer();
