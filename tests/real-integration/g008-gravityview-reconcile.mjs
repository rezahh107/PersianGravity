import fs from 'node:fs';
import path from 'node:path';
import {
  assertMetadataOnlySourceEvidence,
  assertSanitizedProvenance,
} from './g008-gravityview-source-evidence.mjs';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const expectedHead = process.env.WU008_PGR_SHA;
const expectedTree = process.env.WU008_PGR_TREE;
const expectedPgrPackageSha = process.env.WU008_PGR_PACKAGE_SHA256;
const expectedViewSha = process.env.WU008_VIEW_SHA256;
const expectedGfSha = process.env.WU008_GF_SHA256;
if (
  !artifactDir ||
  !/^[a-f0-9]{40}$/.test(expectedHead ?? '') ||
  !/^[a-f0-9]{40}$/.test(expectedTree ?? '') ||
  !/^[a-f0-9]{64}$/.test(expectedPgrPackageSha ?? '') ||
  !/^[a-f0-9]{64}$/.test(expectedViewSha ?? '') ||
  !/^[a-f0-9]{64}$/.test(expectedGfSha ?? '')
) {
  throw new Error('GravityView reconciliation requires exact artifact/head/package identity.');
}

function read(name) {
  return JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));
}
function same(a, b) {
  return JSON.stringify(a) === JSON.stringify(b);
}
function assert(condition, message) {
  if (!condition) throw new Error(message);
}
function allTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object') return Object.values(value).every(allTrue);
  return true;
}
function rowsByToken(snapshot) {
  return new Map(snapshot.rows.map((row) => [row.token, row]));
}
function nativeStructure(snapshot) {
  return {
    sortLinks: snapshot.sortLinks,
    rowAttributes: snapshot.rows.map((row) => ({ token: row.token, attributes: row.attributes })),
  };
}

const source = read('g008-gravityview-source-probe.json');
const fixture = read('g008-gravityview-fixture-baseline.json');
const browser = Object.fromEntries(['enabled', 'disabled', 'english', 'drift'].map((mode) => [mode, read(`g008-gravityview-browser-${mode}.json`)]));
const state = Object.fromEntries(['enabled', 'disabled', 'english', 'drift'].map((mode) => [mode, read(`g008-gravityview-state-${mode}.json`)]));

assert(source.schema_version === '3.0.0', 'GravityView source-probe schema drifted.');
assertMetadataOnlySourceEvidence(source);
assert(source.exact_version === '3.3.4', 'Source probe GravityView version drifted.');
assert(source.exact_package_sha256 === expectedViewSha, 'Source probe GravityView package SHA drifted.');
assert(source.exact_gravityforms_package_sha256 === expectedGfSha, 'Source probe Gravity Forms package SHA drifted.');
assert(source.exact_persiangravity_head === expectedHead, 'Source probe is not bound to exact tested Head.');
assert(allTrue(source.source_contract), `One or more exact source contracts are not proven: ${JSON.stringify(source.source_contract)}`);

assert(fixture.exact_persiangravity_head === expectedHead, 'Fixture is not bound to exact tested Head.');
assert(fixture.exact_gravityview_sha256 === expectedViewSha, 'Fixture GravityView package SHA drifted.');
assert(fixture.site_timezone === 'Asia/Tehran' && fixture.php_timezone === 'UTC', 'Boundary fixture timezone identity drifted.');
assert(fixture.entries.length === 3, 'GravityView fixture cardinality drifted.');
assert(
  fixture.entries.some((entry) => entry.date_created === '2026-03-20 20:29:00' && entry.expected_created_native === '2026-03-20 23:59:00')
  && fixture.entries.some((entry) => entry.date_created === '2026-03-20 20:31:00' && entry.expected_created_native === '2026-03-21 00:01:00'),
  'Boundary-sensitive date_created UTC-to-site-local fixtures are not proven.'
);

for (const mode of ['enabled', 'disabled', 'english', 'drift']) {
  assert(browser[mode].status === 'PASS', `GravityView browser mode ${mode} did not pass.`);
  assert(browser[mode].exact_persiangravity_head === expectedHead, `Browser ${mode} Head drifted.`);
  assert(browser[mode].exact_gravityview_version === '3.3.4', `Browser ${mode} GravityView version drifted.`);
  assert(browser[mode].exact_gravityview_sha256 === expectedViewSha, `Browser ${mode} package SHA drifted.`);
  assert(state[mode].exact_persiangravity_head === expectedHead, `State ${mode} Head drifted.`);
  assert(state[mode].exact_gravityview_version === '3.3.4', `State ${mode} GravityView version drifted.`);
  assert(state[mode].exact_gravityview_sha256 === expectedViewSha, `State ${mode} package SHA drifted.`);
}

assert(state.enabled.module_enabled === true, 'Enabled qualification did not have jalali_presentation enabled.');
assert(state.disabled.module_enabled === false, 'Disabled qualification did not disable jalali_presentation.');
assert(state.english.locale === 'en_US', 'English control locale drifted.');
assert(state.drift.module_enabled === true, 'Version-drift control must keep the module enabled.');
assert(browser.enabled.initial.html.dir === 'rtl', 'Enabled fa_IR surface did not render RTL.');
assert(browser.english.initial.html.dir !== 'rtl', 'English control unexpectedly rendered RTL.');
assert(/^en(?:-|$)/i.test(browser.english.initial.html.lang || ''), `English control document language drifted: ${browser.english.initial.html.lang}`);

const canonicalEntries = (snapshot) => snapshot.entries.map((entry) => ({
  id: entry.id,
  token: entry.token,
  gfapi_date_created: entry.gfapi_date_created,
  gfapi_date_updated: entry.gfapi_date_updated,
  db_date_created: entry.db_date_created,
  db_date_updated: entry.db_date_updated,
  rest_date_created: entry.rest_date_created,
  rest_date_updated: entry.rest_date_updated,
}));

for (const mode of ['disabled', 'english', 'drift']) {
  assert(same(canonicalEntries(state.enabled), canonicalEntries(state[mode])), `Machine semantics differ between enabled and ${mode} modes.`);
  assert(same(state.enabled.gfapi_sort, state[mode].gfapi_sort), `Raw GFAPI sorting differs between enabled and ${mode} modes.`);
  assert(same(browser.enabled.sorting.date_created_asc.entry_ids, browser[mode].sorting.date_created_asc.entry_ids), `GravityView date_created ascending order changed in ${mode} control.`);
  assert(same(browser.enabled.sorting.date_created_desc.entry_ids, browser[mode].sorting.date_created_desc.entry_ids), `GravityView date_created descending order changed in ${mode} control.`);
  assert(same(browser.enabled.sorting.date_updated_asc.entry_ids, browser[mode].sorting.date_updated_asc.entry_ids), `GravityView date_updated ascending order changed in ${mode} control.`);
  assert(same(browser.enabled.sorting.date_updated_desc.entry_ids, browser[mode].sorting.date_updated_desc.entry_ids), `GravityView date_updated descending order changed in ${mode} control.`);
  assert(same(browser.enabled.filtering.date_created_local_2026_03_21.entry_ids, browser[mode].filtering.date_created_local_2026_03_21.entry_ids), `GravityView date_created filter result changed in ${mode} control.`);
  assert(same(browser.enabled.filtering.date_updated_direct_request_noop.entry_ids, browser[mode].filtering.date_updated_direct_request_noop.entry_ids), `GravityView date_updated filter result changed in ${mode} control.`);
}

assert(browser.enabled.filtering.date_created_local_2026_03_21.entry_ids.length === 1, 'date_created search/filter did not isolate one boundary-sensitive entry.');
assert(browser.enabled.filtering.date_updated_direct_request_noop.entry_ids.length === fixture.entries.length, 'date_updated direct request did not retain the exact 3.3.4 native unscoped/no-op result set.');
assert(new URL(browser.enabled.filtering.date_created_local_2026_03_21.url).searchParams.get('gv_start') === '03/21/2026', 'date_created host entry_date query input was not preserved in evidence.');
assert(new URL(browser.enabled.filtering.date_updated_direct_request_noop.url).searchParams.get('filter_date_updated') === '2026-03-21 20:31:00', 'date_updated direct request input was not preserved in evidence.');

const enabledSpans = browser.enabled.initial.rows.flatMap((row) => row.spans);
assert(enabledSpans.filter((span) => span.field === 'date_created').length === fixture.entries.length, 'Enabled date_created presentation was not consumed once per row.');
assert(enabledSpans.filter((span) => span.field === 'date_updated').length === fixture.entries.length, 'Enabled date_updated presentation was not consumed once per row.');
for (const mode of ['disabled', 'english', 'drift']) {
  assert(browser[mode].initial.rows.flatMap((row) => row.spans).length === 0, `${mode} control did not preserve native output.`);
}

for (const expected of fixture.entries) {
  const enabledRow = rowsByToken(browser.enabled.initial).get(expected.token);
  assert(enabledRow, `Missing enabled row for ${expected.token}`);
  assert(enabledRow.text.includes(expected.token), `User-authored text changed for ${expected.token}`);
}
assert(
  same(
    browser.enabled.initial.rows.map((row) => row.token),
    browser.enabled.repeated.rows.map((row) => row.token),
  ),
  'Repeated enabled rendering changed entry identity/order.'
);
assert(same(nativeStructure(browser.enabled.initial), nativeStructure(browser.disabled.initial)), 'Presentation changed GravityView row/sort-link structural attributes.');

const trace = state.enabled.prototype_trace;
assert(Array.isArray(trace), 'Qualification trace is missing.');
assert(
  trace.some((row) => row.hook === 'gravityview/template/field/date_created/output' && row.field_id === 'date_created' && row.presented === true),
  'Runtime did not prove date_created consumption at the exact field-specific GravityView output seam.'
);
assert(
  trace.some((row) => row.hook === 'gravityview/template/field/date_updated/output' && row.field_id === 'date_updated' && row.presented === true),
  'Runtime did not prove date_updated consumption at the exact field-specific GravityView output seam.'
);
assert(
  trace.filter((row) => row.presented === true).every((row) => ['date_created', 'date_updated'].includes(row.field_id)),
  'Qualification prototype presented an unrelated field.'
);

const qualification = {
  schema_version: '2.0.0',
  evidence_class: 'G008_GRAVITYVIEW_DATE_QUALIFICATION_RECONCILIATION',
  status: 'PASS',
  source_contract_proven: true,
  program: 'G-008',
  product: 'GravityView',
  exact_version: '3.3.4',
  exact_package_sha256: expectedViewSha,
  exact_gravityforms_version: '3.1.1.1',
  exact_gravityforms_package_sha256: expectedGfSha,
  exact_persiangravity_head: expectedHead,
  exact_persiangravity_commit: expectedHead,
  exact_persiangravity_tree: expectedTree,
  exact_persiangravity_package_sha256: expectedPgrPackageSha,
  evidence: {
    source: 'g008-gravityview-source-probe.json',
    fixture: 'g008-gravityview-fixture-baseline.json',
    browser: Object.keys(browser).map((mode) => `g008-gravityview-browser-${mode}.json`),
    state: Object.keys(state).map((mode) => `g008-gravityview-state-${mode}.json`),
  },
  source_provenance: source.provenance,
  source_evidence_boundary: source.evidence_boundary,
  independent_source_fail_closed: source.independent_fail_closed,
  source_findings: {
    date_created: 'Exact GravityView DateCreated reads the authoritative Entry date_created value, then formats it through GVCommon::format_date before the field-specific output filter. Exact Gravity Forms source defines date_created as UTC Y-m-d H:i:s.',
    date_updated: 'Exact GravityView DateUpdated inherits the DateCreated renderer with its own date_updated identity. Exact Gravity Forms source defines the updated timestamp as UTC, while GravityView keeps date_updated as a raw system-column request/query identity. The qualification preserves the observed native behavior of a direct filter_date_updated request when no date_updated Search Bar field is configured; it does not claim that every possible date_updated search configuration is unavailable or browser-qualified.',
    timezone: 'The authoritative Entry properties are UTC/system datetimes. In the authentic Asia/Tehran runtime, native GravityView rendering converts those raw instants to site-local civil time before the output seam; the qualification prototype passes the raw UTC DateTime to PGR_Jalali_Presentation::format_datetime(), preserving that site-time presentation domain.',
    consumed_seams: [
      'gravityview/template/field/date_created/output',
      'gravityview/template/field/date_updated/output',
    ],
    context: 'The consumed field-specific filters receive Template_Context with exact field identity and an Entry object exposing as_entry(); the prototype reads authoritative raw date_created/date_updated from that Entry rather than parsing native display strings.',
    query_boundary: 'The host-native entry_date Search Bar request maps gv_start/gv_end to raw date_created with UTC-aware query handling. A direct filter_date_updated request on this fixture, which has no configured date_updated Search Bar field, remains a native no-op in every mode. Sorting and query construction occur upstream of the field output filters, so the qualified presentation seam does not participate in DB/GFAPI/REST/query/sort/filter construction. This evidence does not claim exhaustive browser coverage of every optional GravityView date_updated search configuration.',
  },
  runtime_findings: {
    field_specific_hooks_consumed: true,
    typed_raw_entry_values_available: true,
    boundary_sensitive_utc_to_site_local_native_behavior: true,
    enabled_jalali_visible: true,
    disabled_native_fallback: true,
    english_ltr_native_control: true,
    forced_version_gate_failure_native_fallback: true,
    repeated_render_stable: true,
    host_entry_date_filter_for_date_created_pass: true,
    exact_date_updated_direct_request_noop_preserved: true,
    raw_db_gfapi_rest_equal_across_modes: true,
    gfapi_sort_equal_across_modes: true,
    gravityview_browser_sort_equal_across_modes: true,
    gravityview_browser_filter_equal_across_modes: true,
    query_inputs_and_result_entry_ids_recorded: true,
    row_and_sort_link_attributes_preserved: true,
    unrelated_tokens_preserved: true,
  },
  production_boundary: {
    production_adapter_added: false,
    qualification_only_mu_prototype: true,
    exact_version_fail_closed_required: true,
    locale_context_fail_closed_required: true,
    arbitrary_display_string_parsing_required: false,
    machine_semantics_mutation_required: false,
  },
  dispositions: {
    'gravityview.date-created': 'QUALIFIED_FOR_PRODUCTION_ADAPTER',
    'gravityview.date-updated': 'QUALIFIED_FOR_PRODUCTION_ADAPTER',
  },
};

assertSanitizedProvenance(qualification.source_provenance);
if (
  qualification.source_evidence_boundary?.metadata_only !== true
  || qualification.source_evidence_boundary?.raw_source_persisted !== false
  || qualification.independent_source_fail_closed?.date_created !== true
  || qualification.independent_source_fail_closed?.date_updated !== true
) {
  throw new Error('GravityView qualification source-evidence boundary is incomplete.');
}
fs.writeFileSync(path.join(artifactDir, 'g008-gravityview-date-qualification.json'), JSON.stringify(qualification, null, 2) + '\n');
console.log('PASS GravityView G-008 exact-version qualification reconciliation');
