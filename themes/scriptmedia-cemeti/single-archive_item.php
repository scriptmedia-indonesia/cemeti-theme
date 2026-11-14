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

$cemeti_id      = get_the_ID();
$cemeti_file    = cemeti_archive_file_url( $cemeti_id );
$cemeti_authors = (string) cemeti_get_field( 'authors', $cemeti_id );
$cemeti_year    = (string) cemeti_get_field( 'year', $cemeti_id );
$cemeti_meta    = implode( ' · ', array_filter( array( $cemeti_authors, $cemeti_year ) ) );
$cemeti_gallery = cemeti_get_field( 'gallery', $cemeti_id );
$cemeti_docs    = cemeti_get_field( 'documentation', $cemeti_id );
$cemeti_bios    = cemeti_get_field( 'biographies', $cemeti_id );
$cemeti_content = trim( get_the_content() );

// Same page as an Exhibition: contents bar and sections only when they have something in them.
$cemeti_toc = array();
if ( '' !== $cemeti_content ) {
	$cemeti_toc['foreword'] = 'foreword';
}
if ( $cemeti_gallery ) {
	$cemeti_toc['gallery'] = 'gallery';
}
if ( $cemeti_docs ) {
	$cemeti_toc['documentation'] = 'documentation';
}
if ( $cemeti_bios ) {
	$cemeti_toc['biography'] = 'profile';
}
if ( '' !== $cemeti_file ) {
	$cemeti_toc['booklet'] = 'download';
}
$cemeti_has_toc  = count( $cemeti_toc ) > 1;
$cemeti_has_back = $cemeti_has_toc;
?>
<article class="cemeti-single cemeti-single--archive cemeti-single--exhibition">
	<div class="cemeti-container">
		<span id="toc-top"></span>
		<div class="cemeti-single__nav<?php echo $cemeti_has_toc ? ' cemeti-single__nav--sticky' : ''; ?>">
			<?php if ( $cemeti_has_toc ) : ?>
				<nav class="cemeti-toc" id="toc" aria-labelledby="toc-label">
					<span class="cemeti-toc__label" id="toc-label"><?php echo esc_html( cemeti_t( 'toc' ) ); ?></span>
					<span class="cemeti-toc__links">
						<?php foreach ( $cemeti_toc as $cemeti_key => $cemeti_anchor ) : ?>
							<a href="#<?php echo esc_attr( $cemeti_anchor ); ?>"><?php echo esc_html( cemeti_t( 'biography' === $cemeti_key ? 'profile' : ( 'booklet' === $cemeti_key ? 'download_booklet' : $cemeti_key ) ) ); ?></a>
						<?php endforeach; ?>
					</span>
				</nav>
			<?php endif; ?>
			<a class="cemeti-back" href="<?php echo esc_url( cemeti_back_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( cemeti_t( 'back' ) ); ?></a>
		</div>

		<header class="cemeti-single__head">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'cemeti-poster', array( 'class' => 'cemeti-single__poster' ) ); ?>
			<?php endif; ?>
			<h1 class="cemeti-single__title"><?php echo esc_html( cemeti_title() ); ?></h1>
			<?php if ( '' !== $cemeti_meta ) : ?>
				<p class="cemeti-single__people"><?php echo esc_html( $cemeti_meta ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( '' !== $cemeti_content ) : ?>
			<div class="cemeti-single__body" id="foreword"><?php the_content(); ?></div>
			<?php cemeti_back_to_toc( $cemeti_has_back ); ?>
		<?php endif; ?>

		<?php if ( $cemeti_gallery ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'gallery' ), 'gallery' ); ?>
				<?php cemeti_render_photos( $cemeti_gallery, (string) cemeti_get_field( 'gallery_caption', $cemeti_id ) ); ?>
				<?php cemeti_back_to_toc( $cemeti_has_back ); ?>
			</section>
		<?php endif; ?>

		<?php if ( $cemeti_docs ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'documentation' ), 'documentation' ); ?>
				<?php cemeti_render_photos( $cemeti_docs, (string) cemeti_get_field( 'documentation_caption', $cemeti_id ) ); ?>
				<?php cemeti_back_to_toc( $cemeti_has_back ); ?>
			</section>
		<?php endif; ?>

		<?php if ( $cemeti_bios ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'profile' ), 'profile' ); ?>
				<?php cemeti_render_biographies( $cemeti_bios ); ?>
				<?php cemeti_back_to_toc( $cemeti_has_back ); ?>
			</section>
		<?php endif; ?>

		<?php if ( '' !== $cemeti_file ) : ?>
			<p class="cemeti-single__download" id="download">
				<a class="cemeti-btn cemeti-btn--inline cemeti-btn--download" href="<?php echo esc_url( $cemeti_file ); ?>" download rel="noopener"><?php echo esc_html( cemeti_t( 'download_booklet' ) ); ?></a>
			</p>
		<?php endif; ?>
		<?php cemeti_render_share( get_permalink(), get_the_title() ); ?>
	</div>
</article>
<?php
get_footer();
