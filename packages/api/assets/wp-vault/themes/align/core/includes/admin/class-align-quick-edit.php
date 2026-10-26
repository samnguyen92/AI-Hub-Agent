<?php
/**
 * Align Theme - Quick Edit Universal Password Lock Integration
 *
 * Extends the WordPress Quick Edit interface on post list tables (edit.php)
 * to support the Align Universal Password Lock engine. Adds lock status column,
 * row metadata injection, seamless Quick Edit controls replacing native password
 * inputs, and secure lock persistence.
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Quick_Edit {

	/**
	 * Nonce action for quick edit save.
	 */
	const NONCE_ACTION = 'align_quick_edit_action';

	/**
	 * Nonce name for quick edit save.
	 */
	const NONCE_NAME = 'align_quick_edit_nonce';

	/**
	 * Initialize hooks and filters.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'render_quick_edit_box' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_quick_edit' ), 10, 2 );

		// Register list table columns immediately and on admin_init for dynamic post types
		self::register_post_type_columns();
		add_action( 'admin_init', array( __CLASS__, 'register_post_type_columns' ) );

		// Inject row data into actions as secondary fallback anchor
		add_filter( 'post_row_actions', array( __CLASS__, 'inject_row_data' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'inject_row_data' ), 10, 2 );
	}

	/**
	 * Register list table columns and cell callbacks for all eligible post types.
	 */
	public static function register_post_type_columns(): void {
		if ( ! class_exists( 'Align_Page_Settings' ) ) {
			return;
		}

		$eligible_types = Align_Page_Settings::get_eligible_post_types();
		foreach ( $eligible_types as $post_type ) {
			if ( 'page' === $post_type ) {
				add_filter( 'manage_pages_columns', array( __CLASS__, 'register_column' ) );
				add_action( 'manage_pages_custom_column', array( __CLASS__, 'render_column_cell' ), 10, 2 );
			} else {
				add_filter( "manage_{$post_type}_posts_columns", array( __CLASS__, 'register_column' ) );
				add_action( "manage_{$post_type}_posts_custom_column", array( __CLASS__, 'render_column_cell' ), 10, 2 );
			}
		}
	}

	/**
	 * Enqueue Quick Edit script and inline styles on list table screens.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->post_type ) ) {
			return;
		}

		if ( ! class_exists( 'Align_Page_Settings' ) ) {
			return;
		}

		$eligible_types = Align_Page_Settings::get_eligible_post_types();
		if ( ! in_array( $screen->post_type, $eligible_types, true ) ) {
			return;
		}

		$base_uri  = get_template_directory_uri();
		$js_path   = get_template_directory() . '/core/assets/admin/js/align-quick-edit.js';
		$theme_ver = file_exists( $js_path ) ? (string) filemtime( $js_path ) : time();

		wp_enqueue_script(
			'align-quick-edit',
			$base_uri . '/core/assets/admin/js/align-quick-edit.js',
			array( 'jquery', 'inline-edit-post' ),
			$theme_ver,
			true
		);

		$custom_css = '
			/* Hide native WordPress password input, label, and OR separator in Quick Edit */
			#posts-filter .inline-edit-password-input,
			#posts-filter label:has(.inline-edit-password-input),
			#posts-filter .inline-edit-or {
				display: none !important;
			}

			/* Align Password Lock Column Header & Cell */
			.manage-column.column-align_password_lock {
				width: 48px;
				text-align: center;
			}
			.column-align_password_lock {
				text-align: center;
				vertical-align: middle;
			}
			.column-align_password_lock .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				line-height: 1.2;
				vertical-align: middle;
			}
			.column-align_password_lock .align-lock-active {
				color: #0284c7;
			}
			.column-align_password_lock .align-lock-inactive {
				color: #cbd5e1;
			}

			/* Align Quick Edit Dedicated Password Card */
			.align-quick-edit-password-wrap {
				clear: both;
				margin: 10px 0 8px 0;
				padding: 10px 12px;
				background: #ffffff;
				border: 1px solid #e2e8f0;
				border-radius: 6px;
			}
			.align-qe-lock-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				margin-bottom: 6px;
			}
			.align-qe-lock-title {
				font-size: 12px;
				font-weight: 600;
				color: #1e293b;
				display: flex;
				align-items: center;
				gap: 5px;
			}
			.align-qe-lock-title .dashicons {
				font-size: 14px;
				width: 14px;
				height: 14px;
				color: #0284c7;
			}
			.align-qe-badge {
				font-size: 11px;
				font-weight: 600;
				padding: 2px 8px;
				border-radius: 4px;
			}
			.align-qe-badge-set {
				background: #f0fdf4;
				color: #166534;
				border: 1px solid #bbf7d0;
			}
			.align-qe-badge-none {
				background: #f8fafc;
				color: #64748b;
				border: 1px solid #e2e8f0;
			}
			.align-qe-badge-new {
				background: #f0f9ff;
				color: #0284c7;
				border: 1px solid #bae6fd;
			}
			.align-qe-badge-remove {
				background: #fef2f2;
				color: #dc2626;
				border: 1px solid #fecaca;
			}
			.align-qe-lock-controls {
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.align-qe-lock-controls .align-qe-password-input {
				flex: 1;
				height: 32px;
				border: 1px solid #cbd5e1;
				border-radius: 4px;
				padding: 0 8px;
				font-size: 13px;
			}
		';

		wp_register_style( 'align-quick-edit', false, array(), $theme_ver );
		wp_enqueue_style( 'align-quick-edit' );
		wp_add_inline_style( 'align-quick-edit', $custom_css );
	}

	/**
	 * Register Password Lock column directly after the Title column.
	 *
	 * @param array $columns Existing list table columns.
	 * @return array Modified columns.
	 */
	public static function register_column( array $columns ): array {
		$new_columns = array();
		$inserted    = false;

		foreach ( $columns as $key => $title ) {
			$new_columns[ $key ] = $title;
			if ( 'title' === $key ) {
				$new_columns['align_password_lock'] = '<span class="dashicons dashicons-lock" title="' . esc_attr__( 'Universal Password Lock', 'align' ) . '"></span><span class="screen-reader-text">' . esc_html__( 'Universal Password Lock', 'align' ) . '</span>';
				$inserted = true;
			}
		}

		if ( ! $inserted ) {
			$new_columns['align_password_lock'] = '<span class="dashicons dashicons-lock" title="' . esc_attr__( 'Universal Password Lock', 'align' ) . '"></span><span class="screen-reader-text">' . esc_html__( 'Universal Password Lock', 'align' ) . '</span>';
		}

		return $new_columns;
	}

	/**
	 * Render cell content for Password Lock column.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_column_cell( string $column, int $post_id ): void {
		if ( 'align_password_lock' !== $column ) {
			return;
		}

		$is_locked      = false;
		$has_password   = false;
		$plain_password = '';

		if ( class_exists( 'Align_Post_Password_Lock' ) ) {
			$is_locked      = Align_Post_Password_Lock::is_post_locked( $post_id );
			$hash           = get_post_meta( $post_id, Align_Post_Password_Lock::META_HASH, true );
			$post           = get_post( $post_id );
			$has_password   = ! empty( $hash ) || ( $post instanceof WP_Post && ! empty( $post->post_password ) );
			$plain_password = Align_Post_Password_Lock::get_password( $post_id );
		}

		if ( $is_locked ) {
			echo '<span class="dashicons dashicons-lock align-lock-active" title="' . esc_attr__( 'Password Locked', 'align' ) . '"></span>';
		} else {
			echo '<span class="dashicons dashicons-unlock align-lock-inactive" title="' . esc_attr__( 'Unlocked', 'align' ) . '"></span>';
		}

		printf(
			'<span class="align-pw-lock-data" data-enabled="%s" data-has-password="%s" data-password="%s" style="display:none;"></span>',
			$is_locked ? '1' : '0',
			$has_password ? '1' : '0',
			esc_attr( $plain_password )
		);
	}

	/**
	 * Inject hidden password lock state into row actions as secondary fallback anchor.
	 *
	 * @param array   $actions Row actions list.
	 * @param WP_Post $post    Post object.
	 * @return array Modified actions.
	 */
	public static function inject_row_data( array $actions, $post ): array {
		$post = get_post( $post );
		if ( ! $post || ! ( $post instanceof WP_Post ) ) {
			return $actions;
		}

		$is_locked      = false;
		$has_password   = false;
		$plain_password = '';

		if ( class_exists( 'Align_Post_Password_Lock' ) ) {
			$is_locked      = Align_Post_Password_Lock::is_post_locked( $post->ID );
			$hash           = get_post_meta( $post->ID, Align_Post_Password_Lock::META_HASH, true );
			$has_password   = ! empty( $hash ) || ! empty( $post->post_password );
			$plain_password = Align_Post_Password_Lock::get_password( $post->ID );
		}

		$actions['align_pw_lock_data'] = sprintf(
			'<span class="align-pw-lock-data" data-enabled="%s" data-has-password="%s" data-password="%s" style="display:none;"></span>',
			$is_locked ? '1' : '0',
			$has_password ? '1' : '0',
			esc_attr( $plain_password )
		);

		return $actions;
	}

	/**
	 * Render custom Quick Edit box for password lock.
	 *
	 * @param string $column_name Current column being rendered.
	 * @param string $post_type   Current post type.
	 */
	public static function render_quick_edit_box( string $column_name, string $post_type ): void {
		if ( 'align_password_lock' !== $column_name ) {
			return;
		}

		if ( ! class_exists( 'Align_Page_Settings' ) ) {
			return;
		}

		$eligible_types = Align_Page_Settings::get_eligible_post_types();
		if ( ! in_array( $post_type, $eligible_types, true ) ) {
			return;
		}
		?>
		<fieldset class="align-quick-edit-password-fieldset" style="display:none;">
			<div class="align-quick-edit-password-wrap">
				<div class="align-qe-lock-header">
					<span class="align-qe-lock-title">
						<span class="dashicons dashicons-lock"></span>
						<?php esc_html_e( 'Password Protection', 'align' ); ?>
					</span>
					<span class="align-qe-badge align-qe-badge-none"><?php esc_html_e( 'No Password', 'align' ); ?></span>
				</div>
				<div class="align-qe-lock-controls">
					<input type="text" name="align_password_lock_password" class="align-qe-password-input text" value="" placeholder="<?php esc_attr_e( 'Password', 'align' ); ?>" autocomplete="off" autocorrect="off" spellcheck="false" />
					<input type="hidden" name="align_password_lock_managed" value="1" />
					<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Save Quick Edit password lock state and update post meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_quick_edit( $post_id, $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verify custom Quick Edit nonce or core inline edit nonce
		$nonce_valid = false;
		if ( isset( $_POST[ self::NONCE_NAME ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			$nonce_valid = true;
		} elseif ( isset( $_POST['_inline_edit'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_inline_edit'] ) ), 'inlineeditnonce' ) ) {
			$nonce_valid = true;
		}

		if ( ! $nonce_valid ) {
			return;
		}

		// Ensure password lock fields are managed in this request
		if ( ! isset( $_POST['align_password_lock_managed'] ) ) {
			return;
		}

		// Raw unslashed string to preserve all special characters in password
		$raw_password = isset( $_POST['align_password_lock_password'] ) ? trim( (string) wp_unslash( $_POST['align_password_lock_password'] ) ) : '';

		if ( '' !== $raw_password ) {
			if ( class_exists( 'Align_Post_Password_Lock' ) ) {
				Align_Post_Password_Lock::set_password( (int) $post_id, $raw_password );
			}
			error_log( sprintf( '[Align QuickEdit] Password saved and lock enabled for post ID: %d', $post_id ) );
		} else {
			if ( class_exists( 'Align_Post_Password_Lock' ) ) {
				Align_Post_Password_Lock::disable_lock( (int) $post_id );
			}
			error_log( sprintf( '[Align QuickEdit] Password empty; lock disabled for post ID: %d', $post_id ) );
		}

		// Always force WordPress native post_password to stay empty
		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array( 'post_password' => '' ),
			array( 'ID' => (int) $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );
	}
}
