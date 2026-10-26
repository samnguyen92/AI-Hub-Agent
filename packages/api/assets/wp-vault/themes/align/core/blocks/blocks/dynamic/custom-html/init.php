<?php
/**
 * Align Custom HTML Block Registration
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Align Custom HTML block
 */
function align_register_custom_html_block() {
	$block_json_path = ALIGN_CORE_PATH . 'blocks/blocks/dynamic/custom-html/block.json';
	if ( ! file_exists( $block_json_path ) ) {
		return;
	}

	$metadata = json_decode( file_get_contents( $block_json_path ), true );

	$editor_script_handle = 'align-custom-html-editor';
	$script_path          = ALIGN_CORE_PATH . 'blocks/blocks/dynamic/custom-html/editor.js';
	$script_url           = ALIGN_CORE_URL . 'blocks/blocks/dynamic/custom-html/editor.js';

	wp_register_script(
		$editor_script_handle,
		$script_url,
		array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
		true
	);

	wp_localize_script(
		$editor_script_handle,
		'AlignVvvebConfig',
		array(
			'builderUrl' => admin_url( 'admin.php?page=' . ( defined( 'ALIGN_BUILDER_SLUG' ) ? ALIGN_BUILDER_SLUG : 'align-builder' ) ),
		)
	);

	$editor_style_handle = 'align-custom-html-editor-style';
	$style_path          = ALIGN_CORE_PATH . 'blocks/blocks/dynamic/custom-html/editor.css';
	$style_url           = ALIGN_CORE_URL . 'blocks/blocks/dynamic/custom-html/editor.css';

	wp_register_style(
		$editor_style_handle,
		$style_url,
		array(),
		file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0'
	);

	register_block_type( 'align/custom-html', array(
		'api_version'     => isset( $metadata['apiVersion'] ) ? $metadata['apiVersion'] : 3,
		'title'           => isset( $metadata['title'] ) ? $metadata['title'] : 'Align Builder Content',
		'category'        => isset( $metadata['category'] ) ? $metadata['category'] : 'align-category',
		'icon'            => isset( $metadata['icon'] ) ? $metadata['icon'] : 'html',
		'description'     => isset( $metadata['description'] ) ? $metadata['description'] : '',
		'attributes'      => isset( $metadata['attributes'] ) ? $metadata['attributes'] : array(),
		'supports'        => isset( $metadata['supports'] ) ? $metadata['supports'] : array(),
		'editor_script'   => $editor_script_handle,
		'editor_style'    => $editor_style_handle,
		'render_callback' => 'align_custom_html_render_callback',
	) );
}
add_action( 'init', 'align_register_custom_html_block' );

/**
 * Enqueue editor assets for block editor
 */
function align_custom_html_enqueue_editor_assets() {
	$style_path = ALIGN_CORE_PATH . 'blocks/blocks/dynamic/custom-html/editor.css';
	$style_url  = ALIGN_CORE_URL . 'blocks/blocks/dynamic/custom-html/editor.css';

	wp_enqueue_style(
		'align-custom-html-editor-style',
		$style_url,
		array(),
		file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0'
	);
}
add_action( 'enqueue_block_editor_assets', 'align_custom_html_enqueue_editor_assets' );

/**
 * Render callback for align/custom-html block
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Block inner content.
 * @return string Rendered block HTML.
 */
function align_custom_html_render_callback( $attributes, $content = '' ) {
	ob_start();
	require ALIGN_CORE_PATH . 'blocks/blocks/dynamic/custom-html/render.php';
	return ob_get_clean();
}
