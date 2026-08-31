<?php
/**
 * Theme supports, menus, image sizes.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'vectore_blog_setup' );
/**
 * Register everything WordPress needs to know about this theme.
 */
function vectore_blog_setup() {
	load_theme_textdomain( 'vectore-blog', VECTORE_BLOG_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	register_nav_menus(
		array(
			'primary' => __( 'Header menu', 'vectore-blog' ),
			'footer'  => __( 'Footer menu', 'vectore-blog' ),
		)
	);

	// The card grid is 16:9; the lead card and the post cover want something
	// wider. Both are hard crops so a mixed-aspect media library still produces
	// an even grid.
	add_image_size( 'vectore-card', 720, 405, true );
	add_image_size( 'vectore-cover', 1600, 900, true );
}

/**
 * The editor is styled with the same files as the front end, so a writer sees
 * the real article while writing it. This is why the palette lives in CSS
 * custom properties on :root rather than in per-view literals.
 */
add_action( 'after_setup_theme', 'vectore_blog_editor_styles' );
function vectore_blog_editor_styles() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	add_editor_style( 'assets/css/single.css' );
}

/**
 * WordPress emits an emoji detection script and stylesheet on every page. This
 * blog does not use them and they cost a request plus inline JS on a view that
 * is otherwise two stylesheets. Removed rather than deferred.
 */
add_action( 'init', 'vectore_blog_trim_head' );
function vectore_blog_trim_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	// The shortlink is a duplicate URL a crawler can follow; the canonical in
	// inc/seo.php is the single source of a post's address.
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}

/**
 * Excerpts: a readable two-line card summary, and an ellipsis rather than the
 * default bracketed hellip, which reads as markup in a card.
 */
add_filter( 'excerpt_length', function () { return 26; }, 20 );
add_filter( 'excerpt_more', function () { return '...'; } );

/**
 * Pagination link markup, so archive.css can style one class rather than
 * WordPress's default nested markup.
 */
function vectore_blog_pagination() {
	$links = paginate_links(
		array(
			'type'      => 'array',
			'mid_size'  => 1,
			'prev_text' => __( 'Previous', 'vectore-blog' ),
			'next_text' => __( 'Next', 'vectore-blog' ),
		)
	);

	if ( empty( $links ) ) {
		return;
	}

	echo '<nav class="v-pagination" aria-label="' . esc_attr__( 'Posts', 'vectore-blog' ) . '">';
	foreach ( $links as $link ) {
		echo wp_kses_post( $link );
	}
	echo '</nav>';
}
