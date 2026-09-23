import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');

const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));
const fixture = read('g008-flow-residual-adversarial-fixture.json');
const source = read('g008-residual-source-probe.json');
const enabledState = read('g008-residual-adversarial-state-enabled.json');
const disabledState = read('g008-residual-adversarial-state-disabled.json');
const enabledBrowser = read('g008-entry-detail-candidate-browser-enabled.json');
const disabledBrowser = read('g008-entry-detail-candidate-browser-disabled.json');

const failures = [];
const head = process.env.WU008_PGR_SHA;
const packageSha = process.env.WU008_PGR_PACKAGE_SHA256;
const flowSha = process.env.WU008_FLOW_SHA256;

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
  if (timelineContract?.[flag] !== true) failures.push(`timeline source contract missing ${flag}`);
}
for (const flag of [
  'reuses_entry_detail_grid',
  'optional_timeline_reuses_entry_detail_timeline',
  'no_print_specific_date_formatter',
  'print_style_hook_is_not_date_seam',
  'workflow_sidebar_not_rendered_by_print',
]) {
  if (printContract?.[flag] !== true) failures.push(`print source contract missing ${flag}`);
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
  if (browser.evidence_class !== 'AUTHENTIC_G008_ENTRY_DETAIL_TWO_HOOK_QUALIFICATION_BROWSER') failures.push(`${mode} browser evidence class mismatch`);
  if (browser.mode !== mode) failures.push(`${mode} browser mode mismatch`);
  if (browser.exact_persiangravity_commit !== head || browser.exact_persiangravity_package_sha256 !== packageSha) failures.push(`${mode} browser PGR identity mismatch`);
  if (browser.exact_gravityflow_version !== '3.1.0' || browser.exact_gravityflow_package_sha256 !== flowSha) failures.push(`${mode} browser Flow identity mismatch`);
}
browserIdentity(enabledBrowser, 'enabled');
browserIdentity(disabledBrowser, 'disabled');

const expectedStored = fixture.stored_notes ?? [];
if (expectedStored.length !== 3) failures.push('multi-note fixture does not contain exactly three genuine stored notes');
const storedIds = expectedStored.map((row) => Number(row.id));
if (new Set(storedIds).size !== storedIds.length || storedIds.some((id) => id <= 0)) failures.push('stored note IDs are missing or duplicated');
if (new Set(expectedStored.map((row) => row.date_created)).size !== expectedStored.length) failures.push('stored note timestamps are not distinct');
if (new Set(expectedStored.map((row) => row.value)).size !== expectedStored.length) failures.push('stored note bodies are not distinct');

for (const state of [enabledState, disabledState]) {
  if (JSON.stringify(state.timeline?.stored_before) !== JSON.stringify(state.timeline?.stored_after)) {
    failures.push(`${state.mode}: Timeline experiment changed storage`);
  }
  if (!state.timeline?.storage_equal_after_experiments) failures.push(`${state.mode}: storage equality flag is false`);
  if (!state.timeline?.ids_order_bodies_preserved) failures.push(`${state.mode}: note IDs/order/bodies were not preserved`);
  if (state.timeline?.display_property_ignored !== true) failures.push(`${state.mode}: separate display property was not proven ignored`);
  if (state.timeline?.date_created_is_consumed !== true) failures.push(`${state.mode}: renderer consumption of date_created was not proven`);
}

if (JSON.stringify(enabledState.timeline?.stored_after) !== JSON.stringify(disabledState.timeline?.stored_after)) {
  failures.push('stored Timeline rows differ between module modes');
}
if (JSON.stringify(enabledState.timeline?.canonical) !== JSON.stringify(disabledState.timeline?.canonical)) {
  failures.push('canonical Timeline IDs/order/date/body differ between module modes');
}

const canonical = enabledState.timeline?.canonical ?? [];
const initial = canonical.find((item) => Number(item.id) === 0);
if (!initial) failures.push('initial Entry event is missing from canonical Timeline');
for (const stored of expectedStored) {
  const found = canonical.find((item) => Number(item.id) === Number(stored.id));
  if (!found) {
    failures.push(`stored note ${stored.id} missing from canonical Timeline`);
    continue;
  }
  if (found.date_created !== stored.date_created || found.value !== stored.value) {
    failures.push(`stored note ${stored.id} canonical timestamp/body drifted`);
  }
}

const expectedHeaders = (fixture.timeline_fixture ?? []).map((row) => row.expected_header);
if (expectedHeaders.length !== 4) failures.push('Timeline fixture does not contain initial + three stored headers');
for (const browser of [enabledBrowser, disabledBrowser]) {
  if (JSON.stringify(browser.timeline?.headers) !== JSON.stringify(expectedHeaders)) {
    failures.push(`${browser.mode}: Entry Detail Timeline headers are not exact native host headers`);
  }
  if (JSON.stringify(browser.print?.headers) !== JSON.stringify(expectedHeaders)) {
    failures.push(`${browser.mode}: Print Timeline headers do not match native Timeline headers`);
  }
  const bodies = browser.timeline?.bodies ?? [];
  const printBodies = browser.print?.bodies ?? [];
  for (const stored of expectedStored) {
    if (!bodies.includes(stored.value)) failures.push(`${browser.mode}: Entry Detail note body changed for ${stored.id}`);
    if (!printBodies.includes(stored.value)) failures.push(`${browser.mode}: Print note body changed for ${stored.id}`);
  }
  if (Object.values(browser.print?.workflow_sidebar_presence ?? {}).some((value) => value !== 0)) {
    failures.push(`${browser.mode}: Print unexpectedly contains workflow-sidebar date fields`);
  }
}

if (JSON.stringify(enabledBrowser.timeline?.headers) !== JSON.stringify(disabledBrowser.timeline?.headers)) failures.push('Timeline headers changed with module state');
if (JSON.stringify(enabledBrowser.timeline?.bodies) !== JSON.stringify(disabledBrowser.timeline?.bodies)) failures.push('Timeline bodies changed with module state');
if (JSON.stringify(enabledBrowser.print?.headers) !== JSON.stringify(disabledBrowser.print?.headers)) failures.push('Print Timeline headers changed with module state');
if (JSON.stringify(enabledBrowser.print?.bodies) !== JSON.stringify(disabledBrowser.print?.bodies)) failures.push('Print Timeline bodies changed with module state');

const result = {
  schema_version: '1.0.0',
  evidence_class: 'G008_TIMELINE_PRINT_QUALIFICATION_RECONCILIATION',
  exact_persiangravity_commit: head,
  exact_persiangravity_package_sha256: packageSha,
  exact_gravityflow_version: '3.1.0',
  exact_gravityflow_package_sha256: flowSha,
  timeline: {
    stored_note_count: expectedStored.length,
    initial_entry_present: Boolean(initial),
    storage_unchanged: failures.every((failure) => !failure.includes('storage')),
    ids_order_bodies_unchanged: failures.every((failure) => !failure.includes('IDs/order') && !failure.includes('body changed')),
    user_authored_date_looking_text_untouched: expectedStored.some((row) => /2026-03-20|1405\/01\/01|2030-03-21/.test(row.value))
      && failures.every((failure) => !failure.includes('body changed')),
    separate_display_property_consumed: false,
    date_created_representation_consumed_by_renderer: true,
    initial_entry_disposition: failures.length ? 'NOT_PROVEN' : 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
    stored_note_event_disposition: failures.length ? 'NOT_PROVEN' : 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
  },
  print: {
    field_grid_relation: 'REUSES_ENTRY_DETAIL_FIELD_GRID',
    timeline_relation: 'REUSES_ENTRY_DETAIL_TIMELINE',
    initial_event_propagation_disposition: failures.length ? 'NOT_PROVEN' : 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
    stored_note_event_propagation_disposition: failures.length ? 'NOT_PROVEN' : 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
    independent_date_seam_disposition: failures.length ? 'NOT_PROVEN' : 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
    workflow_sidebar_due_schedule_expiration: 'ABSENT_FROM_PRINT_RENDER_PATH',
  },
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-timeline-print-qualification.json'), `${JSON.stringify(result, null, 2)}\n`);
if (failures.length) throw new Error(`Timeline/Print qualification failed: ${failures.join('; ')}`);
console.log('G008_TIMELINE_PRINT_QUALIFICATION FINAL_NO_ADMISSION_FOR_EXACT_3_1_0');
