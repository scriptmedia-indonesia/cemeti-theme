<?php
/**
 * Open Archive: every program title, A–Z, in two columns (A–M and N–Z).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
the_post();
list( $cemeti_left, $cemeti_right ) = cemeti_split_az( cemeti_group_az( cemeti_archive_entries() ) );
?>
<article class="cemeti-single cemeti-single--open-archive">
	<div class="cemeti-container">
		<?php cemeti_page_head( get_the_title() ); ?>
		<div class="cemeti-single__body"><?php the_content(); ?></div>
		<div class="cemeti-az">
			<?php foreach ( array( $cemeti_left, $cemeti_right ) as $cemeti_column ) : ?>
				<div class="cemeti-az__col">
					<?php foreach ( $cemeti_column as $cemeti_letter => $cemeti_items ) : ?>
						<section>
							<h2 class="cemeti-az__letter"><?php echo esc_html( $cemeti_letter ); ?></h2>
							<ul>
								<?php foreach ( $cemeti_items as $cemeti_item ) : ?>
									<li><a href="<?php echo esc_url( $cemeti_item['url'] ); ?>">“<?php echo esc_html( $cemeti_item['title'] ); ?>”</a></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</article>
<?php
get_footer();
