<?php
/**
 * Align Theme - Align Studio Main View
 * Independent, dedicated studio workspace for editing and exporting Reusable Blocks
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$theme_version   = defined( 'ALIGN_THEME_VERSION' ) ? ALIGN_THEME_VERSION : '1.0.0';
$line_awesome    = ALIGN_BUILDER_URL . 'css/vendor/line-awesome.css';
$bootstrap_css   = ALIGN_BUILDER_URL . 'css/vendor/bootstrap-custom.css';
$codemirror_js   = ALIGN_BUILDER_URL . 'libs/codemirror/cm6-lezer.bundle.min.js?v=' . $theme_version;
$studio_base_url = ALIGN_BUILDER_URL . 'studio/';

$studio_css = $studio_base_url . 'studio.css?v=' . $theme_version;
$editor_css = $studio_base_url . 'studio-editor.css?v=' . $theme_version;
$tokens_css = $studio_base_url . 'studio-tokens.css?v=' . $theme_version;

$editor_js  = $studio_base_url . 'studio-editor.js?v=' . $theme_version;
$tokens_js  = $studio_base_url . 'studio-tokens.js?v=' . $theme_version;
$preview_js = $studio_base_url . 'studio-preview.js?v=' . $theme_version;
$studio_js  = $studio_base_url . 'studio.js?v=' . $theme_version;

$rest_export  = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/export-studio-block' ) );
$rest_draft   = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/studio-draft' ) );
$rest_canvas  = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/studio-canvas' ) );
$rest_preview = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/studio-render-preview' ) );
$rest_ai_bind = esc_url_raw( rest_url( ALIGN_BUILDER_REST_NS . '/studio-ai-bind' ) );
$rest_nonce   = wp_create_nonce( 'wp_rest' );

// Builder redirect URL & Initial Context
$post_id     = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : ( isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0 );
$draft_id    = isset( $_GET['draft_id'] ) ? sanitize_key( $_GET['draft_id'] ) : '';
$builder_url = $post_id > 0
	? admin_url( 'admin.php?page=align-builder&post=' . $post_id )
	: admin_url( 'admin.php?page=align-builder' );

$is_existing_block = false;
$block_title       = '';
$block_slug        = '';
$existing_html     = '';
$existing_css      = '';
$existing_js       = '';

if ( $post_id > 0 ) {
	$target_post = get_post( $post_id );
	if ( $target_post && ( 'wp_block' === $target_post->post_type || get_post_meta( $post_id, '_align_is_dynamic_block', true ) ) ) {
		$is_existing_block = true;
		$block_title       = $target_post->post_title;
		$block_slug        = $target_post->post_name;

		$raw_content = $target_post->post_content;
		if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>(.*?)<!-- \/wp:align\/custom-html -->/s', $raw_content, $m ) ) {
			$existing_html = trim( $m[1] );
		} elseif ( preg_match( '/<!-- wp:html -->(.*?)<!-- \/wp:html -->/s', $raw_content, $m ) ) {
			$existing_html = trim( $m[1] );
		} else {
			$existing_html = $raw_content;
		}

		$style_id = 'style-block-' . $block_slug;
		if ( preg_match( '/<style[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $style_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/style>/is', $existing_html, $css_m ) ) {
			$existing_css  = $css_m[2];
			$existing_html = trim( preg_replace( '/<style[^>]*\bid\s*=\s*(["\']?)' . preg_quote( $style_id, '/' ) . '\1[^>]*>([\s\S]*?)<\/style>/is', '', $existing_html ) );
		}
		if ( empty( $existing_css ) ) {
			$existing_css = get_post_meta( $post_id, '_align_custom_css', true ) ?: '';
			if ( empty( $existing_css ) && class_exists( 'Align_Builder_Assets' ) && ! empty( $block_slug ) ) {
				$existing_css = Align_Builder_Assets::load_external_css( $style_id ) ?: '';
			}
		}
	}
}

$initial_canvas_url = esc_url_raw( add_query_arg(
	array_filter( array(
		'_wpnonce' => $rest_nonce,
		'draft_id' => ! empty( $draft_id ) ? $draft_id : null,
		'id'       => $post_id > 0 ? $post_id : null,
		'v'        => time(),
	) ),
	$rest_canvas
) );

// Full theme & template stylesheets for iframe canvas
$theme_css_urls = class_exists( 'Align_Builder_Assets' )
	? Align_Builder_Assets::get_builder_css_urls()
	: array();

if ( function_exists( 'get_stylesheet_uri' ) ) {
	$theme_css_urls[] = get_stylesheet_uri();
}
$theme_css_urls[] = $bootstrap_css;
$theme_css_urls[] = $line_awesome;
$theme_css_urls   = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $theme_css_urls ) ) ) );

// Theme JS assets for iframe canvas
$theme_js_urls = array();
if ( class_exists( 'Align_Builder_Assets' ) ) {
	$all_js = Align_Builder_Assets::get_builder_js_assets( 'all' );
	foreach ( $all_js as $js_item ) {
		$theme_js_urls[] = is_array( $js_item ) ? $js_item['url'] : $js_item->url;
	}
}
if ( function_exists( 'includes_url' ) ) {
	$theme_js_urls[] = includes_url( '/js/jquery/jquery.min.js' );
}
$theme_js_urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $theme_js_urls ) ) ) );

$font_preloads = class_exists( 'Align_Builder_Assets' ) ? Align_Builder_Assets::get_font_preloads() : array();
$custom_css    = get_option( 'align_custom_css', '' );
$custom_js     = get_option( 'align_custom_js', '' );
$site_url      = home_url( '/' );
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Align Studio - Reusable Block Studio', 'align' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $line_awesome ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( $studio_css ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( $editor_css ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( $tokens_css ); ?>">
	<script>
		window.AlignStudioConfig = {
			restExportUrl: <?php echo wp_json_encode( $rest_export ); ?>,
			restDraftUrl: <?php echo wp_json_encode( $rest_draft ); ?>,
			restCanvasUrl: <?php echo wp_json_encode( $rest_canvas ); ?>,
			restPreviewUrl: <?php echo wp_json_encode( $rest_preview ); ?>,
			restAiBindUrl: <?php echo wp_json_encode( $rest_ai_bind ); ?>,
			initialCanvasUrl: <?php echo wp_json_encode( $initial_canvas_url ); ?>,
			draftId: <?php echo wp_json_encode( $draft_id ); ?>,
			postId: <?php echo intval( $post_id ); ?>,
			isEditMode: <?php echo $is_existing_block ? 'true' : 'false'; ?>,
			blockTitle: <?php echo wp_json_encode( $block_title ); ?>,
			blockSlug: <?php echo wp_json_encode( $block_slug ); ?>,
			existingHtml: <?php echo wp_json_encode( $existing_html ); ?>,
			existingCss: <?php echo wp_json_encode( $existing_css ); ?>,
			existingJs: <?php echo wp_json_encode( $existing_js ); ?>,
			restNonce: <?php echo wp_json_encode( $rest_nonce ); ?>,
			builderUrl: <?php echo wp_json_encode( $builder_url ); ?>,
			siteUrl: <?php echo wp_json_encode( $site_url ); ?>,
			themeCssUrls: <?php echo wp_json_encode( $theme_css_urls ); ?>,
			themeJsUrls: <?php echo wp_json_encode( $theme_js_urls ); ?>,
			fontPreloads: <?php echo wp_json_encode( $font_preloads ); ?>,
			customCss: <?php echo wp_json_encode( $custom_css ); ?>,
			customJs: <?php echo wp_json_encode( $custom_js ); ?>
		};
	</script>
</head>
<body class="align-studio-body">

	<!-- 1. Studio Topbar Header -->
	<header id="studio-header">
		<div class="studio-header-left">
			<a href="<?php echo esc_url( $builder_url ); ?>" class="btn-ghost-back" title="<?php esc_attr_e( 'Return to Align Builder', 'align' ); ?>">
				<i class="la la-arrow-left"></i>
				<span><?php esc_html_e( 'Back to Builder', 'align' ); ?></span>
			</a>
		</div>

		<div class="studio-header-center">
			<div class="studio-block-name-box">
				<input type="text" id="studio-block-title-top" class="studio-block-name-input" placeholder="<?php esc_attr_e( 'Block Name...', 'align' ); ?>" value="<?php echo esc_attr( $is_existing_block && ! empty( $block_title ) ? $block_title : 'Dynamic Reusable Block' ); ?>">
			</div>

			<div class="studio-viewport-group">
				<button type="button" class="studio-viewport-btn active" data-viewport="100%" title="<?php esc_attr_e( 'Desktop View (100%)', 'align' ); ?>">
					<i class="la la-desktop"></i>
				</button>
				<button type="button" class="studio-viewport-btn" data-viewport="768px" title="<?php esc_attr_e( 'Tablet View (768px)', 'align' ); ?>">
					<i class="la la-tablet"></i>
				</button>
				<button type="button" class="studio-viewport-btn" data-viewport="375px" title="<?php esc_attr_e( 'Mobile View (375px)', 'align' ); ?>">
					<i class="la la-mobile"></i>
				</button>
			</div>

			<!-- Preview Mode Toggle: Live Data vs Template -->
			<div class="studio-mode-group" id="studio-mode-group" role="group" aria-label="<?php esc_attr_e( 'Preview Mode', 'align' ); ?>">
				<button type="button" class="studio-mode-btn active" data-mode="live" title="<?php esc_attr_e( 'Preview with real WordPress data', 'align' ); ?>">
					<i class="la la-bolt"></i>
					<span><?php esc_html_e( 'Live Data', 'align' ); ?></span>
				</button>
				<button type="button" class="studio-mode-btn" data-mode="template" title="<?php esc_attr_e( 'Preview raw template tokens', 'align' ); ?>">
					<i class="la la-code"></i>
					<span><?php esc_html_e( 'Template', 'align' ); ?></span>
				</button>
			</div>
		</div>

		<div class="studio-header-right">
			<!-- Panel Toggle for Code Editor -->
			<button type="button" id="toggle-code-btn" class="btn-header-tool active" title="<?php esc_attr_e( 'Toggle Code Editor', 'align' ); ?>">
				<i class="la la-code"></i>
				<span><?php esc_html_e( 'Code', 'align' ); ?></span>
			</button>

			<!-- Shortcode Topbar Pill (Visible after export or in edit mode) -->
			<div id="top-panel-shortcode-container" class="topbar-shortcode-pill" style="<?php echo $is_existing_block ? 'display:inline-flex;' : ''; ?>" title="<?php esc_attr_e( 'Click to copy shortcode', 'align' ); ?>">
				<i class="la la-code"></i>
				<span id="shortcode-top-preview"><?php echo $is_existing_block ? esc_html( '[reuse-block id="' . $post_id . '"]' ) : '[reuse-block id="..."]'; ?></span>
				<i class="la la-copy ms-1"></i>
			</div>

			<!-- Primary Export / Update CTA -->
			<button type="button" id="export-reusable-btn" class="btn-export-primary">
				<i class="la la-cube"></i>
				<span class="btn-text"><?php echo $is_existing_block ? esc_html__( 'Update Reusable Block', 'align' ) : esc_html__( 'Export Reusable Block', 'align' ); ?></span>
				<span class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
			</button>
		</div>
	</header>

	<!-- 2. Main Studio Workspace -->
	<main id="studio-workspace">

		<!-- Center Main Column (Visual Viewport + Docked Code Editor) -->
		<section id="studio-main-column">
			<!-- Scrollable Visual Canvas Viewport -->
			<div id="studio-canvas-viewport">
				<div id="canvas-wrapper" class="canvas-wrapper">
					<iframe id="studio-canvas-iframe" src="<?php echo esc_url( $initial_canvas_url ); ?>" title="<?php esc_attr_e( 'Element Canvas Sandbox', 'align' ); ?>"></iframe>
				</div>
			</div>

			<!-- Docked Bottom Code Editor -->
			<div id="studio-code-panel">
				<div class="studio-editor-header">
					<div class="studio-editor-tabs">
						<div class="editor-label-badge">
							<i class="la la-code"></i>
							<span><?php esc_html_e( 'Code Editor', 'align' ); ?></span>
						</div>
						<button type="button" class="editor-tab-btn active" data-tab="html">
							<i class="la la-code"></i>
							<span>HTML</span>
						</button>
						<button type="button" class="editor-tab-btn" data-tab="css">
							<i class="la la-paint-brush"></i>
							<span>CSS</span>
						</button>
						<button type="button" class="editor-tab-btn" data-tab="js">
							<i class="la la-terminal"></i>
							<span>JS</span>
						</button>
					</div>

					<div class="studio-editor-tools">
						<div class="live-sync-indicator" id="live-sync-badge" title="<?php esc_attr_e( 'Live Preview Sync Active', 'align' ); ?>">
							<span class="sync-dot"></span>
							<span class="sync-label"><?php esc_html_e( 'Live Sync', 'align' ); ?></span>
						</div>

						<button type="button" id="btn-format-html" class="btn-editor-action">
							<i class="la la-magic"></i>
							<span>Format</span>
						</button>

						<button type="button" id="btn-close-code-panel" class="btn-editor-close" title="<?php esc_attr_e( 'Close Code Editor', 'align' ); ?>">
							<i class="la la-times"></i>
						</button>
					</div>
				</div>

				<div class="studio-editor-body">
					<div id="pane-html" class="editor-pane active">
						<textarea id="studio-html-editor" spellcheck="false" placeholder="<!-- HTML code -->"></textarea>
					</div>
					<div id="pane-css" class="editor-pane">
						<textarea id="studio-css-editor" spellcheck="false" placeholder="/* CSS code */"></textarea>
					</div>
					<div id="pane-js" class="editor-pane">
						<textarea id="studio-js-editor" spellcheck="false" placeholder="// JavaScript code"></textarea>
					</div>
				</div>
			</div>
		</section>

		<!-- Right: Dynamic Tokens Palette & Manager -->
		<aside id="studio-right-sidebar">
			<div id="export-success-banner" class="studio-alert-success">
				<!-- Injected upon export -->
			</div>

			<!-- Hidden inputs for backward compatibility -->
			<input type="hidden" id="studio-block-slug" value="dynamic-block">
			<span id="meta-source-page" style="display:none;">--</span>

			<div class="studio-tokens-manager">
				<div class="studio-tokens-card">
					<div class="studio-tokens-header">
						<div class="studio-tokens-title">
							<i class="la la-database"></i>
							<span><?php esc_html_e( 'Dynamic Tokens', 'align' ); ?></span>
						</div>
						<span id="tokens-count-badge" class="studio-tokens-count">17 tokens</span>
					</div>

					<p class="studio-tokens-hint">
						<?php esc_html_e( 'Insert tokens into your HTML template. Values hydrate from real WordPress data during preview and runtime.', 'align' ); ?>
					</p>

					<!-- Custom Prompt / User Specification (Optional) -->
					<div class="tokens-custom-prompt-wrap">
						<textarea id="tokens-custom-prompt" class="tokens-custom-prompt" rows="2" placeholder="<?php esc_attr_e( 'Describe dynamic logic (optional, e.g. "Product card, loop 4 posts")...', 'align' ); ?>"></textarea>
					</div>

					<!-- AI Auto-Bind Action in Sidebar -->
					<button type="button" id="sidebar-ai-convert-btn" class="btn-sidebar-ai-convert" title="<?php esc_attr_e( 'Convert to dynamic with AI', 'align' ); ?>">
						<i class="la la-magic"></i>
						<span class="btn-text"><?php esc_html_e( 'Convert to dynamic with AI', 'align' ); ?></span>
						<span class="spinner-border spinner-border-sm d-none ms-1" role="status"></span>
					</button>

					<!-- Search Box -->
					<div class="tokens-search-wrap">
						<i class="la la-search"></i>
						<input type="text" id="tokens-search-input" class="tokens-search-input" placeholder="<?php esc_attr_e( 'Search tokens...', 'align' ); ?>" autocomplete="off" spellcheck="false">
					</div>

					<!-- Category Filter Bar -->
					<div class="tokens-filter-bar" id="tokens-filter-bar">
						<button type="button" class="tokens-filter-btn active" data-category="all"><?php esc_html_e( 'All', 'align' ); ?></button>
						<button type="button" class="tokens-filter-btn" data-category="post"><?php esc_html_e( 'Post', 'align' ); ?></button>
						<button type="button" class="tokens-filter-btn" data-category="loop"><?php esc_html_e( 'Loop', 'align' ); ?></button>
						<button type="button" class="tokens-filter-btn" data-category="acf"><?php esc_html_e( 'ACF', 'align' ); ?></button>
						<button type="button" class="tokens-filter-btn" data-category="site"><?php esc_html_e( 'Site', 'align' ); ?></button>
					</div>
				</div>

				<!-- Scrollable Token List Container -->
				<div id="tokens-scroll-list" class="tokens-scroll-list">
					<!-- Dynamic items rendered by studio-tokens.js -->
				</div>
			</div>
		</aside>

	</main>

	<!-- Toast Container -->
	<div id="studio-toast" class="studio-toast"></div>

	<!-- CodeMirror 6 + Lezer AST Bundle (from Align Builder) -->
	<script src="<?php echo esc_url( $codemirror_js ); ?>"></script>
	<!-- Studio Modules -->
	<script src="<?php echo esc_url( $editor_js ); ?>"></script>
	<script src="<?php echo esc_url( $tokens_js ); ?>"></script>
	<script src="<?php echo esc_url( $preview_js ); ?>"></script>
	<script src="<?php echo esc_url( $studio_js ); ?>"></script>
</body>
</html>
