<?php
/**
 * Align Agent - Working Notes Manager & Tool
 *
 * Stores concise per-session working notes in user_meta (align_agent_notes_{channel},
 * capped at 1,024 bytes), registers the save_working_note tool (group: content),
 * and auto-updates notes from inspect_render results.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Working_Notes {

	const META_PREFIX = 'align_agent_notes_';
	const MAX_BYTES   = 1024;

	/**
	 * Register save_working_note tool (invoked from Align_Agent_Tools_Patch::register).
	 *
	 * @return void
	 */
	public static function register_tool(): void {
		Align_Agent_Tool_Registry::register(
			'save_working_note',
			array(
				'type'             => 'read',
				'group'            => 'content',
				'scope'            => 'local',
				'capability'       => 'edit_posts',
				'requires_builder' => false,
				'danger'           => false,
				'description'      => 'Save a concise working note (max 1 KB) for the current conversation session so key findings (active template parts, target IDs, JS function names) persist across continuation turns.',
				'parameters'       => array(
					'type'       => 'object',
					'properties' => array(
						'text' => array(
							'type'        => 'string',
							'description' => 'Working note text to store (up to 1,024 bytes; overwrites previous note).',
						),
					),
					'required'   => array( 'text' ),
				),
				'callback'         => array( __CLASS__, 'tool_save_working_note' ),
			)
		);
	}

	/**
	 * Resolve user meta key for the given channel.
	 *
	 * @param string $channel Channel name ('chatbox' or 'telegram').
	 * @return string
	 */
	private static function get_meta_key( string $channel = '' ): string {
		if ( '' === $channel && class_exists( 'Align_Agent_Conversation' ) ) {
			$channel = Align_Agent_Conversation::get_channel();
		}
		$channel = sanitize_key( $channel ?: 'chatbox' );
		return self::META_PREFIX . $channel;
	}

	/**
	 * Retrieve stored working note for a user and channel.
	 *
	 * @param int    $user_id User ID.
	 * @param string $channel Channel name.
	 * @return string
	 */
	public static function get( int $user_id = 0, string $channel = '' ): string {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id <= 0 ) {
			return '';
		}
		$val = get_user_meta( $user_id, self::get_meta_key( $channel ), true );
		return is_string( $val ) ? trim( $val ) : '';
	}

	/**
	 * Save working note (capped at 1,024 bytes, overwriting old note).
	 *
	 * @param int    $user_id User ID.
	 * @param string $text    Note text.
	 * @param string $channel Channel name.
	 * @return string Saved note string.
	 */
	public static function save( int $user_id, string $text, string $channel = '' ): string {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		$clean = trim( wp_strip_all_tags( $text ) );
		if ( strlen( $clean ) > self::MAX_BYTES ) {
			$clean = function_exists( 'mb_strcut' )
				? mb_strcut( $clean, 0, self::MAX_BYTES, 'UTF-8' )
				: substr( $clean, 0, self::MAX_BYTES );
		}
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, self::get_meta_key( $channel ), wp_slash( $clean ) );
		}
		return $clean;
	}

	/**
	 * Clear working note for a user and channel (called on /new and Clear).
	 *
	 * @param int    $user_id User ID.
	 * @param string $channel Channel name.
	 * @return void
	 */
	public static function clear( int $user_id = 0, string $channel = '' ): void {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, self::get_meta_key( $channel ) );
		}
	}

	/**
	 * Auto-generate and save a concise summary from inspect_render output.
	 *
	 * @param int   $user_id User ID.
	 * @param array $map     Output array from inspect_render.
	 * @return void
	 */
	public static function record_from_inspect( int $user_id, array $map ): void {
		$page_id = absint( $map['page']['post_id'] ?? 0 );
		$title   = (string) ( $map['page']['title'] ?? '' );
		$lang    = (string) ( $map['language']['lang'] ?? '' );
		$missing = ! empty( $map['language']['missing'] ) ? implode( ',', (array) $map['language']['missing'] ) : 'none';
		$tpl     = (string) ( $map['template']['tp_id'] ?? '' );
		$header  = (string) ( $map['active_parts']['header'] ?? '' );
		$footer  = (string) ( $map['active_parts']['footer'] ?? '' );

		$summary = sprintf(
			'Page #%d ("%s", lang=%s, missing=%s) | tpl=%s | header=%s | footer=%s',
			$page_id,
			$title,
			$lang,
			$missing,
			$tpl,
			$header,
			$footer
		);

		self::save( $user_id, $summary );
	}

	/**
	 * Callback for save_working_note tool.
	 *
	 * @param array<string, mixed> $args Tool arguments.
	 * @return array<string, mixed>
	 */
	public static function tool_save_working_note( array $args = array() ): array {
		$text = isset( $args['text'] ) ? (string) $args['text'] : '';
		if ( '' === trim( $text ) ) {
			return array(
				'success' => false,
				'error'   => 'empty_note',
				'message' => 'Note text cannot be empty.',
			);
		}
		$saved = self::save( get_current_user_id(), $text );
		return array(
			'success' => true,
			'bytes'   => strlen( $saved ),
			'note'    => $saved,
			'message' => 'Working note saved.',
		);
	}
}
