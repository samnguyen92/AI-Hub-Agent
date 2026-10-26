<?php
/**
 * Align Agent - Posts & Custom Post Types Management Tools
 *
 * Registers read tools (list_post_types, list_posts, get_post) and write tools
 * (create_post, save_post, set_post_status, trash_post, restore_post, duplicate_post,
 * update_post_attributes) for Pages, Posts, and Custom Post Types.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Posts {

	/** Initialize and register all post tools. */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/** Helper to register tool definition concisely. */
	private static function reg( string $name, string $desc, array $props, callable $cb, ?callable $sum = null, array $opts = array() ): void {
		$schema_props = empty( $props ) ? new stdClass() : $props;
		Align_Agent_Tool_Registry::register( $name, array(
			'type'             => $sum ? 'write' : 'read',
			'undo'             => $opts['undo'] ?? array(),
			'capability'       => $opts['cap'] ?? 'edit_posts',
			'group'            => 'content',
			'requires_builder' => ! empty( $opts['builder'] ),
			'danger'           => ! empty( $opts['danger'] ),
			'description'      => $desc,
			'parameters'       => array( 'type' => 'object', 'properties' => $schema_props, 'required' => $opts['req'] ?? array() ),
			'callback'         => $cb,
			'summary_callback' => $sum,
		) );
	}

	/** Register read-only post inspection tools. */
	private static function register_read_tools(): void {
		self::reg( 'list_post_types', 'List all public WordPress post types excluding attachments and internal wp_* types.', array(), array( __CLASS__, 'tool_list_post_types' ), null, array( 'cap' => 'edit_posts' ) );

		self::reg( 'list_posts', 'Query posts, pages, or custom post types with optional status, language filter (lang), keyword search, and pagination.', array(
			'post_type'      => array( 'type' => 'string', 'description' => 'Target post type slug or "any" (default public types including post and page).' ),
			'post_status'    => array( 'type' => 'string', 'description' => 'Comma-separated statuses: publish,draft,private (default).' ),
			'lang'           => array( 'type' => 'string', 'description' => 'Exact language code from list_languages (e.g., call list_languages first to resolve the user\'s requested language into its exact code, then pass that code here).' ),
			's'              => array( 'type' => 'string', 'description' => 'Keyword search query.' ),
			'paged'          => array( 'type' => 'integer', 'description' => 'Page number (default 1).' ),
			'posts_per_page' => array( 'type' => 'integer', 'description' => 'Items per page (default 10, max 50).' ),
		), array( __CLASS__, 'tool_list_posts' ), null, array( 'cap' => 'edit_posts' ) );

		self::reg( 'get_post', 'Retrieve full content and metadata for a single WordPress post/page/CPT by ID, including decoupled HTML/CSS/JS.', array(
			'post_id' => array( 'type' => 'integer', 'description' => 'Numeric WordPress post ID.' ),
		), array( __CLASS__, 'tool_get_post' ), null, array( 'cap' => 'edit_posts', 'req' => array( 'post_id' ) ) );
	}

	/** Register write tools requiring user approval. */
	private static function register_write_tools(): void {
		self::reg( 'create_post', 'Create a new post, page, or custom post type with HTML, CSS, and JS via Align Builder. Defaults to status="draft". IMPORTANT: You MUST write and translate the title, slug, and html content into the website\'s default language (default_lang, e.g., "en" English) unless the user explicitly instructs to write the content in another specific language. Requires user approval.', array(
			'title'     => array( 'type' => 'string', 'description' => 'Post title written/translated into the website\'s default language (or explicitly requested target language).' ),
			'post_type' => array( 'type' => 'string', 'description' => 'Target post type slug (default "post").' ),
			'slug'      => array( 'type' => 'string', 'description' => 'Optional URL slug written/translated into the website\'s default language.' ),
			'html'      => array( 'type' => 'string', 'description' => 'Clean body HTML without <script> tags, written in the website\'s default language unless another content language is explicitly requested.' ),
			'css'       => array( 'type' => 'string', 'description' => 'Optional scoped CSS.' ),
			'js'        => array( 'type' => 'string', 'description' => 'Optional scoped JavaScript.' ),
			'status'    => array( 'type' => 'string', 'description' => 'Post status: draft (default), publish, or private.' ),
			'lang'      => array( 'type' => 'string', 'description' => 'Optional language code for the post (defaults to the site\'s default_lang).' ),
		), array( __CLASS__, 'tool_create_post' ), array( __CLASS__, 'summarize_create_post' ), array( 'builder' => true, 'cap' => 'edit_posts', 'undo' => array( 'adapter' => 'create' ), 'req' => array( 'title', 'html' ) ) );

		self::reg( 'save_post', 'Save updated HTML, CSS, JS, title, or status for an existing post, page, or CPT via Align Builder. Omitted html/css/js keep their current values. Requires user approval.', array(
			'post_id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ), 'html' => array( 'type' => 'string' ),
			'css' => array( 'type' => 'string' ), 'js' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string' ),
		), array( __CLASS__, 'tool_save_post' ), array( __CLASS__, 'summarize_save_post' ), array( 'builder' => true, 'cap' => 'edit_posts', 'undo' => array( 'adapter' => 'post', 'id_arg' => 'post_id' ), 'req' => array( 'post_id' ) ) );

		self::reg( 'set_post_status', 'Change post status (publish, draft, private, pending, future). Scheduled "future" status requires post_date. Requires user approval.', array(
			'post_id' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string' ), 'post_date' => array( 'type' => 'string' ),
		), array( __CLASS__, 'tool_set_post_status' ), array( __CLASS__, 'summarize_set_post_status' ), array( 'cap' => 'edit_posts', 'undo' => array( 'adapter' => 'post', 'id_arg' => 'post_id' ), 'req' => array( 'post_id', 'status' ) ) );

		self::reg( 'trash_post', 'Move a post, page, or CPT to the trash. Permanent deletion is prohibited. Requires user approval.', array( 'post_id' => array( 'type' => 'integer' ) ), array( __CLASS__, 'tool_trash_post' ), array( __CLASS__, 'summarize_trash_post' ), array( 'cap' => 'delete_posts', 'danger' => true, 'undo' => array( 'adapter' => 'trash', 'id_arg' => 'post_id' ), 'req' => array( 'post_id' ) ) );

		self::reg( 'restore_post', 'Restore a previously trashed post, page, or CPT back to draft status. Requires user approval.', array( 'post_id' => array( 'type' => 'integer' ) ), array( __CLASS__, 'tool_restore_post' ), array( __CLASS__, 'summarize_restore_post' ), array( 'cap' => 'delete_posts', 'undo' => array( 'adapter' => 'trash', 'id_arg' => 'post_id' ), 'req' => array( 'post_id' ) ) );

		self::reg( 'duplicate_post', 'Duplicate an existing post/page/CPT including Builder assets and SEO metadata into draft. Requires user approval.', array( 'post_id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ) ), array( __CLASS__, 'tool_duplicate_post' ), array( __CLASS__, 'summarize_duplicate_post' ), array( 'builder' => true, 'cap' => 'edit_posts', 'undo' => array( 'adapter' => 'create' ), 'req' => array( 'post_id' ) ) );

		self::reg( 'update_post_attributes', 'Update post slug, parent, excerpt, date, author, or page template without touching content. Requires user approval.', array(
			'post_id' => array( 'type' => 'integer' ), 'title' => array( 'type' => 'string' ), 'slug' => array( 'type' => 'string' ), 'parent' => array( 'type' => 'integer' ), 'excerpt' => array( 'type' => 'string' ), 'date' => array( 'type' => 'string' ), 'author' => array( 'type' => 'integer' ), 'template' => array( 'type' => 'string' ),
		), array( __CLASS__, 'tool_update_post_attributes' ), array( __CLASS__, 'summarize_update_post_attributes' ), array( 'cap' => 'edit_posts', 'undo' => array( 'adapter' => 'post', 'id_arg' => 'post_id' ), 'req' => array( 'post_id' ) ) );
	}

	/** Sanitize target status with fallback to draft. */
	private static function sanitize_status( $raw ): string {
		$status = sanitize_key( (string) ( $raw ?? 'draft' ) );
		return in_array( $status, array( 'draft', 'publish', 'private', 'pending', 'future' ), true ) ? $status : 'draft';
	}
	/** Summary for create_post. */
	public static function summarize_create_post( array $args ): array {
		$t = sanitize_text_field( (string) ( $args['title'] ?? 'Untitled' ) );
		$c = sanitize_key( (string) ( $args['post_type'] ?? 'post' ) );
		return array( 'title' => sprintf( __( 'Create %1$s: "%2$s"', 'align-agent' ), ucfirst( $c ), $t ), 'description' => sprintf( __( 'Create new %s in draft status.', 'align-agent' ), $c ), 'preview_html' => (string) ( $args['html'] ?? '' ), 'preview_css' => (string) ( $args['css'] ?? '' ), 'meta' => array( 'post_type' => $c, 'status' => 'draft' ) );
	}
	/** Summary for save_post. */
	public static function summarize_save_post( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		$p  = $id > 0 ? get_post( $id ) : null;
		$t  = ! empty( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : ( $p ? $p->post_title : '#' . $id );
		return array( 'title' => sprintf( __( 'Save %1$s #%2$d (%3$s)', 'align-agent' ), ucfirst( $p ? $p->post_type : 'Post' ), $id, $t ), 'description' => __( 'Update content via Align Builder.', 'align-agent' ), 'preview_html' => (string) ( $args['html'] ?? '' ), 'preview_css' => (string) ( $args['css'] ?? '' ), 'meta' => array( 'post_id' => $id ) );
	}

	/** Summary for set_post_status. */
	public static function summarize_set_post_status( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		$s  = sanitize_key( (string) ( $args['status'] ?? 'draft' ) );
		return array( 'title' => sprintf( __( 'Change Status of Post #%1$d to "%2$s"', 'align-agent' ), $id, $s ), 'description' => sprintf( __( 'Update status to %s.', 'align-agent' ), $s ), 'preview_html' => '', 'preview_css' => '', 'meta' => array( 'post_id' => $id, 'status' => $s ) );
	}

	/** Summary for trash_post. */
	public static function summarize_trash_post( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		$p  = $id > 0 ? get_post( $id ) : null;
		return array( 'title' => sprintf( __( 'Move Post #%1$d to Trash', 'align-agent' ), $id ), 'description' => sprintf( __( 'Move "%s" to trash.', 'align-agent' ), $p ? $p->post_title : '#' . $id ), 'preview_html' => '', 'preview_css' => '', 'meta' => array( 'post_id' => $id ) );
	}

	/** Summary for restore_post. */
	public static function summarize_restore_post( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		return array( 'title' => sprintf( __( 'Restore Post #%d from Trash', 'align-agent' ), $id ), 'description' => __( 'Untrash post back to standard listing.', 'align-agent' ), 'preview_html' => '', 'preview_css' => '', 'meta' => array( 'post_id' => $id ) );
	}

	/** Summary for duplicate_post. */
	public static function summarize_duplicate_post( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		$p  = $id > 0 ? get_post( $id ) : null;
		return array( 'title' => sprintf( __( 'Duplicate Post #%1$d (%2$s)', 'align-agent' ), $id, $p ? $p->post_title : '' ), 'description' => __( 'Clone post into new draft.', 'align-agent' ), 'preview_html' => '', 'preview_css' => '', 'meta' => array( 'post_id' => $id ) );
	}

	/** Summary for update_post_attributes. */
	public static function summarize_update_post_attributes( array $args ): array {
		$id = absint( $args['post_id'] ?? 0 );
		return array( 'title' => sprintf( __( 'Update Attributes for Post #%d', 'align-agent' ), $id ), 'description' => __( 'Update post attributes.', 'align-agent' ), 'preview_html' => '', 'preview_css' => '', 'meta' => array( 'post_id' => $id ) );
	}

	/** Tool: list_post_types. */
	public static function tool_list_post_types( array $args = array() ): array {
		$types  = get_post_types( array( 'public' => true ), 'objects' );
		$result = array();
		foreach ( $types as $slug => $obj ) {
			if ( 'attachment' !== $slug && ! str_starts_with( (string) $slug, 'wp_' ) ) {
				$result[] = array( 'slug' => (string) $slug, 'label' => (string) ( $obj->labels->name ?? $obj->label ?? $slug ), 'description' => (string) $obj->description, 'hierarchical' => (bool) $obj->hierarchical );
			}
		}
		return array( 'success' => true, 'post_types' => $result );
	}

	/** Tool: list_posts. */
	public static function tool_list_posts( array $args = array() ): array {
		$paged     = max( 1, absint( $args['paged'] ?? 1 ) );
		$per_page  = min( 50, max( 1, absint( $args['posts_per_page'] ?? 10 ) ) );
		$search    = isset( $args['s'] ) ? sanitize_text_field( (string) $args['s'] ) : '';
		$raw_lang  = isset( $args['lang'] ) ? trim( (string) $args['lang'] ) : '';
		$post_type = ! empty( $args['post_type'] ) ? sanitize_key( (string) $args['post_type'] ) : 'any';

		if ( '' !== $raw_lang && class_exists( 'Align_Agent_Tools_Finder' ) ) {
			$lang_err = Align_Agent_Tools_Finder::validate_site_lang_code( $raw_lang );
			if ( null !== $lang_err ) {
				return $lang_err;
			}
		}
		$lang = sanitize_key( $raw_lang );

		if ( 'any' === $post_type ) {
			$public_types = array_values( get_post_types( array( 'public' => true ) ) );
			$post_type    = array_values( array_filter( $public_types, fn( $t ) => 'attachment' !== $t && ! str_starts_with( $t, 'wp_' ) ) );
		}

		$status     = ! empty( $args['post_status'] ) ? explode( ',', sanitize_text_field( (string) $args['post_status'] ) ) : array( 'publish', 'draft', 'private' );
		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => array_map( 'trim', $status ),
			'paged'          => $paged,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}
		if ( '' !== $lang && class_exists( 'Align_Agent_Tools_Finder' ) ) {
			$query_args['meta_query'] = Align_Agent_Tools_Finder::build_lang_meta_query( $lang );
		}

		$q        = new WP_Query( $query_args );
		$posts    = array();
		$def_lang = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';
		foreach ( $q->posts as $p ) {
			$p_lang  = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( (int) $p->ID ) : (string) get_post_meta( (int) $p->ID, '_align_lang', true );
			$p_lang  = '' !== $p_lang ? $p_lang : $def_lang;
			if ( '' !== $lang && $p_lang !== $lang ) {
				continue;
			}
			$raw_url = (string) get_permalink( $p->ID );
			$dyn_url = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
			$posts[] = array( 'id' => (int) $p->ID, 'title' => (string) $p->post_title, 'slug' => (string) $p->post_name, 'post_type' => (string) $p->post_type, 'status' => (string) $p->post_status, 'lang' => $p_lang, 'date' => (string) $p->post_date, 'url' => $dyn_url );
		}

		return array( 'success' => true, 'site_default_lang' => $def_lang, 'lang_filter' => $lang, 'paged' => $paged, 'per_page' => $per_page, 'total' => (int) $q->found_posts, 'total_pages' => (int) $q->max_num_pages, 'posts' => $posts );
	}

	/** Tool: get_post. */
	public static function tool_get_post( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_id', 'message' => __( 'A valid numeric post_id is required.', 'align-agent' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		$parts       = ( Align_Agent_Tool_Registry::is_builder_available() && class_exists( 'Align_Gateway_Pages' ) ) ? Align_Gateway_Pages::extract_decoupled_parts( $post ) : array( 'html' => (string) $post->post_content, 'css' => '', 'js' => '' );
		$editor_mode = ( class_exists( 'Align_Editor_Mode_Lock' ) && method_exists( 'Align_Editor_Mode_Lock', 'get_effective_mode' ) ) ? Align_Editor_Mode_Lock::get_effective_mode( $post_id ) : '';
		$is_locked   = ( class_exists( 'Align_Editor_Mode_Lock' ) && method_exists( 'Align_Editor_Mode_Lock', 'is_locked' ) ) ? Align_Editor_Mode_Lock::is_locked( $post_id ) : false;
		$def_lang    = class_exists( 'Align_Language_Manager' ) ? (string) Align_Language_Manager::get_default_lang() : 'en';
		$post_lang   = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_post_lang( $post_id ) : (string) get_post_meta( $post_id, '_align_lang', true );
		$lang        = '' !== $post_lang ? $post_lang : $def_lang;

		$raw_url = (string) get_permalink( $post->ID );
		$dyn_url = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;

		return array(
			'success' => true, 'id' => (int) $post->ID, 'title' => (string) $post->post_title, 'slug' => (string) $post->post_name, 'post_type' => (string) $post->post_type,
			'status' => (string) $post->post_status, 'lang' => $lang, 'site_default_lang' => $def_lang, 'editor_mode' => (string) $editor_mode, 'editor_locked' => $is_locked, 'url' => $dyn_url,
			'html' => $parts['html'], 'css' => $parts['css'], 'js' => $parts['js'], 'date' => (string) $post->post_date, 'modified' => (string) $post->post_modified_gmt,
		);
	}

	/** Tool: create_post. */
	public static function tool_create_post( array $args = array() ): array {
		$title = sanitize_text_field( (string) ( $args['title'] ?? '' ) );
		$cpt   = sanitize_key( (string) ( $args['post_type'] ?? 'post' ) );
		$slug  = ! empty( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : sanitize_title( $title );
		if ( '' === $title ) {
			return array( 'success' => false, 'error' => 'missing_title', 'message' => __( 'Post title is required.', 'align-agent' ) );
		}

		$html      = isset( $args['html'] ) ? (string) $args['html'] : '';
		$css       = isset( $args['css'] ) ? (string) $args['css'] : '';
		$js        = isset( $args['js'] ) ? (string) $args['js'] : '';
		if ( class_exists( 'Align_Agent_Tools_Media' ) ) {
			$html = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $html );
			$css  = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $css );
		}
		$guard_err = Align_Agent_Tools_Pages::guard_html_and_css( $html, $css, $js );
		if ( null !== $guard_err ) {
			return $guard_err;
		}

		$status = ( 'telegram' === Align_Agent_Conversation::get_channel() && ( empty( $args['status'] ) || 'publish' === $args['status'] ) ) ? 'draft' : self::sanitize_status( $args['status'] ?? 'draft' );
		if ( 'page' === $cpt ) {
			$req = new WP_REST_Request( 'POST', '/align-builder/v1/create-item' );
			$req->set_header( 'Content-Type', 'application/json' );
			$req->set_body( (string) wp_json_encode( array( 'type' => 'page', 'title' => $title, 'slug' => $slug ) ) );
			$res = Align_Builder_Crud_Handler::create_item( $req );
			if ( is_wp_error( $res ) ) {
				return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
			}
			$data    = $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
			$post_id = absint( $data['post_id'] ?? 0 );
		} else {
			$post_id = wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_type' => $cpt, 'post_status' => 'draft' ) );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return array( 'success' => false, 'error' => 'insert_failed', 'message' => __( 'Failed to create post record.', 'align-agent' ) );
			}
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
			'success' => true, 'post_id' => $post_id, 'post_type' => $cpt, 'title' => $title, 'slug' => $slug, 'status' => $status, 'lang' => $lang, 'site_default_lang' => $def_lang, 'url' => $url,
			'message' => sprintf( __( 'Created %1$s #%2$d ("%3$s") with status "%4$s".', 'align-agent' ), $cpt, $post_id, $title, $status ),
			'ui_card' => array( 'title' => sprintf( __( 'Created %1$s #%2$d', 'align-agent' ), ucfirst( $cpt ), $post_id ), 'message' => sprintf( __( 'Slug: /%1$s | Status: %2$s', 'align-agent' ), $slug, $status ), 'url' => $url, 'post_id' => $post_id, 'rollback_revision_id' => 0 ),
		);
	}

	/** Tool: save_post. */
	public static function tool_save_post( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		if ( class_exists( 'Align_Editor_Mode_Lock' ) && Align_Editor_Mode_Lock::is_locked_to( $post_id, 'gutenberg' ) ) {
			return array( 'success' => false, 'error' => 'editor_mode_locked', 'message' => sprintf( __( 'Post #%d is locked to Gutenberg editor.', 'align-agent' ), $post_id ) );
		}

		if ( ! array_key_exists( 'html', $args ) && ! array_key_exists( 'css', $args ) && ! array_key_exists( 'js', $args ) && empty( $args['title'] ) && ! isset( $args['status'] ) ) {
			return array( 'success' => false, 'error' => 'nothing_to_update', 'message' => __( 'No fields provided to update (must provide at least one of html, css, js, title, status).', 'align-agent' ) );
		}

		$existing  = class_exists( 'Align_Gateway_Pages' ) ? Align_Gateway_Pages::extract_decoupled_parts( $post ) : array( 'html' => $post->post_content, 'css' => '', 'js' => '' );
		$html      = isset( $args['html'] ) ? (string) $args['html'] : $existing['html'];
		$css       = isset( $args['css'] ) ? (string) $args['css'] : $existing['css'];
		$js        = isset( $args['js'] ) ? (string) $args['js'] : $existing['js'];
		if ( class_exists( 'Align_Agent_Tools_Media' ) ) {
			$html = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $html );
			$css  = Align_Agent_Tools_Media::sanitize_internal_urls_to_dynamic( $css );
		}
		$guard_err = Align_Agent_Tools_Pages::guard_html_and_css( $html, $css, $js );
		if ( null !== $guard_err ) {
			return $guard_err;
		}

		$status          = self::sanitize_status( $args['status'] ?? $post->post_status );
		$rollback_rev_id = (int) wp_save_post_revision( $post_id );

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

		$fresh   = get_post( $post_id );
		$raw_url = (string) get_permalink( $post_id );
		$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
		return array(
			'success' => true, 'post_id' => $post_id, 'title' => $fresh ? (string) $fresh->post_title : '', 'status' => $fresh ? (string) $fresh->post_status : $status,
			'url' => $url, 'rollback_revision_id' => $rollback_rev_id, 'message' => sprintf( __( 'Post #%1$d saved successfully (status: %2$s).', 'align-agent' ), $post_id, $status ),
			'ui_card' => array( 'title' => sprintf( __( 'Saved Post #%1$d', 'align-agent' ), $post_id ), 'message' => sprintf( __( 'Status: %1$s | Rollback: #%2$d', 'align-agent' ), $status, $rollback_rev_id ), 'url' => $url, 'post_id' => $post_id, 'rollback_revision_id' => $rollback_rev_id ),
		);
	}

	/** Tool: set_post_status. */
	public static function tool_set_post_status( array $args = array() ): array {
		$post_id   = absint( $args['post_id'] ?? 0 );
		$status    = sanitize_key( (string) ( $args['status'] ?? '' ) );
		$post_date = isset( $args['post_date'] ) ? sanitize_text_field( (string) $args['post_date'] ) : '';

		if ( ! in_array( $status, array( 'publish', 'draft', 'private', 'pending', 'future' ), true ) ) {
			return array( 'success' => false, 'error' => 'invalid_status', 'message' => __( 'Invalid post status specified.', 'align-agent' ) );
		}
		if ( 'future' === $status ) {
			if ( '' === $post_date ) {
				return array( 'success' => false, 'error' => 'missing_post_date', 'message' => __( 'A scheduled date (post_date) is required for future status.', 'align-agent' ) );
			}
			if ( strtotime( $post_date ) <= (int) current_time( 'timestamp' ) ) {
				return array( 'success' => false, 'error' => 'invalid_future_date', 'message' => __( 'Scheduled date must be in the future.', 'align-agent' ) );
			}
		}

		$update = array( 'ID' => $post_id, 'post_status' => $status );
		if ( '' !== $post_date ) {
			$update['post_date']     = $post_date;
			$update['post_date_gmt'] = get_gmt_from_date( $post_date );
		}

		$result = wp_update_post( $update );
		if ( is_wp_error( $result ) || ! $result ) {
			return array( 'success' => false, 'error' => 'update_failed', 'message' => __( 'Failed to update post status.', 'align-agent' ) );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'set_post_status', $post_id, 'update', array( 'status' => $status, 'date' => $post_date ) );
		$raw_url = (string) get_permalink( $post_id );
		$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
		return array(
			'success' => true, 'post_id' => $post_id, 'status' => $status, 'post_date' => $post_date, 'url' => $url, 'message' => sprintf( __( 'Post #%1$d status updated to "%2$s".', 'align-agent' ), $post_id, $status ),
			'ui_card' => array( 'title' => sprintf( __( 'Status Updated: Post #%d', 'align-agent' ), $post_id ), 'message' => sprintf( __( 'Status: %s', 'align-agent' ), $status ), 'url' => $url, 'post_id' => $post_id, 'rollback_revision_id' => 0 ),
		);
	}

	/** Tool: trash_post. */
	public static function tool_trash_post( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		if ( ! wp_trash_post( $post_id ) ) {
			return array( 'success' => false, 'error' => 'trash_failed', 'message' => sprintf( __( 'Failed to move Post #%d to trash.', 'align-agent' ), $post_id ) );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'trash_post', $post_id, 'delete', array( 'title' => $post->post_title ) );
		return array( 'success' => true, 'post_id' => $post_id, 'status' => 'trash', 'message' => sprintf( __( 'Post #%1$d ("%2$s") moved to trash.', 'align-agent' ), $post_id, $post->post_title ), 'ui_card' => array( 'title' => sprintf( __( 'Trashed Post #%d', 'align-agent' ), $post_id ), 'message' => __( 'Moved to trash.', 'align-agent' ), 'url' => '', 'post_id' => $post_id, 'rollback_revision_id' => 0 ) );
	}

	/** Tool: restore_post. */
	public static function tool_restore_post( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		if ( ! wp_untrash_post( $post_id ) ) {
			return array( 'success' => false, 'error' => 'restore_failed', 'message' => sprintf( __( 'Failed to restore Post #%d from trash.', 'align-agent' ), $post_id ) );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'restore_post', $post_id, 'restore', array( 'title' => $post->post_title ) );
		$raw_url = (string) get_permalink( $post_id );
		$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
		return array( 'success' => true, 'post_id' => $post_id, 'status' => 'draft', 'url' => $url, 'message' => sprintf( __( 'Post #%1$d restored from trash.', 'align-agent' ), $post_id ), 'ui_card' => array( 'title' => sprintf( __( 'Restored Post #%d', 'align-agent' ), $post_id ), 'message' => __( 'Untrashed successfully.', 'align-agent' ), 'url' => $url, 'post_id' => $post_id, 'rollback_revision_id' => 0 ) );
	}

	/** Tool: duplicate_post. */
	public static function tool_duplicate_post( array $args = array() ): array {
		$post_id  = absint( $args['post_id'] ?? 0 );
		$original = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $original ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Original post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		$new_title = ! empty( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : sprintf( __( 'Copy of %s', 'align-agent' ), $original->post_title );
		$new_slug  = sanitize_title( $new_title );
		$parts     = class_exists( 'Align_Gateway_Pages' ) ? Align_Gateway_Pages::extract_decoupled_parts( $original ) : array( 'html' => $original->post_content, 'css' => '', 'js' => '' );

		if ( 'page' === $original->post_type ) {
			$req = new WP_REST_Request( 'POST', '/align-builder/v1/create-item' );
			$req->set_header( 'Content-Type', 'application/json' );
			$req->set_body( (string) wp_json_encode( array( 'type' => 'page', 'title' => $new_title, 'slug' => $new_slug ) ) );
			$res = Align_Builder_Crud_Handler::create_item( $req );
			if ( is_wp_error( $res ) ) {
				return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
			}
			$data        = $res instanceof WP_REST_Response ? $res->get_data() : (array) $res;
			$new_post_id = absint( $data['post_id'] ?? 0 );
		} else {
			$new_post_id = wp_insert_post( array( 'post_title' => $new_title, 'post_name' => $new_slug, 'post_type' => $original->post_type, 'post_status' => 'draft' ) );
			if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
				return array( 'success' => false, 'error' => 'duplicate_failed', 'message' => __( 'Failed to duplicate post record.', 'align-agent' ) );
			}
		}

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
		foreach ( array( 'post_id' => $new_post_id, 'html' => $parts['html'], 'css' => $parts['css'], 'js' => $parts['js'], 'status' => 'draft' ) as $k => $v ) {
			$save_req->set_param( $k, $v );
		}
		$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $save_res ) || ( isset( $save_res['status'] ) && 'error' === $save_res['status'] ) ) {
			return array( 'success' => false, 'error' => 'duplicate_failed', 'message' => __( 'Failed to save content for duplicated post.', 'align-agent' ) );
		}

		$seo_keys = array(
			'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_canonical_url', 'rank_math_robots',
			'rank_math_facebook_title', 'rank_math_og_title', 'rank_math_facebook_description', 'rank_math_og_description',
			'rank_math_facebook_image_id', 'rank_math_facebook_image', '_yoast_wpseo_title', '_yoast_wpseo_metadesc',
			'_yoast_wpseo_focuskw', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_opengraph-title',
			'_yoast_wpseo_opengraph-description', '_yoast_wpseo_opengraph-image-id', '_yoast_wpseo_opengraph-image',
			'_align_seo_title', '_align_seo_description', '_align_seo_keywords', '_align_seo_canonical', '_align_seo_noindex',
			'_align_seo_og_title', '_align_seo_og_description', '_align_seo_og_image_id', '_align_seo_og_image',
		);
		foreach ( $seo_keys as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( '' !== $val && false !== $val && null !== $val ) {
				update_post_meta( $new_post_id, $key, $val );
			}
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'duplicate_post', $new_post_id, 'create', array( 'original_id' => $post_id, 'title' => $new_title ) );
		$raw_url = (string) get_permalink( $new_post_id );
		$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
		return array(
			'success' => true, 'post_id' => $new_post_id, 'original_id' => $post_id, 'title' => $new_title, 'status' => 'draft', 'url' => $url,
			'message' => sprintf( __( 'Duplicated post #%1$d to new draft #%2$d ("%3$s").', 'align-agent' ), $post_id, $new_post_id, $new_title ),
			'ui_card' => array( 'title' => sprintf( __( 'Duplicated to #%d', 'align-agent' ), $new_post_id ), 'message' => sprintf( __( 'Draft cloned from #%d.', 'align-agent' ), $post_id ), 'url' => $url, 'post_id' => $new_post_id, 'rollback_revision_id' => 0 ),
		);
	}

	/** Tool: update_post_attributes. */
	public static function tool_update_post_attributes( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}

		$update = array( 'ID' => $post_id );
		if ( ! empty( $args['slug'] ) ) { $update['post_name'] = sanitize_title( (string) $args['slug'] ); }
		if ( isset( $args['parent'] ) ) { $update['post_parent'] = absint( $args['parent'] ); }
		if ( isset( $args['excerpt'] ) ) { $update['post_excerpt'] = sanitize_textarea_field( (string) $args['excerpt'] ); }
		if ( ! empty( $args['date'] ) ) {
			$date                    = sanitize_text_field( (string) $args['date'] );
			$update['post_date']     = $date;
			$update['post_date_gmt'] = get_gmt_from_date( $date );
		}
		if ( isset( $args['author'] ) ) { $update['post_author'] = absint( $args['author'] ); }

		if ( ! empty( $args['title'] ) ) {
			$title = sanitize_text_field( (string) $args['title'] );
			if ( $title !== $post->post_title ) {
				if ( Align_Agent_Tool_Registry::is_builder_available() ) {
					$ren = new WP_REST_Request( 'POST', '/align-builder/v1/rename-item' );
					$ren->set_header( 'Content-Type', 'application/json' );
					$ren->set_body( (string) wp_json_encode( array( 'post_id' => $post_id, 'title' => $title ) ) );
					Align_Builder_Crud_Handler::rename_item( $ren );
				} else {
					$update['post_title'] = $title;
				}
			}
		}

		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		if ( isset( $args['template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( (string) $args['template'] ) );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'update_post_attributes', $post_id, 'update', $args );
		$fresh   = get_post( $post_id );
		$raw_url = (string) get_permalink( $post_id );
		$url     = class_exists( 'Align_Agent_Tools_Media' ) ? Align_Agent_Tools_Media::to_root_relative_url( $raw_url ) : $raw_url;
		return array(
			'success' => true, 'post_id' => $post_id, 'slug' => (string) $fresh->post_name, 'title' => (string) $fresh->post_title, 'url' => $url,
			'message' => sprintf( __( 'Updated attributes for Post #%d.', 'align-agent' ), $post_id ),
			'ui_card' => array( 'title' => sprintf( __( 'Updated Attributes #%d', 'align-agent' ), $post_id ), 'message' => sprintf( __( 'Slug: /%s', 'align-agent' ), $fresh->post_name ), 'url' => $url, 'post_id' => $post_id, 'rollback_revision_id' => 0 ),
		);
	}
}
