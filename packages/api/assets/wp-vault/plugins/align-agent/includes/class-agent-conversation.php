<?php
/**
 * Align Agent - Per-User Conversation & Pending Writes Storage
 *
 * Stores chat messages and pending write actions in WordPress user_meta
 * under 'align_agent_conversation', enforcing message window limits,
 * tool output truncation, and atomic assistant(tool_calls)/tool pairs.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Conversation {

	/**
	 * User meta key for storing conversation state.
	 */
	const META_KEY = 'align_agent_conversation';

	/**
	 * Current conversation channel context ('chatbox', 'telegram', etc.).
	 *
	 * @var string
	 */
	private static string $current_channel = 'chatbox';

	/**
	 * Set active conversation channel for current execution context.
	 *
	 * @param string $channel Channel name ('chatbox' or 'telegram').
	 */
	public static function set_channel( string $channel ): void {
		$channel = sanitize_key( $channel );
		self::$current_channel = ! empty( $channel ) ? $channel : 'chatbox';
	}

	/**
	 * Get active conversation channel.
	 *
	 * @return string
	 */
	public static function get_channel(): string {
		return self::$current_channel;
	}

	/**
	 * Retrieve effective user_meta key for conversation state based on channel.
	 *
	 * @return string
	 */
	public static function get_meta_key(): string {
		if ( 'telegram' === self::$current_channel ) {
			return 'align_agent_conversation_telegram';
		}
		return self::META_KEY;
	}

	/**
	 * Maximum number of recent messages retained per user.
	 */
	const MAX_MESSAGES = 40;

	/**
	 * Maximum length (in characters) of a tool result stored in conversation history.
	 */
	const MAX_TOOL_OUTPUT_CHARS = 8000;

	/**
	 * Expiration window (in seconds) for pending write approvals (15 minutes).
	 */
	const PENDING_TTL_SECONDS = 900;

	/**
	 * Initialize conversation storage hooks.
	 */
	public static function init(): void {
		// Ready for future cleanup hooks.
	}

	/**
	 * Retrieve full conversation state for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array{conversation_id: string, messages: array, pending_writes: array, updated_at: int}
	 */
	public static function get_state( int $user_id = 0 ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id > 0 ) {
			wp_cache_delete( $user_id, 'user_meta' );
		}

		$meta_key = self::get_meta_key();
		$raw      = $user_id > 0 ? get_user_meta( $user_id, $meta_key, true ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$conv_id    = ! empty( $raw['conversation_id'] ) && is_string( $raw['conversation_id'] )
			? $raw['conversation_id']
			: 'conv_' . wp_generate_password( 12, false, false );
		$messages   = isset( $raw['messages'] ) && is_array( $raw['messages'] ) ? array_values( $raw['messages'] ) : array();
		$updated_at = isset( $raw['updated_at'] ) ? (int) $raw['updated_at'] : time();

		$pending_writes = $user_id > 0 && class_exists( 'Align_Agent_Pending_Store' )
			? Align_Agent_Pending_Store::list_for( $user_id, self::get_channel() )
			: array();

		return array(
			'conversation_id' => $conv_id,
			'messages'        => $messages,
			'pending_writes'  => $pending_writes,
			'updated_at'      => $updated_at,
		);
	}

	/**
	 * Save conversation state to user_meta after enforcing window & pair integrity.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $state   State array to persist.
	 * @return bool
	 */
	public static function save_state( int $user_id, array $state ): bool {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id <= 0 ) {
			return false;
		}

		$messages = isset( $state['messages'] ) && is_array( $state['messages'] ) ? array_values( $state['messages'] ) : array();
		$messages = self::trim_messages_preserving_pairs( $messages, self::MAX_MESSAGES );

		$clean_state = array(
			'conversation_id' => ! empty( $state['conversation_id'] ) ? (string) $state['conversation_id'] : 'conv_' . wp_generate_password( 12, false, false ),
			'messages'        => $messages,
			'updated_at'      => time(),
		);

		update_user_meta( $user_id, self::get_meta_key(), wp_slash( $clean_state ) );
		return true;
	}

	/**
	 * Reset conversation and clear pending writes for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array New empty state.
	 */
	public static function reset( int $user_id = 0 ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id > 0 && class_exists( 'Align_Agent_Pending_Store' ) ) {
			Align_Agent_Pending_Store::supersede_all( $user_id, self::get_channel() );
		}

		$fresh = array(
			'conversation_id' => 'conv_' . wp_generate_password( 12, false, false ),
			'messages'        => array(),
			'pending_writes'  => array(),
			'updated_at'      => time(),
		);

		if ( $user_id > 0 ) {
			if ( class_exists( 'Align_Agent_Working_Notes' ) ) {
				Align_Agent_Working_Notes::clear( $user_id, self::get_channel() );
			}
			update_user_meta( $user_id, self::get_meta_key(), $fresh );
			error_log( sprintf( '[Align Agent] Conversation reset for user #%d (channel: %s).', $user_id, self::get_channel() ) );
		}

		return $fresh;
	}

	/**
	 * Completely delete the stored user_meta session and initialize a fresh conversation state.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array{conversation_id: string, previous_conversation_id: string, messages: array, pending_writes: array, updated_at: int}
	 */
	public static function delete_session( int $user_id = 0 ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id > 0 && class_exists( 'Align_Agent_Pending_Store' ) ) {
			Align_Agent_Pending_Store::supersede_all( $user_id, self::get_channel() );
		}

		$old_id   = '';
		$meta_key = self::get_meta_key();
		if ( $user_id > 0 ) {
			$existing = get_user_meta( $user_id, $meta_key, true );
			if ( is_array( $existing ) && ! empty( $existing['conversation_id'] ) ) {
				$old_id = (string) $existing['conversation_id'];
			}
			delete_user_meta( $user_id, $meta_key );
		}

		$new_id = 'conv_' . wp_generate_password( 12, false, false );
		$fresh  = array(
			'conversation_id' => $new_id,
			'messages'        => array(),
			'pending_writes'  => array(),
			'updated_at'      => time(),
		);

		if ( $user_id > 0 ) {
			if ( class_exists( 'Align_Agent_Working_Notes' ) ) {
				Align_Agent_Working_Notes::clear( $user_id, self::get_channel() );
			}
			update_user_meta( $user_id, $meta_key, $fresh );
			error_log( sprintf( '[Align Agent] Session deleted | user: %d | channel: %s | old_id: %s | new_id: %s', $user_id, self::get_channel(), $old_id ?: 'none', $new_id ) );
		}

		$fresh['previous_conversation_id'] = $old_id;
		return $fresh;
	}

	/**
	 * Append a new user message. Automatically closes any unresolved assistant tool_calls
	 * so OpenAI API never rejects the conversation for missing tool outputs.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $content User message text.
	 * @return array Updated state.
	 */
	public static function add_user_message( int $user_id, string $content ): array {
		$state = self::get_state( $user_id );
		$state = self::close_orphan_tool_calls( $state, $user_id );

		$state['messages'][] = array(
			'role'      => 'user',
			'content'   => trim( $content ),
			'timestamp' => time(),
		);

		self::save_state( $user_id, $state );
		return self::get_state( $user_id );
	}

	/**
	 * Append an assistant message (text and/or tool_calls).
	 *
	 * @param int         $user_id    WordPress user ID.
	 * @param string|null $content    Assistant text response.
	 * @param array       $tool_calls Optional array of OpenAI tool_calls.
	 * @param array       $ui_cards   Optional UI cards metadata (approval or result cards) for history rendering.
	 * @return array Updated state.
	 */
	public static function add_assistant_message( int $user_id, ?string $content, array $tool_calls = array(), array $ui_cards = array() ): array {
		$state = self::get_state( $user_id );

		$entry = array(
			'role'      => 'assistant',
			'content'   => null !== $content ? (string) $content : '',
			'timestamp' => time(),
		);

		if ( ! empty( $tool_calls ) ) {
			$entry['tool_calls'] = array_values( $tool_calls );
		}

		if ( ! empty( $ui_cards ) ) {
			$entry['ui_cards'] = array_values( $ui_cards );
		}

		$state['messages'][] = $entry;
		self::save_state( $user_id, $state );
		return self::get_state( $user_id );
	}

	/**
	 * Append a tool execution result message, truncating output to MAX_TOOL_OUTPUT_CHARS.
	 *
	 * @param int    $user_id      WordPress user ID.
	 * @param string $tool_call_id OpenAI tool_call_id.
	 * @param string $tool_name    Function name.
	 * @param mixed  $output       Tool execution result (array or string).
	 * @param array  $ui_card      Optional result card metadata for history rendering.
	 * @return array Updated state.
	 */
	public static function add_tool_message( int $user_id, string $tool_call_id, string $tool_name, $output, array $ui_card = array() ): array {
		$state   = self::get_state( $user_id );
		$encoded = is_string( $output ) ? $output : (string) wp_json_encode( $output );

		$entry = array(
			'role'         => 'tool',
			'tool_call_id' => $tool_call_id,
			'name'         => $tool_name,
			'content'      => self::truncate_tool_output( $encoded ),
			'timestamp'    => time(),
		);

		if ( ! empty( $ui_card ) ) {
			$entry['ui_card'] = $ui_card;
		}

		$state['messages'][] = $entry;
		self::save_state( $user_id, $state );
		return self::get_state( $user_id );
	}

	/**
	 * Queue a pending write operation requiring explicit user approval.
	 *
	 * @param int    $user_id      WordPress user ID.
	 * @param string $tool_call_id OpenAI tool_call_id.
	 * @param string $tool_name    Registered write tool name.
	 * @param array  $args         Parsed tool arguments.
	 * @param array  $summary      Human-readable summary for the UI approval card.
	 * @return string Generated pending_id.
	 */
	public static function queue_pending_write( int $user_id, string $tool_call_id, string $tool_name, array $args, array $summary ): string {
		$now  = time();
		$item = array(
			'tool_call_id' => $tool_call_id,
			'tool_name'    => $tool_name,
			'args'         => $args,
			'summary'      => $summary,
			'created_at'   => $now,
			'expires_at'   => $now + self::PENDING_TTL_SECONDS,
		);

		if ( class_exists( 'Align_Agent_Pending_Store' ) ) {
			return Align_Agent_Pending_Store::add( $user_id, self::get_channel(), $item );
		}

		$pending_id = 'pw_' . wp_generate_password( 16, false, false );
		$item['pending_id'] = $pending_id;
		$state = self::get_state( $user_id );
		$state['pending_writes'][ $pending_id ] = $item;
		self::save_state( $user_id, $state );
		return $pending_id;
	}

	/**
	 * Retrieve and remove a pending write item by pending_id.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $pending_id Pending write identifier.
	 * @return array|null Pending write item or null if not found/expired.
	 */
	public static function pop_pending_write( int $user_id, string $pending_id ): ?array {
		if ( class_exists( 'Align_Agent_Pending_Store' ) ) {
			$result = Align_Agent_Pending_Store::take( $pending_id );
			return $result['item'];
		}

		$state = self::get_state( $user_id );
		if ( empty( $state['pending_writes'][ $pending_id ] ) ) {
			return null;
		}

		$item = $state['pending_writes'][ $pending_id ];
		unset( $state['pending_writes'][ $pending_id ] );
		self::save_state( $user_id, $state );

		if ( ! empty( $item['expires_at'] ) && time() > (int) $item['expires_at'] ) {
			return null;
		}

		return $item;
	}

	/**
	 * Truncate large tool output (such as full page HTML) to MAX_TOOL_OUTPUT_CHARS.
	 *
	 * @param string $text Raw tool output string.
	 * @param int    $max  Maximum allowed character length.
	 * @return string
	 */
	public static function truncate_tool_output( string $text, int $max = self::MAX_TOOL_OUTPUT_CHARS ): string {
		if ( strlen( $text ) <= $max ) {
			return $text;
		}

		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max, 'UTF-8' ) : substr( $text, 0, $max );
		return $cut . "\n...[truncated " . ( strlen( $text ) - strlen( $cut ) ) . ' bytes for context window safety]';
	}

	/**
	 * Close any unresolved assistant(tool_calls) in history with synthetic cancelled tool responses.
	 *
	 * @param array $state Current conversation state.
	 * @return array Updated conversation state.
	 */
	public static function close_orphan_tool_calls( array $state, int $user_id = 0 ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id > 0 && class_exists( 'Align_Agent_Pending_Store' ) ) {
			Align_Agent_Pending_Store::supersede_all( $user_id, self::get_channel() );
		}

		$messages = isset( $state['messages'] ) && is_array( $state['messages'] ) ? array_values( $state['messages'] ) : array();
		if ( empty( $messages ) ) {
			$state['pending_writes'] = array();
			return $state;
		}

		$answered_ids = array();
		foreach ( $messages as $msg ) {
			if ( isset( $msg['role'] ) && 'tool' === $msg['role'] && ! empty( $msg['tool_call_id'] ) ) {
				$answered_ids[ (string) $msg['tool_call_id'] ] = true;
			}
		}

		$repaired = array();
		foreach ( $messages as $msg ) {
			$repaired[] = $msg;
			if ( isset( $msg['role'] ) && 'assistant' === $msg['role'] && ! empty( $msg['tool_calls'] ) && is_array( $msg['tool_calls'] ) ) {
				foreach ( $msg['tool_calls'] as $tc ) {
					$tc_id   = isset( $tc['id'] ) ? (string) $tc['id'] : '';
					$tc_name = isset( $tc['function']['name'] ) ? (string) $tc['function']['name'] : 'unknown';
					if ( '' !== $tc_id && empty( $answered_ids[ $tc_id ] ) ) {
						$repaired[]              = array(
							'role'         => 'tool',
							'tool_call_id' => $tc_id,
							'name'         => $tc_name,
							'content'      => (string) wp_json_encode(
								array(
									'success' => false,
									'status'  => 'cancelled',
									'message' => 'Skipped or cancelled because user sent a new message.',
								)
							),
							'timestamp'    => time(),
						);
						$answered_ids[ $tc_id ] = true;
					}
				}
			}
		}

		$state['messages']       = $repaired;
		$state['pending_writes'] = array();
		return $state;
	}

	/**
	 * Trim messages to $limit while never splitting an assistant(tool_calls) -> tool(tool_call_id) group.
	 *
	 * @param array $messages Full messages list.
	 * @param int   $limit    Maximum number of messages to keep.
	 * @return array
	 */
	public static function trim_messages_preserving_pairs( array $messages, int $limit = self::MAX_MESSAGES ): array {
		$total = count( $messages );
		if ( $total <= $limit ) {
			return $messages;
		}

		$start = $total - $limit;

		// Advance $start if it lands on a 'tool' message so we never leave orphan 'tool' messages at the head.
		while ( $start < $total && isset( $messages[ $start ]['role'] ) && 'tool' === $messages[ $start ]['role'] ) {
			$start++;
		}

		return array_slice( $messages, $start );
	}
}
