<?php
/**
 * "What's on" full-screen yellow panel. Content comes from cemeti_whats_on_items(),
 * which Cemeti content code (M2) feeds with the current Exhibition, Open Studio
 * and Residency. Until then the panel lists nothing.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters the entries shown in the What's on panel.
 *
 * @param array<int, array{label:string,title:string,meta:string,url:string}> $items Entries.
 */
$cemeti_items  = apply_filters( 'cemeti_whats_on_items', array() );
$cemeti_splash = is_front_page() && $cemeti_items && '1' !== (string) cemeti_option( 'hide_splash' );
?>
<div class="cemeti-whats-on" id="cemeti-whats-on" role="dialog" aria-modal="true" aria-labelledby="cemeti-whats-on-title"<?php echo $cemeti_splash ? ' data-cemeti-splash="1"' : ''; ?> hidden>
	<div class="cemeti-whats-on__top">
		<button type="button" class="cemeti-script cemeti-whats-on__close" data-cemeti-close="whats-on"><?php echo esc_html( cemeti_t( 'close' ) ); ?></button>
	</div>
	<div class="cemeti-whats-on__bg" aria-hidden="true"><img src="<?php echo esc_url( CEMETI_URI . '/assets/img/logogram.png' ); ?>" alt="" width="860" height="823"></div>
	<div class="cemeti-whats-on__bar">
		<h2 id="cemeti-whats-on-title" class="cemeti-script"><?php echo esc_html( cemeti_t( 'whats_on' ) ); ?></h2>
	</div>
	<ul class="cemeti-whats-on__list cemeti-script">
		<?php foreach ( $cemeti_items as $cemeti_item ) : ?>
			<li>
				<a href="<?php echo esc_url( $cemeti_item['url'] ); ?>">
					<?php
					$cemeti_people = isset( $cemeti_item['people'] ) ? (string) $cemeti_item['people'] : '';
					$cemeti_both   = isset( $cemeti_item['type'] ) && 'exhibition' === $cemeti_item['type']; // The exhibition shows its title and its artists; the others the names.
					?>
					<span><?php echo esc_html( $cemeti_item['label'] ); ?>:</span>
					<?php if ( $cemeti_both || '' === $cemeti_people ) : ?>
						<span><?php echo esc_html( $cemeti_item['title'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $cemeti_people ) : ?>
						<span><?php echo esc_html( $cemeti_people ); ?></span>
					<?php endif; ?>
					<span><?php echo esc_html( $cemeti_item['meta'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
