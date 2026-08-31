<?php
/**
 * A static page. The reading view minus the byline, the rails and the share
 * row: a page is a destination, not an article with a publication date.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<div class="v-canvas v-single">
		<article <?php post_class( 'v-article' ); ?>>

			<?php vectore_blog_breadcrumbs(); ?>

			<header class="v-single__hero">
				<h1 class="v-single__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="v-single__lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</header>

			<div class="v-content">
				<?php the_content(); ?>
			</div>

		</article>
	</div>

	<?php
endwhile;

get_footer();
