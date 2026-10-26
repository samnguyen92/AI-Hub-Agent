<?php
/**
 * Align Theme - Builder SEO Handler (RankMath Integration)
 *
 * Reads and writes RankMath meta for pages, generates SEO content via Gemini AI.
 * Integrates with class-align-ai-connection.php for Gemini API access.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-builder-seo-evaluator.php';
Align_Builder_Seo_Evaluator::init();

class Align_Builder_Seo_Handler {

	/**
	 * Map of friendly keys to RankMath post_meta keys.
	 */
	const FIELD_MAP = array(
		'focus_keyword'       => 'rank_math_focus_keyword',
		'title'               => 'rank_math_title',
		'description'         => 'rank_math_description',
		'og_title'            => 'rank_math_og_title',
		'og_description'      => 'rank_math_og_description',
		'twitter_title'       => 'rank_math_twitter_title',
		'twitter_description' => 'rank_math_twitter_description',
		'robots'              => 'rank_math_robots',
		'seo_score'           => 'rank_math_seo_score',
	);

	/**
	 * Delegate Rank Math score evaluation to Align_Builder_Seo_Evaluator.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $focus_keywords Comma-separated focus keywords.
	 * @param string $seo_title      SEO title.
	 * @param string $seo_desc       Meta description.
	 * @param string $seo_slug       Post slug.
	 * @param bool   $save           Whether to persist score to post_meta.
	 * @return array Array with 'score' and 'tests'.
	 */
	public static function evaluate_rank_math_score( $post_id, $focus_keywords = '', $seo_title = '', $seo_desc = '', $seo_slug = '', $save = true ) {
		if ( class_exists( 'Align_Builder_Seo_Evaluator' ) ) {
			return Align_Builder_Seo_Evaluator::evaluate( $post_id, $focus_keywords, $seo_title, $seo_desc, $seo_slug, $save );
		}
		return array( 'score' => 0, 'tests' => array() );
	}

	// -------------------------------------------------------------------------
	// REST Callbacks
	// -------------------------------------------------------------------------

	/**
	 * REST GET /seo-meta: Read RankMath meta for a post.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_seo_meta( $request ) {
		$post_id = intval( $request->get_param( 'id' ) );
		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', 'post_id (id param) is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		$is_lang_root = get_post_meta( $post_id, '_align_lang_root', true );
		$is_front     = ( $post_id === (int) get_option( 'page_on_front' ) ) || ( class_exists( 'Align_Language_Manager' ) && Align_Language_Manager::is_language_front_page( $post_id ) );

		$data = array(
			'post_id'         => $post_id,
			'post_title'      => $post->post_title,
			'slug'            => urldecode( (string) $post->post_name ),
			'excerpt'         => $post->post_excerpt,
			'post_url'        => get_permalink( $post_id ),
			'post_lang'       => get_post_meta( $post_id, '_align_lang', true ) ?: '',
			'is_root_page'    => ( ! empty( $is_lang_root ) || $is_front ),
			'rankmath_active' => defined( 'RANK_MATH_VERSION' ),
			'seopress_active' => defined( 'SEOPRESS_VERSION' ),
		);

		foreach ( self::FIELD_MAP as $key => $meta_key ) {
			$data[ $key ] = get_post_meta( $post_id, $meta_key, true ) ?: '';
		}

		error_log( '[Align SEO] get_seo_meta | post_id=' . $post_id . ' | title=' . $data['post_title'] . ' | slug=' . $data['slug'] . ' | lang=' . $data['post_lang'] . ' | is_root=' . ( $data['is_root_page'] ? '1' : '0' ) . ' | score=' . ( $data['seo_score'] ?? 'none' ) );
		return rest_ensure_response( $data );
	}

	/**
	 * REST POST /seo-meta: Save RankMath meta, title, slug, and excerpt for a post.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_seo_meta( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', 'post_id is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		$saved = array();

		// Save post_title, post_name (slug) and post_excerpt if provided
		$post_update  = array( 'ID' => $post_id );
		$title_val    = $request->get_param( 'post_title' );
		$slug_val     = $request->get_param( 'slug' );
		$excerpt_val  = $request->get_param( 'excerpt' );
		$is_lang_root = get_post_meta( $post_id, '_align_lang_root', true );
		$is_front     = ( $post_id === (int) get_option( 'page_on_front' ) ) || ( class_exists( 'Align_Language_Manager' ) && Align_Language_Manager::is_language_front_page( $post_id ) );

		if ( $title_val !== null && trim( (string) $title_val ) !== '' ) {
			$post_update['post_title'] = sanitize_text_field( (string) $title_val );
			$saved[]                   = 'post_title';
		}

		if ( $slug_val !== null ) {
			$raw_slug   = trim( (string) $slug_val );
			$clean_slug = sanitize_title( $raw_slug );
			if ( ! empty( $clean_slug ) ) {
				if ( ! empty( $is_lang_root ) ) {
					// Root language pages MUST always retain their language root slug (e.g. 'zh')
					$post_update['post_name'] = sanitize_title( $is_lang_root );
					$saved[]                  = 'slug_locked';
				} elseif ( $is_front ) {
					// Front page should keep its original slug
					$post_update['post_name'] = $post->post_name;
					$saved[]                  = 'slug_locked';
				} else {
					$post_update['post_name'] = $clean_slug;
					$saved[]                  = 'slug';
				}
			}
		}

		if ( $excerpt_val !== null ) {
			$post_update['post_excerpt'] = sanitize_textarea_field( (string) $excerpt_val );
			$saved[]                     = 'excerpt';
		}

		if ( count( $post_update ) > 1 ) {
			wp_update_post( $post_update );
		}

		// Save RankMath SEO meta
		foreach ( self::FIELD_MAP as $key => $meta_key ) {
			$val = $request->get_param( $key );
			if ( $val !== null ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $val ) );
				$saved[] = $key;
			}
		}

		// Use the score calculated client-side by WpSeoScorer (which mirrors Rank Math's JS algorithm).
		// Only fall back to PHP evaluator if no client score was provided.
		$client_score = $request->get_param( 'seo_score' );
		if ( $client_score !== null && is_numeric( $client_score ) ) {
			$final_score = max( 0, min( 100, (int) $client_score ) );
			update_post_meta( $post_id, 'rank_math_seo_score', $final_score );
			error_log( '[Align SEO] save_seo_meta | post_id=' . $post_id . ' | score=' . $final_score . ' (client-provided) | saved: ' . implode( ', ', $saved ) );
		} else {
			// Fallback: PHP evaluator (used by bulk/background processes that don't have a browser context).
			$kw          = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			$title       = get_post_meta( $post_id, 'rank_math_title', true );
			$desc        = get_post_meta( $post_id, 'rank_math_description', true );
			$slug        = get_post_field( 'post_name', $post_id );
			$eval        = self::evaluate_rank_math_score( $post_id, $kw, $title, $desc, $slug, true );
			$final_score = $eval['score'];
			error_log( '[Align SEO] save_seo_meta | post_id=' . $post_id . ' | score=' . $final_score . ' (PHP fallback) | saved: ' . implode( ', ', $saved ) );
		}

		clean_post_cache( $post_id );
		return rest_ensure_response( array(
			'success'    => true,
			'post_id'    => $post_id,
			'post_title' => get_the_title( $post_id ),
			'saved'      => $saved,
			'seo_score'  => $final_score,
			'post_url'   => get_permalink( $post_id ),
			'slug'       => urldecode( (string) get_post_field( 'post_name', $post_id ) ),
		) );
	}

	/**
	 * REST POST /seo-generate: Generate SEO meta via Gemini AI.
	 * Expects: { post_id, lang, content (page text, stripped) }
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function generate_seo_meta( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$lang    = sanitize_key( $request->get_param( 'lang' ) ?: 'en' );
		$raw_content = $request->get_param( 'content' );

		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', 'post_id is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		// Resolve lang name and script
		$lang_name   = $lang;
		$lang_script = 'default';
		if ( class_exists( 'Align_Language_Manager' ) ) {
			$lang_cfg = Align_Language_Manager::get_language( $lang );
			if ( $lang_cfg ) {
				$lang_name   = $lang_cfg['name'];
				$lang_script = $lang_cfg['script'] ?? 'default';
			}
		}

		// Render blocks first so align/element comment attributes are expanded to real HTML
		if ( empty( $raw_content ) && ! empty( $post->post_content ) ) {
			$raw_content = do_blocks( $post->post_content );
		} elseif ( ! empty( $raw_content ) ) {
			$raw_content = do_blocks( $raw_content );
		}

		// Extract subheadings for prompt context
		preg_match_all( '/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', (string) $raw_content, $hm );
		$subheadings = array();
		if ( ! empty( $hm[1] ) ) {
			foreach ( $hm[1] as $ht ) {
				$h_clean = trim( strip_tags( $ht ) );
				if ( ! empty( $h_clean ) && mb_strlen( $h_clean ) < 60 ) {
					$subheadings[] = $h_clean;
				}
			}
		}
		$subheading_sample = ! empty( $subheadings ) ? implode( ', ', array_slice( $subheadings, 0, 5 ) ) : '';

		// Strip tags and normalize whitespaces
		$clean_content = strip_tags( (string) $raw_content );
		$clean_content = preg_replace( '/[ \t]+/', ' ', $clean_content );
		$clean_content = preg_replace( '/\n\s*\n/', "\n\n", $clean_content );
		$content_str   = trim( $clean_content );

		if ( empty( $content_str ) && ! empty( $post->post_title ) ) {
			$content_str = $post->post_title;
		}

		if ( mb_strlen( $content_str ) > 100000 ) {
			$content_str = mb_substr( $content_str, 0, 100000 );
		}

		// Build Gemini prompt
		$post_title     = $post->post_title;
		$post_url       = get_permalink( $post_id );
		$is_zh          = ( 'zh' === $lang || 0 === strpos( $lang, 'zh' ) );

		// Detect whether the content itself uses simplified or traditional characters
		$content_has_simp = $is_zh && preg_match( '/[\x{4f1a}\x{8fd9}\x{4e2a}\x{6765}\x{8bf4}\x{5e94}\x{65f6}\x{4e1a}\x{4eec}\x{52a8}\x{9669}\x{7b80}]/u', $content_str );
		$content_has_trad = $is_zh && preg_match( '/[\x{6703}\x{9019}\x{500b}\x{4f86}\x{8aaa}\x{61c9}\x{6642}\x{696d}\x{5011}\x{52d5}\x{96aa}\x{7c21}]/u', $content_str );

		$target_script = $lang_script;
		if ( $is_zh ) {
			if ( $content_has_simp && ! $content_has_trad ) {
				$target_script = 'simplified';
			} elseif ( $content_has_trad && ! $content_has_simp ) {
				$target_script = 'traditional';
			}
		}

		if ( $is_zh ) {
			if ( 'simplified' === $target_script ) {
				$is_traditional = false;
				$is_simplified  = true;
			} else {
				$is_traditional = true;
				$is_simplified  = false;
			}
		} else {
			$is_traditional = false;
			$is_simplified  = false;
		}

		$is_hk = ( 'zh-hk' === strtolower( $lang ) || 'zh_hk' === strtolower( $lang ) );

		$script_notice = '';
		if ( $is_traditional ) {
			if ( $is_hk ) {
				$script_notice = "CRITICAL LOCALE REQUIREMENT: HONG KONG TRADITIONAL CHINESE (zh-HK / 繁體中文（香港）)\n"
					. "This content targets HONG KONG readers — NOT mainland China, NOT Taiwan.\n"
					. "ALL metadata (page_title, focus_keyword, title, description, excerpt, og_title, og_description) MUST:\n"
					. "  - Use Hong Kong Traditional Chinese characters exclusively (e.g. 體, 國, 說, 辦, 險, 讓, 處, 專, 業, 員)\n"
					. "  - NEVER use Simplified Chinese characters\n"
					. "  - Use HK-specific financial/insurance terminology:\n"
					. "    * 僱員 (not 雇员 or 雇員), 顧問 (not 顾问), 聯絡 (not 联系 or 聯繫), 保險 (not 保险)\n"
					. "    * 計劃 (not 计划), 監管局 (HKIA), 保障 (not 保障/保护), 醫療 (not 医疗)\n"
					. "    * 私隱政策 (not 隐私政策), 使用條款 (not 使用条款)\n"
					. "  - Reflect natural Hong Kong Chinese phrasing: how a Hong Kong professional would actually search on Google.com.hk\n"
					. "  - Focus keywords must match how HK users search (e.g. 僱員福利計劃, 員工福利方案香港, 商業保險香港)\n\n";
			} else {
				$script_notice = "CRITICAL SCRIPT REQUIREMENT:\nThe content uses TRADITIONAL CHINESE (繁體中文, 正體字).\nAll metadata values (page_title, focus_keyword, title, description, excerpt, og_title, og_description) MUST strictly use standard Traditional Chinese characters (e.g. 體, 國, 說, 灣, 門, 辦, 險, 讓, 處, 專, 業). NEVER output Simplified characters.\n\n";
			}
		} elseif ( $is_simplified ) {
			$script_notice = "CRITICAL SCRIPT REQUIREMENT:\nThe content uses SIMPLIFIED CHINESE (简体中文).\nAll metadata values (page_title, focus_keyword, title, description, excerpt, og_title, og_description) MUST strictly use Simplified Chinese characters (e.g. 险, 动, 简, 总, 门, 国, 为, 业, 体, 处, 让). NEVER output Traditional characters.\n\n";
		}

		// Title length thresholds aligned with Rank Math JS scorer (14-32 CJK chars, 35-60 EN chars)
		$title_min = $is_zh ? 14 : 35;
		$title_max = $is_zh ? 28 : 58;
		$desc_min  = $is_zh ? 45 : 140;
		$desc_max  = $is_zh ? 95 : 160;

		$title_rule = $is_zh
			? "MUST be {$title_min}-{$title_max} Chinese characters (Rank Math optimal), MUST start with the exact primary focus keyword as the very first characters — no punctuation or other words before the keyword"
			: "MUST be {$title_min}-{$title_max} characters (Rank Math optimal), MUST start with the exact primary focus keyword — no brand name or other words before it";
		$desc_rule = $is_zh
			? "{$desc_min}-{$desc_max} Chinese characters (Rank Math optimal), compelling, MUST include the primary keyword in the very first sentence"
			: "{$desc_min}-{$desc_max} characters (Rank Math optimal), compelling, MUST include the primary keyword in the first sentence";
		$kw_rule = "The first keyword is the PRIMARY keyword. It MUST:\n"
			. "  - Be an exact 2-5 word phrase that ALREADY appears verbatim in the article content\n"
			. "  - Appear within the first 15% of the article text (introduction/first paragraph)\n"
			. "  - Ideally match one of the subheadings (" . ( ! empty( $subheading_sample ) ? $subheading_sample : 'see subheadings above' ) . ")\n"
			. "  - Be the term users actually type into Google to find this specific page\n"
			. "Followed by 1-2 secondary keywords, comma-separated.";

		$page_title_ex = $is_traditional ? "2-6 Traditional Chinese characters e.g. 行業洞見" : ( $is_zh ? "2-6 Chinese characters e.g. 行业洞察" : "concise title 2-4 words" );

		$scoring_checklist = $is_zh
			? "RANK MATH 14-POINT SCORING CHECKLIST (your output MUST satisfy ALL):\n"
				. "  [+15pts] Focus keyword present in SEO title\n"
				. "  [+10pts] Focus keyword present in meta description\n"
				. "  [+10pts] Focus keyword present in URL slug (use pinyin or English equivalent)\n"
				. "  [+10pts] Focus keyword appears in first 15% of content (already in content; just ensure it exists)\n"
				. "  [+10pts] Focus keyword appears anywhere in content\n"
				. "  [+10pts] Content has sufficient length (already in content)\n"
				. "  [+5pts]  Focus keyword in at least one H2-H6 subheading (ensure keyword matches a subheading)\n"
				. "  [+5pts]  URL slug is under 75 characters\n"
				. "  [+5pts]  Keyword density >= 1 occurrence per content (already in content)\n"
				. "  [+5pts]  Internal links present (already in content)\n"
				. "  [+5pts]  Outbound/external links present (already in content)\n"
				. "  [+5pts]  SEO title STARTS with the primary focus keyword (first word/phrase)\n"
				. "  [+5pts]  SEO title length is {$title_min}-{$title_max} chars\n"
				. "  [+5pts]  Image with keyword alt text (hint in excerpt: recommend adding alt text)\n"
			: "RANK MATH 14-POINT SCORING CHECKLIST (your output MUST satisfy ALL):\n"
				. "  [+15pts] Focus keyword present in SEO title — start title with the exact keyword\n"
				. "  [+10pts] Focus keyword present in meta description — include in first sentence\n"
				. "  [+10pts] Focus keyword present in URL slug — use keyword as primary slug words\n"
				. "  [+10pts] Focus keyword appears in first 15% of content (content is fixed)\n"
				. "  [+10pts] Focus keyword appears anywhere in content (content is fixed)\n"
				. "  [+10pts] Content length sufficient (content is fixed)\n"
				. "  [+5pts]  Focus keyword in at least one subheading (choose keyword that matches: {$subheading_sample})\n"
				. "  [+5pts]  URL slug <= 75 chars — keep it short\n"
				. "  [+5pts]  Keyword density (content is fixed)\n"
				. "  [+5pts]  Internal links (content is fixed)\n"
				. "  [+5pts]  Outbound/external links (content is fixed)\n"
				. "  [+5pts]  SEO title starts with the primary focus keyword\n"
				. "  [+5pts]  SEO title length is {$title_min}-{$title_max} chars\n"
				. "  [+5pts]  Image alt text with keyword (hint in excerpt: recommend adding keyword alt text)\n";

		$prompt = "You are an elite SEO strategist. Analyze the webpage content below and craft metadata that maximizes the Rank Math SEO score (target: 90+/100).\n\n"
			. $script_notice
			. "Language: {$lang_name} ({$lang})\n"
			. "Current Post Title: {$post_title}\n"
			. "Page URL: {$post_url}\n"
			. ( ! empty( $subheading_sample ) ? "Existing Subheadings: {$subheading_sample}\n" : '' )
			. "Full Article Content:\n{$content_str}\n\n"
			. $scoring_checklist . "\n"
			. "METADATA RULES:\n"
			. "1. Focus Keyword: {$kw_rule}\n"
			. "2. Title: {$title_rule}\n"
			. "3. Description: {$desc_rule}\n"
			. "4. Slug: The URL slug is PERMANENTLY LOCKED. Return the existing slug verbatim: \"{$post->post_name}\"\n"
			. "5. Output ONLY valid JSON with these exact keys (all values in {$lang_name}):\n"
			. "{\n"
			. "  \"page_title\": \"concise title ({$page_title_ex})\",\n"
			. "  \"focus_keyword\": \"Primary Keyword, Secondary Keyword 1, Secondary Keyword 2\",\n"
			. "  \"title\": \"SEO title starting with Primary Keyword ({$title_min}-{$title_max} chars)\",\n"
			. "  \"description\": \"Meta description including Primary Keyword ({$desc_min}-{$desc_max} chars)\",\n"
			. "  \"slug\": \"{$post->post_name}\",\n"
			. "  \"excerpt\": \"compelling 1-2 sentence excerpt (" . ( $is_zh ? 'under 80 Chinese chars' : 'under 160 chars' ) . ")\",\n"
			. "  \"og_title\": \"Open Graph title (" . ( $is_zh ? '20-28 Chinese chars' : '50-60 chars' ) . ")\",\n"
			. "  \"og_description\": \"OG description (" . ( $is_zh ? 'under 85 Chinese chars' : 'under 160 chars' ) . ")\"\n"
			. "}\n"
			. "Do NOT include any markdown codeblocks or explanation, ONLY the JSON object.";

		error_log( '[Align SEO] generate_seo_meta | post_id=' . $post_id . ' | lang=' . $lang . ' | is_hk=' . ( $is_hk ? '1' : '0' ) . ' | script=' . $lang_script . ' | content_len=' . strlen( $content_str ) );

		// Call AI via Align_AI_Connection (OpenRouter / OpenAI-compatible endpoint)
		if ( ! class_exists( 'Align_AI_Connection' ) ) {
			return new WP_Error( 'ai_unavailable', 'Align AI Connection is not available.', array( 'status' => 503 ) );
		}

		$ai_config  = Align_AI_Connection::get_config();
		$ai_headers = Align_AI_Connection::build_headers( $ai_config );

		if ( empty( $ai_config['api_key'] ) ) {
			return new WP_Error( 'no_api_key', 'AI API key is not configured. Please set it in Align Settings > Connect API.', array( 'status' => 503 ) );
		}

		$body = wp_json_encode( array(
			'model'       => $ai_config['model'],
			'messages'    => array(
				array( 'role' => 'user', 'content' => $prompt ),
			),
			'max_tokens'  => 4096,
			'temperature' => 0.3,
			'reasoning'   => array( 'max_tokens' => 250 ),
		) );

		$http_response = wp_remote_post( $ai_config['endpoint'], array(
			'headers' => $ai_headers,
			'body'    => $body,
			'timeout' => 90,
		) );

		if ( is_wp_error( $http_response ) ) {
			error_log( '[Align SEO] AI HTTP error: ' . $http_response->get_error_message() );
			return new WP_Error( 'http_error', $http_response->get_error_message(), array( 'status' => 502 ) );
		}

		$http_code   = wp_remote_retrieve_response_code( $http_response );
		$http_body   = wp_remote_retrieve_body( $http_response );
		$ai_response = json_decode( $http_body, true );

		error_log( '[Align SEO] Raw AI Body (' . $http_code . '): ' . substr( (string) $http_body, 0, 400 ) );

		if ( $http_code !== 200 || empty( $ai_response['choices'][0]['message']['content'] ) ) {
			$err_msg = isset( $ai_response['error']['message'] ) ? $ai_response['error']['message'] : 'AI returned unexpected response (HTTP ' . $http_code . ').';
			error_log( '[Align SEO] AI API error: ' . $err_msg . ' | HTTP=' . $http_code );
			return new WP_Error( 'ai_error', $err_msg, array( 'status' => 502 ) );
		}

		$ai_result = $ai_response['choices'][0]['message']['content'];

		// Parse JSON from AI response (strip markdown code fences and search for JSON object)
		$raw = trim( (string) $ai_result );
		$raw = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
		$raw = preg_replace( '/\s*```$/m', '', $raw );

		$generated = null;
		if ( preg_match( '/\{[\s\S]*\}/', $raw, $matches ) ) {
			$generated = json_decode( $matches[0], true );
		}
		if ( ! is_array( $generated ) ) {
			$generated = array();
			if ( preg_match( '/"page_title"\s*:\s*"([^"]+)"/', $raw, $m ) ) { $generated['page_title'] = $m[1]; }
			if ( preg_match( '/"focus_keyword"\s*:\s*"([^"]+)"/', $raw, $m ) ) { $generated['focus_keyword'] = $m[1]; }
			if ( preg_match( '/"title"\s*:\s*"([^"]+)"/', $raw, $m ) ) { $generated['title'] = $m[1]; }
			if ( preg_match( '/"description"\s*:\s*"([^"]+)"/', $raw, $m ) ) { $generated['description'] = $m[1]; }
			if ( preg_match( '/"excerpt"\s*:\s*"([^"]+)"/', $raw, $m ) ) { $generated['excerpt'] = $m[1]; }
			if ( preg_match( '/"og_title"\s*:\s*"([^"]+)"/', $raw, $m ) ) { $generated['og_title'] = $m[1]; }
			if ( preg_match( '/"og_description"\s*:\s*"([^"]+)"/', $raw, $m ) ) { $generated['og_description'] = $m[1]; }
		}

		if ( empty( $generated['focus_keyword'] ) && empty( $generated['title'] ) ) {
			error_log( '[Align SEO] Failed to parse AI JSON: ' . $raw );
			return new WP_Error( 'parse_error', 'Failed to parse AI response.', array( 'status' => 500 ) );
		}

		if ( empty( $generated['og_title'] ) && ! empty( $generated['title'] ) ) {
			$generated['og_title'] = $generated['title'];
		}
		if ( empty( $generated['og_description'] ) && ! empty( $generated['description'] ) ) {
			$generated['og_description'] = $generated['description'];
		}

		// Sanitize all values and ALWAYS preserve existing post slug
		$result = array();
		$allowed = array( 'page_title', 'focus_keyword', 'title', 'description', 'og_title', 'og_description', 'excerpt' );
		foreach ( $allowed as $k ) {
			$result[ $k ] = sanitize_text_field( $generated[ $k ] ?? '' );
		}
		$result['slug'] = (string) $post->post_name;

		error_log( '[Align SEO] Generated SEO meta for post_id=' . $post_id . ' | keyword=' . $result['focus_keyword'] . ' | page_title=' . ( $result['page_title'] ?? 'none' ) );
		return rest_ensure_response( array(
			'success'    => true,
			'post_id'    => $post_id,
			'lang'       => $lang,
			'generated'  => $result,
		) );
	}
}
