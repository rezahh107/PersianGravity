import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
if (!artifactDir || !manifestPath) throw new Error('GravityView reconciliation requires artifact/manifest paths.');

const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const source = read('g008-gravityview-source-qualification.json');
const modes = ['enabled', 'disabled', 'version-drift', 'english'];
const states = {};
const browsers = {};
for (const mode of modes) {
  states[mode] = {
    pre: read(`g008-gravityview-state-${mode}-pre.json`),
    post: read(`g008-gravityview-state-${mode}-post.json`),
  };
  browsers[mode] = read(`g008-gravityview-browser-${mode}.json`);
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}
function same(actual, expected, message) {
  if (JSON.stringify(actual) !== JSON.stringify(expected)) {
    throw new Error(`${message}\nexpected=${JSON.stringify(expected)}\nactual=${JSON.stringify(actual)}`);
  }
}

assert(source.gravityview.version === '3.3.4', 'Source evidence is not bound to GravityView 3.3.4.');
assert(source.gravityview.package_sha256 === 'af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829', 'GravityView package hash drifted.');
assert(manifest.package_sha256?.gravityview === source.gravityview.package_sha256, 'Runtime package hash differs from source qualification.');
assert(manifest.package_sha256?.gravityforms === '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b', 'Gravity Forms package hash drifted.');
assert(/^[a-f0-9]{40}$/.test(manifest.persiangravity_source_commit || ''), 'Exact PersianGravity source commit is absent.');
assert(/^[a-f0-9]{40}$/.test(manifest.persiangravity_source_tree || ''), 'Exact PersianGravity source tree is absent.');

const machineBaseline = states.enabled.pre.entries;
const viewBaseline = {
  view_id: states.enabled.pre.view_id,
  form_id: states.enabled.pre.form_id,
  view_form_id: states.enabled.pre.view_form_id,
  view_template: states.enabled.pre.view_template,
  view_fields_sha256: states.enabled.pre.view_fields_sha256,
};
for (const mode of modes) {
  for (const phase of ['pre', 'post']) {
    same(states[mode][phase].entries, machineBaseline, `${mode}/${phase}: DB/GFAPI/REST/marker state changed`);
    same({
      view_id: states[mode][phase].view_id,
      form_id: states[mode][phase].form_id,
      view_form_id: states[mode][phase].view_form_id,
      view_template: states[mode][phase].view_template,
      view_fields_sha256: states[mode][phase].view_fields_sha256,
    }, viewBaseline, `${mode}/${phase}: GravityView configuration changed`);
    assert(states[mode][phase].site_timezone === 'Asia/Tehran', `${mode}/${phase}: site timezone drifted`);
    assert(states[mode][phase].php_timezone === 'UTC', `${mode}/${phase}: PHP timezone drifted`);
  }
}

assert(states.enabled.pre.module_enabled === true && states.enabled.pre.locale === 'fa_IR', 'Enabled qualification mode is not fa_IR + module enabled.');
assert(states.disabled.pre.module_enabled === false && states.disabled.pre.locale === 'fa_IR', 'Disabled control is not fa_IR + module disabled.');
assert(states['version-drift'].pre.module_enabled === true && states['version-drift'].pre.forced_version_drift === true, 'Version-drift control was not armed.');
assert(states.english.pre.module_enabled === true && /^en(?:_|-|$)/i.test(states.english.pre.locale), 'English control is not module-enabled English.');

const queryBaseline = browsers.enabled.queries;
for (const mode of modes) same(browsers[mode].queries, queryBaseline, `${mode}: GravityView sort/filter result identities differ from enabled mode`);

function structuralSurface(browser) {
  const base = browser.base.surface;
  return {
    headers: base.headers,
    rows: base.rows.map((row) => ({
      class: row.class,
      dataRow: row.dataRow,
      cells: row.cells.map((cell, index) => ({
        id: cell.id,
        class: cell.class,
        dataLabel: cell.dataLabel,
        colspan: cell.colspan,
        text: index <= 1 ? cell.text : '<DATE_PRESENTATION>',
      })),
    })),
    links: base.links,
    controls: base.controls,
  };
}
const structuralBaseline = structuralSurface(browsers.disabled);
for (const mode of modes) same(structuralSurface(browsers[mode]), structuralBaseline, `${mode}: links/attributes/controls/user text changed outside date presentation`);

const tracePath = path.join(artifactDir, 'g008-gravityview-probe-trace.jsonl');
const traces = fs.readFileSync(tracePath, 'utf8').trim().split(/\r?\n/).filter(Boolean).map((line) => JSON.parse(line));
const counts = {};
for (const trace of traces) {
  const key = `${trace.hook}|${trace.gate}`;
  counts[key] = (counts[key] || 0) + 1;
}
for (const field of ['date_created', 'date_updated']) {
  const hook = `gravityview/template/field/${field}/output`;
  assert((counts[`${hook}|CONVERTED`] || 0) >= 3, `${field}: exact output seam was not authentically consumed for conversion.`);
  assert((counts[`${hook}|MODULE_OR_FACADE`] || 0) >= 3, `${field}: disabled native fallback was not observed.`);
  assert((counts[`${hook}|FORCED_VERSION_MISMATCH`] || 0) >= 3, `${field}: exact-version fail-closed fallback was not observed.`);
  assert((counts[`${hook}|LOCALE`] || 0) >= 3, `${field}: en_US isolation fallback was not observed.`);
}
for (const trace of traces.filter((item) => item.gate === 'CONVERTED')) {
  assert(trace.raw === trace.context_value, `${trace.field_type}: converted trace did not use authoritative context raw value.`);
  assert(!String(trace.output_after || '').includes(String(trace.raw || '')), `${trace.field_type}: conversion appears to have reused raw display text unexpectedly.`);
}

const qualification = {
  schema_version: '1.0.0',
  evidence_class: 'EXACT_GRAVITYVIEW_3_3_4_G008_QUALIFICATION',
  persiangravity_source_commit: manifest.persiangravity_source_commit,
  persiangravity_source_tree: manifest.persiangravity_source_tree,
  package_identity: {
    gravityview_version: '3.3.4',
    gravityview_sha256: source.gravityview.package_sha256,
    gravityforms_version: '3.1.1.1',
    gravityforms_sha256: manifest.package_sha256.gravityforms,
  },
  source_evidence: source,
  runtime_matrix: {
    enabled_fa_ir: 'PASS',
    disabled_fa_ir_native: 'PASS',
    forced_version_drift_native: 'PASS',
    enabled_en_us_native: 'PASS',
    repeated_rendering: 'PASS',
    raw_sorting_date_created: 'PASS',
    raw_sorting_date_updated: 'PASS',
    search_filter_date_created: 'PASS',
    search_filter_date_updated: 'PASS',
    db_gfapi_rest_equality: 'PASS',
    links_attributes_controls_user_text_isolation: 'PASS',
  },
  probe_gate_counts: counts,
  dispositions: {
    'gravityview.date-created': 'QUALIFIED_FOR_PRODUCTION_ADAPTER',
    'gravityview.date-updated': 'QUALIFIED_FOR_PRODUCTION_ADAPTER',
  },
  production_adapter_present: false,
  qualified_scope: 'Exact GravityView 3.3.4 default unlinked date_created/date_updated field rendering through the type-specific output seam, with authoritative UTC Entry raw value taken from Template_Context. Linked/custom-template configurations remain native unless separately proven.',
};

fs.writeFileSync(path.join(artifactDir, 'g008-gravityview-qualification.json'), `${JSON.stringify(qualification, null, 2)}\n`);
process.stdout.write(`${JSON.stringify({ dispositions: qualification.dispositions, source_commit: qualification.persiangravity_source_commit, probe_gate_counts: counts })}\n`);
