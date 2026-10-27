<?php
/**
 * Journal article: wide image, title, author, text, related and share.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
$cemeti_related = cemeti_get_related( get_the_ID() );
$cemeti_author  = (string) cemeti_get_field( 'author', get_the_ID() );
?>
<article class="cemeti-single cemeti-single--journal">
	<div class="cemeti-container">
		<div class="cemeti-single__nav">
			<a class="cemeti-back" href="<?php echo esc_url( cemeti_back_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( cemeti_t( 'back' ) ); ?></a>
		</div>
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'cemeti-hero', array( 'class' => 'cemeti-single__hero' ) ); ?>
		<?php endif; ?>
		<header class="cemeti-single__head cemeti-single__head--left">
			<h1 class="cemeti-single__title"><?php the_title(); ?></h1>
			<?php if ( '' !== $cemeti_author ) : ?>
				<p class="cemeti-single__people"><?php echo esc_html( $cemeti_author ); ?></p>
			<?php endif; ?>
			<p class="cemeti-single__time"><?php echo esc_html( get_the_date( 'd-m-Y' ) . ' · ' . cemeti_post_label( get_post() ) ); ?></p>
		</header>
		<div class="cemeti-single__body"><?php the_content(); ?></div>

		<?php if ( $cemeti_related ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'related' ), 'related' ); ?>
				<?php cemeti_render_related( $cemeti_related ); ?>
			</section>
		<?php endif; ?>
		<?php cemeti_render_share( get_permalink(), get_the_title() ); ?>
	</div>
</article>
<?php
get_footer();
