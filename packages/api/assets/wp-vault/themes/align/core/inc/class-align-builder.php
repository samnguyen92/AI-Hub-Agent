<?php
/**
 * Align Theme Core Engine - Standalone Align Builder Integration Bootstrap & Facade
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ALIGN_BUILDER_SLUG' ) ) {
	define( 'ALIGN_BUILDER_SLUG', 'align-builder' );
}
if ( ! defined( 'ALIGN_BUILDER_TITLE' ) ) {
	define( 'ALIGN_BUILDER_TITLE', 'Align Builder' );
}
if ( ! defined( 'ALIGN_BUILDER_REST_NS' ) ) {
	define( 'ALIGN_BUILDER_REST_NS', 'align-builder/v1' );
}
if ( ! defined( 'ALIGN_BUILDER_PATH' ) ) {
	define( 'ALIGN_BUILDER_PATH', get_template_directory() . '/core/builder/' );
}
if ( ! defined( 'ALIGN_BUILDER_URL' ) ) {
	define( 'ALIGN_BUILDER_URL', get_template_directory_uri() . '/core/builder/' );
}

// 1. Require Submodules
require_once __DIR__ . '/builder/class-builder-asset-history.php';
require_once __DIR__ . '/builder/class-builder-asset-writer.php';
require_once __DIR__ . '/builder/class-builder-assets.php';
require_once __DIR__ . '/builder/class-builder-asset-cleaner.php';
require_once __DIR__ . '/builder/class-builder-asset-sync.php';
require_once __DIR__ . '/builder/class-builder-admin.php';
require_once __DIR__ . '/builder/class-builder-rest-api.php';
require_once __DIR__ . '/builder/class-builder-ui-renderer.php';
require_once __DIR__ . '/builder/class-builder-template-cleaner.php';
require_once __DIR__ . '/builder/class-builder-minifier.php';
require_once __DIR__ . '/builder/class-builder-image-resolver.php';
require_once __DIR__ . '/builder/class-builder-dynamic-studio.php';
require_once __DIR__ . '/builder/class-builder-dynamic-ai-binder.php';
require_once __DIR__ . '/builder/class-builder-dynamic-renderer.php';
require_once __DIR__ . '/builder/class-builder-template-structure.php';
require_once __DIR__ . '/builder/class-builder-persistence-service.php';

/**
 * Main Align Builder Controller & Backward-Compatibility Facade
 */
class Align_Core_Vvveb_Builder {

	/**
	 * Submodule instances.
	 */
	public $admin;
	public $rest_api;
	public $dynamic_studio;

	/**
	 * Constructor initializing all submodules.
	 */
	public function __construct() {
		$this->admin          = new Align_Builder_Admin();
		$this->rest_api       = new Align_Builder_Rest_Api();
		$this->dynamic_studio = new Align_Builder_Dynamic_Studio();
		Align_Builder_Dynamic_AI_Binder::init();
		Align_Builder_Dynamic_Renderer::init();
		Align_Builder_Persistence_Service::init();

		// Asset Garbage Collection hook on post deletion
		add_action( 'before_delete_post', array( 'Align_Builder_Asset_Cleaner', 'handle_before_delete_post' ), 10, 2 );
	}

	// -------------------------------------------------------------
	// Backward-Compatibility Static Proxies
	// -------------------------------------------------------------

	public static function resolve_asset_url( $url ) {
		return Align_Builder_Assets::resolve_asset_url( $url );
	}

	public static function is_asset_enabled( array $asset ) {
		return Align_Builder_Assets::is_asset_enabled( $asset );
	}

	public static function get_builder_css_urls() {
		return Align_Builder_Assets::get_builder_css_urls();
	}

	public static function get_builder_js_assets( $target_location = 'all' ) {
		return Align_Builder_Assets::get_builder_js_assets( $target_location );
	}

	public static function replace_dummy_images( $html ) {
		return Align_Builder_Assets::replace_dummy_images( $html );
	}

	public static function clean_duplicate_templates( $target_type = null ) {
		return Align_Builder_Template_Cleaner::clean_duplicate_templates( $target_type );
	}

	public function render_builder_ui() {
		Align_Builder_Ui_Renderer::render_builder_ui();
	}
}

new Align_Core_Vvveb_Builder();
