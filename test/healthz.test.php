<?php
/**
 * The health-check path.
 *
 * Railway marks a deploy failed when this does not answer 2xx, so a mismatch
 * between the path Railway probes and the path the container answers on takes
 * down deploys of a site that is otherwise working. The two live in different
 * files (blog/railway.json and the ops mu-plugin) and are checked against each
 * other here.
 */

$fail = 0; $checks = 0;
function check( $label, $cond, $detail = '' ) {
	global $fail, $checks;
	$checks++;
	if ( $cond ) { printf( "  ok    %s\n", $label ); }
	else { printf( "  FAIL  %s%s\n", $label, $detail ? "\n        $detail" : '' ); $fail++; }
}

// --- what Railway probes -----------------------------------------------------
$railway = json_decode( file_get_contents( __DIR__ . '/../blog/railway.json' ), true );
$probe   = $railway['deploy']['healthcheckPath'] ?? '';

echo "\nwhat Railway probes\n";
check( 'railway.json declares a health check path', '' !== $probe, 'none found' );

// --- what the container answers on -------------------------------------------
// The mu-plugin derives the path from WP_HOME. Replicate only the WP functions
// it needs, then load the real file and drive its `init` callback.
define( 'ABSPATH', __DIR__ );
define( 'WP_HOME', 'https://vectore.io/blog' );

$GLOBALS['hooks'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) {}
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function home_url( $p = '/' ) { return WP_HOME . $p; }
function status_header( $c ) {}
function get_option( $k, $d = false ) { return $d; }
function update_option( $k, $v ) { return true; }
function __return_false() { return false; }
function wp_safe_redirect( $u, $s = 302 ) {}
function get_post() { return (object) array( 'post_parent' => 0 ); }
function get_permalink( $p = null ) { return WP_HOME . '/x/'; }
function is_attachment() { return false; }
function get_bloginfo( $k = '' ) { return '6.8'; }
function remove_query_arg( $k, $u = '' ) { return $u; }

require __DIR__ . '/../blog/config/mu-plugins/vectore-ops.php';

echo "\nthe path the container derives\n";
check( 'the base path comes out of WP_HOME', '/blog' === vectore_ops_base_path(), vectore_ops_base_path() );

// The handler calls exit(). Run each probe in its own process so the exit does
// not take the test with it.
function probe_in_subprocess( $uri ) {
	$php = <<<'PHP_CODE'
define('ABSPATH', __DIR__);
define('WP_HOME', 'https://vectore.io/blog');
function add_action($h,$cb,$p=10,$a=1){ $GLOBALS['hooks'][$h][]=$cb; }
function add_filter($h,$cb,$p=10,$a=1){}
function wp_parse_url($u,$c=-1){ return parse_url($u,$c); }
function home_url($p='/'){ return WP_HOME.$p; }
function status_header($c){}
function get_option($k,$d=false){ return $d; }
function update_option($k,$v){ return true; }
function __return_false(){ return false; }
function wp_safe_redirect($u,$s=302){}
function get_post(){ return (object)array('post_parent'=>0); }
function get_permalink($p=null){ return WP_HOME.'/x/'; }
function is_attachment(){ return false; }
function get_bloginfo($k=''){ return '6.8'; }
function remove_query_arg($k,$u=''){ return $u; }
require getenv('OPS_FILE');
$_SERVER['REQUEST_URI'] = getenv('PROBE_URI');
foreach ($GLOBALS['hooks']['init'] ?? array() as $cb) { $cb(); }
echo 'NOT_HANDLED';
PHP_CODE;

	$tmp = tempnam( sys_get_temp_dir(), 'probe' ) . '.php';
	file_put_contents( $tmp, "<?php\n" . $php );
	$env = 'OPS_FILE=' . escapeshellarg( __DIR__ . '/../blog/config/mu-plugins/vectore-ops.php' )
		. ' PROBE_URI=' . escapeshellarg( $uri );
	$out = shell_exec( $env . ' php ' . escapeshellarg( $tmp ) . ' 2>/dev/null' );
	unlink( $tmp );
	return str_contains( (string) $out, '"ok":true' );
}

echo "\ndoes the container answer where Railway knocks\n";
// This is the check that matters: the two files must agree.
check( "railway.json probes $probe and the container answers there",
	probe_in_subprocess( $probe ), "no health response at $probe" );
check( 'a query string does not break the match', probe_in_subprocess( $probe . '?x=1' ) );
check( 'a bare /healthz is NOT claimed (it is outside this install)',
	! probe_in_subprocess( '/healthz' ) );
check( 'an ordinary page is not swallowed by the health handler',
	! probe_in_subprocess( '/blog/hello-world/' ) );

printf( "\n%d checks, %d failed\n", $checks, $fail );
exit( $fail ? 1 : 0 );
