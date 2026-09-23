import fs from 'node:fs';
import path from 'node:path';

const wpPath = process.env.WU008_WP_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!wpPath || !artifactDir) throw new Error('WU008_WP_PATH and WU008_ARTIFACT_DIR are required.');

const root = path.join(wpPath, 'wp-content/plugins/gravityflow');
const gfRoot = path.join(wpPath, 'wp-content/plugins/gravityforms');
const exact = {
  version: process.env.WU008_FLOW_VERSION || null,
  sha256: process.env.WU008_FLOW_SHA256 || null,
  gravityformsVersion: process.env.WU008_GF_VERSION || null,
  gravityformsSha256: process.env.WU008_GF_SHA256 || null,
  pgr_sha: process.env.WU008_PGR_SHA || null,
};
if (!/^[a-f0-9]{40}$/.test(exact.pgr_sha ?? '')) throw new Error('Residual probe must bind to the exact PersianGravity Head.');
if (exact.version !== '3.1.0' || exact.sha256 !== 'ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404') {
  throw new Error('Residual probe requires exact Gravity Flow 3.1.0 owner-supplied package.');
}
if (exact.gravityformsVersion !== '3.1.1.1' || exact.gravityformsSha256 !== '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b') {
  throw new Error('Residual timeline authority probe requires exact Gravity Forms 3.1.1.1 package.');
}

function readFrom(base, relative) {
  const file = path.join(base, relative);
  if (!fs.existsSync(file)) throw new Error(`Missing exact source path: ${relative}`);
  return { relative, content: fs.readFileSync(file, 'utf8') };
}

function read(relative) {
  return readFrom(root, relative);
}

function windows(source, needle, radius = 20) {
  const lines = source.content.split(/\r?\n/);
  const result = [];
  for (let i = 0; i < lines.length; i += 1) {
    if (!lines[i].includes(needle)) continue;
    const start = Math.max(0, i - radius);
    const end = Math.min(lines.length, i + radius + 1);
    result.push({
      file: source.relative,
      line: i + 1,
      start: start + 1,
      end,
      source: lines.slice(start, end).map((text, index) => ({ line: start + index + 1, text })),
    });
  }
  return result;
}

function walkPhp(base, current = base) {
  const files = [];
  for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
    const full = path.join(current, entry.name);
    if (entry.isDirectory()) files.push(...walkPhp(base, full));
    else if (entry.name.endsWith('.php')) files.push(full);
  }
  return files.sort();
}

function searchPhp(base, needle, radius = 16) {
  const result = [];
  for (const file of walkPhp(base)) {
    const relative = path.relative(base, file).replaceAll(path.sep, '/');
    const source = { relative, content: fs.readFileSync(file, 'utf8') };
    result.push(...windows(source, needle, radius));
  }
  return result;
}

const status = read('includes/pages/class-status.php');
const entryDetail = read('includes/pages/class-entry-detail.php');
const printEntries = read('includes/pages/class-print-entries.php');
const step = read('includes/steps/class-step.php');
const common = read('includes/class-common.php');
const flowMain = read('class-gravity-flow.php');

const evidence = {
  schema_version: '1.1.0',
  evidence_class: 'G008_RESIDUAL_EXACT_SOURCE_PROBE',
  exact,
  targets: {
    status_due_date: {
      status_due_date: windows(status, 'due_date', 24),
      status_value_filter: windows(status, 'gravityflow_field_value_status_table', 20),
      status_export: windows(status, 'function export', 28),
      due_getter_call_sites: searchPhp(root, 'get_due_date_timestamp()', 16),
    },
    entry_detail_schedule_due_expiration: {
      entry_detail_due_date: windows(entryDetail, 'due_date', 26),
      entry_detail_schedule: windows(entryDetail, 'schedule', 26),
      entry_detail_expiration: windows(entryDetail, 'expiration', 26),
      due_getter_call_sites: searchPhp(root, 'get_due_date_timestamp()', 20),
      schedule_getter_call_sites: searchPhp(root, 'get_schedule_timestamp()', 20),
      expiration_getter_call_sites: searchPhp(root, 'get_expiration_timestamp()', 20),
      due_date_label_sites: searchPhp(root, "'Due Date'", 16),
      schedule_label_sites: searchPhp(root, "'Schedule'", 16),
      expiration_label_sites: searchPhp(root, "'Expiration'", 16),
      step_due_date: windows(step, 'get_due_date_timestamp', 24),
      step_schedule: windows(step, 'get_schedule_timestamp', 24),
      step_expiration: windows(step, 'get_expiration_timestamp', 24),
    },
    timeline_history: {
      timeline: windows(entryDetail, 'timeline', 32),
      date_created: windows(entryDetail, 'date_created', 24),
      notes: windows(entryDetail, 'notes', 24),
      common_timeline: windows(common, 'timeline', 28),
      flow_main_timeline: windows(flowMain, 'timeline', 28),
      gravityforms_get_lead_notes: searchPhp(gfRoot, 'get_lead_notes', 24),
      gravityforms_note_date_created: searchPhp(gfRoot, 'date_created', 12)
        .filter((occurrence) => occurrence.file.includes('note') || occurrence.source.some((line) => line.text.includes('lead_notes') || line.text.includes('entry_notes'))),
    },
    print: {
      print_timeline: windows(printEntries, 'timeline', 28),
      print_entry_detail: windows(printEntries, 'entry_detail', 28),
      print_styles: windows(printEntries, 'gravityflow_print_styles', 20),
    },
  },
};

fs.mkdirSync(artifactDir, { recursive: true });
fs.writeFileSync(path.join(artifactDir, 'g008-residual-source-probe.json'), `${JSON.stringify(evidence, null, 2)}\n`);
console.log('G008_RESIDUAL_SOURCE_PROBE exact Flow 3.1.0');
