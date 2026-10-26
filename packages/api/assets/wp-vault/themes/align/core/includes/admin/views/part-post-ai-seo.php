<?php
/**
 * Align Theme - Admin Post AI SEO & Translation Meta Box View
 *
 * @package Align
 *
 * Variables provided by caller:
 * @var int    $post_id
 * @var string $post_lang
 * @var string $lang_name
 * @var string $seo_title
 * @var string $seo_desc
 * @var string $focus_kw
 * @var string $seo_score
 * @var string $post_slug
 * @var string $post_excerpt
 * @var string $rest_nonce
 * @var string $rest_generate
 * @var string $rest_save_seo
 * @var string $rest_trans
 * @var string $rest_copy_orig
 * @var string $rest_content
 * @var int    $source_post_id
 * @var string $source_post_title
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="align-ai-seo-panel" class="align-ai-seo-wrap">
	<style>
		.align-ai-seo-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 12.5px; color: #1e293b; }
		.align-ai-seo-tabs { display: flex; border-bottom: 1px solid #e2e8f0; margin: -6px -12px 12px; padding: 0 12px; gap: 4px; }
		.align-ai-seo-tab-btn { background: transparent !important; border: none; border-bottom: 2px solid transparent; padding: 8px 10px; font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; transition: all 0.15s; }
		.align-ai-seo-tab-btn:hover { color: #0284c7; }
		.align-ai-seo-tab-btn.is-active { color: #0284c7; border-bottom-color: #0284c7; }
		.align-ai-tab-pane { display: none; }
		.align-ai-tab-pane.is-active { display: block; }
		.align-ai-field-group { margin-bottom: 10px; }
		.align-ai-label { display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; font-weight: 600; color: #334155; margin-bottom: 4px; }
		.align-ai-counter { font-size: 11px; font-weight: 400; color: #64748b; }
		.align-ai-counter.is-warn { color: #d97706; }
		.align-ai-counter.is-good { color: #16a34a; }
		.align-ai-input, .align-ai-textarea, .align-ai-select { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 8px; font-size: 12px; color: #0f172a; background: #ffffff; box-sizing: border-box; }
		.align-ai-input:focus, .align-ai-textarea:focus, .align-ai-select:focus { outline: none; border-color: #0284c7; box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15); }
		.align-ai-textarea { min-height: 54px; resize: vertical; }
		.align-ai-btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; height: 32px; border-radius: 6px; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all 0.15s; background: transparent !important; box-sizing: border-box; text-decoration: none; margin-bottom: 6px; }
		.align-ai-btn-primary { border: 1px solid #0284c7; color: #0284c7; }
		.align-ai-btn-primary:hover { background: #0284c7 !important; color: #ffffff !important; }
		.align-ai-btn-secondary { border: 1px solid #bae6fd; color: #0284c7; background: #f0f9ff !important; }
		.align-ai-btn-secondary:hover { background: #e0f2fe !important; border-color: #7dd3fc; }
		.align-ai-btn:disabled { opacity: 0.6; cursor: not-allowed; }
		.align-ai-score-badge { display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
		.align-ai-score-badge.is-good { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
		.align-ai-score-badge.is-ok { background: #fffbeb; color: #d97706; border-color: #fde68a; }
		.align-ai-status-msg { font-size: 11.5px; padding: 6px 8px; border-radius: 4px; margin-top: 6px; display: none; line-height: 1.4; }
		.align-ai-status-msg.is-success { display: block; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
		.align-ai-status-msg.is-error { display: block; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
		.align-ai-status-msg.is-loading { display: block; background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
		.align-ai-output-box { margin-top: 8px; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; max-height: 140px; overflow-y: auto; font-size: 11.5px; line-height: 1.5; display: none; }
	</style>

	<!-- Tabs -->
	<div class="align-ai-seo-tabs">
		<button type="button" class="align-ai-seo-tab-btn is-active" data-target="#align-tab-seo"><?php esc_html_e( 'AI SEO', 'align' ); ?></button>
		<button type="button" class="align-ai-seo-tab-btn" data-target="#align-tab-content"><?php esc_html_e( 'AI Content', 'align' ); ?></button>
	</div>

	<!-- Tab 1: AI SEO Generator -->
	<div id="align-tab-seo" class="align-ai-tab-pane is-active">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
			<span style="font-size: 11.5px; color: #64748b;"><?php esc_html_e( 'Rank Math SEO', 'align' ); ?></span>
			<span id="align-seo-score-badge" class="align-ai-score-badge <?php echo ( (int) $seo_score >= 80 ) ? 'is-good' : ( ( (int) $seo_score > 0 ) ? 'is-ok' : '' ); ?>">
				<?php echo $seo_score ? sprintf( esc_html__( 'Score: %s/100', 'align' ), esc_html( $seo_score ) ) : esc_html__( 'No Score', 'align' ); ?>
			</span>
		</div>

		<button type="button" id="align-ai-generate-seo-btn" class="align-ai-btn align-ai-btn-secondary">
			<?php esc_html_e( 'Generate SEO with AI', 'align' ); ?>
		</button>

		<div class="align-ai-field-group">
			<label class="align-ai-label" for="align_seo_kw">
				<span><?php esc_html_e( 'Focus Keyword', 'align' ); ?></span>
			</label>
			<input type="text" id="align_seo_kw" class="align-ai-input" value="<?php echo esc_attr( $focus_kw ); ?>" placeholder="e.g. Hong Kong Insurance, PI Review" />
		</div>

		<div class="align-ai-field-group">
			<label class="align-ai-label" for="align_seo_title">
				<span><?php esc_html_e( 'SEO Title', 'align' ); ?></span>
				<span id="align_seo_title_count" class="align-ai-counter">0 / 60</span>
			</label>
			<input type="text" id="align_seo_title" class="align-ai-input" value="<?php echo esc_attr( $seo_title ); ?>" />
		</div>

		<div class="align-ai-field-group">
			<label class="align-ai-label" for="align_seo_desc">
				<span><?php esc_html_e( 'Meta Description', 'align' ); ?></span>
				<span id="align_seo_desc_count" class="align-ai-counter">0 / 160</span>
			</label>
			<textarea id="align_seo_desc" class="align-ai-textarea"><?php echo esc_textarea( $seo_desc ); ?></textarea>
		</div>

		<div class="align-ai-field-group">
			<label class="align-ai-label" for="align_seo_slug">
				<span><?php esc_html_e( 'URL Slug', 'align' ); ?></span>
			</label>
			<input type="text" id="align_seo_slug" class="align-ai-input" value="<?php echo esc_attr( $post_slug ); ?>" />
		</div>

		<div class="align-ai-field-group">
			<label class="align-ai-label" for="align_seo_excerpt">
				<span><?php esc_html_e( 'Post Excerpt', 'align' ); ?></span>
			</label>
			<textarea id="align_seo_excerpt" class="align-ai-textarea"><?php echo esc_textarea( $post_excerpt ); ?></textarea>
		</div>

		<button type="button" id="align-ai-save-seo-btn" class="align-ai-btn align-ai-btn-primary">
			<?php esc_html_e( 'Save SEO & Sync Rank Math', 'align' ); ?>
		</button>

		<div id="align-seo-status" class="align-ai-status-msg"></div>
	</div>

	<!-- Tab 2: AI Content Generator & Translator -->
	<div id="align-tab-content" class="align-ai-tab-pane">
		<div style="margin-bottom: 10px; padding: 6px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11.5px;">
			<strong><?php esc_html_e( 'Post Language:', 'align' ); ?></strong>
			<span style="color: #0284c7; font-weight: 600;"><?php echo esc_html( $lang_name . ' (' . strtoupper( $post_lang ) . ')' ); ?></span>
		</div>

		<?php if ( ! empty( $source_post_id ) ) : ?>
		<!-- Action 0: Copy from Original Post -->
		<div style="margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
			<label class="align-ai-label">
				<span><?php esc_html_e( 'Linked Original (English)', 'align' ); ?></span>
			</label>
			<div style="font-size: 11px; color: #475569; margin-bottom: 6px; padding: 5px 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr( $source_post_title ); ?>">
				📄 <?php echo esc_html( $source_post_title ); ?> (#<?php echo esc_html( $source_post_id ); ?>)
			</div>
			<button type="button" id="align-ai-copy-orig-btn" class="align-ai-btn align-ai-btn-secondary">
				<?php esc_html_e( 'Copy from Original (EN)', 'align' ); ?>
			</button>
			<div style="font-size: 11px; color: #64748b; line-height: 1.4; margin-top: 4px;">
				<?php esc_html_e( 'Copies full structure, classes, and SVGs from English post so you can re-translate or reset.', 'align' ); ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Action 1: Translate Post -->
		<div style="margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
			<label class="align-ai-label">
				<span><?php esc_html_e( 'Translate Article Content', 'align' ); ?></span>
			</label>
			<button type="button" id="align-ai-translate-post-btn" class="align-ai-btn align-ai-btn-secondary">
				<?php echo sprintf( esc_html__( 'Translate Post to %s', 'align' ), esc_html( $lang_name ) ); ?>
			</button>
			<div style="font-size: 11px; color: #64748b; line-height: 1.4;">
				<?php esc_html_e( 'Translates title and block content into the post language with high-accuracy business terminology.', 'align' ); ?>
			</div>
		</div>

		<!-- Action 2: Generate Content Section -->
		<div>
			<label class="align-ai-label" for="align_content_mode">
				<span><?php esc_html_e( 'Generate Section with AI', 'align' ); ?></span>
			</label>
			<select id="align_content_mode" class="align-ai-select" style="margin-bottom: 6px;">
				<option value="section"><?php esc_html_e( 'Standard Section (H2 + Paragraphs)', 'align' ); ?></option>
				<option value="intro"><?php esc_html_e( 'Introduction (H2 + Overview)', 'align' ); ?></option>
				<option value="key_takeaways"><?php esc_html_e( 'Key Takeaways (H2 + Bullet Points)', 'align' ); ?></option>
				<option value="faq"><?php esc_html_e( 'FAQ Section (H3 Questions & Answers)', 'align' ); ?></option>
				<option value="conclusion"><?php esc_html_e( 'Conclusion & Next Steps (H2)', 'align' ); ?></option>
			</select>

			<label class="align-ai-label" for="align_content_prompt">
				<span><?php esc_html_e( 'Instructions (Optional)', 'align' ); ?></span>
			</label>
			<input type="text" id="align_content_prompt" class="align-ai-input" style="margin-bottom: 8px;" placeholder="e.g. Focus on regulatory compliance in Hong Kong" />

			<button type="button" id="align-ai-generate-content-btn" class="align-ai-btn align-ai-btn-primary">
				<?php esc_html_e( 'Generate Section', 'align' ); ?>
			</button>

			<div id="align-content-output" class="align-ai-output-box"></div>
			<button type="button" id="align-ai-insert-content-btn" class="align-ai-btn align-ai-btn-secondary" style="display: none; margin-top: 6px;">
				<?php esc_html_e( 'Insert into Editor', 'align' ); ?>
			</button>
		</div>

		<div id="align-content-status" class="align-ai-status-msg"></div>
	</div>
</div>

<script>
(function() {
	var postId = <?php echo (int) $post_id; ?>;
	var postLang = <?php echo wp_json_encode( $post_lang ); ?>;
	var restNonce = <?php echo wp_json_encode( $rest_nonce ); ?>;
	var restGenSeo = <?php echo wp_json_encode( $rest_generate ); ?>;
	var restSaveSeo = <?php echo wp_json_encode( $rest_save_seo ); ?>;
	var restTrans = <?php echo wp_json_encode( $rest_trans ); ?>;
	var restCopyOrig = <?php echo wp_json_encode( $rest_copy_orig ); ?>;
	var restGenContent = <?php echo wp_json_encode( $rest_content ); ?>;

	// Tabs
	document.querySelectorAll('.align-ai-seo-tab-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			document.querySelectorAll('.align-ai-seo-tab-btn').forEach(function(b) { b.classList.remove('is-active'); });
			document.querySelectorAll('.align-ai-tab-pane').forEach(function(p) { p.classList.remove('is-active'); });
			btn.classList.add('is-active');
			var target = document.querySelector(btn.getAttribute('data-target'));
			if (target) target.classList.add('is-active');
		});
	});

	// Character counters
	var titleInput = document.getElementById('align_seo_title');
	var titleCount = document.getElementById('align_seo_title_count');
	var descInput = document.getElementById('align_seo_desc');
	var descCount = document.getElementById('align_seo_desc_count');

	function updateCounters() {
		if (titleInput && titleCount) {
			var tLen = (titleInput.value || '').length;
			titleCount.textContent = tLen + ' / 60';
			titleCount.className = 'align-ai-counter ' + (tLen > 60 ? 'is-warn' : (tLen >= 35 ? 'is-good' : ''));
		}
		if (descInput && descCount) {
			var dLen = (descInput.value || '').length;
			descCount.textContent = dLen + ' / 160';
			descCount.className = 'align-ai-counter ' + (dLen > 160 ? 'is-warn' : (dLen >= 110 ? 'is-good' : ''));
		}
	}
	if (titleInput) titleInput.addEventListener('input', updateCounters);
	if (descInput) descInput.addEventListener('input', updateCounters);
	updateCounters();

	// Status Helpers
	function showStatus(elId, type, msg) {
		var el = document.getElementById(elId);
		if (!el) return;
		el.className = 'align-ai-status-msg is-' + type;
		el.textContent = msg;
	}

	// Helper: Get Editor Content
	function getEditorContent() {
		if (window.wp && wp.data && wp.data.select('core/editor')) {
			return wp.data.select('core/editor').getEditedPostContent() || '';
		}
		var ta = document.getElementById('content');
		return ta ? ta.value : '';
	}

	// 1. Generate SEO with AI
	var genSeoBtn = document.getElementById('align-ai-generate-seo-btn');
	if (genSeoBtn) {
		genSeoBtn.addEventListener('click', function() {
			genSeoBtn.disabled = true;
			showStatus('align-seo-status', 'loading', 'Generating SEO metadata with AI...');
			var content = getEditorContent();

			fetch(restGenSeo, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
				body: JSON.stringify({ post_id: postId, lang: postLang, content: content })
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				genSeoBtn.disabled = false;
				if (data && data.success && data.generated) {
					var g = data.generated;
					if (g.focus_keyword) document.getElementById('align_seo_kw').value = g.focus_keyword;
					if (g.title) document.getElementById('align_seo_title').value = g.title;
					if (g.description) document.getElementById('align_seo_desc').value = g.description;
					if (g.excerpt) document.getElementById('align_seo_excerpt').value = g.excerpt;
					updateCounters();
					showStatus('align-seo-status', 'success', 'SEO generated successfully! Click Save to apply.');
				} else {
					showStatus('align-seo-status', 'error', (data && data.message) ? data.message : 'AI generation failed.');
				}
			})
			.catch(function(err) {
				genSeoBtn.disabled = false;
				showStatus('align-seo-status', 'error', 'Request failed: ' + err.message);
			});
		});
	}

	// 2. Save SEO & Sync Rank Math
	var saveSeoBtn = document.getElementById('align-ai-save-seo-btn');
	if (saveSeoBtn) {
		saveSeoBtn.addEventListener('click', function() {
			saveSeoBtn.disabled = true;
			showStatus('align-seo-status', 'loading', 'Saving SEO meta to Rank Math & Post...');

			var payload = {
				post_id: postId,
				focus_keyword: document.getElementById('align_seo_kw').value,
				title: document.getElementById('align_seo_title').value,
				description: document.getElementById('align_seo_desc').value,
				slug: document.getElementById('align_seo_slug').value,
				excerpt: document.getElementById('align_seo_excerpt').value
			};

			fetch(restSaveSeo, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
				body: JSON.stringify(payload)
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				saveSeoBtn.disabled = false;
				if (data && data.success) {
					showStatus('align-seo-status', 'success', 'Saved SEO to Rank Math & Post successfully!');
					if (window.wp && wp.data && wp.data.dispatch('core/editor')) {
						wp.data.dispatch('core/editor').editPost({
							slug: payload.slug,
							excerpt: payload.excerpt
						});
					}
				} else {
					showStatus('align-seo-status', 'error', (data && data.message) ? data.message : 'Failed to save SEO meta.');
				}
			})
			.catch(function(err) {
				saveSeoBtn.disabled = false;
				showStatus('align-seo-status', 'error', 'Save error: ' + err.message);
			});
		});
	}

	// 2.5. Copy from Original English Post
	var copyOrigBtn = document.getElementById('align-ai-copy-orig-btn');
	if (copyOrigBtn) {
		copyOrigBtn.addEventListener('click', function() {
			if (!confirm('Are you sure you want to copy content from the original English post? Any unsaved edits in this post will be replaced.')) {
				return;
			}
			copyOrigBtn.disabled = true;
			showStatus('align-content-status', 'loading', 'Copying content from original English post...');

			fetch(restCopyOrig, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
				body: JSON.stringify({ post_id: postId })
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				copyOrigBtn.disabled = false;
				if (data && data.success && data.post_content) {
					showStatus('align-content-status', 'success', 'Original content copied! Updating editor...');
					if (window.wp && wp.data && wp.blocks) {
						try {
							var parsed = wp.blocks.parse(data.post_content);
							wp.data.dispatch('core/block-editor').resetBlocks(parsed);
						} catch (e) {
							console.warn('[Align AI] Block update fallback to reload', e);
							window.location.reload();
						}
					} else {
						window.location.reload();
					}
				} else {
					showStatus('align-content-status', 'error', (data && data.message) ? data.message : 'Failed to copy original content.');
				}
			})
			.catch(function(err) {
				copyOrigBtn.disabled = false;
				showStatus('align-content-status', 'error', 'Error: ' + err.message);
			});
		});
	}

	// 3. Translate Post with AI
	var transPostBtn = document.getElementById('align-ai-translate-post-btn');
	if (transPostBtn) {
		transPostBtn.addEventListener('click', function() {
			transPostBtn.disabled = true;
			showStatus('align-content-status', 'loading', 'Translating entire post with AI (preserving styles & structure)...');

			fetch(restTrans, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
				body: JSON.stringify({ post_id: postId, target_lang: postLang })
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				transPostBtn.disabled = false;
				if (data && data.success) {
					showStatus('align-content-status', 'success', 'Post translated successfully! Updating editor...');
					if (window.wp && wp.data && wp.blocks && data.post_content) {
						try {
							var parsed = wp.blocks.parse(data.post_content);
							wp.data.dispatch('core/block-editor').resetBlocks(parsed);
							if (data.post_title) {
								wp.data.dispatch('core/editor').editPost({ title: data.post_title });
							}
						} catch (e) {
							console.warn('[Align AI] Gutenberg live update failed, reloading page...', e);
							window.location.reload();
						}
					} else {
						window.location.reload();
					}
				} else {
					showStatus('align-content-status', 'error', (data && data.message) ? data.message : 'Translation failed.');
				}
			})
			.catch(function(err) {
				transPostBtn.disabled = false;
				showStatus('align-content-status', 'error', 'Translation error: ' + err.message);
			});
		});
	}

	// 4. Generate Section with AI
	var genContentBtn = document.getElementById('align-ai-generate-content-btn');
	var insertContentBtn = document.getElementById('align-ai-insert-content-btn');
	var contentOutput = document.getElementById('align-content-output');
	var lastGeneratedHtml = '';

	if (genContentBtn) {
		genContentBtn.addEventListener('click', function() {
			var mode = document.getElementById('align_content_mode').value;
			var prompt = document.getElementById('align_content_prompt').value;
			genContentBtn.disabled = true;
			if (insertContentBtn) insertContentBtn.style.display = 'none';
			if (contentOutput) contentOutput.style.display = 'none';
			showStatus('align-content-status', 'loading', 'Generating content with AI...');

			fetch(restGenContent, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
				body: JSON.stringify({ post_id: postId, mode: mode, prompt: prompt, lang: postLang })
			})
			.then(function(res) { return res.json(); })
			.then(function(data) {
				genContentBtn.disabled = false;
				if (data && data.success && data.html) {
					lastGeneratedHtml = data.html;
					if (contentOutput) {
						contentOutput.innerHTML = data.html;
						contentOutput.style.display = 'block';
					}
					if (insertContentBtn) insertContentBtn.style.display = 'inline-flex';
					showStatus('align-content-status', 'success', 'Content generated! Review below or click Insert.');
				} else {
					showStatus('align-content-status', 'error', (data && data.message) ? data.message : 'Content generation failed.');
				}
			})
			.catch(function(err) {
				genContentBtn.disabled = false;
				showStatus('align-content-status', 'error', 'Error: ' + err.message);
			});
		});
	}

	// 5. Insert Generated Content into Gutenberg
	if (insertContentBtn) {
		insertContentBtn.addEventListener('click', function() {
			if (!lastGeneratedHtml) return;
			if (window.wp && wp.data && wp.blocks) {
				try {
					var parsed = wp.blocks.parse(lastGeneratedHtml);
					wp.data.dispatch('core/block-editor').insertBlocks(parsed);
					showStatus('align-content-status', 'success', 'Blocks successfully inserted into article!');
				} catch (e) {
					showStatus('align-content-status', 'error', 'Failed to insert blocks into Gutenberg.');
				}
			} else {
				showStatus('align-content-status', 'error', 'Gutenberg editor block API not found.');
			}
		});
	}
})();
</script>
