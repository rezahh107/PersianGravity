import fs from 'node:fs';
import path from 'node:path';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
const expectedHead = process.env.WU008_PGR_SHA;
const expectedViewSha = process.env.WU008_VIEW_SHA256;

if (!wpPath || !artifactDir || !/^[a-f0-9]{40}$/.test(expectedHead ?? '') || !/^[a-f0-9]{64}$/.test(expectedViewSha ?? '')) {
  throw new Error('WU008_WP_PATH, WU008_ARTIFACT_DIR, exact Head and exact GravityView package SHA are required.');
}

const root = path.join(wpPath, 'wp-content/plugins/gravityview');
if (!fs.existsSync(root)) throw new Error('Exact GravityView source root is missing.');

function walk(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...walk(full));
    else if (/\.php$/i.test(entry.name)) out.push(full);
  }
  return out.sort();
}

function windowFor(lines, index, radius = 10) {
  const start = Math.max(0, index - radius);
  const end = Math.min(lines.length, index + radius + 1);
  return {
    start_line: start + 1,
    end_line: end,
    lines: lines.slice(start, end).map((text, offset) => ({ line: start + offset + 1, text })),
  };
}

const files = walk(root);
const hits = [];
const needles = [
  'date_created',
  'date_updated',
  'gravityview/template/field/',
  'gravityview/template/field/{field_type}/output',
  'orderby',
  'search_criteria',
  'sorting',
  'GFAPI::get_entries',
  'GF_Query',
  'filter_',
  'gv_search',
  'entry_date',
  'sort_columns',
  'is_field_sortable',
  'from_search_criteria',
  'date_range',
  'to_utc',
];

for (const file of files) {
  const relative = path.relative(root, file).replaceAll(path.sep, '/');
  const lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
  for (let index = 0; index < lines.length; index += 1) {
    const matched = needles.filter((needle) => lines[index].includes(needle));
    if (matched.length === 0) continue;
    hits.push({
      file: relative,
      line: index + 1,
      matched,
      window: windowFor(lines, index),
    });
  }
}

const dateHits = hits.filter((hit) => hit.matched.includes('date_created') || hit.matched.includes('date_updated'));
const filterHits = hits.filter((hit) => hit.matched.some((needle) => needle.startsWith('gravityview/template/field/')));
const queryHits = hits.filter((hit) => hit.matched.some((needle) => ['orderby','search_criteria','sorting','GFAPI::get_entries','GF_Query','filter_','gv_search','entry_date','sort_columns','is_field_sortable','from_search_criteria','date_range','to_utc'].includes(needle)));

if (dateHits.length === 0) throw new Error('No exact GravityView date_created/date_updated source hits found.');
if (filterHits.length === 0) throw new Error('No GravityView field output filter family source hits found.');

const evidence = {
  schema_version: '1.0.0',
  program: 'G-008',
  product: 'GravityView',
  exact_version: '3.3.4',
  exact_package_sha256: expectedViewSha,
  exact_persiangravity_head: expectedHead,
  source_root: 'wp-content/plugins/gravityview',
  date_hits: dateHits,
  field_output_filter_hits: filterHits,
  query_sort_filter_hits: queryHits,
};

fs.mkdirSync(artifactDir, { recursive: true });
fs.writeFileSync(path.join(artifactDir, 'g008-gravityview-source-probe.json'), JSON.stringify(evidence, null, 2) + '\n');
console.log(`GravityView source probe: ${dateHits.length} date hits, ${filterHits.length} filter hits, ${queryHits.length} query/sort/filter hits.`);
