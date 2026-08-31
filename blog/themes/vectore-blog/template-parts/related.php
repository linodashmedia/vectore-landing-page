<?php
/**
 * Related posts: up to three more from the same category.
 *
 * Falls back to nothing rather than to "most recent" when the category is thin.
 * A related list that is not actually related is worse than no list.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cats = wp_get_post_categories( get_the_ID() );

if ( empty( $cats ) ) {
	return;
}

$related = new WP_Query(
	array(
		'category__in'        => $cats,
		'post__not_in'        => array( get_the_ID() ),
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);

if ( ! $related->have_posts() ) {
	return;
}
?>
<section class="v-related">
	<h2 class="v-related__title"><?php esc_html_e( 'Keep reading', 'vectore-blog' ); ?></h2>
	<div class="v-grid">
		<?php
		while ( $related->have_posts() ) :
			$related->the_post();
			vectore_blog_card();
		endwhile;
		wp_reset_postdata();
		?>
	</div>
</section>
