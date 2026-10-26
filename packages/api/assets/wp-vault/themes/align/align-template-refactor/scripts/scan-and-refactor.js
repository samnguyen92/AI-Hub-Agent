#!/usr/bin/env node
/**
 * Align Theme Template Refactor Engine
 * CLI tool to scan raw HTML templates and automatically refactor them into Align Theme ZIP structure.
 * Automatically injects data-allow-overwrite and data-overwrite-id attributes into Reusable Blocks.
 * 
 * Usage:
 *   node scan-and-refactor.js <source_dir> <output_dir>
 */

const fs = require('fs');
const path = require('path');

function log(msg, type = 'info') {
    const prefixes = {
        info: '\x1b[36m[Align Refactor]\x1b[0m',
        success: '\x1b[32m[Align Refactor:SUCCESS]\x1b[0m',
        warn: '\x1b[33m[Align Refactor:WARN]\x1b[0m',
        error: '\x1b[31m[Align Refactor:ERROR]\x1b[0m'
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

function extractTagBlock(html, tagName) {
    const regex = new RegExp(`<${tagName}\\b[^>]*>([\\s\\S]*?)<\\/${tagName}>`, 'i');
    const match = html.match(regex);
    return match ? match[0] : null;
}

function removeTagBlock(html, tagName) {
    const regex = new RegExp(`<${tagName}\\b[^>]*>[\\s\\S]*?<\\/${tagName}>`, 'gi');
    return html.replace(regex, '');
}

function extractReusableCandidates(html) {
    const candidates = [];
    const sectionRegex = /<section\b([^>]*)>([\s\S]*?)<\/section>/gi;
    let match;

    while ((match = sectionRegex.exec(html)) !== null) {
        const fullTag = match[0];
        const attrs = match[1];
        
        let slug = null;
        if (/cta|call-to-action/i.test(attrs)) slug = 'cta-banner';
        else if (/pricing|plans/i.test(attrs)) slug = 'pricing-table';
        else if (/testimonial|reviews|feedback/i.test(attrs)) slug = 'testimonials';
        else if (/newsletter|subscribe/i.test(attrs)) slug = 'newsletter';
        else if (/faq|accordion/i.test(attrs)) slug = 'faq';
        else if (/client|partner|sponsor|brand/i.test(attrs)) slug = 'partner-logos';

        if (slug) {
            candidates.push({ slug, html: fullTag });
        }
    }

    return candidates;
}

/**
 * Automatically inject data-allow-overwrite and unique data-overwrite-id attributes into Reusable Block markup.
 */
function injectDataOverwriteAttributes(blockHtml) {
    let output = blockHtml;
    let titleIdx = 1;
    let descIdx = 1;
    let btnIdx = 1;
    let imgIdx = 1;

    // 1. Inject on Headings (h1 - h6)
    output = output.replace(/<h([1-6])\b([^>]*)>/gi, (match, level, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        const ovId = `ov-title-${titleIdx++}`;
        return `<h${level}${attrs} data-allow-overwrite="true" data-overwrite-id="${ovId}">`;
    });

    // 2. Inject on Paragraphs (p)
    output = output.replace(/<p\b([^>]*)>/gi, (match, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        const ovId = `ov-desc-${descIdx++}`;
        return `<p${attrs} data-allow-overwrite="true" data-overwrite-id="${ovId}">`;
    });

    // 3. Inject on Action Links and Buttons (a.btn, a.cta, button)
    output = output.replace(/<(a|button)\b([^>]*)>/gi, (match, tag, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        if ('button' === tag.toLowerCase() || /class=["'][^"']*\b(btn|button|cta|action|link)\b[^"']*["']/i.test(attrs) || /href=["'][^"']+["']/i.test(attrs)) {
            const ovId = `ov-btn-${btnIdx++}`;
            return `<${tag}${attrs} data-allow-overwrite="true" data-overwrite-id="${ovId}">`;
        }
        return match;
    });

    // 4. Inject on Images (img)
    output = output.replace(/<img\b([^>]*)>/gi, (match, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        const ovId = `ov-img-${imgIdx++}`;
        return `<img${attrs} data-allow-overwrite="true" data-overwrite-id="${ovId}">`;
    });

    return output;
}

function cleanPageBody(html) {
    let body = html;
    
    // Extract inner body if body tag exists
    const bodyMatch = body.match(/<body\b[^>]*>([\s\S]*?)<\/body>/i);
    if (bodyMatch) {
        body = bodyMatch[1];
    } else {
        body = body.replace(/<!DOCTYPE[^>]*>/gi, '');
        body = body.replace(/<\/?(html|head)\b[^>]*>/gi, '');
    }

    // Remove <header> and <footer> tags
    body = removeTagBlock(body, 'header');
    body = removeTagBlock(body, 'footer');

    // Remove stray style/script links
    body = body.replace(/<link\b[^>]*rel=["']?stylesheet["']?[^>]*>/gi, '');

    return body.trim();
}

function refactorTemplate(sourceDir, outputDir) {
    if (!fs.existsSync(sourceDir)) {
        log(`Source directory does not exist: ${sourceDir}`, 'error');
        process.exit(1);
    }

    log(`Starting refactor: "${sourceDir}" -> "${outputDir}"`);

    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    const entries = fs.readdirSync(sourceDir, { withFileTypes: true });

    // 1. Identify and copy assets
    const assetFolders = ['css', 'js', 'fonts', 'img', 'images', 'lib', 'libs', 'vendor', 'assets', 'styles', 'scripts', 'media'];
    const destAssetsDir = path.join(outputDir, 'assets');

    for (const entry of entries) {
        if (entry.isDirectory() && assetFolders.includes(entry.name.toLowerCase())) {
            log(`Copying asset folder: ${entry.name}/ -> assets/${entry.name}/`);
            copyDirRecursive(path.join(sourceDir, entry.name), path.join(destAssetsDir, entry.name));
        }
    }

    // 2. Discover all HTML files
    const htmlFiles = [];
    for (const entry of entries) {
        if (entry.isFile() && /\.(html|htm)$/i.test(entry.name)) {
            htmlFiles.push(entry.name);
        }
    }

    if (htmlFiles.length === 0) {
        log(`No HTML files found in ${sourceDir}`, 'warn');
        return;
    }

    log(`Found ${htmlFiles.length} HTML files to process.`);

    let extractedHeader = null;
    let extractedFooter = null;
    const discoveredBlocks = new Map(); // slug -> original raw html

    // Pass 1: Extract Header, Footer, and detect Reusable Blocks
    for (const filename of htmlFiles) {
        const filePath = path.join(sourceDir, filename);
        const rawContent = fs.readFileSync(filePath, 'utf8');

        // Header detection
        if (!extractedHeader) {
            const h = extractTagBlock(rawContent, 'header');
            if (h) {
                extractedHeader = h;
                log(`Detected Header from ${filename}`);
            }
        }

        // Footer detection
        if (!extractedFooter) {
            const f = extractTagBlock(rawContent, 'footer');
            if (f) {
                extractedFooter = f;
                log(`Detected Footer from ${filename}`);
            }
        }

        // Reusable blocks candidate detection
        const candidates = extractReusableCandidates(rawContent);
        for (const cand of candidates) {
            if (!discoveredBlocks.has(cand.slug)) {
                discoveredBlocks.set(cand.slug, cand.html);
                log(`Detected Reusable Block candidate: "${cand.slug}" in ${filename}`);
            }
        }
    }

    // Write Header
    if (extractedHeader) {
        const headerDir = path.join(outputDir, 'header');
        fs.mkdirSync(headerDir, { recursive: true });
        fs.writeFileSync(path.join(headerDir, 'index.html'), extractedHeader.trim(), 'utf8');
        log(`Saved header/index.html`, 'success');
    }

    // Write Footer
    if (extractedFooter) {
        const footerDir = path.join(outputDir, 'footer');
        fs.mkdirSync(footerDir, { recursive: true });
        fs.writeFileSync(path.join(footerDir, 'index.html'), extractedFooter.trim(), 'utf8');
        log(`Saved footer/index.html`, 'success');
    }

    // Write Reusable Blocks with Data Overwrite enabled
    for (const [slug, rawBlockHtml] of discoveredBlocks.entries()) {
        const optimizedBlockHtml = injectDataOverwriteAttributes(rawBlockHtml);
        const blockDir = path.join(outputDir, 'reuse-blocks', slug);
        fs.mkdirSync(blockDir, { recursive: true });
        fs.writeFileSync(path.join(blockDir, 'index.html'), optimizedBlockHtml.trim(), 'utf8');
        log(`Saved reuse-blocks/${slug}/index.html (Injected data-allow-overwrite & Shortcode: [reuse-block slug="${slug}"])`, 'success');
    }

    // Pass 2: Clean and write Pages
    for (const filename of htmlFiles) {
        const baseName = path.basename(filename, path.extname(filename));
        let pageSlug = sanitizeSlug(baseName);
        if (pageSlug === 'index') pageSlug = 'home';

        const filePath = path.join(sourceDir, filename);
        let rawContent = fs.readFileSync(filePath, 'utf8');

        // Replace reusable blocks with shortcodes in page body
        for (const [slug, rawBlockHtml] of discoveredBlocks.entries()) {
            if (rawContent.includes(rawBlockHtml)) {
                const shortcodeDiv = `\n<div class="site-${slug}-wrapper">\n  <div data-component-shortcode="" data-shortcode="[reuse-block slug='${slug}']" data-preview-state="rendered">[reuse-block slug="${slug}"]</div>\n</div>\n`;
                rawContent = rawContent.replace(rawBlockHtml, shortcodeDiv);
                log(`Replaced block "${slug}" with shortcode in ${pageSlug}`);
            }
        }

        // Clean page body (strip doctype, head, header, footer)
        const cleanBody = cleanPageBody(rawContent);

        // Write page folder
        const pageDir = path.join(outputDir, pageSlug);
        fs.mkdirSync(pageDir, { recursive: true });
        fs.writeFileSync(path.join(pageDir, 'index.html'), cleanBody, 'utf8');
        log(`Saved page: ${pageSlug}/index.html`, 'success');
    }

    log(`Template Refactoring Completed Successfully! Ready to ZIP and Import into Align Theme.`, 'success');
}

// CLI Execution
const args = process.argv.slice(2);
if (args.length < 2) {
    console.log('Usage: node scan-and-refactor.js <source_dir> <output_dir>');
    process.exit(1);
}

refactorTemplate(path.resolve(args[0]), path.resolve(args[1]));
