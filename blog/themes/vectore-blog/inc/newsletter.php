<?php
/**
 * Newsletter signup.
 *
 * The blog does not keep its own list. It forwards a signup to the SAME
 * endpoint the marketing site's waitlist form posts to (server.js,
 * POST /api/waitlist), so there is one list and one source of truth for who
 * has signed up. Set VECTORE_WAITLIST_ENDPOINT in wp-config.php.
 *
 * If the endpoint is not configured, the handler says so plainly rather than
 * accepting an address it is going to drop on the floor.
 *
 * @package Vectore_Blog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'VECTORE_WAITLIST_ENDPOINT' ) ) {
	define( 'VECTORE_WAITLIST_ENDPOINT', '' );
}

add_action( 'wp_ajax_vectore_newsletter', 'vectore_blog_handle_newsletter' );
add_action( 'wp_ajax_nopriv_vectore_newsletter', 'vectore_blog_handle_newsletter' );

/**
 * Accept a signup and forward it.
 */
function vectore_blog_handle_newsletter() {
	// The nonce is the cheap half of the spam defence; the honeypot on the
	// client is the other half. A nonce failure here is almost always a reader
	// on a page cached past the nonce lifetime, so the message says what to do
	// rather than accusing them of anything.
	if ( ! check_ajax_referer( 'vectore_newsletter', 'nonce', false ) ) {
		wp_send_json_error(
			array( 'message' => __( 'This form expired. Please refresh the page and try again.', 'vectore-blog' ) ),
			403
		);
	}

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'vectore-blog' ) ), 400 );
	}

	if ( ! VECTORE_WAITLIST_ENDPOINT ) {
		wp_send_json_error(
			array( 'message' => __( 'Signups are not configured yet. Please try again later.', 'vectore-blog' ) ),
			503
		);
	}

	$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'blog';

	$response = wp_remote_post(
		VECTORE_WAITLIST_ENDPOINT,
		array(
			'timeout' => 8,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'email' => $email, 'source' => $source ) ),
		)
	);

	if ( is_wp_error( $response ) ) {
		// Log the real reason for an operator; tell the reader something true
		// and useful without leaking the upstream host or error.
		error_log( '[vectore-newsletter] ' . $response->get_error_message() );
		wp_send_json_error( array( 'message' => __( 'We could not reach the signup service. Please try again.', 'vectore-blog' ) ), 502 );
	}

	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( $code >= 200 && $code < 300 ) {
		wp_send_json_success( array( 'message' => __( 'You are on the list. Watch your inbox.', 'vectore-blog' ) ) );
	}

	if ( 400 === $code ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'vectore-blog' ) ), 400 );
	}

	error_log( '[vectore-newsletter] upstream returned ' . $code );
	wp_send_json_error( array( 'message' => __( 'Something went wrong. Please try again.', 'vectore-blog' ) ), 502 );
}

/**
 * The signup form. One markup, two skins: the light one on a page, the dark one
 * inside .v-nlcard. Only the wrapper class differs.
 *
 * @param string $source Attribution string sent with the signup.
 */
function vectore_blog_newsletter_form( $source = 'blog' ) {
	?>
	<div class="v-nl">
		<form class="v-nl__form"
		      method="post"
		      action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		      data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
		      data-nonce="<?php echo esc_attr( wp_create_nonce( 'vectore_newsletter' ) ); ?>"
		      data-source="<?php echo esc_attr( $source ); ?>">
			<input type="hidden" name="action" value="vectore_newsletter">
			<label class="screen-reader-text" for="v-nl-<?php echo esc_attr( $source ); ?>">
				<?php esc_html_e( 'Email address', 'vectore-blog' ); ?>
			</label>
			<input class="v-nl__input"
			       id="v-nl-<?php echo esc_attr( $source ); ?>"
			       type="email"
			       name="email"
			       required
			       autocomplete="email"
			       placeholder="<?php esc_attr_e( 'you@company.com', 'vectore-blog' ); ?>">
			<span class="v-nl__hp" aria-hidden="true">
				<input type="text" name="website" tabindex="-1" autocomplete="off">
			</span>
			<button class="v-nl__btn" type="submit"><?php esc_html_e( 'Join the list', 'vectore-blog' ); ?></button>
		</form>
		<p class="v-nl__status" role="status" aria-live="polite"></p>
	</div>
	<?php
}
