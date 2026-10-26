<?php
/**
 * Align Settings - Master Tab: SEO & Multi-Language Hub
 *
 * Consolidates Active Languages, Rank Math Redirections Importer,
 * and Translation Matrix Dashboard under a unified sub-navigation bar.
 * Follows Align Settings Style Guide.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="align-master-tab-wrap" id="align-wrap-seo-languages">
	<!-- Subnav Pills for SEO & Languages -->
	<div class="align-subnav-bar" role="tablist">
		<button type="button" class="align-subnav-pill is-active" data-subtab="seo-languages-active">
			<?php esc_html_e( 'Active Languages', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="seo-languages-redirections">
			<?php esc_html_e( 'Rank Math Redirections', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="seo-languages-matrix">
			<?php esc_html_e( 'Translation Matrix', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="seo-languages-bulk">
			<?php esc_html_e( 'Bulk AI Translator', 'align' ); ?>
		</button>
		<button type="button" class="align-subnav-pill" data-subtab="seo-languages-bulk-seo">
			<?php esc_html_e( 'Bulk AI SEO Generator', 'align' ); ?>
		</button>
	</div>

	<!-- Subtab 1: Active Languages -->
	<div class="align-subtab-content is-active" id="subtab-seo-languages-active">
		<?php include __DIR__ . '/tab-languages.php'; ?>
	</div>

	<!-- Subtab 2: Rank Math Redirections -->
	<div class="align-subtab-content" id="subtab-seo-languages-redirections" style="display:none;">
		<?php include __DIR__ . '/tab-redirections.php'; ?>
	</div>

	<!-- Subtab 3: Translation Matrix -->
	<div class="align-subtab-content" id="subtab-seo-languages-matrix" style="display:none;">
		<?php include __DIR__ . '/tab-translation-matrix.php'; ?>
	</div>

	<!-- Subtab 4: Bulk AI Translator -->
	<div class="align-subtab-content" id="subtab-seo-languages-bulk" style="display:none;">
		<?php include __DIR__ . '/part-bulk-ai-translator.php'; ?>
	</div>

	<!-- Subtab 5: Bulk AI SEO Generator -->
	<div class="align-subtab-content" id="subtab-seo-languages-bulk-seo" style="display:none;">
		<?php include __DIR__ . '/part-bulk-seo-generator.php'; ?>
	</div>
</div>
