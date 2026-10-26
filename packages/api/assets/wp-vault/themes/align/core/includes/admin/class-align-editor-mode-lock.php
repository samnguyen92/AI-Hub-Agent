<?php
/**
 * Align Theme - Editor Mode Lock & Switch Controller
 *
 * Enforces single-editor lock per post/page/CPT after first non-empty save,
 * lazy-detects legacy content on editor load, blocks unauthorized mode meta
 * mutations, and provides the destructive switch REST endpoint.
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Editor_Mode_Lock {

	/**
	 * Internal flag allowing controlled mode/lock meta updates during legitimate operations.
	 *
	 * @var bool
	 */
	private static $allowing_mode_switch = false;

	/**
	 * Initialize hooks and filters.
	 */
	public static function init(): void {
		add_action( 'load-post.php', array( __CLASS__, 'handle_editor_load_lazy_lock' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'handle_editor_load_lazy_lock' ), 5 );
		add_action( 'wp_after_insert_post', array( __CLASS__, 'handle_save_post_lock' ), 25, 3 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'filter_protect_locked_mode_meta' ), 10, 5 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'filter_protect_locked_mode_meta' ), 10, 5 );
		add_filter( 'delete_post_metadata', array( __CLASS__, 'filter_protect_locked_mode_meta' ), 10, 5 );
	}

	/**
	 * Retrieve list of supported post types for editor mode lock.
	 * Always includes 'page' and 'post' (D5, D7).
	 *
	 * @return array<int, string>
	 */
	public static function get_supported_post_types(): array {
		$types = class_exists( 'Align_Page_Settings' )
			? Align_Page_Settings::get_eligible_post_types()
			: array( 'post', 'page' );

		if ( ! in_array( 'page', $types, true ) ) {
			$types[] = 'page';
		}
		if ( ! in_array( 'post', $types, true ) ) {
			$types[] = 'post';
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Check whether a post type is eligible for editor mode locking.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public static function is_eligible_post_type( string $post_type ): bool {
		if ( '' === $post_type ) {
			return false;
		}
		return in_array( $post_type, self::get_supported_post_types(), true );
	}

	/**
	 * Default editor mode for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return string 'builder' or 'gutenberg'.
	 */
	public static function get_default_mode( string $post_type ): string {
		return ( 'page' === $post_type ) ? 'builder' : 'gutenberg';
	}

	/**
	 * Check if a post is currently locked to an editor mode.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_locked( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		return '1' === (string) get_post_meta( $post_id, Align_Page_Settings::META_EDITOR_LOCKED, true );
	}

	/**
	 * Get the effective editor mode for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string 'builder' or 'gutenberg'.
	 */
	public static function get_effective_mode( int $post_id ): string {
		$post      = get_post( $post_id );
		$post_type = $post ? $post->post_type : 'page';
		$meta_mode = get_post_meta( $post_id, Align_Page_Settings::META_EDITOR_MODE, true );
		if ( in_array( $meta_mode, array( 'builder', 'gutenberg' ), true ) ) {
			return $meta_mode;
		}
		return self::get_default_mode( $post_type );
	}

	/**
	 * Check if a post is locked specifically to the given mode.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $mode    'builder' or 'gutenberg'.
	 * @return bool
	 */
	public static function is_locked_to( int $post_id, string $mode ): bool {
		if ( ! self::is_locked( $post_id ) ) {
			return false;
		}
		return self::get_effective_mode( $post_id ) === $mode;
	}

	/**
	 * Detect editor mode from raw post_content using WordPress parse_blocks().
	 *
	 * Rules (§4.2):
	 * - Strip empty/whitespace-only blocks (blockName === null).
	 * - 0 blocks remaining -> null (empty content, unlocked).
	 * - Exactly 1 block with blockName === 'align/custom-html' -> 'builder'.
	 * - Otherwise -> 'gutenberg'.
	 *
	 * @param string $content Raw post_content.
	 * @return string|null 'builder', 'gutenberg', or null if empty.
	 */
	public static function detect_mode_from_content( string $content ): ?string {
		if ( '' === trim( $content ) ) {
			return null;
		}

		if ( ! function_exists( 'parse_blocks' ) ) {
			return ( false !== strpos( $content, '<!-- wp:align/custom-html' ) ) ? 'builder' : 'gutenberg';
		}

		$parsed = parse_blocks( $content );
		$blocks = array();

		foreach ( $parsed as $block ) {
			$name       = $block['blockName'] ?? null;
			$inner_html = isset( $block['innerHTML'] ) ? trim( (string) $block['innerHTML'] ) : '';
			if ( empty( $name ) && '' === $inner_html ) {
				continue;
			}
			$blocks[] = $block;
		}

		if ( empty( $blocks ) ) {
			return null;
		}

		if ( 1 === count( $blocks ) && 'align/custom-html' === ( $blocks[0]['blockName'] ?? '' ) ) {
			$inner = isset( $blocks[0]['innerHTML'] ) ? trim( (string) $blocks[0]['innerHTML'] ) : '';
			if ( '' === $inner ) {
				return null;
			}
			return 'builder';
		}

		return 'gutenberg';
	}

	/**
	 * Safely set editor mode and lock state bypassing the metadata guard filter.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $mode    'builder' or 'gutenberg'.
	 * @param bool   $locked  Whether the post is locked.
	 */
	public static function set_mode_and_lock( int $post_id, string $mode, bool $locked ): void {
		$prev_flag                  = self::$allowing_mode_switch;
		self::$allowing_mode_switch = true;
		try {
			if ( in_array( $mode, array( 'builder', 'gutenberg' ), true ) ) {
				update_post_meta( $post_id, Align_Page_Settings::META_EDITOR_MODE, $mode );
			}
			update_post_meta( $post_id, Align_Page_Settings::META_EDITOR_LOCKED, $locked ? '1' : '0' );
		} finally {
			self::$allowing_mode_switch = $prev_flag;
		}
	}

	/**
	 * Lazy lock legacy content when opening an existing post in the editor (§4.2).
	 *
	 * @param int $target_post_id Optional explicit post ID.
	 */
	public static function maybe_lazy_lock_post( int $target_post_id = 0 ): void {
		$post_id = $target_post_id;
		if ( $post_id <= 0 ) {
			if ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ) {
				$post_id = intval( $_GET['post'] );
			} elseif ( isset( $_GET['post_id'] ) && is_numeric( $_GET['post_id'] ) ) {
				$post_id = intval( $_GET['post_id'] );
			} elseif ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
				$post_id = (int) $GLOBALS['post']->ID;
			}
		}

		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( ! self::is_eligible_post_type( $post->post_type ) ) {
			return;
		}

		if ( metadata_exists( 'post', $post_id, Align_Page_Settings::META_EDITOR_LOCKED ) ) {
			return;
		}

		$detected = self::detect_mode_from_content( (string) $post->post_content );
		if ( null === $detected ) {
			return;
		}

		self::set_mode_and_lock( $post_id, $detected, true );
		error_log( sprintf( '[Align Core] Editor mode locked: post #%d (%s) -> %s | source=detect', $post_id, $post->post_type, $detected ) );
	}

	/**
	 * Action callback for load-post.php and enqueue_block_editor_assets.
	 */
	public static function handle_editor_load_lazy_lock(): void {
		self::maybe_lazy_lock_post( 0 );
	}

	/**
	 * Lock editor mode on first non-empty save (§4.3).
	 * Hooked to wp_after_insert_post so REST meta updates finish before locking.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 */
	public static function handle_save_post_lock( $post_id, $post, $update ): void {
		$post_id = intval( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! ( $post instanceof WP_Post ) || 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
			return;
		}

		if ( ! self::is_eligible_post_type( $post->post_type ) ) {
			return;
		}

		if ( self::is_locked( $post_id ) ) {
			return;
		}

		$detected = self::detect_mode_from_content( (string) $post->post_content );
		if ( null === $detected ) {
			return;
		}

		$mode = $detected;
		if ( metadata_exists( 'post', $post_id, Align_Page_Settings::META_EDITOR_MODE ) ) {
			$saved_meta = get_post_meta( $post_id, Align_Page_Settings::META_EDITOR_MODE, true );
			if ( in_array( $saved_meta, array( 'builder', 'gutenberg' ), true ) ) {
				$mode = $saved_meta;
			}
		}

		self::set_mode_and_lock( $post_id, $mode, true );
		error_log( sprintf( '[Align Core] Editor mode locked: post #%d (%s) -> %s | source=save', $post_id, $post->post_type, $mode ) );
	}

	/**
	 * Block direct updates/insertions/deletions to _align_editor_mode and _align_editor_locked (§4.5).
	 *
	 * @param null|bool $check      Whether to allow metadata operation.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value.
	 * @param mixed     $prev_value Previous value, unique flag, or delete_all flag.
	 * @return null|bool
	 */
	public static function filter_protect_locked_mode_meta( $check, $object_id, $meta_key, $meta_value, $prev_value = '' ) {
		$protected = array( Align_Page_Settings::META_EDITOR_MODE, Align_Page_Settings::META_EDITOR_LOCKED );
		if ( ! in_array( $meta_key, $protected, true ) || self::$allowing_mode_switch ) {
			return $check;
		}

		$post_id = intval( $object_id );
		if ( $post_id <= 0 ) {
			return $check;
		}

		if ( Align_Page_Settings::META_EDITOR_LOCKED === $meta_key ) {
			if ( 'delete_post_metadata' === current_filter() ) {
				error_log( sprintf( '[Align Core] Blocked unauthorized deletion of _align_editor_locked on post #%d', $post_id ) );
				return false;
			}
			$target_locked = ( '1' === (string) $meta_value || 1 === $meta_value || true === $meta_value );
			if ( self::is_locked( $post_id ) !== $target_locked ) {
				error_log( sprintf( '[Align Core] Blocked unauthorized mutation of _align_editor_locked on post #%d', $post_id ) );
				return false;
			}
			return $check;
		}

		if ( ! self::is_locked( $post_id ) ) {
			return $check;
		}

		if ( 'delete_post_metadata' === current_filter() ) {
			error_log( sprintf( '[Align Core] Blocked unauthorized deletion of %s on locked post #%d', $meta_key, $post_id ) );
			return false;
		}

		$locked_mode = get_post_meta( $post_id, Align_Page_Settings::META_EDITOR_MODE, true );
		if ( in_array( $locked_mode, array( 'builder', 'gutenberg' ), true ) && $meta_value !== $locked_mode ) {
			error_log( sprintf( '[Align Core] Blocked unauthorized editor mode change on locked post #%d: %s -> %s', $post_id, $locked_mode, (string) $meta_value ) );
			return false;
		}

		return $check;
	}

	/**
	 * Permission callback for POST /switch-editor-mode (§4.4, D6).
	 * Checks valid REST nonce and edit_post capability on the specific post.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public static function check_switch_permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' ) ?: $request->get_param( '_wpnonce' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Invalid or missing REST nonce.', 'align' ), array( 'status' => 403 ) );
		}

		$post_id = intval( $request->get_param( 'post_id' ) );
		if ( $post_id <= 0 ) {
			return new WP_Error( 'missing_post_id', __( 'Valid post_id is required.', 'align' ), array( 'status' => 400 ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit this post.', 'align' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * REST endpoint callback: POST /wp-json/align-builder/v1/switch-editor-mode (§4.4).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_switch_editor_mode( WP_REST_Request $request ) {
		$post_id     = intval( $request->get_param( 'post_id' ) );
		$target_mode = sanitize_key( (string) $request->get_param( 'target_mode' ) );

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) || ! self::is_eligible_post_type( $post->post_type ) ) {
			return new WP_Error( 'invalid_post', __( 'Post not found or post type is not supported.', 'align' ), array( 'status' => 404 ) );
		}

		if ( ! in_array( $target_mode, array( 'builder', 'gutenberg' ), true ) ) {
			return new WP_Error( 'invalid_target_mode', __( 'Target mode must be "builder" or "gutenberg".', 'align' ), array( 'status' => 400 ) );
		}

		$current_mode = self::get_effective_mode( $post_id );
		if ( $target_mode === $current_mode ) {
			return new WP_Error( 'same_editor_mode', __( 'Target editor mode is already active.', 'align' ), array( 'status' => 400 ) );
		}

		$revision_id = 0;
		if ( post_type_supports( $post->post_type, 'revisions' ) && '' !== trim( (string) $post->post_content ) ) {
			$rev = wp_save_post_revision( $post_id );
			if ( is_int( $rev ) && $rev > 0 ) {
				$revision_id = $rev;
			} else {
				$revs = wp_get_post_revisions( $post_id, array( 'posts_per_page' => 1, 'fields' => 'ids' ) );
				if ( ! empty( $revs ) ) {
					$revision_id = (int) reset( $revs );
				}
			}
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return new WP_Error( 'clear_content_failed', $updated->get_error_message(), array( 'status' => 500 ) );
		}

		$assets_cleared = false;
		if ( 'builder' === $current_mode ) {
			if ( class_exists( 'Align_Builder_Asset_Cleaner' ) ) {
				Align_Builder_Asset_Cleaner::delete_post_assets( $post_id );
				$assets_cleared = true;
			}
			delete_post_meta( $post_id, '_align_reusable_overrides' );
		}

		self::set_mode_and_lock( $post_id, $target_mode, false );
		clean_post_cache( $post_id );

		error_log(
			sprintf(
				'[Align API] Switch editor mode: post #%d %s -> %s | revision=%d | assetsCleared=%s',
				$post_id,
				$current_mode,
				$target_mode,
				$revision_id,
				$assets_cleared ? 'true' : 'false'
			)
		);

		return rest_ensure_response(
			array(
				'status'         => 'success',
				'post_id'        => $post_id,
				'mode'           => $target_mode,
				'locked'         => false,
				'revision_id'    => $revision_id,
				'assets_cleared' => $assets_cleared,
			)
		);
	}

	/**
	 * Build localized configuration array for AlignEditorModeConfig (§4.9).
	 *
	 * @param int $post_id Optional post ID.
	 * @return array<string, mixed>
	 */
	public static function get_localized_config( int $post_id = 0 ): array {
		if ( $post_id <= 0 ) {
			if ( isset( $_GET['post'] ) && is_numeric( $_GET['post'] ) ) {
				$post_id = intval( $_GET['post'] );
			} elseif ( isset( $_GET['post_id'] ) && is_numeric( $_GET['post_id'] ) ) {
				$post_id = intval( $_GET['post_id'] );
			} elseif ( isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ) {
				$post_id = (int) $GLOBALS['post']->ID;
			}
		}

		$locked      = $post_id > 0 ? self::is_locked( $post_id ) : false;
		$locked_mode = ( $post_id > 0 && $locked ) ? self::get_effective_mode( $post_id ) : null;
		$rest_ns     = defined( 'ALIGN_BUILDER_REST_NS' ) ? ALIGN_BUILDER_REST_NS : 'align-builder/v1';

		return array(
			'postTypes'  => self::get_supported_post_types(),
			'locked'     => $locked,
			'lockedMode' => $locked_mode,
			'switchUrl'  => rest_url( $rest_ns . '/switch-editor-mode' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
		);
	}
}
