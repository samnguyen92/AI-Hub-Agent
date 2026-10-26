<?php
/**
 * Align Theme - Frontend Language Switcher Shortcode & Renderer
 *
 * Provides [align-language-switcher] shortcode with multiple presentation styles:
 * pills (default), dropdown, flags, and list.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Language_Switcher {

	/**
	 * Track if CSS has been output to avoid duplication.
	 *
	 * @var bool
	 */
	private static $css_output = false;

	/**
	 * Initialize hooks and shortcodes.
	 */
	public static function init() {
		add_shortcode( 'align-language-switcher', array( __CLASS__, 'render' ) );
		add_shortcode( 'align_language_switcher', array( __CLASS__, 'render' ) );
		add_shortcode( 'align-lang-switch',       array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts',        array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register or enqueue frontend assets if needed.
	 */
	public static function register_assets() {
		// Assets enqueued dynamically during shortcode execution
	}

	/**
	 * Render the language switcher shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered HTML.
	 */
	public static function render( $atts = array() ) {
		$atts = shortcode_atts( array(
			'type'         => 'dropdown',
			'show_flags'   => '1',
			'show_names'   => '1',
			'show_code'    => '0',
			'button_label' => 'auto',
			'class'        => '',
			'page_id'      => 0,
		), $atts, 'align-language-switcher' );

		if ( ! class_exists( 'Align_Language_Manager' ) ) {
			return '';
		}

		$languages = Align_Language_Manager::get_languages();
		if ( empty( $languages ) || count( $languages ) < 1 ) {
			return '';
		}

		$show_flags   = ( '1' === $atts['show_flags'] || 'true' === $atts['show_flags'] );
		$show_names   = ( '1' === $atts['show_names'] || 'true' === $atts['show_names'] );
		$show_code    = ( '1' === $atts['show_code']  || 'true' === $atts['show_code'] );
		$button_label = in_array( $atts['button_label'], array( 'auto', 'code', 'name', 'flag', 'none' ), true ) ? $atts['button_label'] : 'auto';
		$type         = in_array( $atts['type'], array( 'pills', 'dropdown', 'flags', 'list' ), true ) ? $atts['type'] : 'dropdown';

		// Resolve current post ID
		$post_id = intval( $atts['page_id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id && isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) ) {
			$post_id = $GLOBALS['post']->ID;
		}

		$current_lang = $post_id ? Align_Language_Manager::get_post_lang( $post_id ) : '';
		if ( ! $current_lang ) {
			$current_lang = Align_Language_Manager::get_default_lang();
		}

		// Map existing translations for this post
		$trans_map = array();
		if ( $post_id ) {
			$translations = Align_Language_Manager::get_translations( $post_id );
			foreach ( $translations as $tr ) {
				$trans_map[ $tr['lang'] ] = $tr['url'];
			}
		}

		// Build items list
		$items = array();
		foreach ( $languages as $lang ) {
			$code      = $lang['code'];
			$name      = $lang['name'];
			$flag      = $lang['flag'] ?? '';
			$is_active = ( $code === $current_lang );

			// Determine URL
			if ( ! empty( $trans_map[ $code ] ) ) {
				$url = $trans_map[ $code ];
			} elseif ( ! empty( $lang['is_default'] ) ) {
				$url = home_url( '/' );
			} elseif ( ! empty( $lang['slug'] ) ) {
				$url = home_url( '/' . trim( $lang['slug'], '/' ) . '/' );
			} else {
				$url = home_url( '/' );
			}

			$items[] = array(
				'code'      => $code,
				'name'      => $name,
				'flag'      => $flag,
				'url'       => $url,
				'is_active' => $is_active,
			);
		}

		$css        = self::get_inline_styles();
		$custom_cls = sanitize_html_class( $atts['class'] );

		if ( 'dropdown' === $type ) {
			$html = self::render_dropdown( $items, $show_flags, $show_names, $show_code, $button_label, $custom_cls );
		} elseif ( 'flags' === $type ) {
			$html = self::render_pills( $items, true, false, false, 'align-lang-switcher--flags ' . $custom_cls );
		} elseif ( 'list' === $type ) {
			$html = self::render_list( $items, $show_flags, $show_names, $show_code, $custom_cls );
		} else {
			$html = self::render_pills( $items, $show_flags, $show_names, $show_code, $custom_cls );
		}

		return $css . $html;
	}

	/**
	 * Render Pills / Inline layout.
	 */
	private static function render_pills( $items, $show_flags, $show_names, $show_code, $extra_cls = '' ) {
		$out = '<nav class="align-lang-switcher align-lang-switcher--pills ' . esc_attr( $extra_cls ) . '" aria-label="' . esc_attr__( 'Language Switcher', 'align' ) . '" data-no-localize="1">';
		foreach ( $items as $it ) {
			$active_cls = $it['is_active'] ? ' is-active' : '';
			$aria_cur   = $it['is_active'] ? ' aria-current="true"' : '';
			$out .= '<a href="' . esc_url( $it['url'] ) . '" class="align-lang-item' . $active_cls . '"' . $aria_cur . ' data-lang="' . esc_attr( $it['code'] ) . '" data-no-localize="1" title="' . esc_attr( $it['name'] ) . '">';
			if ( $show_flags ) {
				$out .= ! empty( $it['flag'] )
					? '<span class="align-lang-flag">' . esc_html( $it['flag'] ) . '</span>'
					: '<span class="align-lang-flag align-lang-flag--badge">' . esc_html( strtoupper( $it['code'] ) ) . '</span>';
			}
			if ( $show_names ) {
				$out .= '<span class="align-lang-name">' . esc_html( $it['name'] ) . '</span>';
			}
			if ( $show_code ) {
				$out .= '<span class="align-lang-code">' . esc_html( strtoupper( $it['code'] ) ) . '</span>';
			}
			$out .= '</a>';
		}
		$out .= '</nav>';
		return $out;
	}

	/**
	 * Render Dropdown layout.
	 */
	private static function render_dropdown( $items, $show_flags, $show_names, $show_code, $button_label = 'auto', $extra_cls = '' ) {
		$active_item = null;
		foreach ( $items as $it ) {
			if ( $it['is_active'] ) {
				$active_item = $it;
				break;
			}
		}
		if ( ! $active_item && ! empty( $items[0] ) ) {
			$active_item = $items[0];
		}

		// Determine button text based on settings
		$button_text = '';
		if ( 'code' === $button_label ) {
			$button_text = strtoupper( $active_item['code'] );
		} elseif ( 'name' === $button_label ) {
			$button_text = $active_item['name'];
		} elseif ( 'flag' === $button_label || 'none' === $button_label ) {
			$button_text = '';
		} else {
			// 'auto' mode: default to uppercase code in button for sleek pill appearance
			if ( ! $show_names && ! $show_code ) {
				$button_text = '';
			} else {
				$button_text = strtoupper( $active_item['code'] );
			}
		}

		$uid = 'lang-drop-' . wp_rand( 100, 9999 );
		$out = '<div class="align-lang-switcher align-lang-switcher--dropdown ' . esc_attr( $extra_cls ) . '" id="' . esc_attr( $uid ) . '" data-no-localize="1">';
		$out .= '<button type="button" class="align-lang-dropdown-toggle" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr__( 'Select Language', 'align' ) . '">';
		if ( $show_flags ) {
			$out .= ! empty( $active_item['flag'] )
				? '<span class="align-lang-flag">' . esc_html( $active_item['flag'] ) . '</span>'
				: '<span class="align-lang-flag align-lang-flag--badge">' . esc_html( strtoupper( $active_item['code'] ) ) . '</span>';
		}
		if ( ! empty( $button_text ) ) {
			$out .= '<span class="align-lang-code-text">' . esc_html( $button_text ) . '</span>';
		}
		// Modern SVG chevron
		$out .= '<svg class="align-lang-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
		$out .= '</button>';

		$out .= '<div class="align-lang-dropdown-menu" role="menu">';
		foreach ( $items as $it ) {
			$active_cls = $it['is_active'] ? ' is-active' : '';
			$aria_cur   = $it['is_active'] ? ' aria-current="true"' : '';
			$out .= '<a href="' . esc_url( $it['url'] ) . '" class="align-lang-dropdown-item' . $active_cls . '" role="menuitem"' . $aria_cur . ' data-lang="' . esc_attr( $it['code'] ) . '" data-no-localize="1">';
			
			$out .= '<span class="align-lang-item-content">';
			if ( $show_flags ) {
				$out .= ! empty( $it['flag'] )
					? '<span class="align-lang-flag">' . esc_html( $it['flag'] ) . '</span>'
					: '<span class="align-lang-flag align-lang-flag--badge">' . esc_html( strtoupper( $it['code'] ) ) . '</span>';
			}
			if ( $show_names ) {
				$out .= '<span class="align-lang-name">' . esc_html( $it['name'] ) . '</span>';
			} elseif ( $show_code ) {
				$out .= '<span class="align-lang-code">' . esc_html( strtoupper( $it['code'] ) ) . '</span>';
			}
			$out .= '</span>';

			// Blue checkmark for active language
			if ( $it['is_active'] ) {
				$out .= '<svg class="align-lang-check" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
			}

			$out .= '</a>';
		}
		$out .= '</div>';
		$out .= '</div>';
		return $out;
	}

	/**
	 * Render List layout.
	 */
	private static function render_list( $items, $show_flags, $show_names, $show_code, $extra_cls = '' ) {
		$out = '<ul class="align-lang-switcher align-lang-switcher--list ' . esc_attr( $extra_cls ) . '" data-no-localize="1">';
		foreach ( $items as $it ) {
			$active_cls = $it['is_active'] ? ' is-active' : '';
			$out .= '<li class="align-lang-list-item' . $active_cls . '">';
			$out .= '<a href="' . esc_url( $it['url'] ) . '" data-lang="' . esc_attr( $it['code'] ) . '" data-no-localize="1">';
			if ( $show_flags ) {
				$out .= ! empty( $it['flag'] )
					? '<span class="align-lang-flag">' . esc_html( $it['flag'] ) . '</span> '
					: '<span class="align-lang-flag align-lang-flag--badge">' . esc_html( strtoupper( $it['code'] ) ) . '</span> ';
			}
			if ( $show_names ) {
				$out .= '<span class="align-lang-name">' . esc_html( $it['name'] ) . '</span>';
			}
			if ( $show_code ) {
				$out .= ' <span class="align-lang-code">' . esc_html( strtoupper( $it['code'] ) ) . '</span>';
			}
			$out .= '</a></li>';
		}
		$out .= '</ul>';
		return $out;
	}

	/**
	 * Output scoped CSS & JS for the switcher on canvas and frontend.
	 */
	private static function get_inline_styles() {
		if ( self::$css_output && ! is_admin() ) {
			return '';
		}
		self::$css_output = true;

		return '<style id="align-lang-switcher-css">
			.align-lang-switcher { display: inline-flex; align-items: center; gap: 6px; box-sizing: border-box; font-family: inherit; position: relative; }
			.align-lang-switcher--pills { flex-wrap: wrap; }
			.align-lang-item { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 9999px; font-size: 13px; font-weight: 500; text-decoration: none !important; color: inherit; border: 1px solid rgba(15, 23, 42, 0.25); background: transparent; transition: all 0.2s ease; }
			.align-lang-item:hover { border-color: #0284c7; background: rgba(2, 132, 199, 0.06); color: #0284c7; }
			.align-lang-item.is-active { background: #0284c7; border-color: #0284c7; color: #ffffff !important; font-weight: 600; }
			.align-lang-flag { font-size: 15px; line-height: 1; display: inline-flex; align-items: center; }
			.align-lang-code { font-size: 11px; font-weight: 600; letter-spacing: 0.04em; }
			.align-lang-flag--badge { display: inline-flex !important; align-items: center !important; justify-content: center !important; font-size: 10px !important; font-weight: 700 !important; padding: 2px 5px !important; border-radius: 4px !important; background: rgba(125, 125, 125, 0.15) !important; color: inherit !important; line-height: 1 !important; }

			/* Flags Only */
			.align-lang-switcher--flags .align-lang-item { padding: 0; border-radius: 50%; width: 32px; height: 32px; justify-content: center; }

			/* List */
			.align-lang-switcher--list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
			.align-lang-list-item a { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 13px; color: inherit; padding: 5px 10px; border-radius: 6px; }
			.align-lang-list-item.is-active a { font-weight: 700; color: #0284c7; }

			/* Dropdown Toggle Button */
			.align-lang-switcher--dropdown { position: relative; display: inline-block; vertical-align: middle; }
			.align-lang-dropdown-toggle { display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 7px !important; height: 34px !important; padding: 0 14px !important; border-radius: 9999px !important; font-size: 13px !important; font-weight: 500 !important; font-family: inherit !important; background: transparent !important; border: 1px solid rgba(15, 23, 42, 0.35) !important; color: inherit !important; cursor: pointer !important; transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important; line-height: 1 !important; white-space: nowrap !important; }
			.align-lang-dropdown-toggle * { color: inherit !important; }
			.align-lang-dropdown-toggle:hover { border-color: rgba(15, 23, 42, 0.8) !important; background: rgba(15, 23, 42, 0.04) !important; }
			.align-lang-switcher--dropdown.is-open .align-lang-dropdown-toggle,
			.align-lang-switcher--dropdown:hover .align-lang-dropdown-toggle { border-color: #0f172a !important; }
			.align-lang-code-text { font-size: 12.5px !important; font-weight: 600 !important; letter-spacing: 0.03em !important; }
			.align-lang-arrow { display: inline-flex; align-items: center; transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1); opacity: 0.7; }
			.align-lang-switcher--dropdown.is-open .align-lang-arrow,
			.align-lang-switcher--dropdown:hover .align-lang-arrow { transform: rotate(180deg); opacity: 1; }

			/* Dropdown Menu Card */
			.align-lang-dropdown-menu { display: none; position: absolute; top: calc(100% + 6px); right: 0; min-width: 170px; background: #ffffff !important; border: 1px solid rgba(15, 23, 42, 0.08) !important; border-radius: 12px !important; box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.12), 0 4px 12px -2px rgba(15, 23, 42, 0.05) !important; padding: 6px !important; z-index: 99999 !important; box-sizing: border-box !important; }
			.align-lang-switcher--dropdown.is-open .align-lang-dropdown-menu,
			.align-lang-switcher--dropdown:hover .align-lang-dropdown-menu,
			.align-lang-switcher--dropdown:focus-within .align-lang-dropdown-menu { display: flex !important; flex-direction: column !important; gap: 2px !important; animation: alignLangMenuFadeIn 0.18s ease forwards; }
			@keyframes alignLangMenuFadeIn {
				from { opacity: 0; transform: translateY(-4px); }
				to { opacity: 1; transform: translateY(0); }
			}

			/* Dropdown Menu Items */
			.align-lang-dropdown-item { display: flex !important; align-items: center !important; justify-content: space-between !important; padding: 8px 12px !important; border-radius: 8px !important; font-size: 13.5px !important; font-weight: 500 !important; color: #1e293b !important; text-decoration: none !important; background: transparent !important; transition: all 0.15s ease !important; line-height: 1.3 !important; }
			.align-lang-item-content { display: inline-flex !important; align-items: center !important; gap: 9px !important; color: inherit !important; }
			.align-lang-dropdown-item .align-lang-name { font-weight: 500 !important; color: inherit !important; }
			.align-lang-dropdown-item .align-lang-code { font-size: 11px !important; font-weight: 600 !important; color: #64748b !important; }
			.align-lang-dropdown-item:hover { background: #f8fafc !important; color: #0f172a !important; }
			.align-lang-dropdown-item.is-active { font-weight: 600 !important; color: #0284c7 !important; background: #eef6fc !important; }
			.align-lang-dropdown-item.is-active .align-lang-name { font-weight: 600 !important; color: #0284c7 !important; }
			.align-lang-check { flex-shrink: 0; stroke: #0284c7; margin-left: 12px; }
		</style>
		<script id="align-lang-switcher-js">
		(function() {
			if (window._alignLangSwitcherBound) return;
			window._alignLangSwitcherBound = true;
			document.addEventListener("click", function(e) {
				var toggle = e.target.closest(".align-lang-dropdown-toggle");
				var switcher = toggle ? toggle.closest(".align-lang-switcher--dropdown") : null;
				document.querySelectorAll(".align-lang-switcher--dropdown.is-open").forEach(function(s) {
					if (s !== switcher) s.classList.remove("is-open");
				});
				if (switcher) {
					e.preventDefault();
					switcher.classList.toggle("is-open");
				}
			});
		})();
		</script>';
	}
}
