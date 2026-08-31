<?php
/**
 * Previous / next post links.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prev = get_previous_post();
$next = get_next_post();

if ( ! $prev && ! $next ) {
	return;
}
?>
<nav class="v-adjacent" aria-label="<?php esc_attr_e( 'More posts', 'vectore-blog' ); ?>">
	<?php if ( $prev ) : ?>
		<a class="v-adjacent--prev" href="<?php echo esc_url( get_permalink( $prev ) ); ?>">
			<span class="v-adjacent__label"><?php esc_html_e( 'Previous', 'vectore-blog' ); ?></span>
			<span class="v-adjacent__title"><?php echo esc_html( get_the_title( $prev ) ); ?></span>
		</a>
	<?php endif; ?>

	<?php if ( $next ) : ?>
		<a class="v-adjacent--next" href="<?php echo esc_url( get_permalink( $next ) ); ?>">
			<span class="v-adjacent__label"><?php esc_html_e( 'Next', 'vectore-blog' ); ?></span>
			<span class="v-adjacent__title"><?php echo esc_html( get_the_title( $next ) ); ?></span>
		</a>
	<?php endif; ?>
</nav>
