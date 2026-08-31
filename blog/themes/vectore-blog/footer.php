<?php
/**
 * The footer.
 *
 * 🔴 The footer has exactly ONE source: this template plus the footer block in
 * style.css. Footer links resolve through the 'footer' menu when one is
 * assigned and fall back to the marketing site's own pages otherwise, so the
 * footer can never point at a 404 on a fresh install.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	</main><!-- .v-main -->

	<footer class="v-footer">
		<div class="v-footer__inner">
			<p class="v-footer__copy">
				<?php
				printf(
					/* translators: 1: current year, 2: site name. */
					esc_html__( '(c) %1$s %2$s', 'vectore-blog' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</p>

			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'v-footer__nav',
						'depth'          => 1,
						'fallback_cb'    => false,
					)
				);
			} else {
				?>
				<ul class="v-footer__nav">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Blog', 'vectore-blog' ); ?></a></li>
					<li><a href="<?php echo esc_url( VECTORE_SITE_URL ); ?>"><?php esc_html_e( 'Product', 'vectore-blog' ); ?></a></li>
					<li><a href="<?php echo esc_url( VECTORE_SITE_URL . '/contact' ); ?>"><?php esc_html_e( 'Contact', 'vectore-blog' ); ?></a></li>
					<li><a href="<?php echo esc_url( VECTORE_SITE_URL . '/privacy' ); ?>"><?php esc_html_e( 'Privacy', 'vectore-blog' ); ?></a></li>
					<li><a href="<?php echo esc_url( VECTORE_SITE_URL . '/terms' ); ?>"><?php esc_html_e( 'Terms', 'vectore-blog' ); ?></a></li>
				</ul>
				<?php
			}
			?>
		</div>

		<?php // Decorative: real text so it stays crisp at any zoom, but it carries no meaning a screen reader needs. ?>
		<div class="v-footer__mark" aria-hidden="true"><span>VECTORE</span></div>
	</footer>
</div><!-- .v-shell -->

<?php wp_footer(); ?>
</body>
</html>
