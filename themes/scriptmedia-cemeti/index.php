<?php
/**
 * Minimal loop used until the Cemeti templates land in M3. The parent theme
 * provides the full fallback templates; this one only keeps the header/footer shell visible.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="cemeti-container">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class(); ?>>
				<h1 class="cemeti-script"><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
