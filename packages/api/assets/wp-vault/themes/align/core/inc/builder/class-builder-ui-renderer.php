<?php
/**
 * Align Theme Core Engine - Builder UI Renderer & Partials Assembler
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Ui_Renderer {

	/**
	 * Assemble HTML UI from modular partials or fallback to monolithic editor.html.
	 *
	 * @return string Assembled HTML content.
	 */
	public static function assemble_builder_html() {
		$partials_dir = ALIGN_BUILDER_PATH . 'partials/';
		$partial_files = array(
			'head.html',
			'sidebar-nav.html',
			'top-panel.html',
			'left-panel.html',
			'canvas.html',
			'right-panel.html',
			'bottom-panel.html',
			'tmpl-inputs.html',
			'tmpl-filemanager.html',
			'modals.html',
			'scripts.html',
		);

		$all_exist = true;
		foreach ( $partial_files as $file ) {
			if ( ! file_exists( $partials_dir . $file ) ) {
				$all_exist = false;
				break;
			}
		}

		if ( $all_exist ) {
			$html = '<!DOCTYPE html><html lang="en" data-bs-theme="dark">';
			$html .= file_get_contents( $partials_dir . 'head.html' );
			$html .= '<body style="overflow:hidden"><div id="container" class="small-nav">';
			$html .= file_get_contents( $partials_dir . 'sidebar-nav.html' );
			$html .= '<div class="main"><div id="vvveb-builder">';
			$html .= file_get_contents( $partials_dir . 'top-panel.html' );
			$html .= '<div id="left-panel">';
			$html .= file_get_contents( $partials_dir . 'left-panel.html' );
			$html .= '</div>';
			$html .= file_get_contents( $partials_dir . 'canvas.html' );
			$html .= '<div id="right-panel">';
			$html .= file_get_contents( $partials_dir . 'right-panel.html' );
			$html .= '</div>';
			$html .= file_get_contents( $partials_dir . 'bottom-panel.html' );
			$html .= '</div></div></div>';
			
			// Load modular input templates if directory exists, else fallback to tmpl-inputs.html
			if ( is_dir( $partials_dir . 'inputs' ) ) {
				$input_partials = glob( $partials_dir . 'inputs/*.html' );
				if ( ! empty( $input_partials ) ) {
					foreach ( $input_partials as $input_file ) {
						$html .= file_get_contents( $input_file );
					}
				} else {
					$html .= file_get_contents( $partials_dir . 'tmpl-inputs.html' );
				}
			} else {
				$html .= file_get_contents( $partials_dir . 'tmpl-inputs.html' );
			}

			$html .= file_get_contents( $partials_dir . 'tmpl-filemanager.html' );
			$html .= file_get_contents( $partials_dir . 'modals.html' );
			if ( file_exists( $partials_dir . 'modal-code-editor.html' ) ) {
				$html .= file_get_contents( $partials_dir . 'modal-code-editor.html' );
			}
			if ( file_exists( $partials_dir . 'modal-revisions.html' ) ) {
				$html .= file_get_contents( $partials_dir . 'modal-revisions.html' );
			}

			// Localize searchable taxonomies and post types for Align Builder components
			$taxonomies = function_exists( 'align_get_all_searchable_taxonomies' ) ? align_get_all_searchable_taxonomies() : array();
			$post_types = function_exists( 'align_get_all_searchable_post_types' ) ? align_get_all_searchable_post_types() : array();
			$html .= '<script>window.alignBlockTaxonomies = ' . wp_json_encode( $taxonomies ) . '; window.alignBlockPostTypes = ' . wp_json_encode( $post_types ) . ';</script>';

			$html .= file_get_contents( $partials_dir . 'scripts.html' );
			$html .= '</body></html>';

			return self::bust_builder_assets_cache( $html );
		}

		// Fallback to standalone editor.html
		if ( file_exists( ALIGN_BUILDER_PATH . 'editor.html' ) ) {
			$raw_editor = file_get_contents( ALIGN_BUILDER_PATH . 'editor.html' );
			return self::bust_builder_assets_cache( $raw_editor );
		}

		return '<html><body><p>Error: Align Builder UI template files not found.</p></body></html>';
	}

	/**
	 * Append dynamic timestamp cache-buster to all local script and stylesheet tags.
	 *
	 * @param string $html Input HTML content.
	 * @return string Processed HTML.
	 */
	public static function bust_builder_assets_cache( $html ) {
		$now = time();

		// 1. Script tags with src
		$html = preg_replace_callback( '/<script\b([^>]*\bsrc=)(["\'])([^"\'>]+?\.js)(\?[^"\']*)?\2([^>]*)>/i', function( $m ) use ( $now ) {
			return '<script' . $m[1] . $m[2] . $m[3] . '?v=' . $now . $m[2] . $m[5] . '>';
		}, $html );

		// 2. Stylesheet links (rel="stylesheet" after href)
		$html = preg_replace_callback( '/<link\b([^>]*\bhref=)(["\'])([^"\'>]+?\.css)(\?[^"\']*)?\2([^>]*rel=["\']stylesheet["\'][^>]*)>/i', function( $m ) use ( $now ) {
			return '<link' . $m[1] . $m[2] . $m[3] . '?v=' . $now . $m[2] . $m[5] . '>';
		}, $html );

		// 3. Stylesheet links (rel="stylesheet" before href)
		$html = preg_replace_callback( '/<link\b([^>]*rel=["\']stylesheet["\'][^>]*\bhref=)(["\'])([^"\'>]+?\.css)(\?[^"\']*)?\2([^>]*)>/i', function( $m ) use ( $now ) {
			return '<link' . $m[1] . $m[2] . $m[3] . '?v=' . $now . $m[2] . $m[5] . '>';
		}, $html );

		return $html;
	}

	/**
	 * Render Standalone Builder UI page with full WordPress environment and scripts.
	 */
	public static function render_builder_ui() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access the builder.', 'align' ), 403 );
		}

		// Prevent any caching on Builder UI response
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Pragma: no-cache' );
			header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
		}

		$active_post_id   = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : ( isset( $_GET['postId'] ) && is_numeric( $_GET['postId'] ) ? intval( $_GET['postId'] ) : 0 );
		$active_tp_id     = isset( $_GET['tp_id'] ) ? sanitize_text_field( $_GET['tp_id'] ) : ( isset( $_GET['pId'] ) ? sanitize_text_field( $_GET['pId'] ) : ( isset( $_GET['postId'] ) && ! is_numeric( $_GET['postId'] ) ? sanitize_text_field( $_GET['postId'] ) : '' ) );
		$active_post_type = $active_post_id ? ( get_post_type( $active_post_id ) ?: '' ) : '';
		$active_post_slug = '';
		if ( $active_post_id ) {
			$post_obj = get_post( $active_post_id );
			if ( $post_obj && ! empty( $post_obj->post_name ) ) {
				$active_post_slug = $post_obj->post_name;
			}
		}

		$is_dynamic_block = false;
		if ( $active_post_id ) {
			$is_dynamic_block = (bool) get_post_meta( $active_post_id, '_align_is_dynamic_block', true );
		}
		if ( ! empty( $_GET['dynamic_block'] ) || ( isset( $_GET['page'] ) && 'align-dynamic-studio' === $_GET['page'] ) ) {
			$is_dynamic_block = true;
		}

		$plugin_url     = ALIGN_BUILDER_URL;
		$rest_nonce     = wp_create_nonce( 'wp_rest' );
		$rest_save_url  = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/save-page' ) ) );
		$rest_pages_url = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/pages' ) ) );
		$rest_shortcode = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/render-shortcode' ) ) );
		$rest_block     = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/render-block' ) ) );
		$rest_create       = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/create-item' ) ) );
		$rest_rename       = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/rename-item' ) ) );
		$rest_delete       = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/delete-item' ) ) );
		$rest_search_links = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/search-links' ) ) );
		$rest_attachment_sizes = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/attachment-sizes' ) ) );
		$rest_reuse_fields = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( 'align-reuse/v1/fields' ) ) );
		$rest_revisions    = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/revisions' ) ) );
		$rest_restore_rev  = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/restore-revision' ) ) );
		$rest_page_content = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/page-content' ) ) );
		$rest_custom_templates = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/custom-templates' ) ) );
		$rest_export_page      = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/export-page' ) ) );
		$rest_import_page      = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/import-page' ) ) );

		$theme_css_urls  = Align_Builder_Assets::get_builder_css_urls();
		$custom_css      = get_option( 'align_custom_css', '' );
		$site_icon_url   = get_site_icon_url( 64 );
		$site_title      = get_bloginfo( 'name' ) ?: 'Align Theme';

		// Language Manager config
		$align_languages    = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
		$align_default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
		$align_active_lang  = sanitize_key( isset( $_GET['lang'] ) ? $_GET['lang'] : ( $active_post_id && class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( $active_post_id ) : '' ) );
		$align_translations = ( $active_post_id && class_exists( 'Align_Language_Manager' ) ) ? Align_Language_Manager::get_translations( $active_post_id ) : array();

		// Detect linked English source post for active post
		$align_source_id    = 0;
		$align_source_title = '';
		if ( $active_post_id && class_exists( 'Align_Language_Manager' ) ) {
			$align_source_id = intval( get_post_meta( $active_post_id, Align_Language_Manager::META_PARENT, true ) );
			if ( ! $align_source_id ) {
				foreach ( $align_translations as $tr ) {
					if ( 'en' === $tr['lang'] && intval( $tr['post_id'] ) !== $active_post_id ) {
						$align_source_id = intval( $tr['post_id'] );
						break;
					}
				}
			}
			if ( $align_source_id ) {
				$src_post = get_post( $align_source_id );
				if ( $src_post ) {
					$align_source_title = $src_post->post_title;
				}
			}
		}

		// SEO & Translator REST URLs
		$rest_seo_meta          = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/seo-meta' ) ) );
		$rest_seo_generate      = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/seo-generate' ) ) );
		$rest_create_trans      = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/create-translation' ) ) );
		$rest_languages         = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/languages' ) ) );
		$rest_translate_content = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/translate-content' ) ) );
		$rest_localize_urls     = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/localize-urls' ) ) );
		$rest_translate_post    = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/translate-post' ) ) );
		$rest_copy_orig         = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/copy-original-post' ) ) );
		$rest_copy_orig_sec     = esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/copy-original-section' ) ) );

		$theme_screenshot_url = file_exists( get_stylesheet_directory() . '/screenshot.png' ) ? get_stylesheet_directory_uri() . '/screenshot.png' :
							   ( file_exists( get_stylesheet_directory() . '/screenshot.jpeg' ) ? get_stylesheet_directory_uri() . '/screenshot.jpeg' :
							   ( file_exists( get_template_directory() . '/screenshot.png' ) ? get_template_directory_uri() . '/screenshot.png' :
							   ( file_exists( get_template_directory() . '/screenshot.jpeg' ) ? get_template_directory_uri() . '/screenshot.jpeg' : '' ) ) );

		wp_enqueue_media();

		ob_start();
		wp_print_styles();
		$wp_styles = ob_get_clean();

		ob_start();
		wp_print_scripts();
		wp_print_media_templates();
		$wp_scripts = ob_get_clean();

		$is_embedded = ! empty( $_GET['embedded'] ) || ( isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) && 'iframe' === $_SERVER['HTTP_SEC_FETCH_DEST'] );
		if ( $is_embedded ) {
			error_log( '[Align Builder] Rendering Builder UI in Gutenberg Embedded Mode for post ID: ' . $active_post_id );
		}

		$editor_html = self::assemble_builder_html();
		$editor_html = str_replace( '<base href="">', '<base href="' . esc_url( ALIGN_BUILDER_URL ) . '">', $editor_html );

		if ( $is_embedded ) {
			if ( strpos( $editor_html, 'class="is-embedded"' ) === false ) {
				$editor_html = str_replace( '<html lang="en"', '<html lang="en" class="is-embedded"', $editor_html );
				$editor_html = str_replace( '<body style="overflow:hidden">', '<body style="overflow:hidden" class="is-embedded">', $editor_html );
			}
		}

		// Inject dynamic WordPress site icon (favicon) tags if configured on the current website.
		if ( function_exists( 'has_site_icon' ) && has_site_icon() ) {
			ob_start();
			wp_site_icon();
			$site_icon_tags = trim( ob_get_clean() );
			if ( ! empty( $site_icon_tags ) ) {
				error_log( '[Align Builder] Injecting WordPress site icon favicon into Builder UI: ' . strlen( $site_icon_tags ) . ' bytes' );
				if ( strpos( $editor_html, '<link rel="icon" href="favicon.ico">' ) !== false ) {
					$editor_html = str_replace( '<link rel="icon" href="favicon.ico">', $site_icon_tags, $editor_html );
				} else {
					$editor_html = str_replace( '</head>', $site_icon_tags . "\n</head>", $editor_html );
				}
			}
		}

		if ( ! empty( $wp_styles ) ) {
			$editor_html = str_replace( '</head>', $wp_styles . '</head>', $editor_html );
		}

		$saved_reusable_overrides = array();
		if ( $active_post_id ) {
			$saved_reusable_overrides = get_post_meta( $active_post_id, '_align_reusable_overrides', true ) ?: array();
		} elseif ( ! empty( $active_tp_id ) && class_exists( 'Align_Reusable_Block' ) ) {
			$tp_post_id = Align_Reusable_Block::resolve_template_post_id( $active_tp_id, $active_post_type );
			if ( $tp_post_id ) {
				$saved_reusable_overrides = get_post_meta( $tp_post_id, '_align_reusable_overrides', true ) ?: array();
			}
		}

		$reusable_blocks_list = array();
		$wp_blocks = get_posts( array(
			'post_type'              => 'wp_block',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		if ( ! empty( $wp_blocks ) ) {
			foreach ( $wp_blocks as $b ) {
				$reusable_blocks_list[] = array(
					'id'    => (int) $b->ID,
					'slug'  => (string) $b->post_name,
					'title' => (string) ( $b->post_title ?: __( '(Untitled)', 'align' ) ),
				);
			}
		}


		$wp_config_script = '
		  ' . $wp_scripts . '
		  <script>
			window.VvvebWpConfig = {
			  builderSlug: ' . wp_json_encode( ALIGN_BUILDER_SLUG ) . ',
			  builderTitle: ' . wp_json_encode( ALIGN_BUILDER_TITLE ) . ',
			  isEmbedded: ' . wp_json_encode( $is_embedded ) . ',
			  restNamespace: ' . wp_json_encode( ALIGN_BUILDER_REST_NS ) . ',
			  activePostId: ' . wp_json_encode( $active_post_id ) . ',
			  activePostSlug: ' . wp_json_encode( $active_post_slug ) . ',
			  activePostType: ' . wp_json_encode( $active_post_type ) . ',
			  activeTpId: ' . wp_json_encode( $active_tp_id ) . ',
			  savedReusableOverrides: ' . wp_json_encode( $saved_reusable_overrides ?: new stdClass() ) . ',
			  reusableBlocks: ' . wp_json_encode( $reusable_blocks_list ) . ',

			  restSaveUrl: ' . wp_json_encode( $rest_save_url ) . ',
			  restPagesUrl: ' . wp_json_encode( $rest_pages_url ) . ',
			  restPageContentUrl: ' . wp_json_encode( $rest_page_content ) . ',
			  restRenderShortcodeUrl: ' . wp_json_encode( $rest_shortcode ) . ',
			  restRenderBlockUrl: ' . wp_json_encode( $rest_block ) . ',
			  restReuseFieldsUrl: ' . wp_json_encode( $rest_reuse_fields ) . ',
			  restAttachmentSizesUrl: ' . wp_json_encode( $rest_attachment_sizes ) . ',
			  restRevisionsUrl: ' . wp_json_encode( $rest_revisions ) . ',
			  restRestoreRevisionUrl: ' . wp_json_encode( $rest_restore_rev ) . ',
			  ajaxUrl: ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',
			  adminUrl: ' . wp_json_encode( admin_url( 'admin.php' ) ) . ',
			  adminDashboardUrl: ' . wp_json_encode( admin_url() ) . ',
			  dynamicStudioUrl: ' . wp_json_encode( admin_url( 'admin.php?page=align-studio' ) ) . ',
			  isDynamicBlock: ' . wp_json_encode( $is_dynamic_block ) . ',
			  restExportDynamicUrl: ' . wp_json_encode( esc_url_raw( add_query_arg( '_wpnonce', $rest_nonce, rest_url( ALIGN_BUILDER_REST_NS . '/export-dynamic-reusable' ) ) ) ) . ',
			  themeCssUrls: ' . wp_json_encode( $theme_css_urls ) . ',
			  customCss: ' . wp_json_encode( $custom_css ) . ',
			  siteIconUrl: ' . wp_json_encode( $site_icon_url ) . ',
			  themeScreenshotUrl: ' . wp_json_encode( $theme_screenshot_url ) . ',
			  siteTitle: ' . wp_json_encode( $site_title ) . ',
			  restNonce: ' . wp_json_encode( $rest_nonce ) . ',
			  restCreateItemUrl: ' . wp_json_encode( $rest_create ) . ',
			  restCustomTemplatesUrl: ' . wp_json_encode( $rest_custom_templates ) . ',
			  restExportPageUrl: ' . wp_json_encode( $rest_export_page ) . ',
			  restImportPageUrl: ' . wp_json_encode( $rest_import_page ) . ',
			  restRenameItemUrl: ' . wp_json_encode( $rest_rename ) . ',
			  restDeleteItemUrl: ' . wp_json_encode( $rest_delete ) . ',
			  restSearchLinksUrl: ' . wp_json_encode( $rest_search_links ) . ',
			  dummyImgUrl: ' . wp_json_encode( 'https://picsum.photos/seed/align/600/400' ) . ',
			  pluginUrl: ' . wp_json_encode( ALIGN_BUILDER_URL ) . ',
			  alignLangEnabled: ' . wp_json_encode( ! empty( $align_languages ) ) . ',
			  alignLanguages: ' . wp_json_encode( $align_languages ) . ',
			  alignDefaultLang: ' . wp_json_encode( $align_default_lang ) . ',
			  alignActiveLang: ' . wp_json_encode( $align_active_lang ) . ',
			  alignTranslations: ' . wp_json_encode( $align_translations ) . ',
			  alignSourceId: ' . wp_json_encode( $align_source_id ) . ',
			  alignSourceTitle: ' . wp_json_encode( $align_source_title ) . ',
			  rankMathActive: ' . wp_json_encode( defined( 'RANK_MATH_VERSION' ) ) . ',
			  rankMathScoreUrl: ' . wp_json_encode( defined( 'RANK_MATH_VERSION' ) ? rest_url( 'rankmath/v1/updateSeoScore' ) : '' ) . ',
			  rankMathNonce: ' . wp_json_encode( defined( 'RANK_MATH_VERSION' ) ? wp_create_nonce( 'wp_rest' ) : '' ) . ',
			  restSeoMetaUrl: ' . wp_json_encode( $rest_seo_meta ) . ',
			  restSeoGenerateUrl: ' . wp_json_encode( $rest_seo_generate ) . ',
			  restCreateTranslationUrl: ' . wp_json_encode( $rest_create_trans ) . ',
			  restLanguagesUrl: ' . wp_json_encode( $rest_languages ) . ',
			  restTranslateContentUrl: ' . wp_json_encode( $rest_translate_content ) . ',
			  restLocalizeUrlsUrl: ' . wp_json_encode( $rest_localize_urls ) . ',
			  restTranslatePostUrl: ' . wp_json_encode( $rest_translate_post ) . ',
			  restCopyOriginalPostUrl: ' . wp_json_encode( $rest_copy_orig ) . ',
			  restCopyOriginalSectionUrl: ' . wp_json_encode( $rest_copy_orig_sec ) . '
			};
			window.Vvveb = window.Vvveb || {};
			window.Vvveb.WpReusableBlocks = window.VvvebWpConfig.reusableBlocks;
		  </script>
		  <script src="libs/builder/components/wordpress/wp-base.js?v=' . time() . '"></script>
		  <script src="libs/builder/components/wordpress/wp-shortcode.js?v=' . time() . '"></script>
		  <script src="libs/builder/components/wordpress/wp-gutenberg-block.js?v=' . time() . '"></script>
		  <script src="libs/builder/components/wordpress/wp-post-content.js?v=' . time() . '"></script>
		  <script src="libs/builder/components/wordpress/wp-live-search.js?v=' . time() . '"></script>
		  <script src="libs/builder/components/wordpress/wp-language-switcher.js?v=' . time() . '"></script>

		  <script src="js/modules/wp-init.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-style-injector.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-components-preview.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-filemanager.js?v=' . time() . '"></script>
		  <script src="js/modules/align-serializer-schema.js?v=' . time() . '"></script>
		  <script src="js/modules/align-serializer.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-save-handler.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-revision-handler.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-media-modal.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-items-crud.js?v=' . time() . '"></script>
		  <script src="js/modules/reuse-block/reuse-block-detector.js?v=' . time() . '"></script>
		  <script src="js/modules/reuse-block/reuse-block-link-picker.js?v=' . time() . '"></script>
		  <script src="js/modules/reuse-block/reuse-block-inspector-live.js?v=' . time() . '"></script>
		  <script src="js/modules/reuse-block/reuse-block-inspector-api.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-reuse-block-overwrite.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-toolbar-tooltips.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-context-menu.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-dynamic-block-converter.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-canvas-translator.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-lang-switcher.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-builder-translator.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-seo-scorer.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-seo-panel.js?v=' . time() . '"></script>
		  <script src="js/modules/wp-export-import.js?v=' . time() . '"></script>

		  <script src="js/vvvebjs-wp-integration.js?v=' . time() . '"></script>
		';

		$editor_html = str_replace( '</body>', $wp_config_script . '</body>', $editor_html );

		echo $editor_html;
	}
}
