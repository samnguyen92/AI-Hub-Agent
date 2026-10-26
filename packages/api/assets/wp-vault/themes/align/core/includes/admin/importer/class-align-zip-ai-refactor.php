<?php
/**
 * Align Theme Core Engine - ZIP Importer AI Refactor Module
 * Two-Stage AI Pipeline:
 * Stage 1: Global Header & Footer Extraction (Header/Footer Specialist)
 * Stage 2: Reusable Blocks & Overwrite Layer Handler (Pattern Specialist + PHP Deterministic DOM Tagger)
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Zip_AI_Refactor {

	/** @var array<string, string> Token to original SVG markup map */
	private static array $svg_map = array();

	/**
	 * Get active AI Configuration (OpenRouter Cloud API / OpenAI SDK standard).
	 *
	 * @param array $custom_config Optional runtime custom config.
	 * @return array Endpoint, API key, model and timeout.
	 */
	public static function get_config( array $custom_config = array() ): array {
		if ( class_exists( 'Align_AI_Connection' ) ) {
			$conn_config = Align_AI_Connection::get_config( $custom_config );
			return array(
				'endpoint'     => $conn_config['endpoint'],
				'base_url'     => $conn_config['base_url'],
				'provider'     => $conn_config['provider'],
				'api_key'      => $conn_config['api_key'],
				'model'        => $conn_config['model'],
				'organization' => $conn_config['organization'],
				'timeout'      => 120,
			);
		}

		$endpoint = ! empty( $custom_config['ai_endpoint'] ) ? $custom_config['ai_endpoint'] : ( defined( 'ALIGN_OPENROUTER_ENDPOINT' ) ? ALIGN_OPENROUTER_ENDPOINT : 'https://openrouter.ai/api/v1/chat/completions' );
		$api_key  = ! empty( $custom_config['ai_api_key'] ) ? $custom_config['ai_api_key'] : ( defined( 'ALIGN_OPENROUTER_API_KEY' ) ? ALIGN_OPENROUTER_API_KEY : ( function_exists( 'get_option' ) ? get_option( 'align_ai_api_key', get_option( 'align_openrouter_api_key', '' ) ) : '' ) );
		$model    = ! empty( $custom_config['ai_model'] ) ? $custom_config['ai_model'] : ( defined( 'ALIGN_OPENROUTER_MODEL' ) ? ALIGN_OPENROUTER_MODEL : ( function_exists( 'get_option' ) ? get_option( 'align_ai_model', get_option( 'align_openrouter_model', 'google/gemini-2.5-flash' ) ) : 'google/gemini-2.5-flash' ) );

		return array(
			'endpoint'     => ! empty( $endpoint ) ? $endpoint : 'https://openrouter.ai/api/v1/chat/completions',
			'base_url'     => 'https://openrouter.ai/api/v1',
			'provider'     => 'openrouter',
			'api_key'      => ! empty( $api_key ) ? $api_key : '',
			'model'        => ! empty( $model ) ? $model : 'google/gemini-2.5-flash',
			'organization' => '',
			'timeout'      => 120,
		);
	}

	/**
	 * Pre-clean HTML to create a lightweight DOM skeleton for AI analysis,
	 * while preserving full SVG markup in an in-memory token registry.
	 *
	 * @param string $html Full HTML document.
	 * @return string Lightweight HTML skeleton.
	 */
	public static function create_dom_skeleton( string $html ): string {
		$clean = preg_replace( '/<!--[\s\S]*?-->/', '', $html );
		$clean = preg_replace( '/<script\b[^>]*>[\s\S]*?<\/script>/is', '', $clean );
		$clean = preg_replace( '/<style\b[^>]*>[\s\S]*?<\/style>/is', '', $clean );

		// Tokenize inline SVGs to drastically shrink payload while preserving 100% of vectors
		$clean = preg_replace_callback( '/<svg\b[^>]*>[\s\S]*?<\/svg>/is', function( $m ) {
			$token = 'align-svg-token-' . count( self::$svg_map );
			self::$svg_map[ $token ] = $m[0];
			return '<svg data-align-svg="' . $token . '"></svg>';
		}, $clean );

		$clean = preg_replace( '/\s+/', ' ', $clean );
		return trim( $clean );
	}

	/**
	 * Restore original full SVG markup from the token registry.
	 *
	 * @param string $html HTML containing SVG tokens.
	 * @return string Fully restored HTML.
	 */
	public static function restore_svgs( string $html ): string {
		if ( empty( self::$svg_map ) || empty( $html ) ) {
			return $html;
		}

		return preg_replace_callback( '/<svg\b[^>]*data-align-svg=[\'"]([^\'"]+)[\'"][^>]*>(?:[\s\S]*?<\/svg>)?/is', function( $m ) {
			$token = $m[1];
			return isset( self::$svg_map[ $token ] ) ? self::$svg_map[ $token ] : $m[0];
		}, $html );
	}

	/**
	 * Automatically ensure all headings, paragraphs, buttons, links, and images
	 * inside a reusable block have data-allow-overwrite="true" and unique data-overwrite-id.
	 *
	 * @param string $html       Block HTML content.
	 * @param string $block_slug Block slug name.
	 * @return string HTML with guaranteed overwrite attributes.
	 */
	public static function inject_overwrite_attributes( string $html, string $block_slug = 'block' ): string {
		if ( empty( trim( $html ) ) ) {
			return $html;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //p | //span | //a | //button | //img' );

		$counter = array();
		foreach ( $nodes as $node ) {
			if ( ! ( $node instanceof DOMElement ) ) continue;
			$tag = strtolower( $node->tagName );
			if ( 'svg' === $tag || 'path' === $tag ) continue;

			$prefix = 'ov-';
			if ( in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
				$prefix = 'ov-heading-';
			} elseif ( in_array( $tag, array( 'p', 'span' ), true ) ) {
				if ( strlen( trim( $node->textContent ) ) < 2 ) continue;
				$prefix = 'ov-text-';
			} elseif ( in_array( $tag, array( 'a', 'button' ), true ) ) {
				$prefix = 'ov-btn-';
			} elseif ( 'img' === $tag ) {
				$prefix = 'ov-img-';
			}

			if ( ! isset( $counter[ $prefix ] ) ) {
				$counter[ $prefix ] = 1;
			} else {
				$counter[ $prefix ]++;
			}

			if ( ! $node->hasAttribute( 'data-allow-overwrite' ) ) {
				$node->setAttribute( 'data-allow-overwrite', 'true' );
			}
			if ( ! $node->hasAttribute( 'data-overwrite-id' ) ) {
				$node->setAttribute( 'data-overwrite-id', $prefix . $counter[ $prefix ] );
			}
		}

		$wrapper = $dom->getElementsByTagName( 'div' )->item( 0 );
		if ( ! $wrapper ) return $html;

		$inner = '';
		foreach ( $wrapper->childNodes as $child ) {
			$inner .= $dom->saveHTML( $child );
		}
		return trim( $inner );
	}

	/**
	 * Replace occurrences of a reusable block in page HTML with its shortcode.
	 *
	 * @param string $page_html        Full page raw HTML.
	 * @param string $original_snippet Snippet extracted for reusable block.
	 * @param string $slug             Reusable block slug.
	 * @return string HTML with [reuse-block slug="..."] shortcode inserted.
	 */
	public static function replace_block_with_shortcode( string $page_html, string $original_snippet, string $slug ): string {
		$slug_clean = function_exists( 'esc_attr' ) ? esc_attr( $slug ) : htmlspecialchars( $slug, ENT_QUOTES, 'UTF-8' );
		$shortcode_div = "\n<div class=\"site-{$slug_clean}-wrapper\">\n  <div data-component-shortcode=\"\" data-shortcode=\"[reuse-block slug='{$slug_clean}']\" data-preview-state=\"rendered\">[reuse-block slug=\"{$slug_clean}\"]</div>\n</div>\n";

		if ( empty( $original_snippet ) ) {
			return $page_html;
		}

		// 1. Direct match
		if ( false !== strpos( $page_html, $original_snippet ) ) {
			return str_replace( $original_snippet, $shortcode_div, $page_html );
		}

		// 2. Whitespace-normalized regex match
		$trimmed = trim( $original_snippet );
		$pattern = preg_quote( $trimmed, '/' );
		$pattern = preg_replace( '/\s+/', '\s+', $pattern );

		if ( @preg_match( '/' . $pattern . '/is', $page_html ) ) {
			return preg_replace( '/' . $pattern . '/is', $shortcode_div, $page_html, 1 );
		}

		// 3. Class or Tag Signature Match (e.g. <section class="cta-banner ...">)
		if ( preg_match( '/^<([a-z0-9]+)\b([^>]*\bclass=[\'"]([^\'"]+)[\'"][^>]*)>/i', $trimmed, $tag_match ) ) {
			$tag_name   = $tag_match[1];
			$class_list = preg_split( '/\s+/', trim( $tag_match[3] ) );
			$main_class = ! empty( $class_list[0] ) ? preg_quote( $class_list[0], '/' ) : '';

			if ( ! empty( $main_class ) && ! in_array( $main_class, array( 'container', 'row', 'col', 'section', 'wrap', 'wrapper' ), true ) ) {
				$section_pattern = '/<' . $tag_name . '\b[^>]*\bclass=[\'"][^\'"]*' . $main_class . '[^\'"]*[\'"][^>]*>[\s\S]*?<\/' . $tag_name . '>/is';
				if ( @preg_match( $section_pattern, $page_html ) ) {
					return preg_replace( $section_pattern, $shortcode_div, $page_html, 1 );
				}
			}
		}

		return $page_html;
	}

	/**
	 * Clean page body wrapper tags (DOCTYPE, html, head, body, header, footer).
	 *
	 * @param string $html Full HTML.
	 * @return string Clean body HTML.
	 */
	public static function clean_page_body_markup( string $html ): string {
		$body = $html;
		if ( preg_match( '/<body\b[^>]*>([\s\S]*?)<\/body>/is', $body, $m ) ) {
			$body = $m[1];
		} else {
			$body = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $body );
			$body = preg_replace( '/<\/?(html|head)\b[^>]*>/i', '', $body );
		}

		$body = preg_replace( '/<header\b[^>]*>[\s\S]*?<\/header>/is', '', $body );
		$body = preg_replace( '/<footer\b[^>]*>[\s\S]*?<\/footer>/is', '', $body );
		$body = preg_replace( '/<link\b[^>]*rel=["\']?stylesheet["\']?[^>]*>/i', '', $body );

		return trim( (string) $body );
	}

	/**
	 * Stage 1: Call AI to extract Global Header & Footer.
	 *
	 * @param array $sample_pages Sample page skeletons.
	 * @param array $config       AI configuration.
	 * @return array Header and Footer markup.
	 */
	public static function stage1_extract_header_footer( array $sample_pages, array $config ): array {
		error_log( '[Align AIRefactor:Stage1] Extracting Global Header & Footer...' );

		$system_prompt = "You are the Align Theme Header and Footer Specialist. Analyze the HTML page skeletons and extract only the global header and footer: 1. Extract Global Header (logo, topbar, nav) into 'header_html' (preserve data-align-svg attributes). 2. Extract Global Footer (copyright, links) into 'footer_html'. Output JSON strictly: {\"header_html\": \"<header>...</header>\", \"footer_html\": \"<footer>...</footer>\"}";
		$request_body  = wp_json_encode( array(
			'model'           => $config['model'],
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => wp_json_encode( array( 'pages' => $sample_pages ) ) ),
			),
		) );

		$headers = class_exists( 'Align_AI_Connection' )
			? Align_AI_Connection::build_headers( $config )
			: array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $config['api_key'],
				'HTTP-Referer'  => function_exists( 'site_url' ) ? site_url() : 'https://align-theme.local',
				'X-Title'       => 'Align Theme Importer',
			);
		$response = wp_remote_post( $config['endpoint'], array( 'headers' => $headers, 'timeout' => $config['timeout'], 'body' => $request_body, 'data_format' => 'body' ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array( 'header_html' => '', 'footer_html' => '' );

		$json_data  = json_decode( wp_remote_retrieve_body( $response ), true );
		$ai_content = $json_data['choices'][0]['message']['content'] ?? '';
		$clean_json = trim( preg_replace( '/^```json\s*/i', '', preg_replace( '/\s*```$/i', '', $ai_content ) ) );
		$parsed     = json_decode( $clean_json, true );
		return is_array( $parsed ) ? $parsed : array( 'header_html' => '', 'footer_html' => '' );
	}

	/**
	 * Stage 2: Dedicated AI Specialist for Reusable Block Recognition.
	 *
	 * @param array $body_skeletons Body-only page skeletons (Header & Footer removed).
	 * @param array $config         AI configuration.
	 * @return array List of discovered reusable blocks with slugs and original snippets.
	 */
	public static function stage2_handle_reusable_blocks( array $body_skeletons, array $config ): array {
		error_log( '[Align AIRefactor:Stage2] Running Reusable Block Specialist Pass...' );

		$system_prompt = "You are the Align Theme Reusable Block Specialist. Analyze page bodies and identify reusable block sections (e.g. CTA banners, pricing tables, testimonials, FAQs, newsletters, partner logos). For each block provide 'slug' (kebab-case) and 'original_html' (exact snippet). Output JSON strictly: {\"reusable_blocks\": [{\"slug\": \"cta-banner\", \"original_html\": \"<section ...>...</section>\"}]}";
		$request_body  = wp_json_encode( array(
			'model'           => $config['model'],
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => wp_json_encode( array( 'page_bodies' => $body_skeletons ) ) ),
			),
		) );

		$headers = class_exists( 'Align_AI_Connection' )
			? Align_AI_Connection::build_headers( $config )
			: array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $config['api_key'],
				'HTTP-Referer'  => function_exists( 'site_url' ) ? site_url() : 'https://align-theme.local',
				'X-Title'       => 'Align Theme Importer',
			);
		$response = wp_remote_post( $config['endpoint'], array( 'headers' => $headers, 'timeout' => $config['timeout'], 'body' => $request_body, 'data_format' => 'body' ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return array();

		$json_data  = json_decode( wp_remote_retrieve_body( $response ), true );
		$ai_content = $json_data['choices'][0]['message']['content'] ?? '';
		$clean_json = trim( preg_replace( '/^```json\s*/i', '', preg_replace( '/\s*```$/i', '', $ai_content ) ) );
		$parsed     = json_decode( $clean_json, true );

		return ( is_array( $parsed ) && ! empty( $parsed['reusable_blocks'] ) && is_array( $parsed['reusable_blocks'] ) )
			? $parsed['reusable_blocks']
			: array();
	}

	/**
	 * Perform intelligent semantic refactoring on an unzipped temporary session directory.
	 * Orchestrates Stage 1 (Header/Footer) and Stage 2 (Dedicated Reusable Blocks).
	 *
	 * @param string $session_dir   Absolute path to temporary unzipped session folder.
	 * @param array  $custom_config Optional custom AI config from UI.
	 * @return array Status, message, and statistics.
	 */
	public static function refactor_session_directory( string $session_dir, array $custom_config = array() ): array {
		self::$svg_map = array();
		$session_dir = trailingslashit( $session_dir );
		if ( ! is_dir( $session_dir ) ) {
			return array( 'success' => false, 'message' => sprintf( __( 'Session directory not found: %s', 'align' ), $session_dir ) );
		}

		$config = self::get_config( $custom_config );
		error_log( sprintf( '[Align AIRefactor] Starting 2-Stage Pipeline (OpenRouter Model: %s) on: %s', $config['model'], $session_dir ) );

		// Clean macOS junk files recursively
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $session_dir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $item ) {
			$base = $item->getBasename();
			if ( '__MACOSX' === $base || '.__MACOSX' === $base || 0 === strpos( $base, '._' ) || '.DS_Store' === $base ) {
				if ( $item->isDir() ) @rmdir( $item->getPathname() ); else @unlink( $item->getPathname() );
			}
		}

		// 1. Auto-unwrap single wrapped directory or dedicated site subfolder
		// 1. Recursively resolve actual site root directory
		require_once __DIR__ . '/class-align-zip-scanner.php';
		$actual_dir = Align_Zip_Scanner::resolve_actual_root_dir( $session_dir );
		error_log( sprintf( '[Align AIRefactor] Resolved actual site root directory: %s', $actual_dir ) );
		$entries    = array_diff( scandir( $actual_dir ), array( '.', '..' ) );

		// 2. Collect flat HTML files from root and optional pages/ subfolder
		$html_files = array();
		foreach ( $entries as $entry ) {
			if ( 0 === strpos( $entry, '._' ) || 0 === strpos( $entry, '.' ) || 0 === strpos( $entry, '__' ) || '__MACOSX' === $entry ) {
				continue;
			}
			$full_path = $actual_dir . $entry;
			if ( is_file( $full_path ) && in_array( strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
				$slug = sanitize_title( pathinfo( $entry, PATHINFO_FILENAME ) );
				if ( 0 === strpos( $slug, '_' ) ) $slug = ltrim( $slug, '_' );
				if ( empty( $slug ) ) continue;
				if ( 'index' === $slug ) $slug = 'home';
				$html_files[ $slug ] = array(
					'path'     => $full_path,
					'filename' => $entry,
					'raw'      => file_get_contents( $full_path ),
				);
			} elseif ( is_dir( $full_path ) && in_array( strtolower( $entry ), array( 'pages', 'html', 'views' ), true ) ) {
				foreach ( array_diff( scandir( $full_path ), array( '.', '..' ) ) as $sub_e ) {
					if ( 0 === strpos( $sub_e, '._' ) || 0 === strpos( $sub_e, '.' ) || 0 === strpos( $sub_e, '__' ) ) continue;
					$sub_p = trailingslashit( $full_path ) . $sub_e;
					if ( is_file( $sub_p ) && in_array( strtolower( pathinfo( $sub_e, PATHINFO_EXTENSION ) ), array( 'html', 'htm' ), true ) ) {
						$sub_slug = sanitize_title( pathinfo( $sub_e, PATHINFO_FILENAME ) );
						if ( 0 === strpos( $sub_slug, '_' ) ) $sub_slug = ltrim( $sub_slug, '_' );
						if ( empty( $sub_slug ) ) continue;
						if ( 'index' === $sub_slug ) $sub_slug = 'home';
						$html_files[ $sub_slug ] = array(
							'path'     => $sub_p,
							'filename' => $sub_e,
							'raw'      => file_get_contents( $sub_p ),
						);
					}
				}
			}
		}

		if ( empty( $html_files ) ) {
			error_log( '[Align AIRefactor] No un-refactored flat HTML files found. Project archive is already structured or using page folders.' );
			return array(
				'success' => true,
				'message' => __( 'Project archive is already structured. Continuing with standard import.', 'align' ),
			);
		}

		$sample_pages = array();
		$count = 0;
		foreach ( $html_files as $slug => $data ) {
			if ( $count < 3 || 'home' === $slug ) {
				$sample_pages[ $slug ] = self::create_dom_skeleton( $data['raw'] );
				$count++;
			}
		}

		// STAGE 1: Extract Global Header & Footer
		$stage1_res = self::stage1_extract_header_footer( $sample_pages, $config );
		if ( ! empty( $stage1_res['header_html'] ) ) {
			$header_dir = $actual_dir . 'header/';
			if ( ! is_dir( $header_dir ) ) wp_mkdir_p( $header_dir );
			file_put_contents( $header_dir . 'index.html', self::restore_svgs( trim( $stage1_res['header_html'] ) ) );
			error_log( sprintf( '[Align AIRefactor] Saved Header part to %s', $header_dir . 'index.html' ) );
		}
		if ( ! empty( $stage1_res['footer_html'] ) ) {
			$footer_dir = $actual_dir . 'footer/';
			if ( ! is_dir( $footer_dir ) ) wp_mkdir_p( $footer_dir );
			file_put_contents( $footer_dir . 'index.html', self::restore_svgs( trim( $stage1_res['footer_html'] ) ) );
			error_log( sprintf( '[Align AIRefactor] Saved Footer part to %s', $footer_dir . 'index.html' ) );
		}

		// STAGE 2: Dedicated Reusable Block Specialist Pass
		$body_skeletons = array();
		foreach ( $sample_pages as $slug => $skeleton ) {
			$body_skeletons[ $slug ] = self::clean_page_body_markup( $skeleton );
		}

		$raw_blocks      = self::stage2_handle_reusable_blocks( $body_skeletons, $config );
		$reusable_blocks = array();

		if ( ! empty( $raw_blocks ) ) {
			foreach ( $raw_blocks as $block ) {
				$b_slug = sanitize_title( $block['slug'] ?? 'block' );
				$b_dir  = $actual_dir . 'reuse-blocks/' . $b_slug . '/';
				if ( ! is_dir( $b_dir ) ) wp_mkdir_p( $b_dir );

				$orig_snippet = self::restore_svgs( $block['original_html'] ?? '' );
				$block_final  = self::inject_overwrite_attributes( $orig_snippet, $b_slug );
				file_put_contents( $b_dir . 'index.html', $block_final );

				$reusable_blocks[] = array( 'slug' => $b_slug, 'original' => $orig_snippet );
				error_log( sprintf( '[Align AIRefactor] Stage 2: Saved Reusable Block "%s" with Overwrite Layer to %s', $b_slug, $b_dir . 'index.html' ) );
			}
		}

		// STAGE 3: Assemble All Pages (Inject Shortcodes & Clean Body)
		$page_count = 0;
		foreach ( $html_files as $slug => $item ) {
			$content = $item['raw'];
			foreach ( $reusable_blocks as $b_info ) {
				if ( ! empty( $b_info['original'] ) ) {
					$content = self::replace_block_with_shortcode( $content, $b_info['original'], $b_info['slug'] );
				}
			}

			$clean_body = self::clean_page_body_markup( $content );
			$page_dir   = $actual_dir . $slug . '/';
			if ( ! is_dir( $page_dir ) ) wp_mkdir_p( $page_dir );
			file_put_contents( $page_dir . 'index.html', $clean_body );
			$page_count++;
			error_log( sprintf( '[Align AIRefactor] Saved Page "%s" (Shortcodes injected) to %s', $slug, $page_dir . 'index.html' ) );

			if ( file_exists( $item['path'] ) ) @unlink( $item['path'] );
		}

		error_log( sprintf( '[Align AIRefactor] 2-Stage Pipeline Completed: Header, Footer, %d Reusable Blocks, %d Pages', count( $reusable_blocks ), $page_count ) );

		return array(
			'success'      => true,
			'message'      => sprintf( __( 'AI Refactor Completed (2-Stage Pipeline): Extracted Header, Footer, %d Reusable Blocks with Overwrite Layers, and %d Pages with [reuse-block] shortcodes.', 'align' ), count( $reusable_blocks ), $page_count ),
			'blocks_count' => count( $reusable_blocks ),
			'pages_count'  => $page_count,
		);
	}
}
