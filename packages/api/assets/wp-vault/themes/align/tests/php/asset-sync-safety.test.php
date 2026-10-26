<?php
/**
 * CLI Test Suite: Asset Synchronization Safety & History Snapshot Verification
 *
 * Verifies non-destructive asset syncing, history snapshots, retention pruning,
 * and restoration behaviors.
 *
 * Run via CLI: php align/tests/php/asset-sync-safety.test.php
 *
 * @package Align
 */

// 1. Define minimal WordPress environment stubs
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'ALIGN_TEST_RUNNER' ) ) {
	define( 'ALIGN_TEST_RUNNER', true );
}

$temp_test_dir = sys_get_temp_dir() . '/align_test_' . uniqid( '', true );
$temp_assets_dir = $temp_test_dir . '/assets/builder/';
mkdir( $temp_assets_dir, 0755, true );

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $str ) {
		return rtrim( (string) $str, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ) {
		return preg_replace( '/[^A-Za-z0-9_\-\.]/', '', (string) $name );
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || @mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'get_stylesheet_directory' ) ) {
	function get_stylesheet_directory() {
		global $temp_test_dir;
		return $temp_test_dir;
	}
}

if ( ! function_exists( 'get_stylesheet_directory_uri' ) ) {
	function get_stylesheet_directory_uri() {
		return 'http://aignrealbuilder.local/wp-content/themes/align-child';
	}
}

if ( ! function_exists( 'get_template_directory' ) ) {
	function get_template_directory() {
		global $temp_test_dir;
		return $temp_test_dir;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $val ) {
		return abs( (int) $val );
	}
}

$mock_user_caps = array();
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		global $mock_user_caps;
		return in_array( $cap, $mock_user_caps, true );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

if ( ! class_exists( 'Align_Agent_Tool_Registry' ) ) {
	class Align_Agent_Tool_Registry {
		public static array $tools = array();
		public static function register( $name, $config ) {
			self::$tools[ $name ] = $config;
		}
	}
}

// 2. Load required Align Builder modules
$base_builder = dirname( dirname( __DIR__ ) ) . '/core/inc/builder/';
require_once $base_builder . 'class-builder-asset-history.php';
require_once $base_builder . 'class-builder-asset-writer.php';
require_once $base_builder . 'class-builder-assets.php';
require_once $base_builder . 'class-builder-asset-sync.php';
require_once $base_builder . 'class-builder-persistence-service.php';

$plugin_tools_file = __DIR__ . '/../../../../../plugins/Align-Agent/includes/tools/content/class-agent-tools-asset-history.php';
if ( file_exists( $plugin_tools_file ) ) {
	require_once $plugin_tools_file;
}

// Configure History service to operate on temp assets directory
Align_Builder_Asset_History::set_custom_assets_dir( $temp_assets_dir );

// 3. Test Runner Infrastructure
$passed = 0;
$failed = 0;

function assert_test( bool $condition, string $title, string $details = '' ): void {
	global $passed, $failed;
	if ( $condition ) {
		echo "✔ PASS: {$title}\n";
		$passed++;
	} else {
		echo "✖ FAIL: {$title}\n";
		if ( '' !== $details ) {
			echo "  Details: {$details}\n";
		}
		$failed++;
	}
}

echo "Running Asset Sync Safety Tests...\n";
echo "Temp directory: {$temp_assets_dir}\n\n";

// =========================================================================
// Case 1: sync_post_assets_from_content without <style> tag does not delete CSS
// =========================================================================
$case1_file = $temp_assets_dir . 'style-page-goi-subscription.css';
$initial_case1_content = "/* Existing valid CSS payload */\n.sub-page { color: #12121c; }";
file_put_contents( $case1_file, $initial_case1_content );

$mock_post = (object) array(
	'ID'           => 52,
	'post_name'    => 'goi-subscription',
	'post_type'    => 'page',
	'post_content' => '<div class="sub-page"><p>Hello world without inline styles</p></div>',
);

Align_Builder_Asset_Sync::sync_post_assets_from_content( 52, $mock_post );

$case1_survived = file_exists( $case1_file );
$case1_intact   = $case1_survived && ( file_get_contents( $case1_file ) === $initial_case1_content );
assert_test(
	$case1_intact,
	'Case 1: sync_post_assets_from_content without <style> tag keeps CSS file byte-identical',
	"File exists: " . ( $case1_survived ? 'yes' : 'no' )
);

// =========================================================================
// Case 2: save_external_assets with explicit empty CSS deletes file and snapshots
// =========================================================================
$case2_file = $temp_assets_dir . 'style-page-test-empty.css';
$case2_content = "/* CSS to be explicitly emptied */\n.test { display: block; }";
file_put_contents( $case2_file, $case2_content );

Align_Builder_Assets::save_external_assets( 'style-page-test-empty', 'script-page-test-empty', '', '' );

$case2_deleted   = ! file_exists( $case2_file );
$case2_snapshots = Align_Builder_Asset_History::list_snapshots( 'style-page-test-empty.css' );
$case2_snap_ok   = ( count( $case2_snapshots ) >= 1 );

assert_test(
	$case2_deleted && $case2_snap_ok,
	'Case 2: save_external_assets with empty CSS deletes file and creates .history snapshot',
	"Deleted: " . ( $case2_deleted ? 'yes' : 'no' ) . " | Snapshots: " . count( $case2_snapshots )
);

// =========================================================================
// Case 3: HTML-only Agent patch path preserves CSS and JS files
// =========================================================================
$case3_css_file = $temp_assets_dir . 'style-page-patch-test.css';
$case3_js_file  = $temp_assets_dir . 'script-page-patch-test.js';
$case3_css_data = "/* Patch test CSS */\n.hero { margin: 0; }";
$case3_js_data  = "/* Patch test JS */\nconsole.log('alive');";
file_put_contents( $case3_css_file, $case3_css_data );
file_put_contents( $case3_js_file, $case3_js_data );

// Simulate non-destructive sync from an HTML-only patch call
Align_Builder_Assets::sync_external_assets( 'style-page-patch-test', 'script-page-patch-test', '', '' );

$case3_css_ok = file_exists( $case3_css_file ) && ( file_get_contents( $case3_css_file ) === $case3_css_data );
$case3_js_ok  = file_exists( $case3_js_file ) && ( file_get_contents( $case3_js_file ) === $case3_js_data );

assert_test(
	$case3_css_ok && $case3_js_ok,
	'Case 3: HTML-only sync preserves existing CSS and JS files on disk',
	"CSS preserved: " . ( $case3_css_ok ? 'yes' : 'no' ) . " | JS preserved: " . ( $case3_js_ok ? 'yes' : 'no' )
);

// =========================================================================
// Case 4: Snapshot prune: 7 distinct overwrites retain exactly 5 newest snapshots
// =========================================================================
$case4_file = $temp_assets_dir . 'style-page-prune-test.css';
file_put_contents( $case4_file, "/* base */" );

for ( $i = 1; $i <= 7; $i++ ) {
	$new_text = "/* distinct version {$i} */\n.v{$i} { padding: {$i}px; }";
	file_put_contents( $case4_file, $new_text );
	// Force unique timestamp / microtime variation to register distinct snapshots
	Align_Builder_Asset_History::snapshot( $case4_file, 'overwrite' );
	usleep( 10000 ); // 10ms delay
}

$case4_snapshots = Align_Builder_Asset_History::list_snapshots( 'style-page-prune-test.css' );
assert_test(
	count( $case4_snapshots ) === 5,
	'Case 4: Snapshot pruning retains exactly 5 newest snapshots after 7 overwrites',
	"Actual snapshot count: " . count( $case4_snapshots )
);

// =========================================================================
// Case 5: restore returns newest snapshot content
// =========================================================================
$case5_file = $temp_assets_dir . 'style-page-restore-test.css';
$version_good = "/* Version Good - 8162 bytes payload */\n.page { background: #fff; }";
$version_bad  = "/* Version Bad - corrupted or empty */";

file_put_contents( $case5_file, $version_good );
Align_Builder_Asset_History::snapshot( $case5_file, 'overwrite' );
usleep( 15000 ); // 15ms

// Overwrite with bad version
file_put_contents( $case5_file, $version_bad );

// Perform restore
$restore_success = Align_Builder_Asset_History::restore( 'style-page-restore-test.css' );
$restored_content = file_exists( $case5_file ) ? file_get_contents( $case5_file ) : '';

assert_test(
	$restore_success && ( $restored_content === $version_good ),
	'Case 5: restore successfully reverts live asset file to newest snapshot content',
	"Restore result: " . ( $restore_success ? 'true' : 'false' ) . " | Matches good version: " . ( $restored_content === $version_good ? 'yes' : 'no' )
);

// =========================================================================
// Case 6: Extension whitelist & Path traversal protection
// =========================================================================
$php_target     = $temp_assets_dir . 'malicious.php';
file_put_contents( $php_target, "<?php phpinfo();" );
$snap_php       = Align_Builder_Asset_History::snapshot( $php_target, 'overwrite' );
$list_traversal = Align_Builder_Asset_History::list_snapshots( '../../../wp-config.php' );
$rest_traversal = Align_Builder_Asset_History::restore( '../../wp-config.php' );
$list_txt       = Align_Builder_Asset_History::list_snapshots( 'readme.txt' );

$case6_passed = ( false === $snap_php ) && empty( $list_traversal ) && ( false === $rest_traversal ) && empty( $list_txt );
assert_test(
	$case6_passed,
	'Case 6: Non-whitelisted extensions (.php, .txt) and path traversal are rejected',
	"snap_php=" . ( $snap_php ? 'true' : 'false' ) . " | list_traversal=" . count( $list_traversal ) . " | rest_traversal=" . ( $rest_traversal ? 'true' : 'false' )
);

// =========================================================================
// Case 7: Information disclosure guard: list_snapshots conceals absolute path
// =========================================================================
$case7_snaps   = Align_Builder_Asset_History::list_snapshots( 'style-page-restore-test.css' );
$has_paths     = false;
$has_filenames = false;
if ( ! empty( $case7_snaps ) ) {
	$first = $case7_snaps[0];
	$has_paths     = isset( $first['path'] );
	$has_filenames = isset( $first['filename'] ) && isset( $first['timestamp'] ) && isset( $first['bytes'] );
}

assert_test(
	( ! $has_paths ) && $has_filenames,
	'Case 7: list_snapshots returns filename, timestamp, bytes and conceals server absolute path',
	"has_paths=" . ( $has_paths ? 'yes' : 'no' ) . " | has_filenames=" . ( $has_filenames ? 'yes' : 'no' )
);

// =========================================================================
// Case 8: Regex filtering ignores malformed filenames in .history
// =========================================================================
$history_dir = $temp_assets_dir . '.history/';
file_put_contents( $history_dir . 'style-page-restore-test.arbitrary-payload.css', '/* corrupted */' );
file_put_contents( $history_dir . 'unrelated.log', 'test' );

$case8_snaps = Align_Builder_Asset_History::list_snapshots( 'style-page-restore-test.css' );
$found_malformed = false;
foreach ( $case8_snaps as $s ) {
	if ( false !== strpos( $s['filename'], 'arbitrary-payload' ) ) {
		$found_malformed = true;
		break;
	}
}

assert_test(
	! $found_malformed,
	'Case 8: list_snapshots ignores malformed or non-snapshot files in .history directory',
	"Found malformed: " . ( $found_malformed ? 'yes' : 'no' )
);

// =========================================================================
// Case 9: .htaccess Apache 2.4 & 2.2 rules
// =========================================================================
$htaccess_path = $history_dir . '.htaccess';
$htaccess_data = file_exists( $htaccess_path ) ? (string) file_get_contents( $htaccess_path ) : '';
$has_mod_authz = ( false !== strpos( $htaccess_data, 'mod_authz_core.c' ) ) && ( false !== strpos( $htaccess_data, 'Require all denied' ) );
$has_legacy    = ( false !== strpos( $htaccess_data, 'Deny from all' ) );

assert_test(
	$has_mod_authz && $has_legacy,
	'Case 9: .htaccess contains both Apache 2.4 (Require all denied) and Apache 2.2 (Deny from all)',
	"Contains Apache 2.4 rules: " . ( $has_mod_authz ? 'yes' : 'no' )
);

// =========================================================================
// Case 10: Align Agent Tools registration & capability verification
// =========================================================================
if ( class_exists( 'Align_Agent_Tools_Asset_History' ) ) {
	Align_Agent_Tools_Asset_History::init();
	$reg_list    = Align_Agent_Tool_Registry::$tools['list_asset_history'] ?? array();
	$reg_restore = Align_Agent_Tool_Registry::$tools['restore_asset_history'] ?? array();

	$caps_ok = ( ( $reg_list['capability'] ?? '' ) === 'edit_theme_options' )
		&& ( ( $reg_restore['capability'] ?? '' ) === 'edit_theme_options' );

	assert_test(
		$caps_ok,
		'Case 10: Align Agent Tools register with edit_theme_options capability',
		"list_cap=" . ( $reg_list['capability'] ?? 'none' ) . " | restore_cap=" . ( $reg_restore['capability'] ?? 'none' )
	);

	// =========================================================================
	// Case 11: Tool callback security checks (forbidden & extension whitelist)
	// =========================================================================
	// 1. Without edit_theme_options -> returns WP_Error('rest_forbidden')
	$mock_user_caps = array();
	$res_list_unauth = Align_Agent_Tools_Asset_History::tool_list_asset_history( array( 'basename' => 'style-page-restore-test.css' ) );
	$res_rest_unauth = Align_Agent_Tools_Asset_History::tool_restore_asset_history( array( 'basename' => 'style-page-restore-test.css' ) );

	$unauth_ok = is_wp_error( $res_list_unauth ) && ( 'rest_forbidden' === $res_list_unauth->get_error_code() )
		&& is_wp_error( $res_rest_unauth ) && ( 'rest_forbidden' === $res_rest_unauth->get_error_code() );

	// 2. With edit_theme_options but invalid extension (.php) -> returns error invalid_extension
	$mock_user_caps = array( 'edit_theme_options' );
	$res_list_badext = Align_Agent_Tools_Asset_History::tool_list_asset_history( array( 'basename' => 'exploit.php' ) );
	$res_rest_badext = Align_Agent_Tools_Asset_History::tool_restore_asset_history( array( 'basename' => 'exploit.php' ) );

	$badext_ok = is_array( $res_list_badext ) && ( 'invalid_extension' === ( $res_list_badext['error'] ?? '' ) )
		&& is_array( $res_rest_badext ) && ( 'invalid_extension' === ( $res_rest_badext['error'] ?? '' ) );

	// 3. With edit_theme_options and valid extension -> succeeds
	$res_list_good = Align_Agent_Tools_Asset_History::tool_list_asset_history( array( 'basename' => 'style-page-restore-test.css' ) );
	$good_ok       = is_array( $res_list_good ) && ( true === ( $res_list_good['success'] ?? false ) );

	assert_test(
		$unauth_ok && $badext_ok && $good_ok,
		'Case 11: Tool callbacks enforce rest_forbidden and extension whitelist (.css/.js)',
		"unauth_ok=" . ( $unauth_ok ? 'yes' : 'no' ) . " | badext_ok=" . ( $badext_ok ? 'yes' : 'no' ) . " | good_ok=" . ( $good_ok ? 'yes' : 'no' )
	);
}

// 4. Cleanup temporary test directory
function recursive_rmdir( string $dir ): void {
	if ( ! is_dir( $dir ) ) return;
	$files = array_diff( scandir( $dir ), array( '.', '..' ) );
	foreach ( $files as $file ) {
		$target = $dir . '/' . $file;
		is_dir( $target ) ? recursive_rmdir( $target ) : @unlink( $target );
	}
	@rmdir( $dir );
}

recursive_rmdir( $temp_test_dir );

// 5. Final report
echo "\nTest Summary: Passed: {$passed} | Failed: {$failed}\n";
exit( $failed > 0 ? 1 : 0 );
