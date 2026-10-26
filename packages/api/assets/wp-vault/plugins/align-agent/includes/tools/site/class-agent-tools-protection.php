<?php
/**
 * Align Agent Site Protection Tools
 *
 * Provides inspection and modification of Align theme site protection settings.
 * Strictly disallows modifying credentials (user/password) through agent tools.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools\Site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Protection {

	/**
	 * Map of writable property names to option keys.
	 *
	 * @var array<string, string>
	 */
	private const WRITABLE = array(
		'enabled'          => 'align_site_protect_enabled',
		'live_domain'      => 'align_site_protect_live_domain',
		'staging_domain'   => 'align_site_protect_staging_domain',
		'auto_noindex'     => 'align_site_protect_auto_noindex',
		'bypass_localhost' => 'align_site_protect_bypass_localhost',
		'bypass_admins'    => 'align_site_protect_bypass_admins',
		'cookie_days'      => 'align_site_protect_cookie_days',
	);

	/**
	 * Forbidden credential properties.
	 *
	 * @var array<string>
	 */
	private const FORBIDDEN = array( 'user', 'password' );

	/**
	 * Register site protection tools into registry.
	 *
	 * @return void
	 */
	public static function init(): void {
		Align_Agent_Tool_Registry::register( 'get_site_protection', array(
			'type'             => 'read',
			'group'            => 'site',
			'scope'            => 'local',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => false,
			'description'      => 'Retrieve site protection settings (enabled, domains, auto_noindex, bypass rules, cookie duration, environment, credential status). Credentials are never exposed.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(),
			),
			'callback'         => array( __CLASS__, 'tool_get_site_protection' ),
		) );

		Align_Agent_Tool_Registry::register( 'set_site_protection', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'option', 'keys' => array( 'align_site_protect_enabled', 'align_site_protect_live_domain', 'align_site_protect_staging_domain', 'align_site_protect_auto_noindex', 'align_site_protect_bypass_localhost', 'align_site_protect_bypass_admins', 'align_site_protect_cookie_days' ) ),
			'group'            => 'site',
			'scope'            => 'global',
			'capability'       => 'manage_options',
			'requires_builder' => false,
			'danger'           => true,
			'description'      => 'Configure site protection settings (enable/disable, domains, auto_noindex, bypass options, cookie days). Modifying username or password is prohibited. Requires user approval.',
			'parameters'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'enabled'          => array( 'type' => 'boolean', 'description' => 'Enable or disable HTTP basic protection.' ),
					'live_domain'      => array( 'type' => 'string', 'description' => 'Production / live domain.' ),
					'staging_domain'   => array( 'type' => 'string', 'description' => 'Staging domain requiring protection.' ),
					'auto_noindex'     => array( 'type' => 'boolean', 'description' => 'Automatically discourage search engines on staging.' ),
					'bypass_localhost' => array( 'type' => 'boolean', 'description' => 'Bypass protection on localhost and local environments.' ),
					'bypass_admins'    => array( 'type' => 'boolean', 'description' => 'Bypass protection for logged-in WordPress administrators.' ),
					'cookie_days'      => array( 'type' => 'integer', 'description' => 'Authentication cookie validity in days (1..365).' ),
				),
				'additionalProperties' => false,
			),
			'callback'         => array( __CLASS__, 'tool_set_site_protection' ),
			'summary_callback' => array( __CLASS__, 'summarize_set_site_protection' ),
		) );
	}

	/**
	 * Callback for get_site_protection.
	 */
	public static function tool_get_site_protection( array $args = array() ): array {
		$current_host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
		$environment  = class_exists( 'Align_Site_Protection' ) && method_exists( 'Align_Site_Protection', 'get_current_environment' )
			? Align_Site_Protection::get_current_environment()
			: wp_get_environment_type();

		$data = array(
			'enabled'          => (bool) get_option( self::WRITABLE['enabled'], false ),
			'live_domain'      => (string) get_option( self::WRITABLE['live_domain'], '' ),
			'staging_domain'   => (string) get_option( self::WRITABLE['staging_domain'], '' ),
			'auto_noindex'     => (bool) get_option( self::WRITABLE['auto_noindex'], true ),
			'bypass_localhost' => (bool) get_option( self::WRITABLE['bypass_localhost'], true ),
			'bypass_admins'    => (bool) get_option( self::WRITABLE['bypass_admins'], true ),
			'cookie_days'      => (int) get_option( self::WRITABLE['cookie_days'], 30 ),
			'current_host'     => $current_host,
			'environment'      => $environment,
			'username_set'     => '' !== trim( (string) get_option( 'align_site_protect_user', '' ) ),
			'password_set'     => '' !== trim( (string) get_option( 'align_site_protect_password', '' ) ),
		);

		return array(
			'success'  => true,
			'settings' => $data,
			'message'  => 'Retrieved site protection settings successfully.',
		);
	}

	/**
	 * Callback for set_site_protection.
	 */
	public static function tool_set_site_protection( array $args ): array {
		foreach ( self::FORBIDDEN as $f ) {
			if ( array_key_exists( $f, $args ) ) {
				return array(
					'success' => false,
					'error'   => 'credential_fields_forbidden',
					'message' => 'Set the protection username and password in Align Settings → Site Protection.',
				);
			}
		}

		if ( empty( $args ) ) {
			return array(
				'success' => false,
				'error'   => 'empty_payload',
				'message' => 'No settings provided to update.',
			);
		}

		$changes = array();

		foreach ( $args as $key => $val ) {
			if ( ! isset( self::WRITABLE[ $key ] ) ) {
				return array(
					'success' => false,
					'error'   => 'field_not_allowed',
					'message' => sprintf( 'Field "%s" is not allowed.', $key ),
				);
			}

			$option_key = self::WRITABLE[ $key ];

			switch ( $key ) {
				case 'enabled':
				case 'auto_noindex':
				case 'bypass_localhost':
				case 'bypass_admins':
					$changes[ $option_key ] = ! empty( $val ) ? 1 : 0;
					break;

				case 'cookie_days':
					$days = (int) $val;
					if ( $days < 1 || $days > 365 ) {
						return array(
							'success' => false,
							'error'   => 'out_of_range',
							'message' => 'cookie_days must be between 1 and 365.',
						);
					}
					$changes[ $option_key ] = $days;
					break;

				case 'live_domain':
				case 'staging_domain':
					$changes[ $option_key ] = sanitize_text_field( (string) $val );
					break;
			}
		}

		// Guard: If enabling protection, password must already be configured in DB.
		if ( isset( $changes[ self::WRITABLE['enabled'] ] ) && 1 === $changes[ self::WRITABLE['enabled'] ] ) {
			$pw = (string) get_option( 'align_site_protect_password', '' );
			if ( '' === trim( $pw ) ) {
				return array(
					'success' => false,
					'error'   => 'password_not_configured',
					'message' => 'Cannot enable site protection before configuring a password in Align Settings.',
				);
			}
		}

		$write_result = Align_Agent_Option_Writer::write( 'set_site_protection', $changes );

		return array(
			'success' => true,
			'changed' => $write_result['changed'],
			'message' => sprintf( 'Site protection settings updated (%d option(s) modified).', count( $write_result['changed'] ) ),
			'ui_card' => array(
				'title'   => 'Site Protection Updated',
				'message' => sprintf( 'Modified: %s.', implode( ', ', $write_result['changed'] ) ),
			),
		);
	}

	/**
	 * Provide approval summary for set_site_protection.
	 */
	public static function summarize_set_site_protection( array $args ): array {
		$warnings     = array();
		$changes_desc = array();
		$current_host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: '';
		$live_domain  = (string) get_option( self::WRITABLE['live_domain'], '' );

		foreach ( $args as $k => $v ) {
			if ( isset( self::WRITABLE[ $k ] ) ) {
				$old = get_option( self::WRITABLE[ $k ] );
				$changes_desc[] = sprintf( '%s: "%s" → "%s"', $k, (string) $old, (string) $v );
			}
		}

		if ( ! empty( $args['enabled'] ) && '' !== $live_domain && $current_host === $live_domain ) {
			$warnings[] = '⚠️ Visitors on the LIVE domain will need a password to view the website.';
		}

		if ( isset( $args['auto_noindex'] ) && empty( $args['auto_noindex'] ) ) {
			$warnings[] = '⚠️ Staging pages may appear in search results.';
		}

		$desc = implode( "\n", $changes_desc );
		if ( ! empty( $warnings ) ) {
			$desc .= "\n\n" . implode( "\n", $warnings );
		}

		return array(
			'title'       => 'Update Site Protection',
			'description' => substr( $desc, 0, 1500 ),
			'meta'        => array( 'keys' => array_keys( $args ) ),
		);
	}
}
