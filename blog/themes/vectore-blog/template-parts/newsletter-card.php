<?php
/**
 * The dark newsletter card.
 *
 * Used twice on a post: as the sticky right rail above 1180px, and as the
 * full-width CTA at the foot of the article below it. Only one is visible at a
 * time (single.css), so the two never compete.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$source = isset( $args['source'] ) ? $args['source'] : 'blog';
?>
<div class="v-nlcard">
	<h2 class="v-nlcard__title"><?php esc_html_e( 'Build a community worth joining', 'vectore-blog' ); ?></h2>
	<p class="v-nlcard__desc">
		<?php esc_html_e( 'What we are learning about courses, cohorts and the people around them. One email, roughly every other week.', 'vectore-blog' ); ?>
	</p>
	<?php vectore_blog_newsletter_form( $source ); ?>
	<p class="v-nl__note"><?php esc_html_e( 'No spam. Leave whenever you like.', 'vectore-blog' ); ?></p>
</div>
