<?php
/**
 * Align Theme - Builder AI Page Content Translator
 *
 * Provides REST endpoints to translate array of text strings or full post content
 * using Align_AI_Connection (OpenRouter / OpenAI-compatible).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-builder-block-translator.php';
require_once __DIR__ . '/class-builder-template-translator.php';

class Align_Builder_Translator {

	/**
	 * Maximum texts per AI batch request to ensure speed and token limits.
	 */
	const BATCH_SIZE = 15;

	public static function rest_translate_content( $request ) {
		$texts               = $request->get_param( 'texts' );
		$target_lang         = sanitize_key( $request->get_param( 'target_lang' ) ?: '' );
		$source_lang         = sanitize_key( $request->get_param( 'source_lang' ) ?: 'en' );
		$custom_instructions = sanitize_textarea_field( $request->get_param( 'custom_instructions' ) ?: '' );

		if ( ! is_array( $texts ) || empty( $texts ) ) return new WP_Error( 'invalid_texts', 'texts required.', array( 'status' => 400 ) );
		if ( empty( $target_lang ) ) return new WP_Error( 'missing_lang', 'target_lang required.', array( 'status' => 400 ) );
		if ( ! class_exists( 'Align_AI_Connection' ) ) return new WP_Error( 'ai_unavailable', 'Align AI Connection unavailable.', array( 'status' => 503 ) );

		$total = count( $texts );
		error_log( "[Align AI Translator] Translating {$total} strings to '{$target_lang}' (source: '{$source_lang}')" );

		$translated_all = array();
		$batches        = array_chunk( $texts, self::BATCH_SIZE, true );
		foreach ( $batches as $batch ) {
			$batch_translated = self::translate_batch( array_values( $batch ), $target_lang, $source_lang, $custom_instructions );
			$translated_all   = array_merge( $translated_all, $batch_translated );
		}

		return rest_ensure_response( array( 'success' => true, 'target_lang' => $target_lang, 'count' => count( $translated_all ), 'translated' => $translated_all ) );
	}

	public static function rest_copy_original_post( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$tp_id   = sanitize_text_field( $request->get_param( 'tp_id' ) ?: '' );
		if ( ! $post_id && ! empty( $tp_id ) ) {
			return Align_Builder_Template_Translator::copy_original_template( $tp_id, sanitize_text_field( $request->get_param( 'template_type' ) ?: '' ) );
		}
		if ( ! $post_id ) return new WP_Error( 'missing_id', 'post_id or tp_id is required.', array( 'status' => 400 ) );

		$post = get_post( $post_id );
		if ( ! $post ) return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );

		$source_id = self::resolve_source_post_id( $post_id );
		if ( empty( $source_id ) ) return new WP_Error( 'no_source', 'Linked original English post not found.', array( 'status' => 404 ) );

		$source_post = get_post( $source_id );
		if ( ! $source_post ) return new WP_Error( 'source_not_found', "Original source post #{$source_id} not found.", array( 'status' => 404 ) );

		if ( function_exists( 'kses_remove_filters' ) ) kses_remove_filters();
		wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $source_post->post_content ) ) );
		if ( function_exists( 'kses_init_filters' ) ) kses_init_filters();

		foreach ( array( '_align_page_css', '_align_page_js', '_align_reusable_overrides' ) as $mk ) {
			$val = get_post_meta( $source_id, $mk, true );
			if ( ! empty( $val ) ) update_post_meta( $post_id, $mk, $val );
		}

		clean_post_cache( $post_id );
		error_log( "[Align AI Translator] Copied original post content from #{$source_id} to #{$post_id}" );

		return rest_ensure_response( array( 'success' => true, 'post_id' => $post_id, 'source_id' => $source_id, 'source_title' => $source_post->post_title, 'post_content' => $source_post->post_content, 'message' => sprintf( __( 'Successfully copied content from "%s" (#%d).', 'align' ), $source_post->post_title, $source_id ) ) );
	}

	/**
	 * REST POST /copy-original-section: Resolves and returns matching original section HTML.
	 */
	public static function rest_copy_original_section( $request ) {
		$post_id   = intval( $request->get_param( 'post_id' ) );
		$sec_idx   = $request->get_param( 'section_index' );
		$sec_id    = sanitize_text_field( $request->get_param( 'section_id' ) ?: '' );

		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', 'post_id is required.', array( 'status' => 400 ) );
		}

		$source_id = self::resolve_source_post_id( $post_id );
		if ( empty( $source_id ) ) {
			return new WP_Error( 'no_source', 'Linked original English post could not be found.', array( 'status' => 404 ) );
		}

		$source_post = get_post( $source_id );
		if ( ! $source_post ) {
			return new WP_Error( 'source_not_found', "Original source post #{$source_id} not found.", array( 'status' => 404 ) );
		}

		$raw = $source_post->post_content;
		if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>([\s\S]*?)<!-- \/wp:align\/custom-html -->/', $raw, $m ) ) {
			$raw = $m[1];
		}

		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<div>' . mb_convert_encoding( $raw, 'HTML-ENTITIES', 'UTF-8' ) . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath   = new DOMXPath( $doc );
		$matched = null;

		if ( ! empty( $sec_id ) ) {
			$nodes = $xpath->query( "//*[@id='{$sec_id}']" );
			if ( $nodes && $nodes->length > 0 ) $matched = $nodes->item( 0 );
		}

		if ( ! $matched && null !== $sec_idx && is_numeric( $sec_idx ) ) {
			$sections = $xpath->query( '//section' );
			$idx      = intval( $sec_idx );
			if ( $sections && $idx >= 0 && $idx < $sections->length ) $matched = $sections->item( $idx );
		}

		if ( ! $matched ) {
			return new WP_Error( 'section_not_found', 'Matching section could not be found in original post.', array( 'status' => 404 ) );
		}

		$matched_html = $doc->saveHTML( $matched );
		error_log( "[Align AI Translator] Copied original section (idx: {$sec_idx}, id: '{$sec_id}') from #{$source_id} for #{$post_id}" );

		return rest_ensure_response( array(
			'success'        => true,
			'html'           => $matched_html,
			'source_post_id' => $source_id,
			'source_title'   => $source_post->post_title,
		) );
	}

	/**
	 * Resolve linked source English post ID for a translation.
	 */
	public static function resolve_source_post_id( $post_id ) {
		$src_id = get_post_meta( $post_id, Align_Language_Manager::META_PARENT, true );
		if ( empty( $src_id ) && class_exists( 'Align_Language_Manager' ) ) {
			foreach ( Align_Language_Manager::get_translations( $post_id ) as $tr ) {
				if ( 'en' === $tr['lang'] && intval( $tr['post_id'] ) !== $post_id ) {
					return intval( $tr['post_id'] );
				}
			}
		}
		return $src_id ?: get_post_meta( $post_id, '_align_translation_source_id', true );
	}

	/**
	 * REST POST /translate-post: Translates a post's content and title on server while preserving all block styles, classes, and layouts.
	 * Expects: { post_id: int, target_lang: string }
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_translate_post( $request ) {
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
		$post_id             = intval( $request->get_param( 'post_id' ) );
		$tp_id               = sanitize_text_field( $request->get_param( 'tp_id' ) ?: '' );
		$target_lang         = sanitize_key( $request->get_param( 'target_lang' ) ?: '' );
		$custom_instructions = sanitize_textarea_field( $request->get_param( 'custom_instructions' ) ?: '' );

		if ( ! $post_id && ! empty( $tp_id ) ) {
			return Align_Builder_Template_Translator::translate_template( $tp_id, $target_lang, $custom_instructions, sanitize_text_field( $request->get_param( 'template_type' ) ?: '' ) );
		}
		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', 'post_id or tp_id is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		if ( empty( $target_lang ) ) {
			$target_lang = get_post_meta( $post_id, '_align_lang', true ) ?: 'zh';
		}

		error_log( "[Align AI Translator] Translating post #{$post_id} to '{$target_lang}'" );

		// Resolve best source content (check English translation post if current post has few/empty blocks)
		$source_post   = $post;
		$source_raw    = $post->post_content;
		$parsed_source = parse_blocks( $source_raw );
		$valid_blocks  = 0;
		foreach ( $parsed_source as $b ) {
			if ( ! empty( $b['blockName'] ) ) {
				$valid_blocks++;
			}
		}

		// If current post has fewer than 5 valid blocks, try getting from linked English post
		if ( $valid_blocks < 5 && class_exists( 'Align_Language_Manager' ) ) {
			$translations = Align_Language_Manager::get_translations( $post_id );
			foreach ( $translations as $tr ) {
				if ( 'en' === $tr['lang'] && intval( $tr['post_id'] ) !== $post_id ) {
					$en_post = get_post( $tr['post_id'] );
					if ( $en_post && ! empty( $en_post->post_content ) ) {
						$source_post   = $en_post;
						$source_raw    = $en_post->post_content;
						$parsed_source = parse_blocks( $source_raw );
						error_log( "[Align AI Translator] Using English source post #{$en_post->ID} for translation to '{$target_lang}'" );
						break;
					}
				}
			}
		}

		$text_targets = array();
		$slots        = array();
		Align_Builder_Block_Translator::collect_translatable_slots( $parsed_source, $text_targets, $slots );

		if ( empty( $text_targets ) ) {
			return rest_ensure_response( array( 'success' => true, 'post_id' => $post_id, 'message' => 'No translatable content found.' ) );
		}

		// Translate in batches
		$batches        = array_chunk( $text_targets, self::BATCH_SIZE, true );
		$translated_all = array();
		foreach ( $batches as $batch ) {
			$batch_res      = self::translate_batch( array_values( $batch ), $target_lang, 'en', $custom_instructions );
			$translated_all = array_merge( $translated_all, $batch_res );
		}

		// Apply translations in-place into block slots (preserving 100% of classes, tags, spans, svgs, styles)
		Align_Builder_Block_Translator::apply_translated_slots( $slots, $translated_all, $text_targets );
		if ( class_exists( 'Align_Language_Url_Localizer' ) ) {
			Align_Language_Url_Localizer::localize_blocks( $parsed_source, $target_lang );
		}

		// Serialize blocks back into clean Gutenberg content and localize raw hrefs
		$new_html = serialize_blocks( $parsed_source );
		if ( class_exists( 'Align_Language_Url_Localizer' ) ) {
			$new_html = Align_Language_Url_Localizer::localize_html( $new_html, $target_lang );
		}

		// Translate post title
		$orig_title  = $source_post->post_title;
		$clean_title = trim( preg_replace( '/\s*\[[A-Z]{2}\]\s*$/i', '', $orig_title ) );
		$new_title   = $post->post_title;
		if ( ! empty( $clean_title ) && preg_match( '/[a-zA-Z]{2,}/', $clean_title ) ) {
			$title_trans = self::translate_batch( array( $clean_title ), $target_lang, 'en', $custom_instructions );
			if ( ! empty( $title_trans[0] ) ) {
				$new_title = $title_trans[0];
			}
		}

		if ( function_exists( 'kses_remove_filters' ) ) {
			kses_remove_filters();
		}
		wp_update_post( array(
			'ID'           => $post_id,
			'post_title'   => wp_slash( $new_title ),
			'post_content' => wp_slash( $new_html ),
		) );
		if ( function_exists( 'kses_init_filters' ) ) {
			kses_init_filters();
		}
		clean_post_cache( $post_id );
		error_log( "[Align AI Translator] Successfully updated post #{$post_id} while preserving block structure and styles (" . strlen( $new_html ) . ' bytes)' );

		return rest_ensure_response( array(
			'success'      => true,
			'post_id'      => $post_id,
			'target_lang'  => $target_lang,
			'text_count'   => count( $text_targets ),
			'post_title'   => get_the_title( $post_id ),
			'post_content' => get_post_field( 'post_content', $post_id ),
		) );
	}

	/**
	 * REST POST /generate-content: Generates or expands article content with AI.
	 * Expects: { post_id, prompt, mode, lang }
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_generate_content( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) );
		$prompt  = sanitize_text_field( $request->get_param( 'prompt' ) ?: '' );
		$mode    = sanitize_key( $request->get_param( 'mode' ) ?: 'section' );
		$lang    = sanitize_key( $request->get_param( 'lang' ) ?: 'zh' );

		if ( ! $post_id ) {
			return new WP_Error( 'missing_id', 'post_id is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'not_found', 'Post not found.', array( 'status' => 404 ) );
		}

		if ( ! class_exists( 'Align_AI_Connection' ) ) {
			return new WP_Error( 'ai_unavailable', 'Align AI Connection is not available.', array( 'status' => 503 ) );
		}

		$ai_config = Align_AI_Connection::get_config();
		if ( empty( $ai_config['api_key'] ) ) {
			return new WP_Error( 'no_api_key', 'AI API key is missing. Please set it in Align Settings.', array( 'status' => 503 ) );
		}

		$lang_name   = ( 'zh' === $lang ) ? 'Chinese' : $lang;
		$lang_script = 'default';
		if ( class_exists( 'Align_Language_Manager' ) ) {
			$l_cfg = Align_Language_Manager::get_language( $lang );
			if ( $l_cfg ) {
				$lang_name   = $l_cfg['name'];
				$lang_script = $l_cfg['script'] ?? 'default';
			}
		}

		$script_extra = '';
		if ( 'traditional' === $lang_script || false !== stripos( $lang_name, 'traditional' ) || false !== stripos( $lang_name, '繁體' ) ) {
			$script_extra = "CRITICAL SCRIPT: Write strictly in TRADITIONAL CHINESE (繁體中文, 正體字). Standard Traditional characters only (e.g. 體, 國, 說, 灣, 門, 辦, 險, 讓, 處, 專, 業). NEVER output Simplified characters.\n";
		} elseif ( 'simplified' === $lang_script || false !== stripos( $lang_name, 'simplified' ) || false !== stripos( $lang_name, '简体' ) ) {
			$script_extra = "CRITICAL SCRIPT: Write strictly in SIMPLIFIED CHINESE (简体中文).\n";
		}

		$mode_instruction = 'Write a comprehensive, professional article section with an H2 heading and 2-3 informative paragraphs.';
		if ( 'intro' === $mode ) {
			$mode_instruction = 'Write an engaging, authoritative introduction with an H2 heading and 2 clear paragraphs.';
		} elseif ( 'conclusion' === $mode ) {
			$mode_instruction = 'Write an impactful conclusion summarizing actionable advice with an H2 heading and 2 paragraphs.';
		} elseif ( 'faq' === $mode ) {
			$mode_instruction = 'Write 3 frequently asked questions (FAQs) with H3 questions and concise answer paragraphs.';
		} elseif ( 'key_takeaways' === $mode ) {
			$mode_instruction = 'Write a Key Takeaways section with an H2 heading and an unordered list (<ul><li>) of 4-5 bullet points.';
		}

		$system_prompt = "You are a professional corporate and insurance writer for Trusted Union. Output valid HTML suitable for WordPress Gutenberg blocks (e.g. <h2>, <h3>, <p>, <ul>, <li>). Do NOT wrap in markdown fences or include <html>/<body> tags.";
		$user_prompt   = "Article Title: {$post->post_title}\nLanguage: {$lang_name} ({$lang})\nTask: {$mode_instruction}\n{$script_extra}Additional instructions: {$prompt}\nGenerate the content in {$lang_name}:";

		$body = wp_json_encode( array(
			'model'       => $ai_config['model'],
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user',   'content' => $user_prompt ),
			),
			'max_tokens'  => 4096,
			'temperature' => 0.5,
		) );

		$response = wp_remote_post( $ai_config['endpoint'], array(
			'headers' => Align_AI_Connection::build_headers( $ai_config ),
			'body'    => $body,
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'http_error', $response->get_error_message(), array( 'status' => 500 ) );
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$content = trim( $data['choices'][0]['message']['content'] ?? '' );
		$content = preg_replace( '/^```(?:html)?\s*/i', '', $content );
		$content = preg_replace( '/\s*```$/', '', $content );

		return rest_ensure_response( array(
			'success' => true,
			'post_id' => $post_id,
			'html'    => $content,
			'mode'    => $mode,
			'lang'    => $lang,
		) );
	}

	/**
	 * Translate an array of strings in a single batch API call.
	 * Preserves HTML tags and formatting within strings.
	 *
	 * @param array  $texts               Array of strings to translate.
	 * @param string $target_lang         Target language code.
	 * @param string $source_lang         Source language code.
	 * @param string $custom_instructions Optional runtime style instructions.
	 * @return array Array of translated strings (same length).
	 */
	public static function translate_batch( $texts, $target_lang, $source_lang = 'en', $custom_instructions = '' ) {
		if ( empty( $texts ) ) return array();

		$ai_config  = Align_AI_Connection::get_config();
		$ai_headers = Align_AI_Connection::build_headers( $ai_config );

		if ( empty( $ai_config['api_key'] ) ) {
			error_log( '[Align AI Translator] API key missing.' );
			return $texts;
		}

		// Resolve language display name, script, and persistent prompt hint
		$lang_name        = strtoupper( $target_lang );
		$lang_script      = 'default';
		$persistent_hint  = '';
		if ( class_exists( 'Align_Language_Manager' ) ) {
			$lang_cfg = Align_Language_Manager::get_language( $target_lang );
			if ( $lang_cfg ) {
				$lang_name       = $lang_cfg['name'];
				$lang_script     = $lang_cfg['script'] ?? 'default';
				$persistent_hint = $lang_cfg['prompt_hint'] ?? '';
			}
		}

		$count         = count( $texts );
		$indexed_input = array();
		foreach ( array_values( $texts ) as $i => $txt ) {
			$indexed_input[ 'k' . $i ] = $txt;
		}

		require_once __DIR__ . '/class-builder-prompt-helper.php';
		$prompt = Align_Builder_Prompt_Helper::compose_batch_prompt(
			$indexed_input,
			$target_lang,
			$lang_name,
			$lang_script,
			$custom_instructions,
			$persistent_hint
		);

		$body = wp_json_encode( array(
			'model'       => $ai_config['model'],
			'messages'    => array(
				array( 'role' => 'system', 'content' => 'You are a translation engine. Return only a valid JSON object mapping each key to its translated string.' ),
				array( 'role' => 'user',   'content' => $prompt ),
			),
			'max_tokens'  => 8192,
			'temperature' => 0.2,
			'reasoning'   => array( 'max_tokens' => 250 ),
		) );

		$t0            = microtime( true );
		$http_response = wp_remote_post( $ai_config['endpoint'], array(
			'headers' => $ai_headers,
			'body'    => $body,
			'timeout' => 90,
		) );
		$dt            = round( microtime( true ) - $t0, 2 );

		if ( is_wp_error( $http_response ) ) {
			error_log( '[Align AI Translator] HTTP error: ' . $http_response->get_error_message() );
			return $texts;
		}

		$http_code = wp_remote_retrieve_response_code( $http_response );
		$raw_body  = wp_remote_retrieve_body( $http_response );
		$data      = json_decode( $raw_body, true );
		$content   = trim( $data['choices'][0]['message']['content'] ?? '' );

		error_log( "[Align AI Translator] Batch response in {$dt}s | HTTP {$http_code} | items: {$count}" );

		$cleaned = preg_replace( '/^```(?:json)?\s*/i', '', $content );
		$cleaned = preg_replace( '/\s*```$/', '', $cleaned );
		$parsed  = json_decode( trim( $cleaned ), true );

		if ( is_array( $parsed ) ) {
			$result = array();
			for ( $i = 0; $i < $count; $i++ ) {
				$k = 'k' . $i;
				if ( isset( $parsed[ $k ] ) && is_string( $parsed[ $k ] ) ) {
					$result[] = $parsed[ $k ];
				} elseif ( isset( $parsed[ $i ] ) && is_string( $parsed[ $i ] ) ) {
					$result[] = $parsed[ $i ];
				} else {
					$result[] = $texts[ $i ];
				}
			}
			return $result;
		}

		error_log( '[Align AI Translator] Failed to parse JSON translation. Raw: ' . substr( $content, 0, 300 ) );

		// Auto-split fallback: If batch failed and has multiple items, divide and retry each half
		if ( $count > 1 ) {
			$half = (int) ceil( $count / 2 );
			error_log( "[Align AI Translator] Auto-splitting failed batch of {$count} items into 2 sub-batches ({$half} and " . ( $count - $half ) . ')' );
			$sub1 = self::translate_batch( array_slice( $texts, 0, $half ), $target_lang, $source_lang, $custom_instructions );
			$sub2 = self::translate_batch( array_slice( $texts, $half ), $target_lang, $source_lang, $custom_instructions );
			return array_merge( $sub1, $sub2 );
		}

		return $texts;
	}
}
