<?php
/**
 * Open Archive item: title, who and when, the text, and the download button (Download Booklet / Unduh Booklet).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
$cemeti_file    = cemeti_archive_file_url( get_the_ID() );
$cemeti_authors = (string) cemeti_get_field( 'authors', get_the_ID() );
$cemeti_year    = (string) cemeti_get_field( 'year', get_the_ID() );
$cemeti_meta    = implode( ' · ', array_filter( array( $cemeti_authors, $cemeti_year ) ) );
?>
<article class="cemeti-single cemeti-single--archive">
	<div class="cemeti-container">
		<div class="cemeti-single__nav">
			<a class="cemeti-back" href="<?php echo esc_url( cemeti_back_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( cemeti_t( 'back' ) ); ?></a>
		</div>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="cemeti-single__figure">
				<?php the_post_thumbnail( 'cemeti-hero', array( 'class' => 'cemeti-single__hero' ) ); ?>
			</figure>
		<?php endif; ?>
		<header class="cemeti-single__head">
			<h1 class="cemeti-single__title"><?php echo esc_html( cemeti_title() ); ?></h1>
			<?php if ( '' !== $cemeti_meta ) : ?>
				<p class="cemeti-single__people"><?php echo esc_html( $cemeti_meta ); ?></p>
			<?php endif; ?>
		</header>
		<div class="cemeti-single__body"><?php the_content(); ?></div>
		<?php if ( '' !== $cemeti_file ) : ?>
			<p class="cemeti-single__download">
				<a class="cemeti-btn cemeti-btn--inline cemeti-btn--download" href="<?php echo esc_url( $cemeti_file ); ?>" download rel="noopener"><?php echo esc_html( cemeti_t( 'download_booklet' ) ); ?></a>
			</p>
		<?php endif; ?>
		<?php cemeti_render_share( get_permalink(), get_the_title() ); ?>
	</div>
</article>
<?php
get_footer();
