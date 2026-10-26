<?php
/**
 * Align Agent Gateway - Telegram Media Ingestion & Staging.
 *
 * Handles photo/document extraction from Telegram updates, safe sideloading into
 * the WordPress Media Library, and ephemeral queuing for subsequent user prompts.
 *
 * @package AlignAgent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Media {

	/**
	 * Pending attachments meta key for Telegram channel staging.
	 */
	const META_KEY_PENDING = 'align_agent_tg_pending_media';

	/**
	 * Queue TTL in seconds (30 minutes).
	 */
	const QUEUE_TTL = 1800;

	/**
	 * Max attachments allowed in queue.
	 */
	const MAX_QUEUE_SIZE = 10;

	/**
	 * Max file size allowed (5MB).
	 */
	const MAX_FILE_SIZE = 5242880;

	/**
	 * Extract candidate media item (photo or image document) from Telegram message.
	 *
	 * @param array $message Raw Telegram message array.
	 * @return array{file_id: string, filename: string, file_size: int}|null
	 */
	public static function extract( array $message ): ?array {
		// 1. Photos: select the largest resolution photo <= 5MB.
		if ( ! empty( $message['photo'] ) && is_array( $message['photo'] ) ) {
			$largest = null;
			foreach ( $message['photo'] as $p ) {
				$size = isset( $p['file_size'] ) ? (int) $p['file_size'] : 0;
				if ( $size > self::MAX_FILE_SIZE ) {
					continue;
				}
				if ( null === $largest || $size >= ( $largest['file_size'] ?? 0 ) ) {
					$largest = $p;
				}
			}

			if ( $largest && ! empty( $largest['file_id'] ) ) {
				return array(
					'file_id'   => (string) $largest['file_id'],
					'filename'  => 'telegram_photo_' . time() . '.jpg',
					'file_size' => isset( $largest['file_size'] ) ? (int) $largest['file_size'] : 0,
				);
			}
		}

		// 2. Documents: accept only allowed image mime types.
		if ( ! empty( $message['document'] ) && is_array( $message['document'] ) ) {
			$doc  = $message['document'];
			$mime = isset( $doc['mime_type'] ) ? strtolower( trim( (string) $doc['mime_type'] ) ) : '';
			$size = isset( $doc['file_size'] ) ? (int) $doc['file_size'] : 0;

			$allowed_mimes = array(
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/webp' => 'webp',
				'image/gif'  => 'gif',
			);

			if ( isset( $allowed_mimes[ $mime ] ) && $size <= self::MAX_FILE_SIZE && ! empty( $doc['file_id'] ) ) {
				$raw_name = ! empty( $doc['file_name'] ) ? sanitize_file_name( (string) $doc['file_name'] ) : '';
				if ( empty( $raw_name ) ) {
					$raw_name = 'telegram_doc_' . time() . '.' . $allowed_mimes[ $mime ];
				}
				return array(
					'file_id'   => (string) $doc['file_id'],
					'filename'  => $raw_name,
					'file_size' => $size,
				);
			}
		}

		return null;
	}

	/**
	 * Ingest a Telegram file into WordPress Media Library.
	 *
	 * @param string $file_id Telegram file ID.
	 * @param string $filename Suggested file name.
	 * @param int    $bound_user_id WordPress admin user ID.
	 * @return array{id: int, url: string, filename: string}|WP_Error
	 */
	public static function ingest( string $file_id, string $filename, int $bound_user_id ) {
		$token = Align_Gateway_Telegram::get_bot_token();
		if ( empty( $token ) ) {
			return new WP_Error( 'no_token', __( 'Telegram bot token is not configured.', 'align-agent' ) );
		}

		// 1. Call getFile.
		$get_file_url = 'https://api.telegram.org/bot' . $token . '/getFile';
		$res          = wp_remote_post(
			$get_file_url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'file_id' => $file_id ) ),
			)
		);

		if ( is_wp_error( $res ) ) {
			error_log( sprintf( '[Align Agent] Telegram getFile failed: %s', $res->get_error_message() ) );
			return $res;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['ok'] ) || empty( $body['result']['file_path'] ) ) {
			$desc = ! empty( $body['description'] ) ? $body['description'] : 'getFile returned empty path';
			error_log( sprintf( '[Align Agent] Telegram getFile invalid response: %s', $desc ) );
			return new WP_Error( 'invalid_file_path', $desc );
		}

		$file_path = (string) $body['result']['file_path'];
		$file_size = isset( $body['result']['file_size'] ) ? (int) $body['result']['file_size'] : 0;
		if ( $file_size > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'file_too_large', __( 'Image file exceeds maximum 5MB limit.', 'align-agent' ) );
		}

		// 2. Build download URL without ever logging it.
		$download_url = 'https://api.telegram.org/file/bot' . $token . '/' . $file_path;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = download_url( $download_url, 30 );
		if ( is_wp_error( $tmp_file ) ) {
			error_log( sprintf( '[Align Agent] Telegram download_url failed: %s', $tmp_file->get_error_message() ) );
			return $tmp_file;
		}

		// 3. Security validation: size, extension, real MIME.
		$real_size = filesize( $tmp_file );
		if ( ! $real_size || $real_size > self::MAX_FILE_SIZE ) {
			@unlink( $tmp_file );
			return new WP_Error( 'file_too_large', __( 'Downloaded file exceeds 5MB.', 'align-agent' ) );
		}

		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'disallowed_extension', __( 'Invalid image extension.', 'align-agent' ) );
		}

		$checked = wp_check_filetype_and_ext( $tmp_file, $filename );
		$type    = ! empty( $checked['type'] ) ? $checked['type'] : '';
		if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'disallowed_mime', __( 'File content does not match allowed image MIME type.', 'align-agent' ) );
		}

		// 4. Sideload into Media Library.
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		$prev_user = get_current_user_id();
		if ( $bound_user_id > 0 ) {
			wp_set_current_user( $bound_user_id );
		}

		$attach_id = media_handle_sideload( $file_array, 0, 'Telegram upload: ' . $filename );

		if ( $bound_user_id > 0 && $prev_user !== $bound_user_id ) {
			wp_set_current_user( $prev_user );
		}

		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp_file );
			error_log( sprintf( '[Align Agent] Telegram media_handle_sideload error: %s', $attach_id->get_error_message() ) );
			return $attach_id;
		}

		$url = (string) wp_get_attachment_url( $attach_id );
		error_log( sprintf( '[Align Agent] Telegram ingested media | file_id: %s | attachment_id: %d', substr( $file_id, 0, 10 ) . '...', $attach_id ) );

		return array(
			'id'       => (int) $attach_id,
			'url'      => $url,
			'filename' => $filename,
		);
	}

	/**
	 * Append an ingested attachment to the user's pending queue.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $att     Attachment descriptor array {id, url, filename}.
	 * @return int Total queued items count.
	 */
	public static function queue( int $user_id, array $att ): int {
		$queue = self::get_queue( $user_id );
		$queue[] = array(
			'id'        => (int) $att['id'],
			'url'       => (string) $att['url'],
			'filename'  => (string) $att['filename'],
			'timestamp' => time(),
		);

		if ( count( $queue ) > self::MAX_QUEUE_SIZE ) {
			$queue = array_slice( $queue, -self::MAX_QUEUE_SIZE );
		}

		update_user_meta( $user_id, self::META_KEY_PENDING, $queue );
		return count( $queue );
	}

	/**
	 * Retrieve valid, unexpired pending attachments.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array List of pending attachments.
	 */
	public static function get_queue( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META_KEY_PENDING, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$now     = time();
		$valid   = array();
		$changed = false;

		foreach ( $raw as $item ) {
			$ts = isset( $item['timestamp'] ) ? (int) $item['timestamp'] : 0;
			if ( ( $now - $ts ) < self::QUEUE_TTL ) {
				$valid[] = $item;
			} else {
				$changed = true;
			}
		}

		if ( $changed ) {
			update_user_meta( $user_id, self::META_KEY_PENDING, $valid );
		}

		return $valid;
	}

	/**
	 * Clear the pending attachments queue.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function clear_queue( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY_PENDING );
	}

	/**
	 * Consume all pending attachments, clear the queue, and format standard AI text tags.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Formatted media tags or empty string if queue was empty.
	 */
	public static function consume( int $user_id ): string {
		$queue = self::get_queue( $user_id );
		if ( empty( $queue ) ) {
			return '';
		}

		self::clear_queue( $user_id );

		$lines = array();
		foreach ( $queue as $att ) {
			$lines[] = sprintf(
				'[Attached Media: attachment_id=%d, url=%s, file=%s]',
				(int) $att['id'],
				(string) $att['url'],
				(string) $att['filename']
			);
		}

		return implode( "\n", $lines );
	}
}
