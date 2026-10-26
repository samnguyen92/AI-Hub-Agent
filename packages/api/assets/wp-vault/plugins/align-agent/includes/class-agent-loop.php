<?php
/**
 * Align Agent - Client-Driven Single-Turn Tool Calling Loop
 *
 * Executes one LLM turn per request, processes read-only tool_calls immediately,
 * queues write tool_calls for explicit user approval, and enforces the MAX_CONTINUE_TURNS limit.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Loop {

	/**
	 * Hard cap on consecutive automatic tool-calling turns per user prompt.
	 */
	const MAX_CONTINUE_TURNS = 20;

	/**
	 * Initialize agent loop hooks.
	 */
	public static function init(): void {
		// Ready for future filters.
	}

	/**
	 * Build the system prompt with current site and design intelligence.
	 *
	 * @return string
	 */
	public static function build_system_prompt(): string {
		$site_title     = (string) get_bloginfo( 'name' );
		$site_url       = (string) home_url();
		$builder_active = Align_Agent_Tool_Registry::is_builder_available();

		$css_vars_hint = '';
		if ( class_exists( 'Align_Gateway_Context' ) ) {
			$intel = Align_Gateway_Context::get_site_intelligence();
			if ( ! empty( $intel['css_variables'] ) && is_array( $intel['css_variables'] ) ) {
				$pairs = array();
				foreach ( array_slice( $intel['css_variables'], 0, 10, true ) as $k => $v ) {
					$pairs[] = $k . ': ' . $v;
				}
				$css_vars_hint = 'Active CSS Variables: ' . implode( '; ', $pairs ) . '.';
			}
		}

		$lines = array(
			sprintf( 'You are Align Agent, the CMS management assistant for "%s" (%s).', $site_title, $site_url ),
			'Always respond clearly, concisely, and in the same language the user used.',
			'Do not use emojis in your responses.',
			'When inspecting or modifying WordPress content and layout:',
			'1. Use list_pages, get_page, and get_revisions to inspect real database state before answering.',
			'2. Never embed external <script src="..."> or inline <script> tags inside HTML. Any JavaScript must go into the dedicated js parameter, or third-party embeds must be placed via Align Studio -> Reuse Block.',
			'3. Always keep HTML tags balanced and semantic. Always preserve existing data-component-shortcode attributes, [reuse-block id="..."] shortcodes, and wp:align/* Gutenberg block wrappers unless explicitly asked to modify them.',
			'4. Default to status="draft" when creating or saving pages unless the user explicitly asks to publish.',
			'5. Headers and footers are template parts. Inspect and update them via get_template and save_template.',
			'6. Navigation menus are static HTML inside the header template part. When modifying menus, always update both desktop (.nav-link-btn) and mobile drawer (.mobile-nav-link) structures concurrently.',
			'7. When calling save_template, always provide at least one content parameter (html, css, js). You can provide both html and css concurrently.',
			'8. Never use patch_content on empty content or with an empty find pattern. When populating or creating content for the first time, use save_template, save_page, or save_post.',
			'9. For large pages with 3 or more sections, call save_page first with the base layout and the first section, then incrementally append each remaining section one by one using patch_content to keep output tokens manageable.',
			'10. STEP EFFICIENCY FOR HTML EDITS: (a) Batch all modifications targeting the same page, post, template, or reuse_block into a single patch_content call (up to 10 edits per call). (b) Invoke independent read-only tools in parallel within the same turn. (c) Do not re-read the entire content via get_page/get_template after each edit; rely on the patch_content return status to verify changes. (d) When a task spans more work than fits in one batch of turns, finish each target object completely before moving to the next and report progress.',
			'Write tools execute immediately. Every change is undoable from the run report. Prefer small, targeted edits.',
			'When the user asks to list or show all pages, posts, or content: call count_content first. If the total is 15 or fewer, list them with links. Otherwise reply with counts per type only, then ask which item they want to find. Use find_content to answer search requests and return links.',
			'MULTI-STEP REASONING & TOOL CHAINING FOR LANGUAGE QUERIES: In Vietnamese, "bài" or "bài viết" refers to both Pages (page) and Posts (post). When the user asks to list, find, create, translate, or trash content by language: (Step 1) ALWAYS call list_languages first to inspect active site languages and resolve the user\'s requested language into its exact configured "code" field (never guess language codes; if the language is not configured on the site, inform the user). (Step 2) Pass that exact "code" into the lang parameter of list_pages(lang: code), list_posts(lang: code), or find_content(lang: code) — NEVER pass language names into keyword search parameters (search, s, query). (Step 3) Execute follow-up actions (such as trash_post(post_id)) using the exact IDs returned from Step 2.',
			'DYNAMIC URL RULE (STRICT): NEVER use absolute URLs containing the site domain or protocol (e.g., "http://trusted-union.local/...", "https://domain.com/...") for internal images, media, or links in HTML or CSS. ALWAYS use Dynamic Root-Relative URLs starting with "/" (e.g., "/wp-content/uploads/2026/10/hero.webp", "/course-001/", "/ja/course-001-ja/").',
			'IMAGE & MEDIA WORKFLOW (SRP TOOL CHAINING): When the user sends an image or asks to insert/replace an image on a page or post: (Step 1) Identify the new image attachment_id from the "[Telegram Media Uploaded...]" context, recent chat history, or by calling list_media({ source: "telegram" }). (Step 2) Find the target post_id using find_content or list_pages. (Step 3) Call list_page_images({ post_id }) to inspect all images on the page in DOM order (index, alt, src, context_snippet) and determine the exact target_index matching the user\'s request (e.g., "ảnh thứ 2" -> target_index=1, "ảnh hero" -> index inside Hero section). (Step 4) Call replace_page_image({ post_id, target_index, new_attachment_id, mobile_size }) to automatically generate responsive <picture> / <img> tags with Dynamic Root-Relative URLs. By default use mobile_size="auto"; if the user asks to use the desktop/original quality image on mobile, pass mobile_size="full" or mobile_size="large".',
		);

		if ( class_exists( 'Align_Agent_Seo_Adapters' ) ) {
			$lines[] = Align_Agent_Seo_Adapters::get_system_prompt_line();
		}

		if ( class_exists( 'Align_Language_Manager' ) ) {
			$langs    = Align_Language_Manager::get_languages();
			$def_lang = Align_Language_Manager::get_default_lang();
			$def_name = $def_lang;
			$active_parts = array();

			foreach ( (array) $langs as $k => $l ) {
				$code   = is_array( $l ) && ! empty( $l['code'] ) ? (string) $l['code'] : (string) $k;
				$name   = is_array( $l ) && ! empty( $l['name'] ) ? (string) $l['name'] : $code;
				$is_def = ( $code === $def_lang ) || ( is_array( $l ) && ! empty( $l['is_default'] ) );
				if ( $is_def ) {
					$def_name       = $name;
					$active_parts[] = sprintf( '%s (%s - default)', $code, $name );
				} else {
					$active_parts[] = sprintf( '%s (%s)', $code, $name );
				}
			}

			$lines[] = sprintf(
				'Site default/root language: %s (%s). Active site languages: %s.',
				$def_lang,
				$def_name,
				implode( ', ', $active_parts )
			);
			$lines[] = 'LANGUAGE RULE: Always reply to the user in the language they used in chat. However, when creating or generating website content (create_page, create_post, etc.), ALWAYS write and translate the page title, slug, and HTML content into the site\'s default language (or the specific language explicitly requested by the user), even if the user\'s chat prompt is in Vietnamese or another language.';
			if ( count( $langs ) > 1 ) {
				$lines[] = 'MULTI-LANGUAGE TRANSLATION WORKFLOW: (1) Read the source page/post via get_page (or get_post) to inspect its original title and html. (2) Call create_translation and immediately pass source_id, target_lang, translated title (in target_lang without [LANG] suffix), and translated html (translating all visible human-readable text into target_lang while preserving 100% of HTML tags, CSS classes, IDs, and SEO hierarchy; URL slug stays SEO-friendly ASCII). (3) If create_translation returns translation_completed: false, you MUST immediately call save_page(post_id, title, html) in the same turn to finish translating the title and HTML before replying to the user. Never leave a [LANG] suffix or untranslated source text in a translation!';
			}
		}

		if ( 'telegram' === Align_Agent_Conversation::get_channel() ) {
			$lines[] = 'Telegram reply rules: write at most 2 sentences in your final reply. Keep each sentence under 20 words. Do not repeat the list of changes because the server sends an automated report.';
		}

		if ( '' !== $css_vars_hint ) {
			$lines[] = $css_vars_hint;
		}

		if ( class_exists( 'Align_Agent_Prompt_Rules' ) ) {
			$lines = array_merge( $lines, Align_Agent_Prompt_Rules::get_lines() );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Run a single step of the client-driven agent loop.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $message Optional new user message (empty on continuation turns).
	 * @param int    $turn    Current continuation turn index (0 to MAX_CONTINUE_TURNS).
	 * @return array{status: string, conversation_id: string, reply: string, turn: int, tool_events: array, pending_approvals: array, message?: string}
	 */
	public static function run_step( int $user_id, string $message = '', int $turn = 0 ): array {
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		$clean_message = trim( $message );
		$turn          = max( 0, $turn );

		if ( '' !== $clean_message ) {
			$state = Align_Agent_Conversation::add_user_message( $user_id, $clean_message );
			$turn  = 0;
		} else {
			$state = Align_Agent_Conversation::get_state( $user_id );
		}

		if ( empty( $state['messages'] ) ) {
			return array(
				'status'            => 'error',
				'conversation_id'   => $state['conversation_id'],
				'reply'             => '',
				'turn'              => $turn,
				'tool_events'       => array(),
				'pending_approvals' => array(),
				'message'           => __( 'Cannot run chat step on an empty conversation.', 'align-agent' ),
			);
		}

		if ( $turn >= self::MAX_CONTINUE_TURNS ) {
			error_log( sprintf( '[Align Agent] Turn limit reached | user: %d | turns: %d | conversation: %s', $user_id, $turn, $state['conversation_id'] ) );
			$limit_note = sprintf(
				__( 'Reached the maximum limit of %d automatic tool turns. The task may not be finished yet. Click Continue or send a new instruction to proceed.', 'align-agent' ),
				self::MAX_CONTINUE_TURNS
			);
			$progress = class_exists( 'Align_Agent_Run_Report' ) ? trim( Align_Agent_Run_Report::render_plain() ) : '';
			if ( '' !== $progress && 'telegram' !== Align_Agent_Conversation::get_channel() ) {
				$limit_note = $progress . "\n\n" . $limit_note;
			}
			Align_Agent_Conversation::add_assistant_message( $user_id, $limit_note );
			return array(
				'status'            => 'done',
				'limit_reached'     => true,
				'conversation_id'   => $state['conversation_id'],
				'reply'             => $limit_note,
				'turn'              => $turn,
				'tool_events'       => array(),
				'pending_approvals' => array(),
			);
		}

		$llm_messages = array_merge(
			array(
				array(
					'role'    => 'system',
					'content' => self::build_system_prompt(),
				),
			),
			$state['messages']
		);

		$tools_schema = Align_Agent_Tool_Registry::get_openai_tools_schema();
		$t0           = microtime( true );

		$is_telegram = 'telegram' === Align_Agent_Conversation::get_channel();

		$ai_res = Align_Gateway_AI::chat_completion(
			$llm_messages,
			array(
				'tools'        => $tools_schema,
				'tool_choice'  => ! empty( $tools_schema ) ? 'auto' : null,
				'temperature'  => 0.2,
				'stream'       => true,
				'idle_timeout' => 45,
				'timeout'      => $is_telegram ? 300 : 90,
			)
		);

		$llm_ms     = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		$tool_calls = isset( $ai_res['tool_calls'] ) && is_array( $ai_res['tool_calls'] ) ? $ai_res['tool_calls'] : array();
		$tc_count   = count( $tool_calls );

		error_log( '[Align Agent] Chat step | user: ' . $user_id . ' | tool_calls: ' . $tc_count . ' | llm_ms: ' . $llm_ms );

		if ( ! $ai_res['success'] ) {
			return array(
				'status'            => 'error',
				'conversation_id'   => $state['conversation_id'],
				'reply'             => '',
				'turn'              => $turn,
				'tool_events'       => array(),
				'pending_approvals' => array(),
				'message'           => $ai_res['message'],
			);
		}

		$assistant_text = isset( $ai_res['content'] ) ? trim( (string) $ai_res['content'] ) : '';

		// Final text response with no tool calls.
		if ( 0 === $tc_count ) {
			if ( '' === $assistant_text ) {
				$assistant_text = __( 'Task completed.', 'align-agent' );
			}
			Align_Agent_Conversation::add_assistant_message( $user_id, $assistant_text );

			return array(
				'status'            => 'done',
				'conversation_id'   => $state['conversation_id'],
				'reply'             => $assistant_text,
				'turn'              => $turn,
				'tool_events'       => array(),
				'pending_approvals' => array(),
			);
		}

		// Record the assistant message with its tool_calls before processing tools.
		Align_Agent_Conversation::add_assistant_message( $user_id, $assistant_text, $tool_calls );

		$tool_events       = array();
		$pending_approvals = array();

		foreach ( $tool_calls as $tc ) {
			$tc_id   = isset( $tc['id'] ) ? (string) $tc['id'] : 'call_' . wp_generate_password( 8, false, false );
			$fn_name = isset( $tc['function']['name'] ) ? sanitize_key( (string) $tc['function']['name'] ) : '';
			$raw_arg = isset( $tc['function']['arguments'] ) ? (string) $tc['function']['arguments'] : '{}';
			$args    = self::parse_tool_arguments( $raw_arg, $fn_name );

			// Validate required parameters from tool registry schema.
			$tool_def = Align_Agent_Tool_Registry::get_tool( $fn_name );

			if ( ! empty( $tool_def['hidden'] ) ) {
				$err_payload = array(
					'success' => false,
					'error'   => 'tool_not_found',
					'message' => sprintf( "Tool '%s' is internal and cannot be called directly.", $fn_name ),
				);
				Align_Agent_Conversation::add_tool_message( $user_id, $tc_id, $fn_name, $err_payload );
				$tool_events[] = array(
					'tool_call_id' => $tc_id,
					'tool_name'    => $fn_name,
					'type'         => Align_Agent_Tool_Registry::is_write_tool( $fn_name ) ? 'write' : 'read',
					'success'      => false,
				);
				continue;
			}

			$required = ( isset( $tool_def['parameters']['required'] ) && is_array( $tool_def['parameters']['required'] ) )
				? $tool_def['parameters']['required']
				: array();
			$missing  = array();
			foreach ( $required as $req_field ) {
				if ( ! array_key_exists( $req_field, $args ) || null === $args[ $req_field ] || ( is_string( $args[ $req_field ] ) && '' === trim( $args[ $req_field ] ) ) ) {
					$missing[] = $req_field;
				} elseif ( 'post_id' === $req_field && (int) $args[ $req_field ] <= 0 ) {
					$missing[] = $req_field;
				}
			}

			if ( ! empty( $missing ) ) {
				$err_payload = array(
					'success' => false,
					'error'   => 'invalid_arguments',
					'message' => 'Missing required parameter(s): ' . implode( ', ', $missing ) . '. Re-send the tool call with valid JSON arguments.',
				);
				Align_Agent_Conversation::add_tool_message( $user_id, $tc_id, $fn_name, $err_payload );
				$tool_events[] = array(
					'tool_call_id' => $tc_id,
					'tool_name'    => $fn_name,
					'type'         => Align_Agent_Tool_Registry::is_write_tool( $fn_name ) ? 'write' : 'read',
					'success'      => false,
				);
				continue;
			}

			// Guard: save_template and save_reuse_block require at least one content field.
			if ( in_array( $fn_name, array( 'save_template', 'save_reuse_block' ), true ) ) {
				$has_content = ( isset( $args['html'] ) && '' !== trim( (string) $args['html'] ) )
					|| ( isset( $args['css'] ) && '' !== trim( (string) $args['css'] ) )
					|| ( isset( $args['js'] ) && '' !== trim( (string) $args['js'] ) )
					|| ( ! empty( $args['title'] ) );

				if ( ! $has_content ) {
					$err_payload = array(
						'success' => false,
						'error'   => 'nothing_to_update',
						'message' => 'No fields provided to update (must provide at least one of html, css, js, title).',
					);
					Align_Agent_Conversation::add_tool_message( $user_id, $tc_id, $fn_name, $err_payload );
					$tool_events[] = array(
						'tool_call_id' => $tc_id,
						'tool_name'    => $fn_name,
						'type'         => 'write',
						'success'      => false,
					);
					continue;
				}
			}

			if ( Align_Agent_Tool_Registry::is_write_tool( $fn_name ) ) {
				$decision = class_exists( 'Align_Agent_Approval_Policy' )
					? Align_Agent_Approval_Policy::classify( $fn_name, $args )
					: 'block';

				error_log( sprintf( '[Align Agent] Policy | %s | %s', $fn_name, $decision ) );

				if ( 'block' === $decision || 'approve' === $decision ) {
					$summary    = Align_Agent_Tool_Registry::summarize_write_action( $fn_name, $args );
					$pending_id = Align_Agent_Conversation::queue_pending_write( $user_id, $tc_id, $fn_name, $args, $summary );

					error_log( '[Align Agent] Tool write queued | tool: ' . $fn_name . ' | pending_id: ' . $pending_id );

					if ( class_exists( 'Align_Agent_Run_Report' ) ) {
						Align_Agent_Run_Report::record( 'needs_approval', array(
							'tool_name' => $fn_name,
							'summary'   => $summary,
						) );
					}

					$pending_approvals[] = array(
						'pending_id'   => $pending_id,
						'tool_call_id' => $tc_id,
						'tool_name'    => $fn_name,
						'summary'      => $summary,
					);
				} else {
					// Autonomous execution ('auto') via Write_Executor with change set snapshot.
					$channel     = Align_Agent_Conversation::get_channel();
					$exec_result = class_exists( 'Align_Agent_Write_Executor' )
						? Align_Agent_Write_Executor::run( $fn_name, $args, $user_id, $channel )
						: Align_Agent_Tool_Registry::execute( $fn_name, $args );

					$ui_card = ! empty( $exec_result['ui_card'] ) && is_array( $exec_result['ui_card'] ) ? $exec_result['ui_card'] : array();
					Align_Agent_Conversation::add_tool_message( $user_id, $tc_id, $fn_name, $exec_result, $ui_card );

					$tool_events[] = array(
						'tool_call_id' => $tc_id,
						'tool_name'    => $fn_name,
						'type'         => 'write',
						'success'      => ! empty( $exec_result['success'] ),
					);
				}
			} else {
				$result = Align_Agent_Tool_Registry::execute( $fn_name, $args );
				Align_Agent_Conversation::add_tool_message( $user_id, $tc_id, $fn_name, $result );

				$tool_events[] = array(
					'tool_call_id' => $tc_id,
					'tool_name'    => $fn_name,
					'type'         => 'read',
					'success'      => ! empty( $result['success'] ),
				);
			}
		}

		$next_status = ! empty( $pending_approvals ) ? 'needs_approval' : 'continue';

		return array(
			'status'            => $next_status,
			'conversation_id'   => $state['conversation_id'],
			'reply'             => $assistant_text,
			'turn'              => $turn + 1,
			'tool_events'       => $tool_events,
			'pending_approvals' => $pending_approvals,
		);
	}

	/**
	 * Parse and sanitize tool call arguments, unwrapping _raw wrappers and repairing invalid JSON escapes.
	 *
	 * @param string $raw_arg Tool call arguments string from OpenAI payload.
	 * @param string $fn_name Optional tool function name for observability logging.
	 * @return array
	 */
	public static function parse_tool_arguments( string $raw_arg, string $fn_name = '' ): array {
		$decoded = json_decode( $raw_arg, true );
		$has_raw = false;

		if ( is_array( $decoded ) && ! isset( $decoded['_raw'] ) ) {
			return $decoded;
		}

		if ( is_array( $decoded ) && isset( $decoded['_raw'] ) && is_string( $decoded['_raw'] ) ) {
			$has_raw = true;
			$str     = $decoded['_raw'];
		} else {
			$str = $raw_arg;
		}

		// Attempt direct json_decode on unwrapped string.
		$res = json_decode( $str, true );
		if ( is_array( $res ) ) {
			if ( $has_raw ) {
				error_log( '[Align Agent] Tool args parse | tool: ' . $fn_name . ' | raw_wrapper: yes | length: ' . strlen( $raw_arg ) . ' | json_error: none | head: ' . substr( $raw_arg, 0, 200 ) );
			}
			return $res;
		}

		// Attempt repair of invalid single backslash escapes (e.g. CSS \2212 or unescaped characters).
		$repaired = preg_replace( '/\\\\(?!["\\\\\/bfnrt]|u[0-9a-fA-F]{4})/', '\\\\\\\\', $str );
		if ( is_string( $repaired ) ) {
			$res = json_decode( $repaired, true );
			if ( is_array( $res ) ) {
				error_log( '[Align Agent] Tool args parse | tool: ' . $fn_name . ' | raw_wrapper: ' . ( $has_raw ? 'yes' : 'no' ) . ' | length: ' . strlen( $raw_arg ) . ' | json_error: repaired_backslashes | head: ' . substr( $raw_arg, 0, 200 ) );
				return $res;
			}
		}

		// Failed parsing completely.
		error_log( '[Align Agent] Tool args parse | tool: ' . $fn_name . ' | raw_wrapper: ' . ( $has_raw ? 'yes' : 'no' ) . ' | length: ' . strlen( $raw_arg ) . ' | json_error: ' . json_last_error_msg() . ' | head: ' . substr( $raw_arg, 0, 200 ) );
		return array();
	}
}
