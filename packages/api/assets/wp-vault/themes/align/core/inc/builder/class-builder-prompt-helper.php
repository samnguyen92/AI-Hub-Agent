<?php
/**
 * Align Theme - Builder AI Translation Prompt & Glossary Helper
 *
 * Encapsulates script variant directives, Hong Kong corporate, HR & insurance
 * glossaries, and multi-tier prompt composition for AI translations.
 *
 * Adheres strictly to Align coding standards & < 500 lines constraint.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Align_Builder_Prompt_Helper {

	/**
	 * Build script-specific directive string.
	 *
	 * @param string $target_lang    Target language code (e.g. 'zh', 'zh-hk').
	 * @param string $script_variant Script variant ('traditional-hk', 'traditional-tw', 'traditional', 'simplified', etc.).
	 * @param string $lang_name      Language display name.
	 * @return string Script directive instructions for the LLM.
	 */
	public static function build_script_directive( $target_lang, $script_variant = 'default', $lang_name = '' ) {
		$is_hk = ( 'traditional-hk' === $script_variant )
			|| 'zh-hk' === strtolower( $target_lang )
			|| 'zh-hant-hk' === strtolower( $target_lang )
			|| ( false !== stripos( $lang_name, 'hong kong' ) || false !== stripos( $lang_name, '香港' ) );

		if ( $is_hk ) {
			return self::build_hk_script_directive();
		}

		if ( 'traditional-tw' === $script_variant || false !== stripos( $lang_name, 'taiwan' ) || false !== stripos( $lang_name, '台灣' ) ) {
			return "CRITICAL SCRIPT & REGION: Translate strictly into TAIWAN TRADITIONAL CHINESE (繁體中文 - 台灣 / 國語). Use standard Taiwanese Traditional characters and terminology conventions. NEVER output Simplified Chinese.";
		}

		if ( 'traditional' === $script_variant || false !== stripos( $lang_name, 'traditional' ) || false !== stripos( $lang_name, '繁體' ) ) {
			return "CRITICAL WRITING SCRIPT: Translate strictly into TRADITIONAL CHINESE (繁體中文, 正體字). Use standard Traditional characters (e.g. 體, 國, 說, 灣, 門, 辦, 險, 讓, 處, 專, 業, 發, 實, 點). NEVER output Simplified Chinese characters under any circumstance.";
		}

		if ( 'simplified' === $script_variant || false !== stripos( $lang_name, 'simplified' ) || false !== stripos( $lang_name, '简体' ) ) {
			return "CRITICAL WRITING SCRIPT: Translate strictly into SIMPLIFIED CHINESE (简体中文).";
		}

		return '';
	}

	/**
	 * Build comprehensive Hong Kong localization directive with tone & terminology rules.
	 *
	 * @return string
	 */
	public static function build_hk_script_directive() {
		$rules = array(
			'CRITICAL REGIONAL LOCALIZATION: HONG KONG TRADITIONAL CHINESE (繁體中文 - 香港 / 港式繁體書面語).',
			'1. SCRIPT: Use standard Hong Kong Traditional Chinese characters (正體字/繁體字). NEVER output Simplified characters or auto-converted Mainland phrasing.',
			'2. TONE & AUDIENCE: Professional, authoritative corporate tone tailored for Hong Kong corporate executives, HR directors, insurance brokers, and high-net-worth private clients.',
			'3. REGISTER: Formal Hong Kong Written Chinese (標準書面語). Do NOT use colloquial spoken Cantonese slang (no 嘅, 咗, 唔, 喺, 諗, 睇, 哋).',
			'4. ZERO MAINLAND BUZZWORDS: Strictly avoid Mainland Chinese administrative jargon (e.g. do NOT use 打造, 抓手, 賦能, 優化, 理賠, 醫保, 工傷保險, 養老金, 鏈接, 項目, 質量).',
			'5. HONG KONG CORPORATE & INSURANCE GLOSSARY (Strictly enforce these exact terms):',
		);

		$glossary = self::get_hk_insurance_glossary();
		foreach ( $glossary as $term => $definition ) {
			$rules[] = "   - {$term} -> {$definition}";
		}

		return implode( "\n", $rules );
	}

	/**
	 * Curated Hong Kong corporate, HR and insurance terminology dictionary.
	 *
	 * @return array
	 */
	public static function get_hk_insurance_glossary() {
		return array(
			"Employees' Compensation"           => '僱員補償保險 / 勞保 (STRICTLY FORBIDDEN: 工傷保險, 勞動保險)',
			'Public Liability Insurance'        => '公眾責任保險 / 第三者責任保險',
			'Directors & Officers (D&O)'        => '董事及高級管理人員責任保險 (D&O 責任保險)',
			'Business Interruption'             => '業務中斷保險',
			'Group Medical / Health Insurance'  => '團體醫療保險 / 醫療保障 (STRICTLY FORBIDDEN: 醫保)',
			'Professional Indemnity'            => '專業責任保險 / 專業彌償保險',
			'Keyman Insurance'                  => '關鍵人員保險 / 關鍵員工保險',
			'Cyber Insurance'                   => '網絡安全保險',
			'Claims / File a Claim'             => '索償 / 提出索償 (STRICTLY FORBIDDEN: 理賠 alone without context)',
			'Underwriting'                      => '核保',
			'Policyholder / Insured'            => '保單持有人 / 受保人',
			'Premium'                           => '保費',
			'Deductible / Excess'               => '自負額 / 墊底費',
			'Coverage / Benefit'                => '保障範圍 / 保障利益',
			'Pension / Retirement / MPF'        => '強積金 / MPF / 強制性公積金 (STRICTLY FORBIDDEN: 養老金, 退休金計劃)',
			'Human Resources / Benefits'        => '人力資源 / 僱員福利',
			'Contact Us / Enquire'              => '聯絡我們 / 查詢',
			'About Us'                          => '關於我們',
			'Clients / Customers'               => '客戶 (not 用戶)',
			'Quality'                           => '質素 (STRICTLY FORBIDDEN: 質量)',
			'Information'                       => '資訊 / 資料 (STRICTLY FORBIDDEN: 信息)',
			'Link / URL'                        => '連結 (STRICTLY FORBIDDEN: 鏈接)',
			'Program / Solution'                => '計劃 / 方案 (not 項目)',
			'Brand Name'                        => 'Keep "Trusted Union" in English unchanged',
		);
	}

	/**
	 * Compose full translation prompt for LLM batch request.
	 *
	 * @param array  $indexed_input       Indexed array of texts ('k0' => '...', ...).
	 * @param string $target_lang         Target language code.
	 * @param string $lang_name           Target language name.
	 * @param string $script_variant      Script variant.
	 * @param string $custom_instructions User-specified runtime instructions.
	 * @param string $persistent_hint     Persistent instructions from language configuration.
	 * @return string Prompt to send to LLM.
	 */
	public static function compose_batch_prompt( $indexed_input, $target_lang, $lang_name, $script_variant, $custom_instructions = '', $persistent_hint = '' ) {
		$script_directive = self::build_script_directive( $target_lang, $script_variant, $lang_name );

		$additional_rules = array();
		if ( ! empty( $persistent_hint ) ) {
			$additional_rules[] = "LANGUAGE CONFIGURATION INSTRUCTIONS:\n" . trim( $persistent_hint );
		}
		if ( ! empty( $custom_instructions ) && $custom_instructions !== $persistent_hint ) {
			$additional_rules[] = "RUNTIME TRANSLATION INSTRUCTIONS & STYLE GUIDELINES:\n" . trim( $custom_instructions );
		}

		$prompt = "You are a professional corporate and financial website translator.\n"
			. "Translate each string in this JSON object into {$lang_name} ({$target_lang}).\n\n"
			. ( $script_directive ? "{$script_directive}\n\n" : '' )
			. ( ! empty( $additional_rules ) ? implode( "\n\n", $additional_rules ) . "\n\n" : '' )
			. "CRITICAL OPERATIONAL RULES:\n"
			. "1. Output a valid JSON object with the EXACT same keys (k0, k1, ...). Never merge, drop, or reorder keys.\n"
			. "2. Keep brand names (e.g. \"Trusted Union\", \"Liam Morarjee\") unchanged.\n"
			. "3. Maintain an authoritative, polished, natural tone suitable for high-end insurance/business advisory.\n"
			. "4. Output ONLY the JSON object, with no markdown code blocks or explanations.\n\n"
			. wp_json_encode( $indexed_input, JSON_UNESCAPED_UNICODE );

		return $prompt;
	}
}
