import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');
const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));

const source = read('g008-residual-source-probe.json');
const fixture = read('g008-timeline-production-fixture.json');
const enabledState = read('g008-residual-adversarial-state-enabled.json');
const disabledState = read('g008-residual-adversarial-state-disabled.json');
const enabledBrowser = read('g008-entry-detail-candidate-browser-enabled.json');
const disabledBrowser = read('g008-entry-detail-candidate-browser-disabled.json');
const enabledHook = read('g008-timeline-production-hook-enabled.json');
const disabledHook = read('g008-timeline-production-hook-disabled.json');

const failures = [];
const head = process.env.WU008_PGR_SHA;
const packageSha = process.env.WU008_PGR_PACKAGE_SHA256;
const flowSha = process.env.WU008_FLOW_SHA256;
const gfSha = process.env.WU008_GF_SHA256;
const expectedFingerprints = {
  flow_entry_detail: 'a7634c5604184502457bcb22cdf1ade892e84c888cc60996aea8a810ced7680a',
  flow_common: 'a8844f4b6ac37eed1a2cc1e904418f6ed8c6b4480e982c5a809e895a6f9e0cc8',
  flow_print: 'df969bf8a37ed4f5619e0e8b2a753dd1133fa0c7551b158d740248c67fcb95c6',
  gf_common: 'ac4ed495ee02a119a4fd08c77279f0db0905e6f20f59c472d5e6bffc801ca355',
};

const timelineContract = source?.source_contract?.timeline_history;
const printContract = source?.source_contract?.print;
for (const flag of [
  'header_formats_note_date_directly',
  'note_body_is_separate_escaped_content',
  'timeline_reads_gravityforms_notes',
  'timeline_inserts_initial_entry_event',
  'initial_event_uses_entry_date_created',
  'timeline_order_is_host_owned',
  'timeline_full_array_filter_runs_after_host_reverse',
  'only_timeline_data_filter_mutates_note_array',
  'common_text_timeline_reuses_note_dates',
  'gravityforms_notes_are_persisted_in_utc',
  'gravityforms_notes_return_raw_date_created',
]) {
  if (timelineContract?.[flag] !== true) failures.push(`historical Timeline source contract missing ${flag}`);
}
for (const flag of [
  'reuses_entry_detail_grid',
  'optional_timeline_reuses_entry_detail_timeline',
  'no_print_specific_date_formatter',
  'print_style_hook_is_not_date_seam',
  'workflow_sidebar_not_rendered_by_print',
]) {
  if (printContract?.[flag] !== true) failures.push(`Print source contract missing ${flag}`);
}

function stateIdentity(state, mode) {
  if (state.evidence_class !== 'AUTHENTIC_G008_RESIDUAL_OPERATIONAL_AND_TIMELINE_STATE') failures.push(`${mode} state evidence class mismatch`);
  if (state.mode !== mode) failures.push(`${mode} state mode mismatch`);
  if (state.exact_persiangravity_commit !== head) failures.push(`${mode} state Head mismatch`);
  if (state.exact_gravityflow_version !== '3.1.0' || state.exact_gravityflow_package_sha256 !== flowSha) failures.push(`${mode} state Flow identity mismatch`);
  if (state.site_timezone !== 'Asia/Tehran' || state.php_default_timezone !== 'UTC') failures.push(`${mode} state timezone mismatch`);
}
stateIdentity(enabledState, 'enabled');
stateIdentity(disabledState, 'disabled');

function browserIdentity(browser, mode) {
  if (browser.evidence_class !== 'AUTHENTIC_G008_ENTRY_DETAIL_AND_TIMELINE_PRODUCTION_BROWSER') failures.push(`${mode} browser evidence class mismatch`);
  if (browser.mode !== mode) failures.push(`${mode} browser mode mismatch`);
  if (browser.exact_persiangravity_commit !== head || browser.exact_persiangravity_package_sha256 !== packageSha) failures.push(`${mode} browser PGR identity mismatch`);
  if (browser.exact_gravityflow_version !== '3.1.0' || browser.exact_gravityflow_package_sha256 !== flowSha) failures.push(`${mode} browser Flow identity mismatch`);
  if (browser.exact_gravityforms_version !== '3.1.1.1' || browser.exact_gravityforms_package_sha256 !== gfSha) failures.push(`${mode} browser GF identity mismatch`);
  if (browser.site_timezone !== 'Asia/Tehran' || browser.php_default_timezone !== 'UTC') failures.push(`${mode} browser timezone mismatch`);
}
browserIdentity(enabledBrowser, 'enabled');
browserIdentity(disabledBrowser, 'disabled');

if (
  enabledHook.class_loaded !== true || enabledHook.module_enabled !== true
  || enabledHook.option_date_format_hooks !== 1 || enabledHook.date_i18n_hooks !== 1
) {
  failures.push(`enabled production hook lifecycle mismatch: ${JSON.stringify(enabledHook)}`);
}
if (
  disabledHook.class_loaded !== false || disabledHook.module_enabled !== false
  || disabledHook.option_date_format_hooks !== 0 || disabledHook.date_i18n_hooks !== 0
) {
  failures.push(`disabled production hooks/class were present: ${JSON.stringify(disabledHook)}`);
}
for (const [label, hook] of [['enabled', enabledHook], ['disabled', disabledHook]]) {
  if (hook.flow_version !== '3.1.0' || hook.gf_version !== '3.1.1.1') failures.push(`${label} hook probe host version mismatch`);
  if (JSON.stringify(hook.source_fingerprints) !== JSON.stringify(expectedFingerprints)) failures.push(`${label} source fingerprint mismatch`);
}

if (fixture.evidence_class !== 'AUTHENTIC_G008_TIMELINE_PRODUCTION_BOUNDARY_FIXTURE') failures.push('production Timeline fixture evidence class mismatch');
if (Number(fixture.event_count) < 5 || Number(fixture.stored_event_count) < 4) failures.push('production fixture does not include initial plus at least four stored events');
if (!Array.isArray(fixture.duplicate_ids) || fixture.duplicate_ids.length < 2 || new Set(fixture.duplicate_ids.map(Number)).size !== fixture.duplicate_ids.length) {
  failures.push('duplicate timestamp fixture does not preserve distinct note IDs');
}
const expectedTimeline = fixture.timeline ?? [];
const expectedStored = expectedTimeline.filter((row) => row.event_kind === 'stored');
const expectedBodies = expectedTimeline.map((row) => row.value);
if (!expectedTimeline.some((row) => row.event_kind === 'initial' && Number(row.id) === 0)) failures.push('initial synthetic Timeline event is missing');
if (expectedStored.length < 4) failures.push('stored Timeline event fixture is incomplete');
if (!expectedStored.some((row) => /2026-03-20|1405\/01\/01|2030-03-21/.test(row.value))) failures.push('date-looking user text boundary fixture is missing');

function canonicalStored(rows) {
  return (rows ?? []).map((row) => ({
    id: Number(row.id),
    date_created: row.date_created,
    value: row.value,
    note_type: row.note_type,
  }));
}
const expectedStoredCanonical = canonicalStored(expectedStored);
for (const state of [enabledState, disabledState]) {
  if (JSON.stringify(state.timeline?.stored_before) !== JSON.stringify(state.timeline?.stored_after)) failures.push(`${state.mode}: Timeline qualification changed storage`);
  if (!state.timeline?.storage_equal_after_experiments) failures.push(`${state.mode}: storage equality flag is false`);
  if (!state.timeline?.ids_order_bodies_preserved) failures.push(`${state.mode}: note IDs/order/bodies were not preserved`);
  if (state.timeline?.display_property_ignored !== true) failures.push(`${state.mode}: historical separate display property finding drifted`);
  if (state.timeline?.date_created_is_consumed !== true) failures.push(`${state.mode}: historical date_created consumption finding drifted`);
  const stored = canonicalStored(state.timeline?.stored_after);
  if (JSON.stringify(stored) !== JSON.stringify(expectedStoredCanonical)) failures.push(`${state.mode}: authentic stored notes do not match production fixture`);
}
if (JSON.stringify(enabledState.candidate_entry) !== JSON.stringify(disabledState.candidate_entry)) failures.push('candidate raw/GFAPI/REST/workflow state differs between module modes');
if (JSON.stringify(enabledState.query) !== JSON.stringify(disabledState.query)) failures.push('query/sort result differs between module modes');
if (JSON.stringify(enabledState.timeline?.stored_after) !== JSON.stringify(disabledState.timeline?.stored_after)) failures.push('stored Timeline rows differ between module modes');
if (JSON.stringify(enabledState.timeline?.canonical) !== JSON.stringify(disabledState.timeline?.canonical)) failures.push('canonical Timeline IDs/order/raw/body differ between module modes');
if (enabledState.csv?.sha256 !== disabledState.csv?.sha256 || enabledState.csv?.marker_absent !== true || disabledState.csv?.marker_absent !== true) failures.push('text/export path changed or marker leaked');

const expectedNativeHeaders = expectedTimeline.map((row) => row.expected_header);
const enabledHeaders = enabledBrowser.timeline?.headers ?? [];
const disabledHeaders = disabledBrowser.timeline?.headers ?? [];
if (JSON.stringify(disabledHeaders) !== JSON.stringify(expectedNativeHeaders)) failures.push('disabled Timeline is not exact native output');
if (enabledHeaders.length !== expectedTimeline.length) failures.push('enabled Timeline header count mismatch');
for (let index = 0; index < expectedTimeline.length; index += 1) {
  const row = expectedTimeline[index];
  const header = enabledHeaders[index] ?? '';
  if (!header.startsWith(row.expected_jalali_date)) failures.push(`enabled Timeline row ${row.id} Jalali date mismatch`);
  if (row.expected_native_time_tail && !header.includes(row.expected_native_time_tail)) failures.push(`enabled Timeline row ${row.id} changed native time output`);
}
if (JSON.stringify(enabledHeaders) === JSON.stringify(disabledHeaders)) failures.push('enabled Timeline did not differ from native control');
if (enabledBrowser.timeline?.marker_leaked !== false || disabledBrowser.timeline?.marker_leaked !== false) failures.push('Timeline marker leakage flag is not false');
for (const browser of [enabledBrowser, disabledBrowser]) {
  const timeline = browser.timeline ?? {};
  if (timeline.collector?.row_count !== expectedTimeline.length || timeline.headers?.length !== expectedTimeline.length || timeline.bodies?.length !== expectedTimeline.length) {
    failures.push(`${browser.mode}: Timeline row/header/body count does not equal authoritative fixture count`);
  }
  if (JSON.stringify(timeline.bodies ?? []) !== JSON.stringify(expectedBodies)) {
    failures.push(`${browser.mode}: Timeline body vector does not exactly equal authoritative fixture values`);
  }
  for (let index = 0; index < expectedTimeline.length; index += 1) {
    if ((timeline.bodies?.[index] ?? '').includes(timeline.headers?.[index] ?? '__missing_header__')) {
      failures.push(`${browser.mode}: Timeline body ${index} captured an enclosing body/header wrapper`);
    }
  }
}
const expectedRepeatedTimeline = (browser) => ({
  headers: browser.timeline?.headers,
  bodies: browser.timeline?.bodies,
  rows: browser.timeline?.rows,
  collector: browser.timeline?.collector,
});
if (JSON.stringify(enabledBrowser.timeline?.repeated) !== JSON.stringify(expectedRepeatedTimeline(enabledBrowser))) {
  failures.push('enabled repeated Timeline render is not deterministic');
}
if (JSON.stringify(disabledBrowser.timeline?.repeated) !== JSON.stringify(expectedRepeatedTimeline(disabledBrowser))) {
  failures.push('disabled repeated Timeline render is not deterministic');
}

const enabledBodies = enabledBrowser.timeline?.bodies ?? [];
const disabledBodies = disabledBrowser.timeline?.bodies ?? [];
if (JSON.stringify(enabledBodies) !== JSON.stringify(disabledBodies)) failures.push('Timeline bodies/order changed with presentation mode');
for (const stored of expectedStored) {
  if (!enabledBodies.includes(stored.value) || !disabledBodies.includes(stored.value)) failures.push(`stored body ${stored.id} changed or disappeared`);
}
const duplicateExpectedBodies = expectedTimeline.filter((row) => row.date_created === fixture.duplicate_timestamp).map((row) => row.value);
if (duplicateExpectedBodies.length < 2 || duplicateExpectedBodies.some((body) => enabledBodies.filter((value) => value === body).length !== 1 || disabledBodies.filter((value) => value === body).length !== 1)) {
  failures.push('duplicate timestamp note identities are not represented by distinct one-to-one body rows');
}

for (const browser of [enabledBrowser, disabledBrowser]) {
  if (browser.print?.relation !== 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION') failures.push(`${browser.mode}: Print inheritance relation missing`);
  if (JSON.stringify(browser.print?.headers) !== JSON.stringify(browser.timeline?.headers)) failures.push(`${browser.mode}: Print headers do not exactly inherit Timeline`);
  if (JSON.stringify(browser.print?.bodies) !== JSON.stringify(browser.timeline?.bodies)) failures.push(`${browser.mode}: Print bodies/order do not match Timeline`);
  if (browser.print?.collector?.row_count !== expectedTimeline.length) failures.push(`${browser.mode}: Print row count does not equal authoritative Timeline fixture count`);
  if (browser.print?.marker_leaked !== false) failures.push(`${browser.mode}: Print marker leaked`);
  if (Object.values(browser.print?.workflow_sidebar_presence ?? {}).some((value) => value !== 0)) failures.push(`${browser.mode}: Print unexpectedly contains workflow-sidebar date fields`);
}

const passed = failures.length === 0;
const result = {
  schema_version: '2.0.0',
  evidence_class: 'G008_TIMELINE_PRINT_PRODUCTION_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: head,
  exact_persiangravity_package_sha256: packageSha,
  exact_gravityflow_version: '3.1.0',
  exact_gravityflow_package_sha256: flowSha,
  exact_gravityforms_version: '3.1.1.1',
  exact_gravityforms_package_sha256: gfSha,
  source_fingerprints: expectedFingerprints,
  timeline: {
    initial_entry_disposition: passed ? 'RUNTIME_PROVEN + ADMITTED_VERIFIED' : 'NOT_PROVEN',
    stored_note_event_disposition: passed ? 'RUNTIME_PROVEN + ADMITTED_VERIFIED' : 'NOT_PROVEN',
    supported_formats: ['F j, Y', 'Y-m-d'],
    unsupported_formats_native: true,
    storage_unchanged: passed,
    ids_order_bodies_unchanged: passed,
    duplicate_timestamp_identity_proven: passed,
    user_authored_date_looking_text_untouched: passed,
    separate_display_property_consumed: false,
    date_created_representation_consumed_by_renderer: true,
    adapter_hook_lifecycle_proven: passed,
    native_disabled_fallback_proven: passed,
    enabled_jalali_presentation_proven: passed,
    body_vector_mode_equality_proven: passed,
    fixture_row_mapping_proven: passed,
    marker_non_leakage_proven: passed,
    operational_state_unchanged: passed,
  },
  print: {
    field_grid_relation: 'REUSES_ENTRY_DETAIL_FIELD_GRID',
    timeline_relation: 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION',
    initial_event_propagation_disposition: passed ? 'RUNTIME_PROVEN + ADMITTED_VERIFIED_BY_TIMELINE_INHERITANCE' : 'NOT_PROVEN',
    stored_note_event_propagation_disposition: passed ? 'RUNTIME_PROVEN + ADMITTED_VERIFIED_BY_TIMELINE_INHERITANCE' : 'NOT_PROVEN',
    independent_date_seam_disposition: 'NO_INDEPENDENT_PRINT_DATE_SEAM_REQUIRED',
    workflow_sidebar_due_schedule_expiration: 'ABSENT_FROM_PRINT_RENDER_PATH',
    body_vector_inheritance_proven: passed,
    native_disabled_inheritance_proven: passed,
    marker_non_leakage_proven: passed,
  },
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-timeline-print-qualification.json'), `${JSON.stringify(result, null, 2)}\n`);
if (failures.length) throw new Error(`Timeline/Print production admission failed: ${failures.join('; ')}`);
console.log('G008_TIMELINE_PRINT_PRODUCTION_ADMISSION RUNTIME_PROVEN + ADMITTED_VERIFIED');
