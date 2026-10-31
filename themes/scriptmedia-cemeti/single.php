<?php
/**
 * Detail page for Exhibition, Public Program, Open Studio and Residency.
 * Sections appear only when they have content; the table of contents links to them.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();

$cemeti_id      = get_the_ID();
$cemeti_type    = get_post_type();
$cemeti_dates   = cemeti_get_date_range( $cemeti_id );
$cemeti_artists = (string) cemeti_get_field( 'artists', $cemeti_id );
$cemeti_gallery = cemeti_get_field( 'gallery', $cemeti_id );
$cemeti_docs    = cemeti_get_field( 'documentation', $cemeti_id );
$cemeti_bios    = cemeti_get_field( 'biographies', $cemeti_id );
$cemeti_booklet = (string) cemeti_get_field( 'booklet_url', $cemeti_id );
$cemeti_related = cemeti_get_related( $cemeti_id );
$cemeti_content = trim( get_the_content() );

// Table of contents: only sections that exist.
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
	$cemeti_toc['biography'] = 'biography';
}
if ( '' !== $cemeti_booklet ) {
	$cemeti_toc['booklet'] = 'booklet';
}
$cemeti_has_toc = count( $cemeti_toc ) > 1;
?>
<article class="cemeti-single cemeti-single--<?php echo esc_attr( $cemeti_type ); ?>">
	<div class="cemeti-container">
		<div class="cemeti-single__nav">
			<?php if ( $cemeti_has_toc ) : ?>
				<nav class="cemeti-toc" id="toc" aria-label="<?php echo esc_attr( cemeti_t( 'toc' ) ); ?>">
					<span><?php echo esc_html( cemeti_t( 'toc' ) ); ?></span>
					<?php foreach ( $cemeti_toc as $cemeti_key => $cemeti_anchor ) : ?>
						<a href="#<?php echo esc_attr( $cemeti_anchor ); ?>"><?php echo esc_html( cemeti_t( $cemeti_key ) ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<a class="cemeti-back" href="<?php echo esc_url( cemeti_back_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( cemeti_t( 'back' ) ); ?></a>
		</div>

		<header class="cemeti-single__head">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'cemeti-poster', array( 'class' => 'cemeti-single__poster' ) ); ?>
			<?php endif; ?>
			<h1 class="cemeti-single__title"><?php the_title(); ?></h1>
			<?php if ( '' !== $cemeti_dates ) : ?>
				<p class="cemeti-single__date"><?php echo esc_html( $cemeti_dates ); ?></p>
			<?php endif; ?>
			<?php
			$cemeti_people = '' !== $cemeti_artists ? $cemeti_artists : (string) cemeti_get_field( 'speakers', $cemeti_id );
			if ( '' !== $cemeti_people ) :
				?>
				<p class="cemeti-single__people"><?php echo cemeti_multiline( $cemeti_people ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<?php endif; ?>
			<?php if ( '' !== (string) cemeti_get_field( 'time_text', $cemeti_id ) ) : ?>
				<p class="cemeti-single__time"><?php echo esc_html( cemeti_get_field( 'time_text', $cemeti_id ) ); ?></p>
			<?php endif; ?>
		</header>

		<?php
		$cemeti_info = array(
			'opening'        => (string) cemeti_get_field( 'opening', $cemeti_id ),
			'public_program' => (string) cemeti_get_field( 'public_program_info', $cemeti_id ),
			''               => (string) cemeti_get_field( 'venue_info', $cemeti_id ),
		);
		if ( array_filter( $cemeti_info ) ) :
			?>
			<div class="cemeti-single__info">
				<?php foreach ( $cemeti_info as $cemeti_key => $cemeti_text ) : ?>
					<?php if ( '' !== $cemeti_text ) : ?>
						<p>
							<?php if ( '' !== $cemeti_key ) : ?>
								<strong><?php echo esc_html( cemeti_t( $cemeti_key ) ); ?>:</strong><br>
							<?php endif; ?>
							<?php echo cemeti_multiline( $cemeti_text ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</p>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $cemeti_content ) : ?>
			<div class="cemeti-single__body" id="foreword"><?php the_content(); ?></div>
			<?php cemeti_back_to_toc( $cemeti_has_toc ); ?>
		<?php endif; ?>

		<?php if ( $cemeti_gallery ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'gallery' ), 'gallery' ); ?>
				<?php cemeti_render_photos( $cemeti_gallery, (string) cemeti_get_field( 'gallery_caption', $cemeti_id ) ); ?>
				<?php cemeti_back_to_toc( $cemeti_has_toc ); ?>
			</section>
		<?php endif; ?>

		<?php if ( $cemeti_docs ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'documentation' ), 'documentation' ); ?>
				<?php cemeti_render_photos( $cemeti_docs, (string) cemeti_get_field( 'documentation_caption', $cemeti_id ) ); ?>
				<?php cemeti_back_to_toc( $cemeti_has_toc ); ?>
			</section>
		<?php endif; ?>

		<?php if ( $cemeti_bios ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'biography' ), 'biography' ); ?>
				<?php cemeti_render_biographies( $cemeti_bios ); ?>
				<?php cemeti_back_to_toc( $cemeti_has_toc ); ?>
			</section>
		<?php endif; ?>

		<?php if ( '' !== $cemeti_booklet ) : ?>
			<section class="cemeti-single__section">
				<?php cemeti_section_heading( cemeti_t( 'booklet' ), 'booklet' ); ?>
				<p><a class="cemeti-btn cemeti-btn--inline" href="<?php echo esc_url( $cemeti_booklet ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( cemeti_t( 'booklet' ) ); ?> (PDF)</a></p>
				<?php cemeti_back_to_toc( $cemeti_has_toc ); ?>
			</section>
		<?php endif; ?>

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
