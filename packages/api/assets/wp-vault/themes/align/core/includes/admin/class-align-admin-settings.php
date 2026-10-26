<?php
/**
 * Streamlined Admin Settings Panel for Visual Builder, WebP, Minify, Media Sizes, and Asset Loading.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Admin_Settings {

	/**
	 * Initialize admin settings hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'remove_wp_media_settings_menu' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_wp_media_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_align_check_api_status', array( __CLASS__, 'ajax_check_api_status' ) );
		add_action( 'wp_ajax_align_convert_html_to_blocks', array( __CLASS__, 'ajax_convert_html_to_blocks' ) );
	}

	/**
	 * Enqueue admin scripts and styles including WP Media uploader on Align Settings page.
	 *
	 * @param string $hook Page hook suffix.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( false !== strpos( $hook, 'align-settings' ) ) {
			if ( function_exists( 'wp_enqueue_code_editor' ) ) {
				wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
			}

			$theme_uri = get_template_directory_uri();
			$version   = defined( 'ALIGN_VERSION' ) ? ALIGN_VERSION : '1.0.0';

			wp_enqueue_script(
				'align-code-editor',
				$theme_uri . '/core/assets/admin/js/align-code-editor.js',
				array( 'jquery', 'code-editor' ),
				$version,
				true
			);
		}
	}

	/**
	 * Remove default WordPress Media settings sub-menu item under Settings.
	 */
	public static function remove_wp_media_settings_menu() {
		remove_submenu_page( 'options-general.php', 'options-media.php' );
	}

	/**
	 * Redirect direct visits to options-media.php to Align Settings Media tab.
	 */
	public static function redirect_wp_media_settings_page() {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		global $pagenow;

		if ( 'options-media.php' === $pagenow ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'align' ) );
			}

			error_log( '[Align Admin Settings] Redirected options-media.php to Align Settings Media tab' );
			$redirect_url = admin_url( 'options-general.php?page=align-settings#performance/perf-media' );
			if ( headers_sent() ) {
				echo '<script>window.location.replace(' . wp_json_encode( $redirect_url ) . ');</script>';
				exit;
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Register settings for all tabs in Align Settings panel.
	 */
	public static function register_settings() {
		// Tab 1: Builder & API Sync
		register_setting( 'align_settings_group', 'align_minify_api_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => 'https://convertb.alignvn.cloud',
		) );
		register_setting( 'align_settings_group', 'align_builder_external_assets', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );

		register_setting( 'align_settings_group', 'align_disable_wpautop', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		) );

		// Tab 2: WebP Media Converter
		register_setting( 'align_settings_group', 'align_webp_enable_auto_convert', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1,
		) );
		register_setting( 'align_settings_group', 'align_webp_quality', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 80,
		) );

		// Tab 3: Minify & Custom Code
		register_setting( 'align_settings_group', 'align_enable_html_minify', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( 'align_settings_group', 'align_custom_css', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_custom_code' ),
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_custom_js', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_custom_code' ),
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_tracking_code', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_custom_code' ),
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_body_open_code', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_custom_code' ),
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_footer_html_code', array(
			'type'              => 'string',
			'sanitize_callback' => array( __CLASS__, 'sanitize_custom_code' ),
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_custom_code_skip_admins', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );

		// Tab 4: Media Sizes
		if ( function_exists( 'wp_get_registered_image_subsizes' ) ) {
			$subsizes       = wp_get_registered_image_subsizes();
			$standard_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );
			foreach ( $standard_sizes as $std_name ) {
				if ( ! isset( $subsizes[ $std_name ] ) ) {
					$subsizes[ $std_name ] = array( 'width' => 0, 'height' => 0, 'crop' => false );
				}
			}
			foreach ( $subsizes as $name => $data ) {
				$opts = align_get_image_size_option_names( $name );
				register_setting( 'align_settings_group', $opts['w'] );
				register_setting( 'align_settings_group', $opts['h'] );
				register_setting( 'align_settings_group', $opts['crop'], array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ) );
			}
		}

		register_setting( 'align_settings_group', 'align_custom_image_sizes', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize_custom_image_sizes' ),
			'default'           => array(),
		) );

		// Tab 5: Load Assets
		register_setting( 'align_settings_group', 'align_loaded_assets', array(
			'type'              => 'array',
			'sanitize_callback' => array( 'Align_Admin_Assets', 'sanitize_loaded_assets' ),
			'default'           => array(),
		) );

		// Tab 6: Git Updater
		register_setting( 'align_settings_group', 'align_git_repository', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => class_exists( 'Align_Git_Updater' ) ? Align_Git_Updater::DEFAULT_REPO : 'bibikeo1996/Align-Real-Builder',
		) );
		register_setting( 'align_settings_group', 'align_git_branch', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => class_exists( 'Align_Git_Updater' ) ? Align_Git_Updater::DEFAULT_BRANCH : 'main',
		) );
		register_setting( 'align_settings_group', 'align_git_token', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => class_exists( 'Align_Git_Updater' ) ? Align_Git_Updater::DEFAULT_TOKEN : '',
		) );
		register_setting( 'align_settings_group', 'align_git_webhook_enabled', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );

		// Tab 7: Plugins
		register_setting( 'align_settings_group', 'align_enable_lenis_scroll', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( 'align_settings_group', 'align_enable_splidejs', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( 'align_settings_group', 'align_enable_delay_assets', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( 'align_settings_group', 'align_delay_custom_urls', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		) );

		// Tab 8: Import ZIP AI Settings (OpenRouter Default)
		register_setting( 'align_settings_group', 'align_openrouter_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_openrouter_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'google/gemini-2.5-flash',
		) );

		// Tab 9: Site Protection & Access Control
		register_setting( 'align_settings_group', 'align_site_protect_enabled', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( 'align_settings_group', 'align_site_protect_live_domain', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'Align_Site_Protection', 'normalize_domain' ),
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_site_protect_staging_domain', array(
			'type'              => 'string',
			'sanitize_callback' => array( 'Align_Site_Protection', 'normalize_domain' ),
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_site_protect_user', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'staging',
		) );
		register_setting( 'align_settings_group', 'align_site_protect_password', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( 'align_settings_group', 'align_site_protect_auto_noindex', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1,
		) );
		register_setting( 'align_settings_group', 'align_site_protect_bypass_localhost', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1,
		) );
		register_setting( 'align_settings_group', 'align_site_protect_bypass_admins', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 1,
		) );
		register_setting( 'align_settings_group', 'align_site_protect_cookie_days', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 30,
		) );
	}

	/**
	 * Proxy helper method to convert internal local asset URL to root-relative path.
	 * Forwarded to Align_Admin_Assets::get_relative_asset_url for backward compatibility.
	 *
	 * @param string $url Raw URL string.
	 * @return string Root-relative path if internal local site URL, or original string.
	 */
	public static function get_relative_asset_url( $url ) {
		return Align_Admin_Assets::get_relative_asset_url( $url );
	}

	/**
	 * Proxy sanitize loaded assets array submitted from admin settings.
	 * Forwarded to Align_Admin_Assets::sanitize_loaded_assets for backward compatibility.
	 *
	 * @param mixed $input Input array from form submission.
	 * @return array Sanitized array of loaded assets.
	 */
	public static function sanitize_loaded_assets( $input ) {
		return Align_Admin_Assets::sanitize_loaded_assets( $input );
	}

	/**
	 * Sanitize custom image sizes array submitted from admin settings.
	 * Automatically generates size name/slug from the label.
	 *
	 * @param mixed $input Input array from form submission.
	 * @return array Sanitized array of image sizes.
	 */
	public static function sanitize_custom_image_sizes( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $input as $size ) {
			if ( ! is_array( $size ) ) {
				continue;
			}

			$label  = isset( $size['label'] ) ? sanitize_text_field( $size['label'] ) : '';
			$name   = sanitize_key( str_replace( ' ', '_', strtolower( $label ) ) );
			$width  = isset( $size['width'] ) ? absint( $size['width'] ) : 0;
			$height = isset( $size['height'] ) ? absint( $size['height'] ) : 0;
			$crop   = ! empty( $size['crop'] ) ? 1 : 0;

			if ( empty( $name ) ) {
				continue;
			}

			$sanitized[] = array(
				'name'   => $name,
				'label'  => $label,
				'width'  => $width,
				'height' => $height,
				'crop'   => $crop,
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize custom HTML/CSS/JS code inputs.
	 *
	 * @param string $value Input code.
	 * @return string Sanitized code.
	 */
	public static function sanitize_custom_code( $value ) {
		if ( current_user_can( 'unfiltered_html' ) ) {
			return $value;
		}
		return wp_kses_post( $value );
	}

	/**
	 * Render the settings page view.
	 */
	public static function render_settings_page() {
		wp_enqueue_media();
		include __DIR__ . '/views/layout.php';
	}

	/**
	 * AJAX endpoint to check external Minify API service status.
	 */
	public static function ajax_check_api_status() {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'align' ) ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'align' ) ) );
		}
		$raw_url  = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : 'https://convertb.alignvn.cloud';
		$response = wp_remote_get( rtrim( $raw_url, '/' ) . '/health', array( 'timeout' => 5 ) );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}
		wp_send_json_success( array( 'code' => wp_remote_retrieve_response_code( $response ) ) );
	}

	/**
	 * AJAX endpoint to convert raw HTML into Gutenberg block markup.
	 */
	public static function ajax_convert_html_to_blocks() {
		$nonce = $_POST['align_settings_nonce'] ?? $_POST['nonce'] ?? $_POST['security'] ?? '';
		if ( ! wp_verify_nonce( $nonce, 'align_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'align' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user permission.', 'align' ) ) );
		}

		$raw_html  = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';
		$word_mode = ! empty( $_POST['word_mode'] );

		if ( '' === trim( $raw_html ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide HTML content to convert.', 'align' ) ) );
		}

		$raw_url = isset( $_POST['api_url'] ) ? sanitize_text_field( wp_unslash( $_POST['api_url'] ) ) : '';
		if ( empty( $raw_url ) ) {
			$raw_url = function_exists( 'align_get_api_converter_url' ) ? align_get_api_converter_url() : 'https://convertb.alignvn.cloud';
		}

		$result = Align_Block_Converter::convert( $raw_html, $word_mode, $raw_url );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}
}
