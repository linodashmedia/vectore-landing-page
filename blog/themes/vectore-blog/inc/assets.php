<?php
/**
 * Stylesheets and scripts.
 *
 * Loading is CONDITIONAL on purpose. The blog index never pays for the
 * single-post stylesheet and a post never pays for the archive grid. Every
 * file is versioned by filemtime, so a deploy busts the cache without anyone
 * having to remember to bump a number.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * filemtime as the asset version.
 *
 * @param string $rel Path relative to the theme root.
 * @return string
 */
function vectore_blog_ver( $rel ) {
	$path = VECTORE_BLOG_DIR . '/' . ltrim( $rel, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : VECTORE_BLOG_VERSION;
}

add_action( 'wp_enqueue_scripts', 'vectore_blog_assets' );
function vectore_blog_assets() {
	// Fonts. Two families, both variable, both preconnected. Display is
	// Bricolage Grotesque (the wordmark and every heading); body is Figtree,
	// which is what the Vectore marketing site already uses, so the two
	// properties read as one brand.
	wp_enqueue_style(
		'vectore-fonts',
		'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700&family=Figtree:ital,wght@0,300..900;1,300..700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'vectore-blog',
		get_stylesheet_uri(),
		array( 'vectore-fonts' ),
		vectore_blog_ver( 'style.css' )
	);

	// Cards load EVERYWHERE, on purpose. A single post renders the card grid in
	// its related-posts section and so does the 404, so gating this on
	// ! is_singular() would leave those two views with unstyled cards.
	wp_enqueue_style(
		'vectore-cards',
		VECTORE_BLOG_URI . '/assets/css/cards.css',
		array( 'vectore-blog' ),
		vectore_blog_ver( 'assets/css/cards.css' )
	);

	// The reading view is the one thing that genuinely is conditional: the
	// three-column grid, the rails and the article typography are dead weight
	// on an index page.
	if ( is_singular() ) {
		wp_enqueue_style(
			'vectore-single',
			VECTORE_BLOG_URI . '/assets/css/single.css',
			array( 'vectore-cards' ),
			vectore_blog_ver( 'assets/css/single.css' )
		);
		wp_enqueue_script(
			'vectore-single',
			VECTORE_BLOG_URI . '/assets/js/single-post.js',
			array(),
			vectore_blog_ver( 'assets/js/single-post.js' ),
			true
		);
	}

	wp_enqueue_script(
		'vectore-header',
		VECTORE_BLOG_URI . '/assets/js/header.js',
		array(),
		vectore_blog_ver( 'assets/js/header.js' ),
		true
	);

	wp_enqueue_script(
		'vectore-newsletter',
		VECTORE_BLOG_URI . '/assets/js/newsletter-form.js',
		array(),
		vectore_blog_ver( 'assets/js/newsletter-form.js' ),
		true
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

/**
 * Preconnect to the font CDN. Without this the browser does not open the
 * gstatic connection until it has parsed the stylesheet that references it,
 * which puts a full round trip in front of the first paint of any heading.
 */
add_filter( 'wp_resource_hints', 'vectore_blog_resource_hints', 10, 2 );
function vectore_blog_resource_hints( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous' );
	}
	return $hints;
}
