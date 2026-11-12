<?php
/**
 * History: page title, description, then one dropdown row per year (same layout as Support Us).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
?>
<article class="cemeti-single cemeti-single--history">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title(), 'blue' ); ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>
	</div>
	<?php cemeti_render_history( get_the_ID() ); ?>
</article>
<?php
get_footer();
