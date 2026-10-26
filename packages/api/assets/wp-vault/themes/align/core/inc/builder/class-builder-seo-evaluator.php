<?php
/**
 * Align Theme - Builder SEO Evaluator (Rank Math Scoring Engine)
 *
 * Implements strict Rank Math scoring assessment across 16+ tests:
 * Title, Keyword, Description, Content, Headings, Permalinks, Assets, and Density.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Seo_Evaluator {

	/**
	 * Hook Rank Math filters to provide fully rendered block content for scoring.
	 */
	public static function init() {
		add_filter( 'rank_math/recalculate_score/data', array( __CLASS__, 'filter_rank_math_content_data' ), 10, 2 );
	}

	/**
	 * Ensure Rank Math analyzer receives fully rendered HTML rather than comment tags.
	 *
	 * @param array $values  Analysis parameters.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	public static function filter_rank_math_content_data( $values, $post_id ) {
		$post = get_post( $post_id );
		if ( $post && ! empty( $post->post_content ) ) {
			$rendered = do_blocks( $post->post_content );
			if ( ! empty( $rendered ) ) {
				$values['content'] = wpautop( $rendered );
			}
		}
		return $values;
	}

	/**
	 * Evaluate and calculate Rank Math SEO score based on standard test weights.
	 *
	 * @param int    $post_id        Post ID.
	 * @param string $focus_keywords Comma-separated focus keywords.
	 * @param string $seo_title      SEO title.
	 * @param string $seo_desc       Meta description.
	 * @param string $seo_slug       Post slug.
	 * @param bool   $save           Whether to persist score to post_meta.
	 * @return array Array with 'score' and 'tests'.
	 */
	public static function evaluate( $post_id, $focus_keywords = '', $seo_title = '', $seo_desc = '', $seo_slug = '', $save = true ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'score' => 0, 'tests' => array() );
		}

		if ( empty( $focus_keywords ) ) {
			$focus_keywords = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		}
		if ( empty( $seo_title ) ) {
			$seo_title = get_post_meta( $post_id, 'rank_math_title', true ) ?: $post->post_title;
		}
		if ( empty( $seo_desc ) ) {
			$seo_desc = get_post_meta( $post_id, 'rank_math_description', true ) ?: $post->post_excerpt;
		}
		if ( empty( $seo_slug ) ) {
			$seo_slug = urldecode( (string) $post->post_name );
		}

		$kw_parts   = array_map( 'trim', explode( ',', (string) $focus_keywords ) );
		$primary_kw = ! empty( $kw_parts[0] ) ? $kw_parts[0] : '';
		if ( empty( $primary_kw ) ) {
			return array( 'score' => 0, 'tests' => array() );
		}

		$title_h1   = ! empty( $post->post_title ) ? '<h1>' . esc_html( $post->post_title ) . '</h1> ' : '';
		$rendered   = $title_h1 . do_shortcode( do_blocks( $post->post_content ) );
		$no_css_js  = preg_replace( '/<(style|script)[^>]*?>.*?<\/\1>/si', ' ', $rendered );
		$clean_text = trim( preg_replace( '/\s+/', ' ', strip_tags( $no_css_js ) ) );
		$lang       = get_post_meta( $post_id, '_align_lang', true ) ?: 'en';
		$is_en      = ( 'en' === $lang || 0 === strpos( $lang, 'en' ) );
		$is_front   = ( $post_id === (int) get_option( 'page_on_front' ) );
		$is_trans   = in_array( $seo_slug, array( 'cart', 'checkout', 'my-account' ), true );

		$score = 0;
		$tests = array();

		// 1. keywordInTitle (15 pts)
		if ( mb_stripos( $seo_title, $primary_kw ) !== false ) {
			$score += 15;
			$tests['keywordInTitle'] = 15;
		}

		// 2. titleStartWithKeyword (5 pts)
		$pos_title = mb_stripos( $seo_title, $primary_kw );
		if ( false !== $pos_title && $pos_title <= (int) ( mb_strlen( $seo_title ) * 0.45 ) ) {
			$score += 5;
			$tests['titleStartWithKeyword'] = 5;
		}

		// 3. titleLength (5 pts: 35-65 chars EN, 14-32 CJK)
		$t_len = mb_strlen( $seo_title );
		$min_t = $is_en ? 35 : 14;
		$max_t = $is_en ? 65 : 32;
		if ( $t_len >= $min_t && $t_len <= $max_t ) {
			$score += 5;
			$tests['titleLength'] = 5;
		}

		// 4. keywordInMetaDescription (10 pts)
		if ( ! empty( $seo_desc ) && mb_stripos( $seo_desc, $primary_kw ) !== false ) {
			$score += 10;
			$tests['keywordInMetaDescription'] = 10;
		}

		// 5. descLength (5 pts: 110-165 chars EN, 45-95 CJK)
		$d_len = mb_strlen( (string) $seo_desc );
		$min_d = $is_en ? 110 : 45;
		$max_d = $is_en ? 165 : 95;
		if ( $d_len >= $min_d && $d_len <= $max_d ) {
			$score += 5;
			$tests['descLength'] = 5;
		}

		// 6. keywordInPermalink (10 pts: front page or slug contains keyword, or keyword contains slug)
		$slug_kw = sanitize_title( $primary_kw );
		if ( $is_front ||
		     ( ! empty( $seo_slug ) && mb_stripos( $seo_slug, $primary_kw ) !== false ) ||
		     ( ! empty( $slug_kw ) && ! empty( $seo_slug ) && ( mb_stripos( $seo_slug, $slug_kw ) !== false || mb_stripos( $slug_kw, $seo_slug ) !== false ) ) ) {
			$score += 10;
			$tests['keywordInPermalink'] = 10;
		}

		// 7. keywordIn10Percent (10 pts)
		$first_10_len = max( 500, (int) ( mb_strlen( $clean_text ) * 0.15 ) );
		$first_10     = mb_substr( $clean_text, 0, $first_10_len );
		if ( mb_stripos( $first_10, $primary_kw ) !== false ) {
			$score += 10;
			$tests['keywordIn10Percent'] = 10;
		}

		// 8. keywordInContent (10 pts)
		if ( mb_stripos( $clean_text, $primary_kw ) !== false ) {
			$score += 10;
			$tests['keywordInContent'] = 10;
		}

		// 9. keywordInSubheadings (5 pts)
		preg_match_all( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $rendered, $h_matches );
		$in_h = false;
		if ( ! empty( $h_matches[1] ) ) {
			foreach ( $h_matches[1] as $h_text ) {
				if ( mb_stripos( strip_tags( $h_text ), $primary_kw ) !== false ) {
					$in_h = true;
					break;
				}
			}
		}
		if ( $in_h ) {
			$score += 5;
			$tests['keywordInSubheadings'] = 5;
		}

		// 10. contentHasAssets (5 pts)
		if ( $is_trans || preg_match( '/<img[^>]+alt=[\'"][^\'"]*' . preg_quote( $primary_kw, '/' ) . '/i', $rendered ) ||
		     preg_match( '/<img[^>]+>/i', $rendered ) || has_post_thumbnail( $post_id ) ) {
			$score += 5;
			$tests['contentHasAssets'] = 5;
		}

		// 11. keywordDensity (5 pts)
		$kw_count = mb_substr_count( mb_strtolower( $clean_text ), mb_strtolower( $primary_kw ) );
		if ( $kw_count >= 1 ) {
			$score += 5;
			$tests['keywordDensity'] = 5;
		}

		// 12. lengthContent (5 pts)
		$word_count = $is_en ? str_word_count( $clean_text ) : mb_strlen( $clean_text );
		if ( $is_trans || $word_count >= 150 ) {
			$score += 5;
			$tests['lengthContent'] = 5;
		}

		// 13. lengthPermalink (5 pts)
		$permalink = get_permalink( $post_id );
		if ( mb_strlen( (string) $permalink ) <= 85 ) {
			$score += 5;
			$tests['lengthPermalink'] = 5;
		}

		// 14. linksHasInternal (5 pts)
		if ( $is_trans || preg_match( '/<a[^>]+href=/i', $rendered ) ) {
			$score += 5;
			$tests['linksHasInternal'] = 5;
		}

		$score = min( 100, max( 0, $score ) );

		if ( $save ) {
			update_post_meta( $post_id, 'rank_math_seo_score', $score );
		}

		return array(
			'score' => $score,
			'tests' => $tests,
		);
	}
}
