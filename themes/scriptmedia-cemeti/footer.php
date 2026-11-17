<?php
/**
 * Site footer: yellow block with Site Settings data.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cemeti_address = cemeti_option( 'address' );
$cemeti_route   = cemeti_validate_maps_url( cemeti_option( 'route_url' ) );
$cemeti_email   = cemeti_option( 'email' );
$cemeti_socials = array(
	'instagram' => cemeti_option( 'instagram_url' ),
	'youtube'   => cemeti_option( 'youtube_url' ),
	'facebook'  => cemeti_option( 'facebook_url' ),
);
?>
</main>

<footer class="cemeti-footer">
	<div class="cemeti-footer__inner">
		<div class="cemeti-footer__info">
			<p class="cemeti-footer__name"><?php echo esc_html( cemeti_option( 'institution_name' ) ); ?></p>
			<address class="cemeti-footer__address">
				<?php
				$cemeti_address_html = nl2br( esc_html( $cemeti_address ) );
				if ( $cemeti_route ) {
					printf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $cemeti_route ), wp_kses( $cemeti_address_html, array( 'br' => array() ) ) );
				} else {
					echo wp_kses( $cemeti_address_html, array( 'br' => array() ) );
				}
				?>
			</address>
			<p class="cemeti-footer__hours"><?php echo wp_kses( nl2br( esc_html( cemeti_option( 'opening_hours' ) ) ), array( 'br' => array() ) ); ?></p>
			<?php if ( is_email( $cemeti_email ) ) : ?>
				<p><a href="<?php echo esc_url( 'mailto:' . $cemeti_email ); ?>"><?php echo esc_html( $cemeti_email ); ?></a></p>
			<?php endif; ?>
		</div>

		<ul class="cemeti-footer__social">
			<?php foreach ( $cemeti_socials as $cemeti_key => $cemeti_url ) : ?>
				<?php if ( $cemeti_url ) : ?>
					<li><a href="<?php echo esc_url( $cemeti_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( cemeti_t( $cemeti_key ) ); ?></a></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
