<?php
/**
 * Template tags — the small pieces of markup that more than one view needs.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The byline.
 *
 * 🔴 This is the SINGLE source of a post's date. It is visible, it is
 * machine-readable, and it is always the MODIFIED date, which is what a reader
 * checking whether an article is still current actually wants. Do not add a
 * second date element to a post view: two dates on one page is how a search
 * engine ends up showing the wrong one.
 */
function vectore_blog_byline() {
	$author_id = (int) get_post_field( 'post_author', get_the_ID() );
	?>
	<div class="v-byline">
		<div class="v-byline__avatar">
			<?php echo get_avatar( $author_id, 80, '', '', array( 'height' => 40, 'width' => 40 ) ); ?>
		</div>
		<div class="v-byline__text">
			<span class="v-byline__by">
				<?php
				printf(
					/* translators: %s: author name, linked. */
					esc_html__( 'Written by %s', 'vectore-blog' ),
					'<a href="' . esc_url( get_author_posts_url( $author_id ) ) . '" rel="author">'
						. esc_html( get_the_author_meta( 'display_name', $author_id ) )
						. '</a>'
				);
				?>
			</span>
			<span class="v-byline__sep" aria-hidden="true"></span>
			<span class="v-byline__date">
				<?php
				printf(
					/* translators: %s: date the post was last updated. */
					esc_html__( 'Last updated %s', 'vectore-blog' ),
					'<time datetime="' . esc_attr( get_the_modified_date( DATE_W3C ) ) . '">'
						. esc_html( get_the_modified_date() )
						. '</time>'
				);
				?>
			</span>
		</div>
	</div>
	<?php
}

/**
 * Breadcrumbs.
 *
 * 🔴 The SINGLE source of the visible trail AND of the BreadcrumbList schema
 * (inc/seo.php builds its JSON-LD from this same trail). If an SEO plugin is
 * ever installed, turn its breadcrumb OFF rather than adding a second one.
 *
 * @return array<int, array{label:string, url:string}> The trail, for reuse by the schema.
 */
function vectore_blog_crumb_trail() {
	$trail = array(
		array(
			'label' => __( 'Home', 'vectore-blog' ),
			'url'   => VECTORE_SITE_URL,
		),
		array(
			'label' => __( 'Blog', 'vectore-blog' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( ! empty( $cats ) ) {
			$trail[] = array(
				'label' => $cats[0]->name,
				'url'   => get_category_link( $cats[0]->term_id ),
			);
		}
		$trail[] = array( 'label' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_singular() ) {
		$trail[] = array( 'label' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$trail[] = array( 'label' => single_term_title( '', false ), 'url' => get_term_link( get_queried_object() ) );
	} elseif ( is_search() ) {
		$trail[] = array(
			'label' => sprintf( __( 'Search: %s', 'vectore-blog' ), get_search_query() ),
			'url'   => get_search_link(),
		);
	}

	return $trail;
}

/**
 * Render the trail.
 */
function vectore_blog_breadcrumbs() {
	$trail = vectore_blog_crumb_trail();
	if ( count( $trail ) < 2 ) {
		return;
	}
	$last = count( $trail ) - 1;
	?>
	<nav class="v-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'vectore-blog' ); ?>">
		<ol>
			<?php foreach ( $trail as $i => $crumb ) : ?>
				<li>
					<?php if ( $i === $last ) : ?>
						<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}

/**
 * One post card for the grid.
 *
 * The whole card is clickable via a stretched pseudo-element on the title link,
 * which keeps exactly ONE link in the accessibility tree. Wrapping the card in
 * a second anchor would make a screen reader announce the title twice and would
 * swallow the category chip.
 *
 * @param string $variant Extra modifier class, e.g. 'v-card--lead'.
 */
function vectore_blog_card( $variant = '' ) {
	$cats = get_the_category();
	$size = ( 'v-card--lead' === $variant ) ? 'vectore-cover' : 'vectore-card';
	?>
	<article <?php post_class( trim( 'v-card ' . $variant ) ); ?>>
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="v-card__media">
				<?php
				the_post_thumbnail(
					$size,
					array(
						'loading' => 'lazy',
						'alt'     => the_title_attribute( array( 'echo' => false ) ),
					)
				);
				?>
			</div>
		<?php else : ?>
			<div class="v-card__media v-card__media--empty" aria-hidden="true"></div>
		<?php endif; ?>

		<div class="v-card__body">
			<?php if ( ! empty( $cats ) ) : ?>
				<a class="v-card__term" href="<?php echo esc_url( get_category_link( $cats[0]->term_id ) ); ?>">
					<?php echo esc_html( $cats[0]->name ); ?>
				</a>
			<?php endif; ?>

			<h2 class="v-card__title">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
			</h2>

			<p class="v-card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>

			<div class="v-card__meta">
				<time datetime="<?php echo esc_attr( get_the_modified_date( DATE_W3C ) ); ?>">
					<?php echo esc_html( get_the_modified_date() ); ?>
				</time>
				<span class="v-card__dot" aria-hidden="true"></span>
				<span><?php echo esc_html( vectore_blog_reading_time() ); ?></span>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Reading time, from the rendered word count.
 *
 * 200 wpm, floored at one minute. Deliberately approximate: the number is a
 * reader's expectation-setter, not a measurement, and rounding it to something
 * more precise would only invite an argument about the figure.
 *
 * @param int|null $post_id Defaults to the current post.
 * @return string
 */
function vectore_blog_reading_time( $post_id = null ) {
	$content = get_post_field( 'post_content', $post_id ?: get_the_ID() );
	$words   = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	$minutes = max( 1, (int) ceil( $words / 200 ) );

	/* translators: %d: estimated reading time in minutes. */
	return sprintf( _n( '%d min read', '%d min read', $minutes, 'vectore-blog' ), $minutes );
}

/**
 * Share links.
 *
 * Plain anchors to each network's share endpoint: no third-party SDK, no
 * iframe, no tracking pixel, and nothing that needs JavaScript to render. The
 * copy-link button is the one exception and it degrades to a normal link.
 */
function vectore_blog_share() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );

	$links = array(
		'X'        => array(
			'href' => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
			'path' => 'M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.65l-5.22-6.82-5.97 6.82H1.66l7.73-8.84L1.24 2.25h6.82l4.71 6.23 5.47-6.23Z',
		),
		'LinkedIn' => array(
			'href' => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
			'path' => 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM2.4 21.5h5.16V9.75H2.4V21.5Zm7.9 0h5.16v-6.2c0-1.63.31-3.2 2.32-3.2 1.99 0 2.02 1.86 2.02 3.3v6.1h5.16v-7.1c0-4.36-.94-7.16-6.03-7.16-2.45 0-4.09 1.34-4.76 2.62h-.07V9.75H10.3V21.5Z',
		),
		'Facebook' => array(
			'href' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
			'path' => 'M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.9h2.54V9.85c0-2.52 1.5-3.91 3.77-3.91 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.78-1.63 1.57v1.89h2.78l-.45 2.9h-2.33V22c4.78-.79 8.44-4.94 8.44-9.94Z',
		),
	);
	?>
	<div class="v-share">
		<p class="v-share__label"><?php esc_html_e( 'Share', 'vectore-blog' ); ?></p>
		<ul class="v-share__list">
			<?php foreach ( $links as $name => $link ) : ?>
				<li>
					<a class="v-share__link"
					   href="<?php echo esc_url( $link['href'] ); ?>"
					   target="_blank"
					   rel="noopener noreferrer"
					   aria-label="<?php echo esc_attr( sprintf( __( 'Share on %s', 'vectore-blog' ), $name ) ); ?>">
						<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
							<path d="<?php echo esc_attr( $link['path'] ); ?>"/>
						</svg>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}
