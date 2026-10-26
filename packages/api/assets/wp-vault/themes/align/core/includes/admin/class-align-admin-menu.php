<?php
/**
 * Align Theme Admin Menu Manager
 *
 * Registers the primary Align Theme top-level navigation at the bottom of
 * the WordPress Admin sidebar, housing the Dashboard and Align ecosystem tools.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Admin_Menu {

	/**
	 * Top-level menu slug.
	 */
	const MENU_SLUG = 'align-settings';

	/**
	 * Initialize admin menu hooks.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Register parent and initial submenus at priority 9 so plugins can hook at default priority 10
		add_action( 'admin_menu', [ __CLASS__, 'register_admin_menus' ], 9 );
		// Cleanup & reparenting hook at priority 99 to guarantee no Align submenus leak into Settings
		add_action( 'admin_menu', [ __CLASS__, 'adopt_and_clean_menus' ], 99 );
		add_action( 'admin_init', [ __CLASS__, 'handle_legacy_redirects' ] );
	}

	/**
	 * Register the Align Theme top-level menu and submenus.
	 */
	public static function register_admin_menus(): void {
		// 1. Primary Top-Level Menu at the bottom of the WP sidebar (position 99)
		add_menu_page(
			esc_html__( 'Align Theme', 'align' ),
			esc_html__( 'Align Theme', 'align' ),
			'manage_options',
			self::MENU_SLUG,
			[ 'Align_Admin_Settings', 'render_settings_page' ],
			'dashicons-align-left',
			99
		);

		// 2. Submenu 1: Dashboard (Links to Align Theme Master Dashboard)
		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Dashboard', 'align' ),
			esc_html__( 'Dashboard', 'align' ),
			'manage_options',
			self::MENU_SLUG,
			[ 'Align_Admin_Settings', 'render_settings_page' ]
		);

		/**
		 * Action allowing official Align ecosystem plugins to hook submenus under Align Theme.
		 *
		 * @param string $parent_slug Top-level menu slug ('align-settings').
		 */
		do_action( 'align_register_admin_submenus', self::MENU_SLUG );
	}

	/**
	 * Auto-adopt and cleanup: Ensure any Align plugin menus registered under Settings
	 * are cleanly migrated inside Align Theme menu and removed from Settings.
	 */
	public static function adopt_and_clean_menus(): void {
		// Clean up any Align plugin submenus from under WordPress Settings (options-general.php)
		$align_submenus = [
			'align-files-settings',
			'align-seo-checker',
			'align-deploy',
			'align-agent',
		];

		foreach ( $align_submenus as $slug ) {
			remove_submenu_page( 'options-general.php', $slug );
		}
	}

	/**
	 * Seamlessly redirect old Settings > Align Settings or Align Files URLs if needed.
	 */
	public static function handle_legacy_redirects(): void {
		global $pagenow;
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) ) {
			$page = sanitize_key( $_GET['page'] );
			if ( 'align-settings' === $page ) {
				$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
				$target = admin_url( 'admin.php?page=' . self::MENU_SLUG . ( $tab ? '&tab=' . $tab : '' ) );
				wp_safe_redirect( $target );
				exit;
			}
		}
	}
}
