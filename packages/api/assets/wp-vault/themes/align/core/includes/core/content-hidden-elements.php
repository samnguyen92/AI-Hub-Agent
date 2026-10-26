<?php
/**
 * Frontend Hidden Elements Stripper for Align Theme.
 *
 * Removes elements marked with data-align-hide from the frontend DOM output.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strip elements marked with data-align-hide from rendered content on frontend.
 *
 * @param string $content HTML content to filter.
 * @return string Filtered HTML content with hidden elements removed.
 */
function align_strip_hidden_elements( $content ) {
	// Fast pre-check: skip in admin, REST requests, or if content is empty or lacks data-align-hide
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || empty( $content ) || ! is_string( $content ) ) {
		return $content;
	}

	if ( strpos( $content, 'data-align-hide' ) === false ) {
		return $content;
	}

	$start_time = microtime( true );

	try {
		if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
			$dom      = new DOMDocument();
			$prev_err = libxml_use_internal_errors( true );
			$loaded   = $dom->loadHTML( '<?xml encoding="UTF-8"?><align-root>' . $content . '</align-root>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			libxml_clear_errors();
			libxml_use_internal_errors( $prev_err );

			if ( $loaded ) {
				$xpath = new DOMXPath( $dom );
				$nodes = $xpath->query( '//*[@data-align-hide="true" or @data-align-hide="1" or @data-align-hide=""]' );

				if ( $nodes && $nodes->length > 0 ) {
					$count           = $nodes->length;
					$nodes_to_remove = array();

					foreach ( $nodes as $node ) {
						$nodes_to_remove[] = $node;
					}

					foreach ( $nodes_to_remove as $node ) {
						if ( $node->parentNode ) {
							try {
								$node->parentNode->removeChild( $node );
							} catch ( \Throwable $e ) {
								// Node may have been already detached by parent removal
							}
						}
					}

					$root_node = $dom->getElementsByTagName( 'align-root' )->item( 0 );
					$output    = '';
					if ( $root_node && $root_node->childNodes ) {
						foreach ( $root_node->childNodes as $child ) {
							$output .= $dom->saveHTML( $child );
						}
					} else {
						$output = $dom->saveHTML();
					}

					if ( is_string( $output ) ) {
						if ( '' !== $output ) {
							$output    = preg_replace( '/<\?xml[^>]*\?>/i', '', $output );
							$protected = str_replace(
								array( '&lt;', '&gt;', '&amp;', '&quot;', '&apos;', '&#039;' ),
								array( '__ALIGN_ENT_LT__', '__ALIGN_ENT_GT__', '__ALIGN_ENT_AMP__', '__ALIGN_ENT_QUOT__', '__ALIGN_ENT_APOS__', '__ALIGN_ENT_APOS__' ),
								$output
							);
							$decoded   = html_entity_decode( $protected, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
							$output    = str_replace(
								array( '__ALIGN_ENT_LT__', '__ALIGN_ENT_GT__', '__ALIGN_ENT_AMP__', '__ALIGN_ENT_QUOT__', '__ALIGN_ENT_APOS__' ),
								array( '&lt;', '&gt;', '&amp;', '&quot;', '&#039;' ),
								$decoded
							);
							$content = trim( $output );
						} else {
							$content = '';
						}

						if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'ALIGN_DEBUG' ) && ALIGN_DEBUG ) ) {
							$elapsed = round( ( microtime( true ) - $start_time ) * 1000, 2 );
							error_log( sprintf( '[Align ContentHandler] Stripped %d hidden element(s) in %sms | Remaining size: %d bytes', $count, $elapsed, strlen( $content ) ) );
						}
					}
				}
			}
		}
	} catch ( \Throwable $e ) {
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'ALIGN_DEBUG' ) && ALIGN_DEBUG ) ) {
			error_log( '[Align ContentHandler] Error in align_strip_hidden_elements: ' . $e->getMessage() );
		}
	}

	return $content;
}

// Hook early at priority 8 so hidden nodes are stripped before component transformations and shortcode processing
add_filter( 'the_content', 'align_strip_hidden_elements', 8 );
add_filter( 'render_block', 'align_strip_hidden_elements', 8 );
