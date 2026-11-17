<?php
/**
 * Generic page: blue script heading and the content.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
?>
<article class="cemeti-single cemeti-single--page">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title(), 'blue' ); ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'cemeti-hero', array( 'class' => 'cemeti-single__hero' ) ); ?>
		<?php endif; ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>
	</div>
</article>
<?php
get_footer();
