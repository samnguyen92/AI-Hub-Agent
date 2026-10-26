<?php
/**
 * Align Theme - Page & Post Editor Settings Manager
 *
 * Handles per-page settings in the WordPress Block Editor (Gutenberg)
 * and Classic Editor, including jQuery loading, editor mode, and
 * Universal Password Lock controls.
 *
 * Adheres strictly to Align Coding Standards (< 500 lines, English logs/comments).
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Page_Settings {

	/**
	 * Post meta key for loading jQuery on demand.
	 */
	const META_LOAD_JQUERY = 'align_load_jquery';

	/**
	 * Post meta key for editor mode ('gutenberg' | 'builder').
	 */
	const META_EDITOR_MODE = '_align_editor_mode';

	/**
	 * Post meta key for editor mode lock state ('1' | '0').
	 */
	const META_EDITOR_LOCKED = '_align_editor_locked';

	/**
	 * Post meta key for password lock enablement ('1' | '0').
	 */
	const META_PASSWORD_LOCK_ENABLED = '_align_password_lock_enabled';

	/**
	 * Nonce action for classic editor fallback.
	 */
	const NONCE_ACTION = 'align_page_settings_save';

	/**
	 * Nonce name for classic editor fallback.
	 */
	const NONCE_NAME = 'align_page_settings_nonce';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_meta_fields' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_classic_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_classic_meta' ), 10, 2 );
	}

	/**
	 * Retrieve list of eligible post types for page settings and password lock.
	 *
	 * @return array Eligible post type slugs.
	 */
	public static function get_eligible_post_types(): array {
		$types    = get_post_types( array( 'show_ui' => true ), 'names' );
		$excluded = array( 'attachment', 'revision', 'nav_menu_item', 'wp_template', 'wp_template_part', 'wp_block' );
		return array_values( array_diff( $types, $excluded ) );
	}

	/**
	 * Register post meta fields for REST API and Gutenberg.
	 */
	public static function register_post_meta_fields() {
		register_post_meta(
			'',
			self::META_LOAD_JQUERY,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'boolean',
				'default'       => false,
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		$eligible_types = self::get_eligible_post_types();
		if ( ! in_array( 'page', $eligible_types, true ) ) {
			$eligible_types[] = 'page';
		}
		if ( ! in_array( 'post', $eligible_types, true ) ) {
			$eligible_types[] = 'post';
		}

		foreach ( $eligible_types as $post_type ) {
			$default_mode = ( 'page' === $post_type ) ? 'builder' : 'gutenberg';
			register_post_meta(
				$post_type,
				self::META_EDITOR_MODE,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'default'           => $default_mode,
					'sanitize_callback' => function( $val ) {
						return in_array( $val, array( 'gutenberg', 'builder' ), true ) ? $val : 'gutenberg';
					},
					'auth_callback'     => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::META_EDITOR_LOCKED,
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'default'           => '0',
					'sanitize_callback' => function( $val ) {
						return ( '1' === (string) $val || true === $val ) ? '1' : '0';
					},
					'auth_callback'     => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				$post_type,
				self::META_PASSWORD_LOCK_ENABLED,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'default'       => '0',
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Enqueue Gutenberg block editor scripts and styles for Document Settings panel.
	 */
	public static function enqueue_editor_assets() {
		$js_path = ALIGN_CORE_PATH . 'assets/admin/js/align-page-settings.js';
		$js_url  = ALIGN_CORE_URL . 'assets/admin/js/align-page-settings.js';
		$version = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0';

		wp_enqueue_script(
			'align-page-settings',
			$js_url,
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-data',
				'wp-core-data',
				'wp-components',
				'wp-api-fetch',
			),
			$version,
			true
		);

		// Hide native WordPress password visibility option in Gutenberg
		$css = '.editor-post-visibility__dialog-password, .components-radio-control__option:has(input[value="password"]) { display: none !important; }';
		wp_add_inline_style( 'wp-edit-post', $css );
	}

	/**
	 * Register classic meta box for non-block editor contexts across eligible post types.
	 */
	public static function register_classic_meta_box() {
		$screens = self::get_eligible_post_types();
		foreach ( $screens as $screen ) {
			add_meta_box(
				'align_page_settings_classic',
				__( 'Align Page Settings', 'align' ),
				array( __CLASS__, 'render_classic_meta_box' ),
				$screen,
				'side',
				'low',
				array(
					'__back_compat_meta_box' => true,
				)
			);
		}
	}

	/**
	 * Render classic meta box HTML.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_classic_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$load_jquery = (bool) get_post_meta( $post->ID, self::META_LOAD_JQUERY, true );
		$editor_mode = get_post_meta( $post->ID, self::META_EDITOR_MODE, true );
		if ( empty( $editor_mode ) ) {
			$editor_mode = ( 'page' === $post->post_type ) ? 'builder' : 'gutenberg';
		}
		$is_locked   = ( '1' === get_post_meta( $post->ID, self::META_EDITOR_LOCKED, true ) );
		?>
		<style>
			#visibility-radio-password, label[for="visibility-radio-password"] { display: none !important; }
			.align-meta-section { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
			.align-meta-section:last-child { border-bottom: none; }
		</style>

		<div class="align-meta-section">
			<label style="display: block; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e( 'Editor Mode', 'align' ); ?></label>
			<select name="<?php echo esc_attr( self::META_EDITOR_MODE ); ?>" style="width: 100%;" <?php disabled( $is_locked, true ); ?>>
				<option value="gutenberg" <?php selected( $editor_mode, 'gutenberg' ); ?>><?php esc_html_e( 'Gutenberg (Block Editor)', 'align' ); ?></option>
				<option value="builder" <?php selected( $editor_mode, 'builder' ); ?>><?php esc_html_e( 'Align Visual Builder', 'align' ); ?></option>
			</select>
			<p class="description" style="margin: 4px 0 0 0; font-size: 11.5px; color: #64748b;">
				<?php
				if ( $is_locked ) {
					echo esc_html( sprintf( __( 'Locked to %s. Use the switch button to change.', 'align' ), $editor_mode ) );
				} else {
					esc_html_e( 'Choose primary editor for this content.', 'align' );
				}
				?>
			</p>
		</div>

		<div class="align-meta-section">
			<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
				<input type="checkbox" name="<?php echo esc_attr( self::META_LOAD_JQUERY ); ?>" value="1" <?php checked( $load_jquery, true ); ?> />
				<span style="font-weight: 600;"><?php esc_html_e( 'Load jQuery', 'align' ); ?></span>
			</label>
			<p class="description" style="margin: 4px 0 0 24px; font-size: 11.5px; color: #64748b;">
				<?php esc_html_e( 'Enable WordPress jQuery for plugins or custom scripts on this page.', 'align' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save classic meta box fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_classic_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$load_jquery = ! empty( $_POST[ self::META_LOAD_JQUERY ] );
		update_post_meta( $post_id, self::META_LOAD_JQUERY, $load_jquery );

		if ( '1' === get_post_meta( $post_id, self::META_EDITOR_LOCKED, true ) ) {
			return;
		}

		if ( isset( $_POST[ self::META_EDITOR_MODE ] ) ) {
			$editor_mode = sanitize_text_field( wp_unslash( $_POST[ self::META_EDITOR_MODE ] ) );
			if ( in_array( $editor_mode, array( 'gutenberg', 'builder' ), true ) ) {
				update_post_meta( $post_id, self::META_EDITOR_MODE, $editor_mode );
			}
		}
	}
}
