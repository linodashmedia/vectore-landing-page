<?php
/**
 * Vectore Blog — theme bootstrap.
 *
 * This theme is STANDALONE. There is no parent, and there is no design state in
 * the database: every colour, template and script is a file in this directory.
 * If it is not in Git, it is not part of the design.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VECTORE_BLOG_VERSION', '1.0.0' );
define( 'VECTORE_BLOG_DIR', get_template_directory() );
define( 'VECTORE_BLOG_URI', get_template_directory_uri() );

/**
 * The public marketing site. The blog links back to it from the header CTA, the
 * brand mark and the footer, and it is the origin the newsletter signup is
 * attributed to. Override in wp-config.php if the domain ever changes, so no
 * template has to be edited.
 */
if ( ! defined( 'VECTORE_SITE_URL' ) ) {
	define( 'VECTORE_SITE_URL', 'https://vectore.io' );
}

require_once VECTORE_BLOG_DIR . '/inc/setup.php';
require_once VECTORE_BLOG_DIR . '/inc/assets.php';
require_once VECTORE_BLOG_DIR . '/inc/template-tags.php';
require_once VECTORE_BLOG_DIR . '/inc/seo.php';
require_once VECTORE_BLOG_DIR . '/inc/llms.php';
require_once VECTORE_BLOG_DIR . '/inc/newsletter.php';
