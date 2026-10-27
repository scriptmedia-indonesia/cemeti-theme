<?php
/**
 * Overlay menu: black panel with two columns, address and route link.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cemeti_menu    = cemeti_default_menu();
$cemeti_route   = cemeti_validate_maps_url( cemeti_option( 'route_url' ) );
$cemeti_address = cemeti_option( 'address' );
?>
<nav class="cemeti-menu" id="cemeti-menu" aria-label="<?php echo esc_attr( cemeti_t( 'main_navigation' ) ); ?>" hidden>
	<div class="cemeti-menu__inner">
		<div class="cemeti-menu__col">
			<?php cemeti_render_menu_items( $cemeti_menu['left'] ); ?>
		</div>
		<div class="cemeti-menu__col">
			<?php cemeti_render_menu_items( $cemeti_menu['right'] ); ?>
			<p class="cemeti-menu__address"><?php echo esc_html( str_replace( "\n", ' ', $cemeti_address ) ); ?></p>
			<?php if ( $cemeti_route ) : ?>
				<a class="cemeti-menu__route" href="<?php echo esc_url( $cemeti_route ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( cemeti_t( 'plan_route' ) ); ?> &gt;&gt;</a>
			<?php endif; ?>
			<button type="button" class="cemeti-menu__whats-on" data-cemeti-toggle="whats-on" aria-controls="cemeti-whats-on" aria-expanded="false"><?php echo esc_html( cemeti_t( 'whats_on' ) ); ?></button>
		</div>
	</div>
</nav>
