<?php
/**
 * wp-config additions, loaded from wp-config.php by WORDPRESS_CONFIG_EXTRA.
 *
 * Everything here has to run BEFORE WordPress boots, which is why it is not a
 * plugin: by the time a plugin loads, WordPress has already decided whether the
 * request is HTTPS and where the site lives.
 *
 * @package Vectore_Blog
 */

/*
 * 🔴 THE PROXY FIX. Read this before changing anything below it.
 *
 * Railway terminates TLS at its edge and speaks plain HTTP to this container.
 * PHP therefore sees $_SERVER['HTTPS'] as unset and concludes the request is
 * insecure. WordPress compares that against the https:// site URL, decides the
 * visitor is in the wrong place, and issues a redirect to https:// — which
 * arrives back at the same container, still looking insecure, and redirects
 * again. That is the infinite redirect loop, and it takes down wp-admin first.
 *
 * The forwarded header is what actually describes the browser's connection, so
 * it is what PHP is told to believe.
 *
 * 🔴 The header is a LIST, not a value, and that detail is the whole bug. Two
 * proxies sit in front of this container (Railway's TLS edge, then the Express
 * app that owns the domain), and each appends its own hop. What arrives is:
 *
 *     X-Forwarded-Proto: https,http
 *
 * because the edge saw HTTPS from the browser and the internal hop to this
 * container is plain HTTP. A `=== 'https'` comparison against that string is
 * false, so WordPress concludes the visitor is on HTTP and redirects to HTTPS,
 * forever. Per RFC 7239 the LEFTMOST entry is the original client, so that is
 * the one to read, and reading it that way is correct for any number of
 * proxies rather than for exactly the two we happen to have today.
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) {
	$vectore_proto = strtolower( trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_PROTO'] )[0] ) );
	if ( 'https' === $vectore_proto ) {
		$_SERVER['HTTPS'] = 'on';
		$_SERVER['SERVER_PORT'] = 443;
	}
	unset( $vectore_proto );
}

/*
 * The public address. Both constants are set, and both include /blog, because
 * WordPress lives at /blog for real (see the Dockerfile's symlink) rather than
 * being rewritten into it.
 *
 * Setting these as constants also LOCKS them: the Settings > General fields go
 * read-only, so nobody can half-move the site by typing a new URL into wp-admin
 * and leaving the container serving the old one.
 */
define( 'WP_HOME',    getenv( 'VECTORE_BLOG_URL' ) ?: 'https://vectore.io/blog' );
define( 'WP_SITEURL', getenv( 'VECTORE_BLOG_URL' ) ?: 'https://vectore.io/blog' );

/** Where the marketing site lives. The theme links back to it. */
define( 'VECTORE_SITE_URL', getenv( 'VECTORE_SITE_URL' ) ?: 'https://vectore.io' );

/**
 * Where blog newsletter signups are forwarded. This is the landing page's own
 * waitlist API, so the blog does not start a second list.
 */
define( 'VECTORE_WAITLIST_ENDPOINT', getenv( 'VECTORE_WAITLIST_ENDPOINT' ) ?: '' );

/*
 * No file editing and no plugin/theme installation from wp-admin. The theme is
 * baked into the image, so anything typed into the built-in editor would be
 * silently discarded on the next deploy: better to remove the editor than to
 * let someone lose an afternoon's work to it.
 */
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', 'true' === getenv( 'VECTORE_LOCK_PLUGINS' ) );

/** Force logins and admin over TLS. Safe here: the edge always offers HTTPS. */
define( 'FORCE_SSL_ADMIN', true );

/*
 * Revisions are capped rather than disabled. Unlimited revisions on a busy
 * editor is the most common way a small WordPress database quietly becomes a
 * large one; zero revisions is how a writer loses a draft.
 */
define( 'WP_POST_REVISIONS', 10 );
define( 'EMPTY_TRASH_DAYS', 14 );
define( 'AUTOSAVE_INTERVAL', 120 );

/** Cron: see config/mu-plugins/vectore-ops.php for why this is off. */
define( 'DISABLE_WP_CRON', 'true' === getenv( 'VECTORE_DISABLE_WP_CRON' ) );

/*
 * Debug output NEVER goes to the page: a PHP notice printed into the response
 * corrupts JSON endpoints and leaks paths. It goes to the log, which on Railway
 * is stderr and therefore the deploy log.
 */
define( 'WP_DEBUG', 'true' === getenv( 'WP_DEBUG' ) );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', 'php://stderr' );
@ini_set( 'display_errors', '0' );

/** Memory. The uploads.ini limit is the floor; admin gets more for imports. */
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
