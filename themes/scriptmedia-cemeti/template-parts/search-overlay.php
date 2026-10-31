<?php
/**
 * Header search bar (yellow strip under the header).
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cemeti-search" id="cemeti-search" hidden>
	<form role="search" method="get" class="cemeti-search__form" action="<?php echo esc_url( cemeti_home_url() ); ?>">
		<label class="screen-reader-text" for="cemeti-search-input"><?php echo esc_html( cemeti_t( 'search_label' ) ); ?></label>
		<input type="search" id="cemeti-search-input" name="s" value="<?php echo esc_attr( get_search_query( false ) ); ?>" placeholder="<?php echo esc_attr( cemeti_t( 'search_placeholder' ) ); ?>" autocomplete="off">
		<button type="submit" class="cemeti-search__submit"><?php echo esc_html( cemeti_t( 'search' ) ); ?></button>
		<button type="button" class="cemeti-search__close" data-cemeti-close="search" aria-label="<?php echo esc_attr( cemeti_t( 'close' ) ); ?>">X</button>
	</form>
</div>
