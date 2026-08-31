<?php
/**
 * A single post — the reading view.
 *
 * Layout note: the hero, both rails and the content are SIBLINGS inside one
 * <article>, which becomes a three-column grid above 1180px (see single.css).
 * The rails are placed in the markup where they should appear when the grid is
 * NOT active: the TOC before the article body, the newsletter after it. That
 * ordering is what makes the mobile view correct without a second template.
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

				<?php vectore_blog_byline(); ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="v-single__cover">
					<?php
					the_post_thumbnail(
						'vectore-cover',
						array(
							'fetchpriority' => 'high',
							'alt'           => the_title_attribute( array( 'echo' => false ) ),
						)
					);
					?>
				</figure>
			<?php endif; ?>

			<?php
			// Left rail. The list itself is built by single-post.js from the
			// post's own <h2>s, which is why the container ships hidden: a post
			// with fewer than two headings gets no TOC and no empty box.
			?>
			<aside class="v-rail v-rail--left">
				<nav class="v-toc" data-v-toc hidden aria-label="<?php esc_attr_e( 'On this page', 'vectore-blog' ); ?>">
					<p class="v-toc__title"><?php esc_html_e( 'On this page', 'vectore-blog' ); ?></p>
				</nav>
				<?php vectore_blog_share(); ?>
			</aside>

			<div class="v-content">
				<?php the_content(); ?>
				<?php
				wp_link_pages(
					array(
						'before' => '<nav class="v-pagination">',
						'after'  => '</nav>',
					)
				);
				?>
			</div>

			<?php
			$tags = get_the_tags();
			if ( ! empty( $tags ) ) :
				?>
				<ul class="v-tags">
					<?php foreach ( $tags as $tag ) : ?>
						<li><a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>">#<?php echo esc_html( $tag->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php vectore_blog_author_card(); ?>

			<?php // Right rail on desktop; the same card reappears below as the mobile CTA. ?>
			<aside class="v-rail v-rail--right">
				<?php get_template_part( 'template-parts/newsletter-card', null, array( 'source' => 'blog_rail' ) ); ?>
			</aside>

			<div class="v-nlcta">
				<?php get_template_part( 'template-parts/newsletter-card', null, array( 'source' => 'blog_footer' ) ); ?>
			</div>

			<?php get_template_part( 'template-parts/adjacent' ); ?>

		</article>

		<?php get_template_part( 'template-parts/related' ); ?>
	</div>

	<?php
endwhile;

get_footer();
