<?php
/**
 * Align Theme Core Engine - Builder Admin Menus, Links & Hooks
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Admin {

	/**
	 * Constructor to bind WordPress admin actions and filters.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_standalone_builder_page' ) );
		add_action( 'admin_menu', array( $this, 'register_builder_menu' ) );
		add_filter( 'default_content', array( $this, 'filter_default_page_content' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_site_editor_assets' ) );
		add_filter( 'theme_page_templates', array( $this, 'filter_theme_page_templates' ), 10, 4 );
		add_filter( 'default_page_template_title', array( $this, 'filter_default_page_template_title' ), 10, 2 );
	}

	/**
	 * Register available theme templates in the classic WordPress Page Template dropdown.
	 *
	 * @param array    $templates Array of template names indexed by file path/slug.
	 * @param WP_Theme $theme     Theme object.
	 * @param WP_Post  $post      Post object.
	 * @param string   $post_type Post type.
	 * @return array Modified templates list.
	 */
	public function filter_theme_page_templates( $templates, $theme, $post, $post_type ) {
		$templates['home']                    = __( 'Blog Archive', 'align' );
		$templates['archive-fluent-products'] = __( 'Shop Archive', 'align' );
		$templates['single-fluent-products']  = __( 'Single Product', 'align' );
		$templates['taxonomy-product-categories'] = __( 'Products by Category', 'align' );
		return $templates;
	}

	/**
	 * Improve the default page template title in page edit / quick edit dropdown.
	 *
	 * @param string $title     Default template label.
	 * @param string $post_type Post type.
	 * @return string Formatted label.
	 */
	public function filter_default_page_template_title( $title, $post_type ) {
		return __( 'Default (Pages)', 'align' );
	}

	/**
	 * Pre-populate default block content for new Pages in Gutenberg editor.
	 *
	 * @param string  $content Default post content.
	 * @param WP_Post $post    Post object.
	 * @return string Modified default content.
	 */
	public function filter_default_page_content( $content, $post ) {
		if ( $post && 'page' === $post->post_type && empty( $content ) ) {
			if ( class_exists( 'Align_Builder_Crud_Handler' ) && method_exists( 'Align_Builder_Crud_Handler', 'generate_default_custom_html_block' ) ) {
				return Align_Builder_Crud_Handler::generate_default_custom_html_block( $post->post_title );
			}
		}
		return $content;
	}

	/**
	 * Intercept standalone builder admin page request and render UI.
	 */
	public function handle_standalone_builder_page() {
		if ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] === ALIGN_BUILDER_SLUG ) {
			if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( 'edit_posts' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'align' ) );
			}

			// Redirect standalone access for standard pages/posts/CPTs to Gutenberg editor (Align embedded canvas)
			if ( empty( $_GET['embedded'] ) && ! empty( $_GET['post_id'] ) && is_numeric( $_GET['post_id'] ) ) {
				$target_post_type = (string) get_post_type( intval( $_GET['post_id'] ) );
				$is_eligible      = class_exists( 'Align_Editor_Mode_Lock' )
					? Align_Editor_Mode_Lock::is_eligible_post_type( $target_post_type )
					: ( 'page' === $target_post_type || 'post' === $target_post_type );
				if ( $is_eligible ) {
					$gutenberg_url = admin_url( 'post.php?post=' . intval( $_GET['post_id'] ) . '&action=edit' );
					if ( ! empty( $_GET['lang'] ) ) {
						$gutenberg_url = add_query_arg( 'lang', sanitize_key( $_GET['lang'] ), $gutenberg_url );
					}
					error_log( sprintf( '[Align Builder] Redirecting standalone page edit (ID: %d) to Gutenberg: %s', intval( $_GET['post_id'] ), $gutenberg_url ) );
					wp_safe_redirect( $gutenberg_url );
					exit;
				}
			}

			// Prevent server-side, proxy, and plugin caching on Standalone Builder page
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
				define( 'DONOTCACHEOBJECT', true );
			}
			if ( ! defined( 'DONOTCACHEDB' ) ) {
				define( 'DONOTCACHEDB', true );
			}
			if ( ! defined( 'DONOTMINIFY' ) ) {
				define( 'DONOTMINIFY', true );
			}
			if ( ! defined( 'DONOTCDN' ) ) {
				define( 'DONOTCDN', true );
			}

			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			if ( ! headers_sent() ) {
				header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
				header( 'Pragma: no-cache' );
				header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
			}

			if ( function_exists( 'do_action' ) ) {
				do_action( 'litespeed_disable_all' );
			}

			Align_Builder_Ui_Renderer::render_builder_ui();
			exit;
		}
	}

	/**
	 * Enqueue Gutenberg block editor & site editor integration scripts and styles.
	 */
	public function enqueue_site_editor_assets() {
		$theme_url  = get_template_directory_uri();
		$theme_path = get_template_directory();

		if ( class_exists( 'Align_Editor_Mode_Lock' ) ) {
			Align_Editor_Mode_Lock::maybe_lazy_lock_post();
		}

		// 1. Site Editor & Block Editor "Edit with Builder" integration overlay
		$overlay_css = '/core/builder/gutenberg/align-editor-overlay.css';
		if ( file_exists( $theme_path . $overlay_css ) ) {
			wp_enqueue_style(
				'align-site-editor-integration-style',
				$theme_url . $overlay_css,
				array(),
				filemtime( $theme_path . $overlay_css )
			);
		}

		// 2. Gutenberg Inspector Controls & Tab Panels styling
		$sidebar_css = '/core/builder/gutenberg/sidebar-common.css';
		if ( file_exists( $theme_path . $sidebar_css ) ) {
			wp_enqueue_style(
				'align-sidebar-main-style',
				$theme_url . $sidebar_css,
				array(),
				filemtime( $theme_path . $sidebar_css )
			);
		}

		// 3. Gutenberg Canvas Block layout & selection styling
		$blocks_css = '/core/builder/gutenberg/align-editor-blocks.css';
		if ( file_exists( $theme_path . $blocks_css ) ) {
			wp_enqueue_style(
				'align-editor-blocks-style',
				$theme_url . $blocks_css,
				array(),
				filemtime( $theme_path . $blocks_css )
			);
		}

		// 4. Template markup builder (mirrors Align_Builder_Template_Structure) + Save Coordinator script
		$markup_js = '/core/builder/gutenberg/align-template-markup.js';
		if ( file_exists( $theme_path . $markup_js ) ) {
			wp_register_script(
				'align-template-markup',
				$theme_url . $markup_js,
				array( 'wp-blocks', 'wp-block-serialization-default-parser', 'wp-data', 'wp-core-data' ),
				filemtime( $theme_path . $markup_js ),
				true
			);
		}

		$coordinator_js = '/core/builder/gutenberg/align-save-coordinator.js';
		if ( file_exists( $theme_path . $coordinator_js ) ) {
			wp_enqueue_script(
				'align-save-coordinator',
				$theme_url . $coordinator_js,
				array( 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-core-data', 'wp-api-fetch', 'align-template-markup' ),
				filemtime( $theme_path . $coordinator_js ),
				true
			);
			wp_localize_script( 'align-save-coordinator', 'AlignVvvebConfig', array(
				'builderUrl' => admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG ),
			) );
		}

		// 5. Template mode lock (content-derived, mirrors Align_Builder_Template_Structure::detect_mode) + Editor Mode Switch & Lock script
		$template_lock_js = '/core/builder/gutenberg/align-template-mode-lock.js';
		if ( file_exists( $theme_path . $template_lock_js ) ) {
			wp_register_script(
				'align-template-mode-lock',
				$theme_url . $template_lock_js,
				array( 'wp-blocks', 'wp-data', 'wp-core-data', 'align-template-markup' ),
				filemtime( $theme_path . $template_lock_js ),
				true
			);
		}

		$mode_switch_js = '/core/builder/gutenberg/align-editor-mode-switch.js';
		if ( file_exists( $theme_path . $mode_switch_js ) ) {
			wp_enqueue_script(
				'align-editor-mode-switch',
				$theme_url . $mode_switch_js,
				array( 'wp-element', 'wp-components', 'wp-data', 'wp-editor', 'wp-api-fetch', 'align-save-coordinator', 'align-template-mode-lock' ),
				filemtime( $theme_path . $mode_switch_js ),
				true
			);
			if ( class_exists( 'Align_Editor_Mode_Lock' ) ) {
				wp_localize_script( 'align-editor-mode-switch', 'AlignEditorModeConfig', Align_Editor_Mode_Lock::get_localized_config() );
			}
		}

		// 6. Site Editor & Gutenberg integration script ("Edit with Builder" buttons & deep linking)
		$integration_js = '/core/builder/gutenberg/align-site-editor-integration.js';
		if ( file_exists( $theme_path . $integration_js ) ) {
			wp_enqueue_script(
				'align-site-editor-integration',
				$theme_url . $integration_js,
				array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-hooks', 'wp-compose', 'wp-data', 'wp-editor', 'align-save-coordinator', 'align-editor-mode-switch' ),
				filemtime( $theme_path . $integration_js ),
				true
			);
			wp_localize_script( 'align-site-editor-integration', 'AlignVvvebConfig', array(
				'builderUrl' => admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG ),
			) );
		}

		// 5. Editor duplicate ID handler for builder blocks
		$duplicate_js = '/core/builder/gutenberg/editor-duplicate-handler.js';
		if ( file_exists( $theme_path . $duplicate_js ) ) {
			wp_enqueue_script(
				'align-editor-duplicate-handler',
				$theme_url . $duplicate_js,
				array( 'wp-blocks', 'wp-element', 'wp-data', 'wp-compose', 'wp-hooks' ),
				filemtime( $theme_path . $duplicate_js ),
				true
			);
		}

		// 6. Block tree auto focus
		$tree_focus_js = '/core/builder/gutenberg/block-tree-auto-focus.js';
		if ( file_exists( $theme_path . $tree_focus_js ) ) {
			wp_enqueue_script(
				'align-block-tree-auto-focus',
				$theme_url . $tree_focus_js,
				array( 'wp-blocks', 'wp-element', 'wp-data', 'wp-editor', 'wp-block-editor' ),
				filemtime( $theme_path . $tree_focus_js ),
				true
			);
		}
	}

	/**
	 * Register headless Builder page in WordPress Admin dashboard for iframe embeds.
	 */
	public function register_builder_menu() {
		// Headless registration so admin.php?page=align-builder remains valid for Gutenberg embedded iframe without displaying in admin menus
		$capability = ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) ? ( current_user_can( 'edit_posts' ) ? 'edit_posts' : 'edit_pages' ) : 'edit_pages';
		add_submenu_page( null, __( ALIGN_BUILDER_TITLE, 'align' ), __( ALIGN_BUILDER_TITLE, 'align' ), $capability, ALIGN_BUILDER_SLUG, array( 'Align_Builder_Ui_Renderer', 'render_builder_ui' ) );
	}

	/**
	 * Determine the active template slug for current frontend view.
	 *
	 * @return string Template slug without theme prefix.
	 */
	public static function get_current_template_slug() {
		if ( is_404() ) {
			return '404';
		}
		if ( is_search() ) {
			return 'search';
		}
		if ( is_home() ) {
			return file_exists( get_stylesheet_directory() . '/templates/home.html' ) ? 'home' : 'index';
		}
		if ( is_post_type_archive( 'fluent-products' ) ) {
			return 'archive-fluent-products';
		}
		if ( is_tax( 'product-categories' ) ) {
			return 'taxonomy-product-categories';
		}
		if ( is_archive() || is_category() || is_tag() || is_author() || is_date() || is_tax() || is_post_type_archive() ) {
			return 'archive';
		}
		if ( is_front_page() && ! is_singular() ) {
			return 'index';
		}
		if ( is_singular() ) {
			$slug = get_page_template_slug();
			if ( $slug ) {
				return pathinfo( $slug, PATHINFO_FILENAME );
			}
			$post_type = get_post_type();
			if ( 'fluent-products' === $post_type ) {
				return 'single-fluent-products';
			}
			return ( 'page' === $post_type ) ? 'page' : 'single';
		}
		global $template;
		if ( ! empty( $template ) ) {
			return sanitize_key( pathinfo( $template, PATHINFO_FILENAME ) );
		}
		return 'index';
	}

	/**
	 * Add "Edit with Align Builder" shortcut link to WordPress Admin Bar on frontend only.
	 * Suppressed inside wp-admin to avoid clutter and redundant navigation.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_builder_link( $wp_admin_bar ) {
		// Only display on frontend when viewing pages/templates, suppress in wp-admin dashboard
		if ( is_admin() || ! current_user_can( 'edit_pages' ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( is_singular() && $post_id ) {
			$builder_url = admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&post_id=' . $post_id );
			$wp_admin_bar->add_node( array(
				'id'    => 'align-builder-link',
				'title' => '<span class="ab-icon dashicons dashicons-layout" style="top: 2px;"></span> Edit with ' . ALIGN_BUILDER_TITLE,
				'href'  => $builder_url,
				'meta'  => array( 'target' => '_self' ),
			) );
		} else {
			$tpl_slug = self::get_current_template_slug();
			if ( $tpl_slug && 'single' !== $tpl_slug ) {
				$tpl_id   = 'tpl_' . get_stylesheet() . '//' . $tpl_slug;
				$tpl_url  = admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&tp_id=' . urlencode( $tpl_id ) );

				$wp_admin_bar->add_node( array(
					'id'    => 'align-builder-link',
					'title' => '<span class="ab-icon dashicons dashicons-layout" style="top: 2px;"></span> Edit with ' . ALIGN_BUILDER_TITLE,
					'href'  => $tpl_url,
					'meta'  => array( 'target' => '_self' ),
				) );
			}
		}
	}

	/**
	 * Add "Edit with Align Builder" quick action to page/post list tables.
	 *
	 * @param array   $actions Post actions list.
	 * @param WP_Post $post Current post object.
	 * @return array Modified post actions.
	 */
	public function add_row_actions( $actions, $post ) {
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			$url = admin_url( 'admin.php?page=' . ALIGN_BUILDER_SLUG . '&post_id=' . $post->ID );
			$actions['edit_align_builder'] = sprintf( '<a href="%s" style="color: #6366f1; font-weight: 600;">%s</a>', esc_url( $url ), sprintf( __( 'Edit with %s', 'align' ), ALIGN_BUILDER_TITLE ) );
		}
		return $actions;
	}
}
