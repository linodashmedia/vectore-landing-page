<?php
/**
 * /blog/llms.txt — a plain-text map of the blog for AI clients.
 *
 * The convention (llmstxt.org) is a Markdown file at a well-known path that
 * says, in about a screenful, what a site is and what is worth reading on it.
 * It is not a ranking signal and nobody should pretend it is. What it does is
 * cheaper and more concrete: an agent that lands here gets the title, the
 * summary, the sections and the current posts without crawling and parsing
 * fifteen HTML pages, and it gets the LAST UPDATED date for each one, which is
 * the single most common thing an answer engine gets wrong about a blog.
 *
 * The marketing site already publishes one at the origin root describing the
 * product. This is its sibling for the writing, and it links back to it, so an
 * agent that finds either one can reach the other.
 *
 * 🔴 Served from a rewrite rule, NOT a static file, because the post list has
 * to stay current. It is cached in a transient and the cache is dropped
 * whenever a post is published, edited or deleted, so it never costs a query on
 * a normal request and never goes stale after an edit.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VECTORE_LLMS_CACHE = 'vectore_llms_txt';

/**
 * Register /llms.txt (relative to the blog root, so /blog/llms.txt live).
 */
add_action( 'init', 'vectore_blog_llms_rewrite' );
function vectore_blog_llms_rewrite() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?vectore_llms=1', 'top' );
}

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'vectore_llms';
	return $vars;
} );

/**
 * Flush rewrites once, when the theme is activated, so the rule above starts
 * working without anyone having to re-save permalinks.
 */
add_action( 'after_switch_theme', function () {
	vectore_blog_llms_rewrite();
	flush_rewrite_rules();
} );

add_action( 'template_redirect', 'vectore_blog_llms_serve' );
/**
 * Serve the file.
 */
function vectore_blog_llms_serve() {
	if ( ! get_query_var( 'vectore_llms' ) ) {
		return;
	}

	$body = get_transient( VECTORE_LLMS_CACHE );
	if ( false === $body ) {
		$body = vectore_blog_llms_build();
		set_transient( VECTORE_LLMS_CACHE, $body, DAY_IN_SECONDS );
	}

	status_header( 200 );
	// text/plain, per the convention. Markdown inside, but a client that just
	// reads it as text loses nothing.
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );          // it is a machine file, not a page
	header( 'Cache-Control: public, max-age=3600' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- plain text, built below.
	exit;
}

/**
 * Drop the cache whenever the post list could have changed.
 *
 * `save_post` covers publishing and editing, `deleted_post` covers removal, and
 * `switch_theme` covers the file no longer being served at all.
 */
foreach ( array( 'save_post', 'deleted_post', 'switch_theme', 'update_option_blogname', 'update_option_blogdescription' ) as $hook ) {
	add_action( $hook, function () {
		delete_transient( VECTORE_LLMS_CACHE );
	} );
}

/**
 * One line per post: title, URL, what it is about, and when it last changed.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function vectore_blog_llms_line( $post ) {
	$summary = has_excerpt( $post )
		? get_the_excerpt( $post )
		: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 28, '...' );

	$summary = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $summary ) ) );

	return sprintf(
		'- [%s](%s): %s (updated %s)',
		wp_strip_all_tags( get_the_title( $post ) ),
		get_permalink( $post ),
		$summary,
		get_the_modified_date( 'Y-m-d', $post )
	);
}

/**
 * Build the document.
 *
 * @return string
 */
function vectore_blog_llms_build() {
	$name = get_bloginfo( 'name' );
	$desc = get_bloginfo( 'description' );

	$out = array();
	$out[] = '# ' . $name;
	$out[] = '';
	$out[] = '> ' . ( $desc ? $desc : __( 'The Vectore blog.', 'vectore-blog' ) );
	$out[] = '';
	$out[] = sprintf(
		/* translators: 1: blog name, 2: product URL. */
		__( '%1$s is the writing arm of Vectore, a platform for running courses, cohorts, coaching, live events and community in one place. Product details are at %2$s, and a machine-readable summary of the product is at %2$s/llms.txt.', 'vectore-blog' ),
		$name,
		untrailingslashit( VECTORE_SITE_URL )
	);
	$out[] = '';

	$posts = get_posts(
		array(
			'posts_per_page'      => 50,
			'post_status'         => 'publish',
			'orderby'             => 'modified',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	if ( $posts ) {
		$out[] = '## Posts';
		$out[] = '';
		foreach ( $posts as $post ) {
			$out[] = vectore_blog_llms_line( $post );
		}
		$out[] = '';
	}

	$cats = get_categories( array( 'hide_empty' => true ) );
	if ( $cats ) {
		$out[] = '## Topics';
		$out[] = '';
		foreach ( $cats as $cat ) {
			$line = sprintf( '- [%s](%s)', $cat->name, get_category_link( $cat->term_id ) );
			if ( trim( (string) $cat->description ) ) {
				$line .= ': ' . trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $cat->description ) ) );
			}
			$line .= sprintf(
				/* translators: %d: number of posts. */
				' (' . _n( '%d post', '%d posts', (int) $cat->count, 'vectore-blog' ) . ')',
				(int) $cat->count
			);
			$out[] = $line;
		}
		$out[] = '';
	}

	// The authors, so an agent can attribute a claim to a person rather than to
	// a domain. Only authors who have actually published, and only with a bio,
	// since a name on its own adds nothing an answer engine can use.
	$authors = get_users( array( 'has_published_posts' => array( 'post' ), 'fields' => array( 'ID' ) ) );
	$lines   = array();
	foreach ( $authors as $author ) {
		$bio = trim( (string) get_the_author_meta( 'description', $author->ID ) );
		if ( '' === $bio ) {
			continue;
		}
		$lines[] = sprintf(
			'- [%s](%s): %s',
			get_the_author_meta( 'display_name', $author->ID ),
			get_author_posts_url( $author->ID ),
			trim( preg_replace( '/\s+/', ' ', $bio ) )
		);
	}
	if ( $lines ) {
		$out[] = '## Authors';
		$out[] = '';
		$out   = array_merge( $out, $lines );
		$out[] = '';
	}

	$out[] = '## Machine-readable feeds';
	$out[] = '';
	$out[] = '- [Sitemap](' . home_url( '/wp-sitemap.xml' ) . '): every indexable URL on the blog.';
	$out[] = '- [RSS](' . get_bloginfo( 'rss2_url' ) . '): full post feed.';
	$out[] = '- [REST API](' . home_url( '/wp-json/wp/v2/posts' ) . '): posts as JSON.';
	$out[] = '';
	$out[] = sprintf(
		/* translators: %s: ISO 8601 date. */
		__( 'Generated %s. Content is free to read, with no paywall or registration.', 'vectore-blog' ),
		gmdate( 'Y-m-d' )
	);
	$out[] = '';

	return implode( "\n", $out );
}
