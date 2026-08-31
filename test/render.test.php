<?php
/**
 * Renders every template against the stub harness and checks the markup.
 *
 * What this proves: the templates run, every function they call exists with the
 * arity they call it with, the output is well-formed HTML, and the design's
 * structural invariants (one h1, one date source, the skip link, the rails)
 * actually appear in the output rather than only in the CSS.
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
require VECTORE_BLOG_DIR . '/inc/newsletter.php';

$fail = 0;
$checks = 0;

function check( $label, $cond, $detail = '' ) {
	global $fail, $checks;
	$checks++;
	if ( $cond ) {
		printf( "  ok    %s\n", $label );
	} else {
		printf( "  FAIL  %s%s\n", $label, $detail ? "\n        $detail" : '' );
		$fail++;
	}
}

function render( $template, $view, $posts = 3 ) {
	$GLOBALS['vview']  = $view;
	$GLOBALS['vposts'] = $posts;
	ob_start();
	include VECTORE_BLOG_DIR . '/' . $template;
	return ob_get_clean();
}

/**
 * A tag-balance check that understands void elements. Catches the unclosed
 * <div> that shifts an entire footer inside an article.
 */
function unbalanced( $html ) {
	// Comments, the doctype and inline script/style bodies are not markup as far
	// as nesting is concerned. `<!-- wp_head -->` in particular parses as a tag
	// named "!--" if it is left in, which reports a failure on every view.
	$html = preg_replace( '#<!--.*?-->#s', '', $html );
	$html = preg_replace( '#<!DOCTYPE[^>]*>#i', '', $html );
	$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $html );

	$void = array( 'area','base','br','col','embed','hr','img','input','link','meta','source','track','wbr' );
	preg_match_all( '#<(/?)([a-zA-Z][a-zA-Z0-9]*)([^>]*?)(/?)>#s', $html, $m, PREG_SET_ORDER );
	$stack = array();
	foreach ( $m as $t ) {
		$name = strtolower( $t[2] );
		if ( in_array( $name, $void, true ) || '/' === $t[4] ) { continue; }
		if ( '/' === $t[1] ) {
			if ( empty( $stack ) ) { return "stray </$name>"; }
			$open = array_pop( $stack );
			if ( $open !== $name ) { return "</$name> closes <$open>"; }
		} else {
			$stack[] = $name;
		}
	}
	return $stack ? 'unclosed <' . implode( '>, <', $stack ) . '>' : '';
}

$views = array(
	'index.php'   => 'home',
	'archive.php' => 'archive',
	'search.php'  => 'search',
	'404.php'     => '404',
	'single.php'  => 'single',
	'page.php'    => 'page',
);

$out = array();

foreach ( $views as $tpl => $view ) {
	echo "\n$tpl ($view)\n";
	$html = render( $tpl, $view, 'single' === $view || 'page' === $view ? 1 : 3 );
	$out[ $view ] = $html;

	check( 'renders without a PHP error', strlen( $html ) > 500, strlen( $html ) . ' bytes' );
	$bal = unbalanced( $html );
	check( 'HTML tags balance', '' === $bal, $bal );
	check( 'has exactly one <h1>', 1 === substr_count( $html, '<h1' ), substr_count( $html, '<h1' ) . ' found' );
	check( 'has the skip link', str_contains( $html, 'v-skip-link' ) );
	check( 'has the floating header', str_contains( $html, 'v-header__bar' ) );
	check( 'has the footer wordmark', str_contains( $html, 'v-footer__mark' ) );
	check( 'opens the mint canvas', str_contains( $html, 'v-canvas' ) );
	check( 'no unreplaced PHP tags leaked', ! str_contains( $html, '<?php' ) );
}

echo "\nsingle-post structure\n";
$s = $out['single'];
check( 'breadcrumb trail is rendered', str_contains( $s, 'v-crumbs' ) );
check( 'byline is rendered', str_contains( $s, 'v-byline' ) );
check( 'left rail (TOC + share) present', str_contains( $s, 'v-rail--left' ) && str_contains( $s, 'v-toc' ) );
check( 'right rail (newsletter) present', str_contains( $s, 'v-rail--right' ) && str_contains( $s, 'v-nlcard' ) );
check( 'mobile newsletter CTA present', str_contains( $s, 'v-nlcta' ) );
check( 'TOC container ships hidden for JS to fill', str_contains( $s, 'data-v-toc' ) && str_contains( $s, 'hidden' ) );
check( 'related posts rendered', str_contains( $s, 'v-related' ) );
check( 'prev/next rendered', str_contains( $s, 'v-adjacent' ) );

// The single-source-of-the-date rule from the design: exactly one <time> in the
// article header, and it must be the MODIFIED date.
preg_match( '#<header class="v-single__hero".*?</header>#s', $s, $hero );
check( 'exactly one <time> in the post header', 1 === substr_count( $hero[0] ?? '', '<time' ),
	substr_count( $hero[0] ?? '', '<time' ) . ' found' );
check( 'that date is the modified date', str_contains( $hero[0] ?? '', '2026-08-20' ) );

echo "\nnewsletter form\n";
ob_start(); vectore_blog_newsletter_form( 'test' ); $nl = ob_get_clean();
check( 'is a real <form> with a real action (works without JS)',
	str_contains( $nl, '<form' ) && str_contains( $nl, 'action="https://vectore.io/blog/wp-admin/admin-ajax.php"' ) );
check( 'carries a nonce', str_contains( $nl, 'data-nonce="testnonce"' ) );
check( 'has a honeypot', str_contains( $nl, 'v-nl__hp' ) );
check( 'email input is labelled', str_contains( $nl, 'screen-reader-text' ) && str_contains( $nl, 'for="v-nl-test"' ) );
check( 'status region is polite', str_contains( $nl, 'aria-live="polite"' ) );

echo "\nSEO output\n";
$GLOBALS['vview'] = 'single';
ob_start(); vectore_blog_head_meta(); vectore_blog_schema(); $head = ob_get_clean();
check( 'exactly one canonical', 1 === substr_count( $head, 'rel="canonical"' ) );
check( 'has a meta description', str_contains( $head, 'name="description"' ) );
check( 'has Open Graph + Twitter cards', str_contains( $head, 'og:title' ) && str_contains( $head, 'twitter:card' ) );

preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $head, $ld );
$json = json_decode( $ld[1] ?? '', true );
check( 'JSON-LD parses', is_array( $json ), json_last_error_msg() );
$types = array_column( $json['@graph'] ?? array(), '@type' );
check( 'graph has Organization, Blog, BlogPosting, BreadcrumbList',
	empty( array_diff( array( 'Organization', 'Blog', 'BlogPosting', 'BreadcrumbList' ), $types ) ),
	implode( ', ', $types ) );
check( 'exactly one BlogPosting', 1 === count( array_keys( $types, 'BlogPosting' ) ) );

// The breadcrumb schema and the visible trail are built from the same function,
// so they cannot disagree. Prove it rather than trusting the comment.
$crumbIdx = array_search( 'BreadcrumbList', $types, true );
$schemaNames = wp_list_pluck( $json['@graph'][ $crumbIdx ]['itemListElement'], 'name' );
preg_match_all( '#<(?:a|span)[^>]*>([^<]+)</(?:a|span)>#', (string) ( preg_match( '#<nav class="v-crumbs".*?</nav>#s', $s, $c ) ? $c[0] : '' ), $vm );
check( 'visible breadcrumbs match the schema exactly',
	array_map( 'trim', $vm[1] ) === array_map( 'trim', $schemaNames ),
	'visible: ' . implode( ' > ', $vm[1] ) . ' | schema: ' . implode( ' > ', $schemaNames ) );

echo "\nreading time\n";
check( 'floors at 1 minute for a short post', '1 min read' === vectore_blog_reading_time() );

printf( "\n%d checks, %d failed\n", $checks, $fail );
exit( $fail ? 1 : 0 );
