<?php
/**
 * Contrast guard for the palette.
 *
 * The colours are read out of style.css, not restated here, so this fails if
 * someone retunes a token without re-checking the pairing it is used in. Every
 * assertion below matches a claim written in the palette comment in that file;
 * the two are meant to be kept honest by this test rather than by memory.
 */

$css = file_get_contents( __DIR__ . '/../blog/themes/vectore-blog/style.css' );

function token( $css, $name ) {
	if ( ! preg_match( '/' . preg_quote( $name, '/' ) . '\s*:\s*(#[0-9A-Fa-f]{6})/', $css, $m ) ) {
		fwrite( STDERR, "token $name not found in style.css\n" );
		exit( 1 );
	}
	return strtoupper( $m[1] );
}

function lum( $hex ) {
	$c = array_map(
		function ( $v ) {
			$v /= 255;
			return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		},
		array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) )
	);
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function ratio( $a, $b ) {
	$la = lum( $a ); $lb = lum( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

$t = array();
foreach ( array( 'brand','accent','accent-deep','glow','ink','text','muted','hair','surface','white','mint','mint-2','coral','amber' ) as $n ) {
	$t[ $n ] = token( $css, '--v-' . $n );
}

$fail = 0;
/**
 * @param string $label What the pairing is used for.
 * @param float  $got   Measured ratio.
 * @param float  $min   Required ratio.
 * @param bool   $atMost True when the point is that the colour is NOT readable.
 */
function want( $label, $got, $min, $atMost = false ) {
	global $fail;
	$ok = $atMost ? $got < $min : $got >= $min;
	printf( "  %s  %-52s %5.2f:1 %s %.1f\n", $ok ? 'ok  ' : 'FAIL', $label, $got, $atMost ? '<' : '>=', $min );
	if ( ! $ok ) { $fail++; }
}

echo "\ntext that must be readable (WCAG AA, 4.5:1 for small text)\n";
want( 'body text on white',                    ratio( $t['text'],   $t['white'] ),   4.5 );
want( 'headings (ink) on white',               ratio( $t['ink'],    $t['white'] ),   4.5 );
want( 'muted meta on white',                   ratio( $t['muted'],  $t['white'] ),   4.5 );
want( 'muted meta on the surface tint',        ratio( $t['muted'],  $t['surface'] ), 4.5 );
want( 'links (accent) on white',               ratio( $t['accent'], $t['white'] ),   4.5 );
want( 'links (accent) on the mint wash',       ratio( $t['accent'], $t['mint'] ),    4.5 );
want( 'button label: white on accent',         ratio( $t['white'],  $t['accent'] ),  4.5 );
want( 'button hover: white on accent-deep',    ratio( $t['white'],  $t['accent-deep'] ), 4.5 );
want( 'body text on the mint wash',            ratio( $t['text'],   $t['mint'] ),    4.5 );

echo "\non the dark surfaces (header bar, footer, newsletter card)\n";
want( 'glow teal on ink',                      ratio( $t['glow'],   $t['ink'] ),     4.5 );
want( 'dark-card button: ink on glow',         ratio( $t['ink'],    $t['glow'] ),    4.5 );
want( 'white on ink',                          ratio( $t['white'],  $t['ink'] ),     4.5 );

echo "\nthe warm complement is DARK-TEXT-ONLY (this is the rationed accent)\n";
want( 'ink on coral',                          ratio( $t['ink'],    $t['coral'] ),   4.5 );
want( 'ink on amber',                          ratio( $t['ink'],    $t['amber'] ),   4.5 );
// These two are the trap the palette comment warns about. They must FAIL to be
// readable, which is why the test asserts they do: if a future retune made
// white legible on coral, the "never white on warm" rule would be stale.
want( 'white on coral stays unusable (by design)', ratio( $t['white'], $t['coral'] ), 4.5, true );
want( 'white on amber stays unusable (by design)', ratio( $t['white'], $t['amber'] ), 4.5, true );

echo "\nnon-text: brand teal is for fills and rules, 3:1 is the bar\n";
want( 'brand teal against white',              ratio( $t['brand'],  $t['white'] ),   3.0 );
// And the reason --v-accent exists at all: the brand teal must NOT be used for
// small text. If it ever clears 4.5:1 the two-token split is no longer needed.
want( 'brand teal is NOT small-text safe',     ratio( $t['brand'],  $t['white'] ),   4.5, true );

echo "\nthe canvas stays a wash, not a block of colour\n";
want( 'mint is close enough to white to read as a tint', ratio( $t['mint'], $t['white'] ), 1.35, true );
want( 'hairlines stay hairlines',              ratio( $t['hair'],   $t['white'] ),   1.6, true );

printf( "\n%s\n", $fail ? "$fail contrast assertion(s) failed" : 'palette holds' );
exit( $fail ? 1 : 0 );
