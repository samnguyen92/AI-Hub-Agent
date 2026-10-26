#!/usr/bin/env node
/**
 * Align Theme ZIP Template Refactor Engine
 * CLI tool that accepts a raw HTML .zip archive, extracts, analyzes,
 * automatically refactors into Align Theme hierarchy, and re-zips into an Align-ready .zip archive.
 *
 * Usage:
 *   node refactor-zip.js <input.zip> [output.zip]
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

function log(msg, type = 'info') {
    const prefixes = {
        info: '\x1b[36m[Align ZipRefactor]\x1b[0m',
        success: '\x1b[32m[Align ZipRefactor:SUCCESS]\x1b[0m',
        warn: '\x1b[33m[Align ZipRefactor:WARN]\x1b[0m',
        error: '\x1b[31m[Align ZipRefactor:ERROR]\x1b[0m'
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

function injectDataOverwriteAttributes(blockHtml) {
    let output = blockHtml;
    let titleIdx = 1;
    let descIdx = 1;
    let btnIdx = 1;
    let imgIdx = 1;

    // 1. Headings
    output = output.replace(/<h([1-6])\b([^>]*)>/gi, (match, level, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        return `<h${level}${attrs} data-allow-overwrite="true" data-overwrite-id="ov-title-${titleIdx++}">`;
    });

    // 2. Paragraphs
    output = output.replace(/<p\b([^>]*)>/gi, (match, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        return `<p${attrs} data-allow-overwrite="true" data-overwrite-id="ov-desc-${descIdx++}">`;
    });

    // 3. Links & Buttons
    output = output.replace(/<(a|button)\b([^>]*)>/gi, (match, tag, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        if ('button' === tag.toLowerCase() || /class=["'][^"']*\b(btn|button|cta|action|link)\b[^"']*["']/i.test(attrs) || /href=["'][^"']+["']/i.test(attrs)) {
            return `<${tag}${attrs} data-allow-overwrite="true" data-overwrite-id="ov-btn-${btnIdx++}">`;
        }
        return match;
    });

    // 4. Images
    output = output.replace(/<img\b([^>]*)>/gi, (match, attrs) => {
        if (/data-allow-overwrite/i.test(attrs)) return match;
        return `<img${attrs} data-allow-overwrite="true" data-overwrite-id="ov-img-${imgIdx++}">`;
    });

    return output;
}

function cleanPageBody(html) {
    let body = html;
    const bodyMatch = body.match(/<body\b[^>]*>([\s\S]*?)<\/body>/i);
    if (bodyMatch) {
        body = bodyMatch[1];
    } else {
        body = body.replace(/<!DOCTYPE[^>]*>/gi, '');
        body = body.replace(/<\/?(html|head)\b[^>]*>/gi, '');
    }

    body = removeTagBlock(body, 'header');
    body = removeTagBlock(body, 'footer');
    body = body.replace(/<link\b[^>]*rel=["']?stylesheet["']?[^>]*>/gi, '');

    return body.trim();
}

function processDirectory(sourceDir, outputDir) {
    if (!fs.existsSync(outputDir)) {
        fs.mkdirSync(outputDir, { recursive: true });
    }

    // Auto-unwrap if single folder
    let actualSource = sourceDir;
    const initialEntries = fs.readdirSync(actualSource, { withFileTypes: true });
    if (initialEntries.length === 1 && initialEntries[0].isDirectory()) {
        actualSource = path.join(actualSource, initialEntries[0].name);
        log(`Unwrapped nested root directory: ${initialEntries[0].name}/`);
    }

    const entries = fs.readdirSync(actualSource, { withFileTypes: true });

    // 1. Copy Assets
    const assetFolders = ['css', 'js', 'fonts', 'img', 'images', 'lib', 'libs', 'vendor', 'assets', 'styles', 'scripts', 'media'];
    const destAssetsDir = path.join(outputDir, 'assets');

    for (const entry of entries) {
        if (entry.isDirectory() && assetFolders.includes(entry.name.toLowerCase())) {
            log(`Copying asset folder: ${entry.name}/ -> assets/${entry.name}/`);
            copyDirRecursive(path.join(actualSource, entry.name), path.join(destAssetsDir, entry.name));
        }
    }

    // 2. Discover HTML files
    const htmlFiles = [];
    for (const entry of entries) {
        if (entry.isFile() && /\.(html|htm)$/i.test(entry.name)) {
            htmlFiles.push(entry.name);
        }
    }

    if (htmlFiles.length === 0) {
        log(`No HTML files found in extracted archive`, 'warn');
        return;
    }

    log(`Found ${htmlFiles.length} HTML files to refactor.`);

    let extractedHeader = null;
    let extractedFooter = null;
    const discoveredBlocks = new Map();

    // Pass 1: Extract Header, Footer & Reusable Blocks
    for (const filename of htmlFiles) {
        const filePath = path.join(actualSource, filename);
        const rawContent = fs.readFileSync(filePath, 'utf8');

        if (!extractedHeader) {
            const h = extractTagBlock(rawContent, 'header');
            if (h) {
                extractedHeader = h;
                log(`Detected Header in ${filename}`);
            }
        }

        if (!extractedFooter) {
            const f = extractTagBlock(rawContent, 'footer');
            if (f) {
                extractedFooter = f;
                log(`Detected Footer in ${filename}`);
            }
        }

        const candidates = extractReusableCandidates(rawContent);
        for (const cand of candidates) {
            if (!discoveredBlocks.has(cand.slug)) {
                discoveredBlocks.set(cand.slug, cand.html);
                log(`Detected Reusable Block: "${cand.slug}" in ${filename}`);
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

    // Write Reusable Blocks
    for (const [slug, rawBlockHtml] of discoveredBlocks.entries()) {
        const optimizedHtml = injectDataOverwriteAttributes(rawBlockHtml);
        const blockDir = path.join(outputDir, 'reuse-blocks', slug);
        fs.mkdirSync(blockDir, { recursive: true });
        fs.writeFileSync(path.join(blockDir, 'index.html'), optimizedHtml.trim(), 'utf8');
        log(`Saved reuse-blocks/${slug}/index.html (with data-allow-overwrite)`, 'success');
    }

    // Pass 2: Clean & Write Pages
    for (const filename of htmlFiles) {
        const baseName = path.basename(filename, path.extname(filename));
        let pageSlug = sanitizeSlug(baseName);
        if (pageSlug === 'index') pageSlug = 'home';

        const filePath = path.join(actualSource, filename);
        let rawContent = fs.readFileSync(filePath, 'utf8');

        for (const [slug, rawBlockHtml] of discoveredBlocks.entries()) {
            if (rawContent.includes(rawBlockHtml)) {
                const shortcodeDiv = `\n<div class="site-${slug}-wrapper">\n  <div data-component-shortcode="" data-shortcode="[reuse-block slug='${slug}']" data-preview-state="rendered">[reuse-block slug="${slug}"]</div>\n</div>\n`;
                rawContent = rawContent.replace(rawBlockHtml, shortcodeDiv);
                log(`Injected shortcode [reuse-block slug="${slug}"] in page ${pageSlug}`);
            }
        }

        const cleanBody = cleanPageBody(rawContent);
        const pageDir = path.join(outputDir, pageSlug);
        fs.mkdirSync(pageDir, { recursive: true });
        fs.writeFileSync(path.join(pageDir, 'index.html'), cleanBody, 'utf8');
        log(`Saved page: ${pageSlug}/index.html`, 'success');
    }
}

function main() {
    const args = process.argv.slice(2);
    if (args.length < 1) {
        console.log('Usage: node refactor-zip.js <input.zip> [output.zip]');
        process.exit(1);
    }

    const inputZip = path.resolve(args[0]);
    if (!fs.existsSync(inputZip)) {
        log(`Input ZIP file not found: ${inputZip}`, 'error');
        process.exit(1);
    }

    const defaultOutputZip = path.join(
        path.dirname(inputZip),
        path.basename(inputZip, '.zip') + '-align-ready.zip'
    );
    const outputZip = args[1] ? path.resolve(args[1]) : defaultOutputZip;

    const tempExtractDir = path.join('/tmp', 'align-raw-' + Date.now());
    const tempOutputDir = path.join('/tmp', 'align-out-' + Date.now());

    try {
        log(`Unpacking input ZIP: ${inputZip}`);
        fs.mkdirSync(tempExtractDir, { recursive: true });
        execSync(`unzip -q "${inputZip}" -d "${tempExtractDir}"`);

        log(`Refactoring template structure...`);
        processDirectory(tempExtractDir, tempOutputDir);

        log(`Packaging final Align-ready ZIP: ${outputZip}`);
        if (fs.existsSync(outputZip)) fs.unlinkSync(outputZip);
        execSync(`cd "${tempOutputDir}" && zip -rq "${outputZip}" .`);

        log(`🎉 Successfully created Align-ready ZIP at: ${outputZip}`, 'success');
    } catch (err) {
        log(`Failed to process ZIP: ${err.message}`, 'error');
    } finally {
        // Cleanup temp directories
        if (fs.existsSync(tempExtractDir)) execSync(`rm -rf "${tempExtractDir}"`);
        if (fs.existsSync(tempOutputDir)) execSync(`rm -rf "${tempOutputDir}"`);
    }
}

main();
