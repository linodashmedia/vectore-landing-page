<?php
/**
 * Plugin Name: Vectore ops
 * Description: Operational defaults for the Vectore blog that belong in code rather than in the database. Must-use, so it cannot be deactivated by accident.
 * Version: 1.0.0
 *
 * Anything in here is a decision we want to survive a database restore. Settings
 * that a person should be able to change live in wp-admin instead.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The path this install is served from, with no trailing slash: '/blog' in
 * production, '' if it is ever moved to a domain root.
 *
 * @return string
 */
function vectore_ops_base_path() {
	$path = (string) wp_parse_url( defined( 'WP_HOME' ) ? WP_HOME : home_url(), PHP_URL_PATH );
	return rtrim( $path, '/' );
}

/**
 * Health check for Railway.
 *
 * Deliberately does NOT touch the database. A health endpoint that queries
 * MySQL turns a slow database into a failed deploy and a restart loop, which is
 * the opposite of what you want while the database is the thing struggling.
 * Railway only needs to know the PHP worker is answering.
 *
 * 🔴 The path is DERIVED from WP_HOME, not written out. The proxy passes the
 * /blog prefix through untouched, so what actually arrives here is
 * `/blog/healthz`, and an earlier version of this matched a bare `/healthz`.
 * It never fired, WordPress served its 404 instead, Railway read the non-2xx as
 * a failed health check, and every deploy would have been marked failed while
 * the site itself was working perfectly. Deriving it means the check follows
 * the install if it ever moves.
 */
add_action( 'init', function () {
	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$path = strtok( $_SERVER['REQUEST_URI'], '?' );

	if ( vectore_ops_base_path() . '/healthz' === $path ) {
		header( 'Content-Type: application/json' );
		header( 'Cache-Control: no-store' );
		status_header( 200 );
		echo '{"ok":true}';
		exit;
	}
}, 0 );

/**
 * Strip the WordPress version from asset URLs.
 *
 * Not security theatre: the theme versions its own assets by filemtime, so
 * core's ?ver=6.8 on top of that is a second, less accurate cache key on the
 * same file.
 */
add_filter( 'style_loader_src', 'vectore_ops_strip_core_ver', 20 );
add_filter( 'script_loader_src', 'vectore_ops_strip_core_ver', 20 );
function vectore_ops_strip_core_ver( $src ) {
	if ( strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) !== false ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}

/**
 * Disable pingbacks and trackbacks on new posts. They are a spam vector with no
 * remaining upside, and turning them off per-post is something everyone forgets.
 */
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'xmlrpc_enabled', '__return_false' );

/**
 * Point core's sitemap and feeds at the public URL, and keep the sitemap free of
 * views nobody should land on from a search result.
 */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	// Author and user archives on a two-author blog are thin duplicates of the
	// post list. Excluded from the sitemap; still reachable, still indexable if
	// someone links to one.
	return ( 'users' === $name ) ? false : $provider;
}, 10, 2 );

/**
 * Attachment pages are a page-per-image with no content on them. Redirect each
 * to the post it belongs to, or to the file itself when it is an orphan.
 */
add_action( 'template_redirect', function () {
	if ( ! is_attachment() ) {
		return;
	}
	$parent = (int) get_post()->post_parent;
	wp_safe_redirect( $parent ? get_permalink( $parent ) : home_url( '/' ), 301 );
	exit;
} );

/**
 * A fresh install ships with "Just another WordPress site" and no permalink
 * structure. Both are set once, on first boot, so the very first URL a crawler
 * sees is already the permanent one. Guarded by an option so an editor's later
 * change is never overwritten.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'vectore_bootstrapped' ) ) {
		return;
	}

	if ( '' === get_option( 'permalink_structure' ) ) {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();
	}

	if ( 'Just another WordPress site' === get_option( 'blogdescription' ) ) {
		update_option( 'blogdescription', 'Notes on building a learning business people actually show up for.' );
	}

	update_option( 'default_ping_status', 'closed' );
	update_option( 'vectore_bootstrapped', 1 );
} );
