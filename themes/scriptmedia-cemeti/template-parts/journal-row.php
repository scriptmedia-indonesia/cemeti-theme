<?php
/**
 * Row used by Journal, Open Call and search lists: date, title, category.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cemeti_date = 'journal' === get_post_type() || ! cemeti_date_keys( get_post_type() )[1]
	? get_the_date( 'd-m-Y' )
	: cemeti_get_date_range();
?>
<li class="cemeti-row">
	<a href="<?php the_permalink(); ?>">
		<span class="cemeti-row__date"><?php echo esc_html( $cemeti_date ); ?></span>
		<span class="cemeti-row__title"><?php the_title(); ?></span>
		<span class="cemeti-row__cat"><?php echo esc_html( cemeti_post_label( get_post() ) ); ?></span>
	</a>
</li>
