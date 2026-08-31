<?php
/**
 * SEO and AI-answer-engine metadata: robots, canonical, Open Graph, JSON-LD.
 *
 * The theme owns this because there is no SEO plugin on the install. 🔴 If one
 * is ever added (Rank Math, Yoast), turn THIS OFF rather than running both: two
 * canonicals, two descriptions or two BlogPosting blocks on one page is worse
 * than neither. Define VECTORE_BLOG_SEO_OFF in wp-config.php to disable
 * everything here in one move.
 *
 * On AI answer engines specifically. The things that decide whether an LLM can
 * read, trust and cite a page are mostly not meta tags:
 *   - the content is server-rendered HTML, not assembled by JavaScript
 *   - one unambiguous canonical URL per page
 *   - an explicit author, an explicit publisher, and a visible date
 *   - a machine-readable summary of what the site is (see inc/llms.php)
 *   - the crawler being allowed in at all, which is robots.txt at the ORIGIN
 *     and therefore lives in the landing page's public/robots.txt, NOT here
 * This file covers the metadata half. The rest is noted where it lives.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the theme's metadata layer switched on?
 *
 * @return bool
 */
function vectore_blog_seo_on() {
	return ! ( defined( 'VECTORE_BLOG_SEO_OFF' ) && VECTORE_BLOG_SEO_OFF );
}

/**
 * The canonical URL for the current view.
 *
 * 🔴 Pagination is the whole reason this is a function rather than three lines
 * inline. An earlier version canonicalised every non-singular view to
 * `home_url('/')`, so /blog/page/2/ told search engines it was really page 1.
 * Google then drops page 2 from the index, and every post that only appears on
 * page 2 or later loses its crawl path. A paged view must point at ITSELF.
 *
 * @return string
 */
function vectore_blog_canonical() {
	$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ), 1 );

	if ( is_singular() ) {
		$url = get_permalink();
		// A post split with <!--nextpage--> paginates through `page`, and each
		// part is its own URL.
		if ( $paged > 1 ) {
			$url = trailingslashit( $url ) . $paged . '/';
		}
		return $url;
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$url = get_term_link( get_queried_object() );
		$url = is_wp_error( $url ) ? home_url( '/' ) : $url;
	} elseif ( is_author() ) {
		$url = get_author_posts_url( (int) get_queried_object_id() );
	} elseif ( is_search() ) {
		// Noindexed anyway, but a self-referential canonical stops a stray link
		// consolidating into the homepage.
		return get_search_link();
	} elseif ( is_404() ) {
		return home_url( '/' );
	} else {
		$url = home_url( '/' );
	}

	return ( $paged > 1 ) ? user_trailingslashit( trailingslashit( $url ) . 'page/' . $paged ) : $url;
}

/**
 * Robots directives.
 *
 * Uses core's `wp_robots` filter rather than printing a tag, so there is exactly
 * one robots meta on the page however many things want to influence it.
 *
 * max-snippet:-1 and max-video-preview:-1 lift the length caps on what a search
 * engine (and an AI answer engine reading the same signal) may quote. On a blog
 * whose whole purpose is to be quoted, capping the snippet is working against
 * yourself. It matches the policy the marketing site already sets.
 *
 * @param array<string, bool|string> $robots Directives.
 * @return array<string, bool|string>
 */
function vectore_blog_robots( $robots ) {
	if ( ! vectore_blog_seo_on() ) {
		return $robots;
	}

	$robots['max-image-preview'] = 'large';
	$robots['max-snippet']       = -1;
	$robots['max-video-preview'] = -1;

	// Search results are infinite, duplicate and worthless in an index. `follow`
	// stays on so the links out of them are still crawled.
	if ( is_search() || is_404() ) {
		$robots['noindex'] = true;
		unset( $robots['max-snippet'], $robots['max-video-preview'] );
	}

	/*
	 * Author archives cut both ways. With a real bio the page is a Person entity
	 * worth having indexed, and author authority is one of the few things an AI
	 * answer engine can actually use to decide whether to trust a claim. With an
	 * empty bio it is a second copy of the post list under a different URL.
	 * So: indexed when there is something on it, noindexed when there is not.
	 */
	if ( is_author() ) {
		$bio = get_the_author_meta( 'description', (int) get_queried_object_id() );
		if ( '' === trim( (string) $bio ) ) {
			$robots['noindex'] = true;
		}
	}

	return $robots;
}
add_filter( 'wp_robots', 'vectore_blog_robots' );

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
		if ( '' === trim( (string) $text ) ) {
			$text = sprintf(
				/* translators: 1: term name, 2: site name. */
				__( 'Everything we have written about %1$s on %2$s.', 'vectore-blog' ),
				single_term_title( '', false ),
				get_bloginfo( 'name' )
			);
		}
	} elseif ( is_author() ) {
		$text = get_the_author_meta( 'description', (int) get_queried_object_id() );
	} else {
		$text = get_bloginfo( 'description' );
	}

	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );

	if ( mb_strlen( $text ) <= 158 ) {
		return $text;
	}

	// Cut on a word boundary. A description sliced mid-word reads as broken in
	// a search result, which is the one place it is guaranteed to be seen.
	$cut = mb_substr( $text, 0, 158 );
	$sp  = mb_strrpos( $cut, ' ' );

	return rtrim( $sp ? mb_substr( $cut, 0, $sp ) : $cut, " ,.;:" ) . '...';
}

/**
 * The social card image for the current view.
 *
 * @return array{url:string, width:int, height:int, alt:string}
 */
function vectore_blog_social_image() {
	if ( is_singular() && has_post_thumbnail() ) {
		$id  = get_post_thumbnail_id();
		$src = wp_get_attachment_image_src( $id, 'vectore-cover' );
		if ( $src ) {
			$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			return array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => '' !== $alt ? $alt : wp_strip_all_tags( get_the_title() ),
			);
		}
	}

	return array(
		'url'    => VECTORE_BLOG_URI . '/assets/img/og-default.png',
		'width'  => 1200,
		'height' => 630,
		'alt'    => get_bloginfo( 'name' ),
	);
}

add_action( 'wp_head', 'vectore_blog_head_meta', 2 );
/**
 * Canonical, description, Open Graph and Twitter cards.
 */
function vectore_blog_head_meta() {
	if ( ! vectore_blog_seo_on() ) {
		return;
	}

	$desc      = vectore_blog_meta_description();
	$image     = vectore_blog_social_image();
	$canonical = vectore_blog_canonical();
	$type      = is_singular( 'post' ) ? 'article' : 'website';

	echo "\n<!-- Vectore Blog: metadata -->\n";

	if ( $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );

	// One language, declared. An AI engine deciding whether a page answers an
	// English question should not have to infer it from the prose.
	printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( $type ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( wp_get_document_title() ) );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $canonical ) );
	if ( $desc ) {
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image['url'] ) );
	printf( '<meta property="og:image:width" content="%d">' . "\n", $image['width'] );
	printf( '<meta property="og:image:height" content="%d">' . "\n", $image['height'] );
	printf( '<meta property="og:image:alt" content="%s">' . "\n", esc_attr( $image['alt'] ) );

	if ( is_singular( 'post' ) ) {
		printf( '<meta property="article:published_time" content="%s">' . "\n", esc_attr( get_the_date( DATE_W3C ) ) );
		printf( '<meta property="article:modified_time" content="%s">' . "\n", esc_attr( get_the_modified_date( DATE_W3C ) ) );
		printf(
			'<meta property="article:author" content="%s">' . "\n",
			esc_attr( get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', get_the_ID() ) ) )
		);
		foreach ( (array) get_the_category() as $cat ) {
			printf( '<meta property="article:section" content="%s">' . "\n", esc_attr( $cat->name ) );
		}
		foreach ( (array) get_the_tags() as $tag ) {
			printf( '<meta property="article:tag" content="%s">' . "\n", esc_attr( $tag->name ) );
		}
	}

	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( wp_get_document_title() ) );
	if ( $desc ) {
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image['url'] ) );
	printf( '<meta name="twitter:image:alt" content="%s">' . "\n", esc_attr( $image['alt'] ) );

	// Points an AI client at the site summary without it having to guess the
	// path. See inc/llms.php.
	printf(
		'<link rel="alternate" type="text/plain" title="llms.txt" href="%s">' . "\n",
		esc_url( home_url( '/llms.txt' ) )
	);

	// Prev/next on paged archives. Google stopped using them as an indexing
	// signal in 2019, but they are still a real navigational relationship and
	// other crawlers (Bing among them) still read them.
	if ( ! is_singular() ) {
		$prev = get_previous_posts_page_link();
		$next = get_next_posts_page_link();
		$max  = (int) $GLOBALS['wp_query']->max_num_pages;
		$page = max( (int) get_query_var( 'paged' ), 1 );

		if ( $page > 1 && $prev ) {
			printf( '<link rel="prev" href="%s">' . "\n", esc_url( $prev ) );
		}
		if ( $page < $max && $next ) {
			printf( '<link rel="next" href="%s">' . "\n", esc_url( $next ) );
		}
	}
}

/**
 * The Person node for an author, reused by BlogPosting, ProfilePage and the
 * author archive so one author is one entity across the whole graph.
 *
 * `sameAs` is the part that matters for an answer engine: it is what ties this
 * byline to the same human on other platforms, which is how an entity gets
 * resolved rather than guessed at.
 *
 * @param int $user_id Author.
 * @return array<string, mixed>
 */
function vectore_blog_person_node( $user_id ) {
	$node = array(
		'@type' => 'Person',
		'@id'   => get_author_posts_url( $user_id ) . '#person',
		'name'  => get_the_author_meta( 'display_name', $user_id ),
		'url'   => get_author_posts_url( $user_id ),
	);

	$bio = trim( (string) get_the_author_meta( 'description', $user_id ) );
	if ( '' !== $bio ) {
		$node['description'] = $bio;
	}

	$avatar = get_avatar_url( $user_id, array( 'size' => 192 ) );
	if ( $avatar ) {
		$node['image'] = array( '@type' => 'ImageObject', 'url' => $avatar );
	}

	// WordPress's own profile fields, plus whatever the install adds. Empty
	// values are dropped rather than emitted as empty strings, which would make
	// the entity look worse than saying nothing.
	$same_as = array();
	foreach ( array( 'url', 'twitter', 'linkedin', 'github', 'mastodon' ) as $field ) {
		$value = trim( (string) get_the_author_meta( $field, $user_id ) );
		if ( '' !== $value && filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$same_as[] = $value;
		}
	}
	if ( $same_as ) {
		$node['sameAs'] = array_values( array_unique( $same_as ) );
	}

	return $node;
}

add_action( 'wp_head', 'vectore_blog_schema', 3 );
/**
 * JSON-LD.
 *
 * One @graph, so every node can reference every other by @id instead of the
 * same organisation being restated four times. Exactly one BlogPosting per post
 * and one BreadcrumbList per view, the latter built from the SAME trail the
 * visible breadcrumb renders (inc/template-tags.php), so the two cannot
 * disagree. There is a test that proves it.
 */
function vectore_blog_schema() {
	if ( ! vectore_blog_seo_on() ) {
		return;
	}

	$site_id = VECTORE_SITE_URL . '#organization';
	$blog_id = home_url( '/' ) . '#blog';
	$lang    = get_bloginfo( 'language' );

	$graph = array();

	$graph[] = array(
		'@type' => 'Organization',
		'@id'   => $site_id,
		'name'  => 'Vectore',
		'url'   => VECTORE_SITE_URL,
		'logo'  => array(
			'@type' => 'ImageObject',
			'url'   => VECTORE_BLOG_URI . '/assets/img/vectore-mark.png',
		),
		'description' => __( 'The complete community platform built for learning: courses, cohorts, coaching, workshops and events, with community built in.', 'vectore-blog' ),
	);

	/*
	 * WebSite with a SearchAction. Two jobs: it is what makes a sitelinks search
	 * box possible, and it tells any agent that the blog HAS a search endpoint
	 * and what its URL template is, so it can look something up rather than
	 * crawling the archive.
	 */
	$graph[] = array(
		'@type'         => 'WebSite',
		'@id'           => home_url( '/' ) . '#website',
		'name'          => get_bloginfo( 'name' ),
		'url'           => home_url( '/' ),
		'inLanguage'    => $lang,
		'publisher'     => array( '@id' => $site_id ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	$graph[] = array(
		'@type'      => 'Blog',
		'@id'        => $blog_id,
		'name'       => get_bloginfo( 'name' ),
		'description'=> get_bloginfo( 'description' ),
		'url'        => home_url( '/' ),
		'inLanguage' => $lang,
		'publisher'  => array( '@id' => $site_id ),
		'isPartOf'   => array( '@id' => home_url( '/' ) . '#website' ),
	);

	$canonical = vectore_blog_canonical();

	if ( is_singular( 'post' ) ) {
		$author_id = (int) get_post_field( 'post_author', get_the_ID() );
		$person    = vectore_blog_person_node( $author_id );
		$graph[]   = $person;

		$posting = array(
			'@type'            => 'BlogPosting',
			'@id'              => get_permalink() . '#post',
			'headline'         => wp_strip_all_tags( get_the_title() ),
			'description'      => vectore_blog_meta_description(),
			'url'              => get_permalink(),
			'datePublished'    => get_the_date( DATE_W3C ),
			'dateModified'     => get_the_modified_date( DATE_W3C ),
			'inLanguage'       => $lang,
			'wordCount'        => str_word_count( wp_strip_all_tags( get_the_content() ) ),
			'author'           => array( '@id' => $person['@id'] ),
			'publisher'        => array( '@id' => $site_id ),
			'isPartOf'         => array( '@id' => $blog_id ),
			'mainEntityOfPage' => array( '@type' => 'WebPage', '@id' => $canonical ),
			/*
			 * No paywall, no registration wall. An answer engine deciding
			 * whether it may quote a page treats an unstated access policy far
			 * more cautiously than a stated open one, and this blog is open.
			 */
			'isAccessibleForFree' => true,
		);

		// ISO 8601 duration. Same number the byline shows, so a reader and a
		// machine are told the same thing.
		$minutes = max( 1, (int) ceil( str_word_count( wp_strip_all_tags( strip_shortcodes( get_the_content() ) ) ) / 200 ) );
		$posting['timeRequired'] = 'PT' . $minutes . 'M';

		$image = vectore_blog_social_image();
		if ( has_post_thumbnail() ) {
			$posting['image'] = array(
				'@type'  => 'ImageObject',
				'url'    => $image['url'],
				'width'  => $image['width'],
				'height' => $image['height'],
			);
		}

		$terms = get_the_category();
		if ( ! empty( $terms ) ) {
			$posting['articleSection'] = wp_list_pluck( $terms, 'name' );
		}

		$tags = get_the_tags();
		if ( ! empty( $tags ) ) {
			$posting['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
		}

		$graph[] = $posting;

	} elseif ( is_page() ) {
		$graph[] = array(
			'@type'      => 'WebPage',
			'@id'        => $canonical,
			'name'       => wp_strip_all_tags( get_the_title() ),
			'description'=> vectore_blog_meta_description(),
			'url'        => $canonical,
			'inLanguage' => $lang,
			'isPartOf'   => array( '@id' => home_url( '/' ) . '#website' ),
			'dateModified' => get_the_modified_date( DATE_W3C ),
		);

	} elseif ( is_author() ) {
		$user_id = (int) get_queried_object_id();
		$person  = vectore_blog_person_node( $user_id );
		$graph[] = $person;
		$graph[] = array(
			'@type'      => 'ProfilePage',
			'@id'        => $canonical,
			'url'        => $canonical,
			'name'       => get_the_author_meta( 'display_name', $user_id ),
			'inLanguage' => $lang,
			'isPartOf'   => array( '@id' => home_url( '/' ) . '#website' ),
			'mainEntity' => array( '@id' => $person['@id'] ),
		);

	} elseif ( is_category() || is_tag() || is_tax() || is_home() ) {
		// A listing page IS a collection, and saying so stops an answer engine
		// treating an archive as a thin article about nothing.
		$graph[] = array(
			'@type'      => 'CollectionPage',
			'@id'        => $canonical,
			'url'        => $canonical,
			'name'       => is_home() ? get_bloginfo( 'name' ) : wp_strip_all_tags( get_the_archive_title() ),
			'description'=> vectore_blog_meta_description(),
			'inLanguage' => $lang,
			'isPartOf'   => array( '@id' => home_url( '/' ) . '#website' ),
		);
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
			'@id'             => $canonical . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	$json = wp_json_encode(
		array( '@context' => 'https://schema.org', '@graph' => $graph ),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
