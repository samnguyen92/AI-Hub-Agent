<?php
/**
 * Align Agent - Server-Generated Run Report
 *
 * Builds a plain-text ASD-STE100 summary of changes executed during a turn.
 * Each sentence contains at most 20 words and expresses one clear action.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Run_Report {

	/**
	 * Singleton instance for static proxy access.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * List of change bullet lines.
	 *
	 * @var array<string>
	 */
	private array $changes = array();

	/**
	 * List of required user action bullet lines.
	 *
	 * @var array<string>
	 */
	private array $todos = array();

	/**
	 * Active Change Set ID for the current turn.
	 *
	 * @var string
	 */
	private string $change_set_id = '';

	/**
	 * Constructor. Initializes bullet collections.
	 */
	public function __construct() {
		$this->changes        = array();
		$this->todos          = array();
		$this->change_set_id  = '';
		self::$instance       = $this;
	}

	/**
	 * Retrieve active instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Reset recorded changes and todos for a fresh turn.
	 */
	public static function reset(): void {
		self::$instance = new self();
	}

	/**
	 * Get current Change Set ID.
	 *
	 * @return string
	 */
	public function get_change_set_id(): string {
		return $this->change_set_id;
	}

	/**
	 * Set current Change Set ID.
	 *
	 * @param string $id Change Set ID.
	 * @return self
	 */
	public function set_change_set_id( string $id ): self {
		$this->change_set_id = sanitize_key( $id );
		return $this;
	}

	/**
	 * Record a change or action during the active turn.
	 *
	 * @param string $kind Event kind: created, edited, edited_clone, uploaded, needs_approval, failed.
	 * @param array  $data Event payload data.
	 */
	public static function record( string $kind, array $data = array() ): void {
		self::get_instance()->record_entry( $kind, $data );
	}

	/**
	 * Record an event entry on this instance.
	 *
	 * @param string $kind Event kind.
	 * @param array  $data Event payload data.
	 * @return self
	 */
	public function record_entry( string $kind, array $data = array() ): self {
		switch ( $kind ) {
			case 'created':
				$type   = ! empty( $data['type'] ) ? strtolower( (string) $data['type'] ) : 'page';
				$title  = ! empty( $data['title'] ) ? (string) $data['title'] : ( ! empty( $data['name'] ) ? (string) $data['name'] : 'Item' );
				$url    = ! empty( $data['url'] ) ? (string) $data['url'] : ( ! empty( $data['link'] ) ? (string) $data['link'] : '' );
				$status = ! empty( $data['status'] ) ? (string) $data['status'] : 'draft';

				if ( 'redirect' === $type ) {
					$src    = (string) ( $data['source'] ?? '' );
					$tgt    = (string) ( $data['target'] ?? '' );
					$bullet = sprintf( '• Created redirect from "%s" to "%s".', $src, $tgt );
				} else {
					$status_tag = ( 'draft' === $status ) ? ' (draft)' : '';
					$bullet     = sprintf( '• Created %s "%s"%s.', $type, $title, $status_tag );
					if ( '' !== $url ) {
						$bullet .= ' View: ' . $url;
					}
				}
				$this->add_change( $bullet );
				break;

			case 'edited':
				$type      = ! empty( $data['type'] ) ? strtolower( (string) $data['type'] ) : 'page';
				$title     = ! empty( $data['title'] ) ? (string) $data['title'] : ( ! empty( $data['name'] ) ? (string) $data['name'] : 'Item' );
				$url       = ! empty( $data['url'] ) ? (string) $data['url'] : ( ! empty( $data['link'] ) ? (string) $data['link'] : '' );
				$backup_id = ! empty( $data['backup_id'] ) ? absint( $data['backup_id'] ) : 0;
				$bullet    = sprintf( '• Edited %s "%s".', $type, $title );
				if ( $backup_id > 0 ) {
					$bullet .= sprintf( ' Backup: #%d.', $backup_id );
				}
				if ( '' !== $url ) {
					$bullet .= ' View: ' . $url;
				}
				$this->add_change( $bullet );
				break;

			case 'edited_clone':
				$title  = ! empty( $data['title'] ) ? (string) $data['title'] : ( ! empty( $data['name'] ) ? (string) $data['name'] : 'Item' );
				$url    = ! empty( $data['url'] ) ? (string) $data['url'] : ( ! empty( $data['link'] ) ? (string) $data['link'] : '' );
				$bullet = sprintf( '• Edited a draft copy of "%s".', $title );
				if ( '' !== $url ) {
					$bullet .= ' View: ' . $url;
				}
				$this->add_change( $bullet );
				$this->add_todo( sprintf( '• Check the draft copy of "%s".', $title ) );
				$this->add_todo( '• Tap "Apply to live page" to publish the changes.' );
				break;

			case 'uploaded':
				$count = ! empty( $data['count'] ) ? max( 1, (int) $data['count'] ) : 1;
				$title = ! empty( $data['title'] ) ? (string) $data['title'] : '';
				if ( $count > 1 ) {
					$bullet = sprintf( '• Added %d images to the Media Library.', $count );
				} elseif ( '' !== $title ) {
					$bullet = sprintf( '• Added image "%s" to the Media Library.', $title );
				} else {
					$bullet = '• Added 1 image to the Media Library.';
				}
				$this->add_change( $bullet );
				break;

			case 'needs_approval':
				$this->add_todo( '• Tap "Approve" or "Cancel" on the confirmation card.' );
				break;

			case 'failed':
				$this->add_todo( '• Review the error message and retry your request.' );
				break;
		}

		return $this;
	}

	/**
	 * Append a unique change bullet.
	 *
	 * @param string $bullet Bullet line.
	 */
	private function add_change( string $bullet ): void {
		if ( ! in_array( $bullet, $this->changes, true ) ) {
			$this->changes[] = $bullet;
		}
	}

	/**
	 * Append a unique todo action bullet.
	 *
	 * @param string $todo Action bullet line.
	 */
	private function add_todo( string $todo ): void {
		if ( ! in_array( $todo, $this->todos, true ) ) {
			$this->todos[] = $todo;
		}
	}

	/**
	 * Count total changes recorded.
	 *
	 * @return int
	 */
	public static function count_changes(): int {
		return count( self::get_instance()->changes );
	}

	/**
	 * Render plain text ASD-STE100 report.
	 *
	 * @return string Rendered report text, or empty string if no events occurred.
	 */
	public static function render_plain(): string {
		return self::get_instance()->render_plain_internal();
	}

	/**
	 * Internal render method.
	 *
	 * @return string
	 */
	public function render_plain_internal(): string {
		$change_count = count( $this->changes );
		if ( 0 === $change_count && empty( $this->todos ) ) {
			return '';
		}

		if ( 1 === $change_count ) {
			$header = '✅ Done. I made 1 change.';
		} elseif ( $change_count > 1 ) {
			$header = sprintf( '✅ Done. I made %d changes.', $change_count );
		} else {
			$header = '✅ Done. I made no changes.';
		}

		$sections = array( $header );

		if ( ! empty( $this->changes ) ) {
			$sections[] = "What changed:\n" . implode( "\n", $this->changes );
		}

		if ( ! empty( $this->todos ) ) {
			$sections[] = "What you need to do:\n" . implode( "\n", $this->todos );
		}

		if ( ! empty( $this->change_set_id ) && class_exists( 'Align_Agent_Change_Set' ) ) {
			$cs = Align_Agent_Change_Set::get( $this->change_set_id );
			if ( $cs && ! empty( $cs['entries'] ) ) {
				$undoable_count = 0;
				$total_entries  = count( $cs['entries'] );
				foreach ( $cs['entries'] as $e ) {
					if ( ! empty( $e['undoable'] ) ) {
						$undoable_count++;
					}
				}
				if ( $undoable_count > 0 && $undoable_count === $total_entries ) {
					$sections[] = '↩️ You can undo these changes.';
				} elseif ( $undoable_count > 0 ) {
					$sections[] = '↩️ You can undo some of these changes.';
				} else {
					$sections[] = 'ℹ️ These changes cannot be undone.';
				}
			}
		}

		return implode( "\n\n", $sections );
	}

	/**
	 * Map an executed tool result to report event records.
	 *
	 * @param int    $user_id     Acting user ID.
	 * @param string $fn_name     Tool function name.
	 * @param array  $args        Tool arguments.
	 * @param array  $exec_result Execution result array.
	 */
	public static function record_event( int $user_id, string $fn_name, array $args, array $exec_result ): void {
		if ( empty( $exec_result['success'] ) ) {
			self::record( 'failed', array( 'tool_name' => $fn_name, 'error' => $exec_result['error'] ?? 'failure' ) );
			return;
		}

		if ( 'create_page' === $fn_name ) {
			$title   = ! empty( $args['title'] ) ? (string) $args['title'] : 'Page';
			$post_id = absint( $exec_result['post_id'] ?? ( $exec_result['id'] ?? 0 ) );
			$url     = $post_id ? (string) get_permalink( $post_id ) : ( $exec_result['permalink'] ?? '' );
			self::record( 'created', array(
				'title'  => $title,
				'url'    => $url,
				'type'   => 'page',
				'status' => $args['status'] ?? 'draft',
			) );
			return;
		}

		if ( 'create_post' === $fn_name ) {
			$title   = ! empty( $args['title'] ) ? (string) $args['title'] : 'Post';
			$post_id = absint( $exec_result['post_id'] ?? ( $exec_result['id'] ?? 0 ) );
			$url     = $post_id ? (string) get_permalink( $post_id ) : ( $exec_result['permalink'] ?? '' );
			self::record( 'created', array(
				'title'  => $title,
				'url'    => $url,
				'type'   => 'post',
				'status' => $args['status'] ?? 'draft',
			) );
			return;
		}

		if ( 'create_redirect' === $fn_name ) {
			self::record( 'created', array(
				'type'   => 'redirect',
				'source' => $args['source'] ?? '',
				'target' => $args['target'] ?? '',
			) );
			return;
		}

		if ( in_array( $fn_name, array( 'save_page', 'patch_content' ), true ) ) {
			$target_type = sanitize_key( (string) ( $args['target_type'] ?? 'page' ) );
			if ( 'template' === $target_type ) {
				self::record( 'edited', array(
					'title'     => (string) ( $args['tp_id'] ?? 'template' ),
					'type'      => 'template',
					'backup_id' => $exec_result['backup_id'] ?? 0,
				) );
				return;
			}
			$post_id = absint( $args['post_id'] ?? ( $args['id'] ?? 0 ) );
			$title   = $post_id ? get_the_title( $post_id ) : ( $args['title'] ?? 'Page' );
			$url     = $post_id ? (string) get_permalink( $post_id ) : '';
			self::record( 'edited', array(
				'title'     => $title,
				'url'       => $url,
				'type'      => 'reuse_block' === $target_type ? 'reusable block' : 'page',
				'backup_id' => $exec_result['backup_id'] ?? 0,
			) );
			return;
		}

		if ( 'save_post' === $fn_name ) {
			$post_id = absint( $args['post_id'] ?? ( $args['id'] ?? 0 ) );
			$title   = $post_id ? get_the_title( $post_id ) : ( $args['title'] ?? 'Post' );
			$url     = $post_id ? (string) get_permalink( $post_id ) : '';
			self::record( 'edited', array(
				'title' => $title,
				'url'   => $url,
				'type'  => 'post',
			) );
			return;
		}

		if ( in_array( $fn_name, array( 'attach_media', 'sideload_media', 'upload_media' ), true ) ) {
			self::record( 'uploaded', array(
				'count' => 1,
				'title' => $args['title'] ?? ( $args['filename'] ?? '' ),
			) );
			return;
		}

		if ( 0 === strpos( $fn_name, 'save_seo_' ) ) {
			$post_id = absint( $args['post_id'] ?? ( $args['id'] ?? 0 ) );
			$title   = $post_id ? get_the_title( $post_id ) : 'Page';
			$url     = $post_id ? (string) get_permalink( $post_id ) : '';
			self::record( 'edited', array(
				'title' => $title,
				'url'   => $url,
				'type'  => 'page SEO',
			) );
			return;
		}

		if ( 'create_translation' === $fn_name ) {
			$post_id = absint( $exec_result['post_id'] ?? 0 );
			$url     = $post_id ? ( $exec_result['preview_url'] ?? (string) get_permalink( $post_id ) ) : '';
			$title   = $post_id ? get_the_title( $post_id ) : 'Translation';
			self::record( 'created', array(
				'title'  => $title,
				'url'    => $url,
				'type'   => 'translation',
				'status' => $exec_result['status'] ?? 'draft',
			) );
			return;
		}

		if ( 'localize_post_urls' === $fn_name ) {
			$post_id = absint( $args['post_id'] ?? ( $exec_result['post_id'] ?? 0 ) );
			$title   = $post_id ? get_the_title( $post_id ) : 'Page';
			$url     = $post_id ? (string) get_permalink( $post_id ) : '';
			self::record( 'edited', array(
				'title' => $title,
				'url'   => $url,
				'type'  => 'page links',
			) );
			return;
		}

		$labels = array(
			'save_template'        => (string) ( $args['tp_id'] ?? 'site template' ),
			'save_reuse_block'     => 'reusable block',
			'save_global_code'     => 'global code',
			'restore_backup'       => (string) ( $exec_result['object_key'] ?? 'backup' ),
			'save_code_slot'       => 'tracking code',
			'update_site_settings' => 'site settings',
			'update_nav_links'     => 'site menu',
			'manage_language'      => 'site languages',
			'set_site_protection'  => 'site protection',
			'set_post_lock'        => 'page password',
			'undo_audit_action'    => 'an earlier change (undo)',
		);

		// Fallback for other write tools.
		self::record( 'edited', array(
			'title'     => $labels[ $fn_name ] ?? ( $args['title'] ?? $fn_name ),
			'type'      => 'item',
			'backup_id' => $exec_result['backup_id'] ?? 0,
		) );
	}
}
