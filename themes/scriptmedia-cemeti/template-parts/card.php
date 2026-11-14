<?php
/**
 * Archive card: square poster, salmon title, bold date.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<li class="cemeti-card">
	<a href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'cemeti-card', array_merge( array( 'class' => 'cemeti-card__image' ), cemeti_image_loading( (int) $GLOBALS['wp_query']->current_post, 4, ! is_paged() ) ) ); ?>
		<?php else : ?>
			<span class="cemeti-card__image cemeti-card__image--empty" aria-hidden="true"></span>
		<?php endif; ?>
		<span class="cemeti-card__body">
			<span class="cemeti-card__title"><?php echo esc_html( cemeti_title() ); ?></span>
			<span class="cemeti-card__date"><?php echo esc_html( cemeti_get_date_range() ); ?></span>
		</span>
	</a>
</li>
