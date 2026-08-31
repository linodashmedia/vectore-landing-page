<?php
/**
 * The blog index, and the fallback for any view without its own template.
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
		<span class="v-head__eyebrow"><?php esc_html_e( 'The Vectore blog', 'vectore-blog' ); ?></span>
		<h1 class="v-head__title"><?php bloginfo( 'name' ); ?></h1>
		<?php if ( get_bloginfo( 'description' ) ) : ?>
			<p class="v-head__sub"><?php bloginfo( 'description' ); ?></p>
		<?php endif; ?>
	</div>

	<?php
	$terms = get_categories( array( 'hide_empty' => true, 'number' => 8 ) );
	if ( ! empty( $terms ) ) :
		?>
		<ul class="v-terms">
			<li class="<?php echo is_home() ? 'is-current' : ''; ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
				   <?php echo is_home() ? 'aria-current="page"' : ''; ?>>
					<?php esc_html_e( 'All', 'vectore-blog' ); ?>
				</a>
			</li>
			<?php foreach ( $terms as $term ) : ?>
				<li><a href="<?php echo esc_url( get_category_link( $term->term_id ) ); ?>"><?php echo esc_html( $term->name ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="v-grid v-grid--home">
			<?php
			$i = 0;
			while ( have_posts() ) :
				the_post();
				// The newest post on the first page leads: it spans the grid and
				// lays out side by side above 900px. On page two and beyond every
				// card is equal, because "lead" only means anything next to the
				// posts it was published before.
				$variant = ( 0 === $i && ! is_paged() ) ? 'v-card--lead' : '';
				vectore_blog_card( $variant );
				$i++;
			endwhile;
			?>
		</div>

		<?php vectore_blog_pagination(); ?>

	<?php else : ?>
		<div class="v-empty">
			<h2><?php esc_html_e( 'Nothing here yet', 'vectore-blog' ); ?></h2>
			<p><?php esc_html_e( 'The first posts are on their way. Check back shortly.', 'vectore-blog' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
