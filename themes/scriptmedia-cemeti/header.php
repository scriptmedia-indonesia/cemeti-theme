<?php
/**
 * Site header: sticky bar, overlay menu, search bar and "What's on" panel.
 *
 * @package Scriptmedia_Cemeti
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="cemeti-skip-link" href="#content"><?php echo esc_html( cemeti_t( 'skip_to_content' ) ); ?></a>

<header class="cemeti-header" id="site-header">
	<div class="cemeti-header__bar">
		<button type="button" class="cemeti-menu-toggle" aria-expanded="false" aria-controls="cemeti-menu" data-cemeti-toggle="menu">
			<span class="cemeti-menu-toggle__icon" aria-hidden="true"></span>
			<span class="cemeti-menu-toggle__label"><?php echo esc_html( cemeti_t( 'menu' ) ); ?></span>
		</button>

		<a class="cemeti-logo" href="<?php echo esc_url( cemeti_home_url() ); ?>" aria-label="<?php echo esc_attr( cemeti_option( 'institution_name' ) ); ?>">
			<img src="<?php echo esc_url( CEMETI_URI . '/assets/img/logo-480.webp' ); ?>" srcset="<?php echo esc_url( CEMETI_URI . '/assets/img/logo-480.webp' ); ?> 480w, <?php echo esc_url( CEMETI_URI . '/assets/img/logo-960.webp' ); ?> 960w" sizes="(min-width: 64rem) 14rem, 30vw" width="480" height="205" fetchpriority="high" alt="<?php echo esc_attr( cemeti_option( 'institution_name' ) ); ?>">
		</a>

		<div class="cemeti-header__tools">
			<nav class="cemeti-lang" aria-label="<?php echo esc_attr( cemeti_t( 'language' ) ); ?>">
				<?php
				$cemeti_langs = cemeti_language_switcher_items();
				foreach ( $cemeti_langs as $cemeti_i => $cemeti_lang ) {
					if ( $cemeti_i ) {
						echo '<span class="cemeti-lang__sep" aria-hidden="true">/</span>';
					}
					printf(
						'<a href="%1$s" hreflang="%2$s" lang="%2$s"%3$s>%4$s</a>',
						esc_url( $cemeti_lang['url'] ),
						esc_attr( $cemeti_lang['code'] ),
						$cemeti_lang['current'] ? ' aria-current="true"' : '',
						esc_html( $cemeti_lang['label'] )
					);
				}
				unset( $cemeti_langs, $cemeti_i, $cemeti_lang );
				?>
			</nav>
			<button type="button" class="cemeti-search-toggle" aria-expanded="false" aria-controls="cemeti-search" data-cemeti-toggle="search" aria-label="<?php echo esc_attr( cemeti_t( 'search' ) ); ?>">
				<svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="5.5" fill="none" stroke="currentColor" stroke-width="3"/><path d="M12.5 12.5 18 18" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/></svg>
			</button>
		</div>
	</div>

	<?php get_template_part( 'template-parts/search-overlay' ); ?>
	<?php get_template_part( 'template-parts/menu-overlay' ); ?>
</header>

<?php get_template_part( 'template-parts/whats-on' ); ?>

<main id="content" class="cemeti-main" tabindex="-1">
