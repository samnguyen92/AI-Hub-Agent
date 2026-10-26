/**
 * Align Theme - Align Studio Dynamic Tokens Manager
 * Manages whitelisted dynamic tokens, search filtering, click-to-copy, and insertion into editor
 *
 * @package Align
 */

window.StudioTokens = (function() {
    'use strict';

    /**
     * Whitelisted token dictionary categorized for dynamic blocks
     */
    const TOKEN_REGISTRY = [
        { token: '{{ post.title }}', category: 'post', label: 'Post Title', desc: 'Real post title from WordPress database (heading, link, or text).' },
        { token: '{{ post.permalink }}', category: 'post', label: 'Post Permalink', desc: 'Direct URL to the single post page.' },
        { token: '{{ post.thumbnail }}', category: 'post', label: 'Featured Image', desc: 'Featured image source URL.' },
        { token: '{{ post.thumbnail_alt }}', category: 'post', label: 'Image Alt Text', desc: 'Alternative description text for the featured image (SEO/A11y).' },
        { token: '{{ post.excerpt }}', category: 'post', label: 'Post Excerpt', desc: 'Trimmed post excerpt or summary text without HTML tags.' },
        { token: '{{ post.content }}', category: 'post', label: 'Post Content', desc: 'Full post body content rendered with standard WordPress filters.' },
        { token: '{{ post.reading_time }}', category: 'post', label: 'Reading Time', desc: 'Estimated reading time (e.g. "3 min read") based on content word count.' },
        { token: '{{ post.comment_count }}', category: 'post', label: 'Comment Count', desc: 'Number of approved comments on the post.' },
        { token: '{{ post.date }}', category: 'post', label: 'Publish Date', desc: 'Formatted publish date according to WordPress site settings.' },
        { token: '{{ post.modified_date }}', category: 'post', label: 'Modified Date', desc: 'Last updated or modified date of the post.' },
        { token: '{{ post.category }}', category: 'post', label: 'Primary Category', desc: 'Name of the first primary category assigned to the post.' },
        { token: '{{ post.category_url }}', category: 'post', label: 'Category URL', desc: 'Archive URL link for the primary category.' },
        { token: '{{ post.tag }}', category: 'post', label: 'Primary Tag / Badge', desc: 'First post tag name (e.g. "Fitment Guide", "News"). Perfect for card badge pills.' },
        { token: '{{ post.tag_url }}', category: 'post', label: 'Tag Archive URL', desc: 'Archive URL link for the primary post tag.' },
        { token: '{{ post.tags }}', category: 'post', label: 'All Post Tags', desc: 'Comma-separated list of all tags assigned to the post.' },
        { token: '{{ post.author_name }}', category: 'post', label: 'Author Name', desc: 'Display name of the post author.' },
        { token: '{{ post.author_avatar }}', category: 'post', label: 'Author Avatar', desc: 'URL to author profile avatar image.' },
        { token: '{{ post.author_url }}', category: 'post', label: 'Author Profile URL', desc: 'Archive URL link to all articles by this author.' },
        { token: '{{ post.author_bio }}', category: 'post', label: 'Author Bio', desc: 'Biographical description or profile summary of the author.' },
        { token: '{{ post.slug }}', category: 'post', label: 'Post Slug', desc: 'URL-friendly post slug (e.g. "gioi-thieu").' },
        { token: '{{ post.id }}', category: 'post', label: 'Post ID', desc: 'Numeric post ID from WordPress database.' },
        { token: '{{ loop_items posts_per_page="6" }}', category: 'loop', label: 'Loop Repeater Container', desc: 'Repeater container. Set posts_per_page="N" (e.g. 3, 4, 8) or data-posts-per-page="N" to customize query count.' },
        { token: '{{ loop.index }}', category: 'loop', label: 'Loop Counter (#1, #2, 01...)', desc: '1-based iteration counter for rankings, numbered cards, step badges (#{{ loop.index }}).' },
        { token: '{{ max_pages }}', category: 'loop', label: 'Max Pages', desc: 'Total query pages. Example: data-max-page="{{ max_pages }}".' },
        { token: '{{ posts_per_page }}', category: 'loop', label: 'Posts Per Page', desc: 'Outputs the active query limit (e.g. 3, 4, 6). Example: data-posts-per-page="{{ posts_per_page }}".' },
        { token: '{{ acf.field_name }}', category: 'acf', label: 'ACF Custom Field', desc: 'Advanced Custom Fields value. Replace "field_name" with actual slug.' },
        { token: '{{ meta.meta_key }}', category: 'acf', label: 'Post Meta Field', desc: 'WordPress native post meta value. Replace "meta_key" with actual key.' },
        { token: '{{ site.name }}', category: 'site', label: 'Site Name', desc: 'Website name or blog title configured in general settings.' },
        { token: '{{ site.url }}', category: 'site', label: 'Home URL', desc: 'Website root homepage URL.' },
        { token: '{{ site.description }}', category: 'site', label: 'Site Tagline', desc: 'Website tagline or slogan configured in general settings.' },
        { token: '{{ site.year }}', category: 'site', label: 'Current Year', desc: 'Current calendar year (e.g. "2026"). Perfect for footer copyright: © {{ site.year }}.' },
        { token: '{{ site.logo }}', category: 'site', label: 'Site Logo URL', desc: 'URL to the custom site logo image.' }
    ];

    let currentCategory = 'all';
    let currentSearchQuery = '';

    /**
     * Initialize tokens manager
     */
    function init() {
        console.log('[Align Tokens] Initializing Dynamic Tokens Manager');
        renderTokens();
        setupSearch();
        setupFilterTabs();
        setupAiConvertAction();
    }

    /**
     * Render tokens list based on active filter and search query
     */
    function renderTokens() {
        const container = document.getElementById('tokens-scroll-list');
        const countBadge = document.getElementById('tokens-count-badge');
        if (!container) return;

        const filtered = TOKEN_REGISTRY.filter(item => {
            const matchesCat = (currentCategory === 'all' || item.category === currentCategory);
            if (!matchesCat) return false;

            if (!currentSearchQuery) return true;
            const q = currentSearchQuery.toLowerCase();
            return item.token.toLowerCase().includes(q)
                || item.label.toLowerCase().includes(q)
                || item.desc.toLowerCase().includes(q)
                || (item.context && item.context.toLowerCase().includes(q));
        });

        if (countBadge) {
            countBadge.textContent = filtered.length + ' tokens';
        }

        if (filtered.length === 0) {
            container.innerHTML = '<div class="tokens-empty-state">No matching tokens found.</div>';
            return;
        }

        // Group tokens by category for clean readability
        const categoryLabels = {
            post: 'Post Data Fields',
            loop: 'Loop & Pagination',
            acf: 'Custom Fields & ACF',
            site: 'Site & Global'
        };

        const grouped = {};
        filtered.forEach(item => {
            const cat = item.category;
            if (!grouped[cat]) grouped[cat] = [];
            grouped[cat].push(item);
        });

        let html = '';
        const categoryOrder = ['post', 'loop', 'acf', 'site'];

        categoryOrder.forEach(catKey => {
            if (grouped[catKey] && grouped[catKey].length > 0) {
                html += `<div class="tokens-group-title"><span>${categoryLabels[catKey] || catKey}</span></div>`;
                grouped[catKey].forEach(item => {
                    html += `
                        <div class="token-item" data-token="${escapeAttr(item.token)}">
                            <div class="token-row-top">
                                <span class="token-badge" data-token="${escapeAttr(item.token)}" title="Click to copy">${escapeHtml(item.token)}</span>
                                <div class="token-actions">
                                    <button type="button" class="token-action-btn btn-insert" title="Insert into Code Editor" data-action="insert">
                                        <i class="la la-level-down-alt"></i>
                                    </button>
                                    <button type="button" class="token-action-btn btn-copy" title="Copy Token" data-action="copy">
                                        <i class="la la-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="token-row-desc">${escapeHtml(item.desc)}</p>
                        </div>
                    `;
                });
            }
        });

        container.innerHTML = html;
        attachItemEvents(container);
    }

    /**
     * Attach click events for copy and insert actions
     */
    function attachItemEvents(container) {
        container.querySelectorAll('.token-item').forEach(card => {
            const token = card.getAttribute('data-token');
            const badge = card.querySelector('.token-badge');
            const btnCopy = card.querySelector('[data-action="copy"]');
            const btnInsert = card.querySelector('[data-action="insert"]');

            const doCopy = (e) => {
                e.stopPropagation();
                copyToken(token, badge, btnCopy);
            };

            if (badge) badge.addEventListener('click', doCopy);
            if (btnCopy) btnCopy.addEventListener('click', doCopy);

            if (btnInsert) {
                btnInsert.addEventListener('click', (e) => {
                    e.stopPropagation();
                    insertToken(token, btnInsert);
                });
            }
        });
    }

    /**
     * Setup search input listener with debounce
     */
    function setupSearch() {
        const input = document.getElementById('tokens-search-input');
        if (!input) return;

        let debounceTimer = null;
        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentSearchQuery = e.target.value.trim();
                renderTokens();
            }, 100);
        });
    }

    /**
     * Setup category filter buttons
     */
    function setupFilterTabs() {
        const bar = document.getElementById('tokens-filter-bar');
        if (!bar) return;

        bar.querySelectorAll('.tokens-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                bar.querySelectorAll('.tokens-filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentCategory = btn.getAttribute('data-category') || 'all';
                renderTokens();
            });
        });
    }

    /**
     * Copy token to clipboard with visual pill feedback and toast
     */
    function copyToken(tokenStr, badgeEl, btnEl) {
        if (!tokenStr) return;

        const onCopied = () => {
            // Visual feedback on the badge pill
            if (badgeEl) {
                if (badgeEl._copyTimer) {
                    clearTimeout(badgeEl._copyTimer);
                }
                const originalText = badgeEl.getAttribute('data-token') || tokenStr;
                badgeEl.classList.add('is-copied');
                badgeEl.textContent = 'Copied!';
                badgeEl._copyTimer = setTimeout(() => {
                    badgeEl.textContent = originalText;
                    badgeEl.classList.remove('is-copied');
                    badgeEl._copyTimer = null;
                }, 1500);
            }

            // Visual feedback on the copy icon button
            if (btnEl) {
                if (btnEl._copyTimer) {
                    clearTimeout(btnEl._copyTimer);
                }
                const icon = btnEl.querySelector('i');
                if (icon) {
                    icon.className = 'la la-check';
                }
                btnEl.classList.add('is-copied');
                btnEl._copyTimer = setTimeout(() => {
                    if (icon) {
                        icon.className = 'la la-copy';
                    }
                    btnEl.classList.remove('is-copied');
                    btnEl._copyTimer = null;
                }, 1500);
            }

            showToast('Copied ' + tokenStr + ' to clipboard', 'success');
            console.log('[Align Tokens] Copied token to clipboard:', tokenStr);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(tokenStr).then(onCopied).catch(() => {
                fallbackCopy(tokenStr);
                onCopied();
            });
        } else {
            fallbackCopy(tokenStr);
            onCopied();
        }
    }

    /**
     * Fallback clipboard copy using textarea
     */
    function fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    /**
     * Insert token into CodeMirror 6 editor at cursor position
     */
    function insertToken(tokenStr, btnEl) {
        if (!tokenStr) return;

        // Ensure Code panel is visible if it was collapsed
        const codePanel = document.getElementById('studio-code-panel');
        const toggleCodeBtn = document.getElementById('toggle-code-btn');
        if (codePanel && codePanel.classList.contains('d-none')) {
            codePanel.classList.remove('d-none');
            if (toggleCodeBtn) toggleCodeBtn.classList.add('active');
        }

        if (btnEl) {
            if (btnEl._insertTimer) clearTimeout(btnEl._insertTimer);
            btnEl.classList.add('is-copied');
            btnEl._insertTimer = setTimeout(() => {
                btnEl.classList.remove('is-copied');
                btnEl._insertTimer = null;
            }, 1200);
        }

        if (window.StudioCodeEditor && typeof window.StudioCodeEditor.insertText === 'function') {
            window.StudioCodeEditor.insertText(tokenStr);
            showToast('Inserted ' + tokenStr + ' into editor', 'info');
            console.log('[Align Tokens] Inserted token into StudioCodeEditor:', tokenStr);
        } else {
            // Direct fallback
            copyToken(tokenStr, null, btnEl);
            showToast('Copied token. Open Code Editor to paste.', 'info');
        }
    }

    /**
     * Setup AI Convert button actions (topbar and sidebar)
     */
    function setupAiConvertAction() {
        const topBtn = document.getElementById('ai-convert-btn');
        const sideBtn = document.getElementById('sidebar-ai-convert-btn');
        const buttons = [topBtn, sideBtn].filter(Boolean);

        if (buttons.length === 0) return;

        const handleAiConvert = async (e) => {
            e.preventDefault();

            // 1. Get current HTML from CodeMirror or canvas
            let currentHtml = '';
            if (window.StudioCodeEditor && typeof window.StudioCodeEditor.getContent === 'function') {
                const edContent = window.StudioCodeEditor.getContent();
                currentHtml = edContent.html || '';
            }
            if (!currentHtml) {
                const iframe = document.getElementById('studio-canvas-iframe');
                if (iframe) {
                    try {
                        const doc = iframe.contentDocument || iframe.contentWindow.document;
                        const stage = doc?.getElementById('align-studio-stage');
                        if (stage) currentHtml = stage.innerHTML;
                    } catch (err) {}
                }
            }

            if (!currentHtml || !currentHtml.trim()) {
                return showToast('No HTML content available to convert with AI.', 'danger');
            }

            const config = window.AlignStudioConfig || {};
            if (!config.restAiBindUrl) {
                return showToast('Configuration error: missing AI endpoint.', 'danger');
            }

            // 2. Set buttons to loading state
            buttons.forEach(btn => {
                btn.disabled = true;
                const txt = btn.querySelector('.btn-text');
                const spin = btn.querySelector('.spinner-border');
                if (txt) txt.textContent = 'Converting with AI...';
                if (spin) spin.classList.remove('d-none');
            });

            const userPromptEl = document.getElementById('tokens-custom-prompt');
            const customPrompt = userPromptEl ? userPromptEl.value.trim() : '';

            showToast('Sending HTML, tokens & requirements to AI...', 'info');
            console.log('[Align Tokens:AI] Converting HTML with AI. Input len:', currentHtml.length, 'Instruction len:', customPrompt.length);

            try {
                const tokenDefs = TOKEN_REGISTRY.map(t => ({
                    token: t.token,
                    label: t.label,
                    desc: t.desc,
                    category: t.category
                }));

                const response = await fetch(config.restAiBindUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': config.restNonce || ''
                    },
                    body: JSON.stringify({
                        html: currentHtml,
                        tokens: tokenDefs,
                        user_instruction: customPrompt,
                        source_page_id: config.postId || 0,
                        post_id: config.postId || 0
                    })
                });

                const data = await response.json();

                if (!response.ok || data.status !== 'success' || !data.html) {
                    throw new Error(data.message || ('Server returned HTTP ' + response.status));
                }

                // 3. Update Code Editor silently
                if (window.StudioCodeEditor && typeof window.StudioCodeEditor.updateHtmlSilently === 'function') {
                    window.StudioCodeEditor.updateHtmlSilently(data.html);
                }

                // 4. Update preview with 0-roundtrip instant hydration
                if (window.StudioPreview && typeof window.StudioPreview.onAiConverted === 'function') {
                    window.StudioPreview.onAiConverted(data.html, data.rendered_html);
                } else if (window.AlignStudio && typeof window.AlignStudio.updateHtmlFromEditor === 'function') {
                    window.AlignStudio.updateHtmlFromEditor(data.html);
                }

                // 5. Open code editor panel on HTML tab so user can review bound tokens
                const codePanel = document.getElementById('studio-code-panel');
                const toggleCodeBtn = document.getElementById('toggle-code-btn');
                if (codePanel && codePanel.classList.contains('d-none')) {
                    codePanel.classList.remove('d-none');
                    if (toggleCodeBtn) toggleCodeBtn.classList.add('active');
                }
                if (window.StudioCodeEditor && typeof window.StudioCodeEditor.switchTab === 'function') {
                    window.StudioCodeEditor.switchTab('html');
                }

                const durationStr = data.duration ? ` (${data.duration}ms)` : '';
                showToast(`Dynamic tokens bound successfully with AI!${durationStr}`, 'success');
                console.log('[Align Tokens:AI] AI conversion completed successfully:', {
                    duration: data.duration,
                    bindings: data.bindings,
                    outputLen: data.html.length
                });
            } catch (err) {
                console.error('[Align Tokens:AI] Error during AI conversion:', err);
                showToast(err.message || 'AI conversion failed.', 'danger');
            } finally {
                buttons.forEach(btn => {
                    btn.disabled = false;
                    const txt = btn.querySelector('.btn-text');
                    const spin = btn.querySelector('.spinner-border');
                    if (txt) {
                        txt.textContent = 'Convert to dynamic with AI';
                    }
                    if (spin) spin.classList.add('d-none');
                });
            }
        };

        buttons.forEach(btn => btn.addEventListener('click', handleAiConvert));
    }

    /**
     * Show Studio Toast notification
     */
    function showToast(message, type = 'info') {
        const toast = document.getElementById('studio-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = `studio-toast visible toast-${type}`;
        toast.style.borderColor = (type === 'success') ? '#10b981' : ((type === 'danger') ? '#ef4444' : '#38bdf8');
        setTimeout(() => toast.classList.remove('visible'), 2800);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        if (!str) return '';
        return str.replace(/"/g, '&quot;');
    }

    // Auto-init on DOMContentLoaded or immediate
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        init: init,
        renderTokens: renderTokens,
        copyToken: copyToken,
        insertToken: insertToken,
        registry: TOKEN_REGISTRY
    };
})();
