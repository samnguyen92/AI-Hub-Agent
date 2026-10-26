/**
 * Align Theme - Real-Time SEO Scorer (RankMath Alignment)
 *
 * Implements comprehensive RankMath diagnostic tests grouped into:
 * - Basic SEO (Keywords in Title, Desc, URL, Content Start, Content, Word Count)
 * - Additional (Keywords in Headings, Image Alt, Density, URL Length, Outbound/Internal Links)
 * - Title Readability (Keyword at Title Start, Title Length)
 */
window.AlignBuilderModules = window.AlignBuilderModules || {};

window.AlignBuilderModules.WpSeoScorer = {
    officialScore: null,

    calculate: function(data) {
        const keywordRaw = (data.focus_keyword || '').trim();
        const title      = (data.title || '').trim();
        const desc       = (data.description || '').trim();
        const slug       = (data.slug || '').trim().toLowerCase();
        let url          = (data.post_url || '').toLowerCase();
        if (slug && !url.includes(slug)) url += '/' + slug;

        const rawList = keywordRaw.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
        const primary = rawList[0] || '';
        const secondaries = rawList.slice(1);
        const isCJK = /[\u4e00-\u9fa5\u3040-\u30ff\uac00-\ud7af]/.test(title + ' ' + desc + ' ' + keywordRaw);

        // Analyze canvas content if available
        let pageText = data.pageText || '';
        let headings = [];
        let images = [];
        let links = [];
        try {
            const iframe = document.getElementById('iframe-layer') || document.getElementById('canvas-frame');
            const doc = iframe?.contentDocument || window.AlignBuilderModules?.WpCanvasTranslator?.getCanvasDocument();
            if (doc && doc.body && !pageText) {
                pageText = doc.body.innerText || '';
            }
            if (doc) {
                headings = Array.from(doc.querySelectorAll('h1, h2, h3, h4, h5, h6')).map(h => (h.innerText || '').toLowerCase().trim());
                images = Array.from(doc.querySelectorAll('img')).map(img => ({ alt: (img.getAttribute('alt') || '').toLowerCase().trim(), src: img.getAttribute('src') || '' }));
                links = Array.from(doc.querySelectorAll('a[href]')).map(a => a.getAttribute('href') || '');
            }
        } catch(e) {}

        // Multi-language word / character metrics
        const cjkChars = (pageText.match(/[\u4e00-\u9fa5]/g) || []).length;
        const latinWords = pageText ? pageText.replace(/[\u4e00-\u9fa5]/g, ' ').trim().split(/\s+/).filter(Boolean).length : 0;
        const equivalentWords = cjkChars > 0 ? Math.round(cjkChars * 0.7) + latinWords : latinWords;
        const totalWords = isCJK ? equivalentWords : latinWords;

        // Content analysis
        const lowerContent = pageText.toLowerCase();
        const first10Len = Math.max(160, Math.round(lowerContent.length * 0.15));
        const first10Text = lowerContent.substring(0, first10Len);

        let occurrences = 0;
        if (primary) {
            const escaped = primary.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const matches = lowerContent.match(new RegExp(escaped, 'g'));
            occurrences = matches ? matches.length : 0;
        }
        const density = (primary && totalWords > 0) ? ((occurrences / totalWords) * 100).toFixed(2) : '0.00';

        // Outbound & Internal Links
        const host = (typeof window !== 'undefined' && window.location) ? window.location.hostname : '';
        const outboundLinks = links.filter(h => h.startsWith('http') && (!host || !h.includes(host)));
        const internalLinks = links.filter(h => h.startsWith('/') || (h.startsWith('http') && host && h.includes(host)));

        // Thresholds
        const minTitle = isCJK ? 14 : 35;
        const maxTitle = isCJK ? 32 : 60;
        const minDesc  = isCJK ? 45 : 110;
        const maxDesc  = isCJK ? 95 : 165;
        const minWords = isCJK ? 300 : 600;

        const titleHasKeyword = primary.length > 0 && title.toLowerCase().includes(primary);
        const titleStartsWithKw = primary.length > 0 && title.toLowerCase().startsWith(primary);
        const titleOptimalLen = title.length >= minTitle && title.length <= maxTitle;
        const descHasKeyword  = primary.length > 0 && desc.toLowerCase().includes(primary);
        const descOptimalLen  = desc.length >= minDesc && desc.length <= maxDesc;
        const slugClean = slug.replace(/[^a-z0-9\u4e00-\u9fa5-]/g, '');
        const urlHasKeyword   = primary.length > 0 && (
            url.includes(encodeURIComponent(primary.replace(/\s+/g, '-'))) ||
            url.includes(primary.replace(/\s+/g, '-')) ||
            (slugClean && slugClean.includes(primary.replace(/\s+/g, '-'))) ||
            (isCJK && (slug.length > 3 || url.length > 20))
        );
        const contentHasKeyword = occurrences > 0;
        const kwAtBeginning = primary.length > 0 && first10Text.includes(primary);
        const contentLenPassed = isCJK ? (cjkChars >= 350 || equivalentWords >= minWords) : latinWords >= minWords;
        const headingHasKw = primary.length > 0 && headings.some(h => h.includes(primary));
        const imgHasKw = primary.length > 0 && images.some(img => img.alt.includes(primary));
        const densityPassed = occurrences >= 1;
        const urlOptimalLen = url.length > 0 && url.length <= 75;
        const hasOutbound = outboundLinks.length > 0;
        const hasInternal = internalLinks.length > 0 || links.length > 0;

        // Group 1: Basic SEO
        const basicChecks = [
            {
                id: 'keyword_in_title',
                label: titleHasKeyword ? "Hurray! You're using Focus Keyword in the SEO Title." : 'Focus Keyword not found in your SEO Title.',
                passed: titleHasKeyword,
                pts: 15,
            },
            {
                id: 'keyword_in_desc',
                label: descHasKeyword ? 'Focus Keyword found in your SEO Meta Description.' : 'Focus Keyword not found in your SEO Meta Description.',
                passed: descHasKeyword,
                pts: 10,
            },
            {
                id: 'keyword_in_url',
                label: urlHasKeyword ? 'Focus Keyword found in the URL.' : 'Focus Keyword not found in the URL.',
                passed: urlHasKeyword,
                pts: 10,
            },
            {
                id: 'keyword_at_beginning',
                label: kwAtBeginning ? 'Focus Keyword appears at the beginning of your content.' : "Focus Keyword doesn't appear at the beginning of your content.",
                passed: kwAtBeginning,
                pts: 10,
            },
            {
                id: 'keyword_in_content',
                label: contentHasKeyword ? 'Focus Keyword appears in the content.' : "Focus Keyword doesn't appear in the content.",
                passed: contentHasKeyword,
                pts: 10,
            },
            {
                id: 'content_length',
                label: contentLenPassed
                    ? (isCJK ? `Content length is optimal (${cjkChars} chars, ~${equivalentWords} words).` : `Content is ${latinWords} words long. Kudos!`)
                    : (isCJK ? `Content is ${cjkChars} chars (~${equivalentWords} words). Consider using at least 350 chars.` : `Content is ${latinWords} words long. Consider using at least 600 words.`),
                passed: contentLenPassed,
                pts: 10,
            },
        ];

        // Group 2: Additional
        const additionalChecks = [
            {
                id: 'keyword_in_subheading',
                label: headingHasKw ? 'Focus Keyword found in subheading(s) like H2, H3, H4, etc.' : 'Focus Keyword not found in subheading(s) like H2, H3, H4, etc.',
                passed: headingHasKw,
                pts: 5,
            },
            {
                id: 'keyword_in_image_alt',
                label: imgHasKw ? 'Found image(s) with Focus Keyword as alt text.' : 'Add an image with your Focus Keyword as alt text.',
                passed: imgHasKw,
                pts: 5,
            },
            {
                id: 'keyword_density',
                label: densityPassed ? `Keyword Density is ${density}% which is good (${occurrences} occurrences).` : `Keyword Density is ${density}% which is low, appears ${occurrences} times.`,
                passed: densityPassed,
                pts: 5,
            },
            {
                id: 'url_length',
                label: urlOptimalLen ? `URL is ${url.length} characters long. Kudos!` : `URL is ${url.length} characters long. Consider keeping under 75 characters.`,
                passed: urlOptimalLen,
                pts: 5,
            },
            {
                id: 'has_outbound_links',
                label: hasOutbound ? `Outbound links found (${outboundLinks.length}). Kudos!` : 'No outbound links were found. Link out to external resources.',
                passed: hasOutbound,
                pts: 5,
            },
            {
                id: 'has_internal_links',
                label: hasInternal ? 'Internal links found in your content. Kudos!' : 'No internal links were found in this content.',
                passed: hasInternal,
                pts: 5,
            },
        ];

        // Group 3: Title Readability
        const titleChecks = [
            {
                id: 'title_starts_with_keyword',
                label: titleStartsWithKw ? 'Focus Keyword used at the beginning of SEO Title.' : 'Focus Keyword not used at the beginning of SEO Title.',
                passed: titleStartsWithKw,
                pts: 5,
            },
            {
                id: 'title_length',
                label: titleOptimalLen ? (isCJK ? `Title length optimal (${title.length} chars).` : `Title length optimal (${title.length}/60 chars).`) : `Title length is ${title.length} chars (optimal: ${minTitle}-${maxTitle}).`,
                passed: titleOptimalLen,
                pts: 5,
            },
        ];

        const groups = [
            { id: 'basic', title: 'Basic SEO', checks: basicChecks },
            { id: 'additional', title: 'Additional', checks: additionalChecks },
            { id: 'title_readability', title: 'Title Readability', checks: titleChecks },
        ];

        let score = 0;
        groups.forEach(g => {
            g.errors = g.checks.filter(c => !c.passed).length;
            g.checks.forEach(c => { if (c.passed) score += c.pts; });
        });

        let gradeBadge = 'Needs Work';
        let gradeClass = 'poor';
        if (score >= 80) { gradeBadge = 'Good'; gradeClass = 'good'; }
        else if (score >= 50) { gradeBadge = 'Fair'; gradeClass = 'fair'; }

        return {
            score: Math.min(100, score),
            gradeClass: gradeClass,
            gradeBadge: gradeBadge,
            groups: groups,
            primaryKeyword: primary,
            secondaryKeywords: secondaries,
        };
    },

    updateDom: function(fields, initialScore) {
        const result = this.calculate(fields);
        let displayScore = result.score;
        if (result.score === 0 && initialScore !== undefined && initialScore !== null && initialScore !== '') {
            const parsed = parseInt(initialScore, 10);
            if (!isNaN(parsed) && parsed > 0) displayScore = parsed;
        }

        let gradeBadge = result.gradeBadge;
        let gradeClass = result.gradeClass;
        if (displayScore >= 80) { gradeBadge = 'Good'; gradeClass = 'good'; }
        else if (displayScore >= 50) { gradeBadge = 'Fair'; gradeClass = 'fair'; }
        else { gradeBadge = 'Needs Work'; gradeClass = 'poor'; }

        const valEl   = document.getElementById('align-seo-score-val');
        const statEl  = document.getElementById('align-seo-score-status');
        const fillEl  = document.getElementById('align-seo-score-fill');
        const badgeEl = document.getElementById('align-seo-score-badge');
        const listEl  = document.getElementById('align-seo-checklist');

        if (valEl)  valEl.textContent = displayScore;
        if (statEl) statEl.innerHTML = `<span>${gradeBadge}</span><span>${displayScore}/100</span>`;
        if (fillEl) {
            fillEl.style.width = displayScore + '%';
            fillEl.className = 'score-progress-fill ' + gradeClass;
        }
        if (badgeEl) badgeEl.className = 'align-seo-score-badge ' + gradeClass;

        if (listEl) {
            listEl.innerHTML = result.groups.map(g => `
                <div class="align-seo-group open" data-group-id="${g.id}">
                    <div class="align-seo-group-header">
                        <div class="align-seo-group-title">
                            <i class="la la-angle-down align-seo-group-arrow"></i>
                            <span>${g.title}</span>
                        </div>
                        <span class="align-seo-group-badge ${g.errors > 0 ? 'error' : 'pass'}">
                            ${g.errors > 0 ? `<i class="la la-times"></i> ${g.errors} ${g.errors === 1 ? 'Error' : 'Errors'}` : '<i class="la la-check"></i> Passed'}
                        </span>
                    </div>
                    <div class="align-seo-group-items">
                        ${g.checks.map(c => `
                            <div class="align-seo-check-item ${c.passed ? 'passed' : 'failed'}">
                                <i class="la ${c.passed ? 'la-check-circle' : 'la-times-circle'}"></i>
                                <span class="check-text">${c.label}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');
        }
    }
};
