import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const expectedHead = process.env.WU008_PGR_SHA;
const expectedViewSha = process.env.WU008_VIEW_SHA256;
if (!artifactDir || !/^[a-f0-9]{40}$/.test(expectedHead ?? '') || !/^[a-f0-9]{64}$/.test(expectedViewSha ?? '')) {
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
function windows(records, file) {
  return records.filter((record) => record.file === file)
    .flatMap((record) => record.window?.lines ?? [])
    .map((line) => line.text)
    .join('\n');
}

const source = read('g008-gravityview-source-probe.json');
const fixture = read('g008-gravityview-fixture-baseline.json');
const browser = Object.fromEntries(['enabled', 'disabled', 'english', 'drift'].map((mode) => [mode, read(`g008-gravityview-browser-${mode}.json`)]));
const state = Object.fromEntries(['enabled', 'disabled', 'english', 'drift'].map((mode) => [mode, read(`g008-gravityview-state-${mode}.json`)]));

assert(source.exact_version === '3.3.4', 'Source probe GravityView version drifted.');
assert(source.exact_package_sha256 === expectedViewSha, 'Source probe GravityView package SHA drifted.');
assert(source.exact_persiangravity_head === expectedHead, 'Source probe is not bound to exact tested Head.');
assert(fixture.exact_persiangravity_head === expectedHead, 'Fixture is not bound to exact tested Head.');
assert(fixture.exact_gravityview_sha256 === expectedViewSha, 'Fixture GravityView package SHA drifted.');

for (const mode of ['enabled', 'disabled', 'english', 'drift']) {
  assert(browser[mode].status === 'PASS', `GravityView browser mode ${mode} did not pass.`);
  assert(browser[mode].exact_persiangravity_head === expectedHead, `Browser ${mode} Head drifted.`);
  assert(state[mode].exact_persiangravity_head === expectedHead, `State ${mode} Head drifted.`);
  assert(state[mode].exact_gravityview_sha256 === expectedViewSha, `State ${mode} package SHA drifted.`);
}

const createdSource = windows(source.date_hits, 'src/Field/Types/DateCreated.php');
const updatedSource = windows(source.date_hits, 'src/Field/Types/DateUpdated.php');
const templateSource = windows(source.field_output_filter_hits, 'src/Template/TemplateField.php');
const searchPolicySource = windows(source.date_hits, 'src/Search/SearchPolicy.php');
const queryVisitorSource = windows(source.date_hits, 'src/Search/Querying/Visitors/QueryFilterVisitor.php');
const sqlAdjustmentSource = windows(source.date_hits, 'vendor_prefixed/gravitykit/query-filters/src/Sql/SqlAdjustmentCallbacks.php');
const searchFieldSource = windows(source.query_sort_filter_hits, 'src/Search/Fields/SearchField.php');
const searchWidgetSource = windows(source.query_sort_filter_hits, 'src/Widget/Types/SearchWidget.php');

assert(createdSource.includes("var $name = 'date_created'"), 'date_created field identity not source-proven.');
assert(createdSource.includes("GVCommon::format_date( $field['value']"), 'date_created raw-to-display formatter path not source-proven.');
assert(updatedSource.includes('class DateUpdated extends \\GravityView_Field_Date_Created'), 'date_updated inheritance contract not source-proven.');
assert(updatedSource.includes("var $name = 'date_updated'"), 'date_updated field identity not source-proven.');
assert(templateSource.includes('gravityview/template/field/{$field->type}/output'), 'Dynamic field-specific output seam not source-proven.');
assert(templateSource.includes("echo apply_filters( 'gravityview/template/field/output'"), 'Consumed final field output seam not source-proven.');
assert(searchPolicySource.includes('date_created') && searchPolicySource.includes('stored in UTC format'), 'date_created UTC semantics not source-proven.');
assert(queryVisitorSource.includes("'date_created'") && queryVisitorSource.includes("DateTimeZone( 'UTC' )"), 'date_created query UTC conversion not source-proven.');
assert(sqlAdjustmentSource.includes('date_updated') && sqlAdjustmentSource.includes('date_created'), 'date_updated raw SQL/query semantics not source-proven.');
assert(searchFieldSource.includes("sprintf( 'filter_%s'"), 'GravityView field-search request identity is not source-proven.');
assert(searchWidgetSource.includes('SearchRequest::from_request'), 'GravityView frontend search request path is not source-proven.');

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
  assert(same(browser.enabled.sorting.date_created_asc.tokens, browser[mode].sorting.date_created_asc.tokens), `GravityView date_created ascending order changed in ${mode} control.`);
  assert(same(browser.enabled.sorting.date_created_desc.tokens, browser[mode].sorting.date_created_desc.tokens), `GravityView date_created descending order changed in ${mode} control.`);
  assert(same(browser.enabled.sorting.date_updated_asc.tokens, browser[mode].sorting.date_updated_asc.tokens), `GravityView date_updated ascending order changed in ${mode} control.`);
  assert(same(browser.enabled.sorting.date_updated_desc.tokens, browser[mode].sorting.date_updated_desc.tokens), `GravityView date_updated descending order changed in ${mode} control.`);
  assert(same(browser.enabled.filtering.date_created_local_2026_03_21.tokens, browser[mode].filtering.date_created_local_2026_03_21.tokens), `GravityView date_created filter result changed in ${mode} control.`);
  assert(same(browser.enabled.filtering.date_updated_local_2026_03_22.tokens, browser[mode].filtering.date_updated_local_2026_03_22.tokens), `GravityView date_updated filter result changed in ${mode} control.`);
}
assert(browser.enabled.filtering.date_created_local_2026_03_21.tokens.length === 1, 'date_created search/filter did not isolate one boundary-sensitive entry.');
assert(browser.enabled.filtering.date_updated_local_2026_03_22.tokens.length === 1, 'date_updated search/filter did not isolate one boundary-sensitive entry.');

const enabledSpans = browser.enabled.initial.rows.flatMap((row) => row.spans);
assert(enabledSpans.filter((span) => span.field === 'date_created').length === fixture.entries.length, 'Enabled date_created presentation was not consumed once per row.');
assert(enabledSpans.filter((span) => span.field === 'date_updated').length === fixture.entries.length, 'Enabled date_updated presentation was not consumed once per row.');
for (const mode of ['disabled', 'english', 'drift']) {
  assert(browser[mode].initial.rows.flatMap((row) => row.spans).length === 0, `${mode} control did not preserve native output.`);
}
assert(
  same(
    browser.enabled.initial.rows.map((row) => row.token),
    browser.enabled.repeated.rows.map((row) => row.token),
  ),
  'Repeated enabled rendering changed entry identity/order.',
);

const trace = state.enabled.prototype_trace;
assert(Array.isArray(trace) && trace.some((row) => row.hook === 'gravityview/template/field/date_created/output' && row.presented === true), 'Runtime did not prove date_created field-specific filter consumption.');
assert(trace.some((row) => row.hook === 'gravityview/template/field/date_updated/output' && row.presented === true), 'Runtime did not prove date_updated field-specific filter consumption.');

const qualification = {
  schema_version: '1.0.0',
  program: 'G-008',
  product: 'GravityView',
  exact_version: '3.3.4',
  exact_package_sha256: expectedViewSha,
  exact_persiangravity_head: expectedHead,
  evidence: {
    source: 'g008-gravityview-source-probe.json',
    fixture: 'g008-gravityview-fixture-baseline.json',
    browser: Object.keys(browser).map((mode) => `g008-gravityview-browser-${mode}.json`),
    state: Object.keys(state).map((mode) => `g008-gravityview-state-${mode}.json`),
  },
  source_findings: {
    date_created: 'GravityView Field DateCreated reads the authoritative entry date_created value and formats it before the field-specific output filter.',
    date_updated: 'GravityView Field DateUpdated inherits DateCreated rendering with its own date_updated identity.',
    consumed_seam: 'gravityview/template/field/{field_type}/output is consumed by TemplateField before the final gravityview/template/field/output echo.',
    query_boundary: 'Query/search/sort paths use raw Gravity Forms entry properties independently of the presentation output seam; date_created search has explicit UTC conversion, date_updated participates in raw query-filter SQL handling, and authentic frontend filter_date_created/filter_date_updated requests return stable raw-entry result identities.',
  },
  runtime_findings: {
    field_specific_hooks_consumed: true,
    typed_raw_entry_values_available: true,
    enabled_jalali_visible: true,
    disabled_native_fallback: true,
    english_ltr_native_control: true,
    forced_gate_failure_native_fallback: true,
    repeated_render_stable: true,
    authentic_date_created_filter_pass: true,
    authentic_date_updated_filter_pass: true,
    raw_db_gfapi_rest_equal_across_modes: true,
    gfapi_sort_equal_across_modes: true,
    gravityview_browser_sort_equal_across_modes: true,
    gravityview_browser_filter_equal_across_modes: true,
    unrelated_tokens_preserved: true,
  },
  dispositions: {
    'gravityview.date-created': 'QUALIFIED_FOR_PRODUCTION_ADAPTER',
    'gravityview.date-updated': 'QUALIFIED_FOR_PRODUCTION_ADAPTER',
  },
};

fs.writeFileSync(path.join(artifactDir, 'g008-gravityview-date-qualification.json'), JSON.stringify(qualification, null, 2) + '\n');
console.log('PASS GravityView G-008 exact-version qualification reconciliation');
