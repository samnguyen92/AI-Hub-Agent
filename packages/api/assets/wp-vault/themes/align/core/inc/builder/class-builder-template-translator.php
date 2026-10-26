<?php
/**
 * Align Theme - Builder Template & Template Part Translator
 *
 * Handles copying layout & content from English master templates and template parts
 * (header, footer, single, index, 404, etc.) to translated variants (-zh, -vi),
 * syncing both database posts (wp_template / wp_template_part) and disk HTML files.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Template_Translator {

	/**
	 * Resolves template parameters from tp_id string.
	 *
	 * @param string $tp_id         Template part or template ID.
	 * @param string $template_type Optional template type ('template' or 'template_part').
	 * @return array Normalized metadata [ 'slug', 'is_template', 'type', 'sub_dir' ].
	 */
	public static function parse_tp_id( $tp_id, $template_type = '' ) {
		$raw_id      = preg_replace( '/^t(p|pl)_/', '', (string) $tp_id );
		$is_template = ( 'template' === $template_type || 0 === strpos( (string) $tp_id, 'tpl_' ) );
		$slug        = sanitize_file_name( ( strpos( $raw_id, '//' ) !== false ) ? explode( '//', $raw_id )[1] : $raw_id );

		if ( ! $is_template ) {
			$stylesheet_tpl = get_stylesheet_directory() . '/templates/' . $slug . '.html';
			$template_tpl   = get_template_directory() . '/templates/' . $slug . '.html';
			if ( file_exists( $stylesheet_tpl ) || file_exists( $template_tpl ) ) {
				$is_template = true;
			}
		}

		$type    = $is_template ? 'wp_template' : 'wp_template_part';
		$sub_dir = $is_template ? 'templates' : 'parts';

		return array(
			'raw_id'      => $raw_id,
			'slug'        => $slug,
			'is_template' => $is_template,
			'type'        => $type,
			'sub_dir'     => $sub_dir,
		);
	}

	/**
	 * Resolves source English slug by stripping language suffix (-zh, -vi, etc.).
	 *
	 * @param string $slug Target slug.
	 * @return string Source slug.
	 */
	public static function resolve_source_slug( $slug ) {
		return preg_replace( '/-([a-z]{2,5})$/i', '', (string) $slug );
	}

	/**
	 * Copies layout and content from original English master template/part to target variant.
	 *
	 * @param string $tp_id         Template or template part ID (e.g. 'align-child//footer-zh').
	 * @param string $template_type Optional explicit template type.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function copy_original_template( $tp_id, $template_type = '' ) {
		$meta        = self::parse_tp_id( $tp_id, $template_type );
		$slug        = $meta['slug'];
		$is_template = $meta['is_template'];
		$type        = $meta['type'];
		$sub_dir     = $meta['sub_dir'];

		$source_slug = self::resolve_source_slug( $slug );
		if ( $source_slug === $slug ) {
			return new WP_Error(
				'already_source',
				sprintf( __( 'Target "%s" is already the original master template.', 'align' ), $slug ),
				array( 'status' => 400 )
			);
		}

		// 1. Locate original source content (check DB first, fallback to disk file)
		$source_id      = class_exists( 'Align_Reusable_Block' ) ? Align_Reusable_Block::resolve_template_post_id( $source_slug, $type ) : 0;
		$source_post    = $source_id ? get_post( $source_id ) : null;
		$source_content = ( $source_post && ! empty( trim( $source_post->post_content ) ) ) ? $source_post->post_content : '';

		if ( empty( $source_content ) ) {
			$child_file  = get_stylesheet_directory() . '/' . $sub_dir . '/' . $source_slug . '.html';
			$parent_file = get_template_directory() . '/' . $sub_dir . '/' . $source_slug . '.html';
			if ( file_exists( $child_file ) && is_readable( $child_file ) ) {
				$source_content = file_get_contents( $child_file );
			} elseif ( file_exists( $parent_file ) && is_readable( $parent_file ) ) {
				$source_content = file_get_contents( $parent_file );
			}
		}

		if ( empty( $source_content ) ) {
			return new WP_Error(
				'source_not_found',
				sprintf( __( 'Original English template "%s" could not be found in database or theme files.', 'align' ), $source_slug ),
				array( 'status' => 404 )
			);
		}

		// 2. Persist to target in database (update or insert)
		$target_id = class_exists( 'Align_Reusable_Block' ) ? Align_Reusable_Block::resolve_template_post_id( $slug, $type ) : 0;

		if ( function_exists( 'kses_remove_filters' ) ) {
			kses_remove_filters();
		}

		if ( $target_id ) {
			wp_update_post( array(
				'ID'           => $target_id,
				'post_content' => wp_slash( $source_content ),
			) );
		} else {
			$target_id = wp_insert_post( array(
				'post_name'    => $slug,
				'post_title'   => ucfirst( str_replace( '-', ' ', $slug ) ),
				'post_content' => wp_slash( $source_content ),
				'post_type'    => $type,
				'post_status'  => 'publish',
			) );
			if ( $target_id && ! is_wp_error( $target_id ) ) {
				wp_set_object_terms( $target_id, get_stylesheet(), 'wp_theme' );
				if ( 'wp_template_part' === $type ) {
					$area = str_starts_with( $slug, 'header' ) ? 'header' : ( str_starts_with( $slug, 'footer' ) ? 'footer' : ( in_array( $slug, array( 'sidebar', 'comments' ), true ) ? $slug : 'uncategorized' ) );
					wp_set_object_terms( $target_id, $area, 'wp_template_part_area' );
				}
			}
		}

		// Copy reusable overrides meta if available
		if ( $source_id && $target_id && ! is_wp_error( $target_id ) ) {
			$src_overrides = get_post_meta( $source_id, '_align_reusable_overrides', true );
			if ( ! empty( $src_overrides ) ) {
				update_post_meta( $target_id, '_align_reusable_overrides', $src_overrides );
			}
		}

		if ( function_exists( 'kses_init_filters' ) ) {
			kses_init_filters();
		}

		// 3. Write synchronized HTML content directly to child theme disk file
		$target_dir = get_stylesheet_directory() . '/' . $sub_dir;
		if ( ! is_dir( $target_dir ) && function_exists( 'wp_mkdir_p' ) ) {
			wp_mkdir_p( $target_dir );
		}
		$target_file = $target_dir . '/' . $slug . '.html';
		if ( is_dir( $target_dir ) ) {
			file_put_contents( $target_file, $source_content );
		}

		// 4. Copy scoped external assets (CSS/JS) if present
		$prefix        = $is_template ? 'template' : 'part';
		$src_style_id  = 'style-' . $prefix . '-' . $source_slug;
		$src_script_id = 'script-' . $prefix . '-' . $source_slug;
		$tgt_style_id  = 'style-' . $prefix . '-' . $slug;
		$tgt_script_id = 'script-' . $prefix . '-' . $slug;

		if ( class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'get_builder_assets_dir' ) ) {
			$assets_dir = Align_Builder_Assets::get_builder_assets_dir();
			$css_code   = ( file_exists( $assets_dir . $src_style_id . '.css' ) && is_readable( $assets_dir . $src_style_id . '.css' ) ) ? file_get_contents( $assets_dir . $src_style_id . '.css' ) : '';
			$js_code    = ( file_exists( $assets_dir . $src_script_id . '.js' ) && is_readable( $assets_dir . $src_script_id . '.js' ) ) ? file_get_contents( $assets_dir . $src_script_id . '.js' ) : '';
			if ( '' !== $css_code || '' !== $js_code ) {
				Align_Builder_Assets::save_external_assets( $tgt_style_id, $tgt_script_id, $css_code, $js_code );
			}
		}

		// 5. Clean caches and log
		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}
		if ( $target_id && ! is_wp_error( $target_id ) ) {
			clean_post_cache( $target_id );
		}

		error_log( sprintf( '[Align AI Translator] Copied original template content from "%s" to "%s" (Target ID: %d, Length: %d)', $source_slug, $slug, (int) $target_id, strlen( $source_content ) ) );

		return rest_ensure_response( array(
			'success'      => true,
			'tp_id'        => $tp_id,
			'target_slug'  => $slug,
			'source_slug'  => $source_slug,
			'post_content' => $source_content,
			'message'      => sprintf( __( 'Successfully copied content from "%s" to "%s".', 'align' ), $source_slug, $slug ),
		) );
	}

	/**
	 * Translates template or template part content with AI.
	 *
	 * @param string $tp_id               Template or template part ID.
	 * @param string $target_lang         Target language code (e.g. 'zh').
	 * @param string $custom_instructions Optional instructions.
	 * @param string $template_type       Optional template type.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function translate_template( $tp_id, $target_lang, $custom_instructions = '', $template_type = '' ) {
		$meta        = self::parse_tp_id( $tp_id, $template_type );
		$slug        = $meta['slug'];
		$is_template = $meta['is_template'];
		$type        = $meta['type'];
		$sub_dir     = $meta['sub_dir'];

		if ( empty( $target_lang ) ) {
			$m           = null;
			$target_lang = preg_match( '/-([a-z]{2,5})$/i', $slug, $m ) ? strtolower( $m[1] ) : 'zh';
		}

		$target_id   = class_exists( 'Align_Reusable_Block' ) ? Align_Reusable_Block::resolve_template_post_id( $slug, $type ) : 0;
		$target_post = $target_id ? get_post( $target_id ) : null;
		$raw_content = ( $target_post && ! empty( trim( $target_post->post_content ) ) ) ? $target_post->post_content : '';

		if ( empty( $raw_content ) ) {
			$disk_file = get_stylesheet_directory() . '/' . $sub_dir . '/' . $slug . '.html';
			if ( file_exists( $disk_file ) && is_readable( $disk_file ) ) {
				$raw_content = file_get_contents( $disk_file );
			}
		}

		// If target content is still empty, copy from original source first
		if ( empty( $raw_content ) ) {
			$copy_res = self::copy_original_template( $tp_id, $template_type );
			if ( is_wp_error( $copy_res ) ) {
				return $copy_res;
			}
			$copy_data   = $copy_res->get_data();
			$raw_content = $copy_data['post_content'] ?? '';
		}

		if ( empty( $raw_content ) ) {
			return new WP_Error( 'not_found', sprintf( __( 'Template content for "%s" not found.', 'align' ), $slug ), array( 'status' => 404 ) );
		}

		$parsed       = parse_blocks( $raw_content );
		$text_targets = array();
		$slots        = array();
		Align_Builder_Block_Translator::collect_translatable_slots( $parsed, $text_targets, $slots );

		if ( empty( $text_targets ) ) {
			return rest_ensure_response( array(
				'success' => true,
				'tp_id'   => $tp_id,
				'message' => 'No translatable content found.',
			) );
		}

		$batches        = array_chunk( $text_targets, Align_Builder_Translator::BATCH_SIZE, true );
		$translated_all = array();
		foreach ( $batches as $batch ) {
			$batch_res      = Align_Builder_Translator::translate_batch( array_values( $batch ), $target_lang, 'en', $custom_instructions );
			$translated_all = array_merge( $translated_all, $batch_res );
		}

		Align_Builder_Block_Translator::apply_translated_slots( $slots, $translated_all, $text_targets );
		if ( class_exists( 'Align_Language_Url_Localizer' ) ) {
			Align_Language_Url_Localizer::localize_blocks( $parsed, $target_lang );
		}

		$new_html = serialize_blocks( $parsed );
		if ( class_exists( 'Align_Language_Url_Localizer' ) ) {
			$new_html = Align_Language_Url_Localizer::localize_html( $new_html, $target_lang );
		}

		if ( function_exists( 'kses_remove_filters' ) ) {
			kses_remove_filters();
		}
		if ( $target_id ) {
			wp_update_post( array(
				'ID'           => $target_id,
				'post_content' => wp_slash( $new_html ),
			) );
		}
		if ( function_exists( 'kses_init_filters' ) ) {
			kses_init_filters();
		}

		$target_file = get_stylesheet_directory() . '/' . $sub_dir . '/' . $slug . '.html';
		if ( is_dir( dirname( $target_file ) ) ) {
			file_put_contents( $target_file, $new_html );
		}

		if ( function_exists( 'wp_clean_theme_json_cache' ) ) {
			wp_clean_theme_json_cache();
		}
		if ( $target_id ) {
			clean_post_cache( $target_id );
		}

		error_log( sprintf( '[Align AI Translator] Translated template "%s" to "%s" (Target ID: %d, Length: %d)', $slug, $target_lang, (int) $target_id, strlen( $new_html ) ) );

		return rest_ensure_response( array(
			'success'      => true,
			'tp_id'        => $tp_id,
			'target_lang'  => $target_lang,
			'post_content' => $new_html,
			'message'      => sprintf( __( 'Successfully translated template "%s" to %s.', 'align' ), $slug, strtoupper( $target_lang ) ),
		) );
	}
}
