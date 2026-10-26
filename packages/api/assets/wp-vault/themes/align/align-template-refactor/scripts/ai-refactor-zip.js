#!/usr/bin/env node
/**
 * Align Theme AI-Powered Template Refactor Engine
 * Two-Stage AI Refactor Pipeline:
 * Stage 1: Global Header & Footer Extraction (Header/Footer Specialist)
 * Stage 2: Reusable Blocks & Overwrite Layer Handler (Pattern Specialist + Deterministic DOM Tagger)
 *
 * Usage:
 *   node ai-refactor-zip.js <input.zip|input_dir> [output.zip|output_dir]
 */

const fs = require('fs');
const path = require('path');
const http = require('http');
const https = require('https');
const { execSync } = require('child_process');

const AI_CONFIG = {
    endpoint: process.env.AI_ENDPOINT || process.env.OPENROUTER_ENDPOINT || 'https://openrouter.ai/api/v1/chat/completions',
    apiKey: process.env.OPENROUTER_API_KEY || process.env.AI_API_KEY || '',
    model: process.env.AI_MODEL || process.env.OPENROUTER_MODEL || 'google/gemini-2.5-flash',
    timeoutMs: 120000
};

const svgRegistry = new Map();

function log(msg, type = 'info') {
    const prefixes = {
        info: '\x1b[36m[Align AIRefactor]\x1b[0m',
        stage1: '\x1b[35m[Align AIRefactor:Stage1]\x1b[0m',
        stage2: '\x1b[34m[Align AIRefactor:Stage2]\x1b[0m',
        success: '\x1b[32m[Align AIRefactor:SUCCESS]\x1b[0m',
        warn: '\x1b[33m[Align AIRefactor:WARN]\x1b[0m',
        error: '\x1b[31m[Align AIRefactor:ERROR]\x1b[0m'
    };
    console.log(`${prefixes[type] || prefixes.info} ${msg}`);
}

function sanitizeSlug(name) {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function copyDirRecursive(src, dest) {
    if (!fs.existsSync(src)) return;
    if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });

    const entries = fs.readdirSync(src, { withFileTypes: true });
    for (const entry of entries) {
        const srcPath = path.join(src, entry.name);
        const destPath = path.join(dest, entry.name);
        if (entry.isDirectory()) {
            copyDirRecursive(srcPath, destPath);
        } else {
            fs.copyFileSync(srcPath, destPath);
        }
    }
}

function createDomSkeleton(html) {
    let clean = html.replace(/<!--[\s\S]*?-->/g, '');
    clean = clean.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
    clean = clean.replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');

    clean = clean.replace(/<svg\b[^>]*>[\s\S]*?<\/svg>/gi, (match) => {
        const token = `align-svg-token-${svgRegistry.size}`;
        svgRegistry.set(token, match);
        return `<svg data-align-svg="${token}"></svg>`;
    });

    return clean.replace(/\s+/g, ' ').trim();
}

function restoreSvgs(html) {
    if (!html || svgRegistry.size === 0) return html;
    return html.replace(/<svg\b[^>]*data-align-svg=["']([^"']+)["'][^>]*>(?:[\s\S]*?<\/svg>)?/gi, (match, token) => {
        return svgRegistry.get(token) || match;
    });
}

function injectOverwriteAttributes(html, blockSlug = 'block') {
    if (!html || !html.trim()) return html;

    let counter = {};
    function getNextId(prefix) {
        if (!counter[prefix]) counter[prefix] = 1;
        else counter[prefix]++;
        return `${prefix}${counter[prefix]}`;
    }

    let res = html.replace(/<(h[1-6])\b([^>]*)>/gi, (match, tag, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        const id = getNextId('ov-heading-');
        return `<${tag}${attrs} data-allow-overwrite="true" data-overwrite-id="${id}">`;
    });

    res = res.replace(/<(p)\b([^>]*)>/gi, (match, tag, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        const id = getNextId('ov-text-');
        return `<${tag}${attrs} data-allow-overwrite="true" data-overwrite-id="${id}">`;
    });

    res = res.replace(/<(a|button)\b([^>]*)>/gi, (match, tag, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        const id = getNextId('ov-btn-');
        return `<${tag}${attrs} data-allow-overwrite="true" data-overwrite-id="${id}">`;
    });

    res = res.replace(/<(img)\b([^>]*)>/gi, (match, tag, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        const id = getNextId('ov-img-');
        return `<${tag}${attrs} data-allow-overwrite="true" data-overwrite-id="${id}">`;
    });

    return res;
}

function replaceBlockWithShortcode(pageHtml, originalSnippet, slug) {
    const shortcodeDiv = `\n<div class="site-${slug}-wrapper">\n  <div data-component-shortcode="" data-shortcode="[reuse-block slug='${slug}']" data-preview-state="rendered">[reuse-block slug="${slug}"]</div>\n</div>\n`;
    if (!originalSnippet) return pageHtml;

    if (pageHtml.includes(originalSnippet)) {
        return pageHtml.replace(originalSnippet, shortcodeDiv);
    }

    try {
        const trimmed = originalSnippet.trim();
        const escaped = trimmed.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const pattern = new RegExp(escaped.replace(/\s+/g, '\\s+'), 'i');
        if (pattern.test(pageHtml)) {
            return pageHtml.replace(pattern, shortcodeDiv);
        }
    } catch (e) {}

    return pageHtml;
}

function cleanPageBodyMarkup(html) {
    let body = html;
    const bodyMatch = body.match(/<body\b[^>]*>([\s\S]*?)<\/body>/i);
    if (bodyMatch) {
        body = bodyMatch[1];
    } else {
        body = body.replace(/<!DOCTYPE[^>]*>/gi, '');
        body = body.replace(/<\/?(html|head)\b[^>]*>/gi, '');
    }

    body = body.replace(/<header\b[^>]*>[\s\S]*?<\/header>/gi, '');
    body = body.replace(/<footer\b[^>]*>[\s\S]*?<\/footer>/gi, '');
    body = body.replace(/<link\b[^>]*rel=["']?stylesheet["']?[^>]*>/gi, '');

    return body.trim();
}

async function callAiChat(messages, responseFormat = null) {
    const urlObj = new URL(AI_CONFIG.endpoint);
    const client = urlObj.protocol === 'https:' ? https : http;

    const payloadObj = {
        model: AI_CONFIG.model,
        messages: messages
    };
    if (responseFormat) {
        payloadObj.response_format = responseFormat;
    }

    const payload = JSON.stringify(payloadObj);

    return new Promise((resolve, reject) => {
        const reqHeaders = {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${AI_CONFIG.apiKey}`,
            'Content-Length': Buffer.byteLength(payload)
        };
        if (AI_CONFIG.endpoint.includes('openrouter.ai')) {
            reqHeaders['HTTP-Referer'] = 'https://align-theme.local';
            reqHeaders['X-Title'] = 'Align Template Refactor CLI';
        }

        const req = client.request(AI_CONFIG.endpoint, {
            method: 'POST',
            headers: reqHeaders,
            timeout: AI_CONFIG.timeoutMs
        }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    try {
                        const parsed = JSON.parse(body);
                        const content = parsed.choices && parsed.choices[0] && parsed.choices[0].message
                            ? parsed.choices[0].message.content
                            : null;
                        resolve(content);
                    } catch (e) {
                        reject(new Error(`Failed to parse AI JSON response: ${e.message}`));
                    }
                } else {
                    reject(new Error(`AI API error HTTP ${res.statusCode}: ${body}`));
                }
            });
        });

        req.on('error', (err) => reject(err));
        req.on('timeout', () => {
            req.destroy();
            reject(new Error(`AI API request timed out after ${AI_CONFIG.timeoutMs}ms`));
        });

        req.write(payload);
        req.end();
    });
}

async function refactorProjectWithAi(sourceDir, outputDir) {
    svgRegistry.clear();
    log(`Connecting to AI Engine: ${AI_CONFIG.endpoint} (Model: ${AI_CONFIG.model})`);

    let actualSource = sourceDir;
    const initialEntries = fs.readdirSync(actualSource, { withFileTypes: true });
    if (initialEntries.length === 1 && initialEntries[0].isDirectory()) {
        actualSource = path.join(actualSource, initialEntries[0].name);
        log(`Unwrapped nested directory: ${initialEntries[0].name}/`);
    }

    const entries = fs.readdirSync(actualSource, { withFileTypes: true });

    // 1. Copy Global Assets
    const assetFolders = ['css', 'js', 'fonts', 'img', 'images', 'lib', 'libs', 'vendor', 'assets', 'asset', 'styles', 'scripts', 'media'];
    const destAssetsDir = path.join(outputDir, 'assets');
    fs.mkdirSync(destAssetsDir, { recursive: true });

    for (const entry of entries) {
        if (entry.isDirectory()) {
            const lower = entry.name.toLowerCase();
            if (lower === 'assets' || lower === 'asset') {
                copyDirRecursive(path.join(actualSource, entry.name), destAssetsDir);
            } else if (assetFolders.includes(lower)) {
                copyDirRecursive(path.join(actualSource, entry.name), path.join(destAssetsDir, entry.name));
            }
        }
    }

    // 2. Discover HTML files (ignoring __MACOSX, ._*, and dotfiles)
    const htmlMap = {};
    for (const entry of entries) {
        if (entry.name.startsWith('.') || entry.name.startsWith('__MACOSX') || entry.name.startsWith('__')) continue;
        if (entry.isFile() && /\.(html|htm)$/i.test(entry.name)) {
            const baseName = path.basename(entry.name, path.extname(entry.name));
            let slug = sanitizeSlug(baseName);
            if (slug.startsWith('_')) slug = slug.replace(/^_+/, '');
            if (!slug) continue;
            if (slug === 'index') slug = 'home';
            const content = fs.readFileSync(path.join(actualSource, entry.name), 'utf8');
            htmlMap[slug] = content;
        }
    }

    const pageSlugs = Object.keys(htmlMap);
    if (pageSlugs.length === 0) {
        log(`No HTML files found in ${sourceDir}`, 'warn');
        return;
    }

    log(`Discovered ${pageSlugs.length} HTML pages. Building lightweight skeletons...`);

    const samplePages = {};
    let count = 0;
    for (const [slug, rawHtml] of Object.entries(htmlMap)) {
        if (count < 3 || slug === 'home') {
            samplePages[slug] = createDomSkeleton(rawHtml);
            count++;
        }
    }

    // =========================================================================
    // STAGE 1: Extract Global Header & Footer
    // =========================================================================
    log('Running Stage 1: Header/Footer Specialist...', 'stage1');
    const stage1Prompt = `You are the Align Theme Header and Footer Specialist.
Analyze the HTML page skeletons and extract only the global header and global footer:
1. Extract Global Header (logo, topbar, navigation) into "header_html" (preserve data-align-svg attributes).
2. Extract Global Footer (copyright, footer links, widgets) into "footer_html" (preserve data-align-svg attributes).
Output JSON format strictly:
{
  "header_html": "<header>...</header>",
  "footer_html": "<footer>...</footer>"
}`;

    let stage1Data = { header_html: '', footer_html: '' };
    try {
        const res1 = await callAiChat([
            { role: 'system', content: stage1Prompt },
            { role: 'user', content: JSON.stringify({ pages: samplePages }) }
        ], { type: 'json_object' });
        stage1Data = JSON.parse(res1.replace(/^```json\s*/i, '').replace(/\s*```$/i, '').trim());
    } catch (e) {
        log(`Stage 1 warning: ${e.message}`, 'warn');
    }

    if (stage1Data.header_html && stage1Data.header_html.trim()) {
        const headerDir = path.join(outputDir, 'header');
        fs.mkdirSync(headerDir, { recursive: true });
        fs.writeFileSync(path.join(headerDir, 'index.html'), restoreSvgs(stage1Data.header_html.trim()), 'utf8');
        log('Saved header/index.html', 'success');
    }

    if (stage1Data.footer_html && stage1Data.footer_html.trim()) {
        const footerDir = path.join(outputDir, 'footer');
        fs.mkdirSync(footerDir, { recursive: true });
        fs.writeFileSync(path.join(footerDir, 'index.html'), restoreSvgs(stage1Data.footer_html.trim()), 'utf8');
        log('Saved footer/index.html', 'success');
    }

    // =========================================================================
    // STAGE 2: Dedicated Reusable Block Specialist Pass
    // =========================================================================
    log('Running Stage 2: Dedicated Reusable Block Specialist...', 'stage2');
    const bodySkeletons = {};
    for (const [slug, skeleton] of Object.entries(samplePages)) {
        bodySkeletons[slug] = cleanPageBodyMarkup(skeleton);
    }

    const stage2Prompt = `You are the Align Theme Reusable Block Specialist.
Analyze the page bodies (header and footer already removed). Identify reusable blocks (sections appearing across multiple pages or self-contained sections like CTA banners, pricing tables, testimonials, FAQ, contact boxes, partner logos).
For each block provide:
- "slug": semantic kebab-case identifier (e.g. cta-banner, pricing-table, testimonials)
- "original_html": the exact HTML section snippet from the input
Output JSON format strictly:
{
  "reusable_blocks": [
    { "slug": "cta-banner", "original_html": "<section ...>...</section>" }
  ]
}`;

    const reusableBlocks = [];
    try {
        const res2 = await callAiChat([
            { role: 'system', content: stage2Prompt },
            { role: 'user', content: JSON.stringify({ page_bodies: bodySkeletons }) }
        ], { type: 'json_object' });
        const stage2Data = JSON.parse(res2.replace(/^```json\s*/i, '').replace(/\s*```$/i, '').trim());

        if (Array.isArray(stage2Data.reusable_blocks)) {
            for (const b of stage2Data.reusable_blocks) {
                const bSlug = sanitizeSlug(b.slug || 'block');
                const bDir = path.join(outputDir, 'reuse-blocks', bSlug);
                fs.mkdirSync(bDir, { recursive: true });

                const rawSnippet = restoreSvgs(b.original_html || '');
                const taggedBlock = injectOverwriteAttributes(rawSnippet, bSlug);

                fs.writeFileSync(path.join(bDir, 'index.html'), taggedBlock, 'utf8');
                reusableBlocks.push({ slug: bSlug, original: rawSnippet });
                log(`Saved reuse-blocks/${bSlug}/index.html (Injected Overwrite Layer & Shortcode: [reuse-block slug="${bSlug}"])`, 'success');
            }
        }
    } catch (e) {
        log(`Stage 2 warning: ${e.message}`, 'warn');
    }

    // =========================================================================
    // STAGE 3: Assemble All Pages (Inject Shortcodes & Clean Body)
    // =========================================================================
    for (const [slug, rawContent] of Object.entries(htmlMap)) {
        let content = rawContent;
        for (const b of reusableBlocks) {
            if (b.original) {
                content = replaceBlockWithShortcode(content, b.original, b.slug);
            }
        }

        const cleanBody = cleanPageBodyMarkup(content);
        const pageDir = path.join(outputDir, slug);
        fs.mkdirSync(pageDir, { recursive: true });
        fs.writeFileSync(path.join(pageDir, 'index.html'), cleanBody, 'utf8');
        log(`Saved page: ${slug}/index.html (Shortcodes injected)`, 'success');
    }

    log(`🎉 2-Stage Refactor Pipeline Completed: Header, Footer, ${reusableBlocks.length} Reusable Blocks, ${pageSlugs.length} Pages`, 'success');
}

async function main() {
    const args = process.argv.slice(2);
    if (args.length < 1) {
        console.log('Usage: node ai-refactor-zip.js <input.zip|input_dir> [output.zip|output_dir]');
        process.exit(1);
    }

    const inputPath = path.resolve(args[0]);
    if (!fs.existsSync(inputPath)) {
        log(`Input path not found: ${inputPath}`, 'error');
        process.exit(1);
    }

    const isZip = inputPath.toLowerCase().endsWith('.zip');
    let tempExtractDir = null;
    let tempOutputDir = null;

    try {
        let workSourceDir = inputPath;

        if (isZip) {
            tempExtractDir = path.join('/tmp', 'align-ai-raw-' + Date.now());
            tempOutputDir = path.join('/tmp', 'align-ai-out-' + Date.now());
            fs.mkdirSync(tempExtractDir, { recursive: true });
            fs.mkdirSync(tempOutputDir, { recursive: true });

            log(`Unpacking ZIP: ${inputPath}`);
            execSync(`unzip -q "${inputPath}" -d "${tempExtractDir}"`);
            workSourceDir = tempExtractDir;
        } else {
            tempOutputDir = args[1] ? path.resolve(args[1]) : path.join(path.dirname(inputPath), path.basename(inputPath) + '-align-ready');
            fs.mkdirSync(tempOutputDir, { recursive: true });
        }

        await refactorProjectWithAi(workSourceDir, isZip ? tempOutputDir : tempOutputDir);

        if (isZip) {
            const defaultOutputZip = path.join(
                path.dirname(inputPath),
                path.basename(inputPath, '.zip') + '-align-ready.zip'
            );
            const outputZip = args[1] ? path.resolve(args[1]) : defaultOutputZip;

            log(`Packaging final Align-ready ZIP: ${outputZip}`);
            if (fs.existsSync(outputZip)) fs.unlinkSync(outputZip);
            execSync(`cd "${tempOutputDir}" && zip -rq "${outputZip}" .`);
            log(`🎉 Successfully created Align-ready ZIP at: ${outputZip}`, 'success');
        } else {
            log(`🎉 Successfully created Align-ready template folder at: ${tempOutputDir}`, 'success');
        }
    } catch (err) {
        log(`Refactor failed: ${err.message}`, 'error');
    } finally {
        if (tempExtractDir && fs.existsSync(tempExtractDir)) execSync(`rm -rf "${tempExtractDir}"`);
        if (isZip && tempOutputDir && fs.existsSync(tempOutputDir)) execSync(`rm -rf "${tempOutputDir}"`);
    }
}

main();
