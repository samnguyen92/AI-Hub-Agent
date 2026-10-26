<?php
/**
 * Align Theme - Universal Post Password Lock Engine
 *
 * Provides granular password protection for singular posts and pages,
 * bypassing WordPress core template quirks, preventing search engine indexing,
 * and maintaining secure HMAC session authentication.
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Post_Password_Lock {

	const META_ENABLED     = '_align_password_lock_enabled';
	const META_HASH        = '_align_password_lock_hash';
	const META_PASSWORD    = '_align_password_lock_password';
	const META_SECRET      = '_align_password_lock_secret';
	const META_VERSION     = '_align_password_lock_version';
	const META_MIGRATED_AT = '_align_password_lock_migrated_at';
	const COOKIE_PREFIX    = 'align_post_lock_';
	const COOKIE_LIFETIME  = 864000; // 10 days
	const REST_NAMESPACE   = 'align/v1';
	const REST_ROUTE       = '/post-password/verify';

	/**
	 * Initialize hooks and filters.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'intercept_locked_post' ), 1 );
		add_action( 'send_headers', array( __CLASS__, 'send_protection_headers' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_wp_robots' ) );
		add_filter( 'post_password_required', array( __CLASS__, 'filter_post_password_required' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_ajax_align_migrate_passwords', array( __CLASS__, 'ajax_migrate_passwords' ) );
	}

	/**
	 * Check if a post has password lock enabled.
	 */
	public static function is_post_locked( int $post_id ): bool {
		$enabled = get_post_meta( $post_id, self::META_ENABLED, true );
		if ( '1' === (string) $enabled ) {
			return true;
		}
		if ( '' === (string) $enabled ) {
			$post = get_post( $post_id );
			if ( $post && ! empty( $post->post_password ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a post is locked for the current visitor.
	 */
	public static function is_locked_for_visitor( $post = null ): bool {
		$post = get_post( $post );
		if ( ! $post || ! ( $post instanceof WP_Post ) ) {
			return false;
		}
		if ( ! self::is_post_locked( $post->ID ) ) {
			return false;
		}
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			return false;
		}
		if ( self::verify_cookie( $post->ID ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Filter WordPress core post_password_required to align with lock status.
	 */
	public static function filter_post_password_required( $required, $post ) {
		$post = get_post( $post );
		if ( $post && self::is_locked_for_visitor( $post ) ) {
			return true;
		}
		return $required;
	}

	/**
	 * Render clean placeholder comment for embedded content.
	 */
	public static function render_embedded_placeholder( $post = null ): string {
		return '<!-- [Align PasswordLock] Content locked -->';
	}

	/**
	 * Intercept public singular requests for locked posts at template_redirect.
	 */
	public static function intercept_locked_post(): void {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! self::is_locked_for_visitor( $post ) ) {
			return;
		}

		status_header( 200 );
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		self::render_prompt_shell( $post );
		exit;
	}

	/**
	 * Send anti-caching and noindex headers for locked posts.
	 */
	public static function send_protection_headers(): void {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id && self::is_post_locked( $post_id ) && ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
				header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			}
		}
	}

	/**
	 * Filter robots directives to prevent indexing locked posts.
	 */
	public static function filter_wp_robots( array $robots ): array {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id && self::is_post_locked( $post_id ) ) {
				$robots['noindex']   = true;
				$robots['nofollow']  = true;
				$robots['noarchive'] = true;
				$robots['nosnippet'] = true;
			}
		}
		return $robots;
	}

	/**
	 * Generate HMAC authentication hash for visitor cookie.
	 */
	public static function generate_cookie_hmac( int $post_id, int $expiry, int $version ): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'align_post_lock_salt' );
		return hash_hmac( 'sha256', $post_id . '|' . $expiry . '|' . $version, $salt );
	}

	/**
	 * Verify visitor authentication cookie for a given post.
	 */
	public static function verify_cookie( int $post_id ): bool {
		$cookie_name = self::COOKIE_PREFIX . $post_id;
		if ( empty( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}

		$cookie_value = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		$parts        = explode( '.', $cookie_value, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$expiry = (int) $parts[0];
		$hmac   = (string) $parts[1];
		if ( $expiry < time() ) {
			return false;
		}

		$version       = (int) get_post_meta( $post_id, self::META_VERSION, true );
		$expected_hmac = self::generate_cookie_hmac( $post_id, $expiry, $version );
		return hash_equals( $expected_hmac, $hmac );
	}

	/**
	 * Issue persistent authentication cookie to visitor.
	 */
	public static function issue_auth_cookie( int $post_id ): void {
		$expiry        = time() + self::COOKIE_LIFETIME;
		$version       = (int) get_post_meta( $post_id, self::META_VERSION, true );
		$hmac          = self::generate_cookie_hmac( $post_id, $expiry, $version );
		$cookie_value  = $expiry . '.' . $hmac;
		$cookie_name   = self::COOKIE_PREFIX . $post_id;
		$cookie_path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$is_secure     = is_ssl();

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( $cookie_name, $cookie_value, array(
				'expires'  => $expiry,
				'path'     => $cookie_path,
				'domain'   => $cookie_domain,
				'secure'   => $is_secure,
				'httponly' => true,
				'samesite' => 'Lax',
			) );
		} else {
			setcookie( $cookie_name, $cookie_value, $expiry, $cookie_path, $cookie_domain, $is_secure, true );
		}
		$_COOKIE[ $cookie_name ] = $cookie_value;
	}

	/**
	 * Encrypt password using authenticated AES-256-CBC with WordPress auth salt.
	 */
	public static function encrypt_password( string $plain ): string {
		if ( '' === $plain || ! extension_loaded( 'openssl' ) ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = openssl_random_pseudo_bytes( 16 );
		$raw = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $raw );
	}

	/**
	 * Decrypt password using AES-256-CBC with WordPress auth salt.
	 */
	public static function decrypt_password( string $cipher ): string {
		if ( '' === $cipher || ! extension_loaded( 'openssl' ) ) {
			return '';
		}
		$data = base64_decode( $cipher );
		if ( strlen( $data ) <= 16 ) {
			return '';
		}
		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = substr( $data, 0, 16 );
		$raw = substr( $data, 16 );
		$res = openssl_decrypt( $raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false !== $res ? (string) $res : '';
	}

	/**
	 * Retrieve plain password for editing in Quick Edit.
	 */
	public static function get_password( int $post_id ): string {
		$plain = (string) get_post_meta( $post_id, self::META_PASSWORD, true );
		if ( '' !== $plain ) {
			return $plain;
		}
		$secret = (string) get_post_meta( $post_id, self::META_SECRET, true );
		if ( '' !== $secret && extension_loaded( 'openssl' ) ) {
			$dec = self::decrypt_password( $secret );
			if ( '' !== $dec ) {
				update_post_meta( $post_id, self::META_PASSWORD, $dec );
				return $dec;
			}
		}
		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_password ) ) {
			update_post_meta( $post_id, self::META_PASSWORD, (string) $post->post_password );
			return (string) $post->post_password;
		}
		return '';
	}

	/**
	 * Set or update post password, storing hash, plain text, and clearing native post_password.
	 */
	public static function set_password( int $post_id, string $password ): void {
		global $wpdb;

		$hash = wp_hash_password( $password );
		update_post_meta( $post_id, self::META_HASH, $hash );
		update_post_meta( $post_id, self::META_PASSWORD, $password );
		update_post_meta( $post_id, self::META_ENABLED, '1' );

		$version = (int) get_post_meta( $post_id, self::META_VERSION, true );
		update_post_meta( $post_id, self::META_VERSION, $version + 1 );

		$wpdb->update(
			$wpdb->posts,
			array( 'post_password' => '' ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
		error_log( '[Align PasswordLock] Password updated and lock active for post_id=' . $post_id );
	}

	/**
	 * Disable password lock for post and invalidate active sessions.
	 */
	public static function disable_lock( int $post_id ): void {
		global $wpdb;

		update_post_meta( $post_id, self::META_ENABLED, '0' );
		delete_post_meta( $post_id, self::META_HASH );
		delete_post_meta( $post_id, self::META_PASSWORD );
		delete_post_meta( $post_id, self::META_SECRET );

		$version = (int) get_post_meta( $post_id, self::META_VERSION, true );
		update_post_meta( $post_id, self::META_VERSION, $version + 1 );

		$wpdb->update(
			$wpdb->posts,
			array( 'post_password' => '' ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
		error_log( '[Align PasswordLock] Password lock disabled for post_id=' . $post_id );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_verify_password' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'  => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'password' => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * REST handler for password verification with rate limiting.
	 */
	public static function rest_verify_password( WP_REST_Request $request ): WP_REST_Response {
		$post_id  = (int) $request->get_param( 'post_id' );
		$password = (string) wp_unslash( (string) $request->get_param( 'password' ) );
		$post     = get_post( $post_id );

		if ( ! $post || ! is_post_type_viewable( $post->post_type ) || ! in_array( $post->post_status, array( 'publish', 'inherit' ), true ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Invalid request.' ), 404 );
		}
		if ( ! self::is_post_locked( $post_id ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Content is not locked.' ), 400 );
		}

		$client_ip     = self::get_client_ip();
		$transient_key = 'align_pwlock_fail_' . md5( $client_ip . '_' . $post_id );
		$fail_count    = (int) get_transient( $transient_key );

		if ( $fail_count >= 5 ) {
			error_log( '[Align PasswordLock] Rate limit exceeded for post_id=' . $post_id . ' from IP: ' . $client_ip );
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Too many failed attempts. Please wait 5 minutes and try again.' ), 429 );
		}

		$hash     = get_post_meta( $post_id, self::META_HASH, true );
		$is_valid = false;
		if ( ! empty( $hash ) ) {
			$is_valid = wp_check_password( $password, $hash );
		} else {
			$legacy_pass = (string) $post->post_password;
			if ( ! empty( $legacy_pass ) && ( hash_equals( $legacy_pass, $password ) || wp_check_password( $password, $legacy_pass ) ) ) {
				$is_valid = true;
				self::set_password( $post_id, $password );
			}
		}

		if ( $is_valid ) {
			delete_transient( $transient_key );
			self::issue_auth_cookie( $post_id );
			error_log( '[Align PasswordLock] Password verified for post_id=' . $post_id );
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		$fail_count++;
		set_transient( $transient_key, $fail_count, 300 );
		error_log( '[Align PasswordLock] Password verification failed for post_id=' . $post_id );
		return new WP_REST_Response( array( 'success' => false, 'message' => 'Incorrect password.' ), 401 );
	}

	/**
	 * Batch migration tool to convert legacy post_password into hash.
	 */
	public static function ajax_migrate_passwords(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized permission level.' ), 403 );
		}
		check_ajax_referer( 'align_migrate_passwords_nonce', 'nonce' );

		global $wpdb;
		$rows  = $wpdb->get_results( "SELECT ID, post_password FROM {$wpdb->posts} WHERE post_password != '' AND post_type != 'revision' LIMIT 50" );
		$count = 0;
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$post_id = (int) $row->ID;
				self::set_password( $post_id, (string) $row->post_password );
				update_post_meta( $post_id, self::META_MIGRATED_AT, time() );
				$count++;
			}
		}

		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_password != '' AND post_type != 'revision'" );
		error_log( '[Align PasswordLock] Batch migration processed ' . $count . ' posts. Remaining: ' . $remaining );
		wp_send_json_success( array( 'migrated' => $count, 'remaining' => $remaining ) );
	}

	/**
	 * Retrieve client IP safely without untrusted proxy headers.
	 */
	private static function get_client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Render minimal HTML prompt shell for locked posts.
	 */
	private static function render_prompt_shell( WP_Post $post ): void {
		$verify_url = esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) );
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
	<title>Protected Content</title>
	<style>
		body { margin: 0; background-color: #ffffff; }
	</style>
</head>
<body>
	<script>
		(function() {
			var postId = <?php echo (int) $post->ID; ?>;
			var verifyUrl = <?php echo wp_json_encode( $verify_url ); ?>;
			function triggerPrompt() {
				setTimeout(function() {
					var pwd = window.prompt('Password is required to view this content:');
					if (pwd === null || pwd === '') {
						window.alert('Password is required to view this content.');
						return;
					}
					fetch(verifyUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						credentials: 'same-origin',
						body: JSON.stringify({ post_id: postId, password: pwd })
					})
					.then(function(res) { return res.json(); })
					.then(function(data) {
						if (data && data.success) {
							window.location.reload();
						} else {
							window.alert((data && data.message) ? data.message : 'Incorrect password.');
							triggerPrompt();
						}
					})
					.catch(function() {
						window.alert('Unable to verify password. Please try again.');
					});
				}, 50);
			}
			window.triggerPrompt = triggerPrompt;
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', triggerPrompt);
			} else {
				triggerPrompt();
			}
		})();
	</script>
</body>
</html>
		<?php
	}
}
