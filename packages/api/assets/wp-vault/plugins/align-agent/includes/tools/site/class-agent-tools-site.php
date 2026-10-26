<?php
/**
 * Align Agent - Site Identity, Homepage, and Operations Tools
 *
 * Registers write tools (set_homepage, update_site_identity, flush_cache)
 * and read tool (site_overview) for site-level management.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Site {

	/** Register all site and ops tools with Align_Agent_Tool_Registry. */
	public static function init(): void {
		self::register_site_tools();
		self::register_ops_tools();
	}

	/** Register site configuration tools. */
	private static function register_site_tools(): void {
		Align_Agent_Tool_Registry::register( 'set_homepage', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'option', 'keys' => array( 'page_on_front', 'page_for_posts', 'show_on_front' ) ),
			'requires_builder' => false,
			'group'            => 'structure',
			'capability'       => 'manage_options',
			'description'      => 'Configure WordPress front page display (show latest posts or static page) and assign homepage and blog posts page IDs. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'show_on_front'  => array( 'type' => 'string', 'enum' => array( 'posts', 'page' ), 'description' => 'Show latest "posts" or a static "page".' ),
					'page_on_front'  => array( 'type' => 'integer', 'description' => 'WordPress page ID to set as static front page.' ),
					'page_for_posts' => array( 'type' => 'integer', 'description' => 'WordPress page ID to set as posts page (blog archive).' ),
				),
				'required'   => array( 'show_on_front' ),
			),
			'callback'         => array( __CLASS__, 'tool_set_homepage' ),
			'summary_callback' => array( __CLASS__, 'summarize_set_homepage' ),
		) );

		Align_Agent_Tool_Registry::register( 'update_site_identity', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'option', 'keys' => array( 'blogname', 'blogdescription', 'site_icon', 'custom_logo' ) ),
			'requires_builder' => false,
			'group'            => 'structure',
			'capability'       => 'manage_options',
			'description'      => 'Update WordPress site identity: title (blogname), tagline (blogdescription), site icon (site_icon), and custom logo (custom_logo). Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'blogname'        => array( 'type' => 'string', 'description' => 'Site title.' ),
					'blogdescription' => array( 'type' => 'string', 'description' => 'Site tagline.' ),
					'site_icon'       => array( 'type' => 'integer', 'description' => 'Attachment ID of image for site icon (favicon).' ),
					'custom_logo'     => array( 'type' => 'integer', 'description' => 'Attachment ID of image for theme custom logo.' ),
				),
			),
			'callback'         => array( __CLASS__, 'tool_update_site_identity' ),
			'summary_callback' => array( __CLASS__, 'summarize_update_site_identity' ),
		) );
	}

	/** Register ops and maintenance tools. */
	private static function register_ops_tools(): void {
		Align_Agent_Tool_Registry::register( 'flush_cache', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'none' ),
			'requires_builder' => false,
			'group'            => 'ops',
			'capability'       => 'manage_options',
			'description'      => 'Flush WordPress object cache, transient data, OPcache, and builder assets. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'flush_rewrites' => array( 'type' => 'boolean', 'description' => 'Optionally flush rewrite rules (default false).' ),
				),
			),
			'callback'         => array( __CLASS__, 'tool_flush_cache' ),
			'summary_callback' => array( __CLASS__, 'summarize_flush_cache' ),
		) );

		Align_Agent_Tool_Registry::register( 'site_overview', array(
			'type'        => 'read',
			'group'       => 'ops',
			'capability'  => 'manage_options',
			'description' => 'Retrieve site status summary including post/page counts, comment counts, recent 404 logs, pending core updates, and versions.',
			'parameters'  => array( 'type' => 'object', 'properties' => new stdClass() ),
			'callback'    => array( __CLASS__, 'tool_site_overview' ),
		) );
	}

	/** Build approval summary for set_homepage. */
	public static function summarize_set_homepage( array $args ): array {
		$show_on_front  = sanitize_key( (string) ( $args['show_on_front'] ?? get_option( 'show_on_front', 'posts' ) ) );
		$page_on_front  = absint( $args['page_on_front'] ?? get_option( 'page_on_front', 0 ) );
		$page_for_posts = absint( $args['page_for_posts'] ?? get_option( 'page_for_posts', 0 ) );

		$front_title = $page_on_front > 0 ? (string) get_the_title( $page_on_front ) : __( 'None', 'align-agent' );
		$posts_title = $page_for_posts > 0 ? (string) get_the_title( $page_for_posts ) : __( 'None', 'align-agent' );

		return array(
			'title'        => __( 'Update Homepage Settings', 'align-agent' ),
			'description'  => sprintf(
				__( 'Set front page display to "%1$s" (Homepage: #%2$d "%3$s", Posts page: #%4$d "%5$s").', 'align-agent' ),
				$show_on_front,
				$page_on_front,
				$front_title,
				$page_for_posts,
				$posts_title
			),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array(
				'show_on_front'  => $show_on_front,
				'page_on_front'  => $page_on_front,
				'page_for_posts' => $page_for_posts,
			),
		);
	}

	/** Build approval summary for update_site_identity. */
	public static function summarize_update_site_identity( array $args ): array {
		$changes = array();
		if ( isset( $args['blogname'] ) ) {
			$changes[] = sprintf( __( 'Title: "%s"', 'align-agent' ), sanitize_text_field( (string) $args['blogname'] ) );
		}
		if ( isset( $args['blogdescription'] ) ) {
			$changes[] = sprintf( __( 'Tagline: "%s"', 'align-agent' ), sanitize_text_field( (string) $args['blogdescription'] ) );
		}
		if ( isset( $args['site_icon'] ) ) {
			$changes[] = sprintf( __( 'Site Icon: #%d', 'align-agent' ), absint( $args['site_icon'] ) );
		}
		if ( isset( $args['custom_logo'] ) ) {
			$changes[] = sprintf( __( 'Logo: #%d', 'align-agent' ), absint( $args['custom_logo'] ) );
		}

		return array(
			'title'        => __( 'Update Site Identity', 'align-agent' ),
			'description'  => ! empty( $changes ) ? implode( ' | ', $changes ) : __( 'Update site identity settings.', 'align-agent' ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => $args,
		);
	}

	/** Build approval summary for flush_cache. */
	public static function summarize_flush_cache( array $args ): array {
		$rewrites = ! empty( $args['flush_rewrites'] );
		return array(
			'title'        => __( 'Flush System Cache', 'align-agent' ),
			'description'  => $rewrites
				? __( 'Flush object cache, transients, OPcache, and rewrite rules.', 'align-agent' )
				: __( 'Flush object cache, transients, and OPcache.', 'align-agent' ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'flush_rewrites' => $rewrites ),
		);
	}

	/** Tool: set_homepage. */
	public static function tool_set_homepage( array $args = array() ): array {
		$show_on_front = sanitize_key( (string) ( $args['show_on_front'] ?? '' ) );
		if ( ! in_array( $show_on_front, array( 'posts', 'page' ), true ) ) {
			return array( 'success' => false, 'error' => 'invalid_mode', 'message' => __( 'show_on_front must be either "posts" or "page".', 'align-agent' ) );
		}

		$keys     = array( 'show_on_front', 'page_on_front', 'page_for_posts' );
		$snapshot = class_exists( 'Align_Agent_Audit_Log' ) ? Align_Agent_Audit_Log::snapshot_options( $keys ) : array();

		update_option( 'show_on_front', $show_on_front );

		if ( 'page' === $show_on_front && isset( $args['page_on_front'] ) ) {
			$page_on_front = absint( $args['page_on_front'] );
			if ( $page_on_front > 0 && get_post( $page_on_front ) ) {
				update_option( 'page_on_front', $page_on_front );
			}
		}

		if ( isset( $args['page_for_posts'] ) ) {
			$page_for_posts = absint( $args['page_for_posts'] );
			if ( $page_for_posts >= 0 ) {
				update_option( 'page_for_posts', $page_for_posts );
			}
		}

		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			Align_Agent_Audit_Log::log_action(
				get_current_user_id(),
				'set_homepage',
				'homepage_settings',
				'update',
				array( 'before' => $snapshot, 'after' => $args )
			);
		}

		error_log( sprintf( '[Align Agent] Homepage settings updated: show_on_front=%s, front=%d, posts=%d', $show_on_front, (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ) );

		return array(
			'success'        => true,
			'show_on_front'  => get_option( 'show_on_front' ),
			'page_on_front'  => (int) get_option( 'page_on_front' ),
			'page_for_posts' => (int) get_option( 'page_for_posts' ),
			'message'        => __( 'Homepage configuration updated successfully.', 'align-agent' ),
			'ui_card'        => array(
				'title'   => __( 'Homepage Configuration Saved', 'align-agent' ),
				'message' => sprintf(
					__( 'Mode: %1$s | Front Page ID: #%2$d | Posts Page ID: #%3$d', 'align-agent' ),
					get_option( 'show_on_front' ),
					(int) get_option( 'page_on_front' ),
					(int) get_option( 'page_for_posts' )
				),
			),
		);
	}

	/** Tool: update_site_identity. */
	public static function tool_update_site_identity( array $args = array() ): array {
		if ( empty( $args ) ) {
			return array( 'success' => false, 'error' => 'empty_payload', 'message' => __( 'At least one identity field must be provided.', 'align-agent' ) );
		}

		$keys     = array( 'blogname', 'blogdescription', 'site_icon' );
		$snapshot = class_exists( 'Align_Agent_Audit_Log' ) ? Align_Agent_Audit_Log::snapshot_options( $keys ) : array();
		$snapshot['custom_logo'] = get_theme_mod( 'custom_logo', 0 );

		$updated_fields = array();

		if ( isset( $args['blogname'] ) ) {
			$blogname = sanitize_text_field( (string) $args['blogname'] );
			update_option( 'blogname', $blogname );
			$updated_fields['blogname'] = $blogname;
		}

		if ( isset( $args['blogdescription'] ) ) {
			$blogdescription = sanitize_text_field( (string) $args['blogdescription'] );
			update_option( 'blogdescription', $blogdescription );
			$updated_fields['blogdescription'] = $blogdescription;
		}

		if ( isset( $args['site_icon'] ) ) {
			$site_icon = absint( $args['site_icon'] );
			update_option( 'site_icon', $site_icon );
			$updated_fields['site_icon'] = $site_icon;
		}

		if ( isset( $args['custom_logo'] ) ) {
			$custom_logo = absint( $args['custom_logo'] );
			set_theme_mod( 'custom_logo', $custom_logo );
			$updated_fields['custom_logo'] = $custom_logo;
		}

		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			Align_Agent_Audit_Log::log_action(
				get_current_user_id(),
				'update_site_identity',
				'site_identity',
				'update',
				array( 'before' => $snapshot, 'after' => $updated_fields )
			);
		}

		error_log( sprintf( '[Align Agent] Site identity updated: %s', implode( ', ', array_keys( $updated_fields ) ) ) );

		return array(
			'success'        => true,
			'updated_fields' => $updated_fields,
			'blogname'       => get_option( 'blogname' ),
			'blogdescription'=> get_option( 'blogdescription' ),
			'site_icon'      => (int) get_option( 'site_icon' ),
			'custom_logo'    => (int) get_theme_mod( 'custom_logo', 0 ),
			'message'        => __( 'Site identity updated successfully.', 'align-agent' ),
			'ui_card'        => array(
				'title'   => __( 'Site Identity Updated', 'align-agent' ),
				'message' => sprintf( __( 'Updated: %s', 'align-agent' ), implode( ', ', array_keys( $updated_fields ) ) ),
			),
		);
	}

	/** Tool: flush_cache. */
	public static function tool_flush_cache( array $args = array() ): array {
		$request = new WP_REST_Request( 'POST', '/align-agent/v1/cache/flush' );
		if ( ! empty( $args['flush_rewrites'] ) ) {
			$request->set_param( 'flush_rewrites', true );
		}

		$response = class_exists( 'Align_Gateway_System' )
			? Align_Gateway_System::handle_cache_flush( $request )
			: null;

		$data = ( $response instanceof WP_REST_Response ) ? $response->get_data() : array( 'success' => true );

		if ( class_exists( 'Align_Agent_Audit_Log' ) ) {
			Align_Agent_Audit_Log::log_action(
				get_current_user_id(),
				'flush_cache',
				'system_cache',
				'flush',
				is_array( $data ) ? $data : array()
			);
		}

		return array(
			'success'   => true,
			'message'   => __( 'System cache flushed successfully.', 'align-agent' ),
			'data'      => $data,
			'ui_card'   => array(
				'title'   => __( 'System Cache Flushed', 'align-agent' ),
				'message' => __( 'Object cache, transient data, and theme caches cleared.', 'align-agent' ),
			),
		);
	}

	/** Tool: site_overview. */
	public static function tool_site_overview( array $args = array() ): array {
		$post_counts    = (array) wp_count_posts( 'post' );
		$page_counts    = (array) wp_count_posts( 'page' );
		$comment_counts = (array) wp_count_comments();

		$raw_404 = array();
		if ( class_exists( 'Align_404_Collector' ) && defined( 'Align_404_Collector::OPTION_KEY' ) ) {
			$raw_404 = get_option( Align_404_Collector::OPTION_KEY, array() );
		} else {
			$raw_404 = get_option( 'align_404_logs', array() );
		}
		$recent_404 = is_array( $raw_404 ) ? array_slice( $raw_404, 0, 10 ) : array();

		if ( ! function_exists( 'get_core_updates' ) ) {
			$update_file = ABSPATH . 'wp-admin/includes/update.php';
			if ( file_exists( $update_file ) ) {
				require_once $update_file;
			}
		}
		$raw_updates   = function_exists( 'get_core_updates' ) ? get_core_updates() : array();
		$latest_update = ! empty( $raw_updates ) && is_object( $raw_updates[0] ) ? array(
			'response'    => (string) ( $raw_updates[0]->response ?? 'latest' ),
			'current'     => (string) ( $raw_updates[0]->current ?? '' ),
			'version'     => (string) ( $raw_updates[0]->version ?? '' ),
			'php_version' => (string) ( $raw_updates[0]->php_version ?? '' ),
		) : null;

		$theme = wp_get_theme();

		return array(
			'success'        => true,
			'site_title'     => (string) get_bloginfo( 'name' ),
			'site_url'       => (string) site_url(),
			'home_url'       => (string) home_url(),
			'wp_version'     => (string) $GLOBALS['wp_version'],
			'php_version'    => PHP_VERSION,
			'active_theme'   => array(
				'name'       => (string) $theme->get( 'Name' ),
				'version'    => (string) $theme->get( 'Version' ),
				'is_child'   => is_child_theme(),
			),
			'counts'         => array(
				'posts'    => array(
					'publish' => (int) ( $post_counts['publish'] ?? 0 ),
					'draft'   => (int) ( $post_counts['draft'] ?? 0 ),
					'trash'   => (int) ( $post_counts['trash'] ?? 0 ),
				),
				'pages'    => array(
					'publish' => (int) ( $page_counts['publish'] ?? 0 ),
					'draft'   => (int) ( $page_counts['draft'] ?? 0 ),
					'trash'   => (int) ( $page_counts['trash'] ?? 0 ),
				),
				'comments' => array(
					'approved' => (int) ( $comment_counts['approved'] ?? 0 ),
					'moderated'=> (int) ( $comment_counts['moderated'] ?? 0 ),
					'spam'     => (int) ( $comment_counts['spam'] ?? 0 ),
				),
			),
			'homepage'       => array(
				'show_on_front'  => (string) get_option( 'show_on_front', 'posts' ),
				'page_on_front'  => (int) get_option( 'page_on_front', 0 ),
				'page_for_posts' => (int) get_option( 'page_for_posts', 0 ),
			),
			'core_update'    => $latest_update,
			'recent_404_logs'=> $recent_404,
		);
	}
}
