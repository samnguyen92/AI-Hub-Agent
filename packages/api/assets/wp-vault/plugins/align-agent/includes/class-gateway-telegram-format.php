<?php
/**
 * Align Agent Gateway - Telegram HTML Formatter & Safe Chunker
 *
 * Converts assistant responses (Markdown/HTML) to Telegram-supported HTML subset
 * (<b>, <i>, <u>, <s>, <code>, <pre>, <a>), escapes non-supported tags, chunks messages
 * to <= 4096 UTF-8 characters without splitting tags, and provides plain text fallback.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Gateway_Telegram_Format {

	/**
	 * Telegram maximum message character length.
	 */
	const MAX_LENGTH = 4096;

	/**
	 * Send an assistant reply to Telegram chat, automatically formatting, chunking, and handling errors.
	 *
	 * @param string $text    Raw assistant reply text.
	 * @param string $chat_id Target Telegram chat ID.
	 * @return bool True if all chunks sent successfully.
	 */
	public static function send_reply( string $text, string $chat_id ): bool {
		$chunks = self::format_and_chunk( $text );
		$all_ok = true;

		foreach ( $chunks as $chunk ) {
			$res = Align_Gateway_Telegram::send_message( $chunk, 'HTML', '', $chat_id );
			if ( empty( $res['success'] ) ) {
				$all_ok = false;
				// Fallback retry with stripped plain text.
				Align_Gateway_Telegram::send_message( wp_strip_all_tags( $chunk ), '', '', $chat_id );
			}
		}

		return $all_ok;
	}

	/**
	 * Convert markdown/html text into an array of safe Telegram HTML chunks <= 4096 chars.
	 *
	 * @param string $input Raw input text.
	 * @return string[] Array of chunked HTML strings.
	 */
	public static function format_and_chunk( string $input ): array {
		$html = self::markdown_to_telegram_html( $input );
		return self::chunk_html( $html, self::MAX_LENGTH );
	}

	/**
	 * Convert standard Markdown to Telegram-supported HTML tags.
	 * Allowed: <b>, <strong>, <i>, <em>, <u>, <ins>, <s>, <strike>, <del>, <code>, <pre>, <a href="...">.
	 *
	 * @param string $text Raw text with Markdown or HTML.
	 * @return string
	 */
	public static function markdown_to_telegram_html( string $text ): string {
		if ( empty( $text ) ) {
			return '';
		}

		// Normalize newlines.
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

		// 1. Extract and protect pre/code blocks.
		$code_blocks = array();
		$text = preg_replace_callback(
			'/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/u',
			function ( $m ) use ( &$code_blocks ) {
				$idx   = count( $code_blocks );
				$code  = htmlspecialchars( $m[2], ENT_QUOTES, 'UTF-8' );
				$token = "%%TGCODEBLOCK{$idx}%%";
				$code_blocks[ $token ] = '<pre><code>' . $code . '</code></pre>';
				return $token;
			},
			$text
		);

		// 2. Extract and protect inline code.
		$inline_codes = array();
		$text = preg_replace_callback(
			'/`([^`\n]+)`/u',
			function ( $m ) use ( &$inline_codes ) {
				$idx   = count( $inline_codes );
				$code  = htmlspecialchars( $m[1], ENT_QUOTES, 'UTF-8' );
				$token = "%%TGINLINECODE{$idx}%%";
				$inline_codes[ $token ] = '<code>' . $code . '</code>';
				return $token;
			},
			$text
		);

		// 3. Escape HTML entities in non-code text to prevent parser breakdown.
		$text = htmlspecialchars( $text, ENT_NOQUOTES, 'UTF-8' );

		// 4. Markdown Headings -> Bold line.
		$text = preg_replace( '/^#{1,6}\s+(.+)$/m', '<b>$1</b>', $text );

		// 5. Markdown Bold: **text** or __text__ -> <b>text</b>.
		$text = preg_replace( '/\*\*(.+?)\*\*/us', '<b>$1</b>', $text );
		$text = preg_replace( '/__(.+?)__/us', '<b>$1</b>', $text );

		// 6. Markdown Italic: *text* or _text_ -> <i>text</i>.
		$text = preg_replace( '/(?<!\w)\*([^*\n]+)\*(?!\w)/u', '<i>$1</i>', $text );
		$text = preg_replace( '/(?<!\w)_([^_\n]+)_(?!\w)/u', '<i>$1</i>', $text );

		// 7. Markdown Strikethrough: ~~text~~ -> <s>text</s>.
		$text = preg_replace( '/~~(.+?)~~/us', '<s>$1</s>', $text );

		// 8. Markdown Links: [label](url).
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/u',
			function ( $m ) {
				$url   = esc_url( htmlspecialchars_decode( $m[2] ) );
				$label = $m[1];
				return '<a href="' . $url . '">' . $label . '</a>';
			},
			$text
		);

		// 9. Reinsert inline code & code blocks.
		foreach ( $inline_codes as $token => $replacement ) {
			$text = str_replace( $token, $replacement, $text );
		}
		foreach ( $code_blocks as $token => $replacement ) {
			$text = str_replace( $token, $replacement, $text );
		}

		return trim( $text );
	}

	/**
	 * Split HTML content into chunks not exceeding $max_length characters,
	 * ensuring open tags are properly closed before split and reopened in next chunk.
	 *
	 * @param string $html       Formatted HTML text.
	 * @param int    $max_length Maximum chunk length in characters.
	 * @return string[]
	 */
	public static function chunk_html( string $html, int $max_length = self::MAX_LENGTH ): array {
		if ( mb_strlen( $html, 'UTF-8' ) <= $max_length ) {
			return array( $html );
		}

		$chunks = array();
		$lines  = explode( "\n", $html );
		$curr   = '';
		$open_tags = array();

		foreach ( $lines as $line ) {
			$candidate = ( '' === $curr ) ? $line : $curr . "\n" . $line;
			if ( mb_strlen( $candidate, 'UTF-8' ) > $max_length - 50 ) {
				if ( '' !== $curr ) {
					$chunks[] = self::seal_chunk( $curr, $open_tags );
					$curr     = self::prefix_chunk( $line, $open_tags );
				} else {
					// Single line exceeds max_length: hard cut at safe boundaries.
					$sub_chunks = self::hard_chunk( $line, $max_length - 50, $open_tags );
					foreach ( $sub_chunks as $sc ) {
						$chunks[] = $sc;
					}
					$curr = '';
				}
			} else {
				$curr = $candidate;
				self::track_open_tags( $line, $open_tags );
			}
		}

		if ( '' !== trim( $curr ) ) {
			$chunks[] = self::seal_chunk( $curr, $open_tags );
		}

		return ! empty( $chunks ) ? $chunks : array( $html );
	}

	/**
	 * Track opened and closed HTML tags in a line.
	 *
	 * @param string   $segment   Text segment.
	 * @param string[] &$open_tags Stack of currently open tags.
	 */
	private static function track_open_tags( string $segment, array &$open_tags ): void {
		if ( preg_match_all( '/<\/?([a-z0-9]+)(?:\s+[^>]*)?>/i', $segment, $matches ) ) {
			foreach ( $matches[0] as $i => $full_tag ) {
				$tag_name = strtolower( $matches[1][ $i ] );
				if ( 0 === strpos( $full_tag, '</' ) ) {
					// Closing tag.
					$key = array_search( $tag_name, array_reverse( $open_tags, true ), true );
					if ( false !== $key ) {
						unset( $open_tags[ $key ] );
						$open_tags = array_values( $open_tags );
					}
				} else {
					// Opening tag (ignore self-closing).
					if ( ! preg_match( '/\/>$/', $full_tag ) && in_array( $tag_name, array( 'b', 'strong', 'i', 'em', 'u', 's', 'code', 'pre', 'a' ), true ) ) {
						$open_tags[] = $tag_name;
					}
				}
			}
		}
	}

	/**
	 * Seal a chunk by closing any currently dangling open tags.
	 *
	 * @param string   $chunk     Chunk text.
	 * @param string[] $open_tags Currently open tags.
	 * @return string
	 */
	private static function seal_chunk( string $chunk, array $open_tags ): string {
		$sealed = $chunk;
		$tags_to_close = array_reverse( $open_tags );
		foreach ( $tags_to_close as $tag ) {
			$sealed .= '</' . $tag . '>';
		}
		return $sealed;
	}

	/**
	 * Prefix a new chunk by reopening dangling tags from previous chunk.
	 *
	 * @param string   $chunk     Chunk text.
	 * @param string[] $open_tags Tags needing reopening.
	 * @return string
	 */
	private static function prefix_chunk( string $chunk, array $open_tags ): string {
		$prefix = '';
		foreach ( $open_tags as $tag ) {
			$prefix .= '<' . $tag . '>';
		}
		return $prefix . $chunk;
	}

	/**
	 * Split long lines while preserving tag boundary integrity.
	 *
	 * @param string   $line       Long text line.
	 * @param int      $max_length Max chunk size.
	 * @param string[] &$open_tags Open tags stack.
	 * @return string[]
	 */
	private static function hard_chunk( string $line, int $max_length, array &$open_tags ): array {
		$res = array();
		$len = mb_strlen( $line, 'UTF-8' );
		$pos = 0;

		while ( $pos < $len ) {
			$piece = mb_substr( $line, $pos, $max_length, 'UTF-8' );
			self::track_open_tags( $piece, $open_tags );
			$res[] = self::seal_chunk( $piece, $open_tags );
			$pos  += $max_length;
		}

		return $res;
	}
}
