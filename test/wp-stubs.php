<?php
/**
 * A minimal WordPress stand-in, enough to RENDER this theme's templates so the
 * markup they produce can be inspected without a database.
 *
 * This is not a WordPress emulator and it is not trying to be. It exists to
 * catch the class of mistake that only shows up when a template actually runs:
 * a function called with the wrong arity, a variable that is never set, an
 * unclosed tag, an unescaped value. Anything that depends on real WordPress
 * behaviour (the loop, rewrites, the database) is out of scope here and is
 * verified on the deployed site instead.
 */

define( 'ABSPATH', __DIR__ . '/wp/' );
define( 'DATE_W3C_FMT', 'c' );

/* --- the fixture ---------------------------------------------------------- */
$GLOBALS['vt'] = array(
	'title'     => 'How cohorts beat courses for retention',
	'excerpt'   => 'Self-paced courses lose people in week two. Cohorts hold them, and the reason is not the content.',
	'content'   => "<p>Lorem ipsum <a href=\"https://example.com\">with a link</a>.</p>\n<h2>The drop-off</h2>\n<p>Body text.</p>\n<figure class=\"wp-block-image\"><img src=\"/x.png\" alt=\"A chart\"></figure>\n<h2>What changes</h2>\n<p>More body text.</p>",
	'permalink' => 'https://vectore.io/blog/cohorts-beat-courses/',
	'thumb'     => true,
	'cats'      => array( (object) array( 'term_id' => 4, 'name' => 'Community' ) ),
	'tags'      => array( (object) array( 'term_id' => 9, 'name' => 'retention' ) ),
);

/* --- view flags, set by the runner ---------------------------------------- */
$GLOBALS['vview'] = 'home';
function vt_view( $v ) { return $GLOBALS['vview'] === $v; }

/* --- the loop ------------------------------------------------------------- */
$GLOBALS['vposts'] = 3;
function have_posts() { return $GLOBALS['vposts'] > 0; }
function the_post() { $GLOBALS['vposts']--; }
function get_the_ID() { return 101; }
function get_post( $p = null ) { return (object) array( 'post_parent' => 0, 'post_content' => $GLOBALS['vt']['content'] ); }

/* --- escaping: the real behaviour, because output correctness is the point -- */
function esc_html( $t )  { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t )  { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u )   { return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $t ) { return esc_html( $t ); }
function wp_kses_post( $t ) { return $t; }
function wp_strip_all_tags( $t ) { return strip_tags( (string) $t ); }
function strip_shortcodes( $t ) { return $t; }

/* --- i18n ----------------------------------------------------------------- */
function __( $t, $d = null ) { return $t; }
function _e( $t, $d = null ) { echo $t; }
function _n( $s, $p, $n, $d = null ) { return $n === 1 ? $s : $p; }
function esc_html__( $t, $d = null ) { return esc_html( $t ); }
function esc_attr__( $t, $d = null ) { return esc_attr( $t ); }
function esc_html_e( $t, $d = null ) { echo esc_html( $t ); }
function esc_attr_e( $t, $d = null ) { echo esc_attr( $t ); }
function load_theme_textdomain( $d, $p ) { return true; }

/* --- hooks: recorded, not run --------------------------------------------- */
$GLOBALS['vhooks'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['vhooks'][] = array( 'action', $h, $cb ); }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['vhooks'][] = array( 'filter', $h, $cb ); }
function remove_action( $h, $cb, $p = 10 ) {}
function remove_filter( $h, $cb, $p = 10 ) {}
function apply_filters( $h, $v ) { return $v; }
function do_action( $h ) {}
function add_theme_support() {}
function add_image_size() {}
function add_editor_style() {}
function register_nav_menus() {}
function __return_false() { return false; }

/* --- post data ------------------------------------------------------------ */
function the_title() { echo esc_html( $GLOBALS['vt']['title'] ); }
function get_the_title( $p = null ) { return $GLOBALS['vt']['title']; }
function the_title_attribute( $a = array() ) {
	$t = esc_attr( $GLOBALS['vt']['title'] );
	if ( ! empty( $a['echo'] ) || ! isset( $a['echo'] ) ) { if ( isset( $a['echo'] ) && ! $a['echo'] ) { return $t; } echo $t; return; }
	return $t;
}
function the_permalink() { echo esc_url( $GLOBALS['vt']['permalink'] ); }
function get_permalink( $p = null ) { return $GLOBALS['vt']['permalink']; }
function the_content() { echo $GLOBALS['vt']['content']; }
function get_the_content() { return $GLOBALS['vt']['content']; }
function get_the_excerpt( $p = null ) { return $GLOBALS['vt']['excerpt']; }
function has_excerpt( $p = null ) { return true; }
function get_post_field( $f, $p = null ) { return 'post_content' === $f ? $GLOBALS['vt']['content'] : 7; }
/** True for the machine-readable formats the theme uses: 'c' and DATE_W3C. */
function vt_is_iso( $f ) { return 'c' === $f || DATE_W3C === $f; }
function get_the_date( $f = '' ) { return vt_is_iso( $f ) ? '2026-08-01T09:00:00+00:00' : 'August 1, 2026'; }
function get_the_modified_date( $f = '' ) { return vt_is_iso( $f ) ? '2026-08-20T11:30:00+00:00' : 'August 20, 2026'; }
function get_the_category( $p = null ) { return $GLOBALS['vt']['cats']; }
function get_the_tags( $p = null ) { return $GLOBALS['vt']['tags']; }
function wp_get_post_categories( $p ) { return array( 4 ); }
function get_categories( $a = array() ) { return $GLOBALS['vt']['cats']; }
function get_category_link( $id ) { return 'https://vectore.io/blog/category/community/'; }
function get_tag_link( $id ) { return 'https://vectore.io/blog/tag/retention/'; }
function get_term_link( $t ) { return 'https://vectore.io/blog/category/community/'; }
function get_author_posts_url( $id ) { return 'https://vectore.io/blog/author/james/'; }
function get_the_author_meta( $f, $id = null ) { return 'James Njoya'; }
function get_avatar( $id, $s = 96, $d = '', $alt = '', $a = array() ) {
	return '<img src="https://example.com/a.png" width="40" height="40" alt="">';
}
function has_post_thumbnail( $p = null ) { return $GLOBALS['vt']['thumb']; }
function get_post_thumbnail_id( $p = null ) { return 55; }
function the_post_thumbnail( $size = '', $attr = array() ) {
	$extra = '';
	foreach ( (array) $attr as $k => $v ) { $extra .= ' ' . $k . '="' . esc_attr( $v ) . '"'; }
	echo '<img src="https://example.com/cover.jpg" width="1600" height="900"' . $extra . '>';
}
function wp_get_attachment_image_src( $id, $size = '' ) { return array( 'https://example.com/cover.jpg', 1600, 900 ); }
function post_class( $c = '' ) { echo 'class="' . esc_attr( is_array( $c ) ? implode( ' ', $c ) : $c ) . ' post"'; }
function body_class( $c = '' ) { echo 'class="' . esc_attr( $GLOBALS['vview'] ) . '"'; }
function get_previous_post() { return (object) array( 'ID' => 100 ); }
function get_next_post() { return (object) array( 'ID' => 102 ); }
function wp_link_pages( $a = array() ) {}
function wp_reset_postdata() {}
function str_word_count_safe( $s ) { return str_word_count( $s ); }

/* --- conditionals --------------------------------------------------------- */
function is_home()      { return vt_view( 'home' ); }
function is_front_page(){ return vt_view( 'home' ); }
function is_singular( $t = '' ) { return vt_view( 'single' ) || vt_view( 'page' ); }
function is_single()    { return vt_view( 'single' ); }
function is_page()      { return vt_view( 'page' ); }
function is_archive()   { return vt_view( 'archive' ); }
function is_category()  { return vt_view( 'archive' ); }
function is_tag()       { return false; }
function is_tax()       { return false; }
function is_author()    { return false; }
function is_search()    { return vt_view( 'search' ); }
function is_paged()     { return false; }
function is_attachment(){ return false; }
function is_404()       { return vt_view( '404' ); }
function comments_open(){ return false; }
function is_wp_error( $t ) { return false; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }

/* --- site data ------------------------------------------------------------ */
function home_url( $p = '/' ) { return 'https://vectore.io/blog' . ( '/' === $p ? '/' : $p ); }
function admin_url( $p = '' ) { return 'https://vectore.io/blog/wp-admin/' . $p; }
function get_bloginfo( $k = '' ) {
	return array(
		'name'        => 'The Vectore Blog',
		'description' => 'Notes on building a learning business people actually show up for.',
		'charset'     => 'UTF-8',
		'version'     => '6.8',
	)[ $k ] ?? '';
}
function bloginfo( $k = '' ) { echo esc_html( get_bloginfo( $k ) ); }
function wp_get_document_title() { return get_the_title() . ' | The Vectore Blog'; }
function language_attributes() { echo 'lang="en-US"'; }
function get_option( $k, $d = false ) { return $d; }
function update_option( $k, $v ) { return true; }
function get_search_query() { return 'cohorts'; }
function get_search_link() { return 'https://vectore.io/blog/?s=cohorts'; }
function get_queried_object() { return (object) array( 'term_id' => 4, 'name' => 'Community' ); }
function single_term_title( $p = '', $d = true ) { return $d ? 'Community' : ( print 'Community' ); }
function get_the_archive_title() { return 'Community'; }
function get_the_archive_description() { return '<p>Everything we have written about community.</p>'; }
function term_description() { return 'Everything we have written about community.'; }
function has_nav_menu( $l ) { return false; }
function wp_nav_menu( $a = array() ) {}
function paginate_links( $a = array() ) {
	return array( '<span class="page-numbers current">1</span>', '<a class="page-numbers" href="#">2</a>' );
}
function get_search_form() { include VECTORE_BLOG_DIR . '/searchform.php'; }
function wp_create_nonce( $a ) { return 'testnonce'; }

/* --- template loading ----------------------------------------------------- */
function get_header() { include VECTORE_BLOG_DIR . '/header.php'; }
function get_footer() { include VECTORE_BLOG_DIR . '/footer.php'; }
function get_template_part( $slug, $name = null, $args = array() ) {
	$f = VECTORE_BLOG_DIR . '/' . $slug . '.php';
	if ( file_exists( $f ) ) { include $f; }
}
function get_template_directory() { return VECTORE_BLOG_DIR; }
function get_template_directory_uri() { return 'https://vectore.io/blog/wp-content/themes/vectore-blog'; }
function get_stylesheet_uri() { return get_template_directory_uri() . '/style.css'; }
function wp_head() { echo "<!-- wp_head -->\n"; }
function wp_footer() { echo "<!-- wp_footer -->\n"; }
function wp_body_open() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}

/* --- misc ----------------------------------------------------------------- */
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_list_pluck( $l, $f ) { return array_map( fn( $i ) => is_object( $i ) ? $i->$f : $i[ $f ], $l ); }
function remove_query_arg( $k, $u = '' ) { return $u; }
function sanitize_email( $e ) { return $e; }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function wp_unslash( $v ) { return $v; }
function wp_safe_redirect( $u, $s = 302 ) {}
function wp_remote_post( $u, $a = array() ) { return array( 'response' => array( 'code' => 200 ) ); }
function wp_remote_retrieve_response_code( $r ) { return 200; }
function check_ajax_referer( $a, $q = false, $d = true ) { return true; }
function wp_send_json_success( $d = null ) {}
function wp_send_json_error( $d = null, $c = null ) {}
function wp_enqueue_scripts() {}

class WP_Query {
	private $n;
	public function __construct( $args = array() ) { $this->n = $args['posts_per_page'] ?? 3; }
	public function have_posts() { return $this->n > 0; }
	public function the_post() { $this->n--; }
}
