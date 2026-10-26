<?php
/**
 * Align Agent - Undo Spec & Object ID Resolver
 *
 * Resolves dynamic undo adapter specifications (e.g. patch_content by target_type)
 * and resolves post IDs, template part IDs (tp_id / tpl_id), and theme file templates
 * into canonical object metadata for Backup Gate and Global Vault.
 *
 * @package Align_Agent
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Agent_Undo_Resolver {

	/**
	 * Resolve dynamic undo spec for tools whose target varies by arguments.
	 *
	 * @param string $tool Tool name.
	 * @param array  $spec Registered undo spec.
	 * @param array  $args Tool arguments.
	 * @return array Resolved undo spec with 'adapter' and 'id_arg'.
	 */
	public static function resolve_spec( string $tool, array $spec, array $args ): array {
		if ( 'patch_content' === $tool ) {
			$target_type = sanitize_key( (string) ( $args['target_type'] ?? 'page' ) );
			if ( 'template' === $target_type ) {
				return array( 'adapter' => 'template', 'id_arg' => 'tp_id' );
			}
			if ( 'reuse_block' === $target_type ) {
				return array( 'adapter' => 'template', 'id_arg' => 'post_id' );
			}
			return array( 'adapter' => 'post', 'id_arg' => 'post_id' );
		}

		return $spec;
	}

	/**
	 * Resolve target object ID, post type, template slug, and theme fallback content.
	 *
	 * @param array  $spec Resolved undo spec.
	 * @param array  $args Tool arguments.
	 * @param string $tool Tool name.
	 * @return array{
	 *     id: int,
	 *     type: string,
	 *     tp_id: string,
	 *     slug: string,
	 *     template_type: string,
	 *     theme_content: string,
	 *     title: string
	 * }
	 */
	public static function resolve_object_id( array $spec, array $args, string $tool ): array {
		$result = array(
			'id'            => 0,
			'type'          => 'unknown',
			'tp_id'         => '',
			'slug'          => '',
			'template_type' => '',
			'theme_content' => '',
			'title'         => '',
		);

		$id_arg  = ! empty( $spec['id_arg'] ) ? (string) $spec['id_arg'] : '';
		$aliases = array( 'post_id', 'page_id', 'template_id', 'block_id', 'id', 'tp_id' );
		$raw     = null;

		if ( '' !== $id_arg && isset( $args[ $id_arg ] ) && '' !== (string) $args[ $id_arg ] ) {
			$raw = $args[ $id_arg ];
		} else {
			foreach ( $aliases as $alias ) {
				if ( isset( $args[ $alias ] ) && '' !== (string) $args[ $alias ] ) {
					$raw = $args[ $alias ];
					break;
				}
			}
		}

		if ( 'update_nav_links' === $tool && ( null === $raw || '' === (string) $raw ) ) {
			$raw = 'tp_header';
		}

		if ( null === $raw || '' === (string) $raw ) {
			return $result;
		}

		// Case 1: Positive integer ID.
		if ( is_numeric( $raw ) && (int) $raw > 0 ) {
			$post_id = absint( $raw );
			$post    = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$prefix = '';
				if ( 'wp_template' === $post->post_type ) {
					$prefix = 'tpl_';
				} elseif ( 'wp_template_part' === $post->post_type ) {
					$prefix = 'tp_';
				}

				$result['id']            = (int) $post->ID;
				$result['type']          = (string) $post->post_type;
				$result['slug']          = (string) $post->post_name;
				$result['title']         = (string) $post->post_title;
				$result['tp_id']         = '' !== $prefix ? ( $prefix . $post->post_name ) : '';
				$result['template_type'] = in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ), true ) ? $post->post_type : '';
			}
			return $result;
		}

		// Case 2: String tp_id / template slug.
		$tp_id           = sanitize_text_field( (string) $raw );
		$is_explicit_tpl = ( 0 === strpos( $tp_id, 'tpl_' ) );
		$is_explicit_tp  = ( 0 === strpos( $tp_id, 'tp_' ) );
		$arg_tpl_type    = sanitize_key( (string) ( $args['template_type'] ?? '' ) );

		$is_tpl      = $is_explicit_tpl || in_array( $arg_tpl_type, array( 'template', 'wp_template' ), true );
		$target_type = $is_tpl ? 'wp_template' : 'wp_template_part';

		$stripped = $tp_id;
		if ( $is_explicit_tpl || $is_explicit_tp ) {
			$stripped = (string) preg_replace( '/^t(p|pl)_/', '', $stripped );
		}

		$slug_raw = ( false !== strpos( $stripped, '//' ) ) ? explode( '//', $stripped )[1] : $stripped;
		$slug     = sanitize_file_name( $slug_raw );

		$result['tp_id']         = $tp_id;
		$result['slug']          = $slug;
		$result['template_type'] = $target_type;

		if ( '' === $slug ) {
			return $result;
		}

		$search_names = array_values(
			array_unique(
				array_filter(
					array(
						$slug,
						$tp_id,
						$stripped,
						sanitize_title( get_stylesheet() . '-' . $slug ),
						sanitize_title( get_template() . '-' . $slug ),
					)
				)
			)
		);

		$found = get_posts(
			array(
				'post_type'      => $target_type,
				'post_status'    => array( 'publish', 'draft', 'private', 'auto-draft' ),
				'posts_per_page' => 1,
				'post_name__in'  => $search_names,
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'slug',
						'terms'    => array( get_stylesheet(), get_template() ),
					),
				),
			)
		);

		if ( empty( $found ) ) {
			$found = get_posts(
				array(
					'post_type'      => $target_type,
					'post_status'    => array( 'publish', 'draft', 'private', 'auto-draft' ),
					'posts_per_page' => 1,
					'post_name__in'  => $search_names,
				)
			);
		}

		if ( ! empty( $found ) && $found[0] instanceof WP_Post ) {
			$post                    = $found[0];
			$result['id']            = (int) $post->ID;
			$result['type']          = (string) $post->post_type;
			$result['slug']          = (string) $post->post_name;
			$result['title']         = (string) ( $post->post_title ?: $slug );
			$result['template_type'] = (string) $post->post_type;
			return $result;
		}

		if ( function_exists( 'get_block_template' ) ) {
			$tpl = get_block_template( get_stylesheet() . '//' . $slug, $target_type );
			if ( ! $tpl && get_template() !== get_stylesheet() ) {
				$tpl = get_block_template( get_template() . '//' . $slug, $target_type );
			}
			if ( ! $tpl && false !== strpos( $stripped, '//' ) ) {
				$tpl = get_block_template( $stripped, $target_type );
			}

			if ( $tpl ) {
				$title = ! empty( $tpl->title ) ? (string) $tpl->title : $slug;
				if ( ! empty( $tpl->wp_id ) && (int) $tpl->wp_id > 0 ) {
					$result['id']            = (int) $tpl->wp_id;
					$result['type']          = $target_type;
					$result['title']         = $title;
					$result['template_type'] = $target_type;
					return $result;
				}

				$result['id']            = 0;
				$result['type']          = 'theme_file';
				$result['title']         = $title;
				$result['theme_content'] = (string) ( $tpl->content ?? '' );
				return $result;
			}
		}

		return $result;
	}
}
