<?php
/**
 * Align Theme Core Engine - Local Agent Workspace CLI Core Controller
 *
 * Provides bidirectional synchronization between WordPress Database (HTML SOT),
 * physical disk assets (CSS/JS SOT), and local agent scratch workspaces.
 *
 * @package Align
 */

if ( php_sapi_name() !== 'cli' ) {
	die( "Direct web access is strictly prohibited.\n" );
}

// 1. Locate and bootstrap WordPress environment
$current_dir = __DIR__;
$wp_root     = dirname( dirname( dirname( dirname( $current_dir ) ) ) );
if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
	$wp_root = dirname( $wp_root );
}

if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "[ERROR] Cannot locate wp-load.php at: {$wp_root}\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
require_once $wp_root . '/wp-load.php';

if ( ! class_exists( 'Align_Builder_Persistence_Service' ) ) {
	fwrite( STDERR, "[ERROR] Align_Builder_Persistence_Service class not loaded.\n" );
	exit( 1 );
}

// 2. Command Line Arguments Parsing
$cmd    = $argv[1] ?? 'help';
$target = $argv[2] ?? '';

$themes_root = dirname( dirname( $current_dir ) );
$scratch_base = $themes_root . '/.agent-scratch';

/**
 * Resolve target post object by numeric ID or slug.
 *
 * @param string $target Target slug or post ID.
 * @return WP_Post|null Found post object or null.
 */
function agent_resolve_post( string $target ): ?WP_Post {
	global $wpdb;
	if ( empty( $target ) ) return null;

	if ( is_numeric( $target ) ) {
		return get_post( (int) $target );
	}

	$post_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('page', 'wp_block', 'post', 'wp_template') ORDER BY ID ASC LIMIT 1",
			sanitize_title( $target )
		)
	);

	return $post_id ? get_post( (int) $post_id ) : null;
}

/**
 * Extract clean HTML body from post content.
 *
 * @param string $raw_content Raw post content with Gutenberg block wrapper.
 * @return string Clean HTML body without block comments.
 */
function agent_extract_clean_html( string $raw_content ): string {
	$content = $raw_content;
	if ( preg_match( '/<!-- wp:align\/custom-html[^>]*>([\s\S]*?)<!-- \/wp:align\/custom-html -->/is', $content, $m ) ) {
		$content = trim( $m[1] );
	}
	// Strip inline style and script tags if legacy content contains them
	$clean = preg_replace( array( '/<style\b[^>]*>[\s\S]*?<\/style>/is', '/<script\b[^>]*>[\s\S]*?<\/script>/is' ), '', $content );
	return trim( (string) $clean );
}

// 3. Command Router
switch ( $cmd ) {
	case 'pull':
		if ( empty( $target ) ) {
			fwrite( STDERR, "Usage: agent-page pull <slug|id>\n" );
			exit( 1 );
		}

		$post = agent_resolve_post( $target );
		if ( ! $post ) {
			fwrite( STDERR, "[ERROR] Post not found for target: {$target}\n" );
			exit( 1 );
		}

		$slug        = $post->post_name ?: (string) $post->ID;
		$target_dir  = $scratch_base . '/' . $slug;
		$child_dir   = $themes_root . '/align-child';
		$assets_dir  = $child_dir . '/assets/builder';

		if ( ! is_dir( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$clean_html = ( 'wp_template' === $post->post_type )
			? $post->post_content
			: agent_extract_clean_html( $post->post_content );

		$css_file = $assets_dir . '/style-page-' . $slug . '.css';
		$js_file  = $assets_dir . '/script-page-' . $slug . '.js';

		$css = file_exists( $css_file ) ? (string) file_get_contents( $css_file ) : '';
		$js  = file_exists( $js_file ) ? (string) file_get_contents( $js_file ) : '';

		// If external files are empty, check if post_content had inline styles
		if ( empty( $css ) && preg_match( '/<style\b[^>]*>([\s\S]*?)<\/style>/is', $post->post_content, $cm ) ) {
			$css = trim( $cm[1] );
		}
		if ( empty( $js ) && preg_match( '/<script\b[^>]*>([\s\S]*?)<\/script>/is', $post->post_content, $jm ) ) {
			$js = trim( $jm[1] );
		}

		// Write scratch workspace files
		file_put_contents( $target_dir . '/content.html', $clean_html );
		file_put_contents( $target_dir . '/style.css', $css );
		file_put_contents( $target_dir . '/script.js', $js );

		// Create auto-backup record for safe rollback
		$backup = array(
			'post_id'          => $post->ID,
			'slug'             => $slug,
			'title'            => $post->post_title,
			'post_status'      => $post->post_status,
			'post_modified'    => $post->post_modified,
			'raw_post_content' => $post->post_content,
			'html'             => $clean_html,
			'css'              => $css,
			'js'               => $js,
			'pulled_at'        => date( 'c' ),
		);
		file_put_contents( $target_dir . '/backup.json', wp_json_encode( $backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

		echo "[SUCCESS] Pulled Page #{$post->ID} ('{$slug}') into workspace: .agent-scratch/{$slug}/\n";
		echo "  ├── content.html (" . strlen( $clean_html ) . " bytes)\n";
		echo "  ├── style.css    (" . strlen( $css ) . " bytes)\n";
		echo "  ├── script.js    (" . strlen( $js ) . " bytes)\n";
		echo "  └── backup.json  (Auto-backup for rollback guard)\n";
		echo "\nLive Preview URL: " . get_permalink( $post->ID ) . "\n";
		break;

	case 'push':
		if ( empty( $target ) ) {
			fwrite( STDERR, "Usage: agent-page push <slug|id>\n" );
			exit( 1 );
		}

		$post = agent_resolve_post( $target );
		if ( ! $post ) {
			fwrite( STDERR, "[ERROR] Post not found for target: {$target}\n" );
			exit( 1 );
		}

		$slug       = $post->post_name ?: (string) $post->ID;
		$target_dir = $scratch_base . '/' . $slug;
		if ( ! is_dir( $target_dir ) && is_dir( $scratch_base . '/' . $target ) ) {
			$target_dir = $scratch_base . '/' . $target;
		}
		if ( ! is_dir( $target_dir ) && is_dir( $scratch_base . '/' . $post->ID ) ) {
			$target_dir = $scratch_base . '/' . $post->ID;
		}

		if ( ! is_dir( $target_dir ) || ! file_exists( $target_dir . '/content.html' ) ) {
			fwrite( STDERR, "[ERROR] Workspace directory not found: .agent-scratch/{$slug}/\n" );
			exit( 1 );
		}

		$html = (string) file_get_contents( $target_dir . '/content.html' );
		$css  = file_exists( $target_dir . '/style.css' ) ? (string) file_get_contents( $target_dir . '/style.css' ) : '';
		$js   = file_exists( $target_dir . '/script.js' ) ? (string) file_get_contents( $target_dir . '/script.js' ) : '';

		if ( 'wp_template' === $post->post_type ) {
			kses_remove_filters();
			wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( $html ),
			) );
			kses_init_filters();
			clean_post_cache( $post->ID );

			$tpl_file = $themes_root . '/align-child/templates/' . $slug . '.html';
			if ( is_dir( dirname( $tpl_file ) ) ) {
				file_put_contents( $tpl_file, $html );
			}

			echo "[SUCCESS] Persisted Template #{$post->ID} ('{$slug}') to Database and disk!\n";
			echo "  ├── Database: wp_posts.post_content updated (" . strlen( $html ) . " bytes HTML)\n";
			echo "  ├── Disk: align-child/templates/{$slug}.html updated\n";
			echo "  └── Cache: Flushed template cache\n";
			break;
		}

		$result = Align_Builder_Persistence_Service::persist_page(
			$post->ID,
			$html,
			$css,
			$js,
			null,
			$post->post_status
		);

		if ( 'error' === ( $result['status'] ?? '' ) ) {
			fwrite( STDERR, "[ERROR] Persistence failed: " . ( $result['message'] ?? 'Unknown error' ) . "\n" );
			exit( 1 );
		}

		clean_post_cache( $post->ID );

		echo "[SUCCESS] Persisted Page #{$post->ID} ('{$slug}') successfully!\n";
		echo "  ├── Database: wp_posts.post_content updated (" . strlen( $html ) . " bytes HTML)\n";
		echo "  ├── Physical CSS: align-child/assets/builder/style-page-{$slug}.css (" . strlen( $css ) . " bytes)\n";
		echo "  ├── Physical JS:  align-child/assets/builder/script-page-{$slug}.js (" . strlen( $js ) . " bytes)\n";
		echo "  └── Cache: Flushed post cache\n";
		echo "\nVerified URL: " . get_permalink( $post->ID ) . "\n";
		break;

	case 'rollback':
		if ( empty( $target ) ) {
			fwrite( STDERR, "Usage: agent-page rollback <slug|id>\n" );
			exit( 1 );
		}

		$post = agent_resolve_post( $target );
		$slug = $post ? ( $post->post_name ?: (string) $post->ID ) : $target;
		$target_dir = $scratch_base . '/' . $slug;
		$backup_file = $target_dir . '/backup.json';

		if ( ! file_exists( $backup_file ) ) {
			fwrite( STDERR, "[ERROR] Backup record not found at: {$backup_file}\n" );
			exit( 1 );
		}

		$backup = json_decode( (string) file_get_contents( $backup_file ), true );
		if ( empty( $backup['post_id'] ) ) {
			fwrite( STDERR, "[ERROR] Invalid backup format in {$backup_file}\n" );
			exit( 1 );
		}

		$result = Align_Builder_Persistence_Service::persist_page(
			(int) $backup['post_id'],
			$backup['html'] ?? '',
			$backup['css'] ?? '',
			$backup['js'] ?? '',
			null,
			$backup['post_status'] ?? ''
		);

		if ( 'error' === ( $result['status'] ?? '' ) ) {
			fwrite( STDERR, "[ERROR] Rollback failed: " . ( $result['message'] ?? 'Unknown error' ) . "\n" );
			exit( 1 );
		}

		clean_post_cache( (int) $backup['post_id'] );

		// Restore scratch files to backup state
		file_put_contents( $target_dir . '/content.html', $backup['html'] ?? '' );
		file_put_contents( $target_dir . '/style.css', $backup['css'] ?? '' );
		file_put_contents( $target_dir . '/script.js', $backup['js'] ?? '' );

		echo "[SUCCESS] Rollback completed for Page #{$backup['post_id']} ('{$slug}') from backup {$backup['pulled_at']}.\n";
		break;

	case 'cleanup':
		if ( empty( $target ) ) {
			fwrite( STDERR, "Usage: agent-page cleanup <slug|id>\n" );
			exit( 1 );
		}

		$post = agent_resolve_post( $target );
		$slug = $post ? ( $post->post_name ?: (string) $post->ID ) : $target;
		$target_dir = $scratch_base . '/' . $slug;
		if ( ! is_dir( $target_dir ) && is_dir( $scratch_base . '/' . $target ) ) {
			$target_dir = $scratch_base . '/' . $target;
		}
		if ( ! is_dir( $target_dir ) && $post && is_dir( $scratch_base . '/' . $post->ID ) ) {
			$target_dir = $scratch_base . '/' . $post->ID;
		}

		if ( is_dir( $target_dir ) ) {
			$files = glob( $target_dir . '/*' );
			foreach ( $files as $f ) {
				@unlink( $f );
			}
			@rmdir( $target_dir );
			echo "[SUCCESS] Cleaned scratch workspace: .agent-scratch/{$slug}/\n";
		} else {
			echo "[INFO] No workspace directory found to clean for: {$slug}\n";
		}
		break;

	case 'preview':
		if ( empty( $target ) ) {
			fwrite( STDERR, "Usage: agent-page preview <slug|id>\n" );
			exit( 1 );
		}

		$post = agent_resolve_post( $target );
		if ( ! $post ) {
			fwrite( STDERR, "[ERROR] Post not found for: {$target}\n" );
			exit( 1 );
		}

		$url = get_permalink( $post->ID );
		$template = get_post_meta( $post->ID, '_wp_page_template', true );
		echo "Live URL: {$url}\n";
		echo "Post ID:  {$post->ID}\n";
		echo "Status:   {$post->post_status}\n";
		echo "Template: " . ( ! empty( $template ) ? $template : 'default' ) . "\n";
		break;

	case 'list':
		$ptype = ! empty( $target ) ? $target : 'page';
		$posts = get_posts( array( 'post_type' => $ptype, 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 50, 'orderby' => 'ID', 'order' => 'ASC' ) );
		echo sprintf( "%-6s | %-30s | %-10s | %-20s | %s\n", "ID", "Slug", "Status", "Template", "Title" );
		echo str_repeat( "-", 95 ) . "\n";
		foreach ( $posts as $p ) {
			$tmpl = get_post_meta( $p->ID, '_wp_page_template', true ) ?: 'default';
			echo sprintf( "%-6d | %-30s | %-10s | %-20s | %s\n", $p->ID, substr( $p->post_name, 0, 30 ), $p->post_status, substr( $tmpl, 0, 20 ), $p->post_title );
		}
		break;

	case 'template-list':
		$templates = get_block_templates( array(), 'wp_template' );
		echo sprintf( "%-6s | %-28s | %-25s | %-10s | %s\n", "WP_ID", "Slug", "Title", "Source", "PostTypes" );
		echo str_repeat( "-", 85 ) . "\n";
		foreach ( $templates as $t ) {
			$post_types = ! empty( $t->post_types ) ? implode( ',', $t->post_types ) : 'all';
			echo sprintf( "%-6s | %-28s | %-25s | %-10s | %s\n",
				$t->wp_id ?: '-',
				substr( $t->slug, 0, 28 ),
				substr( $t->title, 0, 25 ),
				$t->source,
				$post_types
			);
		}
		break;

	case 'template-create':
		$slug  = sanitize_title( $target );
		$title = $argv[3] ?? '';
		$types = ! empty( $argv[4] ) ? explode( ',', $argv[4] ) : array( 'post' );

		if ( empty( $slug ) || empty( $title ) ) {
			fwrite( STDERR, "Usage: agent-page template-create <slug> \"<title>\" [post,page]\n" );
			exit( 1 );
		}

		$existing = get_page_by_path( $slug, OBJECT, 'wp_template' );
		if ( $existing ) {
			fwrite( STDERR, "[ERROR] Template '{$slug}' already exists in DB (ID: {$existing->ID}).\n" );
			exit( 1 );
		}

		$default_content = "<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n\n<!-- wp:post-content /-->\n\n<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->\n";

		$template_id = wp_insert_post( array(
			'post_type'    => 'wp_template',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $default_content,
			'post_status'  => 'publish',
		), true );

		if ( is_wp_error( $template_id ) ) {
			fwrite( STDERR, "[ERROR] Failed to insert template into DB: " . $template_id->get_error_message() . "\n" );
			exit( 1 );
		}

		wp_set_post_terms( $template_id, get_stylesheet(), 'wp_theme' );

		$theme_json_file = $themes_root . '/align-child/theme.json';
		if ( file_exists( $theme_json_file ) ) {
			$tj = json_decode( (string) file_get_contents( $theme_json_file ), true );
			if ( ! isset( $tj['customTemplates'] ) || ! is_array( $tj['customTemplates'] ) ) {
				$tj['customTemplates'] = array();
			}
			$exists_in_json = false;
			foreach ( $tj['customTemplates'] as $ct ) {
				if ( ( $ct['name'] ?? '' ) === $slug ) {
					$exists_in_json = true;
					break;
				}
			}
			if ( ! $exists_in_json ) {
				$tj['customTemplates'][] = array(
					'name'      => $slug,
					'title'     => $title,
					'postTypes' => array_values( array_unique( $types ) ),
				);
				file_put_contents( $theme_json_file, json_encode( $tj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		}

		clean_post_cache( $template_id );
		echo "[SUCCESS] Created CMS Template '{$slug}' (ID: {$template_id}) registered for: " . implode( ', ', $types ) . "\n";
		echo "Live in CMS: Appearance -> Editor -> Templates ('{$title}')\n";
		break;

	case 'template-assign':
		$post_id_or_slug = $target;
		$template_slug   = $argv[3] ?? '';

		if ( empty( $post_id_or_slug ) || empty( $template_slug ) ) {
			fwrite( STDERR, "Usage: agent-page template-assign <post_id|slug> <template_slug|default>\n" );
			exit( 1 );
		}

		$post = agent_resolve_post( $post_id_or_slug );
		if ( ! $post ) {
			fwrite( STDERR, "[ERROR] Target post not found: {$post_id_or_slug}\n" );
			exit( 1 );
		}

		update_post_meta( $post->ID, '_wp_page_template', $template_slug );
		clean_post_cache( $post->ID );
		echo "[SUCCESS] Assigned template '{$template_slug}' to Post #{$post->ID} ('{$post->post_title}').\n";
		break;

	case 'template-delete':
		$slug = sanitize_title( $target );
		if ( empty( $slug ) ) {
			fwrite( STDERR, "Usage: agent-page template-delete <slug>\n" );
			exit( 1 );
		}

		$existing = get_page_by_path( $slug, OBJECT, 'wp_template' );
		if ( ! $existing ) {
			fwrite( STDERR, "[ERROR] Template '{$slug}' not found in DB.\n" );
			exit( 1 );
		}

		wp_delete_post( $existing->ID, true );

		$theme_json_file = $themes_root . '/align-child/theme.json';
		if ( file_exists( $theme_json_file ) ) {
			$tj = json_decode( (string) file_get_contents( $theme_json_file ), true );
			if ( ! empty( $tj['customTemplates'] ) ) {
				$tj['customTemplates'] = array_values( array_filter( $tj['customTemplates'], function( $ct ) use ( $slug ) {
					return ( $ct['name'] ?? '' ) !== $slug;
				} ) );
				file_put_contents( $theme_json_file, json_encode( $tj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			}
		}

		echo "[SUCCESS] Deleted CMS Template '{$slug}' (ID: {$existing->ID}) from DB and theme.json.\n";
		break;

	case 'publish':
		if ( empty( $target ) ) {
			fwrite( STDERR, "Usage: agent-page publish <slug|id>\n" );
			exit( 1 );
		}
		$post = agent_resolve_post( $target );
		if ( ! $post ) {
			fwrite( STDERR, "[ERROR] Post not found: {$target}\n" );
			exit( 1 );
		}
		wp_update_post( array(
			'ID'          => $post->ID,
			'post_status' => 'publish',
		) );
		clean_post_cache( $post->ID );
		echo "[SUCCESS] Published Post #{$post->ID} ('{$post->post_title}').\n";
		echo "Live URL: " . get_permalink( $post->ID ) . "\n";
		break;

	case 'help':
	default:
		echo "Align Local Agent Page Workspace CLI\n";
		echo "====================================\n";
		echo "Commands:\n";
		echo "  pull <slug|id>                     Extract HTML from DB and CSS/JS from disk into .agent-scratch/{slug}/\n";
		echo "  push <slug|id>                     Push modified files into DB (HTML SOT) and disk (CSS/JS SOT)\n";
		echo "  preview <slug|id>                  Print live URL for browser verification\n";
		echo "  rollback <slug|id>                 Restore original state from auto-backup\n";
		echo "  cleanup <slug|id>                  Remove scratch workspace directory after verification\n";
		echo "  list                               List available pages\n";
		echo "  template-list                      List all registered block templates in CMS\n";
		echo "  template-create <slug> \"<title>\"   Create new CMS template in DB & register postTypes\n";
		echo "  template-assign <id> <template>    Assign template to specific post or page\n";
		echo "  template-delete <slug>             Delete custom template from DB and theme.json\n";
		break;
}

