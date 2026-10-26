/*
 Custom Blocks Plugin for VvvebJS Builder
 Manages creation and persistence of custom HTML/CSS/JS blocks in local storage.
*/

Vvveb.CustomBlocks = {
    blocks: [],
    storageKey: 'vvveb_custom_blocks',

    // Clean custom-block outer wrappers from HTML
    cleanWrapper: function (html) {
        if (!html || typeof html !== 'string') return '';
        let cleaned = html.trim();
        const wrapperRegex = /^<div\s+class=["'][^"']*custom-block[^"']*["'][^>]*>([\s\S]*)<\/div>$/i;
        while (wrapperRegex.test(cleaned)) {
            cleaned = cleaned.replace(wrapperRegex, '$1').trim();
        }
        return cleaned;
    },

    // Clean empty div.custom-block elements from Canvas DOM
    cleanCanvasEmptyBlocks: function () {
        try {
            const iframe = document.getElementById('iframe1');
            const frameDoc = (Vvveb.Builder && Vvveb.Builder.frameDoc) ? Vvveb.Builder.frameDoc : (iframe ? iframe.contentDocument : window.FrameDocument);
            if (!frameDoc) return;

            const emptyBlocks = frameDoc.querySelectorAll('div.custom-block, div[class*="custom-block"]');
            emptyBlocks.forEach(function (el) {
                if (!el.textContent.trim() && el.children.length === 0) {
                    el.remove();
                }
            });
        } catch (e) {
            console.error('Error cleaning canvas empty blocks:', e);
        }
    },

    // Initialize custom blocks subsystem and render stored blocks
    init: function () {
        if (!Vvveb.BlocksGroup['Custom']) {
            Vvveb.BlocksGroup['Custom'] = [];
        }

        this.loadStorage();
        this.registerBlocks();
        this.bindEvents();
        this.renderAddBlockUI();
        
        const self = this;
        const cleanHandler = function () {
            self.cleanCanvasEmptyBlocks();
        };
        window.addEventListener('Vvveb.iframe.loaded', cleanHandler);
        window.addEventListener('vvveb.iframe.loaded', cleanHandler);
        this.cleanCanvasEmptyBlocks();
    },

    // Load saved blocks array from LocalStorage
    loadStorage: function () {
        try {
            const data = localStorage.getItem(this.storageKey);
            this.blocks = data ? JSON.parse(data) : [];
            if (!Array.isArray(this.blocks)) {
                this.blocks = [];
            }

            let isModified = false;
            const self = this;
            this.blocks.forEach(function (block) {
                if (block && block.html) {
                    const cleaned = self.cleanWrapper(block.html);
                    if (cleaned !== block.html) {
                        block.html = cleaned;
                        isModified = true;
                    }
                }
            });

            if (isModified) {
                this.saveStorage();
            }
        } catch (e) {
            console.error('Error reading custom blocks from localStorage:', e);
            this.blocks = [];
        }
    },

    // Persist blocks array to LocalStorage
    saveStorage: function () {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.blocks));
            return true;
        } catch (e) {
            console.error('Error saving custom blocks to localStorage:', e);
            return false;
        }
    },

    // Register all loaded blocks into Vvveb engine
    registerBlocks: function () {
        const self = this;
        this.blocks.forEach(function (block) {
            self.addBlockToVvveb(block);
        });
    },

    cleanBlockHtml: function (html) {
        let clean = (html || '').trim();
        clean = clean.replace(/<style\b(?![^>]*\b(?:id="wp-global-styles"|data-align-preview))[^>]*>[\s\S]*?<\/style>/gi, '');
        clean = clean.replace(/<script\b(?![^>]*\bdata-align-preview)[^>]*>[\s\S]*?<\/script>/gi, '');
        return clean.trim();
    },

    // Add a single custom block definition to Vvveb.Blocks and Vvveb.BlocksGroup['Custom']
    addBlockToVvveb: function (block) {
        const type = block.id || ('custom/block_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7));
        const rawHtml = this.cleanWrapper(block.html || '');
        const cleanHtml = this.cleanBlockHtml(rawHtml);
        const blockCss = (block.css || '').trim();
        const blockJs = (block.js || '').trim();

        Vvveb.Blocks.add(type, {
            name: block.name,
            image: block.image || 'icons/code.svg',
            html: cleanHtml,
            afterDrop: function (node) {
                if (blockCss) {
                    const cur = Vvveb.StyleManager?.getCss?.() || '';
                    if (!cur.includes(blockCss.substring(0, Math.min(80, blockCss.length)).trim())) {
                        const merged = cur ? (cur + '\n\n' + blockCss) : blockCss;
                        Vvveb.StyleManager?.setCss?.(merged);
                        Vvveb.CssEditor?.setValue?.(merged);
                        console.log('[Align Custom Blocks] Injected CSS into StyleManager (' + blockCss.length + ' bytes)');
                    }
                }
                if (blockJs) {
                    const cur = Vvveb.JsManager?.getJs?.() || '';
                    if (!cur.includes(blockJs.substring(0, Math.min(80, blockJs.length)).trim())) {
                        const merged = cur ? (cur + '\n\n' + blockJs) : blockJs;
                        Vvveb.JsManager?.setJs?.(merged);
                        Vvveb.JsEditor?.setValue?.(merged);
                        console.log('[Align Custom Blocks] Injected JS into JsManager (' + blockJs.length + ' bytes)');
                    }
                }
                return node;
            }
        });

        if (!Vvveb.BlocksGroup['Custom'].includes(type)) {
            Vvveb.BlocksGroup['Custom'].push(type);
        }
    },

    // Render UI triggers for adding custom blocks in Custom section header
    renderAddBlockUI: function () {
        const customContainers = document.querySelectorAll('li[data-section="Custom"]');
        customContainers.forEach(function (container) {
            // Insert + Add button in label.header if not already present
            const labelHeader = container.querySelector('label.header');
            if (labelHeader && !labelHeader.querySelector('.add-custom-block-header-btn')) {
                labelHeader.style.display = 'flex';
                labelHeader.style.alignItems = 'center';

                const addBtn = document.createElement('button');
                addBtn.className = 'btn btn-xs add-custom-block-header-btn';
                addBtn.setAttribute('data-bs-toggle', 'modal');
                addBtn.setAttribute('data-bs-target', '#customBlockModal');
                addBtn.setAttribute('title', 'Add Custom Block');
                addBtn.style.cssText = 'margin-left: auto; margin-right: 20px; padding: 2px 7px; font-size: 11px; font-weight: 600; border-radius: 4px; border: 1px solid rgba(13, 110, 253, 0.3); background-color: rgba(13, 110, 253, 0.08); color: #0d6efd; line-height: 1.2; display: inline-flex; align-items: center; gap: 3px; cursor: pointer; transition: all 0.15s ease-in-out;';
                addBtn.innerHTML = '<i class="la la-plus"></i> Add';

                addBtn.addEventListener('mouseenter', function () {
                    addBtn.style.backgroundColor = '#0d6efd';
                    addBtn.style.color = '#ffffff';
                    addBtn.style.borderColor = '#0d6efd';
                });
                addBtn.addEventListener('mouseleave', function () {
                    addBtn.style.backgroundColor = 'rgba(13, 110, 253, 0.08)';
                    addBtn.style.color = '#0d6efd';
                    addBtn.style.borderColor = 'rgba(13, 110, 253, 0.3)';
                });

                addBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    e.preventDefault();
                    const modalEl = document.getElementById('customBlockModal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    }
                });

                const arrow = labelHeader.querySelector('.header-arrow');
                if (arrow) {
                    labelHeader.insertBefore(addBtn, arrow);
                } else {
                    labelHeader.appendChild(addBtn);
                }
            }
        });
    },

    // Handler for creating a new custom block from modal input
    saveCustomBlock: function () {
        const nameInput = document.getElementById('custom-block-name');
        const htmlInput = document.getElementById('custom-block-html');
        const cssInput = document.getElementById('custom-block-css');
        const jsInput = document.getElementById('custom-block-js');

        const name = nameInput ? nameInput.value.trim() : '';
        const rawHtml = htmlInput ? htmlInput.value : '';
        const html = this.cleanWrapper(rawHtml);
        const css = cssInput ? cssInput.value : '';
        const js = jsInput ? jsInput.value : '';

        if (!name) {
            alert('Please enter a block name.');
            return;
        }

        const id = 'custom/block_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);
        const newBlock = {
            id: id,
            name: name,
            html: html,
            css: css,
            js: js,
            image: 'icons/code.svg'
        };

        this.blocks.push(newBlock);
        
        if (!this.saveStorage()) {
            this.blocks.pop();
            alert("Local storage is full! Could not save custom block.");
            return;
        }

        this.addBlockToVvveb(newBlock);

        if (Vvveb.Builder && typeof Vvveb.Builder.loadBlockGroups === 'function') {
            Vvveb.Builder.loadBlockGroups();
        }

        const form = document.getElementById('custom-block-form');
        if (form) {
            form.reset();
        }

        const modalElement = document.getElementById('customBlockModal');
        if (modalElement) {
            const modalInstance = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    },

    // Attach event listeners
    bindEvents: function () {
        const self = this;
        const saveBtn = document.getElementById('save-custom-block-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                self.saveCustomBlock();
            });
        }
    }
};

// Monkey patch Vvveb.Builder.loadBlockGroups to trigger renderAddBlockUI after blocks render
if (Vvveb.Builder && typeof Vvveb.Builder.loadBlockGroups === 'function') {
    const originalLoadBlockGroups = Vvveb.Builder.loadBlockGroups;
    Vvveb.Builder.loadBlockGroups = function () {
        originalLoadBlockGroups.apply(this, arguments);
        Vvveb.CustomBlocks.renderAddBlockUI();
    };
}

// Initialize Vvveb.CustomBlocks when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    Vvveb.CustomBlocks.init();
});

