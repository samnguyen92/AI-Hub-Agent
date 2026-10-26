<?php
/**
 * Align Agent - URL Redirection & 404 Log Management Tools
 *
 * Registers tools for managing Rank Math redirections and Align 404 error logs:
 * - list_redirects, create_redirect, delete_redirect
 * - list_404_logs, create_redirect_from_404
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Redirects {

	const OPTION_404_LOGS = 'align_404_logs';

	/** Initialize redirection and 404 log tools. */
	public static function init(): void {
		self::register_redirect_tools();
		self::register_404_tools();
	}

	/** Check if Rank Math Redirections module is active. */
	public static function is_rank_math_active(): bool {
		if ( class_exists( 'Align_Redirection_Importer' ) && method_exists( 'Align_Redirection_Importer', 'is_rank_math_active' ) ) {
			return Align_Redirection_Importer::is_rank_math_active();
		}

		try {
			if ( ! class_exists( '\RankMath\Helper' ) || ! method_exists( '\RankMath\Helper', 'is_module_active' ) ) {
				return false;
			}
			return \RankMath\Helper::is_module_active( 'redirections' ) && class_exists( '\RankMath\Redirections\DB' );
		} catch ( \Throwable $e ) {
			error_log( '[Align Agent] Rank Math active check failed: ' . $e->getMessage() );
			return false;
		}
	}

	/** Purge Rank Math redirection cache table. */
	public static function purge_rankmath_cache(): void {
		if ( class_exists( 'Align_Redirection_Importer' ) && method_exists( 'Align_Redirection_Importer', 'purge_rankmath_cache' ) ) {
			Align_Redirection_Importer::purge_rankmath_cache();
			return;
		}

		try {
			global $wpdb;
			if ( isset( $wpdb->prefix ) ) {
				$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}rank_math_redirections_cache" );
			}
		} catch ( \Throwable $e ) {
			error_log( '[Align Agent] Failed to truncate Rank Math cache: ' . $e->getMessage() );
		}
	}

	/** Register Rank Math redirection tools when active. */
	private static function register_redirect_tools(): void {
		if ( 'rank_math' !== Align_Agent_Seo_Adapters::get_provider() || ! self::is_rank_math_active() ) {
			return;
		}

		Align_Agent_Tool_Registry::register( 'list_redirects', array(
			'type'        => 'read',
			'group'       => 'seo',
			'capability'  => 'manage_options',
			'description' => 'List active URL redirection rules configured in Rank Math SEO with pagination and search filter.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'search'   => array( 'type' => 'string', 'description' => 'Optional search query matching source or target URL.' ),
					'page'     => array( 'type' => 'integer', 'description' => 'Page number (default 1).' ),
					'per_page' => array( 'type' => 'integer', 'description' => 'Rules per page (default 20, max 100).' ),
				),
			),
			'callback'    => array( __CLASS__, 'tool_list_redirects' ),
		) );

		Align_Agent_Tool_Registry::register( 'create_redirect', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'create' ),
			'group'            => 'seo',
			'capability'       => 'manage_options',
			'description'      => 'Create a new URL redirection rule in Rank Math SEO (301, 302, 307, 410, 451). Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'source'      => array( 'type' => 'string', 'description' => 'Source relative path or URL (e.g. /old-page).' ),
					'destination' => array( 'type' => 'string', 'description' => 'Destination path or URL (e.g. /new-page).' ),
					'code'        => array( 'type' => 'integer', 'description' => 'HTTP status code: 301 (default), 302, 307, 410, 451.' ),
				),
				'required'   => array( 'source', 'destination' ),
			),
			'callback'         => array( __CLASS__, 'tool_create_redirect' ),
			'summary_callback' => array( __CLASS__, 'summarize_create_redirect' ),
		) );

		Align_Agent_Tool_Registry::register( 'delete_redirect', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'trash', 'id_arg' => 'id' ),
			'group'            => 'seo',
			'capability'       => 'manage_options',
			'description'      => 'Delete an existing URL redirection rule from Rank Math SEO by its numeric ID. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Numeric redirection ID to delete.' ) ),
				'required'   => array( 'id' ),
			),
			'callback'         => array( __CLASS__, 'tool_delete_redirect' ),
			'summary_callback' => array( __CLASS__, 'summarize_delete_redirect' ),
		) );
	}

	/** Register 404 error log inspection and resolution tools. */
	private static function register_404_tools(): void {
		Align_Agent_Tool_Registry::register( 'list_404_logs', array(
			'type'        => 'read',
			'group'       => 'seo',
			'capability'  => 'manage_options',
			'description' => 'List captured 404 Not Found error logs with request paths, referrers, hit counts, and timestamps.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'search'   => array( 'type' => 'string', 'description' => 'Optional filter matching request path or referrer.' ),
					'page'     => array( 'type' => 'integer', 'description' => 'Page number (default 1).' ),
					'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (default 20, max 50).' ),
				),
			),
			'callback'    => array( __CLASS__, 'tool_list_404_logs' ),
		) );

		if ( self::is_rank_math_active() ) {
			Align_Agent_Tool_Registry::register( 'create_redirect_from_404', array(
				'type'             => 'write',
				'undo'             => array( 'adapter' => 'create' ),
				'group'            => 'seo',
				'capability'       => 'manage_options',
				'description'      => 'Create a 301 redirection in Rank Math for a 404 URL and purge that entry from 404 logs. Requires user approval.',
				'parameters'       => array(
					'type'       => 'object',
					'properties' => array(
						'uri'         => array( 'type' => 'string', 'description' => '404 request path or URL to resolve.' ),
						'destination' => array( 'type' => 'string', 'description' => 'Target destination path or full URL.' ),
						'code'        => array( 'type' => 'integer', 'description' => 'HTTP status code: 301 (default), 302, or 307.' ),
					),
					'required'   => array( 'uri', 'destination' ),
				),
				'callback'         => array( __CLASS__, 'tool_create_redirect_from_404' ),
				'summary_callback' => array( __CLASS__, 'summarize_create_redirect_from_404' ),
			) );
		}
	}

	/** Execute list_redirects tool. */
	public static function tool_list_redirects( array $args = array() ) {
		if ( ! self::is_rank_math_active() ) {
			return new WP_Error( 'plugin_not_active', 'Rank Math SEO Redirections module is inactive.' );
		}

		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, absint( $args['per_page'] ?? 20 ) ) );
		$search   = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';

		try {
			$query_args = array(
				'status' => 'any',
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
				'order'  => 'DESC',
			);
			if ( '' !== $search ) {
				$query_args['search'] = $search;
			}

			$data  = \RankMath\Redirections\DB::get_redirections( $query_args );
			$items = array();
			$rows  = $data['redirections'] ?? array();

			if ( is_iterable( $rows ) ) {
				foreach ( $rows as $r ) {
					$sources = maybe_unserialize( $r['sources'] );
					$pattern = '';
					if ( is_array( $sources ) && ! empty( $sources[0]['pattern'] ) ) {
						$pattern = '/' . ltrim( (string) $sources[0]['pattern'], '/' );
					}
					$items[] = array(
						'id'          => (int) ( $r['id'] ?? 0 ),
						'source'      => $pattern,
						'destination' => (string) ( $r['url_to'] ?? '' ),
						'code'        => (int) ( $r['header_code'] ?? 301 ),
						'status'      => (string) ( $r['status'] ?? 'active' ),
						'hits'        => (int) ( $r['hits'] ?? 0 ),
						'created'     => (string) ( $r['created'] ?? '' ),
					);
				}
			}

			return array(
				'success'      => true,
				'total'        => (int) ( $data['count'] ?? count( $items ) ),
				'page'         => $page,
				'per_page'     => $per_page,
				'redirections' => $items,
			);
		} catch ( \Throwable $e ) {
			error_log( '[Align Agent] list_redirects error: ' . $e->getMessage() );
			return new WP_Error( 'db_error', $e->getMessage() );
		}
	}

	/** Execute create_redirect tool. */
	public static function tool_create_redirect( array $args = array() ) {
		if ( ! self::is_rank_math_active() ) {
			return new WP_Error( 'plugin_not_active', 'Rank Math SEO Redirections module is inactive.' );
		}

		$source = isset( $args['source'] ) ? sanitize_text_field( wp_unslash( (string) $args['source'] ) ) : '';
		$dest   = isset( $args['destination'] ) ? sanitize_text_field( wp_unslash( (string) $args['destination'] ) ) : '';
		$code   = isset( $args['code'] ) ? absint( $args['code'] ) : 301;

		if ( empty( $source ) || empty( $dest ) ) {
			return new WP_Error( 'missing_param', 'Both source and destination URLs are required.' );
		}

		$parsed_source = wp_parse_url( $source, PHP_URL_PATH );
		$clean_source  = $parsed_source ? '/' . ltrim( $parsed_source, '/' ) : $source;
		$clean_dest    = trim( $dest );
		$home          = home_url();
		if ( 0 === strpos( $clean_dest, $home ) ) {
			$clean_dest = substr( $clean_dest, strlen( $home ) );
			$clean_dest = '/' . ltrim( $clean_dest, '/' );
		}

		if ( rtrim( $clean_source, '/' ) === rtrim( $clean_dest, '/' ) ) {
			return array(
				'success' => false,
				'error'   => 'redirect_loop',
				'message' => __( 'Source and destination URLs cannot be identical.', 'align-agent' ),
			);
		}

		// Prevent redirect chain/loop: destination cannot already be configured as a redirection source.
		try {
			if ( class_exists( '\RankMath\Redirections\DB' ) && method_exists( '\RankMath\Redirections\DB', 'match_redirections' ) ) {
				$existing_match = \RankMath\Redirections\DB::match_redirections( $clean_dest );
				if ( ! empty( $existing_match ) ) {
					return array(
						'success' => false,
						'error'   => 'redirect_loop',
						'message' => sprintf( __( 'Redirect loop detected: destination "%s" is already configured as a redirection source.', 'align-agent' ), $clean_dest ),
					);
				}
			}
		} catch ( \Throwable $e ) {
			// Continue if match_redirections lookup fails.
		}

		try {
			$clean_pattern = \RankMath\Redirections\DB::get_clean_pattern( $clean_source, 'exact' );
			$sources       = array( array( 'pattern' => $clean_pattern, 'comparison' => 'exact', 'ignore' => '' ) );

			$insert_id = \RankMath\Redirections\DB::add( array(
				'sources'     => $sources,
				'url_to'      => $clean_dest,
				'header_code' => $code,
				'status'      => 'active',
			) );

			if ( ! $insert_id ) {
				return new WP_Error( 'insert_failed', 'Failed to create redirection in Rank Math.' );
			}

			self::purge_rankmath_cache();
			error_log( sprintf( '[Align Agent] Created %d redirect from %s to %s (ID: %d)', $code, $clean_source, $clean_dest, $insert_id ) );

			return array(
				'success'     => true,
				'redirect_id' => (int) $insert_id,
				'source'      => $clean_source,
				'destination' => $clean_dest,
				'code'        => $code,
				'message'     => sprintf( 'Created %d redirect from %s to %s (#%d).', $code, $clean_source, $clean_dest, $insert_id ),
				'ui_card'     => array(
					'title'   => sprintf( 'Created Redirection #%d', $insert_id ),
					'message' => sprintf( '%s -> %s (%d)', $clean_source, $clean_dest, $code ),
					'url'     => admin_url( 'admin.php?page=rank-math-redirections' ),
				),
			);
		} catch ( \Throwable $e ) {
			error_log( '[Align Agent] create_redirect error: ' . $e->getMessage() );
			return new WP_Error( 'db_error', $e->getMessage() );
		}
	}

	/** Execute delete_redirect tool. */
	public static function tool_delete_redirect( array $args = array() ) {
		if ( ! self::is_rank_math_active() ) {
			return new WP_Error( 'plugin_not_active', 'Rank Math SEO Redirections module is inactive.' );
		}

		$id = absint( $args['id'] ?? 0 );
		if ( $id <= 0 ) {
			return new WP_Error( 'invalid_id', 'A valid numeric redirection ID is required.' );
		}

		try {
			$res = \RankMath\Redirections\DB::delete( $id );
			if ( ! $res ) {
				return new WP_Error( 'delete_failed', sprintf( 'Failed to delete redirection rule #%d.', $id ) );
			}

			self::purge_rankmath_cache();
			error_log( sprintf( '[Align Agent] Deleted Rank Math redirect #%d.', $id ) );

			return array(
				'success' => true,
				'id'      => $id,
				'message' => sprintf( 'Deleted redirection rule #%d.', $id ),
				'ui_card' => array(
					'title'   => sprintf( 'Deleted Redirection #%d', $id ),
					'message' => sprintf( 'Redirection rule #%d was removed from Rank Math.', $id ),
					'url'     => admin_url( 'admin.php?page=rank-math-redirections' ),
				),
			);
		} catch ( \Throwable $e ) {
			error_log( '[Align Agent] delete_redirect error: ' . $e->getMessage() );
			return new WP_Error( 'db_error', $e->getMessage() );
		}
	}

	/** Execute list_404_logs tool. */
	public static function tool_list_404_logs( array $args = array() ): array {
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, absint( $args['per_page'] ?? 20 ) ) );
		$search   = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';

		$logs = get_option( self::OPTION_404_LOGS, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		if ( '' !== $search ) {
			$logs = array_filter( $logs, function( $item ) use ( $search ) {
				$path    = strtolower( (string) ( $item['path'] ?? '' ) );
				$referer = strtolower( (string) ( $item['referer'] ?? '' ) );
				return false !== strpos( $path, $search ) || false !== strpos( $referer, $search );
			} );
		}

		$total  = count( $logs );
		$offset = ( $page - 1 ) * $per_page;
		$slice  = array_slice( array_values( $logs ), $offset, $per_page );

		return array(
			'success'  => true,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'logs'     => $slice,
		);
	}

	/** Execute create_redirect_from_404 tool. */
	public static function tool_create_redirect_from_404( array $args = array() ) {
		if ( ! self::is_rank_math_active() ) {
			return new WP_Error( 'plugin_not_active', 'Rank Math SEO Redirections module is inactive.' );
		}

		$uri  = isset( $args['uri'] ) ? sanitize_text_field( wp_unslash( (string) $args['uri'] ) ) : '';
		$dest = isset( $args['destination'] ) ? sanitize_text_field( wp_unslash( (string) $args['destination'] ) ) : '';
		$code = isset( $args['code'] ) ? absint( $args['code'] ) : 301;

		if ( empty( $uri ) || empty( $dest ) ) {
			return new WP_Error( 'missing_param', 'Both 404 URI and destination URL are required.' );
		}

		$res = self::tool_create_redirect( array(
			'source'      => $uri,
			'destination' => $dest,
			'code'        => $code,
		) );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$parsed_source = wp_parse_url( $uri, PHP_URL_PATH );
		$clean_source  = $parsed_source ? '/' . ltrim( $parsed_source, '/' ) : $uri;

		$logs = get_option( self::OPTION_404_LOGS, array() );
		if ( is_array( $logs ) ) {
			$filtered = array_filter( $logs, function( $item ) use ( $uri, $clean_source ) {
				$p = $item['path'] ?? '';
				return ( $p !== $uri && $p !== $clean_source && trim( (string) $p, '/' ) !== trim( (string) $uri, '/' ) );
			} );
			update_option( self::OPTION_404_LOGS, array_values( $filtered ), false );
		}

		$res['resolved_404_uri'] = $uri;
		$res['message']          = sprintf( 'Resolved 404 for "%s" with %d redirect to "%s" and cleared from 404 logs.', $uri, $code, $dest );

		return $res;
	}

	/** Summarize create_redirect for approval card. */
	public static function summarize_create_redirect( array $args ): array {
		$source = sanitize_text_field( (string) ( $args['source'] ?? '' ) );
		$dest   = sanitize_text_field( (string) ( $args['destination'] ?? '' ) );
		$code   = absint( $args['code'] ?? 301 );
		return array(
			'title'        => __( 'Create URL Redirection Rule', 'align-agent' ),
			'description'  => sprintf( __( 'Redirect %1$s -> %2$s with HTTP status code %3$d in Rank Math SEO.', 'align-agent' ), $source, $dest, $code ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'source' => $source, 'destination' => $dest, 'code' => $code ),
		);
	}

	/** Summarize delete_redirect for approval card. */
	public static function summarize_delete_redirect( array $args ): array {
		$id = absint( $args['id'] ?? 0 );
		return array(
			'title'        => sprintf( __( 'Delete Redirection Rule #%d', 'align-agent' ), $id ),
			'description'  => sprintf( __( 'Permanently remove redirection rule #%d from Rank Math database.', 'align-agent' ), $id ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'id' => $id ),
			'danger'       => true,
		);
	}

	/** Summarize create_redirect_from_404 for approval card. */
	public static function summarize_create_redirect_from_404( array $args ): array {
		$uri  = sanitize_text_field( (string) ( $args['uri'] ?? '' ) );
		$dest = sanitize_text_field( (string) ( $args['destination'] ?? '' ) );
		$code = absint( $args['code'] ?? 301 );
		return array(
			'title'        => __( 'Resolve 404 URL with Redirection', 'align-agent' ),
			'description'  => sprintf( __( 'Create %1$d redirect from 404 URI %2$s to %3$s and clear URI from 404 logs.', 'align-agent' ), $code, $uri, $dest ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'uri' => $uri, 'destination' => $dest, 'code' => $code ),
		);
	}
}
