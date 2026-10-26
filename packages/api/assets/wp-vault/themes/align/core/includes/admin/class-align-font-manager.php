<?php
/**
 * Align Theme - Typography & Dynamic Slot Font Manager
 *
 * Manages standardized typography slots (font-primary-1, font-primary-2, ... font-primary-n).
 * Automatically generates unified @font-face rules, root CSS variables (--font-primary-1/2/n, --font-family-1/2/n),
 * clean preload tags in <head> or <footer> based on asset location, and coordinates font loading strategies.
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Font_Manager {

	/** Option key for font slots configuration. */
	const OPTION_KEY = 'align_font_slots';

	/** Option key for pre-compiled inline @font-face CSS in <head>. */
	const CSS_CACHE_KEY = 'align_font_slots_css';

	/** Option key for pre-compiled delayed @font-face CSS in <footer>. */
	const DELAY_CSS_CACHE_KEY = 'align_font_slots_delay_css';

	/** Standard weights supported per slot. */
	const SUPPORTED_WEIGHTS = array(
		'100' => 'Thin (100)',
		'200' => 'ExtraLight (200)',
		'300' => 'Light (300)',
		'400' => 'Regular (400)',
		'500' => 'Medium (500)',
		'600' => 'SemiBold (600)',
		'700' => 'Bold (700)',
		'800' => 'ExtraBold (800)',
		'900' => 'Black (900)',
	);

	/** Initialize hooks. */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output_preloads' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'output_font_css' ), 2 );
		add_action( 'wp_footer', array( __CLASS__, 'output_footer_preloads' ), 90 );
		add_action( 'wp_footer', array( __CLASS__, 'output_delayed_font_css' ), 95 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_font_css' ) );
		add_action( 'update_option_align_loaded_assets', array( __CLASS__, 'compile_from_loaded_assets' ), 10, 0 );
		add_action( 'add_option_align_loaded_assets', array( __CLASS__, 'compile_from_loaded_assets' ), 10, 0 );
	}

	/** Get default slot definitions (empty files array; files are loaded from Asset Loader). */
	public static function get_default_slots() {
		return array(
			'font_1' => array( 'label' => 'Font 1', 'variable' => '--font-primary-1', 'name' => 'font-primary-1', 'original_name' => 'Barlow', 'fallback' => 'sans-serif', 'loading' => 'preload', 'files' => array() ),
			'font_2' => array( 'label' => 'Font 2', 'variable' => '--font-primary-2', 'name' => 'font-primary-2', 'original_name' => '', 'fallback' => 'sans-serif', 'loading' => 'preload', 'files' => array() ),
			'font_3' => array( 'label' => 'Font 3', 'variable' => '--font-primary-3', 'name' => 'font-primary-3', 'original_name' => '', 'fallback' => 'sans-serif', 'loading' => 'normal', 'files' => array() ),
		);
	}

	/** Retrieve active slot configuration with fallback to defaults or auto-compilation. */
	public static function get_slots() {
		$saved = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $saved ) || empty( $saved ) ) {
			$compiled = self::compile_from_loaded_assets();
			if ( ! empty( $compiled ) ) {
				return $compiled;
			}
			return self::get_default_slots();
		}

		$normalized = array();
		$slot_idx   = 1;

		foreach ( $saved as $key => $slot ) {
			if ( ! is_array( $slot ) ) {
				continue;
			}
			$slot_num = $slot_idx;
			if ( preg_match( '/^font_(\d+)$/', (string) $key, $m ) ) {
				$slot_num = (int) $m[1];
			}
			$slot_key = 'font_' . $slot_num;

			$default_name  = 'font-primary-' . $slot_num;
			$name          = ! empty( $slot['name'] ) ? $slot['name'] : $default_name;
			$original_name = ! empty( $slot['original_name'] ) ? $slot['original_name'] : '';

			if ( empty( $original_name ) && ! preg_match( '/^font-primary-\d+$/', $name ) ) {
				$original_name = $name;
				$name          = $default_name;
			}

			$slot_files = array();
			if ( isset( $slot['files'] ) && is_array( $slot['files'] ) ) {
				foreach ( $slot['files'] as $f ) {
					if ( is_array( $f ) && ! empty( $f['url'] ) ) {
						$f['location'] = isset( $f['location'] ) && 'footer' === strtolower( trim( $f['location'] ) ) ? 'footer' : 'header';
						$slot_files[]  = $f;
					}
				}
			}

			$normalized[ $slot_key ] = array(
				'label'         => 'Font ' . $slot_num,
				'variable'      => '--font-primary-' . $slot_num,
				'name'          => $name,
				'original_name' => $original_name,
				'fallback'      => ! empty( $slot['fallback'] ) ? $slot['fallback'] : 'sans-serif',
				'loading'       => ! empty( $slot['loading'] ) ? $slot['loading'] : ( $slot_num <= 2 ? 'preload' : 'normal' ),
				'files'         => $slot_files,
			);

			$slot_idx++;
		}

		return ! empty( $normalized ) ? $normalized : self::get_default_slots();
	}

	/** Sanitize submitted font slots and pre-compile CSS caches. */
	public static function sanitize_and_compile_slots( $input ) {
		if ( ! is_array( $input ) || empty( $input ) ) {
			return get_option( self::OPTION_KEY, self::get_default_slots() );
		}

		$clean    = array();
		$slot_idx = 1;

		foreach ( $input as $key => $raw_slot ) {
			if ( ! is_array( $raw_slot ) ) {
				continue;
			}

			$slot_num = $slot_idx;
			if ( preg_match( '/^font_(\d+)$/', (string) $key, $m ) ) {
				$slot_num = (int) $m[1];
			}
			$slot_key = 'font_' . $slot_num;

			$default_name  = 'font-primary-' . $slot_num;
			$slot_name     = ! empty( $raw_slot['name'] ) ? sanitize_text_field( trim( $raw_slot['name'] ) ) : $default_name;
			$original_name = ! empty( $raw_slot['original_name'] ) ? sanitize_text_field( trim( $raw_slot['original_name'] ) ) : '';
			$slot_fallback = ! empty( $raw_slot['fallback'] ) ? sanitize_text_field( trim( $raw_slot['fallback'] ) ) : 'sans-serif';
			$slot_loading  = isset( $raw_slot['loading'] ) && in_array( strtolower( $raw_slot['loading'] ), array( 'preload', 'normal', 'delay' ), true )
				? strtolower( $raw_slot['loading'] )
				: ( $slot_num <= 2 ? 'preload' : 'normal' );

			$clean_files = array();
			if ( isset( $raw_slot['files'] ) && is_array( $raw_slot['files'] ) ) {
				foreach ( $raw_slot['files'] as $file ) {
					if ( ! is_array( $file ) || empty( $file['url'] ) ) continue;
					$raw_url = sanitize_text_field( trim( $file['url'] ) );
					if ( empty( $raw_url ) ) continue;
					$weight = isset( $file['weight'] ) && isset( self::SUPPORTED_WEIGHTS[ $file['weight'] ] ) ? (string) $file['weight'] : '400';
					$style  = isset( $file['style'] ) && in_array( strtolower( $file['style'] ), array( 'normal', 'italic' ), true ) ? strtolower( $file['style'] ) : 'normal';
					$f_loc  = isset( $file['location'] ) && 'footer' === strtolower( trim( $file['location'] ) ) ? 'footer' : 'header';
					$clean_files[] = array( 'weight' => $weight, 'style' => $style, 'url' => $raw_url, 'location' => $f_loc );
				}
			}

			$clean[ $slot_key ] = array(
				'label'         => 'Font ' . $slot_num,
				'variable'      => '--font-primary-' . $slot_num,
				'name'          => $slot_name,
				'original_name' => $original_name,
				'fallback'      => $slot_fallback,
				'loading'       => $slot_loading,
				'files'         => $clean_files,
			);
			$slot_idx++;
		}

		if ( empty( $clean ) ) {
			$clean = self::get_default_slots();
		}

		self::compile_and_save_css( $clean );
		error_log( '[Align FontManager] Saved and pre-compiled ' . count( $clean ) . ' font slots successfully' );

		return $clean;
	}

	/** Compile @font-face rules and CSS variables into cache options. */
	public static function compile_and_save_css( array $slots ) {
		$head_css  = '';
		$delay_css = '';
		$variables = '';

		$format_map = array(
			'woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype',
			'otf' => 'opentype', 'eot' => 'embedded-opentype',
		);

		$slot_index = 1;
		foreach ( $slots as $key => $slot ) {
			$slot_num = $slot_index;
			if ( preg_match( '/^font_(\d+)$/', $key, $m ) ) {
				$slot_num = (int) $m[1];
			}
			$slot_index++;

			$name          = ! empty( $slot['name'] ) ? $slot['name'] : ( 'font-primary-' . $slot_num );
			$original_name = ! empty( $slot['original_name'] ) ? $slot['original_name'] : '';
			$fallback      = ! empty( $slot['fallback'] ) ? $slot['fallback'] : 'sans-serif';
			$loading       = ! empty( $slot['loading'] ) ? $slot['loading'] : 'preload';

			$var_primary = '--font-primary-' . $slot_num;
			$var_family  = '--font-family-' . $slot_num;

			$variables .= "  {$var_primary}: '{$name}', {$fallback};\n";
			$variables .= "  {$var_family}: var({$var_primary});\n";

			if ( empty( $slot['files'] ) || ! is_array( $slot['files'] ) ) {
				continue;
			}

			$families_to_declare = array( $name );
			if ( ! empty( $original_name ) && $original_name !== $name ) {
				$families_to_declare[] = $original_name;
			}

			foreach ( $slot['files'] as $file ) {
				if ( empty( $file['url'] ) ) {
					continue;
				}
				$url = self::resolve_font_url( $file['url'] );
				if ( empty( $url ) ) {
					continue;
				}
				$ext    = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
				$format = isset( $format_map[ $ext ] ) ? " format('{$format_map[ $ext ]}')" : '';
				$weight = ! empty( $file['weight'] ) ? $file['weight'] : '400';
				$style  = ! empty( $file['style'] ) ? $file['style'] : 'normal';

				$file_css = '';
				foreach ( $families_to_declare as $font_fam ) {
					$file_css .= "@font-face {\n";
					$file_css .= "  font-family: '{$font_fam}';\n";
					$file_css .= "  src: url('{$url}'){$format};\n";
					$file_css .= "  font-weight: {$weight};\n";
					$file_css .= "  font-style: {$style};\n";
					$file_css .= "  font-display: swap;\n";
					$file_css .= "}\n";
				}

				$file_loc = isset( $file['location'] ) ? strtolower( trim( $file['location'] ) ) : 'header';
				if ( 'footer' === $file_loc || 'delay' === $loading ) {
					$delay_css .= $file_css;
				} else {
					$head_css .= $file_css;
				}
			}
		}

		if ( ! empty( $variables ) ) {
			$global_base = "html, body, h1, h2, h3, h4, h5, h6, .heading-text {\n  font-family: var(--font-primary-1, 'Barlow', sans-serif);\n}\n";
			$head_css    = ":root {\n" . $variables . "  --bs-body-font-family: var(--font-primary-1, 'Barlow', sans-serif);\n}\n" . $global_base . $head_css;
		}

		update_option( self::CSS_CACHE_KEY, trim( $head_css ), true );
		update_option( self::DELAY_CSS_CACHE_KEY, trim( $delay_css ), true );
	}

	/** Output top-priority font preload links in <head> (excludes footer fonts). */
	public static function output_preloads() {
		$slots      = self::get_slots();
		$mime_types = array(
			'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf',
			'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
		);

		foreach ( $slots as $slot ) {
			if ( 'preload' !== $slot['loading'] || empty( $slot['files'] ) ) {
				continue;
			}
			foreach ( $slot['files'] as $file ) {
				$file_loc = isset( $file['location'] ) ? strtolower( trim( $file['location'] ) ) : 'header';
				if ( 'footer' === $file_loc ) {
					continue;
				}
				$url = self::resolve_font_url( $file['url'] );
				if ( empty( $url ) ) {
					continue;
				}
				$ext       = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
				$mime_attr = isset( $mime_types[ $ext ] ) ? ' type="' . $mime_types[ $ext ] . '"' : '';
				echo '<link rel="preload" href="' . esc_url( $url ) . '" as="font"' . $mime_attr . ' crossorigin>' . "\n";
			}
		}
	}

	/** Output footer font preload links in <footer> (priority 90). */
	public static function output_footer_preloads() {
		$slots      = self::get_slots();
		$mime_types = array(
			'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf',
			'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
		);

		foreach ( $slots as $slot ) {
			if ( empty( $slot['files'] ) ) {
				continue;
			}
			foreach ( $slot['files'] as $file ) {
				$file_loc = isset( $file['location'] ) ? strtolower( trim( $file['location'] ) ) : 'header';
				if ( 'footer' !== $file_loc ) {
					continue;
				}
				$url = self::resolve_font_url( $file['url'] );
				if ( empty( $url ) ) {
					continue;
				}
				$ext       = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
				$mime_attr = isset( $mime_types[ $ext ] ) ? ' type="' . $mime_types[ $ext ] . '"' : '';
				echo '<link rel="preload" href="' . esc_url( $url ) . '" as="font"' . $mime_attr . ' crossorigin>' . "\n";
			}
		}
	}

	/** Output compiled @font-face inline CSS in <head>. */
	public static function output_font_css() {
		$css = get_option( self::CSS_CACHE_KEY, '' );
		if ( empty( $css ) ) {
			self::compile_from_loaded_assets();
			$css = get_option( self::CSS_CACHE_KEY, '' );
			if ( empty( $css ) ) {
				$slots = self::get_slots();
				self::compile_and_save_css( $slots );
				$css = get_option( self::CSS_CACHE_KEY, '' );
			}
		}
		if ( ! empty( $css ) ) {
			echo "\n<style id=\"align-fonts-css\">\n" . $css . "\n</style>\n";
		}
	}

	/** Output delayed font CSS in footer or deferred container. */
	public static function output_delayed_font_css() {
		$delay_css = get_option( self::DELAY_CSS_CACHE_KEY, '' );
		if ( ! empty( $delay_css ) ) {
			echo "\n<style id=\"align-fonts-delayed-css\">\n" . $delay_css . "\n</style>\n";
		}
	}

	/** Enqueue compiled font CSS into Gutenberg block editor. */
	public static function enqueue_editor_font_css() {
		$css       = get_option( self::CSS_CACHE_KEY, '' );
		$delay_css = get_option( self::DELAY_CSS_CACHE_KEY, '' );
		$full_css  = trim( $css . "\n" . $delay_css );

		if ( ! empty( $full_css ) ) {
			wp_add_inline_style( 'wp-block-editor', $full_css );
			wp_add_inline_style( 'wp-edit-blocks', $full_css );
		}
	}

	/** Compile @font-face rules and CSS variables from Asset Loader's loaded assets. */
	public static function compile_from_loaded_assets( array $assets = array() ) {
		if ( empty( $assets ) ) {
			$assets = get_option( 'align_loaded_assets', array() );
		}
		if ( ! is_array( $assets ) || empty( $assets ) ) {
			return array();
		}

		$font_assets = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) || empty( $asset['url'] ) || empty( $asset['enabled'] ) ) {
				continue;
			}
			$type = isset( $asset['type'] ) ? strtolower( trim( $asset['type'] ) ) : '';
			if ( 'font' === $type ) {
				$font_assets[] = $asset;
			}
		}

		if ( empty( $font_assets ) ) {
			update_option( self::OPTION_KEY, array(), true );
			update_option( self::CSS_CACHE_KEY, '', true );
			update_option( self::DELAY_CSS_CACHE_KEY, '', true );
			return array();
		}

		$families = array();
		foreach ( $font_assets as $asset ) {
			$url       = $asset['url'];
			$clean_url = strtok( $url, '?#' );
			$raw_name  = function_exists( 'wp_basename' ) ? wp_basename( $clean_url ) : basename( $clean_url );
			$base_fam  = self::detect_family_from_filename( $raw_name );
			$weight    = ! empty( $asset['font_weight'] ) ? (string) $asset['font_weight'] : self::detect_weight_from_filename( $raw_name );
			$style     = ( false !== stripos( $raw_name, 'italic' ) || false !== stripos( $raw_name, 'oblique' ) ) ? 'italic' : 'normal';
			$location  = isset( $asset['location'] ) && 'footer' === strtolower( trim( $asset['location'] ) ) ? 'footer' : 'header';

			if ( ! isset( $families[ $base_fam ] ) ) {
				$families[ $base_fam ] = array(
					'original_name' => $base_fam,
					'files'         => array(),
				);
			}

			$families[ $base_fam ]['files'][] = array(
				'url'      => $url,
				'weight'   => $weight,
				'style'    => $style,
				'location' => $location,
			);
		}

		$slots    = array();
		$slot_idx = 1;
		foreach ( $families as $fam_name => $data ) {
			$slot_key           = 'font_' . $slot_idx;
			$slots[ $slot_key ] = array(
				'label'         => 'Font ' . $slot_idx,
				'variable'      => '--font-primary-' . $slot_idx,
				'name'          => 'font-primary-' . $slot_idx,
				'original_name' => $fam_name,
				'fallback'      => 'sans-serif',
				'loading'       => 'preload',
				'files'         => $data['files'],
			);
			$slot_idx++;
		}

		update_option( self::OPTION_KEY, $slots, true );
		self::compile_and_save_css( $slots );
		error_log( '[Align FontManager] Auto-compiled ' . count( $slots ) . ' font slots from Asset Loader successfully' );
		return $slots;
	}

	/** Detect base family name from filename (e.g. 'Barlow-Bold.woff' -> 'Barlow'). */
	public static function detect_family_from_filename( $filename ) {
		$base    = function_exists( 'wp_basename' ) ? wp_basename( $filename ) : basename( $filename );
		$clean   = preg_replace( '/\.(woff2|woff|ttf|otf|eot)$/i', '', $base );
		$clean   = preg_replace( '/[-_](Thin|ExtraLight|Light|Regular|Medium|SemiBold|Bold|ExtraBold|Black|Heavy|Italic|Normal|[0-9]{3})/i', '', $clean );
		$trimmed = trim( $clean, " -_" );
		return ! empty( $trimmed ) ? $trimmed : 'FontFamily';
	}

	/** Detect weight from filename string. */
	public static function detect_weight_from_filename( $filename ) {
		$map = array(
			'900' => array( 'black', 'heavy', '900' ), '800' => array( 'extrabold', 'extra-bold', 'ultrabold', '800' ),
			'600' => array( 'semibold', 'semi-bold', 'demibold', 'demi-bold', '600' ), '700' => array( 'bold', '700' ),
			'200' => array( 'extralight', 'extra-light', 'ultralight', '200' ), '100' => array( 'thin', '100' ),
			'300' => array( 'light', '300' ), '500' => array( 'medium', '500' ),
		);
		$lower = strtolower( $filename );
		foreach ( $map as $w => $keywords ) {
			foreach ( $keywords as $kw ) {
				if ( false !== strpos( $lower, $kw ) ) return $w;
			}
		}
		return '400';
	}

	/** Helper to resolve font URL cleanly without query parameters. */
	public static function resolve_font_url( $raw_url ) {
		if ( empty( $raw_url ) ) return '';
		if ( class_exists( 'Align_Custom_CSS_JS' ) && method_exists( 'Align_Custom_CSS_JS', 'resolve_asset_url' ) ) {
			return esc_url_raw( Align_Custom_CSS_JS::resolve_asset_url( $raw_url, false ) );
		}
		$trimmed = trim( $raw_url );
		if ( 0 === strpos( $trimmed, '//' ) || preg_match( '#^https?://#i', $trimmed ) ) return esc_url_raw( $trimmed );
		return esc_url_raw( site_url( '/' . ltrim( $trimmed, '/' ) ) );
	}
}
