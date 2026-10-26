<?php
/**
 * Align Theme Core Engine - Builder Asset Writer & Disk Persister
 *
 * Handles atomic writes, minification, and safe deletion/synchronization of external
 * CSS and JS assets on disk with snapshot history tracking.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( ! defined( 'ALIGN_TEST_RUNNER' ) ) {
		exit;
	}
}

class Align_Builder_Asset_Writer {

	/**
	 * Save a single external asset file with minification, snapshot guard, and legacy cleanup.
	 *
	 * @param string      $type         Asset type ('css' or 'js').
	 * @param string      $id           Asset identifier (e.g. 'style-page-52').
	 * @param string      $code         Raw asset source code.
	 * @param array|mixed $legacy_ids   Array or single legacy asset ID to clean up.
	 * @param bool        $allow_delete Whether to allow unlinking if code is empty.
	 * @return void
	 */
	public static function save_single_asset( string $type, string $id, string $code, $legacy_ids = array(), bool $allow_delete = true ): void {
		if ( empty( $id ) ) {
			return;
		}

		$dir = class_exists( 'Align_Builder_Assets' ) && method_exists( 'Align_Builder_Assets', 'get_builder_assets_dir' )
			? Align_Builder_Assets::get_builder_assets_dir()
			: ( class_exists( 'Align_Builder_Asset_History' )
				? Align_Builder_Asset_History::get_assets_dir()
				: trailingslashit( get_stylesheet_directory() ) . 'assets/builder/' );

		$safe_id  = sanitize_file_name( $id );
		$ext      = '.' . $type;
		$file     = $dir . $safe_id . $ext;
		$trimmed  = trim( $code );
		$legacies = is_array( $legacy_ids ) ? $legacy_ids : array_filter( array( (string) $legacy_ids ) );
		if ( 0 !== strpos( $id, 'align-' ) ) {
			$legacies[] = 'align-' . $id;
		}

		if ( '' !== $trimmed ) {
			$minified = ( 'css' === $type )
				? ( class_exists( 'Align_Builder_Minifier' ) ? Align_Builder_Minifier::minify_css( $trimmed ) : $trimmed )
				: ( class_exists( 'Align_Builder_Minifier' ) ? Align_Builder_Minifier::minify_js( $trimmed ) : $trimmed );

			// Create history snapshot prior to overwriting existing file
			if ( file_exists( $file ) && class_exists( 'Align_Builder_Asset_History' ) ) {
				Align_Builder_Asset_History::snapshot( $file, 'overwrite', $minified );
			}

			$written = class_exists( 'Align_Builder_Persistence_Service' ) && method_exists( 'Align_Builder_Persistence_Service', 'atomic_file_put_contents' )
				? Align_Builder_Persistence_Service::atomic_file_put_contents( $file, $minified )
				: ( false !== file_put_contents( $file, $minified ) );

			if ( $written ) {
				error_log( sprintf( '[Align Assets] External %s saved atomically: %s (Bytes: %d)', strtoupper( $type ), $file, strlen( $minified ) ) );
				if ( class_exists( 'Align_Builder_Asset_Cleaner' ) && method_exists( 'Align_Builder_Asset_Cleaner', 'clean_legacy_files' ) ) {
					Align_Builder_Asset_Cleaner::clean_legacy_files( $type, $file, $legacies );
				}
			} else {
				error_log( sprintf( '[Align Assets] [ERROR] Failed to save external %s: %s', strtoupper( $type ), $file ) );
			}
		} elseif ( file_exists( $file ) ) {
			if ( ! $allow_delete ) {
				error_log( sprintf( '[Align Assets] Skip delete (non-destructive sync): %s', $file ) );
				return;
			}

			// Create history snapshot prior to unlinking existing file
			if ( class_exists( 'Align_Builder_Asset_History' ) ) {
				Align_Builder_Asset_History::snapshot( $file, 'delete' );
			}

			unlink( $file );
			clearstatcache( true, $file );
			error_log( sprintf( '[Align Assets] Empty %s, removed file: %s', strtoupper( $type ), $file ) );
		}
	}

	/**
	 * Save CSS and JS assets to external files on disk (destructive on empty).
	 *
	 * Explicit-intent entry point for Builder saves.
	 *
	 * @param string      $style_id          Style asset identifier.
	 * @param string      $script_id         Script asset identifier.
	 * @param string      $css_code          CSS content.
	 * @param string      $js_code           JS content.
	 * @param array|mixed $legacy_style_ids  Legacy style IDs.
	 * @param array|mixed $legacy_script_ids Legacy script IDs.
	 * @return void
	 */
	public static function save_external_assets( $style_id, $script_id, $css_code, $js_code, $legacy_style_ids = array(), $legacy_script_ids = array() ): void {
		self::save_single_asset( 'css', (string) $style_id, (string) $css_code, $legacy_style_ids, true );
		self::save_single_asset( 'js', (string) $script_id, (string) $js_code, $legacy_script_ids, true );
	}

	/**
	 * Synchronize CSS and JS assets to external files (non-destructive on empty).
	 *
	 * Safe entry point for content parsers and sync routines where empty strings must not delete files.
	 *
	 * @param string      $style_id          Style asset identifier.
	 * @param string      $script_id         Script asset identifier.
	 * @param string      $css_code          CSS content.
	 * @param string      $js_code           JS content.
	 * @param array|mixed $legacy_style_ids  Legacy style IDs.
	 * @param array|mixed $legacy_script_ids Legacy script IDs.
	 * @return void
	 */
	public static function sync_external_assets( $style_id, $script_id, $css_code, $js_code, $legacy_style_ids = array(), $legacy_script_ids = array() ): void {
		self::save_single_asset( 'css', (string) $style_id, (string) $css_code, $legacy_style_ids, false );
		self::save_single_asset( 'js', (string) $script_id, (string) $js_code, $legacy_script_ids, false );
	}
}
