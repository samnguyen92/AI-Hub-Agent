<?php
/**
 * Align Agent - Tools Loader
 *
 * Loads and initializes all chatbox tool classes based on an explicit manifest.
 *
 * @package Align_Agent
 * @subpackage Includes\Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Tools_Loader {

	/**
	 * Explicit manifest of tool files and their corresponding classes.
	 * Order matches original bootstrap sequence.
	 *
	 * @var array<string, string>
	 */
	private const MANIFEST = array(
		'seo/class-agent-seo-adapters.php'          => 'Align_Agent_Seo_Adapters',
		'content/class-agent-tools-pages.php'       => 'Align_Agent_Tools_Pages',
		'content/class-agent-tools-posts.php'       => 'Align_Agent_Tools_Posts',
		'content/class-agent-tools-patch.php'       => 'Align_Agent_Tools_Patch',
		'content/class-agent-tools-clone.php'         => 'Align_Agent_Tools_Clone',
		'content/class-agent-tools-asset-history.php' => 'Align_Agent_Tools_Asset_History',
		'taxonomy/class-agent-tools-taxonomy.php'   => 'Align_Agent_Tools_Taxonomy',
		'structure/class-agent-tools-templates.php' => 'Align_Agent_Tools_Templates',
		'site/class-agent-tools-site.php'           => 'Align_Agent_Tools_Site',
		'site/class-agent-tools-global-code.php'    => 'Align_Agent_Tools_Global_Code',
		'seo/class-agent-tools-seo.php'             => 'Align_Agent_Tools_Seo',
		'seo/class-agent-tools-redirects.php'       => 'Align_Agent_Tools_Redirects',
		'media/class-agent-tools-media.php'         => 'Align_Agent_Tools_Media',
		'comments/class-agent-tools-comments.php'   => 'Align_Agent_Tools_Comments',
		'users/class-agent-tools-users.php'         => 'Align_Agent_Tools_Users',
		'site/class-agent-option-writer.php'         => 'Align_Agent_Option_Writer',
		'site/class-agent-tools-code-slots.php'      => 'Align_Agent_Tools_Code_Slots',
		'site/class-agent-tools-settings.php'        => 'Align_Agent_Tools_Settings',
		'site/class-agent-tools-protection.php'      => 'Align_Agent_Tools_Protection',
		'site/class-agent-tools-audit.php'           => 'Align_Agent_Tools_Audit',
		'site/class-agent-tools-health.php'          => 'Align_Agent_Tools_Health',
		'site/class-agent-tools-backups.php'         => 'Align_Agent_Tools_Backups',
		'structure/class-agent-tools-navigation.php' => 'Align_Agent_Tools_Navigation',
		'structure/class-agent-tools-render-map.php' => 'Align_Agent_Tools_Render_Map',
		'i18n/class-agent-tools-i18n.php'            => 'Align_Agent_Tools_I18n',
		'i18n/class-agent-tools-i18n-urls.php'       => 'Align_Agent_Tools_I18n_Urls',
		'content/class-agent-tools-post-lock.php'    => 'Align_Agent_Tools_Post_Lock',
		'content/class-agent-tools-finder.php'       => 'Align_Agent_Tools_Finder',
		'seo/class-agent-tools-seo-audit.php'        => 'Align_Agent_Tools_Seo_Audit',
	);

	/**
	 * Require all tool files defined in the manifest.
	 */
	public static function load(): void {
		foreach ( self::MANIFEST as $path => $class ) {
			$file = __DIR__ . '/' . $path;
			if ( file_exists( $file ) ) {
				require_once $file;
			} else {
				error_log( '[Align Agent] Tool file missing: ' . $path );
			}
		}
	}

	/**
	 * Initialize all loaded tool classes.
	 */
	public static function init(): void {
		foreach ( self::MANIFEST as $path => $class ) {
			if ( class_exists( $class ) ) {
				$class::init();
			} else {
				error_log( '[Align Agent] Tool class not loaded: ' . $class );
			}
		}
	}
}
