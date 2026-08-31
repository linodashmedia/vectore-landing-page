<?php
/**
 * The search form.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="v-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="v-search-field"><?php esc_html_e( 'Search posts', 'vectore-blog' ); ?></label>
	<input class="v-search__input"
	       id="v-search-field"
	       type="search"
	       name="s"
	       value="<?php echo esc_attr( get_search_query() ); ?>"
	       placeholder="<?php esc_attr_e( 'Search posts', 'vectore-blog' ); ?>">
	<button class="v-search__btn" type="submit"><?php esc_html_e( 'Search', 'vectore-blog' ); ?></button>
</form>
