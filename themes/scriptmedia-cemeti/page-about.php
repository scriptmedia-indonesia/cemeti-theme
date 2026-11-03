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
$cemeti_by_group = cemeti_people_by_group();
?>
<article class="cemeti-single cemeti-single--about">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title(), 'yellow' ); ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<?php the_post_thumbnail( 'cemeti-hero', array( 'class' => 'cemeti-single__hero' ) ); ?>
		<?php endif; ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>

		<?php foreach ( $cemeti_groups as $cemeti_slug => $cemeti_key ) : ?>
			<?php if ( ! $cemeti_by_group[ $cemeti_slug ] ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<section class="cemeti-people cemeti-people--<?php echo esc_attr( $cemeti_slug ); ?>">
				<h2 class="cemeti-people__heading"><?php echo esc_html( cemeti_t( $cemeti_key ) ); ?></h2>
				<?php if ( 'team' === $cemeti_slug ) : ?>
					<?php /* Operational team: each role in bold, its names under it, centred between two rules. */ ?>
					<div class="cemeti-team">
						<?php foreach ( cemeti_group_people_by_role( $cemeti_by_group[ $cemeti_slug ] ) as $cemeti_role ) : ?>
							<p class="cemeti-team__role">
								<?php if ( '' !== $cemeti_role['role'] ) : ?>
									<strong><?php echo esc_html( $cemeti_role['role'] ); ?></strong>
								<?php endif; ?>
								<?php foreach ( $cemeti_role['names'] as $cemeti_name ) : ?>
									<span><?php echo esc_html( $cemeti_name ); ?></span>
								<?php endforeach; ?>
							</p>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<ul class="cemeti-people__grid">
						<?php foreach ( $cemeti_by_group[ $cemeti_slug ] as $cemeti_person ) : ?>
							<li>
								<?php if ( has_post_thumbnail( $cemeti_person->ID ) ) : ?>
									<?php echo get_the_post_thumbnail( $cemeti_person, 'cemeti-card', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php endif; ?>
								<span class="cemeti-people__text">
									<strong><?php echo esc_html( get_the_title( $cemeti_person ) ); ?></strong>
									<?php if ( '' !== (string) cemeti_get_field( 'role', $cemeti_person->ID ) ) : ?>
										<span class="cemeti-people__role"><?php echo esc_html( cemeti_get_field( 'role', $cemeti_person->ID ) ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== (string) cemeti_get_field( 'quote', $cemeti_person->ID ) ) : ?>
										<span class="cemeti-people__quote"><?php echo esc_html( cemeti_get_field( 'quote', $cemeti_person->ID ) ); ?></span>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
</article>
<?php
get_footer();
