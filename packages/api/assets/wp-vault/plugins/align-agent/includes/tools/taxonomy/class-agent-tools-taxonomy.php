<?php
/**
 * Align Agent - Taxonomy Management Tools
 *
 * Registers read tools (list_terms) and write tools (create_term, update_term,
 * delete_term, assign_terms) for WordPress categories, tags, and custom taxonomies.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Taxonomy {

	/** Initialize and register all taxonomy tools. */
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
			'capability'       => $opts['cap'] ?? 'manage_categories',
			'group'            => 'taxonomy',
			'requires_builder' => false,
			'danger'           => ! empty( $opts['danger'] ),
			'description'      => $desc,
			'parameters'       => array( 'type' => 'object', 'properties' => $schema_props, 'required' => $opts['req'] ?? array() ),
			'callback'         => $cb,
			'summary_callback' => $sum,
		) );
	}

	/** Register read-only taxonomy tools. */
	private static function register_read_tools(): void {
		self::reg( 'list_terms', 'List taxonomy terms (categories, tags, or CPT taxonomies) with count, hierarchy, and search.', array(
			'taxonomy'   => array( 'type' => 'string', 'description' => 'Target taxonomy (default "category").' ),
			'hide_empty' => array( 'type' => 'boolean', 'description' => 'Whether to hide terms with 0 posts (default false).' ),
			'search'     => array( 'type' => 'string', 'description' => 'Optional term name search query.' ),
			'number'     => array( 'type' => 'integer', 'description' => 'Max number of terms to return (default 100, max 100).' ),
		), array( __CLASS__, 'tool_list_terms' ), null, array( 'cap' => 'manage_categories' ) );
	}

	/** Register write taxonomy tools requiring user approval. */
	private static function register_write_tools(): void {
		self::reg( 'create_term', 'Create a new category, tag, or custom taxonomy term. Requires user approval.', array(
			'term'        => array( 'type' => 'string', 'description' => 'Term name.' ),
			'taxonomy'    => array( 'type' => 'string', 'description' => 'Taxonomy slug (default "category").' ),
			'parent'      => array( 'type' => 'integer', 'description' => 'Parent term ID (for hierarchical taxonomies).' ),
			'description' => array( 'type' => 'string', 'description' => 'Term description.' ),
		), array( __CLASS__, 'tool_create_term' ), array( __CLASS__, 'summarize_create_term' ), array( 'cap' => 'manage_categories', 'undo' => array( 'adapter' => 'term', 'id_arg' => 'term_id' ), 'req' => array( 'term' ) ) );

		self::reg( 'update_term', 'Update an existing taxonomy term name, slug, parent, or description with automatic snapshot. Requires user approval.', array(
			'term_id'     => array( 'type' => 'integer', 'description' => 'Numeric term ID.' ),
			'taxonomy'    => array( 'type' => 'string', 'description' => 'Taxonomy slug (default "category").' ),
			'name'        => array( 'type' => 'string', 'description' => 'New term name.' ),
			'slug'        => array( 'type' => 'string', 'description' => 'New term slug.' ),
			'description' => array( 'type' => 'string', 'description' => 'Updated description.' ),
			'parent'      => array( 'type' => 'integer', 'description' => 'Updated parent term ID.' ),
		), array( __CLASS__, 'tool_update_term' ), array( __CLASS__, 'summarize_update_term' ), array( 'cap' => 'manage_categories', 'undo' => array( 'adapter' => 'term', 'id_arg' => 'term_id' ), 'req' => array( 'term_id' ) ) );

		self::reg( 'delete_term', 'Delete a taxonomy term. Rejected if term has assigned posts (count > 0). Requires user approval.', array(
			'term_id'  => array( 'type' => 'integer', 'description' => 'Numeric term ID to delete.' ),
			'taxonomy' => array( 'type' => 'string', 'description' => 'Taxonomy slug (default "category").' ),
		), array( __CLASS__, 'tool_delete_term' ), array( __CLASS__, 'summarize_delete_term' ), array( 'cap' => 'manage_categories', 'danger' => true, 'undo' => array( 'adapter' => 'none' ), 'req' => array( 'term_id' ) ) );

		self::reg( 'assign_terms', 'Assign categories, tags, or custom terms to a post/page/CPT. Requires user approval.', array(
			'post_id'  => array( 'type' => 'integer', 'description' => 'Target post ID.' ),
			'terms'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Array of term names, slugs, or IDs.' ),
			'taxonomy' => array( 'type' => 'string', 'description' => 'Taxonomy slug (default "category").' ),
			'append'   => array( 'type' => 'boolean', 'description' => 'Whether to append (true) or replace existing terms (false, default).' ),
		), array( __CLASS__, 'tool_assign_terms' ), array( __CLASS__, 'summarize_assign_terms' ), array( 'cap' => 'edit_posts', 'undo' => array( 'adapter' => 'term', 'id_arg' => 'post_id' ), 'req' => array( 'post_id', 'terms' ) ) );
	}

	/** Summary for create_term. */
	public static function summarize_create_term( array $args ): array {
		$name = sanitize_text_field( (string) ( $args['term'] ?? 'Untitled' ) );
		$tax  = sanitize_key( (string) ( $args['taxonomy'] ?? 'category' ) );
		return array(
			'title'        => sprintf( __( 'Create %1$s: "%2$s"', 'align-agent' ), ucfirst( $tax ), $name ),
			'description'  => sprintf( __( 'Add new term to "%s" taxonomy.', 'align-agent' ), $tax ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'taxonomy' => $tax, 'term' => $name ),
		);
	}

	/** Summary for update_term. */
	public static function summarize_update_term( array $args ): array {
		$id   = absint( $args['term_id'] ?? 0 );
		$tax  = sanitize_key( (string) ( $args['taxonomy'] ?? 'category' ) );
		$term = get_term( $id, $tax );
		$name = ! empty( $args['name'] ) ? sanitize_text_field( (string) $args['name'] ) : ( $term && ! is_wp_error( $term ) ? $term->name : '#' . $id );
		return array(
			'title'        => sprintf( __( 'Update %1$s #%2$d ("%3$s")', 'align-agent' ), ucfirst( $tax ), $id, $name ),
			'description'  => __( 'Modify term attributes with audit log snapshot.', 'align-agent' ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'term_id' => $id, 'taxonomy' => $tax ),
		);
	}

	/** Summary for delete_term. */
	public static function summarize_delete_term( array $args ): array {
		$id   = absint( $args['term_id'] ?? 0 );
		$tax  = sanitize_key( (string) ( $args['taxonomy'] ?? 'category' ) );
		$term = get_term( $id, $tax );
		$name = ( $term && ! is_wp_error( $term ) ) ? $term->name : '#' . $id;
		return array(
			'title'        => sprintf( __( 'Delete %1$s #%2$d ("%3$s")', 'align-agent' ), ucfirst( $tax ), $id, $name ),
			'description'  => sprintf( __( 'Delete term from %s (only allowed when 0 posts assigned).', 'align-agent' ), $tax ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'term_id' => $id, 'taxonomy' => $tax ),
		);
	}

	/** Summary for assign_terms. */
	public static function summarize_assign_terms( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$tax     = sanitize_key( (string) ( $args['taxonomy'] ?? 'category' ) );
		$terms   = is_array( $args['terms'] ?? null ) ? implode( ', ', $args['terms'] ) : (string) ( $args['terms'] ?? '' );
		$append  = ! empty( $args['append'] );
		return array(
			'title'        => sprintf( __( 'Assign %1$s to Post #%2$d', 'align-agent' ), ucfirst( $tax ), $post_id ),
			'description'  => sprintf( __( 'Set terms: [%1$s] (mode: %2$s).', 'align-agent' ), $terms, $append ? 'append' : 'replace' ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'post_id' => $post_id, 'taxonomy' => $tax, 'append' => $append ),
		);
	}

	/** Tool: list_terms. */
	public static function tool_list_terms( array $args = array() ): array {
		$taxonomy = ! empty( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : 'category';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( 'success' => false, 'error' => 'invalid_taxonomy', 'message' => sprintf( __( 'Taxonomy "%s" does not exist.', 'align-agent' ), $taxonomy ) );
		}

		$query_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => ! empty( $args['hide_empty'] ),
			'number'     => min( 100, absint( $args['number'] ?? 100 ) ?: 100 ),
		);
		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = sanitize_text_field( (string) $args['search'] );
		}

		$terms = get_terms( $query_args );
		if ( is_wp_error( $terms ) ) {
			return array( 'success' => false, 'error' => $terms->get_error_code(), 'message' => $terms->get_error_message() );
		}

		$items = array();
		foreach ( (array) $terms as $t ) {
			$items[] = array(
				'term_id'     => (int) $t->term_id,
				'name'        => (string) $t->name,
				'slug'        => (string) $t->slug,
				'description' => (string) $t->description,
				'parent'      => (int) $t->parent,
				'count'       => (int) $t->count,
			);
		}

		return array(
			'success'  => true,
			'taxonomy' => $taxonomy,
			'total'    => count( $items ),
			'terms'    => $items,
		);
	}

	/** Tool: create_term. */
	public static function tool_create_term( array $args = array() ): array {
		$term_name = sanitize_text_field( (string) ( $args['term'] ?? '' ) );
		$taxonomy  = ! empty( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : 'category';

		if ( '' === $term_name ) {
			return array( 'success' => false, 'error' => 'missing_term_name', 'message' => __( 'Term name is required.', 'align-agent' ) );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( 'success' => false, 'error' => 'invalid_taxonomy', 'message' => sprintf( __( 'Taxonomy "%s" does not exist.', 'align-agent' ), $taxonomy ) );
		}

		$term_args = array();
		if ( isset( $args['parent'] ) ) {
			$term_args['parent'] = absint( $args['parent'] );
		}
		if ( isset( $args['description'] ) ) {
			$term_args['description'] = sanitize_textarea_field( (string) $args['description'] );
		}

		$res = wp_insert_term( $term_name, $taxonomy, $term_args );
		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
		}

		$term_id = absint( $res['term_id'] ?? 0 );
		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'create_term', $term_id, 'create', array( 'taxonomy' => $taxonomy, 'name' => $term_name ) );

		$fresh = get_term( $term_id, $taxonomy );
		return array(
			'success'  => true,
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'name'     => $fresh ? (string) $fresh->name : $term_name,
			'slug'     => $fresh ? (string) $fresh->slug : '',
			'message'  => sprintf( __( 'Created term "%1$s" (ID: %2$d) in taxonomy "%3$s".', 'align-agent' ), $term_name, $term_id, $taxonomy ),
			'ui_card'  => array( 'title' => sprintf( __( 'Created %1$s: %2$s', 'align-agent' ), ucfirst( $taxonomy ), $term_name ), 'message' => sprintf( __( 'ID: #%d', 'align-agent' ), $term_id ), 'url' => '', 'post_id' => 0, 'rollback_revision_id' => 0 ),
		);
	}

	/** Tool: update_term. */
	public static function tool_update_term( array $args = array() ): array {
		$term_id  = absint( $args['term_id'] ?? 0 );
		$taxonomy = ! empty( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : 'category';

		if ( $term_id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_id', 'message' => __( 'A valid numeric term_id is required.', 'align-agent' ) );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( 'success' => false, 'error' => 'invalid_taxonomy', 'message' => sprintf( __( 'Taxonomy "%s" does not exist.', 'align-agent' ), $taxonomy ) );
		}

		$snapshot = Align_Agent_Audit_Log::snapshot_term( $term_id, $taxonomy );
		if ( ! $snapshot ) {
			return array( 'success' => false, 'error' => 'term_not_found', 'message' => sprintf( __( 'Term #%d was not found in taxonomy "%s".', 'align-agent' ), $term_id, $taxonomy ) );
		}

		$update_args = array();
		if ( ! empty( $args['name'] ) ) {
			$update_args['name'] = sanitize_text_field( (string) $args['name'] );
		}
		if ( ! empty( $args['slug'] ) ) {
			$update_args['slug'] = sanitize_title( (string) $args['slug'] );
		}
		if ( isset( $args['description'] ) ) {
			$update_args['description'] = sanitize_textarea_field( (string) $args['description'] );
		}
		if ( isset( $args['parent'] ) ) {
			$update_args['parent'] = absint( $args['parent'] );
		}

		$res = wp_update_term( $term_id, $taxonomy, $update_args );
		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'update_term', $term_id, 'update', array( 'taxonomy' => $taxonomy, 'snapshot' => $snapshot, 'changes' => $update_args ) );

		$fresh = get_term( $term_id, $taxonomy );
		return array(
			'success'           => true,
			'term_id'           => $term_id,
			'taxonomy'          => $taxonomy,
			'name'              => $fresh ? (string) $fresh->name : '',
			'slug'              => $fresh ? (string) $fresh->slug : '',
			'previous_snapshot' => $snapshot,
			'message'           => sprintf( __( 'Updated term #%1$d in taxonomy "%2$s".', 'align-agent' ), $term_id, $taxonomy ),
			'ui_card'           => array( 'title' => sprintf( __( 'Updated %1$s #%2$d', 'align-agent' ), ucfirst( $taxonomy ), $term_id ), 'message' => sprintf( __( 'Name: %s', 'align-agent' ), $fresh ? $fresh->name : '' ), 'url' => '', 'post_id' => 0, 'rollback_revision_id' => 0 ),
		);
	}

	/** Tool: delete_term. */
	public static function tool_delete_term( array $args = array() ): array {
		$term_id  = absint( $args['term_id'] ?? 0 );
		$taxonomy = ! empty( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : 'category';

		if ( $term_id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_id', 'message' => __( 'A valid numeric term_id is required.', 'align-agent' ) );
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return array( 'success' => false, 'error' => 'term_not_found', 'message' => sprintf( __( 'Term #%d was not found in taxonomy "%s".', 'align-agent' ), $term_id, $taxonomy ) );
		}

		if ( (int) $term->count > 0 ) {
			return array(
				'success' => false,
				'error'   => 'term_not_empty',
				'count'   => (int) $term->count,
				'message' => sprintf( __( 'Cannot delete term "%1$s" because it has %2$d assigned post(s). Reassign or detach posts before deletion.', 'align-agent' ), $term->name, (int) $term->count ),
			);
		}

		$snapshot = Align_Agent_Audit_Log::snapshot_term( $term_id, $taxonomy );
		$deleted  = wp_delete_term( $term_id, $taxonomy );
		if ( is_wp_error( $deleted ) || ! $deleted ) {
			return array( 'success' => false, 'error' => 'delete_failed', 'message' => sprintf( __( 'Failed to delete term #%d.', 'align-agent' ), $term_id ) );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'delete_term', $term_id, 'delete', array( 'taxonomy' => $taxonomy, 'snapshot' => $snapshot ) );
		return array(
			'success'  => true,
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'name'     => (string) $term->name,
			'message'  => sprintf( __( 'Deleted term "%1$s" (#%2$d) from taxonomy "%3$s".', 'align-agent' ), $term->name, $term_id, $taxonomy ),
			'ui_card'  => array( 'title' => sprintf( __( 'Deleted %1$s #%2$d', 'align-agent' ), ucfirst( $taxonomy ), $term_id ), 'message' => __( 'Term successfully removed.', 'align-agent' ), 'url' => '', 'post_id' => 0, 'rollback_revision_id' => 0 ),
		);
	}

	/** Tool: assign_terms. */
	public static function tool_assign_terms( array $args = array() ): array {
		$post_id  = absint( $args['post_id'] ?? 0 );
		$taxonomy = ! empty( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : 'category';
		$append   = ! empty( $args['append'] );

		if ( $post_id <= 0 ) {
			return array( 'success' => false, 'error' => 'invalid_post_id', 'message' => __( 'A valid numeric post_id is required.', 'align-agent' ) );
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( __( 'Post #%d was not found.', 'align-agent' ), $post_id ) );
		}
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array( 'success' => false, 'error' => 'invalid_taxonomy', 'message' => sprintf( __( 'Taxonomy "%s" does not exist.', 'align-agent' ), $taxonomy ) );
		}

		$raw_terms = $args['terms'] ?? array();
		if ( is_string( $raw_terms ) ) {
			$raw_terms = array_filter( array_map( 'trim', explode( ',', $raw_terms ) ) );
		} elseif ( ! is_array( $raw_terms ) ) {
			$raw_terms = array( $raw_terms );
		}

		// Parse terms: convert numeric string terms to integer IDs if applicable.
		$parsed_terms = array();
		foreach ( $raw_terms as $t ) {
			$parsed_terms[] = is_numeric( $t ) ? absint( $t ) : sanitize_text_field( (string) $t );
		}

		$res = wp_set_object_terms( $post_id, $parsed_terms, $taxonomy, $append );
		if ( is_wp_error( $res ) ) {
			return array( 'success' => false, 'error' => $res->get_error_code(), 'message' => $res->get_error_message() );
		}

		Align_Agent_Audit_Log::log_action( get_current_user_id(), 'assign_terms', $post_id, 'update', array( 'taxonomy' => $taxonomy, 'terms' => $parsed_terms, 'append' => $append ) );

		$assigned = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
		$names    = is_array( $assigned ) ? $assigned : array();

		return array(
			'success'        => true,
			'post_id'        => $post_id,
			'taxonomy'       => $taxonomy,
			'assigned_terms' => $names,
			'append'         => $append,
			'message'        => sprintf( __( 'Assigned %1$d term(s) to Post #%2$d in taxonomy "%3$s".', 'align-agent' ), count( $names ), $post_id, $taxonomy ),
			'ui_card'        => array( 'title' => sprintf( __( 'Assigned Terms: Post #%d', 'align-agent' ), $post_id ), 'message' => sprintf( __( 'Taxonomy: %1$s | Terms: %2$s', 'align-agent' ), $taxonomy, implode( ', ', $names ) ), 'url' => (string) get_permalink( $post_id ), 'post_id' => $post_id, 'rollback_revision_id' => 0 ),
		);
	}
}
