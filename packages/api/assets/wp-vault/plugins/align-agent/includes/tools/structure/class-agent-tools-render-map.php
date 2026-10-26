<?php
/**
 * Align Agent - Page Render Map & Source Finder Tools
 *
 * Registers read-only tools inspect_render and find_in_render (REQ-16, REQ-17).
 *
 * @package Align_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Render_Map {

	/** Initialize and register render map tools. */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'inspect_render', array(
			'type'             => 'read',
			'group'            => 'structure',
			'scope'            => 'local',
			'capability'       => 'edit_theme_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Return the complete 10-key render map for a URL or post_id: page info, language & missing translations, active template, active_parts (header/footer), unused_parts (marked legacy), reuse_blocks, global_code, code_slots, open draft clone, and Global Vault backup counts.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'url'     => array( 'type' => 'string', 'description' => 'Relative path (e.g. "/vi/", "/about/") or full page URL.' ),
					'post_id' => array( 'type' => 'integer', 'description' => 'Numeric WordPress post/page ID.' ),
				),
			),
			'callback'         => array( __CLASS__, 'tool_inspect_render' ),
		) );

		Align_Agent_Tool_Registry::register( 'find_in_render', array(
			'type'             => 'read',
			'group'            => 'structure',
			'scope'            => 'local',
			'capability'       => 'edit_theme_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Locate up to 5 exact occurrences of a CSS selector, class/ID token, or text snippet across all active render sources of a page (page:<id>, tp:<slug>, block:<id>, option:align_custom_js, option:align_custom_css), including enclosing JavaScript function names and bodies.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'url'      => array( 'type' => 'string', 'description' => 'Relative path or full URL of the page.' ),
					'post_id'  => array( 'type' => 'integer', 'description' => 'Optional numeric post/page ID.' ),
					'selector' => array( 'type' => 'string', 'description' => 'CSS selector, class, or ID to locate (e.g. ".lang-switcher", "#header-nav").' ),
					'text'     => array( 'type' => 'string', 'description' => 'Visible text, HTML attribute, or JS identifier to search for.' ),
				),
			),
			'callback'         => array( __CLASS__, 'tool_find_in_render' ),
		) );
	}

	/** Resolve a URL path or post_id into a WP_Post and language context. */
	private static function resolve_page( array $args ): array {
		$post_id  = absint( $args['post_id'] ?? ( $args['id'] ?? 0 ) );
		$url      = trim( (string) ( $args['url'] ?? '' ) );
		$req_lang = '';

		if ( $post_id <= 0 && '' !== $url ) {
			$parsed_path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$segments    = array_values( array_filter( explode( '/', trim( $parsed_path, '/' ) ) ) );

			if ( class_exists( 'Align_Language_Manager' ) ) {
				$langs = (array) Align_Language_Manager::get_languages();
				if ( ! empty( $segments ) ) {
					$first = sanitize_key( $segments[0] );
					foreach ( $langs as $k => $l ) {
						$code = is_array( $l ) && ! empty( $l['code'] ) ? (string) $l['code'] : (string) $k;
						$slug = is_array( $l ) && ! empty( $l['slug'] ) ? (string) $l['slug'] : $code;
						if ( $first === $code || $first === $slug ) {
							$req_lang = $code;
							array_shift( $segments );
							break;
						}
					}
				}

				if ( empty( $segments ) && '' !== $req_lang ) {
					$lang_cfg = $langs[ $req_lang ] ?? array();
					if ( ! empty( $lang_cfg['root_page_id'] ) ) {
						$post_id = absint( $lang_cfg['root_page_id'] );
					} elseif ( ( $front_id = absint( get_option( 'page_on_front', 0 ) ) ) > 0 ) {
						$trs = (array) Align_Language_Manager::get_translations( $front_id );
						foreach ( $trs as $k => $tr ) {
							if ( is_array( $tr ) ) {
								$tr_lang = (string) ( $tr['lang'] ?? $k );
								if ( $tr_lang === $req_lang ) {
									$post_id = absint( $tr['post_id'] ?? ( $tr['id'] ?? 0 ) );
									break;
								}
							} elseif ( (string) $k === $req_lang ) {
								$post_id = absint( $tr );
								break;
							}
						}
						$post_id = $post_id > 0 ? $post_id : $front_id;
					}
				}
			}

			if ( $post_id <= 0 && ! empty( $segments ) ) {
				$by_path = get_page_by_path( implode( '/', $segments ), OBJECT, array( 'page', 'post' ) );
				if ( $by_path instanceof WP_Post ) {
					$post_id = (int) $by_path->ID;
				} else {
					$leaf  = sanitize_title( (string) end( $segments ) );
					$found = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => array( 'publish', 'draft' ), 'name' => $leaf, 'posts_per_page' => 1 ) );
					if ( ! empty( $found ) ) {
						$post_id = (int) $found[0]->ID;
					}
				}
			}

			if ( $post_id <= 0 && function_exists( 'url_to_postid' ) ) {
				$post_id = absint( url_to_postid( ( 0 === strpos( $url, 'http' ) ) ? $url : home_url( $url ) ) );
			}
		}

		$post_id = $post_id > 0 ? $post_id : absint( get_option( 'page_on_front', 0 ) );
		return array( 'post' => $post_id > 0 ? get_post( $post_id ) : null, 'req_lang' => $req_lang );
	}

	/** Callback for inspect_render (REQ-16). */
	public static function tool_inspect_render( array $args = array() ): array {
		$resolved = self::resolve_page( $args );
		$post     = $resolved['post'];
		$post_id  = $post instanceof WP_Post ? (int) $post->ID : 0;

		$lock_mode = ( $post_id > 0 && class_exists( 'Align_Editor_Mode_Lock' ) && method_exists( 'Align_Editor_Mode_Lock', 'get_effective_mode' ) )
			? (string) Align_Editor_Mode_Lock::get_effective_mode( $post_id ) : 'builder';
		$page_info = array(
			'post_id'     => $post_id,
			'title'       => $post ? (string) $post->post_title : '',
			'slug'        => $post ? (string) $post->post_name : '',
			'status'      => $post ? (string) $post->post_status : 'none',
			'editor_lock' => $lock_mode,
		);

		$lang_info    = self::build_language_info( $post_id, (string) $resolved['req_lang'] );
		$tpl_map      = self::build_template_and_parts_info( $post );
		$reuse_blocks = self::collect_reuse_blocks( $post, $tpl_map['active_parts'] );

		$raw_css     = (string) get_option( 'align_custom_css', '' );
		$raw_js      = (string) get_option( 'align_custom_js', '' );
		$global_code = array(
			'align_custom_css' => array( 'active' => '' !== trim( $raw_css ), 'bytes' => strlen( $raw_css ) ),
			'align_custom_js'  => array( 'active' => '' !== trim( $raw_js ), 'bytes' => strlen( $raw_js ) ),
		);

		$slot_defs  = array( 'tracking_code' => 'align_tracking_code', 'body_open_code' => 'align_body_open_code', 'footer_html_code' => 'align_footer_html_code' );
		$code_slots = array();
		foreach ( $slot_defs as $s_key => $opt_name ) {
			$s_val = (string) get_option( $opt_name, '' );
			if ( '' !== trim( $s_val ) ) {
				$code_slots[ $s_key ] = array( 'option' => $opt_name, 'bytes' => strlen( $s_val ) );
			}
		}

		$clone_info = null;
		if ( $post_id > 0 ) {
			$clones = get_posts( array( 'post_type' => $post ? $post->post_type : 'page', 'post_status' => 'draft', 'meta_key' => '_align_agent_clone_of', 'meta_value' => $post_id, 'posts_per_page' => 1 ) );
			if ( ! empty( $clones ) && $clones[0] instanceof WP_Post ) {
				$clone_info = array( 'clone_id' => (int) $clones[0]->ID, 'title' => (string) $clones[0]->post_title, 'modified' => (string) $clones[0]->post_modified_gmt );
			}
		}

		$vault_keys = array(
			'tpl:' . $tpl_map['template']['slug'],
			'tp:' . preg_replace( '/^tp_/', '', (string) $tpl_map['active_parts']['header'] ),
			'tp:' . preg_replace( '/^tp_/', '', (string) $tpl_map['active_parts']['footer'] ),
			'option:align_custom_css',
			'option:align_custom_js',
		);
		foreach ( $reuse_blocks as $rb ) {
			$vault_keys[] = 'block:' . $rb['id'];
		}
		$vault_counts = array();
		foreach ( array_unique( $vault_keys ) as $vk ) {
			$cnt                 = get_posts( array( 'post_type' => Align_Agent_Global_Vault::CPT_BACKUP, 'post_status' => 'private', 'title' => $vk, 'posts_per_page' => 20, 'fields' => 'ids' ) );
			$vault_counts[ $vk ] = count( $cnt );
		}

		$response = array(
			'success'      => true,
			'page'         => $page_info,
			'language'     => $lang_info,
			'template'     => $tpl_map['template'],
			'active_parts' => $tpl_map['active_parts'],
			'unused_parts' => $tpl_map['unused_parts'],
			'reuse_blocks' => $reuse_blocks,
			'global_code'  => $global_code,
			'code_slots'   => $code_slots,
			'clone'        => $clone_info,
			'vault'        => $vault_counts,
		);

		if ( class_exists( 'Align_Agent_Working_Notes' ) ) {
			Align_Agent_Working_Notes::record_from_inspect( get_current_user_id(), $response );
		}

		return $response;
	}

	/** Build language and missing translation list for a page. */
	private static function build_language_info( int $post_id, string $fallback_lang ): array {
		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return array( 'lang' => $fallback_lang ?: 'en', 'translations' => array(), 'missing' => array() );
		}

		$def_lang  = (string) Align_Language_Manager::get_default_lang();
		$curr_lang = ( $post_id > 0 ? (string) Align_Language_Manager::get_post_lang( $post_id ) : '' ) ?: ( $fallback_lang ?: $def_lang );
		$raw_trs   = $post_id > 0 ? (array) Align_Language_Manager::get_translations( $post_id ) : array();
		$raw_langs = (array) Align_Language_Manager::get_languages();

		$trs_map          = array();
		$translated_codes = array( $curr_lang );
		foreach ( $raw_trs as $k => $v ) {
			if ( is_array( $v ) ) {
				$c             = (string) ( $v['lang'] ?? $k );
				$id            = absint( $v['id'] ?? ( $v['post_id'] ?? 0 ) );
				$trs_map[ $c ] = $id;
				if ( $id > 0 ) {
					$translated_codes[] = $c;
				}
			} else {
				$c             = (string) $k;
				$id            = absint( $v );
				$trs_map[ $c ] = $id;
				if ( $id > 0 ) {
					$translated_codes[] = $c;
				}
			}
		}

		$missing = array();
		foreach ( $raw_langs as $k => $l ) {
			$code = is_array( $l ) && ! empty( $l['code'] ) ? (string) $l['code'] : (string) $k;
			if ( ! in_array( $code, $translated_codes, true ) ) {
				$missing[] = $code;
			}
		}

		return array(
			'lang'         => $curr_lang,
			'translations' => $trs_map,
			'missing'      => array_values( array_unique( $missing ) ),
		);
	}

	/** Resolve active template, active_parts by area, and unused legacy parts. */
	private static function build_template_and_parts_info( ?WP_Post $post ): array {
		$tpl_slug = 'page';
		if ( $post instanceof WP_Post ) {
			$assigned = get_page_template_slug( $post->ID );
			if ( ! empty( $assigned ) ) {
				$tpl_slug = sanitize_file_name( (string) preg_replace( '/\.html$|\.php$/i', '', basename( $assigned ) ) );
			} elseif ( (int) get_option( 'page_on_front', 0 ) === (int) $post->ID ) {
				$tpl_slug = 'front-page';
			} elseif ( 'post' === $post->post_type ) {
				$tpl_slug = 'single';
			}
		}

		$tpl_raw          = '';
		$has_post_content = true;
		if ( function_exists( 'get_block_template' ) ) {
			$tpl_obj = get_block_template( get_stylesheet() . '//' . $tpl_slug, 'wp_template' ) ?: get_block_template( get_stylesheet() . '//index', 'wp_template' );
			if ( ! $tpl_obj && 'front-page' === $tpl_slug ) {
				$tpl_obj  = get_block_template( get_stylesheet() . '//page', 'wp_template' );
				$tpl_slug = $tpl_obj ? 'page' : $tpl_slug;
			}
			if ( $tpl_obj && ! empty( $tpl_obj->content ) ) {
				$tpl_raw          = (string) $tpl_obj->content;
				$has_post_content = ( false !== strpos( $tpl_raw, 'wp:post-content' ) || false !== strpos( $tpl_raw, 'post-content' ) );
			}
		}

		$referenced_slugs = array();
		if ( '' !== $tpl_raw && preg_match_all( '/<!--\s*wp:template-part\s+(\{.*?\})/s', $tpl_raw, $m ) ) {
			foreach ( $m[1] as $json_str ) {
				$attr = json_decode( $json_str, true );
				if ( is_array( $attr ) && ! empty( $attr['slug'] ) ) {
					$referenced_slugs[] = (string) $attr['slug'];
				}
			}
		}

		$list_res    = class_exists( 'Align_Agent_Tools_Templates' ) ? Align_Agent_Tools_Templates::tool_list_templates( array( 'type' => 'wp_template_part' ) ) : array();
		$parts       = is_array( $list_res['templates'] ?? null ) ? $list_res['templates'] : array();
		$header_tp   = 'tp_header';
		$footer_tp   = 'tp_footer';
		$unused_list = array();

		foreach ( $parts as $p ) {
			$slug         = (string) ( $p['slug'] ?? '' );
			$area         = (string) ( $p['area'] ?? 'uncategorized' );
			$canonical_tp = 'tp_' . $slug;
			$is_ref       = in_array( $slug, $referenced_slugs, true );

			if ( 'header' === $area && 'header' === $slug ) {
				$header_tp = 'tp_header';
			} elseif ( 'footer' === $area && 'footer' === $slug ) {
				$footer_tp = 'tp_footer';
			} elseif ( 'uncategorized' === $area || 'general' === $area || ( ! $is_ref && ! in_array( $slug, array( 'header', 'footer' ), true ) ) ) {
				$unused_list[] = array(
					'slug'   => $slug,
					'tp_id'  => $canonical_tp,
					'area'   => $area,
					'legacy' => true,
				);
			}
		}

		return array(
			'template'     => array( 'tp_id' => 'tpl_' . $tpl_slug, 'slug' => $tpl_slug, 'has_post_content' => $has_post_content ),
			'active_parts' => array( 'header' => $header_tp, 'footer' => $footer_tp ),
			'unused_parts' => $unused_list,
		);
	}

	/** Scan page and active template parts for embedded [reuse-block id="N"] or wp:block. */
	private static function collect_reuse_blocks( ?WP_Post $post, array $active_parts ): array {
		$contents = array();
		if ( $post instanceof WP_Post ) {
			$contents[] = (string) $post->post_content;
		}
		if ( class_exists( 'Align_Agent_Tools_Templates' ) ) {
			foreach ( $active_parts as $tp_id ) {
				$info = Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => (string) $tp_id ) );
				if ( ! empty( $info['html'] ) ) {
					$contents[] = (string) $info['html'];
				}
			}
		}

		$ids     = array();
		$blob    = implode( "\n", $contents );
		if ( preg_match_all( '/\[reuse[-_]block\s+id=["\']?(\d+)["\']?\]/i', $blob, $m1 ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $m1[1] ) );
		}
		if ( preg_match_all( '/wp:block\s+\{"ref":(\d+)/', $blob, $m2 ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $m2[1] ) );
		}

		$blocks = array();
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $bid ) {
			$b_post   = get_post( $bid );
			$blocks[] = array(
				'id'        => $bid,
				'title'     => $b_post ? (string) $b_post->post_title : '#' . $bid,
				'shortcode' => sprintf( '[reuse-block id="%d"]', $bid ),
			);
		}
		return $blocks;
	}

	/**
	 * Callback for find_in_render (REQ-17).
	 */
	public static function tool_find_in_render( array $args = array() ): array {
		$selector = trim( (string) ( $args['selector'] ?? '' ) );
		$text     = trim( (string) ( $args['text'] ?? '' ) );
		$query    = '' !== $selector ? $selector : $text;

		if ( '' === $query ) {
			return array( 'success' => false, 'error' => 'missing_query', 'message' => 'Provide either selector or text to search.' );
		}

		$bare_token = ltrim( $query, '.#' );
		$tokens     = array_values( array_unique( array_filter( array( $query, $bare_token ) ) ) );

		$map     = self::tool_inspect_render( $args );
		$sources = array();

		// 1. Active template parts (header, footer).
		if ( class_exists( 'Align_Agent_Tools_Templates' ) ) {
			foreach ( ( $map['active_parts'] ?? array() ) as $area => $tp_id ) {
				$slug = preg_replace( '/^tp_/', '', (string) $tp_id );
				$info = Align_Agent_Tools_Templates::tool_get_template( array( 'tp_id' => (string) $tp_id, 'template_type' => 'wp_template_part' ) );
				if ( ! empty( $info['success'] ) ) {
					$sources[ 'tp:' . $slug ] = array( 'html' => (string) ( $info['html'] ?? '' ), 'css' => (string) ( $info['css'] ?? '' ), 'js' => (string) ( $info['js'] ?? '' ) );
				}
			}
		}

		// 2. Page content.
		$page_id = absint( $map['page']['post_id'] ?? 0 );
		if ( $page_id > 0 && ( $p_post = get_post( $page_id ) ) instanceof WP_Post ) {
			$parts = class_exists( 'Align_Gateway_Pages' ) ? Align_Gateway_Pages::extract_decoupled_parts( $p_post ) : array( 'html' => (string) $p_post->post_content );
			$sources[ 'page:' . $page_id ] = array(
				'html' => (string) ( $parts['html'] ?? ( $parts['body_html'] ?? $p_post->post_content ) ),
				'css'  => (string) ( $parts['css'] ?? '' ),
				'js'   => (string) ( $parts['js'] ?? '' ),
			);
		}

		// 3. Embedded reusable blocks.
		foreach ( ( $map['reuse_blocks'] ?? array() ) as $rb ) {
			$bid  = absint( $rb['id'] ?? 0 );
			$info = ( $bid > 0 && class_exists( 'Align_Agent_Tools_Templates' ) ) ? Align_Agent_Tools_Templates::tool_get_reuse_block( array( 'post_id' => $bid ) ) : array();
			if ( ! empty( $info['success'] ) ) {
				$sources[ 'block:' . $bid ] = array( 'html' => (string) ( $info['html'] ?? '' ), 'css' => (string) ( $info['css'] ?? '' ), 'js' => (string) ( $info['js'] ?? '' ) );
			}
		}

		// 4. Sitewide custom JS and CSS options.
		$sources['option:align_custom_js']  = array( 'js' => (string) get_option( 'align_custom_js', '' ) );
		$sources['option:align_custom_css'] = array( 'css' => (string) get_option( 'align_custom_css', '' ) );

		$results = array();
		foreach ( $sources as $src_key => $fields ) {
			foreach ( $fields as $field => $content ) {
				if ( '' === trim( $content ) ) {
					continue;
				}
				$match = self::match_in_field( $content, $field, $tokens );
				if ( null !== $match ) {
					$results[] = array(
						'source'      => $src_key,
						'field'       => $field,
						'line'        => $match['line'],
						'js_function' => $match['js_function'],
						'snippet'     => $match['snippet'],
					);
					if ( count( $results ) >= 5 ) {
						break 2;
					}
				}
			}
		}

		return array(
			'success' => true,
			'query'   => $query,
			'total'   => count( $results ),
			'matches' => $results,
		);
	}

	/** Locate token in field content and extract surrounding snippet + enclosing JS function. */
	private static function match_in_field( string $content, string $field, array $tokens ): ?array {
		$pos = false;
		foreach ( $tokens as $tk ) {
			$pos = stripos( $content, $tk );
			if ( false !== $pos ) {
				break;
			}
		}
		if ( false === $pos ) {
			return null;
		}

		$line    = substr_count( substr( $content, 0, $pos ), "\n" ) + 1;
		$js_func = '';

		if ( 'js' === $field ) {
			$before_str = substr( $content, 0, $pos );
			if ( preg_match_all( '/(?:function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(|(?:const|let|var)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*(?:async\s*)?(?:function|\([^)]*\)\s*=>)|([a-zA-Z_$][a-zA-Z0-9_$]*)\s*=\s*function\s*\()/', $before_str, $fm, PREG_OFFSET_CAPTURE ) ) {
				$last_idx  = count( $fm[0] ) - 1;
				$func_name = $fm[1][ $last_idx ][0] ?: ( $fm[2][ $last_idx ][0] ?: $fm[3][ $last_idx ][0] );
				$func_pos  = (int) $fm[0][ $last_idx ][1];
				$js_func   = (string) $func_name;
				$snippet   = substr( $content, $func_pos, 1500 );
				return array( 'line' => $line, 'js_function' => $js_func, 'snippet' => trim( $snippet ) );
			}
		}

		$start   = max( 0, $pos - 250 );
		$snippet = substr( $content, $start, 1500 );
		return array( 'line' => $line, 'js_function' => $js_func, 'snippet' => trim( $snippet ) );
	}
}
