<?php
/**
 * Align Settings - Languages Tab
 *
 * Manage multi-language configuration for Align Builder.
 * Follows Align Settings Style Guide: transparent buttons, card system, accordion standard.
 *
 * @package Align
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$languages    = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_languages() : array();
$default_lang = class_exists( 'Align_Language_Manager' ) ? Align_Language_Manager::get_default_lang() : 'en';
$lang_nonce   = wp_create_nonce( 'align_language_nonce' );
?>

<div class="align-settings-card">
	<div class="align-card-header is-accordion" id="lang-list-header">
		<div>
			<h3 class="align-card-title"><?php esc_html_e( 'Active Languages', 'align' ); ?></h3>
			<p class="align-card-desc"><?php esc_html_e( 'Manage languages for pages in Align Builder. Translate pages and customize writing scripts.', 'align' ); ?></p>
		</div>
		<div class="align-card-header-actions">
			<button type="button" class="button button-primary" id="align-lang-add-btn">
				<?php esc_html_e( 'Add Language', 'align' ); ?>
			</button>
		</div>
	</div>

	<!-- Add Language Form (Accordion, default closed) -->
	<div class="align-card-body" id="align-lang-add-form" style="display: none; border-bottom: 1px solid #e2e8f0;">
		<div class="align-lang-form-grid">
			<div class="align-form-field">
				<label for="lang-code"><?php esc_html_e( 'Language Code', 'align' ); ?> <span class="required">*</span></label>
				<input type="text" id="lang-code" class="regular-text" placeholder="e.g. zh, vi, ko, ja" maxlength="10" style="width:100%"/>
				<p class="description"><?php esc_html_e( 'Short ISO code (lowercase).', 'align' ); ?></p>
			</div>
			<div class="align-form-field">
				<label for="lang-name"><?php esc_html_e( 'Language Name', 'align' ); ?> <span class="required">*</span></label>
				<input type="text" id="lang-name" class="regular-text" placeholder="e.g. Traditional Chinese" maxlength="60" style="width:100%"/>
			</div>
			<div class="align-form-field">
				<label for="lang-script"><?php esc_html_e( 'Writing Script / Variant', 'align' ); ?></label>
				<select id="lang-script" style="width:100%; height:32px;">
					<option value="default"><?php esc_html_e( 'Standard / Default', 'align' ); ?></option>
					<option value="traditional-hk"><?php esc_html_e( 'Hong Kong Traditional (繁體中文 - 香港)', 'align' ); ?></option>
					<option value="traditional-tw"><?php esc_html_e( 'Taiwan Traditional (繁體中文 - 台灣)', 'align' ); ?></option>
					<option value="traditional"><?php esc_html_e( 'Generic Traditional (繁體中文)', 'align' ); ?></option>
					<option value="simplified"><?php esc_html_e( 'Simplified Chinese (简体中文)', 'align' ); ?></option>
				</select>
			</div>
			<div class="align-form-field">
				<label for="lang-slug"><?php esc_html_e( 'URL Slug Prefix', 'align' ); ?></label>
				<input type="text" id="lang-slug" class="regular-text" placeholder="e.g. zh, vi" maxlength="20" style="width:100%"/>
				<p class="description"><?php esc_html_e( 'Subpath /slug/page-url/.', 'align' ); ?></p>
			</div>
			<div class="align-form-field" style="grid-column: 1 / -1;">
				<label for="lang-flag"><?php esc_html_e( 'Flag Emoji', 'align' ); ?></label>
				<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
					<input type="text" id="lang-flag" class="regular-text" placeholder="🇭🇰" maxlength="8" style="width:70px; font-size:18px; text-align:center; height:32px;"/>
					<div class="align-flag-picks" style="display:flex; gap:4px; flex-wrap:wrap;">
						<span class="align-flag-chip" data-flag="🇺🇸">🇺🇸</span>
						<span class="align-flag-chip" data-flag="🇬🇧">🇬🇧</span>
						<span class="align-flag-chip" data-flag="🇭🇰">🇭🇰</span>
						<span class="align-flag-chip" data-flag="🇨🇳">🇨🇳</span>
						<span class="align-flag-chip" data-flag="🇹🇼">🇹🇼</span>
						<span class="align-flag-chip" data-flag="🇸🇬">🇸🇬</span>
						<span class="align-flag-chip" data-flag="🇻🇳">🇻🇳</span>
						<span class="align-flag-chip" data-flag="🇯🇵">🇯🇵</span>
						<span class="align-flag-chip" data-flag="🇰🇷">🇰🇷</span>
						<span class="align-flag-chip" data-flag="🇦🇺">🇦🇺</span>
					</div>
				</div>
			</div>
			<div class="align-form-field" style="grid-column: 1 / -1;">
				<label for="lang-prompt-hint"><?php esc_html_e( 'Custom Translation Style & Instructions', 'align' ); ?></label>
				<textarea id="lang-prompt-hint" rows="2" style="width:100%; font-family:monospace; font-size:12px; border-radius:6px; border:1px solid #cbd5e1; padding:6px 8px;" placeholder="<?php esc_attr_e( 'Optional: Custom style guidelines, audience tone, or specific terminology rules...', 'align' ); ?>"></textarea>
			</div>
		</div>
		<div class="align-form-actions" style="margin-top:12px; display:flex; gap:8px; align-items:center;">
			<button type="button" class="button button-primary" id="align-lang-save-btn"><?php esc_html_e( 'Add Language', 'align' ); ?></button>
			<button type="button" class="button button-secondary" id="align-lang-cancel-btn"><?php esc_html_e( 'Cancel', 'align' ); ?></button>
			<span id="align-lang-form-status" style="font-size:12px; color:#64748b;"></span>
		</div>
	</div>

	<!-- Languages Table -->
	<div class="align-card-body" id="align-lang-table-wrap">
		<?php if ( empty( $languages ) ) : ?>
			<p style="color:#94a3b8; font-size:13px; padding:8px 0;"><?php esc_html_e( 'No languages configured yet.', 'align' ); ?></p>
		<?php else : ?>
		<table class="align-lang-table wp-list-table widefat fixed striped" id="align-lang-table">
			<thead>
				<tr>
					<th style="width:50px;"><?php esc_html_e( 'Flag', 'align' ); ?></th>
					<th style="width:70px;"><?php esc_html_e( 'Code', 'align' ); ?></th>
					<th><?php esc_html_e( 'Name', 'align' ); ?></th>
					<th style="width:140px;"><?php esc_html_e( 'Script', 'align' ); ?></th>
					<th style="width:110px;"><?php esc_html_e( 'URL Prefix', 'align' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Status', 'align' ); ?></th>
					<th style="width:140px;"><?php esc_html_e( 'Actions', 'align' ); ?></th>
				</tr>
			</thead>
			<tbody id="align-lang-tbody">
				<?php foreach ( $languages as $lang ) :
					$is_default = ! empty( $lang['is_default'] );
					$url_prefix = ! empty( $lang['slug'] ) ? '/' . esc_html( $lang['slug'] ) . '/…' : esc_html__( '(root)', 'align' );
					$sc         = $lang['script'] ?? 'default';
				?>
				<tr data-lang-code="<?php echo esc_attr( $lang['code'] ); ?>">
					<td style="font-size:20px; text-align:center;"><?php echo esc_html( $lang['flag'] ?? '' ); ?></td>
					<td><code><?php echo esc_html( $lang['code'] ); ?></code></td>
					<td><strong><?php echo esc_html( $lang['name'] ); ?></strong></td>
					<td>
						<?php if ( 'traditional-hk' === $sc ) : ?>
							<span class="align-lang-badge-script" style="color:#0f766e; background:rgba(15,118,110,0.08); border-color:rgba(15,118,110,0.25);"><?php esc_html_e( 'HK Traditional (香港)', 'align' ); ?></span>
						<?php elseif ( 'traditional-tw' === $sc ) : ?>
							<span class="align-lang-badge-script" style="color:#0284c7; background:rgba(2,132,199,0.08); border-color:rgba(2,132,199,0.25);"><?php esc_html_e( 'TW Traditional (台灣)', 'align' ); ?></span>
						<?php elseif ( 'traditional' === $sc ) : ?>
							<span class="align-lang-badge-script"><?php esc_html_e( 'Traditional (繁體)', 'align' ); ?></span>
						<?php elseif ( 'simplified' === $sc ) : ?>
							<span class="align-lang-badge-script" style="color:#d97706; background:rgba(217,119,6,0.08); border-color:rgba(217,119,6,0.25);"><?php esc_html_e( 'Simplified (简体)', 'align' ); ?></span>
						<?php else : ?>
							<span style="font-size:11px; color:#94a3b8;"><?php esc_html_e( 'Standard', 'align' ); ?></span>
						<?php endif; ?>
					</td>
					<td><code style="font-size:11px;"><?php echo $url_prefix; ?></code></td>
					<td>
						<?php if ( $is_default ) : ?>
							<span class="align-lang-badge-default"><?php esc_html_e( 'Default', 'align' ); ?></span>
						<?php else : ?>
							<span class="align-lang-badge-active"><?php esc_html_e( 'Active', 'align' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<div style="display:flex; gap:6px; align-items:center;">
							<button type="button"
								class="button button-secondary align-lang-edit-btn"
								data-lang="<?php echo esc_attr( $lang['code'] ); ?>"
								data-name="<?php echo esc_attr( $lang['name'] ); ?>"
								data-flag="<?php echo esc_attr( $lang['flag'] ?? '' ); ?>"
								data-slug="<?php echo esc_attr( $lang['slug'] ?? '' ); ?>"
								data-script="<?php echo esc_attr( $sc ); ?>"
								data-prompt="<?php echo esc_attr( $lang['prompt_hint'] ?? '' ); ?>"
								data-default="<?php echo $is_default ? '1' : '0'; ?>">
								<?php esc_html_e( 'Edit', 'align' ); ?>
							</button>
							<?php if ( ! $is_default ) : ?>
								<button type="button"
									class="button button-danger align-lang-remove-btn"
									data-lang="<?php echo esc_attr( $lang['code'] ); ?>"
									data-name="<?php echo esc_attr( $lang['name'] ); ?>">
									<?php esc_html_e( 'Remove', 'align' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
</div>

<!-- Edit Language Modal -->
<div id="align-lang-edit-modal" style="display:none; position:fixed; z-index:99999; inset:0; background:rgba(15,23,42,0.45); backdrop-filter:blur(2px); align-items:center; justify-content:center;">
	<div class="align-settings-card" style="width:480px; max-width:95vw; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); margin:0;">
		<div class="align-card-header" style="padding:14px 20px;">
			<div>
				<h3 class="align-card-title" id="align-edit-modal-title"><?php esc_html_e( 'Edit Language', 'align' ); ?></h3>
				<p class="align-card-desc"><?php esc_html_e( 'Customize name, flag emoji, and writing script.', 'align' ); ?></p>
			</div>
			<button type="button" id="align-edit-close-x" style="background:none; border:none; cursor:pointer; font-size:20px; color:#94a3b8; line-height:1;">&times;</button>
		</div>
		<div class="align-card-body" style="padding:16px 20px;">
			<input type="hidden" id="edit-lang-code" />
			<input type="hidden" id="edit-lang-is-default" />
			<div style="display:flex; flex-direction:column; gap:14px;">
				<div class="align-form-field">
					<label for="edit-lang-name"><?php esc_html_e( 'Language Name', 'align' ); ?> <span class="required">*</span></label>
					<input type="text" id="edit-lang-name" class="regular-text" style="width:100%; height:32px;" />
				</div>
				<div class="align-form-field">
					<label for="edit-lang-flag"><?php esc_html_e( 'Flag Emoji', 'align' ); ?></label>
					<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
						<input type="text" id="edit-lang-flag" class="regular-text" maxlength="8" style="width:70px; font-size:20px; text-align:center; height:32px;" />
						<div class="align-flag-picks" style="display:flex; gap:4px; flex-wrap:wrap;">
							<span class="align-flag-chip" data-flag="🇺🇸">🇺🇸</span>
							<span class="align-flag-chip" data-flag="🇬🇧">🇬🇧</span>
							<span class="align-flag-chip" data-flag="🇨🇳">🇨🇳</span>
							<span class="align-flag-chip" data-flag="🇭🇰">🇭🇰</span>
							<span class="align-flag-chip" data-flag="🇹🇼">🇹🇼</span>
							<span class="align-flag-chip" data-flag="🇸🇬">🇸🇬</span>
							<span class="align-flag-chip" data-flag="🇻🇳">🇻🇳</span>
							<span class="align-flag-chip" data-flag="🇯🇵">🇯🇵</span>
							<span class="align-flag-chip" data-flag="🇰🇷">🇰🇷</span>
							<span class="align-flag-chip" data-flag="🇦🇺">🇦🇺</span>
						</div>
					</div>
					<p class="description"><?php esc_html_e( 'Freely enter or click any flag emoji (including for English).', 'align' ); ?></p>
				</div>
				<div class="align-form-field">
					<label for="edit-lang-script"><?php esc_html_e( 'Writing Script / Variant', 'align' ); ?></label>
					<select id="edit-lang-script" style="width:100%; height:32px;">
						<option value="default"><?php esc_html_e( 'Standard / Default', 'align' ); ?></option>
						<option value="traditional-hk"><?php esc_html_e( 'Hong Kong Traditional (繁體中文 - 香港)', 'align' ); ?></option>
						<option value="traditional-tw"><?php esc_html_e( 'Taiwan Traditional (繁體中文 - 台灣)', 'align' ); ?></option>
						<option value="traditional"><?php esc_html_e( 'Generic Traditional (繁體中文)', 'align' ); ?></option>
						<option value="simplified"><?php esc_html_e( 'Simplified Chinese (简体中文)', 'align' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Choose regional Chinese localization rules and terminology conventions.', 'align' ); ?></p>
				</div>
				<div class="align-form-field">
					<label for="edit-lang-prompt-hint"><?php esc_html_e( 'Custom Translation Style & Instructions', 'align' ); ?></label>
					<textarea id="edit-lang-prompt-hint" rows="3" style="width:100%; font-family:monospace; font-size:12px; border-radius:6px; border:1px solid #cbd5e1; padding:6px 8px;" placeholder="<?php esc_attr_e( 'e.g. Hong Kong corporate tone. Use terms like 僱員補償, 索償, 強積金 MPF. Formal written Chinese (書面語).', 'align' ); ?>"></textarea>
					<p class="description"><?php esc_html_e( 'Custom instructions or glossaries injected into AI translations for this language.', 'align' ); ?></p>
				</div>
				<div class="align-form-field" id="edit-lang-slug-row">
					<label for="edit-lang-slug"><?php esc_html_e( 'URL Slug Prefix', 'align' ); ?></label>
					<input type="text" id="edit-lang-slug" class="regular-text" style="width:100%; height:32px;" />
					<p class="description"><?php esc_html_e( 'URL subpath prefix (e.g. zh, vi). Locked for default language.', 'align' ); ?></p>
				</div>
			</div>
			<div style="margin-top:20px; display:flex; gap:8px; justify-content:flex-end; align-items:center;">
				<span id="align-edit-status" style="font-size:12px; margin-right:auto;"></span>
				<button type="button" class="button button-secondary" id="align-edit-cancel-btn"><?php esc_html_e( 'Cancel', 'align' ); ?></button>
				<button type="button" class="button button-primary" id="align-edit-save-btn"><?php esc_html_e( 'Save Changes', 'align' ); ?></button>
			</div>
		</div>
	</div>
</div>

<style>
.align-lang-table { border-collapse: collapse; }
.align-lang-table th { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
.align-lang-table td { vertical-align: middle; padding: 10px 12px; }
.align-lang-badge-default { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; background: rgba(2,132,199,0.1); color: #0284c7; border: 1px solid rgba(2,132,199,0.3); }
.align-lang-badge-active { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; background: rgba(34,197,94,0.08); color: #16a34a; border: 1px solid rgba(34,197,94,0.25); }
.align-lang-badge-script { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; background: rgba(147,51,234,0.08); color: #9333ea; border: 1px solid rgba(147,51,234,0.25); }
.align-flag-chip { cursor: pointer; font-size: 16px; padding: 2px 5px; border-radius: 4px; border: 1px solid #e2e8f0; transition: all 0.15s; user-select: none; }
.align-flag-chip:hover { background: #f0f9ff; border-color: #bae6fd; transform: scale(1.15); }
.align-lang-form-grid { display: grid; grid-template-columns: 1fr 1.2fr 1.2fr 1fr; gap: 14px; align-items: start; }
.align-form-field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
.align-form-field label .required { color: #dc2626; }
.align-form-field .description { font-size: 11px; color: #9ca3af; margin-top: 3px; }
@media (max-width: 900px) { .align-lang-form-grid { grid-template-columns: 1fr 1fr; } }
</style>

<script>
(function() {
    const nonce = <?php echo json_encode( $lang_nonce ); ?>;
    const ajaxUrl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

    const addBtn    = document.getElementById('align-lang-add-btn');
    const cancelBtn = document.getElementById('align-lang-cancel-btn');
    const form      = document.getElementById('align-lang-add-form');
    const saveBtn   = document.getElementById('align-lang-save-btn');
    const status    = document.getElementById('align-lang-form-status');

    if (addBtn) {
        addBtn.addEventListener('click', function() {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if (form.style.display !== 'none') document.getElementById('lang-code').focus();
        });
    }
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            form.style.display = 'none';
            clearAddForm();
        });
    }

    function clearAddForm() {
        ['lang-code','lang-name','lang-slug','lang-flag','lang-prompt-hint'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const sc = document.getElementById('lang-script');
        if (sc) sc.value = 'default';
        if (status) status.textContent = '';
    }

    const flagMap = { zh: '🇭🇰', 'zh-hk': '🇭🇰', cn: '🇨🇳', en: '🇺🇸', vi: '🇻🇳', ja: '🇯🇵', ko: '🇰🇷', fr: '🇫🇷', de: '🇩🇪', es: '🇪🇸' };
    const codeInput = document.getElementById('lang-code');
    const flagInput = document.getElementById('lang-flag');
    if (codeInput && flagInput) {
        codeInput.addEventListener('input', function() {
            const val = this.value.trim().toLowerCase();
            if (!flagInput.value && flagMap[val]) flagInput.value = flagMap[val];
            if (val.startsWith('zh') && document.getElementById('lang-script')) {
                document.getElementById('lang-script').value = 'traditional-hk';
            }
        });
    }
    // Flag chip click delegation for Add Form
    document.querySelectorAll('#align-lang-add-form .align-flag-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            if (flagInput) flagInput.value = this.dataset.flag;
        });
    });

    // Save new language
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            const code   = (document.getElementById('lang-code')?.value || '').trim();
            const name   = (document.getElementById('lang-name')?.value || '').trim();
            const slug   = (document.getElementById('lang-slug')?.value || '').trim();
            const script = (document.getElementById('lang-script')?.value || 'default');
            let   flag   = (document.getElementById('lang-flag')?.value || '').trim();
            if (!flag && flagMap[code.toLowerCase()]) flag = flagMap[code.toLowerCase()];

            const prompt = (document.getElementById('lang-prompt-hint')?.value || '').trim();

            if (!code || !name) {
                if (status) { status.textContent = 'Language code and name are required.'; status.style.color = '#dc2626'; }
                return;
            }

            saveBtn.disabled = true;
            if (status) { status.textContent = 'Adding language...'; status.style.color = '#64748b'; }

            const fd = new FormData();
            fd.append('action', 'align_language_add');
            fd.append('nonce', nonce);
            fd.append('code', code);
            fd.append('name', name);
            fd.append('slug', slug);
            fd.append('flag', flag);
            fd.append('script', script);
            fd.append('prompt_hint', prompt);

            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(function(data) {
                    saveBtn.disabled = false;
                    if (data.success) {
                        if (status) { status.textContent = 'Added successfully. Refreshing...'; status.style.color = '#16a34a'; }
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        if (status) { status.textContent = data.data || 'Failed to add.'; status.style.color = '#dc2626'; }
                    }
                })
                .catch(function(err) {
                    saveBtn.disabled = false;
                    if (status) { status.textContent = 'Network error.'; status.style.color = '#dc2626'; }
                });
        });
    }

    // Modal Edit Language elements
    const editModal   = document.getElementById('align-lang-edit-modal');
    const editCloseX  = document.getElementById('align-edit-close-x');
    const editCancel  = document.getElementById('align-edit-cancel-btn');
    const editSave    = document.getElementById('align-edit-save-btn');
    const editStatus  = document.getElementById('align-edit-status');
    const editCode    = document.getElementById('edit-lang-code');
    const editName    = document.getElementById('edit-lang-name');
    const editFlag    = document.getElementById('edit-lang-flag');
    const editScript  = document.getElementById('edit-lang-script');
    const editSlug    = document.getElementById('edit-lang-slug');
    const editSlugRow = document.getElementById('edit-lang-slug-row');
    const editTitle   = document.getElementById('align-edit-modal-title');

    function closeEditModal() {
        if (editModal) editModal.style.display = 'none';
        if (editStatus) editStatus.textContent = '';
    }

    if (editCloseX) editCloseX.addEventListener('click', closeEditModal);
    if (editCancel) editCancel.addEventListener('click', closeEditModal);
    document.querySelectorAll('#align-lang-edit-modal .align-flag-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            if (editFlag) editFlag.value = this.dataset.flag;
        });
    });
    // Open Edit Modal
    document.querySelectorAll('.align-lang-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const code      = btn.dataset.lang;
            const name      = btn.dataset.name;
            const flag      = btn.dataset.flag || '';
            const slug      = btn.dataset.slug || '';
            const script    = btn.dataset.script || 'default';
            const prompt    = btn.dataset.prompt || '';
            const isDefault = btn.dataset.default === '1';

            editCode.value   = code;
            editName.value   = name;
            editFlag.value   = flag;
            editScript.value = script;
            editSlug.value   = slug;

            const editPrompt = document.getElementById('edit-lang-prompt-hint');
            if (editPrompt) editPrompt.value = prompt;

            if (editTitle) editTitle.textContent = 'Edit Language: ' + name + ' (' + code + ')';

            if (isDefault) {
                if (editSlugRow) editSlugRow.style.display = 'none';
            } else {
                if (editSlugRow) editSlugRow.style.display = 'block';
            }

            if (editStatus) editStatus.textContent = '';
            if (editModal) editModal.style.display = 'flex';
            editName.focus();
        });
    });

    // Save Edit Modal
    if (editSave) {
        editSave.addEventListener('click', function() {
            const code   = editCode.value.trim();
            const name   = editName.value.trim();
            const flag   = editFlag.value.trim();
            const script = editScript.value;
            const slug   = editSlug.value.trim();
            const prompt = (document.getElementById('edit-lang-prompt-hint')?.value || '').trim();

            if (!name) {
                if (editStatus) { editStatus.textContent = 'Language name is required.'; editStatus.style.color = '#dc2626'; }
                return;
            }

            editSave.disabled = true;
            if (editStatus) { editStatus.textContent = 'Saving changes...'; editStatus.style.color = '#64748b'; }

            const fd = new FormData();
            fd.append('action', 'align_language_update');
            fd.append('nonce', nonce);
            fd.append('code', code);
            fd.append('name', name);
            fd.append('flag', flag);
            fd.append('script', script);
            fd.append('slug', slug);
            fd.append('prompt_hint', prompt);

            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(function(data) {
                    editSave.disabled = false;
                    if (data.success) {
                        if (editStatus) { editStatus.textContent = 'Saved successfully! Refreshing...'; editStatus.style.color = '#16a34a'; }
                        setTimeout(() => window.location.reload(), 600);
                    } else {
                        if (editStatus) { editStatus.textContent = data.data || 'Failed to update.'; editStatus.style.color = '#dc2626'; }
                    }
                })
                .catch(function() {
                    editSave.disabled = false;
                    if (editStatus) { editStatus.textContent = 'Network error.'; editStatus.style.color = '#dc2626'; }
                });
        });
    }

    // Remove language
    document.querySelectorAll('.align-lang-remove-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const code = btn.dataset.lang;
            const name = btn.dataset.name;
            if (!confirm('Remove language "' + name + '" (' + code + ')? Existing translated pages will remain.')) return;

            btn.disabled = true;
            btn.textContent = 'Removing...';

            const fd = new FormData();
            fd.append('action', 'align_language_remove');
            fd.append('nonce', nonce);
            fd.append('code', code);

            fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(function(data) {
                    if (data.success) {
                        const row = document.querySelector('tr[data-lang-code="' + code + '"]');
                        if (row) row.remove();
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Remove';
                        alert(data.data || 'Failed to remove language.');
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = 'Remove';
                });
        });
    });
})();
</script>
