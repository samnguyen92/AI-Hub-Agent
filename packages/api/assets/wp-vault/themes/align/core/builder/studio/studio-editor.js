/**
 * Align Theme - Align Studio Code Editor JS
 * Docked bottom drawer with CodeMirror 6 syntax highlighting (HTML/CSS/JS) and live sync
 *
 * @package Align
 */

window.StudioCodeEditor = (function() {
    'use strict';

    let cmHtml = null;
    let cmCss = null;
    let cmJs = null;

    let htmlDebounceTimer = null;
    let cssDebounceTimer = null;
    let jsDebounceTimer = null;

    let activeTab = 'html';
    let isInternalUpdate = false;

    /**
     * Initialize Code Editor module with CodeMirror 6
     */
    function init() {
        setupCodeMirrorEditors();
        setupTabs();
        setupToolButtons();

        console.log('[Align Studio] Code Editor initialized with CodeMirror 6 (HTML/CSS/JS)');
    }

    /**
     * Setup CodeMirror 6 syntax-highlighted instances for HTML, CSS, JS
     */
    function setupCodeMirrorEditors() {
        const htmlTextarea = document.getElementById('studio-html-editor');
        const cssTextarea = document.getElementById('studio-css-editor');
        const jsTextarea = document.getElementById('studio-js-editor');

        if (window.CodeMirror && typeof window.CodeMirror.fromTextArea === 'function') {
            try {
                if (htmlTextarea && !cmHtml) {
                    cmHtml = window.CodeMirror.fromTextArea(htmlTextarea, {
                        mode: 'html',
                        lineNumbers: true,
                        lineWrapping: true
                    });
                    cmHtml.on('change', function(cm, v) {
                        if (v && v.origin === 'setValue') return;
                        if (isInternalUpdate) return;
                        setSyncStatus('syncing');
                        clearTimeout(htmlDebounceTimer);
                        htmlDebounceTimer = setTimeout(() => {
                            const val = cm.getValue();
                            if (window.AlignStudio?.updateHtmlFromEditor) {
                                window.AlignStudio.updateHtmlFromEditor(val);
                            }
                            setSyncStatus('synced');
                        }, 150);
                    });
                }

                if (cssTextarea && !cmCss) {
                    cmCss = window.CodeMirror.fromTextArea(cssTextarea, {
                        mode: 'css',
                        lineNumbers: true,
                        lineWrapping: true
                    });
                    cmCss.on('change', function(cm, v) {
                        if (v && v.origin === 'setValue') return;
                        if (isInternalUpdate) return;
                        setSyncStatus('syncing');
                        clearTimeout(cssDebounceTimer);
                        cssDebounceTimer = setTimeout(() => {
                            const val = cm.getValue();
                            if (window.AlignStudio?.updateCssFromEditor) {
                                window.AlignStudio.updateCssFromEditor(val);
                            }
                            setSyncStatus('synced');
                        }, 100);
                    });
                }

                if (jsTextarea && !cmJs) {
                    cmJs = window.CodeMirror.fromTextArea(jsTextarea, {
                        mode: 'js',
                        lineNumbers: true,
                        lineWrapping: true
                    });
                    cmJs.on('change', function(cm, v) {
                        if (v && v.origin === 'setValue') return;
                        if (isInternalUpdate) return;
                        setSyncStatus('syncing');
                        clearTimeout(jsDebounceTimer);
                        jsDebounceTimer = setTimeout(() => {
                            const val = cm.getValue();
                            if (window.AlignStudio?.updateJsFromEditor) {
                                window.AlignStudio.updateJsFromEditor(val);
                            }
                            setSyncStatus('synced');
                        }, 200);
                    });
                }
            } catch (cmErr) {
                console.warn('[Align Studio] CodeMirror initialization failed, falling back to textareas:', cmErr);
            }
        }

        // Fallback for textarea input events if CodeMirror failed
        if (!cmHtml && htmlTextarea) {
            htmlTextarea.addEventListener('input', () => {
                if (isInternalUpdate) return;
                setSyncStatus('syncing');
                clearTimeout(htmlDebounceTimer);
                htmlDebounceTimer = setTimeout(() => {
                    if (window.AlignStudio?.updateHtmlFromEditor) window.AlignStudio.updateHtmlFromEditor(htmlTextarea.value);
                    setSyncStatus('synced');
                }, 150);
            });
        }
        if (!cmCss && cssTextarea) {
            cssTextarea.addEventListener('input', () => {
                if (isInternalUpdate) return;
                setSyncStatus('syncing');
                clearTimeout(cssDebounceTimer);
                cssDebounceTimer = setTimeout(() => {
                    if (window.AlignStudio?.updateCssFromEditor) window.AlignStudio.updateCssFromEditor(cssTextarea.value);
                    setSyncStatus('synced');
                }, 100);
            });
        }
        if (!cmJs && jsTextarea) {
            jsTextarea.addEventListener('input', () => {
                if (isInternalUpdate) return;
                setSyncStatus('syncing');
                clearTimeout(jsDebounceTimer);
                jsDebounceTimer = setTimeout(() => {
                    if (window.AlignStudio?.updateJsFromEditor) window.AlignStudio.updateJsFromEditor(jsTextarea.value);
                    setSyncStatus('synced');
                }, 200);
            });
        }
    }

    /**
     * Setup tab switching between HTML, CSS, and JS
     */
    function setupTabs() {
        const tabs = document.querySelectorAll('.editor-tab-btn');
        const closeBtn = document.getElementById('btn-close-code-panel');
        const codePanel = document.getElementById('studio-code-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                switchTab(tab.dataset.tab);
            });
        });

        if (closeBtn && codePanel) {
            closeBtn.addEventListener('click', function() {
                codePanel.classList.add('d-none');
                const toggleBtn = document.getElementById('toggle-code-btn');
                if (toggleBtn) toggleBtn.classList.remove('active');
            });
        }
    }

    /**
     * Switch active tab
     */
    function switchTab(tabName) {
        if (!tabName) return;
        activeTab = tabName;

        const tabs = document.querySelectorAll('.editor-tab-btn');
        const panes = document.querySelectorAll('.editor-pane');

        tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabName));
        panes.forEach(p => p.classList.toggle('active', p.id === `pane-${tabName}`));

        setTimeout(() => {
            if (tabName === 'html' && cmHtml) {
                cmHtml.refresh();
                cmHtml.focus();
            } else if (tabName === 'css' && cmCss) {
                cmCss.refresh();
                cmCss.focus();
            } else if (tabName === 'js' && cmJs) {
                cmJs.refresh();
                cmJs.focus();
            }
        }, 50);
    }

    /**
     * Update Live Sync indicator state and color
     */
    function setSyncStatus(status, label) {
        const badge = document.getElementById('live-sync-badge');
        if (!badge) return;
        const dot = badge.querySelector('.sync-dot');
        const text = badge.querySelector('.sync-label') || badge;

        if (status === 'syncing') {
            badge.style.color = '#f59e0b';
            badge.style.borderColor = 'rgba(245, 158, 11, 0.3)';
            badge.style.backgroundColor = 'rgba(245, 158, 11, 0.1)';
            if (dot) dot.style.backgroundColor = '#f59e0b';
            if (text) text.textContent = label || 'Syncing...';
        } else if (status === 'visual-edit') {
            badge.style.color = '#38bdf8';
            badge.style.borderColor = 'rgba(56, 189, 248, 0.3)';
            badge.style.backgroundColor = 'rgba(56, 189, 248, 0.1)';
            if (dot) dot.style.backgroundColor = '#38bdf8';
            if (text) text.textContent = label || 'Visual Synced';
            setTimeout(() => setSyncStatus('synced'), 1400);
        } else {
            badge.style.color = '#10b981';
            badge.style.borderColor = 'rgba(16, 185, 129, 0.25)';
            badge.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
            if (dot) dot.style.backgroundColor = '#10b981';
            if (text) text.textContent = label || 'Live Sync';
        }
    }

    /**
     * Setup format / beautify button
     */
    function setupToolButtons() {
        const formatBtn = document.getElementById('btn-format-html');
        if (formatBtn) {
            formatBtn.addEventListener('click', function() {
                if (activeTab === 'html') {
                    const current = cmHtml ? cmHtml.getValue() : document.getElementById('studio-html-editor')?.value;
                    const formatted = formatCode(current, 'html');
                    if (cmHtml) cmHtml.setValue(formatted);
                    else if (document.getElementById('studio-html-editor')) document.getElementById('studio-html-editor').value = formatted;
                } else if (activeTab === 'css') {
                    const current = cmCss ? cmCss.getValue() : document.getElementById('studio-css-editor')?.value;
                    const formatted = formatCode(current, 'css');
                    if (cmCss) cmCss.setValue(formatted);
                    else if (document.getElementById('studio-css-editor')) document.getElementById('studio-css-editor').value = formatted;
                } else if (activeTab === 'js') {
                    const current = cmJs ? cmJs.getValue() : document.getElementById('studio-js-editor')?.value;
                    const formatted = formatCode(current, 'js');
                    if (cmJs) cmJs.setValue(formatted);
                    else if (document.getElementById('studio-js-editor')) document.getElementById('studio-js-editor').value = formatted;
                }
            });
        }
    }

    /**
     * Code beautifier using CM6 bundle beautifiers or fallback
     */
    function formatCode(code, type = 'html') {
        if (!code || typeof code !== 'string') return '';
        if (type === 'html' && window.html_beautify) {
            try { return window.html_beautify(code, { indent_size: 2, wrap_line_length: 0 }); } catch (e) {}
        }
        if (type === 'css' && window.css_beautify) {
            try { return window.css_beautify(code, { indent_size: 2 }); } catch (e) {}
        }
        if (type === 'js' && window.js_beautify) {
            try { return window.js_beautify(code, { indent_size: 2 }); } catch (e) {}
        }
        return code.trim();
    }

    /**
     * Set initial values in CodeMirror editors
     */
    function setContent(html, css, js) {
        isInternalUpdate = true;
        const fmtHtml = formatCode(html, 'html');
        const fmtCss = formatCode(css, 'css');
        const fmtJs = formatCode(js, 'js');

        if (cmHtml) cmHtml.setValue(fmtHtml || '');
        else if (document.getElementById('studio-html-editor')) document.getElementById('studio-html-editor').value = fmtHtml || '';

        if (cmCss) cmCss.setValue(fmtCss || '');
        else if (document.getElementById('studio-css-editor')) document.getElementById('studio-css-editor').value = fmtCss || '';

        if (cmJs) cmJs.setValue(fmtJs || '');
        else if (document.getElementById('studio-js-editor')) document.getElementById('studio-js-editor').value = fmtJs || '';

        setTimeout(() => {
            if (activeTab === 'html' && cmHtml) cmHtml.refresh();
            if (activeTab === 'css' && cmCss) cmCss.refresh();
            if (activeTab === 'js' && cmJs) cmJs.refresh();
            isInternalUpdate = false;
        }, 100);
    }

    /**
     * Update HTML from visual canvas edit without triggering input loops
     */
    function updateHtmlSilently(newHtml) {
        if (typeof newHtml !== 'string') return;
        isInternalUpdate = true;
        const formatted = formatCode(newHtml, 'html');
        if (cmHtml) {
            cmHtml.setValue(formatted);
        } else {
            const el = document.getElementById('studio-html-editor');
            if (el) el.value = formatted;
        }
        setSyncStatus('visual-edit');
        setTimeout(() => { isInternalUpdate = false; }, 50);
    }

    /**
     * Get current content from editors
     */
    function getContent() {
        return {
            html: cmHtml ? cmHtml.getValue() : (document.getElementById('studio-html-editor')?.value || ''),
            css: cmCss ? cmCss.getValue() : (document.getElementById('studio-css-editor')?.value || ''),
            js: cmJs ? cmJs.getValue() : (document.getElementById('studio-js-editor')?.value || '')
        };
    }

    /**
     * Insert text at cursor position in the active CodeMirror editor
     */
    function insertText(text) {
        if (!text) return false;

        let activeCm = null;
        let textareaId = 'studio-html-editor';

        if (activeTab === 'html') {
            activeCm = cmHtml;
            textareaId = 'studio-html-editor';
        } else if (activeTab === 'css') {
            activeCm = cmCss;
            textareaId = 'studio-css-editor';
        } else if (activeTab === 'js') {
            activeCm = cmJs;
            textareaId = 'studio-js-editor';
        }

        if (activeCm) {
            activeCm.focus();
            activeCm.replaceSelection(text);
            return true;
        }

        const ta = document.getElementById(textareaId);
        if (ta) {
            const start = ta.selectionStart || 0;
            const end = ta.selectionEnd || 0;
            ta.value = ta.value.substring(0, start) + text + ta.value.substring(end);
            ta.selectionStart = ta.selectionEnd = start + text.length;
            ta.focus();
            return true;
        }

        return false;
    }

    return {
        init: init,
        switchTab: switchTab,
        setContent: setContent,
        updateHtmlSilently: updateHtmlSilently,
        setSyncStatus: setSyncStatus,
        formatCode: formatCode,
        getContent: getContent,
        insertText: insertText
    };
})();
