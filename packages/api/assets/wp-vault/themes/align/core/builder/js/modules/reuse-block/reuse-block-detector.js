/**
 * Align Builder Reusable Block - Selection Detector & API Fetcher
 * @package Align
 */

window.AlignBuilderModules = window.AlignBuilderModules || {};
window.AlignBuilderModules.WpReuseBlockOverwrite = window.AlignBuilderModules.WpReuseBlockOverwrite || {};

Object.assign(window.AlignBuilderModules.WpReuseBlockOverwrite, {
    fieldsCache: {},
    masterDefaults: {},
    _pendingFetches: {},
    _selectTimer: null,
    _inputTimer: null,

    clearCache: function() {
        this.fieldsCache = {};
        this._pendingFetches = {};
    },

    escapeAttr: function(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },

    getAbsoluteImgUrl: function(src, el) {
        if (el?.src && typeof el.src === 'string' && el.src.startsWith('http') && (!src || src === el.getAttribute('src'))) return el.src;
        if (!src) return (window.Vvveb ? Vvveb.baseUrl + 'icons/image.svg' : '');
        if (src.startsWith('http://') || src.startsWith('https://') || src.startsWith('data:')) return src;
        if (src.startsWith('/')) return window.location.origin + src;
        const cleanSrc = src.replace(/^(\.\/)/, '');
        if (window.VvvebWpConfig?.pluginUrl) return window.VvvebWpConfig.pluginUrl + cleanSrc;
        if (window.Vvveb?.baseUrl) return Vvveb.baseUrl + cleanSrc;
        return src;
    },

    /**
     * Initialize event listeners and hooks
     */
    init: function() {
        const self = this;

        // 1. Hook into Vvveb.Components.render so overwrite inspector renders AFTER properties panel renders
        if (typeof Vvveb !== "undefined" && Vvveb.Components && Vvveb.Components.render) {
            const origRender = Vvveb.Components.render;
            Vvveb.Components.render = function(type, panel) {
                origRender.call(this, type, panel);
                self.queueNodeSelection(Vvveb.Builder ? Vvveb.Builder.selectedEl : null, false, 40);
            };
        }

        // 2. Listen for Vvveb.Builder.selectNode event
        window.addEventListener("Vvveb.Builder.selectNode", function(e) {
            const targetNode = e.detail ? e.detail.target : (Vvveb.Builder ? Vvveb.Builder.selectedEl : null);
            self.queueNodeSelection(targetNode, false, 50);
        });

        // 3. Listen for input changes in the inspector panel (e.g. typing shortcode text [reuse-block id="128"])
        const rightPanel = document.getElementById("right-panel");
        if (rightPanel) {
            rightPanel.addEventListener("input", function(e) {
                if (e.target && (e.target.matches('[data-key="shortcode"]') || e.target.matches('[data-key="wp-block"]') || e.target.name === 'shortcode')) {
                    if (self._inputTimer) clearTimeout(self._inputTimer);
                    self._inputTimer = setTimeout(function() {
                        if (Vvveb.Builder && Vvveb.Builder.selectedEl) {
                            self.handleNodeSelection(Vvveb.Builder.selectedEl, true);
                        }
                    }, 150);
                }
            });
        }

        // 4. Listen for WpComponentsPreview render completion to sync live overwrite nodes
        window.addEventListener("Align.WpComponentsPreview.rendered", function(e) {
            const renderedNode = e.detail ? e.detail.node : null;
            if (renderedNode && window.Vvveb?.Builder && (window.Vvveb.Builder.selectedEl === renderedNode || (window.Vvveb.Builder.selectedEls && window.Vvveb.Builder.selectedEls.has(renderedNode)))) {
                self.handleNodeSelection(renderedNode, true);
            }
        });
    },

    /**
     * Queue node selection with debounce to prevent duplicate triggers from simultaneous events
     */
    queueNodeSelection: function(node, forceRefresh, delay) {
        const self = this;
        if (self._selectTimer) {
            clearTimeout(self._selectTimer);
            self._selectTimer = null;
        }
        self._selectTimer = setTimeout(function() {
            self._selectTimer = null;
            self.handleNodeSelection(node || (Vvveb.Builder ? Vvveb.Builder.selectedEl : null), forceRefresh);
        }, delay !== undefined ? delay : 30);
    },

    /**
     * Extract Reusable Block ID from a selected DOM node or shortcode input
     *
     * @param {Element} node
     * @return {string|null} Reusable Block ID or null
     */
    getReuseBlockId: function(node) {
        if (!node) return null;

        const targetNode = (node.closest ? node.closest('[data-component-shortcode], [data-component-wp-block], [data-reuse-block-id], [data-shortcode], [data-wp-block]') : null) || node;

        const resolveId = (val) => {
            if (!val) return null;
            if (/^\d+$/.test(val)) return String(val);
            const blocks = window.Vvveb?.WpReusableBlocks || window.VvvebWpConfig?.reusableBlocks;
            if (Array.isArray(blocks)) {
                const target = String(val).toLowerCase();
                const found = blocks.find(b => (b.slug && b.slug.toLowerCase() === target) || (b.name && b.name.toLowerCase() === target));
                if (found && (found.id || found.post_id)) return String(found.id || found.post_id);
            }
            return String(val);
        };

        // 1. Check direct attribute
        const attrId = (targetNode.getAttribute && targetNode.getAttribute('data-reuse-block-id')) || (node.getAttribute && node.getAttribute('data-reuse-block-id'));
        if (attrId) return resolveId(attrId);

        // 2. Collect candidate shortcode text sources
        const textSources = [
            targetNode.getAttribute ? (targetNode.getAttribute('data-shortcode') || targetNode.getAttribute('data-wp-block') || targetNode.getAttribute('data-shortcode-text')) : '',
            node.getAttribute ? (node.getAttribute('data-shortcode') || node.getAttribute('data-wp-block') || node.getAttribute('data-shortcode-text')) : '',
            document.querySelector('#right-panel input[data-key="shortcode"], #right-panel textarea[data-key="shortcode"]')?.value || '',
            targetNode.innerText || targetNode.textContent || node.innerText || node.textContent || ''
        ];

        for (const text of textSources) {
            if (!text) continue;
            const matchId = text.match(/\[(?:reuse-block|align_block|align_reusable_block|reusable_block|reuse_block)\b[^\]]*\bid=["']?(\d+)["']?/i);
            if (matchId && matchId[1]) return matchId[1];

            const matchSlug = text.match(/\[(?:reuse-block|align_block|align_reusable_block|reusable_block|reuse_block)\b[^\]]*\bslug=["']?([^"'\s\]]+)["']?/i);
            if (matchSlug && matchSlug[1]) return resolveId(matchSlug[1]);

            const matchGutenberg = text.match(/(?:templateId|id)["']?\s*[:=]\s*["']?(\d+)["']?/i);
            if (matchGutenberg && matchGutenberg[1]) return matchGutenberg[1];
        }

        return null;
    },

    /**
     * Extract Reusable Block Instance ID from a selected DOM node or shortcode input
     *
     * @param {Element} node
     * @return {string} Instance identifier
     */
    getReuseBlockInstanceId: function(node) {
        if (!node) return 'default';

        const targetNode = (node.closest ? node.closest('[data-component-shortcode], [data-component-wp-block], [data-reuse-block-id], [data-shortcode], [data-wp-block]') : null) || node;

        if (targetNode.getAttribute) {
            const instAttr = targetNode.getAttribute('data-instance-id') || targetNode.getAttribute('data-instance') || targetNode.getAttribute('instance');
            if (instAttr) return instAttr;
        }
        if (node.getAttribute) {
            const instAttr = node.getAttribute('data-instance-id') || node.getAttribute('data-instance') || node.getAttribute('instance');
            if (instAttr) return instAttr;
        }

        let shortcodeText = (targetNode.getAttribute ? (targetNode.getAttribute('data-shortcode') || targetNode.getAttribute('data-wp-block') || targetNode.getAttribute('data-shortcode-text')) : '') || '';
        if (!shortcodeText && node.getAttribute) {
            shortcodeText = node.getAttribute('data-shortcode') || node.getAttribute('data-wp-block') || node.getAttribute('data-shortcode-text') || '';
        }
        if (!shortcodeText) {
            const inspectorInput = document.querySelector('#right-panel input[data-key="shortcode"], #right-panel input[name="shortcode"], #right-panel textarea[data-key="shortcode"], #right-panel textarea[name="shortcode"]');
            if (inspectorInput && inspectorInput.value) {
                shortcodeText = inspectorInput.value;
            }
        }

        if (shortcodeText) {
            const matchInst = shortcodeText.match(/\b(?:instance|instance_id|key)=["']?([a-zA-Z0-9_-]+)["']?/i);
            if (matchInst && matchInst[1]) return matchInst[1];
        }

        const innerText = targetNode.innerText || targetNode.textContent || node.innerText || node.textContent || '';
        const matchTextInst = innerText.match(/\b(?:instance|instance_id|key)=["']?([a-zA-Z0-9_-]+)["']?/i);
        if (matchTextInst && matchTextInst[1]) return matchTextInst[1];

        // Disambiguate if multiple instances of same blockId exist without explicit instance
        const blockId = this.getReuseBlockId(targetNode);
        if (blockId && window.Vvveb && window.Vvveb.Builder && window.Vvveb.Builder.frameDoc) {
            const allSame = Array.from(window.Vvveb.Builder.frameDoc.querySelectorAll('[data-component-shortcode], [data-component-wp-block]')).filter(el => {
                const sc = el.getAttribute('data-shortcode') || el.getAttribute('data-wp-block') || '';
                return sc.includes(blockId) || (el.getAttribute('data-reuse-block-id') === blockId);
            });
            const idx = allSame.indexOf(targetNode);
            if (idx > 0) {
                const autoInst = 'inst_' + (idx + 1);
                if (targetNode.setAttribute) targetNode.setAttribute('data-instance-id', autoInst);
                return autoInst;
            }
        }

        return 'default';
    },

    /**
     * Main handler triggered on node selection or inspector update
     *
     * @param {Element} node
     * @param {boolean} forceRefresh
     */
    handleNodeSelection: function(node, forceRefresh) {
        const self = this;
        if (!node) {
            self.removeAccordion();
            return;
        }

        const wpConfig = window.VvvebWpConfig || {};

        if (wpConfig.activePostType === 'wp_block') {
            self.removeAccordion();
            return;
        }

        const blockId = self.getReuseBlockId(node);
        const instanceId = self.getReuseBlockInstanceId(node) || 'default';
        const cacheKey = (blockId || 'noblock') + '_' + instanceId;

        const accWrapper = document.getElementById("align-content-overwrite-section");
        const currentActiveNodeId = accWrapper ? accWrapper.dataset.activeNodeId : null;
        const currentActiveInstId = accWrapper ? accWrapper.dataset.instanceId : null;
        node._alignUid = node._alignUid || (node.getAttribute && node.getAttribute('data-ast-id')) || node.id || ('rn_' + Math.random().toString(36).substr(2, 9));
        const targetNodeId = String(node.id || (node.getAttribute && node.getAttribute('data-ast-id')) || node._alignUid || blockId || 'selected');

        const hasValidContent = accWrapper && accWrapper.querySelector('.section[data-section="content_overwrite"]') && accWrapper.querySelector('[data-header="content_overwrite"]');

        if (!forceRefresh && hasValidContent && accWrapper._alignBlockNode === node && currentActiveNodeId === targetNodeId && currentActiveInstId === instanceId && accWrapper.dataset.sourceType !== 'loading') {
            const rightProps = document.querySelector("#right-panel .component-properties") || document.querySelector("#properties");
            const targetContainer = rightProps?.querySelector('.tab-pane[data-section="content"]') || rightProps?.querySelector('.tab-pane') || rightProps;
            if (targetContainer && accWrapper.parentNode === targetContainer && targetContainer.lastElementChild !== accWrapper) {
                targetContainer.appendChild(accWrapper);
            }
            return; // Accordion is already active for this node & instance, DO NOT re-create DOM or reload thumbnails
        }

        if (blockId && wpConfig.activePostId && String(blockId) === String(wpConfig.activePostId)) {
            self.removeAccordion();
            return;
        }

        const overwriteDomNodes = [];
        const queryTarget = node.shadowRoot || node;
        if (queryTarget.querySelectorAll) {
            queryTarget.querySelectorAll('[data-allow-overwrite="true"]').forEach(function(el) {
                overwriteDomNodes.push(el);
            });
        }
        if (node.shadowRoot && node.querySelectorAll && queryTarget !== node) {
            node.querySelectorAll('[data-allow-overwrite="true"]').forEach(function(el) {
                if (overwriteDomNodes.indexOf(el) === -1) {
                    overwriteDomNodes.push(el);
                }
            });
        }

        if (node.getAttribute && node.getAttribute('data-allow-overwrite') === 'true') {
            if (overwriteDomNodes.indexOf(node) === -1) {
                overwriteDomNodes.push(node);
            }
        }

        if (!blockId && overwriteDomNodes.length === 0) {
            self.removeAccordion();
            return;
        }

        if (overwriteDomNodes.length > 0) {
            if (blockId) {
                if (!self.masterDefaults || !self.masterDefaults[blockId] || forceRefresh) {
                    self.fetchFieldsFromApi(blockId, instanceId, function(fields) {
                        const currentSelected = Vvveb.Builder ? Vvveb.Builder.selectedEl : null;
                        if (!currentSelected) return;
                        const currentBlockId = self.getReuseBlockId(currentSelected);
                        const currentInstId = self.getReuseBlockInstanceId(currentSelected) || 'default';
                        if (currentSelected !== node || currentBlockId !== blockId || currentInstId !== instanceId) {
                            return;
                        }
                        // Overwrite master values cache with true master defaults from server
                        if (self.masterDefaults && self.masterDefaults[blockId]) {
                            self._masterValuesCache = self._masterValuesCache || {};
                            self._masterValuesCache[cacheKey] = Object.assign({}, self._masterValuesCache[cacheKey] || {}, self.masterDefaults[blockId]);
                        }
                        // Refresh live inspector rows to ensure reset buttons have true master template text
                        const accWrapper = document.getElementById("align-content-overwrite-section");
                        if (accWrapper && accWrapper._alignBlockNode === node && accWrapper.dataset.instanceId === instanceId) {
                            self.renderContentOverwriteAccordion(node, overwriteDomNodes, blockId, instanceId);
                        }
                    });
                } else if (self.masterDefaults && self.masterDefaults[blockId]) {
                    self._masterValuesCache = self._masterValuesCache || {};
                    self._masterValuesCache[cacheKey] = Object.assign({}, self._masterValuesCache[cacheKey] || {}, self.masterDefaults[blockId]);
                }
            }
            self.renderContentOverwriteAccordion(node, overwriteDomNodes, blockId, instanceId);
            return;
        }

        if (blockId) {
            if (self.fieldsCache[cacheKey] && !forceRefresh) {
                self.renderContentOverwriteAccordionFromApi(node, self.fieldsCache[cacheKey], blockId, instanceId);
            } else {
                if (forceRefresh) {
                    delete self.fieldsCache[cacheKey];
                    if (self._pendingFetches && self._pendingFetches[blockId + '_' + instanceId]) {
                        delete self._pendingFetches[blockId + '_' + instanceId];
                    }
                }

                // Show loading section while waiting for preview or REST response
                self.renderLoadingAccordion(node, blockId, instanceId);

                self.fetchFieldsFromApi(blockId, instanceId, function(fields) {
                    const currentSelected = Vvveb.Builder ? Vvveb.Builder.selectedEl : null;
                    if (!currentSelected) return;
                    const currentBlockId = self.getReuseBlockId(currentSelected);
                    const currentInstId = self.getReuseBlockInstanceId(currentSelected) || 'default';
                    if (currentSelected !== node || currentBlockId !== blockId || currentInstId !== instanceId) {
                        return;
                    }

                    const liveNodes = (node.shadowRoot || node).querySelectorAll ? (node.shadowRoot || node).querySelectorAll('[data-allow-overwrite="true"]') : [];
                    if (liveNodes.length > 0) {
                        self.renderContentOverwriteAccordion(node, Array.from(liveNodes), blockId, instanceId);
                    } else if (fields && fields.length > 0) {
                        self.fieldsCache[cacheKey] = fields;
                        self.renderContentOverwriteAccordionFromApi(node, fields, blockId, instanceId);
                    } else {
                        self.removeAccordion();
                    }
                });
            }
        } else {
            self.removeAccordion();
        }
    },

    /**
     * Fetch editable fields for a reusable block post via REST API with request deduplication
     */
    fetchFieldsFromApi: function(blockId, instanceId, callback) {
        const self = this;
        if (typeof instanceId === 'function') {
            callback = instanceId;
            instanceId = 'default';
        }
        instanceId = instanceId || 'default';
        if (!blockId) {
            if (typeof callback === 'function') callback([]);
            return;
        }

        const fetchKey = blockId + '_' + instanceId;
        if (self._pendingFetches && self._pendingFetches[fetchKey]) {
            self._pendingFetches[fetchKey].push(callback);
            return;
        }

        self._pendingFetches = self._pendingFetches || {};
        self._pendingFetches[fetchKey] = [callback];

        let url = '';
        let activePostId = (window.VvvebWpConfig && window.VvvebWpConfig.activePostId) ? window.VvvebWpConfig.activePostId : '';
        let activeTpId = (window.VvvebWpConfig && window.VvvebWpConfig.activeTpId) ? window.VvvebWpConfig.activeTpId : '';
        let activeTemplateType = (window.VvvebWpConfig && window.VvvebWpConfig.activePostType) ? window.VvvebWpConfig.activePostType : '';

        const curPageObj = window.Vvveb?.FileManager?.currentPage ? window.Vvveb.FileManager.pages[window.Vvveb.FileManager.currentPage] : null;
        if (curPageObj?.postId) {
            activePostId = curPageObj.postId;
            activeTpId = '';
            activeTemplateType = curPageObj.templateType || 'page';
        } else if (curPageObj?.tpId) {
            activeTpId = curPageObj.tpId;
            activePostId = '';
            activeTemplateType = curPageObj.templateType || (curPageObj.tpId.startsWith('tpl_') ? 'template' : 'template_part');
        }

        const baseFieldsUrl = window.VvvebWpConfig?.restReuseFieldsUrl || '/wp-json/align-reuse/v1/fields';
        url = baseFieldsUrl + (baseFieldsUrl.indexOf('?') > -1 ? '&' : '?') + 'id=' + encodeURIComponent(blockId);

        if (instanceId && instanceId !== 'default') {
            url += '&instance_id=' + encodeURIComponent(instanceId);
        }
        if (activePostId) {
            url += '&page_id=' + encodeURIComponent(activePostId);
        }
        if (activeTpId) {
            url += '&tp_id=' + encodeURIComponent(activeTpId);
            if (activeTemplateType) {
                url += '&template_type=' + encodeURIComponent(activeTemplateType);
            }
        }

        fetch(url, {
            headers: {
                'X-WP-Nonce': (window.VvvebWpConfig ? window.VvvebWpConfig.restNonce : '')
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            const fields = (data && Array.isArray(data.fields)) ? data.fields : [];
            if (fields && fields.length > 0 && blockId) {
                self.masterDefaults = self.masterDefaults || {};
                self.masterDefaults[blockId] = self.masterDefaults[blockId] || {};
                fields.forEach(function(f) {
                    if (f && f.id) {
                        const mVal = (f.master_value !== undefined) ? f.master_value : f.value;
                        if (f.type === 'image' || f.fieldType === 'image' || f.attribute === 'src') {
                            self.masterDefaults[blockId][f.id] = {
                                type: 'media', src: mVal || '', width: f.width || '', height: f.height || '', alt: f.alt || '', align: f.align || '', hidden: false
                            };
                        } else if (f.type === 'button') {
                            self.masterDefaults[blockId][f.id] = { type: 'button', content: (f.master_value !== undefined) ? f.master_value : (f.content || mVal || ''), hidden: false };
                        } else if (f.type === 'link' || f.attribute === 'href') {
                            self.masterDefaults[blockId][f.id] = {
                                type: 'link', content: (f.master_text !== undefined ? f.master_text : (f.content || '')),
                                href: mVal || '', target: (f.master_target !== undefined ? f.master_target : (f.target || '')),
                                link_id: (f.master_link_id !== undefined ? f.master_link_id : (f.link_id || '')),
                                link_title: (f.master_link_title !== undefined ? f.master_link_title : (f.link_title || '')),
                                hidden: false
                            };
                        } else {
                            self.masterDefaults[blockId][f.id] = { type: 'typography', content: mVal || '', hidden: false };
                        }
                    }
                });
            }
            const callbacks = self._pendingFetches[fetchKey] || [];
            delete self._pendingFetches[fetchKey];
            callbacks.forEach(function(cb) {
                if (typeof cb === 'function') cb(fields);
            });
        })
        .catch(function() {
            const callbacks = self._pendingFetches[fetchKey] || [];
            delete self._pendingFetches[fetchKey];
            callbacks.forEach(function(cb) {
                if (typeof cb === 'function') cb([]);
            });
        });
    },

    /**
     * Render lightweight loading placeholder in inspector while preview or fields are fetching
     */
    renderLoadingAccordion: function(blockNode, blockId, instanceId) {
        const rightPanelProps = document.querySelector("#right-panel .component-properties") || document.querySelector("#properties");
        if (!rightPanelProps) return;
        const targetContainer = rightPanelProps.querySelector('.tab-pane[data-section="content"]') || rightPanelProps.querySelector('.tab-pane') || rightPanelProps;
        if (!targetContainer) return;

        let accWrapper = document.getElementById("align-content-overwrite-section");
        if (accWrapper && accWrapper._alignBlockNode === blockNode) return;
        this.removeAccordion();

        const escFn = this.escapeAttr || (window.AlignBuilderModules?.WpReuseBlockOverwrite?.escapeAttr) || (s => String(s || ''));
        accWrapper = document.createElement("div");
        accWrapper.id = "align-content-overwrite-section";
        accWrapper.className = "align-content-overwrite-section";
        accWrapper._alignBlockNode = blockNode;
        accWrapper.dataset.activeNodeId = String(blockNode.id || (blockNode.getAttribute && blockNode.getAttribute('data-ast-id')) || blockNode._alignUid || blockId || 'selected');
        accWrapper.dataset.instanceId = instanceId;
        accWrapper.dataset.sourceType = 'loading';
        accWrapper.innerHTML = '<label class="header" data-header="content_overwrite" for="header_content_overwrite"><span>Content Overwrite' + (instanceId && instanceId !== 'default' ? ' (' + escFn(instanceId) + ')' : '') + ' <span class="spinner-border spinner-border-sm text-primary ms-1" style="width:11px;height:11px;vertical-align:middle;" role="status"></span></span><div class="header-arrow"></div></label><input class="header_check" type="checkbox" id="header_content_overwrite" checked="checked"><div class="section row" data-section="content_overwrite"><div class="p-3 text-muted text-center" style="font-size:11px;">Loading overwrite fields...</div></div>';
        targetContainer.appendChild(accWrapper);
    },

    /**
     * Remove Content Overwrite accordion thoroughly if present
     */
    removeAccordion: function() {
        const elements = document.querySelectorAll(
            '#align-content-overwrite-section, #align-reusable-overwrite-section, .align-content-overwrite-section, [data-section="content_overwrite"], [data-header="content_overwrite"]'
        );
        elements.forEach(function(el) {
            const wrapper = el.closest('#align-content-overwrite-section, #align-reusable-overwrite-section, .align-content-overwrite-section') || el;
            if (wrapper && wrapper.parentNode) {
                wrapper.parentNode.removeChild(wrapper);
            }
        });
    }
});
