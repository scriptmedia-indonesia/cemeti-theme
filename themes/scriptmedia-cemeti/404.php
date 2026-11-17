<?php
/**
 * 404 (derived: not in the design PDF).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="cemeti-container cemeti-notfound">
	<h1 class="cemeti-heading cemeti-ctx-yellow"><?php echo esc_html( cemeti_t( 'not_found' ) ); ?></h1>
	<p><?php echo esc_html( cemeti_t( 'not_found_text' ) ); ?></p>
	<p><a class="cemeti-btn cemeti-btn--inline" href="<?php echo esc_url( cemeti_home_url() ); ?>"><?php echo esc_html( cemeti_t( 'go_home' ) ); ?></a></p>
</div>
<?php
get_footer();
