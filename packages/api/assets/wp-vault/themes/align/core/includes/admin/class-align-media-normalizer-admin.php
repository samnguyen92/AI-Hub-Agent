<?php
/**
 * Media Filename Normalizer Admin Controller for Align Theme.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Media_Normalizer_Admin {

	const OPTION_CHECKPOINT = 'align_media_normalizer_checkpoint';
	const NONCE_ACTION      = 'align_media_normalizer_nonce';
	const CAPABILITY        = 'upload_files';

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'media_row_actions', array( __CLASS__, 'add_media_row_action' ), 10, 2 );
		add_filter( 'bulk_actions-upload', array( __CLASS__, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_footer-upload.php', array( __CLASS__, 'render_upload_modal' ) );
		add_action( 'wp_ajax_align_media_normalizer_get_ids', array( __CLASS__, 'ajax_get_ids' ) );
		add_action( 'wp_ajax_align_media_normalizer_process_single', array( __CLASS__, 'ajax_process_single' ) );
		add_action( 'wp_ajax_align_media_normalizer_reset', array( __CLASS__, 'ajax_reset_checkpoint' ) );
	}

	public static function enqueue_admin_assets( $hook ) {
		$is_settings_page = ( false !== strpos( $hook, 'align-settings' ) );
		$is_media_page    = ( 'upload.php' === $hook );

		if ( ! $is_settings_page && ! $is_media_page ) {
			return;
		}

		$theme_uri = get_template_directory_uri();
		$version   = defined( 'ALIGN_VERSION' ) ? ALIGN_VERSION : '1.0.0';

		wp_enqueue_style( 'align-media-normalizer', $theme_uri . '/core/assets/admin/css/align-media-normalizer.css', array(), $version );
		wp_enqueue_script( 'align-media-normalizer', $theme_uri . '/core/assets/admin/js/align-media-normalizer.js', array( 'jquery' ), $version, true );

		$prefilled_ids = array();
		if ( ! empty( $_GET['align_normalizer_ids'] ) ) {
			$raw_ids       = sanitize_text_field( wp_unslash( $_GET['align_normalizer_ids'] ) );
			$prefilled_ids = array_filter( array_map( 'intval', explode( ',', $raw_ids ) ) );
		}

		$checkpoint = (int) get_option( self::OPTION_CHECKPOINT, 0 );

		wp_localize_script(
			'align-media-normalizer',
			'alignNormalizerData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'checkpoint'   => $checkpoint,
				'prefilledIds' => array_values( $prefilled_ids ),
				'isUploadPage' => $is_media_page,
				'strings'      => array(
					'confirmStart' => __( 'Are you sure you want to format all media filenames? This renames files on disk and updates database records.', 'align' ),
					'stopping'     => __( 'Stopping process...', 'align' ),
					'complete'     => __( 'Filename formatting completed!', 'align' ),
					'serverError'  => __( 'Server communication error occurred.', 'align' ),
					'btnFormat'    => __( 'Format Filenames', 'align' ),
				),
			)
		);
	}

	public static function add_media_row_action( $actions, $post ) {
		if ( current_user_can( self::CAPABILITY ) ) {
			$actions['align_format_name'] = sprintf(
				'<a href="#" class="align-format-name-link" data-id="%d" title="%s">%s</a>',
				(int) $post->ID,
				esc_attr__( 'Format this filename to clean lowercase kebab-case', 'align' ),
				esc_html__( 'Format Name', 'align' )
			);
		}
		return $actions;
	}

	public static function add_bulk_actions( $actions ) {
		$actions['align_bulk_format_name'] = __( 'Format Filenames', 'align' );
		return $actions;
	}

	public static function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'align_bulk_format_name' !== $action || empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return $redirect_to;
		}

		check_admin_referer( 'bulk-media' );
		$ids = implode( ',', array_filter( array_map( 'intval', $post_ids ) ) );

		return add_query_arg(
			array( 'align_normalizer_ids' => $ids ),
			admin_url( 'upload.php' )
		);
	}

	public static function render_upload_modal() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$has_checkpoint = (bool) get_option( self::OPTION_CHECKPOINT, false );
		?>
		<div id="align-normalizer-modal" class="align-normalizer-modal-overlay" style="display: none;">
			<div class="align-normalizer-modal-content">
				<div class="align-normalizer-modal-header">
					<div>
						<h3 class="align-normalizer-modal-title"><?php esc_html_e( 'Format Media Filenames', 'align' ); ?></h3>
						<p class="align-normalizer-modal-desc"><?php esc_html_e( 'Sanitize filenames to clean lowercase kebab-case format on disk and database.', 'align' ); ?></p>
					</div>
					<button type="button" class="align-normalizer-modal-close" id="align-normalizer-modal-close" aria-label="<?php esc_attr_e( 'Close dialog', 'align' ); ?>">&times;</button>
				</div>
				<div class="align-normalizer-stats-grid">
					<div class="align-normalizer-stat-box">
						<div class="align-normalizer-stat-label"><?php esc_html_e( 'Total Media', 'align' ); ?></div>
						<div class="align-normalizer-stat-val" id="align-norm-modal-total">0</div>
					</div>
					<div class="align-normalizer-stat-box is-changed">
						<div class="align-normalizer-stat-label"><?php esc_html_e( 'Formatted', 'align' ); ?></div>
						<div class="align-normalizer-stat-val" id="align-norm-modal-changed">0</div>
					</div>
					<div class="align-normalizer-stat-box is-clean">
						<div class="align-normalizer-stat-label"><?php esc_html_e( 'Already Clean', 'align' ); ?></div>
						<div class="align-normalizer-stat-val" id="align-norm-modal-clean">0</div>
					</div>
					<div class="align-normalizer-stat-box is-error">
						<div class="align-normalizer-stat-label"><?php esc_html_e( 'Errors', 'align' ); ?></div>
						<div class="align-normalizer-stat-val" id="align-norm-modal-error">0</div>
					</div>
				</div>
				<div class="align-normalizer-progress-wrapper" style="margin: 16px 0;">
					<div class="align-normalizer-progress-header" style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px;">
						<span id="align-norm-modal-progress-percent">0%</span>
						<span id="align-norm-modal-progress-count">0 / 0</span>
					</div>
					<div class="align-normalizer-progress-bar-bg">
						<div class="align-normalizer-progress-bar-fill" id="align-norm-modal-progress-fill"></div>
					</div>
				</div>
				<div class="align-normalizer-actions-bar" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px;">
					<button type="button" class="button button-primary" id="align-norm-modal-start-btn">
						<span class="align-btn-text"><?php esc_html_e( 'Format All Media', 'align' ); ?></span>
					</button>
					<button type="button" class="button button-secondary" id="align-norm-modal-resume-btn" style="<?php echo $has_checkpoint ? 'display: inline-flex;' : 'display: none;'; ?>">
						<span class="align-btn-text"><?php esc_html_e( 'Resume Last Job', 'align' ); ?></span>
					</button>
					<button type="button" class="button button-danger" id="align-norm-modal-cancel-btn" style="display: none;">
						<span class="align-btn-text"><?php esc_html_e( 'Cancel', 'align' ); ?></span>
					</button>
					<button type="button" class="button button-secondary" id="align-norm-modal-retry-btn" style="display: none;">
						<span class="align-btn-text"><?php esc_html_e( 'Retry Failed', 'align' ); ?></span>
					</button>
				</div>
				<div class="align-normalizer-console-wrapper">
					<div class="align-normalizer-console-header">
						<span><?php esc_html_e( 'Live Processing Log', 'align' ); ?></span>
						<button type="button" class="align-normalizer-console-clear-btn" id="align-norm-modal-clear-log-btn"><?php esc_html_e( 'Clear Log', 'align' ); ?></button>
					</div>
					<div class="align-normalizer-console-body" id="align-norm-modal-console-body">
						<div class="align-normalizer-log-entry"><?php esc_html_e( 'Ready to format media filenames.', 'align' ); ?></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function ajax_get_ids() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized user capability.', 'align' ) ) );
		}

		global $wpdb;
		$mode       = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'all';
		$custom_ids = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
		$ids        = array();

		if ( ! empty( $custom_ids ) ) {
			$ids = array_values( array_filter( array_map( 'intval', explode( ',', $custom_ids ) ) ) );
			delete_option( self::OPTION_CHECKPOINT );
		} elseif ( 'resume' === $mode ) {
			$checkpoint = (int) get_option( self::OPTION_CHECKPOINT, PHP_INT_MAX );
			if ( $checkpoint <= 0 ) {
				$checkpoint = PHP_INT_MAX;
			}
			$sql = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID < %d AND post_type = 'attachment' ORDER BY ID DESC", $checkpoint );
			$ids = array_map( 'intval', $wpdb->get_col( $sql ) );
		} else {
			$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' ORDER BY ID DESC";
			$ids = array_map( 'intval', $wpdb->get_col( $sql ) );
		}

		error_log( sprintf( '[Align MediaNormalizer] Fetched %d IDs for processing (Mode: %s)', count( $ids ), $mode ) );
		wp_send_json_success( array( 'ids' => $ids, 'total' => count( $ids ) ) );
	}

	public static function ajax_process_single() {
		$nonce = isset( $_POST['security'] ) ? sanitize_key( $_POST['security'] ) : '';
		$tick  = wp_verify_nonce( $nonce, self::NONCE_ACTION );

		if ( ! $tick || ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token or unauthorized permissions.', 'align' ) ) );
		}

		$current_nonce = ( 2 === $tick ) ? wp_create_nonce( self::NONCE_ACTION ) : $nonce;
		$id            = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( empty( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing attachment ID.', 'align' ) ) );
		}

		// Raise execution limits for image processing
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 300 );
		}

		// Prevent race condition using transient lock
		$lock_key = 'align_norm_lock_' . $id;
		if ( get_transient( $lock_key ) ) {
			wp_send_json_error( array(
				'id'            => $id,
				'message'       => __( 'File is currently being processed by another task.', 'align' ),
				'current_nonce' => $current_nonce,
			) );
		}
		set_transient( $lock_key, time(), 60 );

		$result = Align_Media_Normalizer::normalize_attachment( $id );
		delete_transient( $lock_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'id'            => $id,
				'message'       => $result->get_error_message(),
				'current_nonce' => $current_nonce,
			) );
		}

		update_option( self::OPTION_CHECKPOINT, $id );

		wp_send_json_success( array(
			'id'            => $id,
			'old_filename'  => $result['old_filename'],
			'new_filename'  => $result['new_filename'],
			'changed'       => $result['changed'],
			'renamed_count' => $result['renamed_count'],
			'current_nonce' => $current_nonce,
		) );
	}

	public static function ajax_reset_checkpoint() {
		check_ajax_referer( self::NONCE_ACTION, 'security' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized permissions.', 'align' ) ) );
		}

		delete_option( self::OPTION_CHECKPOINT );
		wp_send_json_success();
	}
}
