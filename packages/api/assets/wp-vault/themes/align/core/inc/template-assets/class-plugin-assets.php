<?php
/**
 * Plugin Assets Manager
 *
 * Centralized registration and conditional enqueue for third-party
 * plugin libraries (Splide, Lightbox, etc.) used by custom blocks.
 *
 * Assets are registered once at `init`, then conditionally enqueued
 * only when a page contains a block that requires them.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PluginAssets {

	/**
	 * Plugin asset registry.
	 *
	 * Each entry maps a unique plugin key to its assets config:
	 *   'js'     => [ handle, relative_path, deps[], version ],
	 *   'css'    => [ handle, relative_path, deps[], version ],
	 *   'blocks' => [ 'namespace/block-name', ... ] — blocks that need this plugin,
	 *
	 * @return array
	 */
	private static function getRegistry(): array {
		$plugins_url = get_template_directory_uri() . '/core/assets/plugins/';
		$plugins_dir = get_template_directory() . '/core/assets/plugins/';

		$registry = [
			'splide' => [
				'js' => [
					'handle'  => 'splide-js',
					'src'     => $plugins_url . 'splide/splide.min.js',
					'deps'    => [],
					'ver'     => '4.1.4',
				],
				'css' => [
					'handle'  => 'splide-css',
					'src'     => $plugins_url . 'splide/splide.min.css',
					'deps'    => [],
					'ver'     => '4.1.4',
				],
				'blocks' => [
					'align/query-builder',
					'align/slide',
					'align/marquee',
				],
				// Block-owned scripts/styles to also mark as optimized
				// (WP auto-enqueues these via register_block_type 'script'/'style' keys,
				//  but they still need data-align="optimize" for lazy loading)
				'block_scripts' => [
					'align-query-builder-frontend',
				],
				'block_styles' => [
					'align-query-builder-style',
				],
			],
			'splide-autoscroll' => [
				'js' => [
					'handle'  => 'splide-autoscroll-js',
					'src'     => $plugins_url . 'splide/splide-extension-auto-scroll.min.js',
					'deps'    => ['splide-js'],
					'ver'     => '0.5.3',
				],
				'blocks' => [
					'align/marquee',
				],
				'block_scripts' => [
					'align-marquee-frontend',
				],
				'block_styles' => [
					'align-marquee-style',
				],
			],
		];

		// Enable Splide.js and Auto-scroll extension globally if enabled in theme settings
		if ( (int) get_option( 'align_enable_splidejs', 0 ) === 1 ) {
			$registry['splide']['always_enqueue']            = true;
			$registry['splide-autoscroll']['always_enqueue'] = true;
		}

		return $registry;
	}

	/**
	 * Register all plugin assets globally.
	 * Hooked to `init` so handles are available for block dependency chains.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::getRegistry() as $plugin ) {
			if ( ! empty( $plugin['js'] ) ) {
				$js = $plugin['js'];
				if ( ! wp_script_is( $js['handle'], 'registered' ) ) {
					wp_register_script(
						$js['handle'],
						$js['src'],
						$js['deps'],
						$js['ver'],
						[
							'in_footer' => true,
							'strategy'  => 'defer',
						]
					);
				}
			}

			if ( ! empty( $plugin['css'] ) ) {
				$css = $plugin['css'];
				if ( ! wp_style_is( $css['handle'], 'registered' ) ) {
					$local_path = str_replace(
						[ get_template_directory_uri(), get_stylesheet_directory_uri() ],
						[ get_template_directory(), get_stylesheet_directory() ],
						$css['src']
					);
					if ( file_exists( $local_path ) ) {
						wp_register_style(
							$css['handle'],
							$css['src'],
							$css['deps'],
							$css['ver']
						);
					}
				}
			}
		}
	}

	/**
	 * Conditionally enqueue plugin assets based on block presence.
	 * Hooked to `wp_enqueue_scripts`.
	 *
	 * Checks the current post content for blocks that require each plugin.
	 * Only enqueues when at least one matching block is found.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$is_global_splide = ( (int) get_option( 'align_enable_splidejs', 0 ) === 1 ) && class_exists( 'Align_Custom_CSS_JS' );

		foreach ( self::getRegistry() as $plugin ) {
			$should_enqueue = ( ! $is_global_splide && ! empty( $plugin['always_enqueue'] ) ) || ( ! empty( $plugin['blocks'] ) && self::pageHasBlocks( $plugin['blocks'] ) );
			if ( ! $should_enqueue ) {
				continue;
			}

			// Enqueue CSS (load normally to prevent layout break/CLS)
			if ( ! empty( $plugin['css'] ) && wp_style_is( $plugin['css']['handle'], 'registered' ) ) {
				wp_enqueue_style( $plugin['css']['handle'] );
			}

			// Enqueue JS (lazy loaded)
			if ( ! empty( $plugin['js'] ) && wp_script_is( $plugin['js']['handle'], 'registered' ) ) {
				wp_enqueue_script( $plugin['js']['handle'] );
				AssetsJS::markAsOptimized( $plugin['js']['handle'] );
			}

			// Mark block-owned scripts for optimize (WP auto-enqueues them via block type)
			if ( ! empty( $plugin['block_scripts'] ) ) {
				foreach ( $plugin['block_scripts'] as $handle ) {
					AssetsJS::markAsOptimized( $handle );
				}
			}
		}
	}

	/**
	 * Check if the current page contains any of the specified blocks.
	 *
	 * Handles singular posts (including reusable blocks) and
	 * archive pages (checks all posts in the main query).
	 *
	 * @param array $blockNames List of block names to check for.
	 * @return bool
	 */
	private static function pageHasBlocks( array $blockNames ): bool {
		if ( empty( $blockNames ) ) {
			return false;
		}

		// Singular: check the queried post
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				return self::contentHasBlocks( $post->post_content, $blockNames );
			}
			return false;
		}

		// Archive / Home: check all posts in the main query
		global $wp_query;
		if ( ! empty( $wp_query->posts ) ) {
			foreach ( $wp_query->posts as $post ) {
				if ( self::contentHasBlocks( $post->post_content, $blockNames ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Deep-check if content contains any of the specified blocks.
	 * Handles reusable blocks (core/block) by resolving their inner content.
	 *
	 * @param string $content   Post content to scan.
	 * @param array  $blockNames Block names to look for.
	 * @return bool
	 */
	private static function contentHasBlocks( string $content, array $blockNames ): bool {
		// Quick bail: fast string check before expensive parsing
		$hasCandidate = false;
		foreach ( $blockNames as $name ) {
			if ( false !== strpos( $content, '<!-- wp:' . str_replace( '/', '/', $name ) ) ) {
				$hasCandidate = true;
				break;
			}
		}

		if ( ! $hasCandidate ) {
			// Also check for reusable blocks that might contain our target
			if ( false === strpos( $content, '<!-- wp:block' ) ) {
				return false;
			}
		}

		$blocks = parse_blocks( $content );
		return self::blocksContain( $blocks, $blockNames );
	}

	/**
	 * Recursively check parsed blocks for target block names.
	 * Resolves reusable blocks (core/block) by fetching their content.
	 *
	 * @param array $blocks     Parsed block array.
	 * @param array $blockNames Target block names.
	 * @return bool
	 */
	private static function blocksContain( array $blocks, array $blockNames ): bool {
		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'], $blockNames, true ) ) {
				return true;
			}

			// Resolve reusable blocks
			if ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
				$reusable = get_post( $block['attrs']['ref'] );
				if ( $reusable instanceof WP_Post ) {
					$innerBlocks = parse_blocks( $reusable->post_content );
					if ( self::blocksContain( $innerBlocks, $blockNames ) ) {
						return true;
					}
				}
			}

			// Check innerBlocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				if ( self::blocksContain( $block['innerBlocks'], $blockNames ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
