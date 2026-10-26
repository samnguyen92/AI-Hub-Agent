<?php
/**
 * Align Agent - Media Management & Sideload Tools
 *
 * Registers media management tools:
 * - list_media (read)
 * - set_featured_image, attach_media, sideload_media, update_media_meta, trash_media (write)
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Media {
	const MAX_SIDELOAD_BYTES = 5242880;
	const ALLOWED_MIMES = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );

	/**
	 * Convert any internal site/home URL to a Dynamic Root-Relative URL starting with '/'.
	 *
	 * @param string $url Input URL.
	 * @return string Root-relative URL (e.g. '/wp-content/uploads/...' or '/slug/').
	 */
	public static function to_root_relative_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( str_starts_with( $url, 'data:' ) || str_starts_with( $url, 'blob:' ) || str_starts_with( $url, '#' ) || str_starts_with( $url, 'mailto:' ) || str_starts_with( $url, 'tel:' ) ) {
			return $url;
		}
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			return $url;
		}

		$site_host = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		if ( '' !== $url_host && ( $url_host === $site_host || $url_host === $home_host ) ) {
			$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
			$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
			$frag  = (string) wp_parse_url( $url, PHP_URL_FRAGMENT );
			$rel   = ( '' !== $path && str_starts_with( $path, '/' ) ) ? $path : '/' . ltrim( $path, '/' );
			if ( '' !== $query ) {
				$rel .= '?' . $query;
			}
			if ( '' !== $frag ) {
				$rel .= '#' . $frag;
			}
			return $rel;
		}

		if ( str_starts_with( $url, 'wp-content/' ) ) {
			return '/' . $url;
		}

		return $url;
	}

	/**
	 * Sanitize all occurrences of site_url() and home_url() (both http and https) inside HTML/CSS
	 * into Dynamic Root-Relative URLs starting with '/'.
	 *
	 * @param string $content HTML or CSS string.
	 * @return string Sanitized string with root-relative internal URLs.
	 */
	public static function sanitize_internal_urls_to_dynamic( string $content ): string {
		if ( '' === $content ) {
			return '';
		}

		$bases = array_unique( array_filter( array(
			untrailingslashit( site_url() ),
			untrailingslashit( home_url() ),
			untrailingslashit( set_url_scheme( site_url(), 'http' ) ),
			untrailingslashit( set_url_scheme( site_url(), 'https' ) ),
			untrailingslashit( set_url_scheme( home_url(), 'http' ) ),
			untrailingslashit( set_url_scheme( home_url(), 'https' ) ),
		) ) );

		foreach ( $bases as $base ) {
			// Replace base followed by slash (e.g. http://trusted-union.local/wp-content/... -> /wp-content/...)
			$content = str_ireplace( $base . '/', '/', $content );
			// Replace exact quoted root URL (e.g. href="http://trusted-union.local" -> href="/")
			$content = str_ireplace(
				array( '"' . $base . '"', "'" . $base . "'", '(' . $base . ')' ),
				array( '"/"', "'/'", '(/)' ),
				$content
			);
		}

		return $content;
	}

	/**
	 * Build structured available sizes map with Dynamic Root-Relative URLs for an attachment.
	 *
	 * @param int $att_id Attachment ID.
	 * @return array<string, array{url: string, width: int, height: int}>
	 */
	public static function get_dynamic_available_sizes( int $att_id ): array {
		$available = array();
		if ( class_exists( 'Align_Image_Sizes' ) && method_exists( 'Align_Image_Sizes', 'get_attachment_sizes_data' ) ) {
			$data = Align_Image_Sizes::get_attachment_sizes_data( $att_id );
			if ( ! empty( $data['sizes'] ) && is_array( $data['sizes'] ) ) {
				foreach ( $data['sizes'] as $s ) {
					if ( ! empty( $s['name'] ) && ! empty( $s['url'] ) ) {
						$available[ (string) $s['name'] ] = array(
							'url'    => self::to_root_relative_url( (string) $s['url'] ),
							'width'  => (int) ( $s['width'] ?? 0 ),
							'height' => (int) ( $s['height'] ?? 0 ),
						);
					}
				}
			}
			if ( ! isset( $available['full'] ) && ! empty( $data['full_url'] ) ) {
				$available['full'] = array(
					'url'    => self::to_root_relative_url( (string) $data['full_url'] ),
					'width'  => (int) ( $data['width'] ?? 0 ),
					'height' => (int) ( $data['height'] ?? 0 ),
				);
			}
			if ( ! empty( $available ) ) {
				return $available;
			}
		}

		$meta     = wp_get_attachment_metadata( $att_id );
		$full_src = wp_get_attachment_image_src( $att_id, 'full' );
		$full_url = $full_src ? (string) $full_src[0] : (string) wp_get_attachment_url( $att_id );
		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $info ) {
				$src = wp_get_attachment_image_src( $att_id, $name );
				if ( $src && ! empty( $src[0] ) ) {
					$available[ (string) $name ] = array(
						'url'    => self::to_root_relative_url( (string) $src[0] ),
						'width'  => (int) ( $info['width'] ?? $src[1] ?? 0 ),
						'height' => (int) ( $info['height'] ?? $src[2] ?? 0 ),
					);
				}
			}
		}
		if ( '' !== $full_url ) {
			$available['full'] = array(
				'url'    => self::to_root_relative_url( $full_url ),
				'width'  => (int) ( $full_src[1] ?? $meta['width'] ?? 0 ),
				'height' => (int) ( $full_src[2] ?? $meta['height'] ?? 0 ),
			);
		}
		return $available;
	}

	/** Initialize media read and write tools. */
	public static function init(): void {
		self::register_read_tools();
		self::register_write_tools();
	}

	/** Register read-only media tools (list_media, list_page_images). */
	private static function register_read_tools(): void {
		Align_Agent_Tool_Registry::register( 'list_media', array(
			'type'        => 'read',
			'group'       => 'media',
			'capability'  => 'upload_files',
			'description' => 'List image attachments from the Media Library with optional search, source filter (e.g. "telegram"), telegram_message_id lookup, and pagination. Returns Dynamic Root-Relative URLs (/wp-content/uploads/...).',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'search'              => array( 'type' => 'string', 'description' => 'Optional search keyword.' ),
					'source'              => array( 'type' => 'string', 'description' => 'Optional source filter (e.g., "telegram" to list only photos uploaded from Telegram).' ),
					'telegram_message_id' => array( 'type' => 'integer', 'description' => 'Optional Telegram message_id to locate the exact image sent in that message.' ),
					'page'                => array( 'type' => 'integer', 'description' => 'Page number (default 1).' ),
					'per_page'            => array( 'type' => 'integer', 'description' => 'Items per page (default 20, max 50).' ),
				),
			),
			'callback'    => array( __CLASS__, 'tool_list_media' ),
		) );

		Align_Agent_Tool_Registry::register( 'list_page_images', array(
			'type'        => 'read',
			'group'       => 'media',
			'capability'  => 'edit_posts',
			'description' => 'Inspect and list all images (<picture> and standalone <img>) inside a specific Page or Post in DOM order (0-based index), returning each image\'s index, attachment_id, Dynamic Root-Relative src, alt text, responsive size settings, and surrounding text/heading context_snippet so you can identify which image to replace.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'Numeric WordPress page or post ID to inspect.' ),
				),
				'required'   => array( 'post_id' ),
			),
			'callback'    => array( __CLASS__, 'tool_list_page_images' ),
		) );
	}

	/** Register write media tools. */
	private static function register_write_tools(): void {
		Align_Agent_Tool_Registry::register( 'upload_media', array(
			'type'        => 'write',
			'group'       => 'media',
			'capability'  => 'upload_files',
			'undo'        => array( 'adapter' => 'media' ),
			'description' => 'Upload an image (from base64_data or url) into the WordPress Media Library via media_handle_sideload(), automatically triggering Align Theme WebP conversion and sub-size generation. Deduplicates by telegram_file_unique_id and returns attachment_id, Dynamic Root-Relative URL (/wp-content/uploads/...webp), and available_sizes.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'base64_data'             => array( 'type' => 'string', 'description' => 'Optional base64-encoded image payload.' ),
					'url'                     => array( 'type' => 'string', 'description' => 'Optional HTTPS image URL to download if base64_data is not provided.' ),
					'filename'                => array( 'type' => 'string', 'description' => 'Optional filename (default: "telegram-photo.jpg").' ),
					'alt'                     => array( 'type' => 'string', 'description' => 'Optional alt text / title for the uploaded image.' ),
					'telegram_message_id'     => array( 'type' => 'integer', 'description' => 'Optional Telegram message_id containing the photo.' ),
					'telegram_chat_id'        => array( 'type' => 'string', 'description' => 'Optional Telegram chat_id.' ),
					'telegram_file_unique_id' => array( 'type' => 'string', 'description' => 'Optional Telegram file_unique_id for idempotent deduplication.' ),
				),
			),
			'callback'         => array( __CLASS__, 'tool_upload_media' ),
			'summary_callback' => array( __CLASS__, 'summarize_upload_media' ),
		) );

		Align_Agent_Tool_Registry::register( 'replace_page_image', array(
			'type'             => 'write',
			'group'            => 'media',
			'capability'       => 'edit_posts',
			'requires_builder' => true,
			'undo'             => array( 'adapter' => 'post', 'id_arg' => 'post_id' ),
			'description'      => 'Replace an existing image inside a Page or Post (identified by target_index or target_src from list_page_images) with new_attachment_id, generating an Align Builder 3-tier responsive <picture> (or standalone <img> when skip_picture=true) using 100% Dynamic Root-Relative URLs (/wp-content/uploads/...).',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'           => array( 'type' => 'integer', 'description' => 'Target page or post ID.' ),
					'target_index'      => array( 'type' => 'integer', 'description' => '0-based image index on the page (from list_page_images).' ),
					'target_src'        => array( 'type' => 'string', 'description' => 'Alternative to target_index: current src URL of the image to replace.' ),
					'new_attachment_id' => array( 'type' => 'integer', 'description' => 'WordPress attachment_id of the new image (from upload_media or list_media).' ),
					'alt'               => array( 'type' => 'string', 'description' => 'Optional new alt text (defaults to existing page alt or attachment alt).' ),
					'mobile_size'       => array( 'type' => 'string', 'description' => 'Responsive size for Mobile (<=599px). Default "auto". Pass "full" or "large" if user requests high-res desktop image on mobile.' ),
					'tablet_size'       => array( 'type' => 'string', 'description' => 'Responsive size for Tablet (<=992px). Default "auto".' ),
					'desktop_size'      => array( 'type' => 'string', 'description' => 'Responsive size for Desktop (>992px). Default "auto".' ),
					'skip_picture'      => array( 'type' => 'boolean', 'description' => 'Default false. Set true to output a single <img> with data-skip-picture="true".' ),
				),
				'required'   => array( 'post_id', 'new_attachment_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_replace_page_image' ),
			'summary_callback' => array( __CLASS__, 'summarize_replace_page_image' ),
		) );
		Align_Agent_Tool_Registry::register( 'set_featured_image', array(
			'type'             => 'write',
			'group'            => 'media',
			'capability'       => 'edit_posts',
			'undo'             => array( 'adapter' => 'post' ),
			'description'      => 'Set Featured Image (thumbnail) for a page or post. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array( 'post_id' => array( 'type' => 'integer', 'description' => 'Post/page ID.' ), 'attachment_id' => array( 'type' => 'integer', 'description' => 'Media attachment ID.' ) ),
				'required'   => array( 'post_id', 'attachment_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_set_featured_image' ),
			'summary_callback' => array( __CLASS__, 'summarize_set_featured_image' ),
		) );

		Align_Agent_Tool_Registry::register( 'attach_media', array(
			'type'             => 'write',
			'group'            => 'media',
			'capability'       => 'upload_files',
			'undo'             => array( 'adapter' => 'meta', 'meta_keys' => array( '_wp_attachment_image_alt' ) ),
			'description'      => 'Attach Media Library image to parent post and optionally update alt text and title. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer', 'description' => 'Media attachment ID.' ),
					'post_id'       => array( 'type' => 'integer', 'description' => 'Parent post/page ID.' ),
					'alt_text'      => array( 'type' => 'string', 'description' => 'Optional alt text.' ),
					'title'         => array( 'type' => 'string', 'description' => 'Optional title.' ),
				),
				'required'   => array( 'attachment_id', 'post_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_attach_media' ),
			'summary_callback' => array( __CLASS__, 'summarize_attach_media' ),
		) );

		Align_Agent_Tool_Registry::register( 'sideload_media', array(
			'type'             => 'write',
			'group'            => 'media',
			'capability'       => 'upload_files',
			'undo'             => array( 'adapter' => 'media' ),
			'description'      => 'Download user-provided HTTPS image (raster only, max 5MB) into Media Library. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'url'      => array( 'type' => 'string', 'description' => 'HTTPS image URL explicitly provided by user.' ),
					'post_id'  => array( 'type' => 'integer', 'description' => 'Optional parent post ID.' ),
					'alt_text' => array( 'type' => 'string', 'description' => 'Optional alt text.' ),
					'title'    => array( 'type' => 'string', 'description' => 'Optional title.' ),
				),
				'required'   => array( 'url' ),
			),
			'callback'         => array( __CLASS__, 'tool_sideload_media' ),
			'summary_callback' => array( __CLASS__, 'summarize_sideload_media' ),
		) );

		Align_Agent_Tool_Registry::register( 'update_media_meta', array(
			'type'             => 'write',
			'group'            => 'media',
			'capability'       => 'upload_files',
			'undo'             => array( 'adapter' => 'meta', 'meta_keys' => array( '_wp_attachment_image_alt' ) ),
			'description'      => 'Update attachment alt text, title, caption, or description in Media Library. Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer', 'description' => 'Media attachment ID.' ),
					'alt'           => array( 'type' => 'string', 'description' => 'Alternative text.' ),
					'title'         => array( 'type' => 'string', 'description' => 'Attachment title.' ),
					'caption'       => array( 'type' => 'string', 'description' => 'Caption (post_excerpt).' ),
					'description'   => array( 'type' => 'string', 'description' => 'Description (post_content).' ),
				),
				'required'   => array( 'attachment_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_update_media_meta' ),
			'summary_callback' => array( __CLASS__, 'summarize_update_media_meta' ),
		) );

		Align_Agent_Tool_Registry::register( 'trash_media', array(
			'type'             => 'write',
			'group'            => 'media',
			'capability'       => 'delete_posts',
			'danger'           => true,
			'undo'             => array( 'adapter' => 'trash' ),
			'description'      => 'Move an attachment to trash (only when MEDIA_TRASH is true and image is not in use). Requires user approval.',
			'parameters'       => array(
				'type'       => 'object',
				'properties' => array( 'attachment_id' => array( 'type' => 'integer', 'description' => 'Media ID to trash.' ) ),
				'required'   => array( 'attachment_id' ),
			),
			'callback'         => array( __CLASS__, 'tool_trash_media' ),
			'summary_callback' => array( __CLASS__, 'summarize_trash_media' ),
		) );
	}

	/** Execute list_media tool. */
	public static function tool_list_media( array $args = array() ): array {
		$page       = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page   = min( 50, max( 1, absint( $args['per_page'] ?? 20 ) ) );
		$search     = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';
		$source     = isset( $args['source'] ) ? sanitize_key( (string) $args['source'] ) : '';
		$tg_msg_id  = isset( $args['telegram_message_id'] ) ? absint( $args['telegram_message_id'] ) : 0;

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'paged'          => $page,
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$meta_query = array();
		if ( '' !== $source ) {
			$meta_query[] = array(
				'key'   => '_align_source',
				'value' => $source,
			);
		}
		if ( $tg_msg_id > 0 ) {
			$meta_query[] = array(
				'key'   => '_align_tg_message_id',
				'value' => (string) $tg_msg_id,
			);
		}
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		$query = new WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $att ) {
			if ( ! ( $att instanceof WP_Post ) ) {
				continue;
			}
			$meta        = wp_get_attachment_metadata( $att->ID );
			$sizes_map   = self::get_dynamic_available_sizes( (int) $att->ID );
			$att_source  = (string) get_post_meta( $att->ID, '_align_source', true );
			$att_tg_mid  = get_post_meta( $att->ID, '_align_tg_message_id', true );
			$items[]     = array(
				'attachment_id'       => (int) $att->ID,
				'title'               => (string) $att->post_title,
				'url'                 => self::to_root_relative_url( (string) wp_get_attachment_url( $att->ID ) ),
				'mime_type'           => (string) $att->post_mime_type,
				'alt_text'            => (string) get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
				'width'               => (int) ( $meta['width'] ?? 0 ),
				'height'              => (int) ( $meta['height'] ?? 0 ),
				'available_sizes'     => array_keys( $sizes_map ),
				'source'              => '' !== $att_source ? $att_source : 'wp',
				'telegram_message_id' => '' !== (string) $att_tg_mid ? (int) $att_tg_mid : null,
				'parent_id'           => (int) $att->post_parent,
				'date'                => (string) $att->post_date,
			);
		}

		return array(
			'success'     => true,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
			'media'       => $items,
		);
	}

	/** Summarize upload_media for approval card. */
	public static function summarize_upload_media( array $args ): array {
		$filename = ! empty( $args['filename'] ) ? sanitize_file_name( (string) $args['filename'] ) : 'telegram-photo.jpg';
		return array(
			'title'        => sprintf( __( 'Upload Media "%s"', 'align-agent' ), $filename ),
			'description'  => __( 'Upload image into WordPress Media Library and generate responsive WebP sizes.', 'align-agent' ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'filename' => $filename ),
		);
	}

	/** Execute upload_media tool. */
	public static function tool_upload_media( array $args = array() ) {
		$base64_data   = isset( $args['base64_data'] ) ? trim( (string) $args['base64_data'] ) : '';
		$raw_url       = isset( $args['url'] ) ? trim( (string) $args['url'] ) : '';
		$filename      = ! empty( $args['filename'] ) ? sanitize_file_name( (string) $args['filename'] ) : 'telegram-photo.jpg';
		$alt           = isset( $args['alt'] ) ? sanitize_text_field( (string) $args['alt'] ) : '';
		$tg_message_id = isset( $args['telegram_message_id'] ) && '' !== (string) $args['telegram_message_id'] ? absint( $args['telegram_message_id'] ) : null;
		$tg_chat_id    = isset( $args['telegram_chat_id'] ) ? sanitize_text_field( (string) $args['telegram_chat_id'] ) : '';
		$tg_unique_id  = isset( $args['telegram_file_unique_id'] ) ? sanitize_text_field( (string) $args['telegram_file_unique_id'] ) : '';

		// Idempotent deduplication by telegram_file_unique_id if already uploaded and attachment still exists.
		if ( '' !== $tg_unique_id ) {
			$existing = get_posts( array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'meta_key'       => '_align_tg_file_unique_id',
				'meta_value'     => $tg_unique_id,
			) );
			if ( ! empty( $existing ) && $existing[0] instanceof WP_Post ) {
				$existing_id  = (int) $existing[0]->ID;
				$existing_url = (string) wp_get_attachment_url( $existing_id );
				$existing_f   = get_attached_file( $existing_id );
				if ( '' !== $existing_url && ( ! $existing_f || file_exists( $existing_f ) ) ) {
					if ( null !== $tg_message_id ) {
						update_post_meta( $existing_id, '_align_tg_message_id', (string) $tg_message_id );
					}
					$sizes_map = self::get_dynamic_available_sizes( $existing_id );
					return array(
						'success'             => true,
						'deduplicated'        => true,
						'attachment_id'       => $existing_id,
						'url'                 => self::to_root_relative_url( $existing_url ),
						'available_sizes'     => $sizes_map,
						'telegram_message_id' => $tg_message_id,
						'message'             => sprintf( 'Reused existing attachment #%d for Telegram file %s.', $existing_id, $tg_unique_id ),
					);
				}
			}
		}

		if ( '' === $base64_data && '' === $raw_url ) {
			return array( 'success' => false, 'error' => 'missing_payload', 'message' => 'Either base64_data or url is required to upload media.' );
		}

		if ( ! function_exists( 'wp_tempnam' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp_file = '';
		if ( '' !== $base64_data ) {
			if ( preg_match( '/^data:image\/([a-zA-Z0-9+.-]+);base64,(.*)$/s', $base64_data, $m ) ) {
				$base64_data = $m[2];
			}
			$binary = base64_decode( $base64_data, true );
			if ( false === $binary || '' === $binary ) {
				return array( 'success' => false, 'error' => 'invalid_base64', 'message' => 'Invalid base64 image data.' );
			}
			if ( strlen( $binary ) > self::MAX_SIDELOAD_BYTES ) {
				return array( 'success' => false, 'error' => 'file_too_large', 'message' => 'Image exceeds 5 MB limit.' );
			}
			$tmp_file = wp_tempnam( $filename );
			if ( ! $tmp_file || false === file_put_contents( $tmp_file, $binary ) ) {
				return array( 'success' => false, 'error' => 'temp_write_failed', 'message' => 'Failed to write temporary image file.' );
			}
		} else {
			$tmp_file = download_url( $raw_url, 20 );
			if ( is_wp_error( $tmp_file ) ) {
				return array( 'success' => false, 'error' => 'download_failed', 'message' => $tmp_file->get_error_message() );
			}
			if ( '' === $filename || 'telegram-photo.jpg' === $filename ) {
				$url_base = wp_basename( (string) wp_parse_url( $raw_url, PHP_URL_PATH ) );
				if ( '' !== $url_base ) {
					$filename = sanitize_file_name( $url_base );
				}
			}
		}

		$size = file_exists( $tmp_file ) ? (int) filesize( $tmp_file ) : 0;
		if ( $size <= 0 || $size > self::MAX_SIDELOAD_BYTES ) {
			@unlink( $tmp_file );
			return array( 'success' => false, 'error' => 'file_too_large', 'message' => 'Image exceeds 5 MB limit or is empty.' );
		}

		// Ensure filename has a valid extension matching its actual image MIME type.
		$img_info = @getimagesize( $tmp_file );
		$mime     = is_array( $img_info ) && ! empty( $img_info['mime'] ) ? strtolower( (string) $img_info['mime'] ) : '';
		$mime_ext_map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);
		if ( empty( $mime_ext_map[ $mime ] ) ) {
			@unlink( $tmp_file );
			return array( 'success' => false, 'error' => 'invalid_mime_type', 'message' => 'File content is not a valid raster image (JPEG, PNG, WebP, GIF).' );
		}
		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			$filename = pathinfo( $filename, PATHINFO_FILENAME ) . '.' . $mime_ext_map[ $mime ];
		}

		$title  = '' !== $alt ? $alt : pathinfo( $filename, PATHINFO_FILENAME );
		$att_id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( $filename ),
				'type'     => $mime,
				'tmp_name' => $tmp_file,
				'error'    => 0,
				'size'     => $size,
			),
			0,
			$title
		);

		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp_file );
			return array( 'success' => false, 'error' => $att_id->get_error_code(), 'message' => $att_id->get_error_message() );
		}

		$att_id = (int) $att_id;
		if ( '' !== $alt ) {
			update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
		}

		$source_val = ( null !== $tg_message_id || '' !== $tg_chat_id || '' !== $tg_unique_id ) ? 'telegram' : 'mcp';
		update_post_meta( $att_id, '_align_source', $source_val );
		if ( null !== $tg_message_id ) {
			update_post_meta( $att_id, '_align_tg_message_id', (string) $tg_message_id );
		}
		if ( '' !== $tg_chat_id ) {
			update_post_meta( $att_id, '_align_tg_chat_id', $tg_chat_id );
		}
		if ( '' !== $tg_unique_id ) {
			update_post_meta( $att_id, '_align_tg_file_unique_id', $tg_unique_id );
		}

		$dyn_url   = self::to_root_relative_url( (string) wp_get_attachment_url( $att_id ) );
		$sizes_map = self::get_dynamic_available_sizes( $att_id );

		return array(
			'success'             => true,
			'attachment_id'       => $att_id,
			'url'                 => $dyn_url,
			'available_sizes'     => $sizes_map,
			'telegram_message_id' => $tg_message_id,
			'message'             => sprintf( 'Uploaded media as attachment #%d (%s).', $att_id, $dyn_url ),
		);
	}

	/**
	 * Extract image-related attributes (data-cursor-img, data-hover-img, data-bg, style background-image, etc.)
	 * from a single opening HTML tag or attribute fragment.
	 *
	 * @param string $tag_html Opening tag HTML string.
	 * @return array<int, array{attr: string, value: string, raw_value: string}>
	 */
	private static function extract_image_attrs_from_tag( string $tag_html ): array {
		$found = array();
		if ( '' === $tag_html ) {
			return $found;
		}

		if ( preg_match_all( '/\b(data-cursor-img|data-hover-img|data-img|data-image|data-bg|data-background|data-thumb|data-poster|poster)\s*=\s*([\'"])(.*?)\2/i', $tag_html, $am, PREG_SET_ORDER ) ) {
			foreach ( $am as $m ) {
				$val = trim( $m[3] );
				if ( '' !== $val ) {
					$found[] = array(
						'attr'      => strtolower( $m[1] ),
						'value'     => self::to_root_relative_url( $val ),
						'raw_value' => $val,
					);
				}
			}
		}

		if ( preg_match( '/\bstyle\s*=\s*([\'"])(.*?)\1/is', $tag_html, $sm ) ) {
			if ( preg_match_all( '/background(?:-image)?\s*:[^;]*?url\(\s*([\'"]?)(.*?)\1\s*\)/i', $sm[2], $bm, PREG_SET_ORDER ) ) {
				foreach ( $bm as $b ) {
					$val = trim( $b[2] );
					if ( '' !== $val && ! str_starts_with( $val, 'data:' ) ) {
						$found[] = array(
							'attr'      => 'style:background-image',
							'value'     => self::to_root_relative_url( $val ),
							'raw_value' => $val,
						);
					}
				}
			}
		}

		return $found;
	}

	/**
	 * Locate the enclosing item container range and opening tag around a given byte offset in HTML.
	 * Stops at sibling closing tags (</li>, </a>, </article>, </figure>, </section>, </div>).
	 *
	 * @param string $html   Full HTML content.
	 * @param int    $offset Byte offset of the <picture> or <img> tag.
	 * @param int    $length Byte length of the <picture> or <img> tag.
	 * @return array{start: int, end: int, related_attributes: array<int, array{attr: string, value: string}>}
	 */
	private static function find_enclosing_container_info( string $html, int $offset, int $length ): array {
		$win_start = max( 0, $offset - 800 );
		$before    = substr( $html, $win_start, $offset - $win_start );

		// Do not cross an earlier sibling's closing container tag.
		if ( preg_match_all( '/<\/(li|a|article|figure|section|tr|td)\s*>/i', $before, $cm, PREG_OFFSET_CAPTURE ) ) {
			$last_close = end( $cm[0] );
			$close_end  = (int) $last_close[1] + strlen( (string) $last_close[0] );
			$win_start += $close_end;
			$before     = substr( $html, $win_start, $offset - $win_start );
		}

		$after_pos = $offset + $length;
		$after     = substr( $html, $after_pos, 800 );
		$win_end   = $after_pos + strlen( $after );
		if ( preg_match( '/<\/(li|a|article|figure|section|div)\s*>/i', $after, $acm, PREG_OFFSET_CAPTURE ) ) {
			$win_end = $after_pos + (int) $acm[0][1] + strlen( (string) $acm[0][0] );
		}

		$container_region = substr( $html, $win_start, $win_end - $win_start );
		$related_attrs    = array();
		if ( preg_match_all( '/<[a-zA-Z0-9:-]+\b[^>]*>/s', $container_region, $tm ) ) {
			foreach ( $tm[0] as $open_tag ) {
				if ( preg_match( '/^<(?:img|source|picture)\b/i', $open_tag ) ) {
					continue;
				}
				foreach ( self::extract_image_attrs_from_tag( $open_tag ) as $attr_item ) {
					$related_attrs[] = array(
						'attr'  => $attr_item['attr'],
						'value' => $attr_item['value'],
					);
				}
			}
		}

		return array(
			'start'              => $win_start,
			'end'                => $win_end,
			'related_attributes' => $related_attrs,
		);
	}

	/**
	 * Extract human-readable context snippet (including data-src text from split-text headings) around an offset.
	 *
	 * @param string $html   Full HTML content.
	 * @param int    $offset Start offset.
	 * @param int    $length Tag length.
	 * @return string
	 */
	private static function build_context_snippet( string $html, int $offset, int $length ): string {
		$before_html = substr( $html, max( 0, $offset - 450 ), min( 450, $offset ) );
		$after_html  = substr( $html, $offset + $length, 350 );

		// Preserve data-src="..." labels when inner text is split into spans/chars.
		$enrich = static function( string $frag ): string {
			return (string) preg_replace_callback(
				'/\bdata-src\s*=\s*([\'"])(.*?)\1/i',
				static fn( $m ) => ' ' . $m[2] . ' ',
				$frag
			);
		};

		$before_txt  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $enrich( $before_html ) ) ) );
		$after_txt   = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $enrich( $after_html ) ) ) );
		$before_snip = mb_substr( $before_txt, -55, null, 'UTF-8' );
		$after_snip  = mb_substr( $after_txt, 0, 45, 'UTF-8' );
		return trim( $before_snip . ' [IMAGE] ' . $after_snip );
	}

	/**
	 * Resolve attachment ID from a Dynamic Root-Relative or absolute image URL.
	 *
	 * @param string $src Image URL.
	 * @return int
	 */
	private static function resolve_attachment_id_from_src( string $src ): int {
		if ( '' === $src || ! function_exists( 'attachment_url_to_postid' ) || false === strpos( $src, '/wp-content/uploads/' ) ) {
			return 0;
		}
		$clean_src = explode( '?', $src )[0];
		$att_id    = (int) attachment_url_to_postid( home_url( $clean_src ) );
		if ( $att_id <= 0 ) {
			$stripped = preg_replace( '/(-\d+x\d+)(\.[a-zA-Z0-9]+)$/', '$2', $clean_src );
			if ( $stripped !== $clean_src ) {
				$att_id = (int) attachment_url_to_postid( home_url( (string) $stripped ) );
			}
		}
		return max( 0, $att_id );
	}

	/**
	 * Extract ordered image slots (<picture>, standalone <img>, and standalone attribute/background images) from HTML content.
	 *
	 * @param string $html Raw page/post HTML.
	 * @return array<int, array<string, mixed>>
	 */
	private static function extract_html_image_slots( string $html ): array {
		$slots = array();
		if ( '' === trim( $html ) ) {
			return $slots;
		}

		$raw_items        = array();
		$covered_ranges   = array();
		$pic_img_pattern  = '/<!--[\s\S]*?-->|(<picture\b[^>]*>[\s\S]*?<\/picture>)|(<img\b[^>]*>)/is';

		if ( preg_match_all( $pic_img_pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $i => $full_match ) {
				$pic_str = ! empty( $matches[1][ $i ][0] ) ? (string) $matches[1][ $i ][0] : '';
				$img_str = ! empty( $matches[2][ $i ][0] ) ? (string) $matches[2][ $i ][0] : '';
				if ( '' === $pic_str && '' === $img_str ) {
					continue; // HTML comment
				}

				$raw_tag    = '' !== $pic_str ? $pic_str : $img_str;
				$offset     = (int) $full_match[1];
				$length     = strlen( $raw_tag );
				$is_picture = ( '' !== $pic_str );
				$inner_img  = $img_str;
				if ( $is_picture && preg_match( '/<img\b[^>]*>/i', $pic_str, $im ) ) {
					$inner_img = $im[0];
				}

				$src = '';
				if ( preg_match( '/\bsrc\s*=\s*([\'"])(.*?)\1/i', $inner_img, $sm ) ) {
					$src = self::to_root_relative_url( trim( $sm[2] ) );
				}

				$alt = '';
				if ( preg_match( '/\balt\s*=\s*([\'"])(.*?)\1/i', $inner_img, $am ) ) {
					$alt = html_entity_decode( $am[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				}

				$att_id = 0;
				if ( preg_match( '/\b(?:data-id|data-align-image-id)\s*=\s*[\'"]?(\d+)[\'"]?/i', $raw_tag, $idm ) ) {
					$att_id = absint( $idm[1] );
				} elseif ( preg_match( '/\bwp-image-(\d+)\b/i', $raw_tag, $clm ) ) {
					$att_id = absint( $clm[1] );
				} elseif ( '' !== $src ) {
					$att_id = self::resolve_attachment_id_from_src( $src );
				}

				$align_sizes = 'auto';
				if ( preg_match( '/\b(?:data-align-sizes|data-selected-sizes)\s*=\s*([\'"])(.*?)\1/i', $raw_tag, $szm ) ) {
					$align_sizes = trim( $szm[2] );
				}

				$container_info   = self::find_enclosing_container_info( $html, $offset, $length );
				$covered_ranges[] = array( $container_info['start'], $container_info['end'] );

				$raw_items[] = array(
					'type'               => $is_picture ? 'picture' : 'img',
					'attachment_id'      => $att_id,
					'src'                => $src,
					'alt'                => $alt,
					'is_picture'         => $is_picture,
					'data_align_sizes'   => $align_sizes,
					'related_attributes' => $container_info['related_attributes'],
					'context_snippet'    => self::build_context_snippet( $html, $offset, $length ),
					'_raw_tag'           => $raw_tag,
					'_inner_img'         => $inner_img,
					'_offset'            => $offset,
					'_length'            => $length,
					'_container_start'   => $container_info['start'],
					'_container_end'     => $container_info['end'],
				);
			}
		}

		// Scan for standalone elements with image attributes or inline background-image not already covered by a <picture>/<img> container.
		if ( preg_match_all( '/<[a-zA-Z0-9:-]+\b[^>]*>/s', $html, $tag_matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $tag_matches[0] as $tm ) {
				$open_tag   = (string) $tm[0];
				$tag_offset = (int) $tm[1];
				$tag_len    = strlen( $open_tag );
				if ( preg_match( '/^<(?:img|source|picture)\b/i', $open_tag ) ) {
					continue;
				}
				$img_attrs = self::extract_image_attrs_from_tag( $open_tag );
				if ( empty( $img_attrs ) ) {
					continue;
				}
				$in_covered = false;
				foreach ( $covered_ranges as $rng ) {
					if ( $tag_offset >= $rng[0] && $tag_offset <= $rng[1] ) {
						$in_covered = true;
						break;
					}
				}
				if ( $in_covered ) {
					continue;
				}

				$primary = $img_attrs[0];
				$type    = ( 'style:background-image' === $primary['attr'] ) ? 'background' : 'attribute';
				$raw_items[] = array(
					'type'               => $type,
					'attachment_id'      => self::resolve_attachment_id_from_src( $primary['value'] ),
					'src'                => $primary['value'],
					'alt'                => '',
					'is_picture'         => false,
					'data_align_sizes'   => 'auto',
					'related_attributes' => array_map(
						static fn( $a ) => array( 'attr' => $a['attr'], 'value' => $a['value'] ),
						$img_attrs
					),
					'context_snippet'    => self::build_context_snippet( $html, $tag_offset, $tag_len ),
					'_raw_tag'           => $open_tag,
					'_inner_img'         => '',
					'_offset'            => $tag_offset,
					'_length'            => $tag_len,
					'_container_start'   => $tag_offset,
					'_container_end'     => $tag_offset + $tag_len,
				);
			}
		}

		usort( $raw_items, static fn( $a, $b ) => $a['_offset'] <=> $b['_offset'] );
		foreach ( $raw_items as $idx => $item ) {
			$item['index'] = $idx;
			$slots[]       = $item;
		}

		return $slots;
	}

	/** Execute list_page_images tool. */
	public static function tool_list_page_images( array $args = array() ): array {
		$post_id = absint( $args['post_id'] ?? $args['id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( 'Post #%d was not found.', $post_id ) );
		}

		$parts = class_exists( 'Align_Gateway_Pages' )
			? Align_Gateway_Pages::extract_decoupled_parts( $post )
			: array( 'html' => (string) $post->post_content );
		$html  = (string) ( $parts['html'] ?? '' );
		$slots = self::extract_html_image_slots( $html );

		$images = array();
		foreach ( $slots as $s ) {
			$images[] = array(
				'index'              => $s['index'],
				'type'               => $s['type'],
				'attachment_id'      => $s['attachment_id'],
				'src'                => $s['src'],
				'alt'                => $s['alt'],
				'is_picture'         => $s['is_picture'],
				'data_align_sizes'   => $s['data_align_sizes'],
				'related_attributes' => $s['related_attributes'],
				'context_snippet'    => $s['context_snippet'],
			);
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'title'   => (string) $post->post_title,
			'count'   => count( $images ),
			'images'  => $images,
		);
	}

	/** Summarize replace_page_image for approval card. */
	public static function summarize_replace_page_image( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$att_id  = absint( $args['new_attachment_id'] ?? 0 );
		$idx     = isset( $args['target_index'] ) ? (int) $args['target_index'] : -1;
		return array(
			'title'        => sprintf( __( 'Replace Image on Post #%1$d with Media #%2$d', 'align-agent' ), $post_id, $att_id ),
			'description'  => sprintf( __( 'Replace image (index: %1$d) on post #%2$d with attachment #%3$d.', 'align-agent' ), $idx, $post_id, $att_id ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'post_id' => $post_id, 'new_attachment_id' => $att_id, 'target_index' => $idx ),
		);
	}

	/**
	 * Update all image-related attributes (data-cursor-img, data-bg, style background-image, etc.)
	 * inside an HTML fragment to a new Dynamic Root-Relative URL and record synced attribute changes.
	 *
	 * @param string               $fragment          HTML fragment to update.
	 * @param string               $new_url           New Dynamic Root-Relative URL.
	 * @param array<string,string> $synced_attributes Output map of synced attribute => new URL.
	 * @param string               $only_match_base   Optional canonical filename to restrict updates to.
	 * @return string Updated HTML fragment.
	 */
	private static function update_image_attrs_in_fragment( string $fragment, string $new_url, array &$synced_attributes, string $only_match_base = '' ): string {
		if ( '' === $fragment || '' === $new_url ) {
			return $fragment;
		}

		$matches_target = static function( string $candidate_url ) use ( $only_match_base ): bool {
			if ( '' === $only_match_base ) {
				return true;
			}
			$clean_path = (string) wp_parse_url( $candidate_url, PHP_URL_PATH );
			$base       = wp_basename( $clean_path );
			$canon_base = (string) preg_replace( '/(-\d+x\d+)(\.[a-zA-Z0-9]+)$/', '$2', $base );
			return ( '' !== $canon_base && 0 === strcasecmp( $canon_base, $only_match_base ) );
		};

		$updated = (string) preg_replace_callback(
			'/<([a-zA-Z0-9:-]+)\b[^>]*>/s',
			static function( array $tag_m ) use ( $new_url, &$synced_attributes, $matches_target ): string {
				$tag_str  = $tag_m[0];
				$tag_name = strtolower( $tag_m[1] );
				if ( in_array( $tag_name, array( 'img', 'source', 'picture' ), true ) ) {
					return $tag_str;
				}

				$tag_str = (string) preg_replace_callback(
					'/\b(data-cursor-img|data-hover-img|data-img|data-image|data-bg|data-background|data-thumb|data-poster|poster)(\s*=\s*)([\'"])(.*?)\3/i',
					static function( array $am ) use ( $new_url, &$synced_attributes, $matches_target ): string {
						$attr_name = strtolower( $am[1] );
						$old_val   = trim( $am[4] );
						if ( '' === $old_val || ! $matches_target( $old_val ) ) {
							return $am[0];
						}
						$synced_attributes[ $attr_name ] = $new_url;
						return $am[1] . $am[2] . $am[3] . esc_attr( $new_url ) . $am[3];
					},
					$tag_str
				);

				$tag_str = (string) preg_replace_callback(
					'/\b(style\s*=\s*)([\'"])(.*?)\2/is',
					static function( array $sm ) use ( $new_url, &$synced_attributes, $matches_target ): string {
						$style_body = (string) preg_replace_callback(
							'/(background(?:-image)?\s*:[^;]*?url\(\s*)([\'"]?)(.*?)\2(\s*\))/i',
							static function( array $bm ) use ( $new_url, &$synced_attributes, $matches_target ): string {
								$old_val = trim( $bm[3] );
								if ( '' === $old_val || str_starts_with( $old_val, 'data:' ) || ! $matches_target( $old_val ) ) {
									return $bm[0];
								}
								$synced_attributes['style:background-image'] = $new_url;
								return $bm[1] . $bm[2] . esc_attr( $new_url ) . $bm[2] . $bm[4];
							},
							$sm[3]
						);
						return $sm[1] . $sm[2] . $style_body . $sm[2];
					},
					$tag_str
				);

				return $tag_str;
			},
			$fragment
		);

		return $updated;
	}

	/** Execute replace_page_image tool. */
	public static function tool_replace_page_image( array $args = array() ): array {
		$post_id           = absint( $args['post_id'] ?? 0 );
		$new_attachment_id = absint( $args['new_attachment_id'] ?? 0 );
		$has_index         = isset( $args['target_index'] ) && '' !== (string) $args['target_index'];
		$target_index      = $has_index ? (int) $args['target_index'] : -1;
		$target_src        = isset( $args['target_src'] ) ? trim( (string) $args['target_src'] ) : '';
		$mobile_size       = ! empty( $args['mobile_size'] ) ? strtolower( trim( (string) $args['mobile_size'] ) ) : 'auto';
		$tablet_size       = ! empty( $args['tablet_size'] ) ? strtolower( trim( (string) $args['tablet_size'] ) ) : 'auto';
		$desktop_size      = ! empty( $args['desktop_size'] ) ? strtolower( trim( (string) $args['desktop_size'] ) ) : 'auto';
		$skip_picture      = ! empty( $args['skip_picture'] );

		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return array( 'success' => false, 'error' => 'post_not_found', 'message' => sprintf( 'Post #%d was not found.', $post_id ) );
		}

		$att = $new_attachment_id > 0 ? get_post( $new_attachment_id ) : null;
		if ( ! $att || 'attachment' !== $att->post_type || ! wp_attachment_is_image( $new_attachment_id ) ) {
			return array( 'success' => false, 'error' => 'invalid_attachment', 'message' => sprintf( 'Attachment #%d is not a valid image in Media Library.', $new_attachment_id ) );
		}

		$parts = class_exists( 'Align_Gateway_Pages' )
			? Align_Gateway_Pages::extract_decoupled_parts( $post )
			: array( 'html' => (string) $post->post_content, 'css' => '', 'js' => '' );
		$html  = (string) ( $parts['html'] ?? '' );
		$slots = self::extract_html_image_slots( $html );

		if ( empty( $slots ) ) {
			return array( 'success' => false, 'error' => 'no_images_on_page', 'message' => sprintf( 'Post #%d does not contain any image elements or image attributes to replace.', $post_id ) );
		}

		$matched_slot = null;
		if ( $has_index && isset( $slots[ $target_index ] ) ) {
			$matched_slot = $slots[ $target_index ];
		} elseif ( '' !== $target_src ) {
			$norm_target = self::to_root_relative_url( $target_src );
			foreach ( $slots as $s ) {
				if ( $s['src'] === $norm_target || $s['src'] === $target_src || ( '' !== $norm_target && false !== strpos( $s['src'], $norm_target ) ) ) {
					$matched_slot = $s;
					break;
				}
				if ( ! empty( $s['related_attributes'] ) && is_array( $s['related_attributes'] ) ) {
					foreach ( $s['related_attributes'] as $rel ) {
						$rv = (string) ( $rel['value'] ?? '' );
						if ( $rv === $norm_target || $rv === $target_src || ( '' !== $norm_target && false !== strpos( $rv, $norm_target ) ) ) {
							$matched_slot = $s;
							break 2;
						}
					}
				}
			}
		}

		if ( ! $matched_slot ) {
			return array(
				'success'          => false,
				'error'            => 'target_image_not_found',
				'message'          => 'Could not locate the target image on the page. Please check target_index or target_src against available_images.',
				'available_images' => array_map( function( $s ) {
					return array(
						'index'              => $s['index'],
						'type'               => $s['type'],
						'attachment_id'      => $s['attachment_id'],
						'src'                => $s['src'],
						'alt'                => $s['alt'],
						'related_attributes' => $s['related_attributes'],
						'context_snippet'    => $s['context_snippet'],
					);
				}, $slots ),
			);
		}

		$sizes_map = self::get_dynamic_available_sizes( $new_attachment_id );
		$new_url   = self::to_root_relative_url( (string) wp_get_attachment_url( $new_attachment_id ) );
		$attr_url  = $new_url;
		if ( 'auto' !== $desktop_size && isset( $sizes_map[ $desktop_size ]['url'] ) ) {
			$attr_url = (string) $sizes_map[ $desktop_size ]['url'];
		} elseif ( 'auto' !== $mobile_size && isset( $sizes_map[ $mobile_size ]['url'] ) ) {
			$attr_url = (string) $sizes_map[ $mobile_size ]['url'];
		}

		$att_alt = (string) get_post_meta( $new_attachment_id, '_wp_attachment_image_alt', true );
		$new_alt = isset( $args['alt'] ) && '' !== trim( (string) $args['alt'] )
			? sanitize_text_field( (string) $args['alt'] )
			: ( '' !== $matched_slot['alt'] ? $matched_slot['alt'] : ( '' !== $att_alt ? $att_alt : (string) $att->post_title ) );

		$has_custom_sizes  = ( 'auto' !== $mobile_size || 'auto' !== $tablet_size || 'auto' !== $desktop_size );
		$align_sizes_str   = sprintf( 'mobile:%s,tablet:%s,desktop:%s', $mobile_size, $tablet_size, $desktop_size );
		$synced_attributes = array();
		$old_src           = (string) $matched_slot['src'];
		$old_canon_base    = '';
		if ( '' !== $old_src ) {
			$old_path       = (string) wp_parse_url( $old_src, PHP_URL_PATH );
			$old_canon_base = (string) preg_replace( '/(-\d+x\d+)(\.[a-zA-Z0-9]+)$/', '$2', wp_basename( $old_path ) );
		}

		// Mechanism 3: Direct Attribute / Background Replacement when slot type is 'attribute' or 'background'.
		if ( in_array( $matched_slot['type'], array( 'attribute', 'background' ), true ) ) {
			$replacement_markup = self::update_image_attrs_in_fragment( $matched_slot['_raw_tag'], $attr_url, $synced_attributes );
			$new_html           = substr_replace( $html, $replacement_markup, $matched_slot['_offset'], $matched_slot['_length'] );
		} else {
			$base_img = '' !== $matched_slot['_inner_img'] ? $matched_slot['_inner_img'] : '<img src="" alt="">';
			// Strip old srcset/sizes/data-srcset so resolver builds fresh ones.
			$base_img = preg_replace( '/\s+\b(?:srcset|data-srcset|sizes|width|height)\s*=\s*(?:([\'"])(.*?)\1|[^\s>]+)/i', '', $base_img );

			// Update src.
			if ( preg_match( '/\bsrc\s*=\s*([\'"])(.*?)\1/i', $base_img ) ) {
				$base_img = preg_replace( '/\bsrc\s*=\s*([\'"])(.*?)\1/i', 'src="' . esc_attr( $new_url ) . '"', $base_img, 1 );
			} else {
				$base_img = preg_replace( '/(\s*\/?>)$/', ' src="' . esc_attr( $new_url ) . '"$1', $base_img, 1 );
			}

			// Update alt.
			if ( preg_match( '/\balt\s*=\s*([\'"])(.*?)\1/i', $base_img ) ) {
				$base_img = preg_replace( '/\balt\s*=\s*([\'"])(.*?)\1/i', 'alt="' . esc_attr( $new_alt ) . '"', $base_img, 1 );
			} else {
				$base_img = preg_replace( '/(\s*\/?>)$/', ' alt="' . esc_attr( $new_alt ) . '"$1', $base_img, 1 );
			}

			// Update data-id.
			if ( preg_match( '/\bdata-id\s*=\s*([\'"]?)\d+\1/i', $base_img ) ) {
				$base_img = preg_replace( '/\bdata-id\s*=\s*([\'"]?)\d+\1/i', 'data-id="' . $new_attachment_id . '"', $base_img, 1 );
			} else {
				$base_img = preg_replace( '/(\s*\/?>)$/', ' data-id="' . $new_attachment_id . '"$1', $base_img, 1 );
			}

			// Update wp-image-{id} class on img.
			if ( preg_match( '/\bclass\s*=\s*([\'"])(.*?)\1/i', $base_img, $cm ) ) {
				$cls = trim( preg_replace( '/\bwp-image-\d+\b/', '', $cm[2] ) );
				$cls = trim( preg_replace( '/\s+/', ' ', $cls . ' wp-image-' . $new_attachment_id ) );
				$base_img = preg_replace( '/\bclass\s*=\s*([\'"])(.*?)\1/i', 'class="' . esc_attr( $cls ) . '"', $base_img, 1 );
			} else {
				$base_img = preg_replace( '/(\s*\/?>)$/', ' class="wp-image-' . $new_attachment_id . '"$1', $base_img, 1 );
			}

			if ( $skip_picture ) {
				if ( ! preg_match( '/\bdata-skip-picture\b/i', $base_img ) ) {
					$base_img = preg_replace( '/(\s*\/?>)$/', ' data-skip-picture="true"$1', $base_img, 1 );
				}
				$replacement_markup = class_exists( 'Align_Builder_Image_Resolver' )
					? Align_Builder_Image_Resolver::resolve_img_tag( $base_img )
					: $base_img;
			} else {
				$base_img  = preg_replace( '/\s+\bdata-skip-picture\s*=\s*(?:([\'"])(.*?)\1|[^\s>]+)/i', '', $base_img );
				$pic_attrs = 'data-id="' . $new_attachment_id . '" class="wp-image-' . $new_attachment_id . '"';
				if ( $has_custom_sizes ) {
					$pic_attrs .= ' data-align-sizes="' . esc_attr( $align_sizes_str ) . '"';
				} elseif ( 'auto' !== $matched_slot['data_align_sizes'] && '' !== $matched_slot['data_align_sizes'] ) {
					$pic_attrs .= ' data-align-sizes="' . esc_attr( $matched_slot['data_align_sizes'] ) . '"';
				}
				$candidate_pic      = '<picture ' . $pic_attrs . '>' . $base_img . '</picture>';
				$replacement_markup = class_exists( 'Align_Builder_Image_Resolver' )
					? Align_Builder_Image_Resolver::resolve_picture_tag( $candidate_pic )
					: $candidate_pic;
			}

			$replacement_markup = self::sanitize_internal_urls_to_dynamic( (string) $replacement_markup );

			// Mechanism 2 (Enclosing Item Container Attribute Sync): Sync all image attributes (e.g. data-cursor-img) inside the enclosing container.
			$c_start         = (int) ( $matched_slot['_container_start'] ?? $matched_slot['_offset'] );
			$c_end           = (int) ( $matched_slot['_container_end'] ?? ( $matched_slot['_offset'] + $matched_slot['_length'] ) );
			$before_in_cont  = substr( $html, $c_start, $matched_slot['_offset'] - $c_start );
			$after_in_cont   = substr( $html, $matched_slot['_offset'] + $matched_slot['_length'], max( 0, $c_end - ( $matched_slot['_offset'] + $matched_slot['_length'] ) ) );
			$before_in_cont  = self::update_image_attrs_in_fragment( $before_in_cont, $attr_url, $synced_attributes );
			$after_in_cont   = self::update_image_attrs_in_fragment( $after_in_cont, $attr_url, $synced_attributes );
			$updated_cont    = $before_in_cont . $replacement_markup . $after_in_cont;
			$new_html        = substr_replace( $html, $updated_cont, $c_start, $c_end - $c_start );
		}

		// Mechanism 1 (Same-Filename / Old-Src Sync): Sync any remaining image attributes inside the same container region referencing the same old canonical filename.
		if ( '' !== $old_canon_base && in_array( $matched_slot['type'], array( 'attribute', 'background' ), true ) ) {
			$new_html = self::update_image_attrs_in_fragment( $new_html, $attr_url, $synced_attributes, $old_canon_base );
		}

		$new_html = self::sanitize_internal_urls_to_dynamic( $new_html );
		$new_css  = self::sanitize_internal_urls_to_dynamic( (string) ( $parts['css'] ?? '' ) );

		$save_req = new WP_REST_Request( 'POST', '/align-builder/v1/save-page' );
		$save_req->set_param( 'post_id', $post_id );
		$save_req->set_param( 'html', $new_html );
		$save_req->set_param( 'css', $new_css );
		$save_req->set_param( 'js', (string) ( $parts['js'] ?? '' ) );
		$save_res = Align_Builder_Crud_Handler::save_page( $save_req );
		if ( is_wp_error( $save_res ) ) {
			return array( 'success' => false, 'error' => $save_res->get_error_code(), 'message' => $save_res->get_error_message() );
		}

		return array(
			'success'            => true,
			'post_id'            => $post_id,
			'replaced_index'     => $matched_slot['index'],
			'old_src'            => $old_src,
			'new_attachment_id'  => $new_attachment_id,
			'new_src'            => $new_url,
			'alt'                => $new_alt,
			'data_align_sizes'   => $has_custom_sizes ? $align_sizes_str : $matched_slot['data_align_sizes'],
			'synced_attributes'  => $synced_attributes,
			'rendered_markup'    => $replacement_markup,
			'url'                => self::to_root_relative_url( (string) get_permalink( $post_id ) ),
			'message'            => sprintf( 'Replaced image at index %d on post #%d with attachment #%d (%s).', $matched_slot['index'], $post_id, $new_attachment_id, $new_url ),
		);
	}

	/** Summarize set_featured_image for approval card. */
	public static function summarize_set_featured_image( array $args ): array {
		$post_id = absint( $args['post_id'] ?? 0 );
		$att_id  = absint( $args['attachment_id'] ?? 0 );
		$img_url = $att_id > 0 ? (string) wp_get_attachment_url( $att_id ) : '';
		return array(
			'title'        => sprintf( __( 'Set Featured Image for Post #%d', 'align-agent' ), $post_id ),
			'description'  => sprintf( __( 'Assign attachment #%1$d (%2$s) as Featured Image.', 'align-agent' ), $att_id, $img_url ?: 'media' ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'post_id' => $post_id, 'attachment_id' => $att_id ),
		);
	}

	/** Execute set_featured_image tool. */
	public static function tool_set_featured_image( array $args = array() ) {
		$post_id = absint( $args['post_id'] ?? 0 );
		$att_id  = absint( $args['attachment_id'] ?? 0 );

		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post #%d was not found.', $post_id ) );
		}

		$att = $att_id > 0 ? get_post( $att_id ) : null;
		if ( ! $att || 'attachment' !== $att->post_type || ! wp_attachment_is_image( $att_id ) ) {
			return new WP_Error( 'invalid_attachment', sprintf( 'Attachment #%d is not a valid image.', $att_id ) );
		}

		set_post_thumbnail( $post_id, $att_id );
		clean_post_cache( $post_id );

		$img_url  = self::to_root_relative_url( (string) wp_get_attachment_image_url( $att_id, 'full' ) );
		$page_url = self::to_root_relative_url( (string) get_permalink( $post_id ) );

		return array(
			'success'       => true,
			'post_id'       => $post_id,
			'attachment_id' => $att_id,
			'image_url'     => $img_url,
			'url'           => $page_url,
			'message'       => sprintf( 'Featured Image set to attachment #%d.', $att_id ),
			'ui_card'       => array(
				'title'   => sprintf( 'Featured Image Updated (#%d)', $post_id ),
				'message' => sprintf( 'Attachment #%d assigned to "%s".', $att_id, $post->post_title ),
				'url'     => $page_url,
			),
		);
	}

	/** Summarize attach_media for approval card. */
	public static function summarize_attach_media( array $args ): array {
		$att_id  = absint( $args['attachment_id'] ?? 0 );
		$post_id = absint( $args['post_id'] ?? 0 );
		return array(
			'title'        => sprintf( __( 'Attach Media #%1$d to Post #%2$d', 'align-agent' ), $att_id, $post_id ),
			'description'  => sprintf( __( 'Link Media Library attachment #%1$d to post #%2$d.', 'align-agent' ), $att_id, $post_id ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'attachment_id' => $att_id, 'post_id' => $post_id ),
		);
	}

	/** Execute attach_media tool. */
	public static function tool_attach_media( array $args = array() ) {
		$att_id  = absint( $args['attachment_id'] ?? 0 );
		$post_id = absint( $args['post_id'] ?? 0 );

		$att  = $att_id > 0 ? get_post( $att_id ) : null;
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $att || 'attachment' !== $att->post_type ) {
			return new WP_Error( 'invalid_attachment', sprintf( 'Attachment #%d not found.', $att_id ) );
		}
		if ( ! $post ) {
			return new WP_Error( 'post_not_found', sprintf( 'Post #%d not found.', $post_id ) );
		}

		wp_update_post( array( 'ID' => $att_id, 'post_parent' => $post_id ) );
		if ( ! empty( $args['alt_text'] ) ) {
			update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt_text'] ) );
		}
		if ( ! empty( $args['title'] ) ) {
			wp_update_post( array( 'ID' => $att_id, 'post_title' => sanitize_text_field( (string) $args['title'] ) ) );
		}

		return array(
			'success'       => true,
			'attachment_id' => $att_id,
			'post_id'       => $post_id,
			'image_url'     => self::to_root_relative_url( (string) wp_get_attachment_url( $att_id ) ),
			'message'       => sprintf( 'Attached media #%d to post #%d.', $att_id, $post_id ),
		);
	}

	/** Summarize sideload_media for approval card. */
	public static function summarize_sideload_media( array $args ): array {
		$url = isset( $args['url'] ) ? esc_url_raw( (string) $args['url'] ) : '';
		return array(
			'title'        => __( 'Sideload External Image to Media Library', 'align-agent' ),
			'description'  => sprintf( __( 'Download %s (HTTPS, max 5MB, raster only) into Media Library.', 'align-agent' ), $url ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'url' => $url ),
		);
	}

	/** Check if URL was present in conversation messages. */
	public static function is_url_in_user_conversation( string $url ): bool {
		if ( ! class_exists( 'Align_Agent_Conversation' ) ) {
			return false;
		}
		$state    = Align_Agent_Conversation::get_state( get_current_user_id() );
		$messages = $state['messages'] ?? array();
		foreach ( $messages as $msg ) {
			if ( isset( $msg['role'] ) && 'user' === $msg['role'] && ! empty( $msg['content'] ) ) {
				if ( false !== strpos( (string) $msg['content'], $url ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Execute sideload_media tool with M2 security validation. */
	public static function tool_sideload_media( array $args = array() ) {
		$raw_url = isset( $args['url'] ) ? trim( (string) $args['url'] ) : '';
		$post_id = absint( $args['post_id'] ?? 0 );

		if ( '' === $raw_url || 'https' !== strtolower( (string) wp_parse_url( $raw_url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $raw_url ) ) {
			return new WP_Error( 'invalid_url', 'A valid HTTPS image URL is required.' );
		}

		if ( ! self::is_url_in_user_conversation( $raw_url ) ) {
			return new WP_Error( 'url_not_from_user', 'Security: sideload_media only accepts URLs explicitly typed in chat.' );
		}

		$basename = wp_basename( (string) wp_parse_url( $raw_url, PHP_URL_PATH ) );
		$ext      = strtolower( (string) pathinfo( $basename, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext || ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			return new WP_Error( 'disallowed_extension', 'Only .jpg, .jpeg, .png, .webp, and .gif images are allowed.' );
		}

		if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp_file = download_url( $raw_url, 20 );
		if ( is_wp_error( $tmp_file ) ) {
			return new WP_Error( 'download_failed', $tmp_file->get_error_message() );
		}

		$size = file_exists( $tmp_file ) ? (int) filesize( $tmp_file ) : 0;
		if ( $size <= 0 || $size > self::MAX_SIDELOAD_BYTES ) {
			@unlink( $tmp_file );
			return new WP_Error( 'file_too_large', 'Image exceeds 5 MB limit or is empty.' );
		}

		$check     = wp_check_filetype_and_ext( $tmp_file, $basename, self::ALLOWED_MIMES );
		$real_type = $check['type'] ?? false;
		if ( empty( $check['ext'] ) || empty( $real_type ) || ! in_array( $real_type, array_values( self::ALLOWED_MIMES ), true ) ) {
			@unlink( $tmp_file );
			return new WP_Error( 'invalid_mime_type', 'File content does not match a valid raster image MIME type.' );
		}

		$title  = isset( $args['title'] ) && '' !== trim( (string) $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : null;
		$att_id = media_handle_sideload( array( 'name' => sanitize_file_name( $basename ), 'tmp_name' => $tmp_file ), $post_id, $title );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp_file );
			return $att_id;
		}

		if ( ! empty( $args['alt_text'] ) ) {
			update_post_meta( (int) $att_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt_text'] ) );
		}

		$img_url = self::to_root_relative_url( (string) wp_get_attachment_url( (int) $att_id ) );
		return array(
			'success'       => true,
			'attachment_id' => (int) $att_id,
			'post_id'       => $post_id,
			'url'           => $img_url,
			'message'       => sprintf( 'Imported image as attachment #%d.', $att_id ),
		);
	}

	/** Summarize update_media_meta for approval card. */
	public static function summarize_update_media_meta( array $args ): array {
		$att_id = absint( $args['attachment_id'] ?? 0 );
		$parts  = array();
		foreach ( array( 'alt', 'title', 'caption', 'description' ) as $f ) {
			if ( isset( $args[ $f ] ) ) {
				$parts[] = ucfirst( $f ) . ': "' . sanitize_text_field( (string) $args[ $f ] ) . '"';
			}
		}
		return array(
			'title'        => sprintf( __( 'Update Media Metadata #%d', 'align-agent' ), $att_id ),
			'description'  => sprintf( __( 'Update fields (%s) for attachment #%d.', 'align-agent' ), implode( ', ', $parts ) ?: 'metadata', $att_id ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'attachment_id' => $att_id ),
		);
	}

	/** Execute update_media_meta tool. */
	public static function tool_update_media_meta( array $args = array() ) {
		$att_id = absint( $args['attachment_id'] ?? 0 );
		$att    = $att_id > 0 ? get_post( $att_id ) : null;
		if ( ! $att || 'attachment' !== $att->post_type ) {
			return new WP_Error( 'attachment_not_found', sprintf( 'Attachment #%d was not found.', $att_id ) );
		}

		$post_updates   = array( 'ID' => $att_id );
		$updated_fields = array();
		if ( array_key_exists( 'alt', $args ) && null !== $args['alt'] ) {
			$alt = sanitize_text_field( (string) $args['alt'] );
			update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
			$updated_fields['alt'] = $alt;
		}

		$field_map = array( 'title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content' );
		foreach ( $field_map as $k => $col ) {
			if ( array_key_exists( $k, $args ) && null !== $args[ $k ] ) {
				$val = 'description' === $k ? wp_kses_post( (string) $args[ $k ] ) : sanitize_text_field( (string) $args[ $k ] );
				$post_updates[ $col ] = $val;
				$updated_fields[ $k ] = $val;
			}
		}

		if ( count( $post_updates ) > 1 ) {
			wp_update_post( $post_updates );
		}
		clean_post_cache( $att_id );

		return array(
			'success'        => true,
			'attachment_id'  => $att_id,
			'updated_fields' => $updated_fields,
			'url'            => self::to_root_relative_url( (string) wp_get_attachment_url( $att_id ) ),
			'message'        => sprintf( 'Updated metadata for attachment #%d.', $att_id ),
		);
	}

	/** Summarize trash_media for approval card. */
	public static function summarize_trash_media( array $args ): array {
		$att_id = absint( $args['attachment_id'] ?? 0 );
		return array(
			'title'        => sprintf( __( 'Move Media #%d to Trash', 'align-agent' ), $att_id ),
			'description'  => sprintf( __( 'Move attachment #%d to trash (permanent deletion prohibited).', 'align-agent' ), $att_id ),
			'preview_html' => '',
			'preview_css'  => '',
			'meta'         => array( 'attachment_id' => $att_id ),
			'danger'       => true,
		);
	}

	/** Execute trash_media tool. */
	public static function tool_trash_media( array $args = array() ) {
		$att_id = absint( $args['attachment_id'] ?? 0 );
		$att    = $att_id > 0 ? get_post( $att_id ) : null;
		if ( ! $att || 'attachment' !== $att->post_type ) {
			return new WP_Error( 'attachment_not_found', sprintf( 'Attachment #%d was not found.', $att_id ) );
		}

		if ( ! defined( 'MEDIA_TRASH' ) || ! MEDIA_TRASH ) {
			return new WP_Error( 'media_trash_disabled', 'WordPress media trash is disabled (MEDIA_TRASH is not enabled). Permanent deletion of media files is prohibited.' );
		}

		global $wpdb;
		$featured_posts = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value = %d AND p.post_status != 'trash'",
			$att_id
		) );

		$content_posts = array();
		$att_url       = wp_get_attachment_url( $att_id );
		if ( $att_url ) {
			$like_full     = '%' . $wpdb->esc_like( $att_url ) . '%';
			$content_posts = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status != 'trash' AND post_type NOT IN ('attachment', 'revision') LIMIT 20",
				$like_full
			) );
		}

		$in_use = array_values( array_unique( array_merge( array_map( 'intval', (array) $featured_posts ), array_map( 'intval', (array) $content_posts ) ) ) );
		if ( ! empty( $in_use ) ) {
			return new WP_Error( 'media_in_use', sprintf( 'Media #%1$d is in use by post(s): %2$s. Remove before trashing.', $att_id, implode( ', ', $in_use ) ), array( 'in_use_posts' => $in_use ) );
		}

		$res = wp_trash_post( $att_id );
		if ( ! $res ) {
			return new WP_Error( 'trash_failed', sprintf( 'Failed to move attachment #%d to trash.', $att_id ) );
		}

		error_log( sprintf( '[Align Agent] Trashed attachment #%d.', $att_id ) );

		return array(
			'success'       => true,
			'attachment_id' => $att_id,
			'message'       => sprintf( 'Moved attachment #%d to trash.', $att_id ),
		);
	}
}
