<?php
/**
 * Align Agent - SEO Provider Settings & Post Meta Adapters
 *
 * Manages the 'align_agent_seo_provider' option (rank_math, yoast, theme_default, others),
 * generates non-blocking configuration warnings, and provides guarded post meta
 * adapters for Rank Math SEO, Yoast SEO, and Align Theme Default SEO (Align_Seo).
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Seo_Adapters {

	/**
	 * WordPress option key storing the selected SEO provider.
	 */
	const OPTION_PROVIDER = 'align_agent_seo_provider';

	/**
	 * Allowed SEO provider identifiers.
	 */
	const ALLOWED_PROVIDERS = array( 'rank_math', 'yoast', 'theme_default', 'others' );

	/**
	 * Initialize SEO adapter hooks and ensure initial default option is seeded.
	 */
	public static function init(): void {
		self::ensure_default_provider();
	}

	/**
	 * Seed initial default SEO provider on first install if option does not exist yet.
	 *
	 * @return string Selected or newly seeded provider.
	 */
	public static function ensure_default_provider(): string {
		$current = get_option( self::OPTION_PROVIDER, false );
		if ( false !== $current && in_array( (string) $current, self::ALLOWED_PROVIDERS, true ) ) {
			return (string) $current;
		}

		if ( defined( 'RANK_MATH_VERSION' ) ) {
			$default = 'rank_math';
		} elseif ( defined( 'WPSEO_VERSION' ) ) {
			$default = 'yoast';
		} else {
			$default = 'theme_default';
		}

		update_option( self::OPTION_PROVIDER, $default );
		error_log( sprintf( '[Align Agent] Initialized default SEO provider: %s.', $default ) );
		return $default;
	}

	/**
	 * Retrieve the currently configured SEO provider.
	 *
	 * @return string One of: rank_math, yoast, theme_default, others.
	 */
	public static function get_provider(): string {
		$val = (string) get_option( self::OPTION_PROVIDER, '' );
		if ( ! in_array( $val, self::ALLOWED_PROVIDERS, true ) ) {
			return self::ensure_default_provider();
		}
		return $val;
	}

	/**
	 * Return human-readable label for a provider slug.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public static function get_provider_label( string $provider ): string {
		$labels = array(
			'rank_math'     => 'Rank Math SEO',
			'yoast'         => 'Yoast SEO',
			'theme_default' => 'Align Theme Default (Align_Seo)',
			'others'        => 'Other SEO Plugin (Manual Table)',
		);
		return $labels[ $provider ] ?? $provider;
	}

	/**
	 * Save SEO provider selection from the settings form and append any non-blocking warnings.
	 *
	 * @param string $notice_html Reference to admin notice HTML string.
	 */
	public static function handle_settings_save( string &$notice_html ): void {
		if ( ! isset( $_POST['align_agent_seo_provider'] ) ) {
			return;
		}

		$raw      = sanitize_key( wp_unslash( $_POST['align_agent_seo_provider'] ) );
		$provider = in_array( $raw, self::ALLOWED_PROVIDERS, true ) ? $raw : 'theme_default';
		update_option( self::OPTION_PROVIDER, $provider );
		error_log( sprintf( '[Align Agent] Updated SEO provider setting to "%s".', $provider ) );

		$warnings = self::get_config_warnings( $provider );
		foreach ( $warnings as $warning_msg ) {
			$notice_html .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $warning_msg ) . '</p></div>';
		}
	}

	/**
	 * Inspect current environment constants and return non-blocking warning messages.
	 *
	 * @param string $provider Optional provider to check (defaults to saved provider).
	 * @return array<int, string>
	 */
	public static function get_config_warnings( string $provider = '' ): array {
		if ( '' === $provider ) {
			$provider = self::get_provider();
		}

		$warnings = array();

		if ( 'rank_math' === $provider && ! defined( 'RANK_MATH_VERSION' ) ) {
			$warnings[] = __( 'Warning: Rank Math SEO is selected, but RANK_MATH_VERSION is not detected. The save_seo_rank_math tool will return plugin_not_active until Rank Math is activated.', 'align-agent' );
		}

		if ( 'yoast' === $provider && ! defined( 'WPSEO_VERSION' ) ) {
			$warnings[] = __( 'Warning: Yoast SEO is selected, but WPSEO_VERSION is not detected. The save_seo_yoast tool will return plugin_not_active until Yoast SEO is activated.', 'align-agent' );
		}

		if ( 'theme_default' === $provider && ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) ) ) {
			$active_plugin = defined( 'RANK_MATH_VERSION' ) ? 'Rank Math SEO' : 'Yoast SEO';
			$warnings[]    = sprintf(
				/* translators: %s: Active SEO plugin name */
				__( 'Warning: Align Theme Default SEO is selected while %s is active. To prevent duplicate meta tags in <head>, Align_Seo automatically suppresses its frontend output while Rank Math or Yoast is active.', 'align-agent' ),
				$active_plugin
			);
		}

		return $warnings;
	}

	/**
	 * Build the 1-line system prompt instruction describing the site's active SEO provider.
	 *
	 * @return string
	 */
	public static function get_system_prompt_line(): string {
		$provider = self::get_provider();
		if ( 'others' === $provider ) {
			return 'Site SEO provider: Other third-party SEO plugin (no SEO write tool registered; when asked to write or optimize SEO, return a ready-to-paste 4-row Markdown table with columns "Field" and "Prepared Value" for: Title [max 70 chars], Description [max 160 chars], Focus keyword, and Social image [1200x630 Media Library URL]).';
		}

		$tool_map = array(
			'rank_math'     => 'save_seo_rank_math',
			'yoast'         => 'save_seo_yoast',
			'theme_default' => 'save_seo_theme_default',
		);
		$tool     = $tool_map[ $provider ] ?? 'save_seo_theme_default';
		return sprintf( 'Site SEO provider: %s (use %s to write SEO metadata and check_seo_output to verify <head> tags).', self::get_provider_label( $provider ), $tool );
	}

	/**
	 * Save Rank Math SEO post meta with strict Line-1 plugin constant guard.
	 * Writes both rank_math_facebook_* and rank_math_og_* for 100% compatibility
	 * with Rank Math frontend OpenGraph output and Align Builder SEO panel.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param array $args    SEO fields (title, description, focus_keyword, canonical, noindex, og_title, og_description, og_image_id).
	 * @return array|WP_Error
	 */
	public static function save_rank_math( int $post_id, array $args ) {
		if ( ! defined( 'RANK_MATH_VERSION' ) ) {
			return new WP_Error( 'plugin_not_active', 'Rank Math SEO is not active.' );
		}

		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post #%d was not found.', $post_id ) );
		}

		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || empty( $post_type_obj->public ) ) {
			return new WP_Error( 'invalid_post_type', sprintf( 'Post #%d has a non-public post type (%s).', $post_id, $post->post_type ) );
		}

		$saved = array();

		if ( isset( $args['title'] ) ) {
			$val = sanitize_text_field( (string) $args['title'] );
			update_post_meta( $post_id, 'rank_math_title', $val );
			$saved['title'] = $val;
		}

		if ( isset( $args['description'] ) ) {
			$val = sanitize_text_field( (string) $args['description'] );
			update_post_meta( $post_id, 'rank_math_description', $val );
			$saved['description'] = $val;
		}

		if ( isset( $args['focus_keyword'] ) ) {
			$val = sanitize_text_field( (string) $args['focus_keyword'] );
			update_post_meta( $post_id, 'rank_math_focus_keyword', $val );
			$saved['focus_keyword'] = $val;
		}

		if ( isset( $args['canonical'] ) ) {
			$val = esc_url_raw( (string) $args['canonical'] );
			update_post_meta( $post_id, 'rank_math_canonical_url', $val );
			$saved['canonical'] = $val;
		}

		if ( array_key_exists( 'noindex', $args ) && null !== $args['noindex'] ) {
			$noindex = ! empty( $args['noindex'] );
			$robots  = $noindex ? array( 'noindex', 'follow' ) : array( 'index', 'follow' );
			update_post_meta( $post_id, 'rank_math_robots', $robots );
			$saved['noindex'] = $noindex;
		}

		if ( isset( $args['og_title'] ) ) {
			$val = sanitize_text_field( (string) $args['og_title'] );
			update_post_meta( $post_id, 'rank_math_facebook_title', $val );
			update_post_meta( $post_id, 'rank_math_og_title', $val );
			$saved['og_title'] = $val;
		}

		if ( isset( $args['og_description'] ) ) {
			$val = sanitize_text_field( (string) $args['og_description'] );
			update_post_meta( $post_id, 'rank_math_facebook_description', $val );
			update_post_meta( $post_id, 'rank_math_og_description', $val );
			$saved['og_description'] = $val;
		}

		if ( isset( $args['og_image_id'] ) ) {
			$img_id  = absint( $args['og_image_id'] );
			$img_url = $img_id > 0 ? (string) wp_get_attachment_image_url( $img_id, 'full' ) : '';
			update_post_meta( $post_id, 'rank_math_facebook_image_id', $img_id );
			update_post_meta( $post_id, 'rank_math_facebook_image', $img_url );
			$saved['og_image_id']  = $img_id;
			$saved['og_image_url'] = $img_url;
		}

		if ( class_exists( 'Align_Builder_Seo_Handler' ) && method_exists( 'Align_Builder_Seo_Handler', 'evaluate_rank_math_score' ) ) {
			$kw    = (string) get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			$title = (string) get_post_meta( $post_id, 'rank_math_title', true );
			$desc  = (string) get_post_meta( $post_id, 'rank_math_description', true );
			$slug  = (string) get_post_field( 'post_name', $post_id );
			Align_Builder_Seo_Handler::evaluate_rank_math_score( $post_id, $kw, $title, $desc, $slug, true );
		}

		clean_post_cache( $post_id );
		error_log( sprintf( '[Align Agent] Saved Rank Math SEO meta for post #%d.', $post_id ) );

		return array(
			'provider' => 'rank_math',
			'post_id'  => $post_id,
			'saved'    => $saved,
			'warnings' => array(),
		);
	}

	/**
	 * Save Yoast SEO post meta with strict Line-1 plugin constant guard.
	 * Checks wpseo_social opengraph setting and warns if OpenGraph is disabled in Yoast.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param array $args    SEO fields (title, description, focus_keyword, canonical, noindex, og_title, og_description, og_image_id).
	 * @return array|WP_Error
	 */
	public static function save_yoast( int $post_id, array $args ) {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return new WP_Error( 'plugin_not_active', 'Yoast SEO is not active.' );
		}

		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post #%d was not found.', $post_id ) );
		}

		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || empty( $post_type_obj->public ) ) {
			return new WP_Error( 'invalid_post_type', sprintf( 'Post #%d has a non-public post type (%s).', $post_id, $post->post_type ) );
		}

		$saved    = array();
		$warnings = array();

		if ( isset( $args['title'] ) ) {
			$val = sanitize_text_field( (string) $args['title'] );
			update_post_meta( $post_id, '_yoast_wpseo_title', $val );
			$saved['title'] = $val;
		}

		if ( isset( $args['description'] ) ) {
			$val = sanitize_text_field( (string) $args['description'] );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $val );
			$saved['description'] = $val;
		}

		if ( isset( $args['focus_keyword'] ) ) {
			$val = sanitize_text_field( (string) $args['focus_keyword'] );
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $val );
			$saved['focus_keyword'] = $val;
		}

		if ( isset( $args['canonical'] ) ) {
			$val = esc_url_raw( (string) $args['canonical'] );
			update_post_meta( $post_id, '_yoast_wpseo_canonical', $val );
			$saved['canonical'] = $val;
		}

		if ( array_key_exists( 'noindex', $args ) && null !== $args['noindex'] ) {
			$noindex = ! empty( $args['noindex'] );
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $noindex ? '1' : '0' );
			$saved['noindex'] = $noindex;
		}

		$saving_og = isset( $args['og_title'] ) || isset( $args['og_description'] ) || isset( $args['og_image_id'] );
		if ( $saving_og && ! self::is_yoast_opengraph_enabled() ) {
			$warnings[] = 'Yoast SEO OpenGraph (Social) feature is currently disabled in wpseo_social settings. OpenGraph meta keys were saved to the database, but Yoast will not print og:* tags in <head> until OpenGraph is enabled in Yoast SEO > Settings > Social.';
		}

		if ( isset( $args['og_title'] ) ) {
			$val = sanitize_text_field( (string) $args['og_title'] );
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', $val );
			$saved['og_title'] = $val;
		}

		if ( isset( $args['og_description'] ) ) {
			$val = sanitize_text_field( (string) $args['og_description'] );
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', $val );
			$saved['og_description'] = $val;
		}

		if ( isset( $args['og_image_id'] ) ) {
			$img_id  = absint( $args['og_image_id'] );
			$img_url = $img_id > 0 ? (string) wp_get_attachment_image_url( $img_id, 'full' ) : '';
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-image-id', (string) $img_id );
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-image', $img_url );
			$saved['og_image_id']  = $img_id;
			$saved['og_image_url'] = $img_url;
		}

		clean_post_cache( $post_id );
		error_log( sprintf( '[Align Agent] Saved Yoast SEO meta for post #%d.', $post_id ) );

		return array(
			'provider' => 'yoast',
			'post_id'  => $post_id,
			'saved'    => $saved,
			'warnings' => $warnings,
		);
	}

	/**
	 * Check whether Yoast SEO Social OpenGraph output is enabled in wpseo_social option.
	 *
	 * @return bool
	 */
	public static function is_yoast_opengraph_enabled(): bool {
		$social = get_option( 'wpseo_social', array() );
		if ( ! is_array( $social ) ) {
			return true;
		}
		return ! array_key_exists( 'opengraph', $social ) || ! empty( $social['opengraph'] );
	}

	/**
	 * Save Align Theme Default SEO post meta with strict Line-1 class_exists guard.
	 *
	 * @param int   $post_id WordPress post ID.
	 * @param array $args    SEO fields.
	 * @return array|WP_Error
	 */
	public static function save_theme_default( int $post_id, array $args ) {
		if ( ! class_exists( 'Align_Seo' ) ) {
			return new WP_Error( 'plugin_not_active', 'Align Theme SEO (Align_Seo) is not active.' );
		}

		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post #%d was not found.', $post_id ) );
		}

		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || empty( $post_type_obj->public ) ) {
			return new WP_Error( 'invalid_post_type', sprintf( 'Post #%d has a non-public post type (%s).', $post_id, $post->post_type ) );
		}

		$res = Align_Seo::save_meta( $post_id, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$warnings = array();
		if ( Align_Seo::has_external_seo_plugin() ) {
			$warnings[] = 'Rank Math or Yoast SEO is active on this site. Align_Seo saved the _align_seo_* meta keys, but suppressed its frontend <head> output to avoid duplicate meta tags.';
		}

		return array(
			'provider' => 'theme_default',
			'post_id'  => $post_id,
			'saved'    => $res['saved'] ?? array(),
			'warnings' => $warnings,
		);
	}
}
