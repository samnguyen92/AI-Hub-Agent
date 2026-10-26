<?php
/**
 * Dynamic Template Assets Loader Bootstrap Entry
 *
 * Coordinator class that registers hooks, parses directories,
 * and delegates processing to CSS, JS, Config, and Cache modules.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/template-assets/class-assets-config.php';
require_once __DIR__ . '/template-assets/class-assets-cache.php';
require_once __DIR__ . '/template-assets/class-assets-resolver.php';
require_once __DIR__ . '/template-assets/class-assets-css.php';
require_once __DIR__ . '/template-assets/class-assets-js.php';
require_once __DIR__ . '/template-assets/class-plugin-assets.php';

class TemplateAssetsLoader {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'manageJquery' ], 1 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueueTemplateAssets' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueueBuilderAssets' ], 20 );
		add_action( 'wp_footer', [ self::class, 'enqueueTemplateFooterAssets' ] );
		add_action( 'wp_head', [ self::class, 'outputHeadJsScript' ], 5 );
		add_action( 'wp_head', [ self::class, 'outputJqueryProxyStub' ], 1 );
		// registerAdminEditorStyles is disabled to prevent theme CSS from loading into WordPress Gutenberg CMS.
		// All theme styles are loaded exclusively into Align Page Builder canvas.
		add_action( 'admin_init', [ self::class, 'handleClearCacheAction' ] );
		add_action( 'admin_notices', [ self::class, 'renderAdminNotices' ] );

		add_action( 'init', [ PluginAssets::class, 'register' ] );
		add_action( 'wp_enqueue_scripts', [ PluginAssets::class, 'enqueue' ] );

		add_filter( 'style_loader_tag', [ AssetsCSS::class, 'filterTag' ], 10, 3 );
		add_filter( 'script_loader_tag', [ AssetsJS::class, 'filterTag' ], 10, 3 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wp_footer', function() {
				$templateName = AssetsResolver::getCurrentTemplateName();
				$jquery_enqueued = wp_script_is( 'jquery', 'enqueued' ) || wp_script_is( 'jquery-core', 'enqueued' );
				$jquery_templates = get_option( 'align_jquery_templates', [] );
				$jquery_templates_str = is_array( $jquery_templates ) ? implode( ',', $jquery_templates ) : '';
				?>
				<script>
					console.log( '--- ALIGN JQUERY DEBUG ---' );
					console.log( 'Resolved Template:', <?php echo json_encode( $templateName ); ?> );
					console.log( 'jQuery Enqueued:', <?php echo json_encode( $jquery_enqueued ? 'yes' : 'no' ); ?> );
					console.log( 'jQuery Saved Templates:', <?php echo json_encode( $jquery_templates_str ); ?> );
					console.log( 'User Logged In / Admin Bar:', <?php echo json_encode( ( is_user_logged_in() || is_admin_bar_showing() ) ? 'yes' : 'no' ); ?> );
					console.log( '--------------------------' );
				</script>
				<?php
			}, 9999 );
		}
	}

	public static function enqueueTemplateAssets(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$general = AssetsCache::get( 'general' );
		if ( false === $general ) {
			$general = AssetsResolver::getGeneralAssets();
			AssetsCache::set( 'general', $general );
		}

		if ( ! empty( $general ) ) {
			if ( ! empty( $general['css']['enqueue'] ) ) {
				foreach ( $general['css']['enqueue'] as $css ) {
					wp_enqueue_style( $css['handle'], $css['src'], [], $css['ver'] );
				}
			}

			if ( ! empty( $general['js']['enqueue'] ) ) {
				foreach ( $general['js']['enqueue'] as $js ) {
					wp_enqueue_script( $js['handle'], $js['src'], [], $js['ver'], true );
				}
			}
		}

		$templateName = AssetsResolver::getCurrentTemplateName();
		if ( empty( $templateName ) ) {
			return;
		}

		$assets = AssetsCache::get( $templateName );
		if ( false === $assets ) {
			$assets = AssetsResolver::getTemplateAssets( $templateName );
			AssetsCache::set( $templateName, $assets );
		}

		if ( ! empty( $assets ) ) {
			if ( ! empty( $assets['js']['enqueue'] ) ) {
				AssetsJS::enqueueFooter( $assets['js']['enqueue'] );
			}
		}
	}

	public static function enqueueTemplateFooterAssets(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$templateName = AssetsResolver::getCurrentTemplateName();
		if ( empty( $templateName ) ) {
			return;
		}

		$assets = AssetsCache::get( $templateName );
		if ( false === $assets ) {
			$assets = AssetsResolver::getTemplateAssets( $templateName );
			AssetsCache::set( $templateName, $assets );
		}

		if ( ! empty( $assets['css']['enqueue'] ) ) {
			AssetsCSS::enqueueFooter( $assets['css']['enqueue'] );
		}
	}

	/**
	 * Enqueue external builder CSS and JS assets from assets/builder/ if present.
	 */
	public static function enqueueBuilderAssets(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! (bool) get_option( 'align_builder_external_assets', 0 ) ) {
			return;
		}

		$builder_dir = trailingslashit( get_stylesheet_directory() ) . 'assets/builder/';
		$builder_uri = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/builder/';

		// Enqueue template part assets (header, footer) if present on disk
		foreach ( [ 'header', 'footer' ] as $part ) {
			$part_css = $builder_dir . 'style-part-' . $part . '.css';
			if ( file_exists( $part_css ) && ! wp_style_is( 'style-part-' . $part, 'enqueued' ) ) {
				wp_enqueue_style( 'style-part-' . $part, $builder_uri . 'style-part-' . $part . '.css', [], (string) filemtime( $part_css ) );
			}
			$part_js = $builder_dir . 'script-part-' . $part . '.js';
			if ( file_exists( $part_js ) && ! wp_script_is( 'script-part-' . $part, 'enqueued' ) ) {
				wp_enqueue_script( 'script-part-' . $part, $builder_uri . 'script-part-' . $part . '.js', [], (string) filemtime( $part_js ), true );
			}
		}

		$templateName = AssetsResolver::getCurrentTemplateName();
		if ( empty( $templateName ) ) {
			return;
		}

		$candidates = [
			'style-template-' . $templateName,
			'style-page-' . $templateName,
		];
		if ( in_array( $templateName, [ 'index', 'home' ], true ) ) {
			$candidates[] = 'style-template-home';
			$candidates[] = 'style-page-home';
		}

		foreach ( $candidates as $handle ) {
			$css_file = $builder_dir . $handle . '.css';
			if ( file_exists( $css_file ) && ! wp_style_is( $handle, 'enqueued' ) ) {
				wp_enqueue_style( $handle, $builder_uri . $handle . '.css', [], (string) filemtime( $css_file ) );
				break;
			}
		}

		$js_handle = str_replace( 'style-', 'script-', $candidates[0] );
		$js_file   = $builder_dir . $js_handle . '.js';
		if ( file_exists( $js_file ) && ! wp_script_is( $js_handle, 'enqueued' ) ) {
			wp_enqueue_script( $js_handle, $builder_uri . $js_handle . '.js', [], (string) filemtime( $js_file ), true );
		}

		// Enqueue singular post/page builder assets if present on disk
		if ( is_singular() ) {
			$post_id  = get_queried_object_id();
			$post_obj = get_post( $post_id );
			if ( $post_obj ) {
				$p_slug   = ! empty( $post_obj->post_name ) ? $post_obj->post_name : (string) $post_id;
				$p_prefix = ( 'wp_block' === $post_obj->post_type ) ? 'block' : 'page';
				foreach ( [ 'style-' . $p_prefix . '-' . $p_slug, 'style-' . $p_prefix . '-' . $post_id ] as $p_handle ) {
					$p_css = $builder_dir . $p_handle . '.css';
					if ( file_exists( $p_css ) && ! wp_style_is( $p_handle, 'enqueued' ) ) {
						wp_enqueue_style( $p_handle, $builder_uri . $p_handle . '.css', [], (string) filemtime( $p_css ) );
						break;
					}
				}
				$p_js_handle = 'script-' . $p_prefix . '-' . $p_slug;
				$p_js        = $builder_dir . $p_js_handle . '.js';
				if ( file_exists( $p_js ) && ! wp_script_is( $p_js_handle, 'enqueued' ) ) {
					wp_enqueue_script( $p_js_handle, $builder_uri . $p_js_handle . '.js', [], (string) filemtime( $p_js ), true );
				}
			}
		}
	}

	public static function registerAdminEditorStyles(): void {
		// Intentionally left blank. Theme stylesheets are loaded exclusively inside Align Page Builder canvas.
	}

	public static function manageJquery(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( is_user_logged_in() || is_admin_bar_showing() ) {
			return;
		}

		$loadJquery   = false;
		$postId       = 0;
		$templateName = AssetsResolver::getCurrentTemplateName();

		if ( is_singular() ) {
			$postId = get_queried_object_id();
			if ( $postId > 0 ) {
				$loadJquery = (bool) get_post_meta( $postId, 'align_load_jquery', true );
			}
		}

		if ( ! empty( $templateName ) ) {
			$savedTemplates = get_option( 'align_jquery_templates', [] );
			if ( ! is_array( $savedTemplates ) ) {
				$savedTemplates = [];
			}
			$jqueryTemplates = apply_filters( 'align_jquery_templates', $savedTemplates );
			if ( is_array( $jqueryTemplates ) ) {
				$nameVariants = [ $templateName ];
				if ( strpos( $templateName, 'template-' ) === 0 ) {
					$nameVariants[] = substr( $templateName, strlen( 'template-' ) );
				} else {
					$nameVariants[] = 'template-' . $templateName;
				}
				foreach ( $nameVariants as $variant ) {
					if ( in_array( $variant, $jqueryTemplates, true ) ) {
						$loadJquery = true;
						break;
					}
				}
			}
		}

		$loadJquery = (bool) apply_filters( 'align_load_jquery', $loadJquery, $postId, $templateName );

		if ( $loadJquery ) {
			wp_deregister_script( 'jquery-core' );
			wp_deregister_script( 'jquery-migrate' );
			wp_deregister_script( 'jquery' );

			wp_register_script( 'jquery-core', includes_url( '/js/jquery/jquery.min.js' ), [], null, [ 'in_footer' => true ] );
			wp_register_script( 'jquery-migrate', includes_url( '/js/jquery/jquery-migrate.min.js' ), [ 'jquery-core' ], null, [ 'in_footer' => true ] );
			wp_register_script( 'jquery', false, [ 'jquery-core', 'jquery-migrate' ] );
			wp_enqueue_script( 'jquery' );

			AssetsJS::markAsOptimized( 'jquery-core' );
			AssetsJS::markAsOptimized( 'jquery-migrate' );
		} else {
			wp_deregister_script( 'jquery-core' );
			wp_deregister_script( 'jquery-migrate' );
			wp_deregister_script( 'jquery' );
		}
	}

	public static function outputHeadJsScript(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$scripts = get_option( 'align_head_js_scripts', [] );
		if ( empty( $scripts ) || ! is_array( $scripts ) ) {
			return;
		}

		$templateName = AssetsResolver::getCurrentTemplateName();
		$post_type    = is_singular() ? get_post_type() : '';

		$nameVariants = [ $templateName ];
		if ( strpos( $templateName, 'template-' ) === 0 ) {
			$nameVariants[] = substr( $templateName, strlen( 'template-' ) );
		} else {
			$nameVariants[] = 'template-' . $templateName;
		}

		foreach ( $scripts as $item ) {
			if ( empty( $item['enabled'] ) || empty( trim( $item['code'] ?? '' ) ) ) {
				continue;
			}

			$targets = isset( $item['targets'] ) && is_array( $item['targets'] ) ? $item['targets'] : [];
			if ( empty( $targets ) ) {
				continue;
			}

			$should_output = false;
			foreach ( $nameVariants as $variant ) {
				if ( in_array( $variant, $targets, true ) ) {
					$should_output = true;
					break;
				}
			}

			if ( ! $should_output && ! empty( $post_type ) && in_array( $post_type, $targets, true ) ) {
				$should_output = true;
			}

			if ( $should_output ) {
				$script_title = ! empty( $item['title'] ) ? esc_html( $item['title'] ) : 'Head Custom JS Script';
				echo "\n" . '<!-- Align Head Custom JS Script: ' . $script_title . ' -->' . "\n";
				echo $item['code'] . "\n";
				echo '<!-- End Align Head Custom JS Script -->' . "\n";
			}
		}
	}

	public static function handleClearCacheAction(): void {
		if ( isset( $_GET['page'] ) && 'align-settings' === $_GET['page'] && isset( $_GET['action'] ) && 'clear_assets_cache' === $_GET['action'] && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'align_clear_assets_cache_nonce' );
			AssetsCache::clearAll();
			wp_safe_redirect( add_query_arg( [ 'page' => 'align-settings', 'tab' => 'general', 'cache_cleared' => 1 ], admin_url( 'options-general.php' ) ) );
			exit;
		}
	}

	public static function renderAdminNotices(): void {
		if ( isset( $_GET['cache_cleared'] ) && 1 === (int) $_GET['cache_cleared'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template assets cache cleared successfully.', 'align' ) . '</p></div>';
		}
	}

	public static function outputJqueryProxyStub(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( AssetsConfig::shouldSkipOptimize() ) {
			return;
		}

		$proxyFile = ALIGN_CORE_PATH . 'assets/js/align-jquery-proxy.js';
		if ( ! file_exists( $proxyFile ) ) {
			return;
		}

		$content = file_get_contents( $proxyFile );
		if ( empty( trim( $content ) ) ) {
			return;
		}

		echo "\n<script id=\"align-jquery-proxy-js\">\n" . trim( $content ) . "\n</script>\n";
	}
}
