<?php
/**
 * About: photo, text, then Advisory Board and Team (People entries by group).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();

$cemeti_groups = array(
	'advisory-board' => 'advisory_board',
	'team'           => 'team',
);
?>
<article class="cemeti-single cemeti-single--about">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title(), 'yellow' ); ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'cemeti-hero', array( 'class' => 'cemeti-single__hero' ) ); ?>
		<?php endif; ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>

		<?php
		foreach ( $cemeti_groups as $cemeti_slug => $cemeti_key ) :
			$cemeti_people = new WP_Query(
				array(
					'post_type'      => 'person',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
					'tax_query'      => array( array( 'taxonomy' => 'person_group', 'field' => 'slug', 'terms' => $cemeti_slug ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);
			if ( ! $cemeti_people->have_posts() ) {
				continue;
			}
			?>
			<section class="cemeti-people cemeti-people--<?php echo esc_attr( $cemeti_slug ); ?>">
				<h2 class="cemeti-people__heading"><?php echo esc_html( cemeti_t( $cemeti_key ) ); ?></h2>
				<ul class="cemeti-people__grid">
					<?php
					while ( $cemeti_people->have_posts() ) :
						$cemeti_people->the_post();
						?>
						<li>
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'cemeti-card', array( 'loading' => 'lazy' ) ); ?>
							<?php endif; ?>
							<span class="cemeti-people__text">
								<strong><?php the_title(); ?></strong>
								<?php if ( '' !== (string) cemeti_get_field( 'role', get_the_ID() ) ) : ?>
									<span class="cemeti-people__role"><?php echo esc_html( cemeti_get_field( 'role', get_the_ID() ) ); ?></span>
								<?php endif; ?>
								<?php if ( '' !== (string) cemeti_get_field( 'quote', get_the_ID() ) ) : ?>
									<span class="cemeti-people__quote"><?php echo esc_html( cemeti_get_field( 'quote', get_the_ID() ) ); ?></span>
								<?php endif; ?>
							</span>
						</li>
					<?php endwhile; ?>
				</ul>
			</section>
			<?php wp_reset_postdata(); ?>
		<?php endforeach; ?>
	</div>
</article>
<?php
get_footer();
