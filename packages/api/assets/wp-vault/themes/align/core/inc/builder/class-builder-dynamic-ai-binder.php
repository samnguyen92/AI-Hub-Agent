<?php
/**
 * Align Theme Core Engine - Dynamic Block Studio AI Token Binder
 *
 * Calls centralized Align AI Connection (Gemini Flash) to transpile static HTML
 * into dynamic WordPress block template by injecting whitelisted tokens and identifying loops.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Dynamic_AI_Binder {

	/**
	 * In-memory map for preserved SVGs during AI conversion
	 *
	 * @var array
	 */
	private static $svg_map = array();

	/**
	 * Initialize REST API hooks
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Register REST routes for AI binding
	 */
	public static function register_rest_routes() {
		register_rest_route(
			ALIGN_BUILDER_REST_NS,
			'/studio-ai-bind',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_ai_bind' ),
				'permission_callback' => function() {
					return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Handle REST request to bind dynamic tokens using AI
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_ai_bind( $request ) {
		$params           = $request->get_json_params();
		$raw_html         = ! empty( $params['html'] ) ? wp_unslash( $params['html'] ) : '';
		$custom_tokens    = ! empty( $params['tokens'] ) && is_array( $params['tokens'] ) ? $params['tokens'] : array();
		$user_instruction = ! empty( $params['user_instruction'] ) ? sanitize_textarea_field( wp_unslash( $params['user_instruction'] ) ) : '';
		$source_page_id   = ! empty( $params['source_page_id'] ) ? absint( $params['source_page_id'] ) : 0;
		$post_id          = ! empty( $params['post_id'] ) ? absint( $params['post_id'] ) : 0;

		if ( empty( trim( $raw_html ) ) ) {
			return new WP_Error( 'empty_html', __( 'Cannot convert empty HTML payload.', 'align' ), array( 'status' => 400 ) );
		}

		if ( ! class_exists( 'Align_AI_Connection' ) ) {
			return new WP_Error( 'missing_ai_connection', __( 'Align AI Connection module is not available.', 'align' ), array( 'status' => 500 ) );
		}

		$config = Align_AI_Connection::get_config();
		if ( empty( $config['api_key'] ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'AI API Key is missing. Please configure your API key in Align Settings > Connect API.', 'align' ),
				array( 'status' => 400 )
			);
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		$start_time = microtime( true );

		// 1. Pre-process HTML: tokenize SVGs to preserve 100% vector code and save prompt tokens
		self::$svg_map = array();
		$html_for_ai   = preg_replace_callback( '/<svg\b[^>]*>[\s\S]*?<\/svg>/is', function( $m ) {
			$token = 'ALIGN_SVG_PLACEHOLDER_' . count( self::$svg_map );
			self::$svg_map[ $token ] = $m[0];
			return '<div data-align-svg-token="' . $token . '"></div>';
		}, $raw_html );

		// 2. Build system and user prompt
		$system_prompt = self::build_system_prompt( $custom_tokens, $user_instruction );
		$user_prompt   = "Convert this HTML to a dynamic block template with whitelisted tokens.\n";
		if ( ! empty( $user_instruction ) ) {
			$user_prompt .= "User specification: \"" . $user_instruction . "\"\n";
		}
		$user_prompt .= "\nHTML Input:\n" . $html_for_ai;

		$payload_data = array(
			'model'           => $config['model'],
			'response_format' => array( 'type' => 'json_object' ),
			'messages'        => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_prompt ),
			),
		);

		// Optimize OpenRouter requests for maximum speed and avoid reasoning stalls
		if ( false !== strpos( $config['endpoint'], 'openrouter.ai' ) ) {
			$payload_data['provider']  = array( 'sort' => 'throughput' );
			$payload_data['reasoning'] = array( 'effort' => 'low' );
		}

		$request_body = wp_json_encode( $payload_data );
		$api_timeout  = ! empty( $config['timeout'] ) ? (int) $config['timeout'] : 120;
		$headers      = Align_AI_Connection::build_headers( $config );

		$response = wp_remote_post( $config['endpoint'], array(
			'headers'     => $headers,
			'timeout'     => $api_timeout,
			'body'        => $request_body,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			$err_msg = $response->get_error_message();
			error_log( '[Align DynamicStudio:AI] Remote post error: ' . $err_msg );
			if ( false !== strpos( $err_msg, 'timed out' ) || false !== strpos( $err_msg, 'cURL error 28' ) ) {
				$err_msg = sprintf(
					__( 'AI request timed out after %d seconds. The model "%s" was too slow. Try again or switch to "google/gemini-2.5-flash" in Align Settings > Connect API for sub-second speed.', 'align' ),
					$api_timeout,
					esc_html( $config['model'] )
				);
			}
			return new WP_Error( 'ai_request_failed', $err_msg, array( 'status' => 500 ) );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_str    = wp_remote_retrieve_body( $response );

		if ( 200 !== $status_code ) {
			error_log( sprintf( '[Align DynamicStudio:AI] Remote API returned HTTP %d: %s', $status_code, substr( $body_str, 0, 300 ) ) );
			$err_json = json_decode( $body_str, true );
			$err_msg  = $err_json['error']['message'] ?? ( 'AI provider returned error code ' . $status_code );
			return new WP_Error( 'ai_provider_error', $err_msg, array( 'status' => $status_code ) );
		}

		$json_data  = json_decode( $body_str, true );
		$ai_content = $json_data['choices'][0]['message']['content'] ?? '';

		if ( empty( $ai_content ) ) {
			return new WP_Error( 'ai_empty_response', __( 'AI provider returned an empty response.', 'align' ), array( 'status' => 500 ) );
		}

		// 3. Clean up and parse JSON response
		$clean_json = trim( preg_replace( '/^```json\s*/i', '', preg_replace( '/\s*```$/i', '', $ai_content ) ) );
		$parsed     = json_decode( $clean_json, true );

		$converted_html   = '';
		$bindings_summary = array();

		if ( is_array( $parsed ) && ! empty( $parsed['html'] ) ) {
			$converted_html   = $parsed['html'];
			$bindings_summary = ! empty( $parsed['bindings_summary'] ) && is_array( $parsed['bindings_summary'] ) ? $parsed['bindings_summary'] : array();
		} elseif ( is_array( $parsed ) && ! empty( $parsed['outer_html'] ) ) {
			$converted_html = $parsed['outer_html'];
		} else {
			$converted_html = $clean_json;
		}

		// 4. Restore SVGs
		foreach ( self::$svg_map as $token => $orig_svg ) {
			$converted_html = str_replace( '<div data-align-svg-token="' . $token . '"></div>', $orig_svg, $converted_html );
			$converted_html = str_replace( $token, $orig_svg, $converted_html );
		}

		// 5. Pre-render dynamic markup for immediate 0-roundtrip live preview
		$rendered_html = class_exists( 'Align_Builder_Dynamic_Renderer' )
			? Align_Builder_Dynamic_Renderer::render_for_preview( $converted_html, $source_page_id, $post_id )
			: $converted_html;

		$duration = round( ( microtime( true ) - $start_time ) * 1000 );
		error_log( sprintf( '[Align DynamicStudio:AI] AI token binding completed in %d ms | Input len: %d | Output len: %d', $duration, strlen( $raw_html ), strlen( $converted_html ) ) );

		return rest_ensure_response( array(
			'status'        => 'success',
			'html'          => $converted_html,
			'rendered_html' => $rendered_html,
			'bindings'      => $bindings_summary,
			'duration'      => $duration,
			'message'       => __( 'Dynamic tokens assigned successfully by AI!', 'align' ),
		) );
	}

	/**
	 * Build strict system prompt with whitelisted tokens
	 *
	 * @param array  $custom_tokens   Optional token overrides.
	 * @param string $user_instruction Optional user description of desired dynamic behavior.
	 * @return string
	 */
	private static function build_system_prompt( array $custom_tokens = array(), string $user_instruction = '' ): string {
		$tokens_context = '';
		if ( ! empty( $custom_tokens ) ) {
			$tokens_context = "\nTokens list provided by client:\n" . wp_json_encode( $custom_tokens );
		}

		$user_instruction_section = '';
		if ( ! empty( trim( $user_instruction ) ) ) {
			$user_instruction_section = "\n\n### USER'S CUSTOM REQUIREMENT / INTENT (CRITICAL GUIDANCE):\n" .
				"The user specifically describes their goal for this block:\n" .
				"\"" . trim( $user_instruction ) . "\"\n" .
				"Carefully satisfy the user's intent when picking tokens and repeaters. HOWEVER, YOU MUST STILL ABSOLUTELY ADHERE TO RULE #1: PRESERVE THE EXISTING HTML STRUCTURE, CLASSES, IDS, AND TAGS EXACTLY. DO NOT INVENT NEW HTML; ONLY REPLACE DUMMY TEXT, LINKS, IMAGES, OR CARDS WITH MATCHING TOKENS ACCORDING TO THE USER'S INTENT.";
		}

		return "You are a deterministic HTML-to-WordPress Dynamic Block Transpiler for Align Theme.
Your SOLE AND EXCLUSIVE task is to convert static HTML into clean dynamic template markup by replacing dummy text, dummy links, dummy images, and repeater items with whitelisted tokens.

### STRICT BOUNDARY RULES:
1. PRESERVE 100% CSS CLASSES, IDS & ATTRIBUTES: Do NOT modify, add, or delete Tailwind classes, Bootstrap classes, IDs, inline styles, or layout tags. Keep all styling classes byte-for-byte intact. The raw HTML structure is fixed and already designed; your job is ONLY to place tokens into the correct positions.
2. REPEATER & POSTS_PER_PAGE AUTOMATIC DETECTION:
   - COUNT how many repetitive sibling cards/items exist in the input grid/list!
     * If the input grid has 3 cards (e.g. 3 columns), count N = 3.
     * If the input grid has 4 cards (e.g. 4 columns), count N = 4.
     * If the input grid has 8 cards (e.g. 2 rows of 4), count N = 8.
     * If the user specifies a count in their custom instruction (e.g. 'show 9 cards'), use that count instead!
   - Keep ONLY the FIRST item as the single card template, and bind dynamic tokens to it.
   - PURGE all duplicate sibling dummy items.
   - Wrap the single card template in: {{ loop_items posts_per_page=\"N\" }}...{{ /loop_items }}
   - ALSO add data-posts-per-page=\"N\" to the grid container element.
   - Example (input has 3 cards in a 3-column layout):
     <div class=\"grid grid-cols-3\" data-posts-per-page=\"3\">
       {{ loop_items posts_per_page=\"3\" }}
       <article class=\"card\">...</article>
       {{ /loop_items }}
     </div>
3. RANKINGS, STEP BADGES & NUMBER COUNTERS:
   - When cards or list items contain sequential order numbers, rank badges (e.g. '#1', '#2', '01', '02', 'Step 1', 'Top 1'), replace the static number with '{{ loop.index }}' or '#{{ loop.index }}'.
   - Example: <span class=\"rank\">#1</span> => <span class=\"rank\">#{{ loop.index }}</span>
   - Example: <div class=\"step-num\">01</div> => <div class=\"step-num\">0{{ loop.index }}</div>
4. LOAD MORE & PAGINATION:
   - If a 'Load More', 'Xem thêm', or pagination button exists, add data-page=\"1\" data-max-page=\"{{ max_pages }}\" data-posts-per-page=\"N\" (using the detected count N).
   - Example: <button class=\"align-load-more-btn\" data-page=\"1\" data-max-page=\"{{ max_pages }}\" data-posts-per-page=\"3\">Load more</button>
5. WHITELISTED TOKENS DICTIONARY:
   - {{ post.title }}: Post title (for main headings, card titles, article titles)
   - {{ post.permalink }}: Post permalink URL (for <a href=\"...\"> links pointing to the post)
   - {{ post.thumbnail }}: Featured image URL (for <img src=\"...\">)
   - {{ post.thumbnail_alt }}: Featured image alt text (for <img alt=\"...\">)
   - {{ post.excerpt }}: Short summary or excerpt text (for <p> or excerpt containers)
   - {{ post.content }}: Full post content
   - {{ post.reading_time }}: Estimated reading time (e.g. '3 min read' or '5 min read')
   - {{ post.comment_count }}: Number of comments (e.g. '5' or '12 comments')
   - {{ post.date }}: Publish date (for date badges, timestamps)
   - {{ post.modified_date }}: Last updated date
   - {{ post.category }}: Primary category name
   - {{ post.category_url }}: Primary category archive URL
   - {{ post.tag }}: Primary tag name or badge text (e.g. for pills, category/topic badges like 'FITMENT GUIDE', 'NEWS')
   - {{ post.tag_url }}: Primary tag archive URL (for badge/tag link)
   - {{ post.tags }}: Comma-separated tag list
   - {{ post.author_name }}: Author display name
   - {{ post.author_avatar }}: Author avatar URL (for author avatar <img src=\"...\">)
   - {{ post.author_url }}: Author profile URL (for author link <a href=\"...\">)
   - {{ post.author_bio }}: Author bio description
   - {{ post.slug }}: URL slug of the post
   - {{ post.id }}: Numeric post ID
   - {{ loop_items }}: Loop placeholder representing cloned child cards in a grid/list
   - {{ loop.index }}: Loop counter (1-based: 1, 2, 3...). CRITICAL FOR RANKINGS & STEP BADGES: When cards have ordered numbers like '#1', '01', 'Rank 1', 'Step 1', or badge counters, replace that static number with '#{{ loop.index }}' or '{{ loop.index }}'. Example: <span class=\"rank-badge\">#{{ loop.index }}</span>
   - {{ max_pages }}: Total pages for query pagination. Example: data-max-page=\"{{ max_pages }}\"
   - {{ posts_per_page }}: Number of posts per page/query limit. Example: data-posts-per-page=\"{{ posts_per_page }}\"
   - {{ site.name }}: Site title
   - {{ site.url }}: Site home URL
   - {{ site.description }}: Site tagline/description
   - {{ site.year }}: Current calendar year (e.g. '2026'). Example: &copy; {{ site.year }} {{ site.name }}
   - {{ site.logo }}: Custom site logo URL
   - {{ acf.field_name }}: Custom ACF field if a field appears to be a specific custom attribute
   - {{ meta.meta_key }}: Custom post meta field
{$tokens_context}{$user_instruction_section}

6. OUTPUT FORMAT:
   Return ONLY valid JSON (no markdown fences, no conversational text):
   {
     \"html\": \"<converted clean HTML markup>\",
     \"bindings_summary\": [\"post.title\", \"post.permalink\", \"post.thumbnail\", \"loop.index\"]
   }";
	}
}
