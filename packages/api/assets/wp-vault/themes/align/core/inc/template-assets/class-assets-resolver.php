<?php
/**
 * Assets Loader Directory Scanning and Name Resolver
 *
 * @package Align
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class AssetsResolver {
	/**
	 * Retrieve active page/post template base name.
	 *
	 * @return string Template base name (e.g. 'template-01').
	 */
	public static function getCurrentTemplateName(): string {
		$templateName = '';
		if ( is_admin() ) {
			$postId = 0;
			if ( isset( $_GET['post'] ) ) {
				$postId = (int) $_GET['post'];
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$postId = (int) $_POST['post_ID'];
			}
			if ( $postId > 0 ) {
				$slug = get_page_template_slug( $postId );
				if ( $slug ) {
					$templateName = pathinfo( $slug, PATHINFO_FILENAME );
				} else {
					$post_type = get_post_type( $postId );
					if ( 'page' === $post_type ) {
						$templateName = 'page';
					} elseif ( 'post' === $post_type ) {
						$templateName = 'single';
					}
				}
			}
		} else {
			if ( is_singular() ) {
				$slug = get_page_template_slug();
				if ( $slug ) {
					$templateName = pathinfo( $slug, PATHINFO_FILENAME );
				} else {
					$post_type = get_post_type();
					if ( 'page' === $post_type ) {
						$templateName = 'page';
					} elseif ( 'post' === $post_type ) {
						$templateName = 'single';
					}
				}
			} elseif ( is_404() ) {
				$templateName = '404';
			} elseif ( is_search() ) {
				$templateName = 'search';
			} elseif ( is_archive() || is_category() || is_tag() || is_author() || is_date() || is_tax() || is_post_type_archive() ) {
				$templateName = 'archive';
			} elseif ( is_front_page() || is_home() ) {
				$templateName = 'index';
			}
		}
		if ( empty( $templateName ) ) {
			global $template;
			if ( ! empty( $template ) ) {
				$templateName = pathinfo( $template, PATHINFO_FILENAME );
			}
		}
		// Clean FSE custom templates prefix
		if ( strpos( $templateName, 'wp-custom-template-' ) === 0 ) {
			$templateName = substr( $templateName, strlen( 'wp-custom-template-' ) );
		}
		return sanitize_key( $templateName );
	}
	/**
	 * Map template name to page name.
	 *
	 * @param string $templateName
	 * @return string
	 */
	public static function getPageNameFromTemplate( string $templateName ): string {
		$mapping = [
			'page'                           => 'default',
			'template-homepage'              => 'homepage',
			'template-apc'                   => 'apc',
			'template-cad-avl'               => 'cad',
			'template-solutions'             => 'solutions',
			'template-transitgpt'            => 'transitgpt',
			'template-who-we-serve'          => 'who-we-serve',
			'template-passenger-information' => 'passenger',
			'template-press-release'         => 'case-study',
			'template-podcast'               => 'case-study',
		];
		if ( isset( $mapping[ $templateName ] ) ) {
			return $mapping[ $templateName ];
		}
		// Fallback: strip 'template-' prefix
		if ( strpos( $templateName, 'template-' ) === 0 ) {
			return substr( $templateName, strlen( 'template-' ) );
		}
		return $templateName;
	}
	/**
	 * Check if minified assets should be bypassed (for logged-in users / admins for debugging).
	 *
	 * @return bool True if logged in / admin bar showing, false for guest visitors.
	 */
	public static function shouldSkipMinified(): bool {
		if ( is_user_logged_in() || is_admin_bar_showing() ) {
			return true;
		}
		if ( class_exists( 'AssetsConfig' ) && AssetsConfig::shouldSkipOptimize() ) {
			return true;
		}
		return false;
	}

	/**
	 * Resolve asset directory path in child theme.
	 * Admin / Logged-in: Priority assets/src/ -> assets/ -> assets/dist/ (Raw unminified for debugging)
	 * Guest / Visitor: Priority assets/dist/ -> assets/src/ -> assets/ (Minified production)
	 *
	 * @param string $subPath Relative subpath (e.g. 'general/css' or 'template/404/css')
	 * @return array Tuple of [ absolute_path, relative_path_under_child_theme ]
	 */
	public static function resolveAssetSubdir( string $subPath ): array {
		$themeDir = get_stylesheet_directory();
		$trimmed  = ltrim( $subPath, '/' );

		$distRel   = 'assets/dist/' . $trimmed;
		$srcRel    = 'assets/src/' . $trimmed;
		$legacyRel = 'assets/' . $trimmed;

		$distPath   = $themeDir . '/' . $distRel;
		$srcPath    = $themeDir . '/' . $srcRel;
		$legacyPath = $themeDir . '/' . $legacyRel;

		$hasDist   = is_dir( $distPath ) && count( array_diff( scandir( $distPath ) ?: [], [ '.', '..' ] ) ) > 0;
		$hasSrc    = is_dir( $srcPath ) && count( array_diff( scandir( $srcPath ) ?: [], [ '.', '..' ] ) ) > 0;
		$hasLegacy = is_dir( $legacyPath ) && count( array_diff( scandir( $legacyPath ) ?: [], [ '.', '..' ] ) ) > 0;

		if ( self::shouldSkipMinified() ) {
			// Admin / Logged-in: Prefer SRC (Raw unminified)
			if ( $hasSrc ) {
				return [ $srcPath, $srcRel ];
			}
			if ( $hasLegacy ) {
				return [ $legacyPath, $legacyRel ];
			}
			if ( $hasDist ) {
				return [ $distPath, $distRel ];
			}
		} else {
			// Guest / Visitor: Prefer DIST (Minified)
			if ( $hasDist ) {
				return [ $distPath, $distRel ];
			}
			if ( $hasSrc ) {
				return [ $srcPath, $srcRel ];
			}
			if ( $hasLegacy ) {
				return [ $legacyPath, $legacyRel ];
			}
		}

		return [ $distPath, $distRel ];
	}

	/**
	 * Get relative file paths for all template-specific CSS assets.
	 *
	 * @return array List of relative CSS file paths.
	 */
	public static function getAllPageCssPaths(): array {
		$paths = [];
		$themeDir = get_stylesheet_directory();

		list( $templateDir, $baseRel ) = self::resolveAssetSubdir( 'template' );

		if ( is_dir( $templateDir ) ) {
			$subdirs = scandir( $templateDir );
			if ( $subdirs ) {
				foreach ( $subdirs as $subdir ) {
					if ( '.' === $subdir || '..' === $subdir ) {
						continue;
					}
					// Look for important.css (or important.min.css)
					$filePathMin = $templateDir . '/' . $subdir . '/css/important.min.css';
					$filePathRaw = $templateDir . '/' . $subdir . '/css/important.css';

					if ( file_exists( $filePathRaw ) && filesize( $filePathRaw ) > 0 ) {
						$paths[] = $baseRel . '/' . $subdir . '/css/important.css';
					} elseif ( file_exists( $filePathMin ) && filesize( $filePathMin ) > 0 ) {
						$paths[] = $baseRel . '/' . $subdir . '/css/important.min.css';
					}
				}
			}
		}
		return $paths;
	}

	/**
	 * Check if a CSS filename should be enqueued externally (strictly Bootstrap Reboot or Utilities)
	 * rather than inlined in the head, enabling browser reuse and server zip/gzip compression.
	 *
	 * @param string $filename
	 * @return bool
	 */
	public static function isExternalCssFile( string $filename ): bool {
		$basename = strtolower( basename( $filename ) );
		return (
			strpos( $basename, 'reboot' ) !== false ||
			strpos( $basename, 'utility' ) !== false ||
			strpos( $basename, 'utilities' ) !== false
		);
	}

	/**
	 * Scan assets/general directory and collect global CSS/JS files.
	 *
	 * Categorizes CSS assets into 'inline' (critical head style tag) and 'enqueue'
	 * (external stylesheet link tags strictly for heavy reusable framework assets like reboot and utilities).
	 *
	 * @return array
	 */
	public static function getGeneralAssets(): array {
		$assets = [
			'css' => [
				'inline'  => [],
				'enqueue' => [],
			],
			'js'  => [
				'inline'  => [],
				'enqueue' => [],
			],
		];

		list( $generalCssDir, $relGeneralCssDir ) = self::resolveAssetSubdir( 'general/css' );
		list( $generalJsDir, $relGeneralJsDir )   = self::resolveAssetSubdir( 'general/js' );
		$themeUrl = get_stylesheet_directory_uri();

		if ( is_dir( $generalCssDir ) ) {
			$cssFiles = self::scanDirectory( $generalCssDir, 'css' );
			foreach ( $cssFiles as $file ) {
				$filePath  = $generalCssDir . '/' . $file;
				$cleanName = str_replace( '/', '-', preg_replace( '/\.(min\.)?css$/i', '', $file ) );
				$handle    = "align-general-{$cleanName}";
				$fileUrl   = $themeUrl . '/' . $relGeneralCssDir . '/' . $file;
				$ver       = file_exists( $filePath ) ? filemtime( $filePath ) : false;

				if ( self::isExternalCssFile( $file ) ) {
					$assets['css']['enqueue'][] = [
						'handle' => $handle,
						'src'    => $fileUrl,
						'ver'    => $ver,
					];
				} else {
					$content = file_exists( $filePath ) ? file_get_contents( $filePath ) : '';
					if ( ! empty( $content ) ) {
						$assets['css']['inline'][] = [
							'handle'  => $handle,
							'content' => $content,
						];
					}
				}
			}

			// Ensure reboot is enqueued before utilities for correct CSS cascade
			if ( ! empty( $assets['css']['enqueue'] ) ) {
				usort( $assets['css']['enqueue'], function( $a, $b ) {
					$aIsReboot = strpos( strtolower( $a['handle'] ), 'reboot' ) !== false;
					$bIsReboot = strpos( strtolower( $b['handle'] ), 'reboot' ) !== false;
					if ( $aIsReboot && ! $bIsReboot ) {
						return -1;
					}
					if ( ! $aIsReboot && $bIsReboot ) {
						return 1;
					}
					return 0;
				} );
			}
		}

		if ( is_dir( $generalJsDir ) ) {
			$jsFiles = self::scanDirectory( $generalJsDir, 'js' );
			foreach ( $jsFiles as $file ) {
				$filePath  = $generalJsDir . '/' . $file;
				$cleanName = str_replace( '/', '-', preg_replace( '/\.(min\.)?js$/i', '', $file ) );
				$handle    = "align-general-{$cleanName}";
				$fileUrl   = $themeUrl . '/' . $relGeneralJsDir . '/' . $file;
				$ver       = file_exists( $filePath ) ? filemtime( $filePath ) : false;
				$content   = file_exists( $filePath ) ? file_get_contents( $filePath ) : '';

				if ( ! empty( $content ) ) {
					$assets['js']['inline'][] = [
						'handle'  => $handle,
						'content' => $content,
					];
				}
			}
		}
		return $assets;
	}

	/**
	 * Scan template subdirectory directories and collect assets metadata.
	 *
	 * @param string $templateName Active template name.
	 * @return array Multi-dimensional array containing enqueued/inline styles and footer scripts.
	 */
	public static function getTemplateAssets( string $templateName ): array {
		// Clear transient asset cache when resolving assets if cache exists
		$cacheKey = class_exists( 'AssetsConfig' ) ? AssetsConfig::CACHE_PREFIX . $templateName : 'align_template_assets_' . $templateName;
		if ( false !== get_transient( $cacheKey ) ) {
			delete_transient( $cacheKey );
		}

		$assets = [
			'css' => [
				'enqueue' => [],
				'inline'  => [],
			],
			'js'  => [
				'enqueue' => [],
				'inline'  => [],
			],
		];

		$themeUrl = get_stylesheet_directory_uri();

		list( $cssPath, $relCssPath ) = self::resolveAssetSubdir( "template/{$templateName}/css" );
		list( $jsPath, $relJsPath )   = self::resolveAssetSubdir( "template/{$templateName}/js" );

		// Handle CSS files in template directory
		if ( is_dir( $cssPath ) ) {
			$cssFiles = self::scanDirectory( $cssPath, 'css' );
			foreach ( $cssFiles as $file ) {
				$cleanName = str_replace( '/', '-', preg_replace( '/\.(min\.)?css$/i', '', $file ) );
				$filePath  = $cssPath . '/' . $file;
				$fileUrl   = $themeUrl . '/' . $relCssPath . '/' . $file;
				$ver       = file_exists( $filePath ) ? filemtime( $filePath ) : false;
				$handle    = "align-template-{$templateName}-{$cleanName}";

				if ( strpos( $cleanName, 'important' ) !== false && strpos( $cleanName, 'non-important' ) === false ) {
					$content = file_exists( $filePath ) ? file_get_contents( $filePath ) : '';
					$assets['css']['inline'][] = [
						'handle'  => $handle,
						'content' => $content,
					];
				} else {
					$content      = file_exists( $filePath ) ? file_get_contents( $filePath ) : '';
					$cleanContent = trim( preg_replace( '!/\*[\s\S]*?\*/!', '', $content ) );

					// Skip adding to enqueue if file contains no actual CSS rules
					if ( ! empty( $cleanContent ) ) {
						$assets['css']['enqueue'][] = [
							'handle' => $handle,
							'src'    => $fileUrl,
							'ver'    => $ver,
						];
					}
				}
			}
		}

		// Handle JS files in template directory
		if ( is_dir( $jsPath ) ) {
			$jsFiles = self::scanDirectory( $jsPath, 'js' );
			foreach ( $jsFiles as $file ) {
				$cleanName = str_replace( '/', '-', preg_replace( '/\.(min\.)?js$/i', '', $file ) );
				$filePath  = $jsPath . '/' . $file;
				$fileUrl   = $themeUrl . '/' . $relJsPath . '/' . $file;
				$ver       = file_exists( $filePath ) ? filemtime( $filePath ) : false;
				$handle    = "align-template-{$templateName}-{$cleanName}";

				if ( strpos( $cleanName, 'important' ) !== false && strpos( $cleanName, 'non-important' ) === false ) {
					$content = file_exists( $filePath ) ? file_get_contents( $filePath ) : '';
					$assets['js']['inline'][] = [
						'handle'  => $handle,
						'content' => $content,
					];
				} else {
					$strategy = 'defer';
					if ( strpos( $cleanName, 'async' ) !== false ) {
						$strategy = 'async';
					}
					$assets['js']['enqueue'][] = [
						'handle'   => $handle,
						'src'      => $fileUrl,
						'ver'      => $ver,
						'strategy' => $strategy,
					];
				}
			}
		}
		return $assets;
	}

	/**
	 * Get relative file paths for general CSS assets.
	 *
	 * @return array List of relative CSS file paths.
	 */
	public static function getGeneralCssPaths(): array {
		$paths = [];
		list( $generalCssDir, $relGeneralCssDir ) = self::resolveAssetSubdir( 'general/css' );

		if ( is_dir( $generalCssDir ) ) {
			$cssFiles = self::scanDirectory( $generalCssDir, 'css' );
			foreach ( $cssFiles as $file ) {
				$paths[] = $relGeneralCssDir . '/' . $file;
			}
		}
		return $paths;
	}

	/**
	 * Get relative file paths for template-specific CSS assets.
	 *
	 * @param string $templateName Active template name.
	 * @return array List of relative CSS file paths.
	 */
	public static function getTemplateCssPaths( string $templateName ): array {
		$paths = [];
		list( $cssPath, $relCssPath ) = self::resolveAssetSubdir( "template/{$templateName}/css" );

		if ( is_dir( $cssPath ) ) {
			$cssFiles = self::scanDirectory( $cssPath, 'css' );
			foreach ( $cssFiles as $file ) {
				$paths[] = $relCssPath . '/' . $file;
			}
		}
		return $paths;
	}

	/**
	 * Scan directory files recursively by extension.
	 *
	 * @param string $dirPath Root path of directory to scan.
	 * @param string $extension Target extension file filter.
	 * @param string $baseSubDir Relative subfolder offset for recursion.
	 * @return array List of relative file paths.
	 */
	public static function scanDirectory( string $dirPath, string $extension, string $baseSubDir = '' ): array {
		$files       = [];
		$currentPath = '' !== $baseSubDir ? $dirPath . '/' . $baseSubDir : $dirPath;

		if ( ! is_dir( $currentPath ) ) {
			return $files;
		}

		$dir = scandir( $currentPath );
		if ( false === $dir ) {
			return $files;
		}

		foreach ( $dir as $file ) {
			if ( '.' === $file || '..' === $file || strpos( $file, '.' ) === 0 ) {
				continue;
			}

			$fullFilePath = $currentPath . '/' . $file;
			$relFilePath  = '' !== $baseSubDir ? $baseSubDir . '/' . $file : $file;

			if ( is_dir( $fullFilePath ) ) {
				$subFiles = self::scanDirectory( $dirPath, $extension, $relFilePath );
				$files    = array_merge( $files, $subFiles );
			} elseif ( pathinfo( $file, PATHINFO_EXTENSION ) === $extension ) {
				$files[] = $relFilePath;
			}
		}
		return $files;
	}
}
