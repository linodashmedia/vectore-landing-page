<?php
/**
 * Search results.
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
		<span class="v-head__eyebrow"><?php esc_html_e( 'Search', 'vectore-blog' ); ?></span>
		<h1 class="v-head__title">
			<?php
			printf(
				/* translators: %s: the search term. */
				esc_html__( 'Results for "%s"', 'vectore-blog' ),
				esc_html( get_search_query() )
			);
			?>
		</h1>
	</div>

	<?php get_search_form(); ?>

	<?php if ( have_posts() ) : ?>
		<div class="v-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				vectore_blog_card();
			endwhile;
			?>
		</div>
		<?php vectore_blog_pagination(); ?>
	<?php else : ?>
		<div class="v-empty">
			<h2><?php esc_html_e( 'Nothing matched', 'vectore-blog' ); ?></h2>
			<p><?php esc_html_e( 'Try a different word, or browse everything we have published.', 'vectore-blog' ); ?></p>
			<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'All posts', 'vectore-blog' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
