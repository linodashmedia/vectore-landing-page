<?php
/**
 * SEO: canonical, meta description, Open Graph, and JSON-LD.
 *
 * The theme owns this because there is no SEO plugin on the install. 🔴 If one
 * is ever added (Rank Math, Yoast), turn THIS OFF rather than running both:
 * two canonicals, two descriptions or two BlogPosting blocks on one page is
 * worse than neither. Define VECTORE_BLOG_SEO_OFF in wp-config.php to disable
 * everything here in one move.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A plain-text description for the current view, capped at a length search
 * engines will actually render.
 *
 * @return string
 */
function vectore_blog_meta_description() {
	if ( is_singular() ) {
		$post = get_post();
		$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	} else {
		$text = get_bloginfo( 'description' );
	}

	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );

	return mb_substr( $text, 0, 158 );
}

/**
 * The image for the current view: the featured image on a post, the site's
 * default social card otherwise.
 *
 * @return string
 */
function vectore_blog_social_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'vectore-cover' );
		if ( $src ) {
			return $src[0];
		}
	}
	return VECTORE_BLOG_URI . '/assets/img/og-default.png';
}

add_action( 'wp_head', 'vectore_blog_head_meta', 2 );
function vectore_blog_head_meta() {
	if ( defined( 'VECTORE_BLOG_SEO_OFF' ) && VECTORE_BLOG_SEO_OFF ) {
		return;
	}

	$desc  = vectore_blog_meta_description();
	$image = vectore_blog_social_image();

	if ( is_singular() ) {
		$canonical = get_permalink();
		$type      = 'article';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$canonical = get_term_link( get_queried_object() );
		$type      = 'website';
	} else {
		$canonical = home_url( '/' );
		$type      = 'website';
	}

	if ( is_wp_error( $canonical ) ) {
		$canonical = home_url( '/' );
	}

	echo "\n<!-- Vectore Blog: metadata -->\n";

	if ( $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );

	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( wp_get_document_title() ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $canonical ) );
	if ( $desc ) {
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );

	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( wp_get_document_title() ) );
	if ( $desc ) {
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image ) );

	if ( is_singular( 'post' ) ) {
		printf( '<meta property="article:published_time" content="%s">' . "\n", esc_attr( get_the_date( DATE_W3C ) ) );
		printf( '<meta property="article:modified_time" content="%s">' . "\n", esc_attr( get_the_modified_date( DATE_W3C ) ) );
	}
}

add_action( 'wp_head', 'vectore_blog_schema', 3 );
/**
 * JSON-LD.
 *
 * Exactly one BlogPosting per post and one BreadcrumbList per view, built from
 * the SAME trail the visible breadcrumb renders (inc/template-tags.php), so the
 * two can never disagree.
 */
function vectore_blog_schema() {
	if ( defined( 'VECTORE_BLOG_SEO_OFF' ) && VECTORE_BLOG_SEO_OFF ) {
		return;
	}

	$graph = array();

	$graph[] = array(
		'@type' => 'Organization',
		'@id'   => VECTORE_SITE_URL . '#organization',
		'name'  => 'Vectore',
		'url'   => VECTORE_SITE_URL,
		'logo'  => VECTORE_BLOG_URI . '/assets/img/vectore-mark.png',
	);

	$graph[] = array(
		'@type'     => 'Blog',
		'@id'       => home_url( '/' ) . '#blog',
		'name'      => get_bloginfo( 'name' ),
		'url'       => home_url( '/' ),
		'publisher' => array( '@id' => VECTORE_SITE_URL . '#organization' ),
	);

	if ( is_singular( 'post' ) ) {
		$posting = array(
			'@type'            => 'BlogPosting',
			'@id'              => get_permalink() . '#post',
			'headline'         => wp_strip_all_tags( get_the_title() ),
			'description'      => vectore_blog_meta_description(),
			'url'              => get_permalink(),
			'datePublished'    => get_the_date( DATE_W3C ),
			'dateModified'     => get_the_modified_date( DATE_W3C ),
			'wordCount'        => str_word_count( wp_strip_all_tags( get_the_content() ) ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', get_the_ID() ) ),
				'url'   => get_author_posts_url( (int) get_post_field( 'post_author', get_the_ID() ) ),
			),
			'publisher'        => array( '@id' => VECTORE_SITE_URL . '#organization' ),
			'isPartOf'         => array( '@id' => home_url( '/' ) . '#blog' ),
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => get_permalink() ),
		);

		if ( has_post_thumbnail() ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'vectore-cover' );
			if ( $src ) {
				$posting['image'] = array(
					'@type'  => 'ImageObject',
					'url'    => $src[0],
					'width'  => $src[1],
					'height' => $src[2],
				);
			}
		}

		$terms = get_the_category();
		if ( ! empty( $terms ) ) {
			$posting['articleSection'] = wp_list_pluck( $terms, 'name' );
		}

		$graph[] = $posting;
	}

	$trail = vectore_blog_crumb_trail();
	if ( count( $trail ) > 1 ) {
		$items = array();
		foreach ( $trail as $i => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => wp_strip_all_tags( $crumb['label'] ),
				'item'     => $crumb['url'],
			);
		}
		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	$json = wp_json_encode(
		array( '@context' => 'https://schema.org', '@graph' => $graph ),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
