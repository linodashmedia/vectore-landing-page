<?php
/**
 * Category, tag, author and date archives.
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
		<span class="v-head__eyebrow">
			<?php
			if ( is_category() ) {
				esc_html_e( 'Category', 'vectore-blog' );
			} elseif ( is_tag() ) {
				esc_html_e( 'Tag', 'vectore-blog' );
			} elseif ( is_author() ) {
				esc_html_e( 'Author', 'vectore-blog' );
			} else {
				esc_html_e( 'Archive', 'vectore-blog' );
			}
			?>
		</span>
		<h1 class="v-head__title"><?php echo esc_html( get_the_archive_title() ); ?></h1>
		<?php
		$description = get_the_archive_description();
		if ( $description ) :
			?>
			<div class="v-head__sub"><?php echo wp_kses_post( $description ); ?></div>
		<?php endif; ?>
	</div>

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
			<h2><?php esc_html_e( 'No posts here yet', 'vectore-blog' ); ?></h2>
			<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to all posts', 'vectore-blog' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
