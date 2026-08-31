<?php
/**
 * robots.txt policy.
 *
 * This file is the single thing standing between the blog and every crawler,
 * and it is easy to break silently: the origin serves ONE robots.txt for both
 * the marketing site and /blog, so a rule written for one can quietly exclude
 * the other. Nothing here parses HTML; it checks the policy is intact.
 */

$txt = file_get_contents( __DIR__ . '/../public/robots.txt' );

$fail = 0; $checks = 0;
function check( $label, $cond, $detail = '' ) {
	global $fail, $checks;
	$checks++;
	if ( $cond ) { printf( "  ok    %s\n", $label ); }
	else { printf( "  FAIL  %s%s\n", $label, $detail ? "\n        $detail" : '' ); $fail++; }
}

/** Agents named in the allow group (everything before the `User-agent: *` block). */
$allowBlock = explode( 'User-agent: *', $txt )[0];
$allowed = array();
preg_match_all( '/^User-agent:\s*(\S+)/mi', $allowBlock, $m );
$allowed = array_map( 'strtolower', $m[1] );

echo "\nAI answer engines must be able to reach the blog\n";
// Getting crawled is upstream of every other AI-SEO measure. If these are not
// in the allow group, the `User-agent: * Disallow: /` block at the foot of the
// file shuts them out and nothing else in the theme matters.
foreach ( array( 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'DuckAssistBot' ) as $bot ) {
	check( "$bot is allowed", in_array( strtolower( $bot ), $allowed, true ) );
}

echo "\nsearch engines\n";
foreach ( array( 'Googlebot', 'Bingbot', 'DuckDuckBot', 'Applebot' ) as $bot ) {
	check( "$bot is allowed", in_array( strtolower( $bot ), $allowed, true ) );
}

echo "\nthe blog's own paths\n";
check( '/blog is explicitly allowed', (bool) preg_match( '#^Allow:\s*/blog\s*$#mi', $txt ) );
check( 'wp-admin is disallowed', (bool) preg_match( '#^Disallow:\s*/blog/wp-admin/#mi', $txt ) );
check( 'admin-ajax stays reachable (the newsletter form posts to it)',
	(bool) preg_match( '#^Allow:\s*/blog/wp-admin/admin-ajax\.php#mi', $txt ) );
check( 'wp-login is disallowed', (bool) preg_match( '#^Disallow:\s*/blog/wp-login\.php#mi', $txt ) );

// Search results and author archives are kept OUT of the index with a noindex
// meta tag, which only works if the crawler is allowed to fetch the page and
// read it. A Disallow here would hide that directive and is a real regression.
check( 'blog search is NOT disallowed (noindex needs to be readable)',
	! preg_match( '#^Disallow:\s*/blog/\?s=#mi', $txt ) );
check( 'author archives are NOT disallowed (same reason)',
	! preg_match( '#^Disallow:\s*/blog/author#mi', $txt ) );

echo "\nsitemaps\n";
check( 'the marketing sitemap is declared', str_contains( $txt, 'Sitemap: https://vectore.app/sitemap.xml' ) );
check( "the blog's sitemap is declared", str_contains( $txt, 'Sitemap: https://vectore.io/blog/wp-sitemap.xml' ) );

echo "\nthe catch-all still closes the door\n";
check( 'unlisted crawlers are still blocked',
	(bool) preg_match( '/User-agent:\s*\*\s*\nDisallow:\s*\/\s*$/m', $txt ) );

echo "\nllms.txt at the origin\n";
$llms = file_get_contents( __DIR__ . '/../public/llms.txt' );
check( 'points at the blog', str_contains( $llms, 'vectore.io/blog' ) );
check( "points at the blog's own llms.txt", str_contains( $llms, 'vectore.io/blog/llms.txt' ) );

printf( "\n%d checks, %d failed\n", $checks, $fail );
exit( $fail ? 1 : 0 );
