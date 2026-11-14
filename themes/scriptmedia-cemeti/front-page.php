<?php
/**
 * Home: the featured entry of each program, then the latest Journal rows.
 * Featured = current, else soonest upcoming, else latest previous.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

?>
<div class="cemeti-container cemeti-home">
	<h1 class="screen-reader-text"><?php echo esc_html( cemeti_option( 'institution_name' ) ); ?></h1>
	<?php
	// The program sections and the Journal rows are the same for every visitor: kept for a few hours (see inc/performance.php).
	cemeti_cached_fragment(
		'home',
		static function () {
			get_template_part( 'template-parts/home-sections' );
		}
	);
	?>

	<?php if ( have_posts() && is_page() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<div class="cemeti-single__body"><?php the_content(); ?></div>
		<?php endwhile; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
