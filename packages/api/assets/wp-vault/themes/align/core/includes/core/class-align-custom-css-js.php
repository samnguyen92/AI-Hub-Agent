<?php
/**
 * Align Custom CSS/JS Frontend Output Engine
 *
 * Handles rendering user custom CSS in <head> and custom JS in <footer>
 * strictly when non-empty content exists to avoid redundant tags.
 * Also handles loading custom asset files (CSS/JS) in <head> or <footer>.
 *
 * @package Align
 */

if (!defined('ABSPATH')) {
    exit;
}

class Align_Custom_CSS_JS
{
    /**
     * Initialize frontend hooks & persistence listeners.
     */
    public static function init()
    {
        add_action('wp_head', [__CLASS__, 'output_font_preloads_top'], 1);
        add_action('wp_head', [__CLASS__, 'output_loaded_assets_header'], 90);
        add_action('wp_head', [__CLASS__, 'output_tracking_code'], 1);
        add_action('wp_head', [__CLASS__, 'output_custom_css'], 99);
        add_action('wp_footer', [__CLASS__, 'output_loaded_assets_footer'], 90);
        add_action('wp_footer', [__CLASS__, 'output_custom_js'], 99);

        // Pre-minify custom CSS/JS once upon save in settings -> zero API calls during frontend visits
        add_action('update_option_align_custom_css', [__CLASS__, 'on_save_custom_css'], 10, 2);
        add_action('add_option_align_custom_css', [__CLASS__, 'on_add_custom_css'], 10, 2);
        add_action('update_option_align_custom_js', [__CLASS__, 'on_save_custom_js'], 10, 2);
        add_action('add_option_align_custom_js', [__CLASS__, 'on_add_custom_js'], 10, 2);
    }

    public static function on_save_custom_css($old_value, $new_value): void
    {
        if (is_string($new_value)) update_option('align_custom_css_minified', self::minify_css($new_value), true);
    }

    public static function on_add_custom_css($option, $value): void
    {
        if (is_string($value)) update_option('align_custom_css_minified', self::minify_css($value), true);
    }

    public static function on_save_custom_js($old_value, $new_value): void
    {
        if (is_string($new_value)) update_option('align_custom_js_minified', self::minify_js($new_value), true);
    }

    public static function on_add_custom_js($option, $value): void
    {
        if (is_string($value)) update_option('align_custom_js_minified', self::minify_js($value), true);
    }

    /**
     * Check if custom output should be suppressed.
     *
     * @return bool
     */
    private static function should_skip_output(): bool
    {
        return (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST));
    }

    /**
     * Check if an asset entry is enabled.
     */
    public static function is_asset_enabled(array $asset): bool
    {
        $raw = $asset['enabled'] ?? false;
        return is_bool($raw) ? $raw : (is_numeric($raw) ? 1 === (int) $raw : in_array(strtolower(trim((string) $raw)), ['1', 'true', 'on'], true));
    }

    /**
     * Output font preload links at top of <head> (priority 1).
     */
    public static function output_font_preloads_top()
    {
        if (self::should_skip_output() || class_exists('Align_Font_Manager')) {
            return;
        }

        $assets = get_option('align_loaded_assets', []);
        if (!is_array($assets) || empty($assets)) {
            return;
        }

        $mime_types = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject'];

        foreach ($assets as $asset) {
            if (!is_array($asset) || !self::is_asset_enabled($asset)) {
                continue;
            }
            $location = isset($asset['location']) && is_string($asset['location']) ? strtolower(trim($asset['location'])) : 'header';
            $type     = isset($asset['type']) ? strtolower(trim($asset['type'])) : 'css';
            if ('header' !== $location || 'font' !== $type) {
                continue;
            }

            $raw_url = isset($asset['url']) ? trim($asset['url']) : '';
            if (empty($raw_url)) {
                continue;
            }

            $url = esc_url(self::resolve_asset_url($raw_url, false));
            if (empty($url)) {
                continue;
            }

            $ext       = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mime_attr = isset($mime_types[$ext]) ? ' type="' . $mime_types[$ext] . '"' : '';
            echo '<link rel="preload" href="' . $url . '" as="font"' . $mime_attr . ' crossorigin>' . "\n";
        }
    }

    /**
     * Output custom assets registered for the header.
     */
    public static function output_loaded_assets_header()
    {
        self::render_loaded_assets('header');
    }

    /**
     * Output custom assets registered for the footer.
     */
    public static function output_loaded_assets_footer()
    {
        self::render_loaded_assets('footer');
    }

    /**
     * Resolve asset URL dynamically for frontend rendering.
     * Converts relative paths (starting with '/' or 'wp-content/') to full site URLs,
     * while leaving external CDN URLs untouched.
     *
     * @param string $url Asset URL stored in settings.
     * @return string Resolved absolute URL for HTML rendering.
     */
    public static function resolve_asset_url(string $url, bool $with_version = true): string
    {
        if (empty($url)) {
            return '';
        }

        $trimmed = trim($url);

        if (0 === strpos($trimmed, '//') || preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        $clean_path = ltrim($trimmed, '/');
        $resolved   = site_url('/' . $clean_path);

        if ($with_version && defined('ABSPATH')) {
            $local_path = ABSPATH . $clean_path;
            if (file_exists($local_path)) {
                $resolved .= '?ver=' . filemtime($local_path);
            }
        }

        return $resolved;
    }

    /**
     * Render loaded custom assets (CSS/JS) for specified location.
     *
     * @param string $target_location 'header' or 'footer'.
     */
    private static function render_loaded_assets(string $target_location)
    {
        if (self::should_skip_output()) {
            return;
        }

        $assets = class_exists('Align_Admin_Assets') ? Align_Admin_Assets::get_effective_loaded_assets() : get_option('align_loaded_assets', []);
        if (!is_array($assets) || empty($assets)) {
            return;
        }

        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $enabled  = self::is_asset_enabled($asset);
            $location = isset($asset['location']) && is_string($asset['location']) ? strtolower(trim($asset['location'])) : 'header';
            if (!in_array($location, ['header', 'footer'], true)) $location = 'header';
            if (!$enabled || $location !== strtolower(trim($target_location))) continue;

            $raw_url = isset($asset['url']) ? trim($asset['url']) : '';
            if (empty($raw_url)) continue;

            $resolved_url = self::resolve_asset_url($raw_url);
            $url          = esc_url($resolved_url);
            if (empty($url)) continue;

            $type    = isset($asset['type']) ? strtolower(trim($asset['type'])) : 'css';
            $loading = isset($asset['loading']) ? strtolower(trim($asset['loading'])) : 'normal';
            if ('font' === $type) continue;

            // Core deduplication: avoid re-rendering if printed or already enqueued via WP
            static $rendered_urls = [];
            if (isset($rendered_urls[$url])) continue;
            if ('js' === $type) {
                global $wp_scripts;
                if ($wp_scripts instanceof WP_Scripts) {
                    $bname = basename(preg_replace('/[?#].*$/', '', $url));
                    foreach ($wp_scripts->queue as $handle) {
                        $src = $wp_scripts->registered[$handle]->src ?? '';
                        if ($src && basename(preg_replace('/[?#].*$/', '', $src)) === $bname) continue 2;
                    }
                }
            }
            $rendered_urls[$url] = true;

            // Output inline style or script directly into HTML if is_inline is enabled
            if (!empty($asset['is_inline'])) {
                $local_path = class_exists('Align_Admin_Assets') ? Align_Admin_Assets::resolve_asset_filesystem_path($raw_url) : false;
                if ($local_path && file_exists($local_path)) {
                    $content = @file_get_contents($local_path);
                    if (false !== $content && '' !== trim($content)) {
                        $id_attr = esc_attr('align-inline-' . sanitize_title(basename($raw_url)));
                        if ('css' === $type) {
                            $base_dir_url = trailingslashit( dirname( $url ) );
                            $content = preg_replace_callback( '/url\(\s*[\'"]?(?!(?:https?:|\/\/|data:|\/))([^\'")]+)[\'"]?\s*\)/i', function( $m ) use ( $base_dir_url ) {
                                return 'url(\'' . $base_dir_url . ltrim( $m[1], './' ) . '\')';
                            }, $content );
                            echo '<style id="' . $id_attr . '">' . $content . '</style>' . "\n";
                            continue;
                        } elseif ('js' === $type) {
                            echo '<script id="' . $id_attr . '">' . $content . '</script>' . "\n";
                            continue;
                        }
                    }
                }
            }

            if ('css' === $type) {
                if ('delay' === $loading && class_exists('AssetsConfig') && !AssetsConfig::shouldSkipOptimize()) {
                    echo '<link rel="stylesheet" data-delay="true" data-href="' . $url . '">' . "\n";
                } else {
                    echo '<link rel="stylesheet" href="' . $url . '">' . "\n";
                }
            } elseif ('js' === $type) {
                if ('delay' === $loading && class_exists('AssetsConfig') && !AssetsConfig::shouldSkipOptimize()) {
                    echo '<script data-delay="true" data-src="' . $url . '" defer></script>' . "\n";
                } else {
                    $attr = ('defer' === $loading) ? ' defer' : (('async' === $loading) ? ' async' : '');
                    echo '<script src="' . $url . '"' . $attr . '></script>' . "\n";
                }
            }
        }
    }

    /**
     * Internal queues for consolidated inline CSS & JS.
     *
     * @var array<string, string>
     */
    private static array $inline_css_queue = [];

    /**
     * @var array<string, array<string, string>>
     */
    private static array $inline_js_queue = [
        'header' => [],
        'footer' => [],
    ];

    /**
     * Register an inline CSS snippet to the unified frontend queue.
     *
     * @param string $key Unique identifier.
     * @param string $css CSS stylesheet content.
     */
    public static function add_inline_css(string $key, string $css): void
    {
        $trimmed = trim($css);
        if ('' !== $trimmed) {
            self::$inline_css_queue[$key] = $trimmed;
        }
    }

    /**
     * Register an inline JS snippet to the unified frontend queue.
     *
     * @param string $key Unique identifier.
     * @param string $js JS script content.
     * @param string $location 'footer' or 'header'.
     */
    public static function add_inline_js(string $key, string $js, string $location = 'footer'): void
    {
        $trimmed = trim($js);
        if ('' !== $trimmed) {
            $loc = in_array(strtolower($location), ['header', 'footer'], true) ? strtolower($location) : 'footer';
            self::$inline_js_queue[$loc][$key] = $trimmed;
        }
    }

    /**
     * Minify CSS helper with fallback to local minifier.
     *
     * @param string $css
     * @return string
     */
    public static function minify_css(string $css): string
    {
        if (class_exists('Align_Builder_Minifier')) {
            return Align_Builder_Minifier::local_minify_css($css);
        }
        $clean = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $clean = str_replace(["\r\n", "\r", "\n", "\t"], '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = preg_replace('/\s*([\{\};:,])\s*/', '$1', $clean);
        return trim($clean);
    }

    /**
     * Minify JS helper with string protection and whitespace reduction.
     *
     * @param string $js
     * @return string
     */
    public static function minify_js(string $js): string
    {
        $trimmed = trim($js);
        if ('' === $trimmed) {
            return '';
        }

        if (class_exists('Align_Builder_Minifier')) {
            $min = Align_Builder_Minifier::local_minify_js($trimmed);
        } else {
            $clean = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $trimmed);
            $clean = preg_replace('/\/\/[^\r\n]*/', '', $clean);
            $min = $clean;
        }

        // Tokenize strings to protect string literals from whitespace collapsing
        $tokens = [];
        $pattern = '/("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|`(?:[^`\\\\]|\\\\.)*`)/s';
        $min = preg_replace_callback($pattern, function ($matches) use (&$tokens) {
            $token = '%%%ALIGN_JS_INLINE_TOKEN_' . count($tokens) . '%%%';
            $tokens[$token] = $matches[0];
            return $token;
        }, $min);

        // Collapse safe whitespace around common JS syntax delimiters
        $min = preg_replace('/\s*([\{\}\(\)\[\]\;\:\,\=\!\?\+\-\*\/\<\>\&\|])\s*/', '$1', $min);
        $min = preg_replace('/\s+/', ' ', $min);

        return !empty($tokens) ? trim(strtr($min, $tokens)) : trim($min);
    }

    /**
     * Output custom tracking code directly inside <head>.
     * Only outputs when non-empty content exists.
     */
    public static function output_tracking_code()
    {
        if (self::should_skip_output()) {
            return;
        }

        $tracking = get_option('align_tracking_code', '');
        if (is_string($tracking) && '' !== trim($tracking)) {
            echo "\n" . '<!-- Align Tracking Code -->' . "\n";
            echo trim($tracking) . "\n";
            echo '<!-- End Align Tracking Code -->' . "\n";
        }
    }

    /**
     * Output unified consolidated inline CSS in <head>.
     * Pre-minified in DB & constants -> zero API calls and zero runtime regex overhead.
     */
    public static function output_custom_css()
    {
        if (self::should_skip_output()) {
            return;
        }

        $minified_css = get_option('align_custom_css_minified', null);
        if (null === $minified_css) {
            $raw_css      = get_option('align_custom_css', '');
            $minified_css = is_string($raw_css) && '' !== trim($raw_css) ? self::minify_css($raw_css) : '';
            update_option('align_custom_css_minified', $minified_css, true);
        }

        if ('' !== $minified_css) {
            self::$inline_css_queue['custom-css'] = $minified_css;
        }

        $queue = apply_filters('align_inline_css', self::$inline_css_queue);
        if (empty($queue) || !is_array($queue)) {
            return;
        }

        $combined_css = trim(implode('', $queue));
        if ('' !== $combined_css) {
            echo "\n<style id=\"align-core-inline-css\">" . $combined_css . "</style>\n";
        }
    }

    /**
     * Output unified consolidated inline JS before </body>.
     * Pre-minified in DB & constants -> zero API calls and zero runtime regex overhead.
     */
    public static function output_custom_js()
    {
        if (self::should_skip_output()) {
            return;
        }

        $minified_js = get_option('align_custom_js_minified', null);
        if (null === $minified_js) {
            $raw_js      = get_option('align_custom_js', '');
            $minified_js = is_string($raw_js) && '' !== trim($raw_js) ? self::minify_js($raw_js) : '';
            update_option('align_custom_js_minified', $minified_js, true);
        }

        if ('' !== $minified_js) {
            self::$inline_js_queue['footer']['custom-js'] = $minified_js;
        }

        $footer_queue = isset(self::$inline_js_queue['footer']) && is_array(self::$inline_js_queue['footer'])
            ? self::$inline_js_queue['footer']
            : [];

        $queue = apply_filters('align_inline_footer_js', $footer_queue);
        if (empty($queue) || !is_array($queue)) {
            return;
        }

        $combined_js = trim(implode(';', $queue));
        if ('' !== $combined_js) {
            echo "\n<script id=\"align-core-inline-js\">" . $combined_js . "</script>\n";
        }
    }

    /**
     * Enqueue custom assets and inline styles in Gutenberg Block Editor & FSE Site Editor.
     */
    public static function enqueue_editor_custom_assets()
    {
        $assets = get_option('align_loaded_assets', []);
        if (is_array($assets) && !empty($assets)) {
            $font_face_css = '';
            $format_map    = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype', 'eot' => 'embedded-opentype'];

            foreach ($assets as $index => $asset) {
                if (!is_array($asset) || !self::is_asset_enabled($asset)) {
                    continue;
                }
                $raw_url = isset($asset['url']) ? trim($asset['url']) : '';
                if (empty($raw_url)) {
                    continue;
                }
                $type = isset($asset['type']) ? strtolower(trim($asset['type'])) : 'css';
                $url  = esc_url(self::resolve_asset_url($raw_url));
                if (empty($url)) {
                    continue;
                }

                if ('css' === $type) {
                    wp_enqueue_style('align-editor-custom-asset-' . $index, $url, [], null);
                } elseif ('font' === $type) {
                    $font_name = pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
                    $ext       = strtolower(pathinfo(wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    $format    = isset($format_map[$ext]) ? " format('{$format_map[$ext]}')" : '';
                    if (!empty($font_name)) {
                        $font_face_css .= "@font-face { font-family: '{$font_name}'; src: url('{$url}'){$format}; font-display: swap; }\n";
                    }
                }
            }

            if (!empty($font_face_css)) {
                wp_add_inline_style('wp-block-editor', $font_face_css);
                wp_add_inline_style('wp-edit-blocks', $font_face_css);
            }
        }

        $custom_css = get_option('align_custom_css', '');
        if (is_string($custom_css) && '' !== trim($custom_css)) {
            wp_add_inline_style('wp-block-editor', trim($custom_css));
            wp_add_inline_style('wp-edit-blocks', trim($custom_css));
        }
    }
}
