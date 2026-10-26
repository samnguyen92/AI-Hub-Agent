<?php
/**
 * Align Agent - Page Management Tools for Chatbox Function Calling
 *
 * Registers read tools (list_pages, get_page, get_revisions, validate_markup)
 * and write tools (save_page, create_page, restore_revision) routed exclusively
 * through Align_Builder_* handlers.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Pages {

	/**
	 * Register all read and write page tools with Align_Agent_Tool_Registry.
	 */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/** Register the 4 read-only page inspection tools. */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register( 'list_pages', array(
			'type'        => 'read',
			'group'       => 'content',
			'description' => 'List WordPress pages with optional search query, language filter (lang), post status filter (publish, draft, private), and pagination.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'search'   => array( 'type' => 'string', 'description' => 'Optional keyword search query.' ),
					'lang'     => array( 'type' => 'string', 'description' => 'Exact language code from list_languages (e.g., call list_languages first to resolve the user\'s requested language into its exact code, then pass that code here).' ),
					'status'   => array( 'type' => 'string', 'description' => 'Comma-separated statuses (publish,draft,private).' ),
					'page'     => array( 'type' => 'integer', 'description' => 'Page number (default 1).' ),
					'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (default 10, max 50).' ),
				),
			),
			'callback'    => array( __CLASS__, 'tool_list_pages' ),
		) );

		Align_Agent_Tool_Registry::register( 'get_page', array(
			'type'        => 'read',
			'group'       => 'content',
			'description' => 'Retrieve full details of a single WordPress page by ID, including decoupled HTML body, CSS, and JS.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'integer', 'description' => 'Numeric WordPress post ID of the page.' ) ), 'required' => array( 'id' ) ),
			'callback'    => array( __CLASS__, 'tool_get_page' ),
		) );

		Align_Agent_Tool_Registry::register( 'get_revisions', array(
			'type'        => 'read',
			'group'       => 'content',
			'description' => 'Retrieve the revision history for a given WordPress page, post, or template part ID.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'       => array( 'type' => 'integer', 'description' => 'Numeric WordPress post ID of the page/post.' ),
					'tp_id'         => array( 'type' => 'string', 'description' => 'Template part or template ID/slug (e.g. tp_header, index).' ),
					'template_type' => array( 'type' => 'string', 'description' => 'Optional template type: wp_template or wp_template_part.' ),
				),
			),
			'callback'    => array( __CLASS__, 'tool_get_revisions' ),
		) );

		Align_Agent_Tool_Registry::register( 'validate_markup', array(
			'type'        => 'read',
			'group'       => 'content',
			'description' => 'Validate HTML tag balance, DOM structure, and CSS syntax in a dry-run sandbox before saving.',
			'parameters'  => array( 'type' => 'object', 'properties' => array( 'html' => array( 'type' => 'string', 'description' => 'HTML body markup to validate.' ), 'css' => array( 'type' => 'string', 'description' => 'Optional CSS rules to validate.' ) ), 'required' => array( 'html' ) ),
			'callback'    => array( __CLASS__, 'tool_validate_markup' ),
		) );
	}

	/** Register write tools requiring user approval. */
	private static function register_write_tools(): void {
		Align_Agent_Tool_Registry::register( 'save_page', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'post', 'id_arg' => 'post_id' ),
			'requires_builder' => true,
			'group'            => 'content',
			'description'      => 'Save updated HTML, CSS, JS, title, or status for an existing WordPress page via Align Builder. Keeps current post status when "status" is omitted. Omitted html/css/js keep their current values. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Numeric WordPress page ID.' ),
					'title'   => array( 'type' => 'string', 'description' => 'Optional new page title.' ),
					'html'    => array( 'type' => 'string', 'description' => 'Clean body HTML without <script> tags.' ),
					'css'     => array( 'type' => 'string', 'description' => 'Optional scoped page CSS.' ),
					'js'      => array( 'type' => 'string', 'description' => 'Optional scoped page JavaScript.' ),
					'status'  => array( 'type' => 'string', 'description' => 'Optional post status: draft, publish, pending, or private. Omit to keep the current status.' ),
				),
				'required'   => array( 'post_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_save_page' ),
			'summary_callback' => array( __CLASS__, 'summarize_save_page' ),
		) );

		Align_Agent_Tool_Registry::register( 'create_page', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'create' ),
			'requires_builder' => true,
			'group'            => 'content',
			'description'      => 'Create a new WordPress page with HTML, CSS, and JS via Align Builder. Defaults to status="draft". IMPORTANT: You MUST write and translate the title, slug, and html content into the website\'s default language (default_lang, e.g., "en" English) unless the user explicitly instructs to write the page content in another specific language (e.g., if the user chats in Vietnamese "tạo page khóa học 001" on an English site, you MUST pass title="Course 001", slug="course-001", and English HTML). Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'title'  => array( 'type' => 'string', 'description' => 'Page title written/translated into the website\'s default language (or explicitly requested target language).' ),
					'slug'   => array( 'type' => 'string', 'description' => 'Optional URL slug written/translated into the website\'s default language (e.g. "course-001").' ),
					'html'   => array( 'type' => 'string', 'description' => 'Clean body HTML without <script> tags, written in the website\'s default language unless another content language is explicitly requested.' ),
					'css'    => array( 'type' => 'string', 'description' => 'Optional scoped page CSS.' ),
					'js'     => array( 'type' => 'string', 'description' => 'Optional scoped page JavaScript.' ),
					'status' => array( 'type' => 'string', 'description' => 'Post status: draft (default), publish, or private.' ),
					'lang'   => array( 'type' => 'string', 'description' => 'Optional language code for the page (defaults to the site\'s default_lang).' ),
				),
				'required'   => array( 'title', 'html' ),
			),
			'callback'         => array( __CLASS__, 'tool_create_page' ),
			'summary_callback' => array( __CLASS__, 'summarize_create_page' ),
		) );

		Align_Agent_Tool_Registry::register( 'restore_revision', array(
			'type'             => 'write',
			'undo'             => array( 'adapter' => 'post', 'id_arg' => 'post_id' ),
			'requires_builder' => true,
			'group'            => 'content',
			'description'      => 'Restore a WordPress page, post, or template to a specific revision ID via Align Builder Revision Handler and resync CSS/JS disk assets.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array( 'type' => 'integer', 'description' => 'Numeric WordPress page or post ID.' ),
					'tp_id'       => array( 'type' => 'string', 'description' => 'Template part or template ID/slug.' ),
					'revision_id' => array( 'type' => 'integer', 'description' => 'Target revision ID to restore.' ),
				),
				'required'   => array( 'revision_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_restore_revision' ),
			'summary_callback' => array( __CLASS__, 'summarize_restore_revision' ),
		) );
	}

	/** Execute list_pages tool. */
	public static function tool_list_pages( array $args = array() ): array {
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, absint( $args['per_page'] ?? 10 ) ) );
		$search   = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$raw_lang = isset( $args['lang'] ) ? trim( (string) $args['lang'] ) : '';
		$status   = isset( $args['status'] ) ? sanitize_text_field( (string) $args['status'] ) : 'publish,draft,private';

		if ( '' !== $raw_lang && class_exists( 'Align_Agent_Tools_Finder' ) ) {
			$lang_err = Align_Agent_Tools_Finder::validate_site_lang_code( $raw_lang );
			if ( null !== $lang_err ) {
				return $lang_err;
			}
		}
		$lang = sanitize_key( $raw_lang );

		$request = new WP_REST_Request( 'GET', '/align-agent/v1/pages' );
		$request->set_param( 'page', $page );
		$request->set_param( 'per_page', $per_page );
		if ( '' !== $search ) { $request->set_param( 's', $search ); }
		if ( '' !== $lang ) { $request->set_param( 'lang', $lang ); }
		if ( '' !== $status ) { $request->set_param( 'status', $status ); }

		$response = Align_Gateway_Pages::handle_get_pages( $request );
		$items    = $response instanceof WP_REST_Response ? $response->get_data() : array();
		$headers  = $response instanceof WP_REST_Response ? $response->get_headers() : array();
		$def_lang = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';

		if ( is_array( $items ) ) {
			foreach ( $items as &$item ) {
				if ( is_array( $item ) && ! empty( $item['id'] ) ) {
					$item_lang    = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( (int) $item['id'] ) : (string) get_post_meta( (int) $item['id'], '_align_lang', true );
					$item['lang'] = '' !== $item_lang ? $item_lang : $def_lang;
				}
			}
			unset( $item );
		}

		return array(
			'success'           => true,
			'site_default_lang' => $def_lang,
			'lang_filter'       => $lang,
			'page'              => $page,
			'per_page'          => $per_page,
			'total'             => isset( $headers['X-WP-Total'] ) ? (int) $headers['X-WP-Total'] : count( (array) $items ),
			'total_pages'       => isset( $headers['X-WP-TotalPages'] ) ? (int) $headers['X-WP-TotalPages'] : 1,
			'pages'             => is_array( $items ) ? $items : array(),
		);
	}

	/** Execute get_page tool. */
	public static function tool_get_page( array $args = array() ): array {
		$post_id = absint( $args['id'] ?? $args['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_id', 'message' => __( 'A valid numeric page ID is required.', 'align-agent' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return array( 'success' => false, 'error' => 'page_not_found', 'message' => sprintf( __( 'Page #%d was not found.', 'align-agent' ), $post_id ) );
		}

		$parts       = Align_Gateway_Pages::extract_decoupled_parts( $post );
		$editor_mode = ( class_exists( 'Align_Editor_Mode_Lock' ) && method_exists( 'Align_Editor_Mode_Lock', 'get_effective_mode' ) ) ? Align_Editor_Mode_Lock::get_effective_mode( $post_id ) : '';
		$is_locked   = ( class_exists( 'Align_Editor_Mode_Lock' ) && method_exists( 'Align_Editor_Mode_Lock', 'is_locked' ) ) ? Align_Editor_Mode_Lock::is_locked( $post_id ) : false;
		$def_lang    = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';
		$post_lang   = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( $post_id ) : (string) get_post_meta( $post_id, '_align_lang', true );
		$lang        = '' !== $post_lang ? $post_lang : $def_lang;

		$raw_url = (string) get_permalink( $post->ID );
		$dyn_url = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;

		return array(
			'success'           => true,
			'id'                => (int) $post->ID,
			'title'             => (string) $post->post_title,
			'slug'              => (string) $post->post_name,
			'status'            => (string) $post->post_status,
			'lang'              => $lang,
			'site_default_lang' => $def_lang,
			'editor_mode'       => (string) $editor_mode,
			'editor_locked'     => $is_locked,
			'url'               => $dyn_url,
			'html'              => $parts['html'],
			'css'               => $parts['css'],
			'js'                => $parts['js'],
			'modified'          => (string) $post->post_modified_gmt,
		);
	}

	/** Execute get_revisions tool. */
	public static function tool_get_revisions( array $args = array() ): array {
		$post_id       = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		$tp_id         = isset( $args['tp_id'] ) ? sanitize_text_field( (string) $args['tp_id'] ) : '';
		$template_type = isset( $args['template_type'] ) ? sanitize_text_field( (string) $args['template_type'] ) : '';

		if ( $post_id <= 0 && '' === $tp_id ) {
			return array( 'success' => false, 'error' => 'invalid_id', 'message' => __( 'A valid numeric post_id or string tp_id is required.', 'align-agent' ) );
		}

		if ( class_exists( 'Align_Builder_Revision_Handler' ) ) {
			$request = new WP_REST_Request( 'GET', '/align-builder/v1/revisions' );
			if ( $post_id > 0 ) {
				$request->set_param( 'post_id', $post_id );
			}
			if ( '' !== $tp_id ) {
				$request->set_param( 'tp_id', $tp_id );
			}
			if ( '' !== $template_type ) {
				$request->set_param( 'template_type', $template_type );
			}
			$response = Align_Builder_Revision_Handler::get_revisions( $request );
			if ( is_wp_error( $response ) ) {
				return array( 'success' => false, 'error' => $response->get_error_code(), 'message' => $response->get_error_message() );
			}
			$data = $response instanceof WP_REST_Response ? $response->get_data() : (array) $response;
			return array(
				'success'   => true,
				'post_id'   => $data['post_id'] ?? $post_id,
				'tp_id'     => $tp_id,
				'total'     => isset( $data['total'] ) ? (int) $data['total'] : count( $data['revisions'] ?? array() ),
				'revisions' => $data['revisions'] ?? array(),
			);
		}

		$revs  = wp_get_post_revisions( $post_id, array( 'order' => 'DESC', 'orderby' => 'date' ) );
		$items = array();
		foreach ( $revs as $rev ) {
			$items[] = array( 'revision_id' => (int) $rev->ID, 'post_id' => $post_id, 'title' => (string) $rev->post_title, 'date' => (string) $rev->post_modified, 'content_length' => strlen( (string) $rev->post_content ) );
		}
		return array( 'success' => true, 'post_id' => $post_id, 'total' => count( $items ), 'revisions' => $items );
	}

	/** Execute validate_markup tool. */
	public static function tool_validate_markup( array $args = array() ): array {
		$html   = isset( $args['html'] ) ? (string) $args['html'] : '';
		$css    = isset( $args['css'] ) ? (string) $args['css'] : '';
		$result = Align_Gateway_Validator::validate_markup( $html, $css );
		return array_merge( array( 'success' => true ), $result );
	}

	/** Guard HTML against forbidden <script> tags, inspect JS, and run dry-run markup validator. */
	public static function guard_html_and_css( string $html, string $css, string $js = '' ): ?array {
		if ( class_exists( 'Align_Agent_Content_Guard' ) ) {
			return Align_Agent_Content_Guard::guard( $html, $css, $js );
		}
		if ( preg_match( '/<script\b[^>]*>/i', $html ) ) {
			return array( 'success' => false, 'error' => 'forbidden_script_tag', 'message' => __( 'Embedding <script> is forbidden.', 'align-agent' ) );
		}
		$validation = Align_Gateway_Validator::validate_markup( $html, $css );
		return empty( $validation['valid'] ) ? array( 'success' => false, 'error' => 'invalid_markup', 'validation' => $validation ) : null;
	}

	/** Sanitize target status with fallback to draft. */
	private static function sanitize_status( $raw ): string {
		$status = sanitize_key( (string) ( $raw ?? 'draft' ) );
		return in_array( $status, array( 'draft', 'publish', 'private', 'pending' ), true ) ? $status : 'draft';
	}

	/** Build approval summary for save_page. */
	public static function summarize_save_page( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		$title   = ! empty( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : ( $post ? $post->post_title : '#' . $post_id );
		$status  = isset( $args['status'] ) && '' !== $args['status'] ? self::sanitize_status( $args['status'] ) : ( $post ? $post->post_status : 'draft' );
		return array(
			'title'        => sprintf( __( 'Save Page #%1$d (%2$s)', 'align-agent' ), $post_id, $title ),
			'description'  => sprintf( __( 'Update page content via Align Builder (target status: %s).', 'align-agent' ), $status ),
			'preview_html' => isset( $args['html'] ) ? (string) $args['html'] : '',
			'preview_css'  => isset( $args['css'] ) ? (string) $args['css'] : '',
			'meta'         => array( 'post_id' => $post_id, 'status' => $status ),
		);
	}

	/** Build approval summary for create_page. */
	public static function summarize_create_page( array $args ): array {
		$title  = sanitize_text_field( (string) ( $args['title'] ?? 'Untitled Page' ) );
		$slug   = ! empty( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : sanitize_title( $title );
		$status = self::sanitize_status( $args['status'] ?? 'draft' );
		return array(
			'title'        => sprintf( __( 'Create Page "%s"', 'align-agent' ), $title ),
			'description'  => sprintf( __( 'Create new page (slug: /%1$s, status: %2$s) via Align Builder.', 'align-agent' ), $slug, $status ),
			'preview_html' => isset( $args['html'] ) ? (string) $args['html'] : '',
			'preview_css'  => isset( $args['css'] ) ? (string) $args['css'] : '',
			'meta'         => array( 'slug' => $slug, 'status' => $status ),
		);
	}

	/** Build approval summary for restore_revision. */
	public static function summarize_restore_revision( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$rev_id  = absint( $args['revision_id'] ?? 0 );
		$tp_id   = isset( $args['tp_id'] ) ? sanitize_text_field( (string) $args['tp_id'] ) : '';
		$label   = '' !== $tp_id ? $tp_id : ( '#' . $post_id );
		return array(
			'title'        => sprintf( __( 'Restore %1$s to Revision #%2$d', 'align-agent' ), $label, $rev_id ),
			'description'  => __( 'Revert entity content and synchronize CSS/JS disk assets from the selected revision.', 'align-agent' ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'post_id' => $post_id, 'revision_id' => $rev_id, 'tp_id' => $tp_id ),
		);
	}

	/** Execute save_page via Align_Builder_Crud_Handler::save_page. */
	public static function tool_save_page( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		$public_types  = get_post_types( array( 'public' => true ) );
		$allowed_types = array_merge( array_values( $public_types ), array( 'wp_block', 'wp_template', 'wp_template_part' ) );
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			return array( 'success' => false, 'error' => 'unsupported_post_type', 'message' => sprintf( __( 'Post #%1$d type (%2$s) is not supported for editing.', 'align-agent' ), $post_id, $post->post_type ) );
		}

		if ( class_exists( 'Align_Editor_Mode_Lock' ) && Align_Editor_Mode_Lock::is_locked_to( $post_id, 'gutenberg' ) ) {
			error_log( sprintf( '[Align Agent] save_page blocked for Post #%d: editor_mode_locked to gutenberg.', $post_id ) );
			return array( 'success' => false, 'error' => 'editor_mode_locked', 'message' => sprintf( __( 'Page #%d is locked to the Gutenberg editor. Switch editor mode in the page editor before saving with Align Agent.', 'align-agent' ), $post_id ) );
		}

		if ( ! array_key_exists( 'html', $args ) && ! array_key_exists( 'css', $args ) && ! array_key_exists( 'js', $args ) && empty( $args['title'] ) && ! isset( $args['status'] ) ) {
			return array( 'success' => false, 'error' => 'nothing_to_update', 'message' => __( 'No fields provided to update (must provide at least one of html, css, js, title, status).', 'align-agent' ) );
		}

		$existing  = Align_Gateway_Pages::extract_decoupled_parts( $post );
		$html      = isset( $args['html'] ) ? (string) $args['html'] : $existing['html'];
		$css       = isset( $args['css'] ) ? (string) $args['css'] : $existing['css'];
		$js        = isset( $args['js'] ) ? (string) $args['js'] : $existing['js'];
		if ( class_exists( 'Align_Agent_Tools_Media' ) ) {
			$html = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $html );
			$css  = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $css );
		}
		$guard_err = self::guard_html_and_css( $html, $css, $js );
		if ( null !== $guard_err ) {
			return $guard_err;
		}

		// Empty status tells the Builder to keep the current post status (prevents unpublishing live pages).
		$status          = isset( $args['status'] ) && '' !== $args['status'] ? self::sanitize_status( $args['status'] ) : '';
		$rollback_rev_id = (int) wp_save_post_revision( $post_id );
		if ( $rollback_rev_id <= 0 ) {
			$revs            = wp_get_post_revisions( $post_id, array( 'order' => 'DESC', 'orderby' => 'ID' ) );
			$latest_rev      = ! empty( $revs ) ? reset( $revs ) : null;
			$rollback_rev_id = $latest_rev ? (int) $latest_rev->ID : 0;
		}

		$preserved_slug = ! empty( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : (string) $post->post_name;

		if ( ! empty( $args['title'] ) ) {
			$new_title = sanitize_text_field( (string) $args['title'] );
			if ( '' !== $new_title && $new_title !== $post->post_title ) {
				$rename_req = new WP_REST_Request( 'POST', '/align-builder/v1/rename-item' );
				$rename_req->set_header( 'Content-Type', 'application/json' );
				$rename_req->set_body( (string) wp_json_encode( array( 'post_id' => $post_id, 'title' => $new_title ) ) );
				Align_Builder_Crud_Handler::rename_item( $rename_req );
			}
		}

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
		foreach ( array( 'post_id' => $post_id, 'html' => $html, 'css' => $css, 'js' => $js, 'status' => $status ) as $k => $v ) {
			$save_req->set_param( $k, $v );
		}

		$res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
		}

		$fresh = get_post( $post_id );
		if ( '' !== $preserved_slug && $fresh && $fresh->post_name !== $preserved_slug ) {
			wp_update_post( array( 'ID' => $post_id, 'post_name' => $preserved_slug ) );
			$fresh = get_post( $post_id );
		}
		$raw_url = (string) get_permalink( $post_id );
		$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
		$status  = $fresh ? (string) $fresh->post_status : ( '' !== $status ? $status : (string) $post->post_status );
		error_log( sprintf( '[Align Agent] save_page saved Post #%d (status: %s).', $post_id, $status ) );
		return array(
			'success'              => true,
			'post_id'              => $post_id,
			'title'                => $fresh ? (string) $fresh->post_title : '',
			'status'               => $status,
			'url'                  => $url,
			'rollback_revision_id' => $rollback_rev_id,
			'message'              => sprintf( __( 'Page #%1$d saved successfully (status: %2$s).', 'align-agent' ), $post_id, $status ),
			'ui_card'              => array( 'title' => sprintf( __( 'Saved Page #%1$d: %2$s', 'align-agent' ), $post_id, $fresh ? $fresh->post_title : '' ), 'message' => sprintf( __( 'Status: %1$s | Rollback Revision: #%2$d', 'align-agent' ), $status, $rollback_rev_id ), 'url' => $url, 'post_id' => $post_id, 'rollback_revision_id' => $rollback_rev_id ),
		);
	}

	/** Execute create_page via Align_Builder_Crud_Handler::create_item + save_page. */
	public static function tool_create_page( array $args = array() ): array {
		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$slug  = ! empty( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : sanitize_title( $title );
		if ( '' === $title || '' === $slug ) {
			return array( 'success' => false, 'error' => 'missing_fields', 'message' => __( 'Page title is required to create a page.', 'align-agent' ) );
		}

		$html      = isset( $args['html'] ) ? (string) $args['html'] : '';
		$css       = isset( $args['css'] ) ? (string) $args['css'] : '';
		$js        = isset( $args['js'] ) ? (string) $args['js'] : '';
		if ( class_exists( 'Align_Agent_Tools_Media' ) ) {
			$html = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $html );
			$css  = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $css );
		}
		$guard_err = self::guard_html_and_css( $html, $css, $js );
		if ( null !== $guard_err ) {
			return $guard_err;
		}

		$status = ( 'telegram' === Align_Agent_Conversation::get_channel() && ( empty( $args['status'] ) || 'publish' === $args['status'] ) )
			? 'draft'
			: self::sanitize_status( $args['status'] ?? 'draft' );
		$create_req = new WP_REST_Request( 'POST', '/align-builder/v1/create-item' );
		$create_req->set_header( 'Content-Type', 'application/json' );
		$create_req->set_body( (string) wp_json_encode( array( 'type' => 'page', 'title' => $title, 'slug' => $slug ) ) );

		$create_res = Align_Builder_Crud_Handler::create_item( $create_req );
		if ( is_wp_error( $create_res ) ) {
			return array( 'success' => false, 'error' => $create_res->get_error_code(), 'message' => $create_res->get_error_message() );
		}

		$data    = $create_res instanceof WP_REST_Response ? $create_res->get_data() : (array) $create_res;
		$post_id = absint( $data['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array( 'success' => false, 'error' => 'create_failed', 'message' => __( 'Failed to obtain new post ID from Builder.', 'align-agent' ) );
		}

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
		foreach ( array( 'post_id' => $post_id, 'html' => $html, 'css' => $css, 'js' => $js, 'status' => $status ) as $k => $v ) {
			$save_req->set_param( $k, $v );
		}

		$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $save_res ) ) {
			return array( 'success' => false, 'error' => $save_res->get_error_code(), 'message' => $save_res->get_error_message() );
		}

		$def_lang = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';
		$req_lang = ! empty( $args['lang'] ) ? sanitize_key( (string) $args['lang'] ) : '';
		if ( '' !== $req_lang && class_exists( 'Align_Language_Manager' ) && null !== Align_Language_Manager::get_language( $req_lang ) ) {
			$lang = $req_lang;
		} else {
			$post_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( $post_id ) : (string) get_post_meta( $post_id, '_align_lang', true );
			$lang      = '' !== $post_lang ? $post_lang : $def_lang;
		}
		update_post_meta( $post_id, '_align_lang', $lang );

		$raw_url = (string) get_permalink( $post_id );
		$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
		return array(
			'success'           => true,
			'post_id'           => $post_id,
			'title'             => $title,
			'slug'              => $slug,
			'status'            => $status,
			'lang'              => $lang,
			'site_default_lang' => $def_lang,
			'url'               => $url,
			'message'           => sprintf( __( 'Created page #%1$d ("%2$s") with status "%3$s".', 'align-agent' ), $post_id, $title, $status ),
			'ui_card'           => array( 'title' => sprintf( __( 'Created Page #%1$d: %2$s', 'align-agent' ), $post_id, $title ), 'message' => sprintf( __( 'Slug: /%1$s | Status: %2$s', 'align-agent' ), $slug, $status ), 'url' => $url, 'post_id' => $post_id, 'rollback_revision_id' => 0 ),
		);
	}

	/** Execute restore_revision via Align_Builder_Revision_Handler::restore_revision + asset sync. */
	public static function tool_restore_revision( array $args = array() ): array {
		$post_id     = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		$revision_id = absint( $args['revision_id'] ?? 0 );
		$tp_id       = isset( $args['tp_id'] ) ? sanitize_text_field( (string) $args['tp_id'] ) : '';

		if ( $revision_id <= 0 || ( $post_id <= 0 && '' === $tp_id ) ) {
			return array( 'success' => false, 'error' => 'missing_ids', 'message' => __( 'A revision_id and either post_id or tp_id are required.', 'align-agent' ) );
		}
		if ( ! class_exists( 'Align_Builder_Revision_Handler' ) ) {
			return array( 'success' => false, 'error' => 'builder_unavailable', 'message' => __( 'Align Builder Revision Handler is not available.', 'align-agent' ) );
		}

		$req  = new WP_REST_Request( 'POST', '/align-builder/v1/restore-revision' );
		$req->set_header( 'Content-Type', 'application/json' );
		$body = array( 'revision_id' => $revision_id );
		if ( $post_id > 0 ) $body['post_id'] = $post_id;
		if ( '' !== $tp_id ) $body['tp_id'] = $tp_id;
		$req->set_body( (string) wp_json_encode( $body ) );

		$res = Align_Builder_Revision_Handler::restore_revision( $req );
		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
		}

		$data             = $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
		$resolved_post_id = absint( $data['post_id'] ?? $post_id );

		$post = $resolved_post_id > 0 ? get_post( $resolved_post_id ) : null;
		$rev_css  = (string) get_metadata( 'post', $revision_id, '_align_css', true );
		$rev_js   = (string) get_metadata( 'post', $revision_id, '_align_js', true );
		if ( ( '' !== $rev_css || '' !== $rev_js ) && $post ) {
			if ( class_exists( 'Align_Builder_Persistence_Service' ) && method_exists( 'Align_Builder_Persistence_Service', 'sync_post_assets_from_content' ) ) {
				Align_Builder_Persistence_Service::sync_post_assets_from_content( $resolved_post_id, $post );
			}
		}

		$url = $resolved_post_id > 0 ? (string) get_permalink( $resolved_post_id ) : '';
		return array(
			'success'     => true,
			'post_id'     => $resolved_post_id,
			'tp_id'       => $tp_id,
			'revision_id' => $revision_id,
			'url'         => $url,
			'message'     => sprintf( __( 'Restored revision #%1$d and synchronized assets.', 'align-agent' ), $revision_id ),
			'ui_card'     => array( 'title' => sprintf( __( 'Restored Revision #%d', 'align-agent' ), $revision_id ), 'message' => sprintf( __( 'Entity #%1$d (tp_id: %2$s) reverted.', 'align-agent' ), $resolved_post_id, $tp_id ?: 'none' ), 'url' => $url, 'post_id' => $resolved_post_id, 'rollback_revision_id' => 0 ),
		);
	}
}
