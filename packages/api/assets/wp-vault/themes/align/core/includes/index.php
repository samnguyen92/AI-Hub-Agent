<?php
/**
 * Includes Entrypoint Loader
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. System Logger
require_once __DIR__ . '/core/logger.php';

// 1.1 Site Protection & Access Control Engine
require_once __DIR__ . '/core/class-align-site-protection.php';
Align_Site_Protection::init();

// 1.2 Universal Password Lock Engine
require_once __DIR__ . '/core/class-align-post-password-lock.php';
Align_Post_Password_Lock::init();

// 2. Core Features & Helpers
require_once __DIR__ . '/core/class-align-post-content.php';
Align_Post_Content::init();
require_once __DIR__ . '/core/content-filters.php';
require_once __DIR__ . '/core/content-hidden-elements.php';
require_once __DIR__ . '/core/breadcrumbs-block-filters.php';
require_once __DIR__ . '/core/class-align-template-tweaks.php';
Align_Template_Tweaks::init();
require_once __DIR__ . '/core/template-actions.php';
require_once __DIR__ . '/core/theme-functions.php';
require_once __DIR__ . '/core/theme-init.php';
require_once __DIR__ . '/core/rewrite-rules.php';
require_once __DIR__ . '/core/minify.php';
require_once __DIR__ . '/core/class-align-script-sanitizer.php';
Align_Script_Sanitizer::init();

// 3. Responsive Breakpoints Central Manager
require_once __DIR__ . '/core/class-align-breakpoints.php';
AlignBreakpoints::init();

// 3.1 Multi-Language Manager
require_once __DIR__ . '/core/class-align-language-manager.php';
Align_Language_Manager::init();
require_once __DIR__ . '/core/class-align-language-switcher.php';
Align_Language_Switcher::init();
require_once __DIR__ . '/core/class-align-post-language-meta.php';
Align_Post_Language_Meta::init();
require_once __DIR__ . '/core/class-align-page-language-admin.php';
Align_Page_Language_Admin::init();
require_once __DIR__ . '/core/class-align-template-language.php';
Align_Template_Language::init();
require_once __DIR__ . '/core/class-align-language-url-localizer.php';
Align_Language_Url_Localizer::init();

// 3.2 Hreflang SEO Alternate Tags (Multi-language Google indexing)
require_once __DIR__ . '/core/class-align-hreflang.php';
Align_Hreflang::init();

// 3.2.1 Native Theme Default SEO Engine
require_once __DIR__ . '/core/class-align-seo.php';
Align_Seo::init();

// 3.3 404 URL Monitor & Quick Redirect Collector
require_once __DIR__ . '/core/class-align-404-collector.php';
Align_404_Collector::init();

// 4. Post Query Helper for Builder
require_once __DIR__ . '/core/class-align-post-helper.php';
AlignPostHelper::init();

// 4.1 Native Reusable Blocks (wp_block) Shortcode & REST Engine
require_once __DIR__ . '/core/class-align-reusable-block.php';
Align_Reusable_Block::init();

// 5. Custom CSS/JS Frontend Output Engine
require_once __DIR__ . '/core/class-align-custom-css-js.php';
Align_Custom_CSS_JS::init();
require_once __DIR__ . '/core/class-align-custom-code-slots.php';
Align_Custom_Code_Slots::init();

// 6. REST API Sync Engine for Builder / External Tools
require_once __DIR__ . '/core/api-sync.php';
Align_API_Sync::init();

// 6.1. Unified Multi-Endpoint Health Check Engine
require_once __DIR__ . '/core/class-align-endpoint-tester.php';
Align_Endpoint_Tester::init();
require_once __DIR__ . '/core/class-align-block-converter.php';
require_once dirname( __DIR__ ) . '/builder/gutenberg/class-align-editor-paste.php';

// 7. WebP Media Converter & Fallback Engine
require_once __DIR__ . '/core/class-align-webp.php';
Align_WebP::init();

// 7.1. Secure SVG Upload & Media Preview Engine
require_once __DIR__ . '/core/class-align-svg-support.php';
Align_SVG_Support::init();

// 7.2. Media Filename Normalizer & Auto-Sanitization Engine
require_once __DIR__ . '/core/class-align-media-normalizer.php';
Align_Media_Normalizer::init();
require_once __DIR__ . '/admin/class-align-media-normalizer-admin.php';
Align_Media_Normalizer_Admin::init();

// 8. Image Lazyload Engine

require_once __DIR__ . '/core/class-align-lazyload.php';
Align_Lazyload::init();

// 8.1. Smooth Scroll Engine (Lenis)
require_once __DIR__ . '/core/class-align-smooth-scroll.php';
Align_Smooth_Scroll::init();

// 8.2. LCP Featured Image Preload Engine
require_once __DIR__ . '/core/class-align-lcp-preload.php';
Align_LCP_Preload::init();

// 9. Drag & Drop Post Order Manager
require_once __DIR__ . '/core/class-align-post-order.php';
require_once __DIR__ . '/core/class-align-post-order-ajax.php';
Align_Post_Order::init();

// 9.1 Post Duplicator Engine
require_once __DIR__ . '/admin/class-align-post-duplicator.php';
Align_Post_Duplicator::init();

// 10. Clean Admin Settings System
require_once __DIR__ . '/admin/class-align-image-sizes.php';
Align_Image_Sizes::init();

require_once __DIR__ . '/admin/class-align-thumbnail-regenerator.php';
Align_Thumbnail_Regenerator::init();

require_once __DIR__ . '/admin/class-align-admin-assets.php';
Align_Admin_Assets::init();

require_once __DIR__ . '/admin/class-align-admin-menu.php';
Align_Admin_Menu::init();

require_once __DIR__ . '/admin/class-align-admin-settings.php';
Align_Admin_Settings::init();

require_once __DIR__ . '/admin/class-align-ai-connection.php';
Align_AI_Connection::init();

require_once __DIR__ . '/admin/class-align-post-ai-seo.php';
Align_Post_Ai_Seo::init();

require_once __DIR__ . '/admin/class-align-page-settings.php';
Align_Page_Settings::init();

require_once __DIR__ . '/admin/class-align-editor-mode-lock.php';
Align_Editor_Mode_Lock::init();

require_once __DIR__ . '/admin/class-align-quick-edit.php';
Align_Quick_Edit::init();

require_once __DIR__ . '/admin/class-align-font-manager.php';
Align_Font_Manager::init();

// 11. Git Updater Engine
require_once __DIR__ . '/admin/class-align-git-updater.php';
Align_Git_Updater::init();
require_once __DIR__ . '/admin/class-align-plugin-updater.php';
Align_Plugin_Updater::init();

// 12. HTML Project ZIP Importer Engine
require_once __DIR__ . '/admin/importer/class-align-zip-importer.php';
Align_Zip_Importer::init();

// 13. Rank Math Redirection Importer & Translation Matrix
require_once __DIR__ . '/admin/class-align-redirection-importer.php';
Align_Redirection_Importer::init();

require_once __DIR__ . '/admin/class-align-translation-matrix.php';
Align_Translation_Matrix::init();

require_once __DIR__ . '/admin/class-align-bulk-translator.php';
Align_Bulk_Translator::init();

require_once __DIR__ . '/admin/class-align-bulk-url-scanner.php';
Align_Bulk_Url_Scanner::init();

require_once __DIR__ . '/admin/class-align-bulk-seo-generator.php';
Align_Bulk_Seo_Generator::init();

// 14. Dedicated AI REST API Endpoint Engine
require_once __DIR__ . '/ai-endpoint/class-align-ai-endpoint.php';
Align_AI_Endpoint::init();

