<?php
/**
 * Align Builder Custom Templates Handler
 *
 * Handles persistent storage, retrieval, and deletion of custom user/admin layout templates.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Custom_Templates_Handler {

	const OPTION_KEY = 'align_builder_custom_templates';

	/**
	 * Get all custom templates.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public static function get_templates( $request ) {
		$templates = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		return rest_ensure_response( array(
			'status'    => 'success',
			'templates' => array_values( $templates ),
		) );
	}

	/**
	 * Save or create a custom template.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_template( $request ) {
		$params = $request->get_json_params();
		$name   = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$html   = isset( $params['html'] ) ? trim( (string) $params['html'] ) : '';
		$css    = isset( $params['css'] ) ? trim( (string) $params['css'] ) : '';
		$js     = isset( $params['js'] ) ? trim( (string) $params['js'] ) : '';
		$image  = isset( $params['image'] ) ? esc_url_raw( trim( (string) $params['image'] ) ) : '';
		$group  = isset( $params['group'] ) ? sanitize_text_field( $params['group'] ) : 'Layout';
		$id     = isset( $params['id'] ) && ! empty( $params['id'] )
			? sanitize_key( $params['id'] )
			: ( 'custom_layout_' . time() . '_' . wp_generate_password( 5, false ) );

		if ( empty( $name ) || empty( $html ) ) {
			return new WP_Error(
				'missing_fields',
				__( 'Template name and HTML structure are required.', 'align' ),
				array( 'status' => 400 )
			);
		}

		$templates = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		$templates[ $id ] = array(
			'id'      => $id,
			'name'    => $name,
			'html'    => $html,
			'css'     => $css,
			'js'      => $js,
			'image'   => $image,
			'group'   => ! empty( $group ) ? $group : 'Layout',
			'updated' => current_time( 'mysql' ),
		);

		update_option( self::OPTION_KEY, $templates, false );
		error_log( sprintf( '[Align CrudHandler] Saved custom layout template "%s" (ID: %s, Size: %d bytes)', $name, $id, strlen( $html ) ) );

		return rest_ensure_response( array(
			'status'   => 'success',
			'template' => $templates[ $id ],
		) );
	}

	/**
	 * Delete a custom template.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_template( $request ) {
		$params = $request->get_json_params();
		$id     = isset( $params['id'] ) ? sanitize_key( $params['id'] ) : '';
		if ( empty( $id ) ) {
			$id = sanitize_key( (string) $request->get_param( 'id' ) );
		}

		if ( empty( $id ) ) {
			return new WP_Error(
				'missing_id',
				__( 'Template ID is required for deletion.', 'align' ),
				array( 'status' => 400 )
			);
		}

		$templates = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		if ( isset( $templates[ $id ] ) ) {
			$name = isset( $templates[ $id ]['name'] ) ? $templates[ $id ]['name'] : $id;
			unset( $templates[ $id ] );
			update_option( self::OPTION_KEY, $templates, false );
			error_log( sprintf( '[Align CrudHandler] Deleted custom layout template "%s" (ID: %s)', $name, $id ) );
		}

		return rest_ensure_response( array(
			'status' => 'success',
			'id'     => $id,
		) );
	}
}
