<?php
/**
 * Align Theme Core Engine Bootstrap
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. Require Custom Blocks Engine
$blocks_index = __DIR__ . '/blocks/index.php';
if ( file_exists( $blocks_index ) ) {
	require_once $blocks_index;
}

// 2. Load Core Includes & Essential Modules Loader
$includes_index = __DIR__ . '/includes/index.php';
if ( file_exists( $includes_index ) ) {
	require_once $includes_index;
}

// 3. Load Dynamic Template Assets Loader
$assets_loader = __DIR__ . '/inc/template-assets-loader.php';
if ( file_exists( $assets_loader ) ) {
	require_once $assets_loader;
	if ( class_exists( 'TemplateAssetsLoader' ) ) {
		TemplateAssetsLoader::init();
	}
}

// 4. Load Visual Builder Engine Controller
$builder_engine = __DIR__ . '/inc/class-align-builder.php';
if ( file_exists( $builder_engine ) ) {
	require_once $builder_engine;
}

