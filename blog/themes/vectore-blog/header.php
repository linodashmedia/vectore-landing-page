<?php
/**
 * The document head and the floating header.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#07211F">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="v-skip-link" href="#v-main"><?php esc_html_e( 'Skip to content', 'vectore-blog' ); ?></a>

<div class="v-shell">
	<header class="v-header">
		<div class="v-header__inner">
			<div class="v-header__bar">
				<a class="v-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<span class="v-header__brand-dot" aria-hidden="true"></span>
					<span>Vectore</span>
				</a>

				<button class="v-header__burger"
				        type="button"
				        aria-expanded="false"
				        aria-controls="v-header-nav"
				        aria-label="<?php esc_attr_e( 'Toggle menu', 'vectore-blog' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
						<path d="M4 7h16M4 12h16M4 17h16"/>
					</svg>
				</button>

				<nav class="v-header__nav" id="v-header-nav" aria-label="<?php esc_attr_e( 'Primary', 'vectore-blog' ); ?>">
					<?php
					if ( has_nav_menu( 'primary' ) ) {
						wp_nav_menu(
							array(
								'theme_location' => 'primary',
								'container'      => false,
								'menu_class'     => 'v-header__menu',
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
					} else {
						// No menu assigned yet: a sensible default so a fresh
						// install is never headerless.
						?>
						<ul class="v-header__menu">
							<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'All posts', 'vectore-blog' ); ?></a></li>
							<li><a href="<?php echo esc_url( VECTORE_SITE_URL ); ?>"><?php esc_html_e( 'Product', 'vectore-blog' ); ?></a></li>
						</ul>
						<?php
					}
					?>
					<a class="v-header__cta" href="<?php echo esc_url( VECTORE_SITE_URL . '/#waitlist' ); ?>">
						<?php esc_html_e( 'Join the waitlist', 'vectore-blog' ); ?>
					</a>
				</nav>

				<a class="v-header__cta" href="<?php echo esc_url( VECTORE_SITE_URL . '/#waitlist' ); ?>">
					<?php esc_html_e( 'Join the waitlist', 'vectore-blog' ); ?>
				</a>
			</div>
		</div>
	</header>

	<main class="v-main" id="v-main">
