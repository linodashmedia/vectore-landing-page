<?php
/**
 * SEO and AI-answer-engine metadata.
 *
 * Checks the things that are expensive to notice in production: a canonical
 * that de-indexes pagination, a search page that gets indexed, a JSON-LD graph
 * whose nodes do not actually reference each other, a description sliced
 * mid-word.
 */

require __DIR__ . '/wp-stubs.php';

define( 'VECTORE_BLOG_VERSION', '1.0.0' );
define( 'VECTORE_BLOG_DIR', __DIR__ . '/../blog/themes/vectore-blog' );
define( 'VECTORE_BLOG_URI', 'https://vectore.io/blog/wp-content/themes/vectore-blog' );
define( 'VECTORE_SITE_URL', 'https://vectore.io' );
define( 'VECTORE_WAITLIST_ENDPOINT', 'https://vectore.io/api/waitlist' );

require VECTORE_BLOG_DIR . '/inc/setup.php';
require VECTORE_BLOG_DIR . '/inc/template-tags.php';
require VECTORE_BLOG_DIR . '/inc/seo.php';
require VECTORE_BLOG_DIR . '/inc/llms.php';

$fail = 0; $checks = 0;
function check( $label, $cond, $detail = '' ) {
	global $fail, $checks;
	$checks++;
	if ( $cond ) { printf( "  ok    %s\n", $label ); }
	else { printf( "  FAIL  %s%s\n", $label, $detail ? "\n        $detail" : '' ); $fail++; }
}

/** Render head metadata for a view. */
function head_for( $view, $paged = 0 ) {
	$GLOBALS['vview']  = $view;
	$GLOBALS['vpaged'] = $paged;
	ob_start();
	vectore_blog_head_meta();
	vectore_blog_schema();
	return ob_get_clean();
}
function graph_of( $head ) {
	preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $head, $m );
	return json_decode( $m[1] ?? '', true );
}
function meta_of( $head, $attr, $name ) {
	preg_match( '#<meta ' . $attr . '="' . preg_quote( $name, '#' ) . '" content="([^"]*)"#', $head, $m );
	return $m[1] ?? null;
}
function canonical_of( $head ) {
	preg_match( '#<link rel="canonical" href="([^"]*)"#', $head, $m );
	return $m[1] ?? null;
}

echo "\ncanonical URLs\n";
check( 'a post canonicalises to itself', canonical_of( head_for( 'single' ) ) === 'https://vectore.io/blog/cohorts-beat-courses/' );
check( 'the blog index canonicalises to itself', canonical_of( head_for( 'home' ) ) === 'https://vectore.io/blog/' );

// The bug this whole function exists for: page 2 must not claim to be page 1,
// or Google drops it and every post only reachable from page 2 loses its
// crawl path.
$p2 = canonical_of( head_for( 'home', 2 ) );
check( 'page 2 of the index canonicalises to PAGE 2, not page 1',
	$p2 === 'https://vectore.io/blog/page/2/', "got $p2" );
$c2 = canonical_of( head_for( 'archive', 3 ) );
check( 'page 3 of a category canonicalises to page 3',
	str_contains( (string) $c2, '/page/3' ), "got $c2" );

echo "\nrel=prev / rel=next on paged views\n";
$h = head_for( 'home', 2 );
check( 'page 2 declares a previous page', str_contains( $h, '<link rel="prev"' ) );
check( 'page 2 declares a next page', str_contains( $h, '<link rel="next"' ) );
$h1 = head_for( 'home', 0 );
check( 'page 1 declares no previous page', ! str_contains( $h1, '<link rel="prev"' ) );

echo "\nrobots directives\n";
$GLOBALS['vview'] = 'single'; $r = vt_robots();
check( 'a post is indexable', empty( $r['noindex'] ) );
check( 'snippet length is uncapped, so answer engines may quote in full',
	isset( $r['max-snippet'] ) && -1 === $r['max-snippet'] );
check( 'large image previews are allowed', 'large' === ( $r['max-image-preview'] ?? '' ) );

$GLOBALS['vview'] = 'search'; $r = vt_robots();
check( 'search results are noindexed', ! empty( $r['noindex'] ) );
check( 'search results are still followed', empty( $r['nofollow'] ) );

$GLOBALS['vview'] = '404'; $r = vt_robots();
check( '404 is noindexed', ! empty( $r['noindex'] ) );

// An author page with a bio is a Person entity worth indexing. Without one it
// is a second copy of the post list.
$GLOBALS['vview'] = 'author';
$r = vt_robots();
check( 'an author WITH a bio is indexable', empty( $r['noindex'] ) );
$saved = $GLOBALS['vauthor']['description'];
$GLOBALS['vauthor']['description'] = '';
$r = vt_robots();
check( 'an author WITHOUT a bio is noindexed', ! empty( $r['noindex'] ) );
$GLOBALS['vauthor']['description'] = $saved;

echo "\nmeta description\n";
// Set the view explicitly: the checks above leave it on the index, where the
// description is the short site tagline and the truncation path never runs.
$GLOBALS['vview'] = 'single';
$d = vectore_blog_meta_description();
check( 'is within the length search engines render', mb_strlen( $d ) <= 161, mb_strlen( $d ) . ' chars' );
$GLOBALS['vt']['excerpt'] = str_repeat( 'community driven learning ', 20 );
$long = vectore_blog_meta_description();
check( 'a long description is cut on a word boundary, not mid-word',
	str_ends_with( $long, '...' ) && ! preg_match( '/\s\S{1,3}\.\.\.$/', $long ), $long );
$GLOBALS['vt']['excerpt'] = 'Self-paced courses lose people in week two. Cohorts hold them, and the reason is not the content.';

echo "\nOpen Graph and Twitter\n";
$h = head_for( 'single' );
foreach ( array( 'og:locale','og:site_name','og:type','og:title','og:url','og:description','og:image','og:image:width','og:image:height','og:image:alt','article:published_time','article:modified_time','article:author','article:section','article:tag' ) as $prop ) {
	check( "emits $prop", null !== meta_of( $h, 'property', $prop ) );
}
foreach ( array( 'twitter:card','twitter:title','twitter:description','twitter:image','twitter:image:alt' ) as $n ) {
	check( "emits $n", null !== meta_of( $h, 'name', $n ) );
}
check( 'og:url matches the canonical', meta_of( $h, 'property', 'og:url' ) === canonical_of( $h ) );
check( 'og:type is article on a post', 'article' === meta_of( $h, 'property', 'og:type' ) );
check( 'og:type is website on the index', 'website' === meta_of( head_for( 'home' ), 'property', 'og:type' ) );
check( 'og:image:alt describes the image, not the site', 'A cohort working together' === meta_of( $h, 'property', 'og:image:alt' ) );
check( 'points AI clients at llms.txt', str_contains( $h, 'title="llms.txt"' ) );

echo "\nJSON-LD graph\n";
$g = graph_of( head_for( 'single' ) );
check( 'parses', is_array( $g ), json_last_error_msg() );
$types = array_column( $g['@graph'], '@type' );
check( 'has Organization, WebSite, Blog, Person, BlogPosting, BreadcrumbList',
	empty( array_diff( array( 'Organization','WebSite','Blog','Person','BlogPosting','BreadcrumbList' ), $types ) ),
	implode( ', ', $types ) );
check( 'exactly one BlogPosting', 1 === count( array_keys( $types, 'BlogPosting' ) ) );

$byType = array_combine( $types, $g['@graph'] );
$post = $byType['BlogPosting'];
check( 'the post declares an author by reference, not by repetition',
	isset( $post['author']['@id'] ) && $post['author']['@id'] === $byType['Person']['@id'] );
check( 'the post declares its publisher', $post['publisher']['@id'] === 'https://vectore.io#organization' );
check( 'published and modified dates are both present and ISO 8601',
	preg_match( '/^\d{4}-\d{2}-\d{2}T/', $post['datePublished'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T/', $post['dateModified'] ) );
check( 'declares the content is free to read (answer engines check this)',
	true === ( $post['isAccessibleForFree'] ?? null ) );
check( 'declares reading time as an ISO 8601 duration',
	isset( $post['timeRequired'] ) && preg_match( '/^PT\d+M$/', $post['timeRequired'] ), $post['timeRequired'] ?? 'missing' );
check( 'declares a language', ! empty( $post['inLanguage'] ) );
check( 'carries its sections and keywords', ! empty( $post['articleSection'] ) && ! empty( $post['keywords'] ) );

$person = $byType['Person'];
check( 'the author has a description', ! empty( $person['description'] ) );
check( 'the author has sameAs links (entity resolution)', ! empty( $person['sameAs'] ) );
check( 'empty profile fields are dropped, not emitted blank',
	! in_array( '', $person['sameAs'], true ) );

$site = $byType['WebSite'];
check( 'WebSite exposes a SearchAction', 'SearchAction' === ( $site['potentialAction']['@type'] ?? '' ) );
check( 'the search template carries the placeholder',
	str_contains( $site['potentialAction']['target']['urlTemplate'], '{search_term_string}' ) );

echo "\nJSON-LD on non-post views\n";
foreach ( array( 'home' => 'CollectionPage', 'archive' => 'CollectionPage', 'page' => 'WebPage', 'author' => 'ProfilePage' ) as $view => $want ) {
	$t = array_column( graph_of( head_for( $view ) )['@graph'], '@type' );
	check( "$view emits $want", in_array( $want, $t, true ), implode( ', ', $t ) );
	check( "$view emits no BlogPosting", ! in_array( 'BlogPosting', $t, true ) );
}

echo "\nllms.txt\n";
$GLOBALS['vview'] = 'home';
$llms = vectore_blog_llms_build();
check( 'opens with an H1 title', str_starts_with( $llms, '# ' ) );
check( 'has the one-line blockquote summary the convention expects', (bool) preg_match( '/^> .+/m', $llms ) );
check( 'lists posts with links', str_contains( $llms, '## Posts' ) && substr_count( $llms, '](https://vectore.io/blog/' ) >= 2 );
check( 'each post carries a last-updated date', (bool) preg_match( '/\(updated \d{4}-\d{2}-\d{2}\)/', $llms ) );
check( 'lists topics', str_contains( $llms, '## Topics' ) );
check( 'lists authors with their bios', str_contains( $llms, '## Authors' ) && str_contains( $llms, 'cohort programmes' ) );
check( 'points at the sitemap, RSS and REST feeds',
	str_contains( $llms, 'wp-sitemap.xml' ) && str_contains( $llms, 'feed' ) && str_contains( $llms, 'wp-json' ) );
check( 'links back to the product llms.txt', str_contains( $llms, 'vectore.io/llms.txt' ) );
check( 'states the content is free to read', str_contains( $llms, 'no paywall' ) );
check( 'contains no HTML', ! preg_match( '/<[a-z]/i', $llms ) );

echo "\nthe kill switch\n";
define( 'VECTORE_BLOG_SEO_OFF', true );
$off = head_for( 'single' );
check( 'VECTORE_BLOG_SEO_OFF suppresses everything, for when an SEO plugin arrives',
	'' === trim( $off ), substr( $off, 0, 80 ) );

printf( "\n%d checks, %d failed\n", $checks, $fail );
exit( $fail ? 1 : 0 );
