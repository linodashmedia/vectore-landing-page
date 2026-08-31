<?php
/**
 * Verifies the X-Forwarded-Proto handling in blog/config/wp-config-extra.php
 * against the real file, one case per process (the file defines constants, so
 * it can only be loaded once).
 *
 * Usage: php test/proxy-proto.test.php "<header value or -->"
 * Prints "on" or "off" for whether WordPress will treat the request as HTTPS.
 */

$header = $argv[1] ?? '--';

if ( '--' !== $header ) {
	$_SERVER['HTTP_X_FORWARDED_PROTO'] = $header;
}

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../blog/config/wp-config-extra.php';

echo isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'on' : 'off';
