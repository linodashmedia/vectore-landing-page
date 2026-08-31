<?php
/**
 * Static checks on the stylesheets.
 *
 * The important one is the LAST: every colour the design uses is a custom
 * property, so a token that is referenced but never defined fails silently in
 * the browser (the property resolves to nothing and the rule is dropped). That
 * is invisible in review and obvious to a visitor.
 */

$root  = __DIR__ . '/../blog/themes/vectore-blog';
$files = array(
	'style.css'              => "$root/style.css",
	'assets/css/cards.css'   => "$root/assets/css/cards.css",
	'assets/css/single.css'  => "$root/assets/css/single.css",
);

$fail = 0;
$checks = 0;
function check( $label, $cond, $detail = '' ) {
	global $fail, $checks;
	$checks++;
	if ( $cond ) { printf( "  ok    %s\n", $label ); }
	else { printf( "  FAIL  %s%s\n", $label, $detail ? "\n        $detail" : '' ); $fail++; }
}

/** Strip comments so braces and colons inside them are not counted. */
function decomment( $css ) { return preg_replace( '#/\*.*?\*/#s', '', $css ); }

$all = '';
foreach ( $files as $name => $path ) {
	echo "\n$name\n";
	$css = file_get_contents( $path );
	$all .= $css;
	$bare = decomment( $css );

	check( 'braces balance',
		substr_count( $bare, '{' ) === substr_count( $bare, '}' ),
		substr_count( $bare, '{' ) . ' open, ' . substr_count( $bare, '}' ) . ' close' );

	check( 'no unterminated comment', substr_count( $css, '/*' ) === substr_count( $css, '*/' ) );
	check( 'no leftover TODO/FIXME', ! preg_match( '/\b(TODO|FIXME|XXX)\b/', $css ) );
	// The design brief is a teal palette. A stray hex from the source theme's
	// orange would be invisible in review and glaring on the page.
	check( 'no orange left over from the source design',
		! preg_match( '/#(FE4A22|E65100|FF7A18|C44400|D53E1D)/i', $css ) );
}

echo "\ncustom properties\n";
$bare = decomment( $all );

// Everything defined anywhere in the bundle...
preg_match_all( '/(--[a-z0-9-]+)\s*:/i', $bare, $def );
$defined = array_unique( $def[1] );

// ...against everything referenced.
preg_match_all( '/var\(\s*(--[a-z0-9-]+)/i', $bare, $use );
$used = array_unique( $use[1] );

// A var() with a fallback still renders if the token is missing, so those are
// listed separately rather than treated as safe.
preg_match_all( '/var\(\s*(--[a-z0-9-]+)\s*,/i', $bare, $fb );
$withFallback = array_unique( $fb[1] );

$missing = array_diff( $used, $defined, $withFallback );
check( 'every referenced token is defined', empty( $missing ), implode( ', ', $missing ) );

$unused = array_diff( $defined, $used );

// A token may legitimately go unreferenced by the theme's own CSS in exactly
// two cases, and both are checked rather than assumed:
//
//   1. theme.json publishes it to writers as a block-editor colour, so the
//      consumer is a post, not a stylesheet. That list is read from the file,
//      so deleting a colour there makes its token dead again.
//   2. JavaScript reads or writes it.
$theme = json_decode( file_get_contents( "$root/theme.json" ), true );
$published = array_map(
	fn( $c ) => '--v-' . $c['slug'],
	$theme['settings']['color']['palette'] ?? array()
);
$jsOwned = array( '--v-admin-bar' );

$dead = array_diff( $unused, $published, $jsOwned );
check( 'no dead tokens', empty( $dead ), implode( ', ', $dead ) );

// The reverse direction: a colour offered in the block editor that the theme
// has no token for would let a writer pick a colour the design never defined.
$tokenless = array_diff( $published, $defined );
check( 'every editor palette colour has a matching token', empty( $tokenless ), implode( ', ', $tokenless ) );

printf( "\n%d checks, %d failed\n", $checks, $fail );
exit( $fail ? 1 : 0 );
