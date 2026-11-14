<?php
/**
 * Archive pagination.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_posts_pagination(
	array(
		'mid_size'  => 1,
		'prev_text' => esc_html( cemeti_t( 'newer' ) ),
		'next_text' => esc_html( cemeti_t( 'older' ) ),
		'screen_reader_text' => esc_html( cemeti_t( 'pagination' ) ),
		'aria_label'         => esc_html( cemeti_t( 'pagination' ) ),
	)
);
