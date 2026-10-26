<?php
/**
 * Class Align_SVG_Support
 * Provides secure SVG upload support, MIME type registration, sanitization, dimension extraction, and Media Library previews.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_SVG_Support {

	/**
	 * Initialize SVG Support hooks.
	 */
	public static function init() {
		// 1. Allow SVG in upload MIME types
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_mime' ) );

		// 2. Fix file type and extension validation for WordPress 4.7.1+
		add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'fix_svg_filetype_and_ext' ), 10, 4 );

		// 3. Sanitize SVG content before upload
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'sanitize_svg_upload' ) );

		// 4. Generate SVG metadata dimensions (width/height from viewBox or width/height attrs)
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'generate_svg_metadata' ), 10, 2 );

		// 5. Enhance JS attachment response for Media Modal / Picker
		add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'prepare_svg_for_js' ), 10, 3 );

		// 6. Admin styling for SVG preview in Media Library grid
		add_action( 'admin_head', array( __CLASS__, 'add_svg_admin_styles' ) );
	}

	/**
	 * Register SVG MIME types.
	 *
	 * @param array $mimes Current allowed MIME types.
	 * @return array Modified MIME types.
	 */
	public static function allow_svg_mime( $mimes ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Fix WordPress core check for SVG files.
	 *
	 * @param array       $data     File data.
	 * @param string      $file     Full path to the file.
	 * @param string      $filename The name of the file.
	 * @param array|null  $mimes    Array of MIME types.
	 * @return array Corrected file data.
	 */
	public static function fix_svg_filetype_and_ext( $data, $file, $filename, $mimes ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		} elseif ( 'svgz' === $ext ) {
			$data['ext']  = 'svgz';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}

	/**
	 * Sanitize SVG file to strip malicious code before upload.
	 *
	 * @param array $file Upload file info.
	 * @return array Upload file info or error.
	 */
	public static function sanitize_svg_upload( $file ) {
		$filename = isset( $file['name'] ) ? $file['name'] : '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( 'svg' !== $ext && 'svgz' !== $ext ) {
			return $file;
		}

		$tmp_path = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( empty( $tmp_path ) || ! file_exists( $tmp_path ) ) {
			return $file;
		}

		$content = file_get_contents( $tmp_path );
		if ( false === $content ) {
			$file['error'] = __( 'Unable to read SVG file for verification.', 'align' );
			return $file;
		}

		// Handle gzipped SVG
		$is_gzipped = ( 'svgz' === $ext || 0 === strpos( $content, "\x1f\x8b\x08" ) );
		if ( $is_gzipped && function_exists( 'gzdecode' ) ) {
			$decoded = @gzdecode( $content );
			if ( false !== $decoded ) {
				$content = $decoded;
			}
		}

		// Basic sanity check: Must contain <svg ... </svg>
		if ( false === stripos( $content, '<svg' ) ) {
			$file['error'] = __( 'Invalid SVG file structure.', 'align' );
			return $file;
		}

		// Security: Strip dangerous tags and attributes
		$content = self::clean_svg_content( $content );

		// Write sanitized content back to temporary file
		if ( $is_gzipped && function_exists( 'gzencode' ) ) {
			file_put_contents( $tmp_path, gzencode( $content ) );
		} else {
			file_put_contents( $tmp_path, $content );
		}

		return $file;
	}

	/**
	 * Clean and sanitize SVG XML markup.
	 *
	 * @param string $svg_content Raw SVG content.
	 * @return string Sanitized SVG content.
	 */
	public static function clean_svg_content( string $svg_content ): string {
		// 1. Remove dangerous elements (<script>, <style> with expressions, <object>, <iframe>, <embed>, <applet>)
		$patterns = array(
			'/<script\b[^>]*>[\s\S]*?<\/script>/is',
			'/<object\b[^>]*>[\s\S]*?<\/object>/is',
			'/<iframe\b[^>]*>[\s\S]*?<\/iframe>/is',
			'/<embed\b[^>]*>[\s\S]*?<\/embed>/is',
			'/<applet\b[^>]*>[\s\S]*?<\/applet>/is',
			'/<!DOCTYPE\b[^>]*>/is',
			'/<!ENTITY\b[^>]*>/is',
		);
		$svg_content = preg_replace( $patterns, '', $svg_content );

		// 2. Remove inline event handlers (onload, onerror, onclick, etc.)
		$svg_content = preg_replace( '/\s+on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $svg_content );

		// 3. Remove javascript: pseudo protocols in href/xlink:href/src
		$svg_content = preg_replace( '/(href|xlink:href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '', $svg_content );

		return $svg_content;
	}

	/**
	 * Extract width and height dimensions from SVG file.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment Post ID.
	 * @return array Enhanced metadata with width and height.
	 */
	public static function generate_svg_metadata( $metadata, $attachment_id ) {
		$mime_type = get_post_mime_type( $attachment_id );
		if ( 'image/svg+xml' !== $mime_type ) {
			return $metadata;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		$content = file_get_contents( $file_path );
		if ( 0 === strpos( $content, "\x1f\x8b\x08" ) && function_exists( 'gzdecode' ) ) {
			$content = @gzdecode( $content );
		}

		$width  = 0;
		$height = 0;

		// Try to parse root <svg> tag attributes
		if ( preg_match( '/<svg\b([^>]*)>/is', (string) $content, $m ) ) {
			$attrs = $m[1];

			if ( preg_match( '/\bwidth\s*=\s*["\']([0-9.]+)(px)?["\']/i', $attrs, $wm ) ) {
				$width = floatval( $wm[1] );
			}
			if ( preg_match( '/\bheight\s*=\s*["\']([0-9.]+)(px)?["\']/i', $attrs, $hm ) ) {
				$height = floatval( $hm[1] );
			}

			// Fallback to viewBox="minX minY width height"
			if ( ( ! $width || ! $height ) && preg_match( '/\bviewBox\s*=\s*["\']\s*([0-9.-]+)\s+([0-9.-]+)\s+([0-9.]+)\s+([0-9.]+)\s*["\']/i', $attrs, $vm ) ) {
				if ( ! $width ) {
					$width = floatval( $vm[3] );
				}
				if ( ! $height ) {
					$height = floatval( $vm[4] );
				}
			}
		}

		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}

		$metadata['width']  = $width > 0 ? (int) round( $width ) : 100;
		$metadata['height'] = $height > 0 ? (int) round( $height ) : 100;

		return $metadata;
	}

	/**
	 * Prepare SVG attachment for WordPress Media Modal JS response.
	 *
	 * @param array   $response   JS response data.
	 * @param WP_Post $attachment Attachment post object.
	 * @param array   $meta       Attachment metadata.
	 * @return array Modified JS response.
	 */
	public static function prepare_svg_for_js( $response, $attachment, $meta ) {
		if ( 'image/svg+xml' === $response['mime'] ) {
			$width  = ! empty( $meta['width'] ) ? intval( $meta['width'] ) : 100;
			$height = ! empty( $meta['height'] ) ? intval( $meta['height'] ) : 100;

			$response['image'] = array(
				'src'    => $response['url'],
				'width'  => $width,
				'height' => $height,
			);
			$response['thumb'] = array(
				'src'    => $response['url'],
				'width'  => $width,
				'height' => $height,
			);
			if ( empty( $response['sizes'] ) ) {
				$response['sizes'] = array();
			}
			$response['sizes']['full'] = array(
				'url'         => $response['url'],
				'width'       => $width,
				'height'      => $height,
				'orientation' => $width >= $height ? 'landscape' : 'portrait',
			);
			$response['sizes']['thumbnail'] = array(
				'url'         => $response['url'],
				'width'       => min( $width, 150 ),
				'height'      => min( $height, 150 ),
				'orientation' => $width >= $height ? 'landscape' : 'portrait',
			);
		}
		return $response;
	}

	/**
	 * Output admin CSS styles for crisp SVG preview rendering.
	 */
	public static function add_svg_admin_styles() {
		echo '<style id="align-svg-admin-styles">
			.attachment-266x266, .thumbnail img[src$=".svg"], .attachment-preview img[src$=".svg"], .media-icon img[src$=".svg"] { width: 100% !important; height: auto !important; max-height: 100% !important; }
			.media-modal .media-frame-content .attachments-browser .attachment-preview .thumbnail .centered img[src$=".svg"] { width: 100% !important; height: auto !important; transform: none !important; }
		</style>';
	}
}
