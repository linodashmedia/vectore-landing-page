<?php
/**
 * Renders the theme's templates to standalone HTML for visual review.
 *
 * Same stub harness the render test uses, so what is screenshotted is the real
 * templates and the real stylesheets, not a mock-up of them. Only the asset
 * URLs and the font link are substituted, because there is no WordPress here to
 * enqueue them.
 *
 * Output: test/preview/*.html
 */

require __DIR__ . '/wp-stubs.php';

define( 'VECTORE_BLOG_VERSION', '1.0.0' );
define( 'VECTORE_BLOG_DIR', __DIR__ . '/../blog/themes/vectore-blog' );
define( 'VECTORE_BLOG_URI', '/theme' );
define( 'VECTORE_SITE_URL', 'https://vectore.io' );
define( 'VECTORE_WAITLIST_ENDPOINT', 'https://vectore.io/api/waitlist' );

require VECTORE_BLOG_DIR . '/inc/setup.php';
require VECTORE_BLOG_DIR . '/inc/template-tags.php';
require VECTORE_BLOG_DIR . '/inc/seo.php';
require VECTORE_BLOG_DIR . '/inc/newsletter.php';

// Longer, more realistic fixture content so the reading column, the generated
// table of contents and the card excerpts all get something true to size to.
$GLOBALS['vt']['content'] = <<<'HTML'
<p>Every course platform sells the same promise: upload your lessons, open the doors, watch the completion rate climb. It does not climb. The median self-paced course finishes somewhere south of fifteen percent, and the people who drop out are rarely the ones who could not follow the material.</p>
<h2>The drop-off is social, not intellectual</h2>
<p>Week one is enthusiasm. Week two is when a learner hits the first thing they cannot do on the first try, and the only company they have is a progress bar. Nobody is waiting for them. Nothing happens if they close the tab.</p>
<blockquote><p>A cohort does not teach better than a course. It just makes quitting visible, and that turns out to be most of the battle.</p></blockquote>
<p>Put twenty people through the same material on the same weeks and the mechanics change entirely. Somebody else is stuck on the same lesson. Somebody finished it and will tell you how.</p>
<figure class="wp-block-image"><img src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=1200&q=60" alt="A group working together at a table" width="1200" height="700"></figure>
<h2>What the numbers actually say</h2>
<p>We pulled completion data across the programmes running on Vectore in the last two quarters and split it by format.</p>
<table><thead><tr><th>Format</th><th>Median completion</th><th>Returned for a second programme</th></tr></thead><tbody><tr><td>Self-paced course</td><td>14%</td><td>9%</td></tr><tr><td>Course plus an open forum</td><td>21%</td><td>17%</td></tr><tr><td>Cohort with fixed dates</td><td>63%</td><td>48%</td></tr></tbody></table>
<p>The middle row is the interesting one. Bolting a forum onto a course barely moves the number, which is the part most platforms get wrong.</p>
<h2>What this means if you are building one</h2>
<p>Start with the calendar, not the curriculum. Pick the dates first and let the material fit them. Then make the room before you make the lessons.</p>
<ul><li>Fixed start and end dates, published before enrolment opens.</li><li>One live session a week, even a short one.</li><li>A place to be stuck out loud that is not a support inbox.</li></ul>
<h2>Where we go from here</h2>
<p>None of this argues that self-paced material is worthless. It argues that the material was never the scarce thing.</p>
HTML;

$views = array(
	'blog-index'    => array( 'index.php',  'home',    3 ),
	'single-post'   => array( 'single.php', 'single',  1 ),
	'category'      => array( 'archive.php','archive', 3 ),
);

@mkdir( __DIR__ . '/preview', 0777, true );

// The webfonts are served from test/preview/fonts rather than from Google, so
// a preview renders with the REAL display face even with no network. That
// matters more than it sounds: the footer wordmark is sized in vw and nowrap
// inside an overflow:hidden band, so a wider fallback face crops it and the
// preview shows a bug the live site does not have.
$fonts = '<link rel="stylesheet" href="/fonts/fonts.css">';

$styles = '<link rel="stylesheet" href="/theme/style.css">'
	. '<link rel="stylesheet" href="/theme/assets/css/cards.css">';

foreach ( $views as $name => $spec ) {
	list( $tpl, $view, $posts ) = $spec;

	$GLOBALS['vview']  = $view;
	$GLOBALS['vposts'] = $posts;

	ob_start();
	include VECTORE_BLOG_DIR . '/' . $tpl;
	$html = ob_get_clean();

	$head = $fonts . $styles;
	if ( 'single' === $view ) {
		$head .= '<link rel="stylesheet" href="/theme/assets/css/single.css">';
	}
	$head .= '<script src="/theme/assets/js/header.js" defer></script>';
	if ( 'single' === $view ) {
		$head .= '<script src="/theme/assets/js/single-post.js" defer></script>';
	}

	$html = str_replace( '<!-- wp_head -->', $head, $html );

	file_put_contents( __DIR__ . "/preview/$name.html", $html );
	printf( "  wrote preview/%s.html (%d KB)\n", $name, strlen( $html ) / 1024 );
}
