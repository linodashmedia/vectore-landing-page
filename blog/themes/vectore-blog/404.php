<?php
/**
 * 404.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="v-canvas">
	<div class="v-head">
		<span class="v-head__eyebrow"><?php esc_html_e( 'Error 404', 'vectore-blog' ); ?></span>
		<h1 class="v-head__title"><?php esc_html_e( 'That page has moved on', 'vectore-blog' ); ?></h1>
		<p class="v-head__sub"><?php esc_html_e( 'The link is broken or the post was renamed. Search for it, or start from the top.', 'vectore-blog' ); ?></p>
	</div>

	<?php get_search_form(); ?>

	<?php
	$recent = new WP_Query( array( 'posts_per_page' => 3, 'ignore_sticky_posts' => true ) );
	if ( $recent->have_posts() ) :
		?>
		<div class="v-grid">
			<?php
			while ( $recent->have_posts() ) :
				$recent->the_post();
				vectore_blog_card();
			endwhile;
			wp_reset_postdata();
			?>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
