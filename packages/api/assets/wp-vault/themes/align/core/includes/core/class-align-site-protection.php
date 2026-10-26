<?php
/**
 * Align Theme Site Protection & Access Control Engine
 *
 * Provides HTTP Basic Authentication guard and automated search engine
 * no-index barriers for staging and private environments.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Site_Protection {

	/**
	 * Cookie name for persistent authentication token.
	 */
	const COOKIE_NAME = 'align_site_protect_token';

	/**
	 * Authentication realm name displayed in browser login prompt.
	 */
	const AUTH_REALM = 'Restricted Access - Staging Site';

	/**
	 * Initialize site protection and SEO barrier hooks.
	 */
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'handle_protection' ], 1 );
		add_action( 'send_headers', [ __CLASS__, 'send_noindex_headers' ] );
		add_action( 'wp_head', [ __CLASS__, 'output_noindex_meta' ], 1 );
		add_filter( 'pre_option_blog_public', [ __CLASS__, 'force_noindex_option' ] );
		add_filter( 'wp_robots', [ __CLASS__, 'filter_wp_robots' ] );
		add_filter( 'robots_txt', [ __CLASS__, 'filter_robots_txt' ], 10, 2 );
	}

	/**
	 * Handle request authentication and access control evaluation.
	 */
	public static function handle_protection(): void {
		// 1. Check if protection feature is enabled
		if ( ! self::is_enabled() ) {
			return;
		}

		// 2. Check system bypass rules (CLI, Cron, or development constant)
		if ( self::is_system_bypassed() ) {
			return;
		}

		// 3. Check localhost development environment bypass
		$bypass_localhost = (int) get_option( 'align_site_protect_bypass_localhost', 1 );
		if ( 1 === $bypass_localhost && self::is_localhost() ) {
			return;
		}

		// 4. Check administrator bypass for logged-in users
		$bypass_admins = (int) get_option( 'align_site_protect_bypass_admins', 1 );
		if ( 1 === $bypass_admins && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		// Retrieve configured credentials
		$configured_user = (string) get_option( 'align_site_protect_user', 'staging' );
		$configured_pass = (string) get_option( 'align_site_protect_password', '' );

		// Prevent locking out if password is not yet configured
		if ( '' === $configured_pass ) {
			error_log( '[Align Security] Site protection is enabled but password is empty. Access allowed to prevent lockout.' );
			return;
		}

		// 5. Check persistent session cookie
		$expected_token = self::generate_token( $configured_pass );
		$cookie_token   = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';

		if ( ! empty( $cookie_token ) && hash_equals( $expected_token, $cookie_token ) ) {
			return;
		}

		// 6. Inspect HTTP Basic Authentication credentials
		$submitted_user = '';
		$submitted_pass = '';

		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$submitted_user = (string) $_SERVER['PHP_AUTH_USER'];
			$submitted_pass = (string) $_SERVER['PHP_AUTH_PW'];
		} else {
			// FastCGI / Nginx authorization header fallback
			$auth_header = '';
			if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
				$auth_header = (string) $_SERVER['HTTP_AUTHORIZATION'];
			} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
				$auth_header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			}

			if ( ! empty( $auth_header ) && 0 === stripos( $auth_header, 'Basic ' ) ) {
				$decoded = base64_decode( substr( $auth_header, 6 ), true );
				if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
					list( $submitted_user, $submitted_pass ) = explode( ':', $decoded, 2 );
				}
			}
		}

		// Validate submitted credentials
		if ( '' !== $submitted_user && hash_equals( $configured_user, $submitted_user ) && hash_equals( $configured_pass, $submitted_pass ) ) {
			self::issue_auth_cookie( $expected_token );
			$client_ip = self::get_client_ip();
			error_log( '[Align Security] User authenticated successfully: ' . $configured_user . ' from IP: ' . $client_ip );
			return;
		}

		// 7. Credentials missing or invalid - trigger HTTP 401 challenge
		$client_ip = self::get_client_ip();
		error_log( '[Align Security] Access blocked - 401 prompt triggered for IP: ' . $client_ip );

		self::send_401_headers();
		self::render_401_fallback();
		exit;
	}

	/**
	 * Check if site protection is enabled.
	 *
	 * When environment is detected as 'live' or 'local', protection is automatically disabled.
	 * When environment is 'staging', protection is automatically forced active.
	 * When environment is 'custom', manual setting is respected.
	 *
	 * @return bool True if protection active, false otherwise.
	 */
	public static function is_enabled(): bool {
		$env = self::get_current_environment();
		if ( 'live' === $env || 'local' === $env ) {
			return false;
		}
		if ( 'staging' === $env ) {
			return true;
		}

		return 1 === (int) get_option( 'align_site_protect_enabled', 0 );
	}

	/**
	 * Check if auto no-index barrier is active.
	 *
	 * When environment is detected as 'live' or 'local', barrier is disabled.
	 * When environment is 'staging', barrier is forced active.
	 * When environment is 'custom', respects the manual auto_noindex setting.
	 *
	 * @return bool True if noindex barrier is active, false otherwise.
	 */
	public static function is_noindex_active(): bool {
		$env = self::get_current_environment();
		if ( 'live' === $env || 'local' === $env ) {
			return false;
		}
		if ( 'staging' === $env ) {
			return true;
		}

		$enabled      = self::is_enabled();
		$auto_noindex = 1 === (int) get_option( 'align_site_protect_auto_noindex', 1 );
		return $enabled && $auto_noindex;
	}

	/**
	 * Normalize domain string by stripping protocol, path, port, trailing slash, www prefix and invalid characters.
	 *
	 * @param string $raw_domain Raw domain or URL string.
	 * @return string Normalized domain name.
	 */
	public static function normalize_domain( string $raw_domain ): string {
		$raw_domain = (string) preg_replace( '#<(script|style)[^>]*?>.*?</\\1>#si', '', $raw_domain );

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			$raw_domain = wp_strip_all_tags( $raw_domain );
		} elseif ( function_exists( 'sanitize_text_field' ) ) {
			$raw_domain = sanitize_text_field( $raw_domain );
		}

		$domain = trim( strip_tags( (string) $raw_domain ) );
		if ( '' === $domain ) {
			return '';
		}

		$domain = (string) preg_replace( '#^https?://#i', '', $domain );
		$domain = (string) preg_replace( '#^//#', '', $domain );
		$domain = (string) preg_replace( '~[/?#].*$~', '', $domain );
		$domain = (string) preg_replace( '#:\d+$#', '', $domain );
		$domain = strtolower( trim( $domain, " \t\n\r\0\x0B/" ) );
		$domain = (string) preg_replace( '#^www\.#i', '', $domain );
		$domain = (string) preg_replace( '/[^a-z0-9.-]/', '', $domain );

		return trim( $domain, '.-' );
	}

	/**
	 * Retrieve current HTTP host without port number.
	 *
	 * @return string Clean host name in lowercase.
	 */
	public static function get_current_host(): string {
		$host = '';
		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$host = (string) $_SERVER['HTTP_HOST'];
		} elseif ( ! empty( $_SERVER['SERVER_NAME'] ) ) {
			$host = (string) $_SERVER['SERVER_NAME'];
		}

		if ( function_exists( 'wp_unslash' ) && function_exists( 'sanitize_text_field' ) ) {
			$host = sanitize_text_field( wp_unslash( $host ) );
		}

		$host = strtolower( trim( $host ) );
		return (string) preg_replace( '/:\d+$/', '', $host );
	}

	/**
	 * Detect current running environment (local, staging, live, or custom).
	 *
	 * Automatically handles 'www.' tolerance by comparing normalized domain names.
	 *
	 * @return string Environment identifier: 'local' | 'staging' | 'live' | 'custom'.
	 */
	public static function get_current_environment(): string {
		if ( self::is_localhost() ) {
			return 'local';
		}

		$current_host   = self::normalize_domain( self::get_current_host() );
		$live_domain    = self::normalize_domain( (string) get_option( 'align_site_protect_live_domain', '' ) );
		$staging_domain = self::normalize_domain( (string) get_option( 'align_site_protect_staging_domain', '' ) );

		if ( '' !== $live_domain && $current_host === $live_domain ) {
			return 'live';
		}

		if ( '' !== $staging_domain && $current_host === $staging_domain ) {
			return 'staging';
		}

		return 'custom';
	}

	/**
	 * Check if request is bypassed by system execution context.
	 *
	 * @return bool
	 */
	public static function is_system_bypassed(): bool {
		if ( function_exists( 'wp_is_cli' ) && wp_is_cli() ) {
			return true;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		if ( defined( 'ALIGN_SITE_PROTECT_BYPASS' ) && ALIGN_SITE_PROTECT_BYPASS ) {
			return true;
		}

		return false;
	}

	/**
	 * Detect if request originates from a local development environment.
	 *
	 * Only checks server-bound IP (SERVER_ADDR / LOCAL_ADDR) and hostnames to prevent
	 * client IP spoofing via X-Forwarded-For headers (CWE-290).
	 *
	 * @return bool True if local environment detected, false otherwise.
	 */
	public static function is_localhost(): bool {
		$server_ips = [];
		if ( ! empty( $_SERVER['SERVER_ADDR'] ) ) {
			$server_ips[] = (string) $_SERVER['SERVER_ADDR'];
		}
		if ( ! empty( $_SERVER['LOCAL_ADDR'] ) ) {
			$server_ips[] = (string) $_SERVER['LOCAL_ADDR'];
		}

		foreach ( $server_ips as $sip ) {
			if ( in_array( $sip, [ '127.0.0.1', '::1' ], true ) ) {
				return true;
			}
		}

		$host       = self::get_current_host();
		$normalized = self::normalize_domain( $host );

		if ( 'localhost' === $host || '127.0.0.1' === $host || 'localhost' === $normalized ) {
			return true;
		}

		$local_suffixes = [ '.local', '.test', '.localhost', '.internal', '.lndo.site', '.ddev.site' ];
		foreach ( $local_suffixes as $suffix ) {
			$len = strlen( $suffix );
			if ( substr( $host, -$len ) === $suffix ) {
				return true;
			}
		}

		if ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), [ 'local', 'development' ], true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate persistent token using HMAC-SHA256 and WordPress auth salt.
	 *
	 * @param string $password Cleartext password.
	 * @return string HMAC-SHA256 token.
	 */
	public static function generate_token( string $password ): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'align_protect_salt' );
		return hash_hmac( 'sha256', $password, $salt );
	}

	/**
	 * Issue persistent authentication cookie to remember authenticated browser session.
	 *
	 * @param string $token HMAC token value.
	 */
	public static function issue_auth_cookie( string $token ): void {
		$days = absint( get_option( 'align_site_protect_cookie_days', 30 ) );
		if ( $days < 1 ) {
			$days = 30;
		}

		$expiry        = time() + ( $days * DAY_IN_SECONDS );
		$cookie_path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$is_secure     = is_ssl();

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::COOKIE_NAME, $token, [
				'expires'  => $expiry,
				'path'     => $cookie_path,
				'domain'   => $cookie_domain,
				'secure'   => $is_secure,
				'httponly' => true,
				'samesite' => 'Lax',
			] );
		} else {
			setcookie( self::COOKIE_NAME, $token, $expiry, $cookie_path, $cookie_domain, $is_secure, true );
		}

		$_COOKIE[ self::COOKIE_NAME ] = $token;
	}

	/**
	 * Send HTTP 401 Unauthorized response headers.
	 */
	public static function send_401_headers(): void {
		header( 'WWW-Authenticate: Basic realm="' . self::AUTH_REALM . '"' );
		header( 'HTTP/1.1 401 Unauthorized' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}

	/**
	 * Send X-Robots-Tag HTTP header if automated no-index barrier is active.
	 */
	public static function send_noindex_headers(): void {
		if ( self::is_noindex_active() && ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet' );
		}
	}

	/**
	 * Output meta robots tag into wp_head.
	 */
	public static function output_noindex_meta(): void {
		if ( self::is_noindex_active() ) {
			echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">' . "\n";
		}
	}

	/**
	 * Force WordPress blog_public option to '0' (discourage search engines).
	 *
	 * @param mixed $value Original option value.
	 * @return mixed Overridden value '0' if no-index active, otherwise original value.
	 */
	public static function force_noindex_option( $value ) {
		if ( self::is_noindex_active() ) {
			return '0';
		}
		return $value;
	}

	/**
	 * Filter WordPress robots meta directives array.
	 *
	 * @param array $robots Directives array.
	 * @return array Modified robots array.
	 */
	public static function filter_wp_robots( array $robots ): array {
		if ( self::is_noindex_active() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	/**
	 * Disallow all crawler access in dynamic robots.txt.
	 *
	 * @param string $output Robots.txt content.
	 * @param bool   $public Whether site is considered public.
	 * @return string Modified robots.txt content.
	 */
	public static function filter_robots_txt( string $output, bool $public = true ): string {
		if ( self::is_noindex_active() ) {
			return "User-agent: *\nDisallow: /\n";
		}
		return $output;
	}

	/**
	 * Retrieve client IP address safely with proxy support.
	 *
	 * @return string Client IP address.
	 */
	public static function get_client_ip(): string {
		$ip = '';
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts     = explode( ',', $forwarded );
			$ip        = trim( $parts[0] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}

		return '0.0.0.0';
	}

	/**
	 * Render minimal fallback HTML for HTTP 401 Unauthorized screen.
	 */
	private static function render_401_fallback(): void {
		?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
	<title>401 Unauthorized - Access Restricted</title>
	<style>
		* { box-sizing: border-box; }
		body { margin: 0; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 85vh; }
		.error-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 460px; width: 100%; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); }
		.error-badge { display: inline-block; padding: 4px 10px; font-size: 11px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; border-radius: 4px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; margin-bottom: 16px; }
		h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 10px 0; line-height: 1.3; }
		p { font-size: 14px; line-height: 1.6; color: #64748b; margin: 0 0 20px 0; }
		.error-notice { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; font-size: 12.5px; color: #475569; line-height: 1.5; }
	</style>
</head>
<body>
	<div class="error-card">
		<div class="error-badge">HTTP 401 Unauthorized</div>
		<h1>Restricted Access</h1>
		<p>This environment is protected with access control. Valid credentials are required to view site contents.</p>
		<div class="error-notice">
			Please refresh this page and provide valid authentication credentials when prompted by your browser.
		</div>
	</div>
</body>
</html>
		<?php
	}
}
