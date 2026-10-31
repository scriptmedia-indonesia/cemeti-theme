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

$cemeti_sections = array( 'exhibition', 'residency', 'public_program', 'open_studio' );
?>
<div class="cemeti-container cemeti-home">
	<h1 class="screen-reader-text"><?php echo esc_html( cemeti_option( 'institution_name' ) ); ?></h1>
	<?php
	foreach ( $cemeti_sections as $cemeti_type ) :
		$cemeti_post = cemeti_featured_post( $cemeti_type );
		$cemeti_obj  = get_post_type_object( $cemeti_type );
		if ( ! $cemeti_post || ! $cemeti_obj ) {
			continue;
		}
		$cemeti_people = (string) cemeti_get_field( 'artists', $cemeti_post->ID );
		if ( '' === $cemeti_people ) {
			$cemeti_people = (string) cemeti_get_field( 'speakers', $cemeti_post->ID );
		}
		$cemeti_residency = 'residency' === $cemeti_type;
		?>
		<section class="cemeti-home__section cemeti-home__section--<?php echo esc_attr( $cemeti_type ); ?>">
			<h2 class="cemeti-heading cemeti-ctx-home"><a href="<?php echo esc_url( get_post_type_archive_link( $cemeti_type ) ); ?>"><?php echo esc_html( $cemeti_obj->labels->singular_name ); ?></a></h2>
			<div class="cemeti-home__item">
				<div class="cemeti-home__poster">
					<a href="<?php echo esc_url( get_permalink( $cemeti_post ) ); ?>" tabindex="-1" aria-hidden="true">
						<?php echo get_the_post_thumbnail( $cemeti_post, 'cemeti-card', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
					<a class="cemeti-btn" href="<?php echo esc_url( get_post_type_archive_link( $cemeti_type ) ); ?>">Previous <?php echo esc_html( $cemeti_obj->labels->singular_name ); ?></a>
				</div>
				<div class="cemeti-home__text">
					<h3 class="cemeti-home__title"><a href="<?php echo esc_url( get_permalink( $cemeti_post ) ); ?>"><?php echo esc_html( get_the_title( $cemeti_post ) ); ?></a></h3>
					<?php if ( $cemeti_residency ) : ?>
						<?php echo wp_kses_post( wpautop( wp_trim_words( get_the_excerpt( $cemeti_post ), 80 ) ) ); ?>
						<p class="cemeti-home__current cemeti-script">
							<?php echo esc_html( cemeti_t( 'current_residence' ) ); ?>:<br>
							<?php echo cemeti_multiline( str_replace( "\n", ', ', trim( $cemeti_people ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><br>
							<?php echo esc_html( cemeti_get_date_range( $cemeti_post->ID ) ); ?>
						</p>
					<?php else : ?>
						<?php if ( '' !== $cemeti_people && 'public_program' !== $cemeti_type ) : ?>
							<p class="cemeti-home__people"><?php echo esc_html( str_replace( "\n", ', ', trim( $cemeti_people ) ) ); ?></p>
						<?php endif; ?>
						<p class="cemeti-home__date"><?php echo esc_html( cemeti_get_date_range( $cemeti_post->ID ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</section>
	<?php endforeach; ?>

	<?php
	$cemeti_journal = new WP_Query( array( 'post_type' => 'journal', 'posts_per_page' => 3, 'no_found_rows' => true ) );
	if ( $cemeti_journal->have_posts() ) :
		?>
		<section class="cemeti-home__section cemeti-home__section--journal">
			<h2 class="cemeti-heading cemeti-ctx-home"><a href="<?php echo esc_url( get_post_type_archive_link( 'journal' ) ); ?>">Journal</a></h2>
			<ul class="cemeti-row-list">
				<?php
				while ( $cemeti_journal->have_posts() ) {
					$cemeti_journal->the_post();
					get_template_part( 'template-parts/journal-row' );
				}
				wp_reset_postdata();
				?>
			</ul>
			<a class="cemeti-btn cemeti-btn--inline" href="<?php echo esc_url( get_post_type_archive_link( 'journal' ) ); ?>">Previous Journal</a>
		</section>
	<?php endif; ?>

	<?php if ( have_posts() && is_page() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<div class="cemeti-single__body"><?php the_content(); ?></div>
		<?php endwhile; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
