#!/usr/bin/env node
/**
 * 🔍 Align Builder Fast Semantic Search Engine & Knowledge Graph API
 *
 * Can be executed directly via CLI or imported as a CommonJS module.
 *
 * CLI Usage:
 *   node align/core/builder/manifest/search.js "<query>"             # Search files by query in path, title, keywords, symbols
 *   node align/core/builder/manifest/search.js --cluster "Engine"    # Filter by cluster name
 *   node align/core/builder/manifest/search.js --keyword "sortable"  # Filter by specific keyword
 *   node align/core/builder/manifest/search.js --related <file_path> # Get 360-degree dependencies and related files
 *   node align/core/builder/manifest/search.js --list-clusters       # List all clusters and statistics
 *   node align/core/builder/manifest/search.js "<query>" --json      # Output raw JSON for AI consumption
 *
 * Programmatic Usage:
 *   const { search, findRelated, listClusters, getFile } = require('./search.js');
 *   const results = search('sortable tree');
 */

const fs = require('fs');
const path = require('path');

const manifestPath = path.join(__dirname, 'manifest.json');
let manifestCache = null;

function loadManifest() {
  if (manifestCache) return manifestCache;
  if (!fs.existsSync(manifestPath)) {
    throw new Error('manifest.json not found in ' + __dirname);
  }
  manifestCache = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  return manifestCache;
}

/**
 * Perform semantic scoring search across files in the manifest.
 * @param {string} queryStr Query terms
 * @param {object} options Filter options: { cluster, keyword, limit }
 * @returns {Array} List of matched file objects
 */
function search(queryStr = '', options = {}) {
  const manifest = loadManifest();
  const limit = options.limit || 10;

  if (options.cluster) {
    const filter = options.cluster.toLowerCase();
    return manifest.files
      .filter(f => f.cluster.toLowerCase().includes(filter))
      .slice(0, limit);
  }

  if (options.keyword) {
    const kw = options.keyword.toLowerCase();
    return manifest.files
      .filter(f => f.keywords.some(k => k.toLowerCase().includes(kw)))
      .slice(0, limit);
  }

  const terms = queryStr.toLowerCase().split(/\s+/).filter(Boolean);
  if (terms.length === 0) return [];

  const scored = manifest.files.map(file => {
    let score = 0;
    const pathLower = file.path.toLowerCase();
    const titleLower = file.title.toLowerCase();
    const descLower = file.description.toLowerCase();
    const symbolsLower = file.symbols_and_apis.join(' ').toLowerCase();
    const keywordsLower = file.keywords.join(' ').toLowerCase();

    for (const term of terms) {
      if (pathLower.includes(term)) score += 10;
      if (titleLower.includes(term)) score += 8;
      if (keywordsLower.includes(term)) score += 6;
      if (symbolsLower.includes(term)) score += 5;
      if (descLower.includes(term)) score += 2;
    }

    return { file, score };
  });

  return scored
    .filter(r => r.score > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, limit)
    .map(r => r.file);
}

/**
 * Find 360-degree graph relationships for a file.
 * @param {string} targetPath File path or substring
 * @returns {object|null} File object with relationships
 */
function findRelated(targetPath = '') {
  const manifest = loadManifest();
  const target = targetPath.toLowerCase();
  const file = manifest.files.find(f => f.path.toLowerCase().includes(target));
  if (!file) return null;

  return {
    path: file.path,
    title: file.title,
    cluster: file.cluster,
    relationships: file.relationships
  };
}

/**
 * Find a specific file by exact or partial path.
 * @param {string} targetPath 
 * @returns {object|null}
 */
function getFile(targetPath = '') {
  const manifest = loadManifest();
  const target = targetPath.toLowerCase();
  return manifest.files.find(f => f.path.toLowerCase().includes(target)) || null;
}

/**
 * List all clusters with file counts and descriptions.
 * @returns {Array}
 */
function listClusters() {
  const manifest = loadManifest();
  return manifest.clusters.map(c => ({
    ...c,
    file_count: manifest.statistics.files_by_cluster[c.name] || 0
  }));
}

// Export programmatic API
module.exports = {
  search,
  findRelated,
  getFile,
  listClusters,
  loadManifest
};

// If run from command line
if (require.main === module) {
  const args = process.argv.slice(2);

  if (args.length === 0 || args.includes('-h') || args.includes('--help')) {
    console.log(`
🔍 Align Builder Semantic Search Engine
Usage:
  node search.js <query>                 Search files by keyword, symbol, or filename
  node search.js --cluster <name>        Filter files by cluster name
  node search.js --keyword <keyword>     Search exact keyword
  node search.js --related <path>        Find 360° dependencies and callers for a file
  node search.js --list-clusters         List all 12 functional clusters
  --json                                 Output results as raw JSON
`);
    process.exit(0);
  }

  const isJson = args.includes('--json');
  const queryArgs = args.filter(a => a !== '--json');

  if (queryArgs.includes('--list-clusters')) {
    const clusters = listClusters();
    if (isJson) {
      console.log(JSON.stringify(clusters, null, 2));
    } else {
      console.log('\n🏛️ Align Builder Functional Clusters:');
      clusters.forEach((c, idx) => {
        console.log(`  ${idx + 1}. [${c.name}] (${c.file_count} files) -> ${c.base_path}`);
        console.log(`     ${c.description}`);
      });
    }
    process.exit(0);
  }

  if (queryArgs[0] === '--related' && queryArgs[1]) {
    const rel = findRelated(queryArgs[1]);
    if (!rel) {
      console.log(isJson ? 'null' : `No file matching "${queryArgs[1]}" found.`);
      process.exit(0);
    }
    if (isJson) {
      console.log(JSON.stringify(rel, null, 2));
    } else {
      console.log(`\n🔗 360° Relationships for: ${rel.path} (${rel.title})`);
      console.log(`\n  ◀️ Depends On (${rel.relationships.depends_on.length}):`);
      rel.relationships.depends_on.forEach(dep => console.log(`     - ${dep}`));
      console.log(`\n  ▶️ Depended By (${rel.relationships.depended_by.length}):`);
      rel.relationships.depended_by.forEach(dep => console.log(`     - ${dep}`));
      console.log(`\n  🔄 Related Files (${rel.relationships.related_files.length}):`);
      rel.relationships.related_files.forEach(relFile => console.log(`     - ${relFile}`));
    }
    process.exit(0);
  }

  let results = [];
  if (queryArgs[0] === '--cluster' && queryArgs[1]) {
    results = search('', { cluster: queryArgs[1], limit: 50 });
  } else if (queryArgs[0] === '--keyword' && queryArgs[1]) {
    results = search('', { keyword: queryArgs[1], limit: 50 });
  } else {
    results = search(queryArgs.join(' '), { limit: 10 });
  }

  if (isJson) {
    console.log(JSON.stringify(results, null, 2));
  } else {
    console.log(`\n🔍 Found ${results.length} matching file(s):`);
    results.forEach((f, idx) => {
      console.log(`\n[${idx + 1}] ${f.title} (${f.cluster})`);
      console.log(`    📁 Path: ${f.path} (${f.lines_of_code} lines, ${f.type})`);
      console.log(`    📝 ${f.description.substring(0, 160)}...`);
      console.log(`    🏷️  Keywords: ${f.keywords.slice(0, 8).join(', ')}`);
      if (f.symbols_and_apis && f.symbols_and_apis.length > 0) {
        console.log(`    ⚙️  Symbols: ${f.symbols_and_apis.slice(0, 4).join(', ')}`);
      }
    });
  }
}
