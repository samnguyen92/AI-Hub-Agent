<?php
/**
 * Align Agent - System Prompt Rules (Render Model, Diagnose-First, Global-Safe, Working Notes)
 *
 * Supplies architectural render model rules, diagnose-first workflow rules,
 * global-scope safety instructions, and active session working notes for Align_Agent_Loop.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Prompt_Rules {

	/**
	 * Build additional system prompt rule lines for the current user and channel.
	 *
	 * @param int    $user_id User ID.
	 * @param string $channel Active channel name.
	 * @return array<int, string>
	 */
	public static function get_lines( int $user_id = 0, string $channel = '' ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( '' === $channel && class_exists( 'Align_Agent_Conversation' ) ) {
			$channel = Align_Agent_Conversation::get_channel();
		}

		$lines = array(
			'RENDER MODEL: Pages render in this exact hierarchy: block template (tpl_*) -> active template parts by area (header: tp_header, footer: tp_footer; ignore legacy uncategorized parts) -> post_content -> embedded [reuse-block id="N"] -> sitewide CSS/JS (align_custom_css, align_custom_js) -> active code slots (tracking_code, body_open_code, footer_html_code).',
			'DIAGNOSE-FIRST RULE: For any request to inspect or edit HTML, CSS, JS, or fix a JavaScript function on the website, ALWAYS call inspect_render and/or find_in_render FIRST to pinpoint the exact active source (page:<id>, tp:<slug>, block:<id>, option:align_custom_js, option:align_custom_css) in 1-2 tool calls before making edits. NEVER read or modify PHP files; if a request requires editing PHP theme/plugin code, inform the user to contact a developer.',
			'GLOBAL-SAFE RULE: Before modifying any global-scope object (tp_*, tpl_*, reuse_block, align_custom_css, align_custom_js, or code slots), state the exact object_key and the specific section/function being changed. Every global write is automatically backed up to the Global Vault; include the returned backup_id in your summary or rely on the automated Run Report.',
		);

		if ( class_exists( 'Align_Agent_Working_Notes' ) ) {
			$note = Align_Agent_Working_Notes::get( $user_id, $channel );
			if ( '' !== $note ) {
				$lines[] = 'WORKING NOTES: ' . $note;
			}
		}

		return $lines;
	}
}
