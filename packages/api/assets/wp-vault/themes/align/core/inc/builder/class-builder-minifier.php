<?php
/**
 * Align Theme Core Engine - Builder Asset & Markup Minification Engine
 * Provides high-performance tokenized and cloud-accelerated minification for HTML, CSS, and JS payloads.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Minifier {

	/** @var array<string, string> */
	private static array $memory_cache = array();

	/**
	 * Minify HTML markup with tokenized preservation for sensitive elements.
	 */
	public static function minify_html( string $html ): string {
		$trimmed = trim( $html );
		if ( '' === $trimmed ) {
			return '';
		}

		// 1. Protect sensitive tags (<pre>, <code>, <textarea>, <script>, <style>, <svg>)
		$tokens = array();
		$html   = preg_replace_callback(
			'/<(pre|code|textarea|script|style|svg)\b[^>]*>[\s\S]*?<\/\1>/is',
			function ( $matches ) use ( &$tokens ) {
				$token            = '%%%ALIGN_HTML_TOKEN_' . count( $tokens ) . '%%%';
				$tokens[ $token ] = $matches[0];
				return $token;
			},
			$html
		);

		// 1.5. Strip orphaned script strings leaked outside script tags
		$html = preg_replace( '#(?:<script[^>]*>\s*)?["\']?IntersectionObserver["\']?\s*in\s*window\s*(?:&&|&amp;&amp;)\s*!?window\.matchMedia\(.+?\)\.matches\s*(?:&&|&amp;&amp;)\s*document\.documentElement\.classList\.add\(.+?js-reveal.+?\);?(?:\s*<\/script>)?#i', '', $html );

		// 2. Remove HTML comments while preserving conditional and Gutenberg comments
		$html = preg_replace( '/<!--(?!\[if|\s*\/?wp:)[\s\S]*?-->/s', '', $html );

		// 3. Normalize whitespace outside protected tokens
		$html = preg_replace( '/[ \t\r\n]+/', ' ', $html );
		$html = preg_replace( '/>\s{2,}</', '> <', $html );

		// Strip whitespace between block-level elements
		$block_tags = 'address|article|aside|blockquote|canvas|dd|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|li|main|nav|noscript|ol|p|pre|section|table|tfoot|tbody|thead|tr|th|td|ul|video';
		$html       = preg_replace( '/(<\/(?:' . $block_tags . ')>)\s+(?=<(?:\/|)(?:' . $block_tags . ')\b)/i', '$1', $html );
		$html       = preg_replace( '/(<(?:' . $block_tags . ')\b[^>]*>)\s+(?=<(?:\/|)(?:' . $block_tags . ')\b)/i', '$1', $html );
		$html       = preg_replace( '/<\s+/', '<', $html );
		$html       = preg_replace( '/\s+>/', '>', $html );

		return ! empty( $tokens ) ? trim( strtr( $html, $tokens ) ) : trim( $html );
	}

	/**
	 * Local engine: Minify CSS stylesheet with tokenized preservation.
	 */
	public static function local_minify_css( string $css ): string {
		$trimmed = trim( $css );
		if ( '' === $trimmed ) {
			return '';
		}

		$tokens  = array();
		$pattern = '/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|url\s*\((?:(?:\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|[^\)])*)\)|(?:calc|min|max|clamp)\s*(?<math_paren>\((?:[^\(\)]|(?&math_paren))*\)))/is';
		$css     = preg_replace_callback( $pattern, function ( $matches ) use ( &$tokens ) {
			$token            = '%%%ALIGN_CSS_TOKEN_' . count( $tokens ) . '%%%';
			$tokens[ $token ] = $matches[0];
			return $token;
		}, $css );

		$css = preg_replace( '/\/\*[\s\S]*?\*\//', '', $css );
		$css = preg_replace( '/\s+/', ' ', $css );
		$css = preg_replace( '/\s*([\{\}\:\;\,>\+~])\s*/', '$1', $css );
		$css = str_replace( ';}', '}', $css );
		$css = str_replace( array( ':0px', ':0em', ':0rem', ':0%' ), ':0', $css );

		return ! empty( $tokens ) ? trim( strtr( $css, $tokens ) ) : trim( $css );
	}

	/**
	 * Local engine: Minify JavaScript code with tokenized preservation.
	 */
	public static function local_minify_js( string $js ): string {
		$trimmed = trim( $js );
		if ( '' === $trimmed ) {
			return '';
		}

		$tokens  = array();
		$pattern = '/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`)/s';
		$js      = preg_replace_callback( $pattern, function ( $matches ) use ( &$tokens ) {
			$token            = '%%%ALIGN_JS_TOKEN_' . count( $tokens ) . '%%%';
			$tokens[ $token ] = $matches[0];
			return $token;
		}, $js );

		$pattern_regex = '/(?:^|[=(,:;!&|?+\-*\/\[{\n]\s*|\b(?:return|typeof|yield|await|case|delete|void)\s*)\K(\/(?![*\/])(?:\\\\.|[^\\\\\/\r\n])+\/[gimsuy]*)/';
		$js            = preg_replace_callback( $pattern_regex, function ( $matches ) use ( &$tokens ) {
			$token            = '%%%ALIGN_JS_TOKEN_' . count( $tokens ) . '%%%';
			$tokens[ $token ] = $matches[1];
			return $token;
		}, $js );

		$js = preg_replace( '/\/\*[\s\S]*?\*\//', '', $js );
		$js = preg_replace( '/\/\/[^\r\n]*/', '', $js );
		$js = preg_replace( '/^[ \t]+|[ \t]+$/m', '', $js );
		$js = preg_replace( '/[\r\n]+/', "\n", $js );

		return ! empty( $tokens ) ? trim( strtr( $js, $tokens ) ) : trim( $js );
	}

	/**
	 * Check if CSS code is already minified using structural heuristics.
	 */
	public static function is_already_minified_css( string $css ): bool {
		$trimmed = trim( $css );
		if ( '' === $trimmed || preg_match( '/\/\*[\s\S]*?\*\/|\s{2,}/', $trimmed ) || preg_match( '/\s*[\{\}\;\:]\s*[\r\n]+|\{\s+|\s+\}|\:\s+/', $trimmed ) ) {
			return false;
		}
		$line_count   = substr_count( $trimmed, "\n" ) + 1;
		$avg_line_len = strlen( $trimmed ) / $line_count;
		return ( $avg_line_len >= 100 || 1 === $line_count ) && ! preg_match( '/[\r\n]\s+/', $trimmed );
	}

	/**
	 * Check if JS code is already minified using structural heuristics.
	 */
	public static function is_already_minified_js( string $js ): bool {
		$trimmed = trim( $js );
		if ( '' === $trimmed || preg_match( '/\/\*[\s\S]*?\*\/|^\s*\/\/[^\r\n]*|\s{2,}/m', $trimmed ) || preg_match( '/[\{\;]\s*[\r\n]+\s+/', $trimmed ) ) {
			return false;
		}
		$line_count   = substr_count( $trimmed, "\n" ) + 1;
		$avg_line_len = strlen( $trimmed ) / $line_count;
		return ( $avg_line_len >= 100 || 1 === $line_count ) && ! preg_match( '/[\r\n]\s+/', $trimmed );
	}

	/**
	 * Retrieve the configured Align Cloud Minify API endpoint.
	 */
	private static function get_api_endpoint(): string {
		$endpoint = 'https://convertb.alignvn.cloud/api/minify';
		if ( function_exists( 'get_option' ) ) {
			$opt = get_option( 'align_minify_api_url', $endpoint );
			if ( ! empty( $opt ) && is_string( $opt ) ) {
				$trimmed = rtrim( trim( $opt ), '/' );
				if ( preg_match( '#/api/(convert|minify|unminify|scss2css|scss-to-css|sass-to-css)$#i', $trimmed ) ) {
					return preg_replace( '#/api/(convert|minify|unminify|scss2css|scss-to-css|sass-to-css)$#i', '/api/minify', $trimmed );
				}
				return ( false === strpos( $trimmed, '/api/' ) ) ? $trimmed . '/api/minify' : $trimmed;
			}
		}
		return $endpoint;
	}

	/** @var bool Fast-fail circuit breaker if cloud minifier API is unreachable */
	private static bool $cloud_offline = false;

	/**
	 * Call Align Cloud Minify API with resilient client adapters (wp_remote_post -> cURL -> stream).
	 */
	private static function call_cloud_minify( string $code, string $type ): ?string {
		if ( self::$cloud_offline ) {
			return null;
		}

		$endpoint = self::get_api_endpoint();
		$payload  = json_encode( array( 'code' => $code, 'type' => $type ) );
		if ( false === $payload ) {
			return null;
		}

		$raw_body = null;
		if ( function_exists( 'wp_remote_post' ) ) {
			$res = wp_remote_post( $endpoint, array(
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => $payload,
				'timeout'     => 3,
				'data_format' => 'body',
			) );
			if ( is_wp_error( $res ) ) {
				self::$cloud_offline = true;
				error_log( sprintf( '[Align API] Minify Cloud API unreachable (%s) | Endpoint: %s | Bypassing minification to preserve raw code.', $res->get_error_message(), $endpoint ) );
			} else {
				$code_status = wp_remote_retrieve_response_code( $res );
				if ( 200 === $code_status ) {
					$raw_body = wp_remote_retrieve_body( $res );
				} else {
					self::$cloud_offline = true;
					error_log( sprintf( '[Align API] Minify Cloud API returned HTTP %d | Endpoint: %s | Bypassing minification to preserve raw code.', $code_status, $endpoint ) );
				}
			}
		} elseif ( function_exists( 'curl_init' ) ) {
			$ch = curl_init( $endpoint );
			curl_setopt_array( $ch, array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $payload,
				CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json' ),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 3,
				CURLOPT_CONNECTTIMEOUT => 2,
				CURLOPT_SSL_VERIFYPEER => false,
			) );
			$raw_body  = curl_exec( $ch );
			$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			$curl_err  = curl_error( $ch );
			curl_close( $ch );
			if ( 200 !== $http_code ) {
				self::$cloud_offline = true;
				error_log( sprintf( '[Align API] Minify Cloud API cURL failed (HTTP %d, %s) | Bypassing minification to preserve raw code.', $http_code, $curl_err ) );
				$raw_body = null;
			}
		} else {
			$ctx = stream_context_create( array(
				'http' => array(
					'method'  => 'POST',
					'header'  => "Content-Type: application/json\r\n",
					'content' => $payload,
					'timeout' => 3,
				),
			) );
			$raw_body = @file_get_contents( $endpoint, false, $ctx );
		}

		if ( is_string( $raw_body ) ) {
			$data = json_decode( $raw_body, true );
			if ( ! empty( $data['success'] ) && isset( $data['code'] ) && is_string( $data['code'] ) ) {
				return $data['code'];
			}
		}
		return null;
	}

	/**
	 * Central Two-Tier Caching & Fallback Minification Pipeline.
	 */
	private static function process_minification( string $code, string $type ): string {
		$trimmed = trim( $code );
		if ( '' === $trimmed ) {
			return '';
		}

		// 1. Heuristic already minified check
		if ( ( 'css' === $type && self::is_already_minified_css( $trimmed ) ) ||
			 ( 'js' === $type && self::is_already_minified_js( $trimmed ) ) ) {
			error_log( sprintf( '[Align Minifier] %s bypassed minification (Heuristic: already minified)', strtoupper( $type ) ) );
			return $trimmed;
		}

		// 2. Memory cache check
		$cache_key = md5( $type . ':' . $trimmed );
		if ( isset( self::$memory_cache[ $cache_key ] ) ) {
			error_log( sprintf( '[Align Minifier] %s memory cache hit | Key: %s', strtoupper( $type ), $cache_key ) );
			return self::$memory_cache[ $cache_key ];
		}

		// 3. Transient cache check (if WordPress environment)
		$transient_key = 'align_min_' . $cache_key;
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( $transient_key );
			if ( false !== $cached && is_string( $cached ) ) {
				error_log( sprintf( '[Align Minifier] %s transient cache hit | Key: align_min_%s', strtoupper( $type ), $cache_key ) );
				self::$memory_cache[ $cache_key ] = $cached;
				return $cached;
			}
		}

		// 4. Try Cloud API minification
		$minified = self::call_cloud_minify( $trimmed, $type );

		// 5. Pure Passthrough (Option A): Zero PHP regex to prevent code breakage or corrupted selectors
		if ( null === $minified || '' === $minified ) {
			error_log( sprintf( '[Align API] %s minification bypassed (Cloud API unavailable). Preserving raw untouched code.', strtoupper( $type ) ) );
			$minified = $trimmed;
		} else {
			error_log( sprintf( '[Align Minifier] %s minified via Cloud API | Raw: %d b -> Min: %d b', strtoupper( $type ), strlen( $code ), strlen( $minified ) ) );
		}

		// 6. Store in memory and transient caches
		self::$memory_cache[ $cache_key ] = $minified;
		if ( function_exists( 'set_transient' ) ) {
			$expiration = defined( 'HOUR_IN_SECONDS' ) ? 12 * HOUR_IN_SECONDS : 43200;
			set_transient( $transient_key, $minified, $expiration );
		}

		return $minified;
	}

	/**
	 * Minify CSS stylesheet via Cloud API with local fallback and two-tier caching.
	 */
	public static function minify_css( string $css ): string {
		return self::process_minification( $css, 'css' );
	}

	/**
	 * Minify JavaScript code via Cloud API with local fallback and two-tier caching.
	 */
	public static function minify_js( string $js ): string {
		return self::process_minification( $js, 'js' );
	}

	/**
	 * Clear the in-memory cache.
	 */
	public static function clear_memory_cache(): void {
		self::$memory_cache = array();
	}

	/**
	 * Get the current in-memory cache entries.
	 *
	 * @return array<string, string>
	 */
	public static function get_memory_cache(): array {
		return self::$memory_cache;
	}

	/**
	 * Minify HTML, CSS, JS and assemble final scoped payload with observability logging.
	 */
	public static function minify_payload( string $html, string $css, string $js, string $style_id, string $script_id ): string {
		$raw_html_len = strlen( $html );
		$raw_css_len  = strlen( $css );
		$raw_js_len   = strlen( $js );
		$raw_total    = $raw_html_len + $raw_css_len + $raw_js_len;

		$min_html = self::minify_html( $html );
		$min_css  = self::minify_css( $css );
		$min_js   = self::minify_js( $js );

		$assembled = '';
		if ( '' !== $min_css ) {
			$style_attr = function_exists( 'esc_attr' ) ? esc_attr( $style_id ) : htmlspecialchars( $style_id, ENT_QUOTES, 'UTF-8' );
			$assembled .= '<style id="' . $style_attr . '">' . $min_css . '</style>';
		}

		$assembled .= $min_html;

		if ( '' !== $min_js ) {
			$script_attr = function_exists( 'esc_attr' ) ? esc_attr( $script_id ) : htmlspecialchars( $script_id, ENT_QUOTES, 'UTF-8' );
			$assembled  .= '<script id="' . $script_attr . '">' . $min_js . '</script>';
		}

		$assembled = trim( $assembled );
		$min_total = strlen( $assembled );

		$saved_bytes = max( 0, $raw_total - $min_total );
		$saved_pct   = $raw_total > 0 ? number_format( ( $saved_bytes / $raw_total ) * 100, 1 ) : '0.0';

		error_log( sprintf(
			'[Align Minifier] Payload Minified | Raw: %d bytes -> Min: %d bytes | Saved: %s%% (CSS: %d b, JS: %d b, HTML: %d b)',
			$raw_total,
			$min_total,
			$saved_pct,
			strlen( $min_css ),
			strlen( $min_js ),
			strlen( $min_html )
		) );

		return $assembled;
	}
}
