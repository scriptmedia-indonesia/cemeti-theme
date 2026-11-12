<?php
/**
 * Open Archive: page title, description, then the database of booklets and writings to read or download.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
?>
<article class="cemeti-single cemeti-single--open-archive">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title() ); ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>
		<?php cemeti_render_archive(); ?>
	</div>
</article>
<?php
get_footer();
