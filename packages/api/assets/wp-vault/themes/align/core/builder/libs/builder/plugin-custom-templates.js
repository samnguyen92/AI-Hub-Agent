/**
 * Align Builder Custom Layout Templates Subsystem
 * Manages user/admin created layout templates with DB sync (REST API) & LocalStorage backup.
 *
 * @package Align
 */

Vvveb.CustomTemplates = {
    templates: [],
    storageKey: 'vvveb_custom_templates',
    editingTemplateId: null,

    init: function () {
        if (!Vvveb.SectionsGroup['Layout']) {
            Vvveb.SectionsGroup['Layout'] = [];
        }

        this.loadStorage();
        this.registerAll();
        this.bindEvents();
        this.fetchServerTemplates();
    },

    loadStorage: function () {
        try {
            const data = localStorage.getItem(this.storageKey);
            this.templates = data ? JSON.parse(data) : [];
            if (!Array.isArray(this.templates)) {
                this.templates = [];
            }
        } catch (e) {
            console.error('[Align Templates] Error loading templates from localStorage:', e);
            this.templates = [];
        }
    },

    saveStorage: function () {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.templates));
            return true;
        } catch (e) {
            console.error('[Align Templates] Error saving templates to localStorage:', e);
            return false;
        }
    },

    fetchServerTemplates: function () {
        const self = this;
        const restUrl = window.VvvebWpConfig ? window.VvvebWpConfig.restCustomTemplatesUrl : null;
        if (!restUrl) return;

        fetch(restUrl, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': (window.VvvebWpConfig && window.VvvebWpConfig.restNonce) ? window.VvvebWpConfig.restNonce : ''
            }
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data && data.status === 'success' && Array.isArray(data.templates)) {
                self.templates = data.templates;
                self.saveStorage();
                self.registerAll();
                if (Vvveb.Builder && typeof Vvveb.Builder.loadSectionGroups === 'function') {
                    Vvveb.Builder.loadSectionGroups();
                }
            }
        })
        .catch(function (err) {
            console.warn('[Align Templates] Failed to fetch server templates, using local cache:', err);
        });
    },

    cleanTemplateHtml: function (html) {
        let clean = (html || '').trim();
        clean = clean.replace(/<style\b(?![^>]*\b(?:id="wp-global-styles"|data-align-preview))[^>]*>[\s\S]*?<\/style>/gi, '');
        clean = clean.replace(/<script\b(?![^>]*\bdata-align-preview)[^>]*>[\s\S]*?<\/script>/gi, '');
        return clean.trim();
    },

    registerAll: function () {
        const self = this;
        this.templates.forEach(function (tpl) {
            self.registerTemplate(tpl);
        });
    },

    registerTemplate: function (tpl) {
        const group = tpl.group || 'Layout';
        if (!Vvveb.SectionsGroup[group]) {
            Vvveb.SectionsGroup[group] = [];
        }

        const type = tpl.id || ('layout/' + (tpl.name || 'template').toLowerCase().replace(/[^a-z0-9]/g, '-'));
        const cleanHtml = this.cleanTemplateHtml(tpl.html);
        const tplCss = (tpl.css || '').trim();
        const tplJs = (tpl.js || '').trim();

        Vvveb.Sections.add(type, {
            name: tpl.name,
            image: tpl.image || 'icons/image.svg',
            html: cleanHtml,
            isCustom: true,
            customId: tpl.id,
            afterDrop: function (node) {
                if (tplCss) {
                    const cur = Vvveb.StyleManager?.getCss?.() || '';
                    if (!cur.includes(tplCss.substring(0, Math.min(80, tplCss.length)).trim())) {
                        const merged = cur ? (cur + '\n\n' + tplCss) : tplCss;
                        Vvveb.StyleManager?.setCss?.(merged);
                        Vvveb.CssEditor?.setValue?.(merged);
                        console.log('[Align Custom Templates] Injected CSS into StyleManager (' + tplCss.length + ' bytes)');
                    }
                }
                if (tplJs) {
                    const cur = Vvveb.JsManager?.getJs?.() || '';
                    if (!cur.includes(tplJs.substring(0, Math.min(80, tplJs.length)).trim())) {
                        const merged = cur ? (cur + '\n\n' + tplJs) : tplJs;
                        Vvveb.JsManager?.setJs?.(merged);
                        Vvveb.JsEditor?.setValue?.(merged);
                        console.log('[Align Custom Templates] Injected JS into JsManager (' + tplJs.length + ' bytes)');
                    }
                }
                return node;
            }
        });

        if (!Vvveb.SectionsGroup[group].includes(type)) {
            Vvveb.SectionsGroup[group].push(type);
        }
    },

    saveCustomTemplate: function () {
        const nameInput = document.getElementById('custom-template-name');
        const groupInput = document.getElementById('custom-template-group');
        const htmlInput = document.getElementById('custom-template-html');
        const cssInput = document.getElementById('custom-template-css');
        const jsInput = document.getElementById('custom-template-js');

        let name = nameInput ? nameInput.value.trim() : '';
        let group = (groupInput && groupInput.value.trim()) ? groupInput.value.trim() : 'Layout';
        let html = htmlInput ? htmlInput.value.trim() : '';
        let css = cssInput ? cssInput.value.trim() : '';
        let js = jsInput ? jsInput.value.trim() : '';

        // Auto-extract inline style/script if pasted into HTML textarea
        const sm = html.match(/<style\b[^>]*>([\s\S]*?)<\/style>/gi);
        if (sm) {
            const extCss = sm.map(s => s.replace(/<\/?style\b[^>]*>/gi, '').trim()).filter(Boolean).join('\n\n');
            if (extCss) { css = css ? (css + '\n\n' + extCss) : extCss; html = html.replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '').trim(); }
        }
        const jm = html.match(/<script\b[^>]*>([\s\S]*?)<\/script>/gi);
        if (jm) {
            const extJs = jm.map(s => s.replace(/<\/?script\b[^>]*>/gi, '').trim()).filter(Boolean).join('\n\n');
            if (extJs) { js = js ? (js + '\n\n' + extJs) : extJs; html = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '').trim(); }
        }

        if (!name) {
            alert('Please enter a template name.');
            if (nameInput) nameInput.focus();
            return;
        }
        if (!html) {
            alert('Please enter the HTML structure for this template.');
            if (htmlInput) htmlInput.focus();
            return;
        }

        const isEditing = Boolean(this.editingTemplateId);
        const id = isEditing ? this.editingTemplateId : ('custom_layout_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6));

        const templateData = {
            id: id,
            name: name,
            group: group,
            image: '',
            html: html,
            css: css,
            js: js,
            updated: new Date().toISOString()
        };

        if (isEditing) {
            const idx = this.templates.findIndex(function (t) { return t.id === id; });
            if (idx !== -1) {
                const oldGroup = this.templates[idx].group || 'Layout';
                if (oldGroup !== group && Vvveb.SectionsGroup[oldGroup]) {
                    const gIdx = Vvveb.SectionsGroup[oldGroup].indexOf(id);
                    if (gIdx !== -1) {
                        Vvveb.SectionsGroup[oldGroup].splice(gIdx, 1);
                    }
                }
                this.templates[idx] = templateData;
            } else {
                this.templates.push(templateData);
            }
        } else {
            this.templates.push(templateData);
        }

        this.saveStorage();
        this.registerTemplate(templateData);

        // Sync with WordPress backend
        const restUrl = window.VvvebWpConfig ? window.VvvebWpConfig.restCustomTemplatesUrl : null;
        if (restUrl) {
            fetch(restUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': (window.VvvebWpConfig && window.VvvebWpConfig.restNonce) ? window.VvvebWpConfig.restNonce : ''
                },
                body: JSON.stringify(templateData)
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                console.log('[Align Templates] Template saved to backend:', data);
            })
            .catch(function (err) {
                console.warn('[Align Templates] Could not persist template to backend:', err);
            });
        }

        if (Vvveb.Builder && typeof Vvveb.Builder.loadSectionGroups === 'function') {
            Vvveb.Builder.loadSectionGroups();
        }

        this.editingTemplateId = null;
        const form = document.getElementById('custom-template-form');
        if (form) form.reset();

        const modalEl = document.getElementById('customTemplateModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            if (modalInstance) modalInstance.hide();
        }

        if (typeof displayToast === 'function') {
            displayToast('success', isEditing ? 'Template Updated' : 'Template Created', 'Template "' + name + '" has been saved successfully!');
        }
    },

    deleteCustomTemplate: function (id) {
        if (!confirm('Are you sure you want to delete this template?')) {
            return;
        }

        const idx = this.templates.findIndex(function (t) { return t.id === id; });
        if (idx !== -1) {
            const removed = this.templates.splice(idx, 1)[0];
            this.saveStorage();

            const group = removed.group || 'Layout';
            if (Vvveb.SectionsGroup[group]) {
                const sIdx = Vvveb.SectionsGroup[group].indexOf(removed.id);
                if (sIdx !== -1) {
                    Vvveb.SectionsGroup[group].splice(sIdx, 1);
                }
            }

            const restUrl = window.VvvebWpConfig ? window.VvvebWpConfig.restCustomTemplatesUrl : null;
            if (restUrl) {
                fetch(restUrl, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (window.VvvebWpConfig && window.VvvebWpConfig.restNonce) ? window.VvvebWpConfig.restNonce : ''
                    },
                    body: JSON.stringify({ id: id })
                })
                .catch(function (err) {
                    console.warn('[Align Templates] Error deleting from backend:', err);
                });
            }

            if (Vvveb.Builder && typeof Vvveb.Builder.loadSectionGroups === 'function') {
                Vvveb.Builder.loadSectionGroups();
            }

            if (typeof displayToast === 'function') {
                displayToast('info', 'Template Deleted', 'Template has been removed.');
            }
        }
    },

    openCreateModal: function () {
        this.editingTemplateId = null;
        const form = document.getElementById('custom-template-form');
        if (form) form.reset();

        const modalLabel = document.getElementById('customTemplateModalLabel');
        if (modalLabel) {
            modalLabel.innerHTML = '<i class="la la-layer-group text-primary"></i> Create Layout Template';
        }
        const saveBtn = document.getElementById('save-custom-template-btn');
        if (saveBtn) {
            saveBtn.innerHTML = '<i class="la la-check"></i> Save Template';
        }

        const modalEl = document.getElementById('customTemplateModal');
        if (!modalEl) return;

        const useElBtn = document.getElementById('btn-use-selected-canvas-element');
        let selectedNode = null;
        try {
            selectedNode = Vvveb.Builder.selectedEl;
        } catch (e) {}

        if (useElBtn) {
            if (selectedNode && selectedNode.outerHTML) {
                useElBtn.style.display = 'inline-flex';
            } else {
                useElBtn.style.display = 'none';
            }
        }

        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
    },

    openEditModal: function (id) {
        let tpl = this.templates.find(function (t) { return t.id === id; });
        if (!tpl) {
            const sectionDef = Vvveb.Sections.get(id);
            if (sectionDef && sectionDef.customId) {
                tpl = this.templates.find(function (t) { return t.id === sectionDef.customId; });
            }
        }
        if (!tpl) return console.warn('[Align Templates] Template not found:', id);

        this.editingTemplateId = tpl.id;
        const setVal = function (fid, val) { const el = document.getElementById(fid); if (el) el.value = val || ''; };
        setVal('custom-template-name', tpl.name);
        setVal('custom-template-group', tpl.group || 'Layout');
        setVal('custom-template-html', tpl.html);
        setVal('custom-template-css', tpl.css);
        setVal('custom-template-js', tpl.js);

        const modalLabel = document.getElementById('customTemplateModalLabel');
        if (modalLabel) modalLabel.innerHTML = '<i class="la la-edit text-primary"></i> Edit Layout Template';
        const saveBtn = document.getElementById('save-custom-template-btn');
        if (saveBtn) saveBtn.innerHTML = '<i class="la la-check"></i> Update Template';

        const useElBtn = document.getElementById('btn-use-selected-canvas-element');
        if (useElBtn) useElBtn.style.display = 'none';

        const modalEl = document.getElementById('customTemplateModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    },

    renderUI: function () {
        const self = this;
        const layoutContainers = document.querySelectorAll('li[data-section="Layout"]');
        layoutContainers.forEach(function (container) {
            const labelHeader = container.querySelector('label.header');
            if (labelHeader && !labelHeader.querySelector('.add-custom-template-header-btn')) {
                labelHeader.style.display = 'flex';
                labelHeader.style.alignItems = 'center';

                const addBtn = document.createElement('button');
                addBtn.className = 'btn btn-xs add-custom-template-header-btn';
                addBtn.setAttribute('title', 'Create Template');
                addBtn.style.cssText = 'margin-left: auto; margin-right: 20px; padding: 2px 7px; font-size: 11px; font-weight: 600; border-radius: 4px; border: 1px solid rgba(13, 110, 253, 0.3); background-color: rgba(13, 110, 253, 0.08); color: #0d6efd; line-height: 1.2; display: inline-flex; align-items: center; gap: 3px; cursor: pointer;';
                addBtn.innerHTML = '<i class="la la-plus"></i> Create';
                addBtn.onclick = function (e) { e.stopPropagation(); e.preventDefault(); self.openCreateModal(); };

                const arrow = labelHeader.querySelector('.header-arrow');
                if (arrow) labelHeader.insertBefore(addBtn, arrow);
                else labelHeader.appendChild(addBtn);
            }

            const ol = container.querySelector('ol');
            if (ol && ol.children.length === 0) {
                ol.innerHTML = `<li class="empty-template-placeholder" style="list-style:none;padding:24px 15px;text-align:center;color:#64748b;font-size:12px;">
                    <i class="la la-layer-group" style="font-size:26px;opacity:0.4;display:block;margin-bottom:6px;"></i>
                    No layout templates yet.<br>
                    <button type="button" class="btn btn-outline-primary btn-xs mt-2 trigger-create-tpl-btn" style="padding:3px 10px;font-size:11.5px;border-radius:4px;">
                        <i class="la la-plus"></i> Create Template
                    </button>
                </li>`;
                ol.querySelector('.trigger-create-tpl-btn')?.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.openCreateModal();
                });
            } else if (ol) {
                ol.querySelectorAll('li[data-drag-type="section"]').forEach(function (li) {
                    const type = li.getAttribute('data-type');
                    const sectionDef = Vvveb.Sections.get(type);
                    if (sectionDef && sectionDef.isCustom && !li.querySelector('.custom-tpl-actions')) {
                        const oldDel = li.querySelector(':scope > .delete-custom-tpl-btn');
                        if (oldDel) oldDel.remove();

                        const actionsWrap = document.createElement('div');
                        actionsWrap.className = 'custom-tpl-actions';
                        actionsWrap.style.cssText = 'position:absolute;top:8px;right:8px;display:flex;align-items:center;gap:4px;z-index:10;';

                        const makeBtn = function (cls, title, icon, color, bg, cb) {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.className = cls;
                            b.title = title;
                            b.style.cssText = `width:24px;height:24px;padding:0;border:none;display:inline-flex;align-items:center;justify-content:center;background:${bg};color:${color};border-radius:4px;cursor:pointer;font-size:12px;transition:all 0.15s;line-height:1;`;
                            b.innerHTML = `<i class="${icon}"></i>`;
                            b.onmouseenter = function () { b.style.background = color; b.style.color = '#fff'; };
                            b.onmouseleave = function () { b.style.background = bg; b.style.color = color; };
                            b.onclick = function (e) { e.stopPropagation(); e.preventDefault(); cb(); };
                            return b;
                        };

                        const editBtn = makeBtn('edit-custom-tpl-btn', 'Edit Template (HTML, CSS, JS)', 'la la-edit', '#0284c7', 'rgba(14,165,233,0.15)', function () {
                            self.openEditModal(sectionDef.customId || type);
                        });
                        const delBtn = makeBtn('delete-custom-tpl-btn', 'Delete Template', 'la la-trash', '#ef4444', 'rgba(239,68,68,0.15)', function () {
                            self.deleteCustomTemplate(sectionDef.customId || type);
                        });

                        actionsWrap.appendChild(editBtn);
                        actionsWrap.appendChild(delBtn);
                        li.style.position = 'relative';
                        li.appendChild(actionsWrap);
                    }
                });
            }
        });
    },

    bindEvents: function () {
        const self = this;

        // Top toolbar "Create Template" button
        document.getElementById('btn-create-template-action')?.addEventListener('click', function (e) {
            e.preventDefault();
            self.openCreateModal();
        });

        // Save button in modal
        document.getElementById('save-custom-template-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            self.saveCustomTemplate();
        });

        // Use selected element button
        document.getElementById('btn-use-selected-canvas-element')?.addEventListener('click', function (e) {
            e.preventDefault();
            try {
                const el = Vvveb.Builder.selectedEl;
                if (el) {
                    const htmlInput = document.getElementById('custom-template-html');
                    const nameInput = document.getElementById('custom-template-name');
                    if (htmlInput) htmlInput.value = el.outerHTML;
                    if (nameInput && !nameInput.value.trim()) {
                        nameInput.value = (el.tagName.toLowerCase() + ' layout').replace(/^\w/, c => c.toUpperCase());
                    }
                }
            } catch (err) {
                console.error('[Align Templates] Error grabbing selected element:', err);
            }
        });

        // Reset edit state when modal closes
        document.getElementById('customTemplateModal')?.addEventListener('hidden.bs.modal', function () {
            self.editingTemplateId = null;
            document.getElementById('custom-template-form')?.reset();
        });
    }
};

// Hook into Vvveb.Builder.loadSectionGroups to update UI
if (typeof Vvveb !== 'undefined' && Vvveb.Builder) {
    const origLoadSectionGroups = Vvveb.Builder.loadSectionGroups;
    Vvveb.Builder.loadSectionGroups = function () {
        if (typeof origLoadSectionGroups === 'function') {
            origLoadSectionGroups.apply(this, arguments);
        }
        Vvveb.CustomTemplates.renderUI();
    };
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    Vvveb.CustomTemplates.init();
});
