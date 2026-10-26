<?php
/**
 * Plugin Name: Align Agent
 * Plugin URI: https://alignvn.cloud
 * Description: Autonomous AI Agent & Remote Bot Gateway for WordPress & Align Theme.
 * Version: 1.0.0
 * Author: Align Team
 * Text Domain: align-agent
 *
 * @package Align_Agent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin Constants.
if ( ! defined( 'ALIGN_AGENT_VERSION' ) ) {
	define( 'ALIGN_AGENT_VERSION', '1.0.0' );
}

if ( ! defined( 'ALIGN_AGENT_PLUGIN_DIR' ) ) {
	define( 'ALIGN_AGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ALIGN_AGENT_PLUGIN_URL' ) ) {
	define( 'ALIGN_AGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'ALIGN_AGENT_REST_NS' ) ) {
	define( 'ALIGN_AGENT_REST_NS', 'align-agent/v1' );
}

// 1. Load Authentication & Authorization Controller.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-auth.php';

// 2. Load System & Operations Handler.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-system.php';

// 3. Load Design Context & Raw CSS Handler.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-context.php';

// 4. Load Pages CRUD & Rollback Controller.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-pages.php';

// 5. Load Sandbox Dry-Run Validator.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-validator.php';

// 6. Load Telegram Bot Gateway.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram.php';

// 6b. Load Telegram Pairing Controller (6-digit code flow).
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-pairing.php';

// 6c. Load Telegram Formatter & Safe Chunker.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-format.php';

// 6d. Load Telegram Media Ingestion & Staging.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-media.php';

// 6e. Load Telegram Approval Controller (Inline Keyboards).
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-approval.php';

// 6e. Load Telegram Undo Controller.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-undo.php';

// 6e. Load Telegram Continue Controller.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-continue.php';

// 6e. Load Telegram Process Mutex Lock.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-lock.php';

// 6f. Load Telegram Autonomous Runner.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-telegram-runner.php';

// 7. Load OpenAI-Compatible AI Provider Controller & Streaming Engine.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-ai-stream.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-ai.php';

// 8. Load Autonomous Agent Engine Controller.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-agent.php';

// 9. Load Telegram Webhook Controller.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-webhook.php';

// 10. Load Admin Settings Controller.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-admin.php';

// 11. Load REST API Router & MCP Server.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-mcp.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-gateway-router.php';

// 12. Load Git Updater.
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-git-updater.php';

require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-content-guard.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-patch-engine.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-tool-registry.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-audit-log.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-change-set.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-undo-resolver.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-undo-adapters.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-global-vault.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-vault-gc.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-write-executor.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-undo-service.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-pending-store.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-conversation.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-approval-service.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-approval-policy.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-page-clone.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-run-report.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-working-notes.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-prompt-rules.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/tools/class-agent-tools-loader.php';
Align_Agent_Tools_Loader::load();
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-loop.php';
require_once ALIGN_AGENT_PLUGIN_DIR . 'includes/class-agent-chat-controller.php';

add_action( 'align_agent_cs_gc', array( 'Align_Agent_Change_Set', 'gc' ) );

/**
 * Plugin Activation Handler.
 * Generates an initial secret key if none exists.
 */
function align_agent_activate(): void {
	$current_key = get_option( Align_Gateway_Auth::OPTION_KEY, '' );
	if ( empty( $current_key ) ) {
		$default_key = 'sk-align-' . wp_generate_password( 32, false, false );
		update_option( Align_Gateway_Auth::OPTION_KEY, $default_key );
		error_log( '[Align Agent] Generated initial secret key on plugin activation.' );
	}

	$timestamp = wp_next_scheduled( 'align_agent_cron_poll' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'align_agent_cron_poll' );
	}

	if ( ! wp_next_scheduled( 'align_agent_cs_gc' ) ) {
		wp_schedule_event( time(), 'daily', 'align_agent_cs_gc' );
	}

	if ( ! wp_next_scheduled( 'align_agent_vault_gc' ) ) {
		wp_schedule_event( time(), 'daily', 'align_agent_vault_gc' );
	}

	flush_rewrite_rules( false );
	error_log( '[Align Agent] Plugin activated successfully.' );
}
register_activation_hook( __FILE__, 'align_agent_activate' );

/**
 * Plugin Deactivation Handler.
 */
function align_agent_deactivate(): void {
	$timestamp = wp_next_scheduled( 'align_agent_cron_poll' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'align_agent_cron_poll' );
	}

	$cs_timestamp = wp_next_scheduled( 'align_agent_cs_gc' );
	if ( $cs_timestamp ) {
		wp_unschedule_event( $cs_timestamp, 'align_agent_cs_gc' );
	}

	$vault_timestamp = wp_next_scheduled( 'align_agent_vault_gc' );
	if ( $vault_timestamp ) {
		wp_unschedule_event( $vault_timestamp, 'align_agent_vault_gc' );
	}

	flush_rewrite_rules( false );
	error_log( '[Align Agent] Plugin deactivated.' );
}
register_deactivation_hook( __FILE__, 'align_agent_deactivate' );

/**
 * Boot Align Agent Gateway components on plugins_loaded.
 */
function align_agent_bootstrap(): void {
	Align_Gateway_Auth::init();
	Align_Gateway_Telegram::init();
	Align_Gateway_AI::init();
	Align_Gateway_Admin::init();
	Align_Gateway_Router::init();
	Align_Agent_Git_Updater::init();
	Align_Agent_Global_Vault::init();
	Align_Agent_Vault_Gc::init();
	Align_Agent_Tool_Registry::init();
	Align_Agent_Audit_Log::init();
	Align_Agent_Conversation::init();
	Align_Agent_Tools_Loader::init();
	Align_Agent_Loop::init();
	Align_Agent_Chat_Controller::init();

	$timestamp = wp_next_scheduled( 'align_agent_cron_poll' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'align_agent_cron_poll' );
	}
}
add_action( 'plugins_loaded', 'align_agent_bootstrap' );
