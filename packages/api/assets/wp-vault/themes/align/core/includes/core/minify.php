<?php
class WP_HTML_Compression_Extreme
{
    protected $html;

    public function __construct($html)
    {
        if (!empty($html)) {
            $this->html = $this->minify($html);
        }
    }

    public function __toString()
    {
        return (string)$this->html;
    }

    /**
     * Extract script/style/pre/textarea/code blocks safely using strpos,
     * handling cases where </script> appears inside JS string literals.
     *
     * @param string $html
     * @param array  &$blocks
     * @param int    &$i
     * @return string HTML with sensitive blocks replaced by placeholders
     */
    private function extractBlocks(string $html, array &$blocks, int &$i): string
    {
        $tags = ['script', 'style', 'pre', 'textarea', 'code'];
        $result = $html;

        foreach ($tags as $tag) {
            $output = '';
            $remaining = $result;
            $openTag = '<' . $tag;
            $closeTag = '</' . $tag . '>';

            while (($start = stripos($remaining, $openTag)) !== false) {
                // Find the end of the opening tag
                $tagEnd = strpos($remaining, '>', $start);
                if ($tagEnd === false) {
                    break;
                }
                // Check it's actually a valid open tag (not partial match like <script-tag>)
                $char = substr($remaining, $start + strlen($openTag), 1);
                if ($char !== '' && $char !== '>' && $char !== ' ' && $char !== "\t" && $char !== "\n" && $char !== "\r" && $char !== '/') {
                    // Not a valid open tag for this element, skip
                    $output .= substr($remaining, 0, $start + strlen($openTag));
                    $remaining = substr($remaining, $start + strlen($openTag));
                    continue;
                }

                // Find the matching close tag
                $closeStart = stripos($remaining, $closeTag, $tagEnd);
                if ($closeStart === false) {
                    break;
                }

                $fullBlock = substr($remaining, $start, $closeStart - $start + strlen($closeTag));

                $key = "%%HTMLMIN{$i}%%";
                $blocks[$key] = $fullBlock;
                $i++;

                $output .= substr($remaining, 0, $start) . $key;
                $remaining = substr($remaining, $closeStart + strlen($closeTag));
            }

            $result = $output . $remaining;
        }

        return $result;
    }

    private function minify($html)
    {
        // Save original for fallback validation
        $original = $html;

        // Count script/style tags in original for validation
        $origScriptOpen  = substr_count(strtolower($html), '<script');
        $origScriptClose = substr_count(strtolower($html), '</script>');
        $origStyleOpen   = substr_count(strtolower($html), '<style');
        $origStyleClose  = substr_count(strtolower($html), '</style>');

        // ===== Step 1: Protect sensitive blocks =====
        $blocks = [];
        $i = 0;
        $html = $this->extractBlocks($html, $blocks, $i);

        // ===== Step 2: Remove HTML comments (outside protected blocks) =====
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // ===== Step 3: Collapse whitespace =====
        $html = preg_replace('/\s+/', ' ', $html);

        // ===== Step 4: Remove line breaks =====
        $html = str_replace(["\n", "\r", "\t"], '', $html);

        $html = trim($html);

        // ===== Step 5: Restore protected blocks safely using a reverse loop =====
        if (!empty($blocks)) {
            for ($j = $i - 1; $j >= 0; $j--) {
                $key = "%%HTMLMIN{$j}%%";
                if (isset($blocks[$key])) {
                    $html = str_replace($key, $blocks[$key], $html);
                }
            }
        }

        // ===== Step 6: Validate output - fallback if tag counts don't match =====
        $outScriptOpen  = substr_count(strtolower($html), '<script');
        $outScriptClose = substr_count(strtolower($html), '</script>');
        $outStyleOpen   = substr_count(strtolower($html), '<style');
        $outStyleClose  = substr_count(strtolower($html), '</style>');

        if (
            $outScriptOpen  !== $origScriptOpen  ||
            $outScriptClose !== $origScriptClose ||
            $outStyleOpen   !== $origStyleOpen   ||
            $outStyleClose  !== $origStyleClose
        ) {
            // Tag mismatch detected — return original HTML unmodified to prevent JS errors
            return $original;
        }

        return $html;
    }
}

function wp_html_compression_finish($html)
{
    return new WP_HTML_Compression_Extreme($html);
}

function wp_html_compression_start()
{
    if (is_admin()) {
        return;
    }

    // Check if HTML minify option is enabled
    if (!get_option('align_enable_html_minify', 0)) {
        return;
    }

    // Bypass HTML minification for robots.txt and llms.txt plain text feeds
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
    if (preg_match('#^/robots\.txt$#i', $path) || preg_match('#^/llms\.txt$#i', $path)) {
        return;
    }

    ob_start('wp_html_compression_finish');
}

add_action('template_redirect', 'wp_html_compression_start', 0);
