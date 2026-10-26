<?php
/**
 * Gutenberg HTML paste conversion bridge.
 *
 * @package Align
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Editor_Paste {
	/** Initialize editor and REST hooks. */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/** Enqueue the paste interceptor in Gutenberg editors. */
	public static function enqueue() {
		$path = '/core/builder/gutenberg/editor-html-paste.js';
		wp_enqueue_script( 'align-editor-html-paste', get_template_directory_uri() . $path,
			array( 'wp-blocks', 'wp-data', 'wp-api-fetch', 'wp-rich-text', 'wp-notices', 'wp-dom-ready', 'wp-block-editor' ),
			filemtime( get_template_directory() . $path ), true );
	}

	/** Register the authenticated conversion endpoint. */
	public static function register_routes() {
		register_rest_route( 'align/v1', '/convert-pasted-html', array(
			'methods' => 'POST',
			'permission_callback' => array( __CLASS__, 'permission' ),
			'callback' => array( __CLASS__, 'convert' ),
			'args' => array(
				'html' => array( 'type' => 'string', 'required' => true ),
				'post_id' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
				'post_type' => array( 'type' => 'string', 'required' => true ),
				'word_mode' => array( 'type' => 'boolean', 'default' => false ),
			),
		) );
	}

	/**
	 * Check permission for the current editor object.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function permission( $request ) {
		$type = get_post_type_object( $request['post_type'] );
		if ( ! $type || ! $type->show_in_rest ) {
			return false;
		}
		if ( $request['post_id'] ) {
			return get_post_type( $request['post_id'] ) === $type->name && current_user_can( 'edit_post', $request['post_id'] );
		}
		return current_user_can( $type->cap->create_posts );
	}

	/**
	 * Convert pasted HTML.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error
	 */
	public static function convert( $request ) {
		error_log( '[Align API] Editor paste conversion | post_id=' . $request['post_id'] . ' | post_type=' . $request['post_type'] );
		return Align_Block_Converter::convert( $request['html'], $request['word_mode'] );
	}
}
Align_Editor_Paste::init();
