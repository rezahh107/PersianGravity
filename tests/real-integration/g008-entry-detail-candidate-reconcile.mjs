import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');
const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));

const fixture = read('g008-flow-residual-adversarial-fixture.json');
const source = read('g008-residual-source-probe.json');
const enabledState = read('g008-residual-adversarial-state-enabled.json');
const disabledState = read('g008-residual-adversarial-state-disabled.json');
const enabled = read('g008-entry-detail-candidate-browser-enabled.json');
const disabled = read('g008-entry-detail-candidate-browser-disabled.json');

const failures = [];
const flowHash = process.env.WU008_FLOW_SHA256;
const pgrHead = process.env.WU008_PGR_SHA;
const pgrPackageHash = process.env.WU008_PGR_PACKAGE_SHA256;

function requireIdentity(evidence, label) {
  if (evidence.exact_persiangravity_commit !== pgrHead) failures.push(`${label}: PersianGravity Head mismatch`);
  if (evidence.exact_persiangravity_package_sha256 !== pgrPackageHash && Object.hasOwn(evidence, 'exact_persiangravity_package_sha256')) failures.push(`${label}: PersianGravity package mismatch`);
  if (evidence.exact_gravityflow_version !== '3.1.0') failures.push(`${label}: Gravity Flow version mismatch`);
  if (evidence.exact_gravityflow_package_sha256 !== flowHash) failures.push(`${label}: Gravity Flow package mismatch`);
  if (evidence.site_timezone !== 'Asia/Tehran' || evidence.php_default_timezone !== 'UTC') failures.push(`${label}: timezone identity mismatch`);
}
requireIdentity(enabled, 'enabled browser');
requireIdentity(disabled, 'disabled browser');
requireIdentity(enabledState, 'enabled state');
requireIdentity(disabledState, 'disabled state');

if (enabled.mode !== 'enabled' || disabled.mode !== 'disabled') failures.push('browser mode identity mismatch');
if (enabledState.mode !== 'enabled' || disabledState.mode !== 'disabled') failures.push('state mode identity mismatch');

const contract = source?.source_contract?.entry_detail_schedule_due_expiration;
const requiredContractFlags = [
  'shared_format_hook_scopes_submitted_last_updated_due_expiration',
  'flow_format_date_delegates_to_gravityforms',
  'gravityforms_format_date_reaches_date_i18n',
  'wordpress_date_i18n_exposes_supported_filter',
  'wordpress_date_i18n_treats_numeric_input_as_local_timestamp_with_offset',
];
for (const key of requiredContractFlags) {
  if (contract?.[key] !== true) failures.push(`exact source contract missing ${key}`);
}

const fields = ['submitted', 'last_updated', 'due', 'expiration'];
const enabledObservations = enabled.exact?.candidateEvidence?.observations ?? [];
if (enabledObservations.length !== 4) failures.push('enabled exact marker observation count is not four');
if (enabled.exact?.candidateEvidence?.marker_date_i18n_calls !== 4) failures.push('enabled exact marker call count is not four');
if (disabled.exact?.candidateEvidence?.marker_date_i18n_calls !== 0) failures.push('disabled mode reached marker path');
if (enabled.exact?.candidateEvidence?.nested_due_getter_calls !== 0 || enabled.exact?.candidateEvidence?.nested_expiration_getter_calls !== 0) {
  failures.push('presentation marker path re-entered an operational due/expiration getter');
}

for (let index = 0; index < fields.length; index += 1) {
  const key = fields[index];
  const observation = enabledObservations[index];
  if (!observation || typeof observation.jalali !== 'string' || !observation.jalali) {
    failures.push(`enabled ${key}: Jalali observation missing`);
    continue;
  }
  const enabledVisible = enabled.exact.fields?.[key];
  const disabledVisible = disabled.exact.fields?.[key];
  if (typeof enabledVisible !== 'string' || !enabledVisible.includes(observation.jalali)) {
    failures.push(`enabled ${key}: visible output did not consume Jalali date`);
  }
  const reconstructedNative = enabledVisible.replace(observation.jalali, observation.native_fallback);
  if (reconstructedNative !== disabledVisible) {
    failures.push(`enabled ${key}: native time/host formatting was not preserved around date replacement`);
  }

  const utcCivil = new Date(Number(observation.timestamp_with_offset) * 1000).toISOString().slice(0, 19).replace('T', ' ');
  if (utcCivil !== observation.local_civil) {
    failures.push(`enabled ${key}: timestamp-with-offset was not interpreted as local civil via UTC components`);
  }
}

const boundaryCivils = enabledObservations.map((item) => item.local_civil);
if (
  boundaryCivils[0] !== '2026-03-20 23:59:00'
  || boundaryCivils[1] !== '2026-03-21 00:01:00'
  || boundaryCivils[2] !== '2026-03-21 00:03:00'
  || boundaryCivils[3] !== '2026-03-21 23:59:00'
) {
  failures.push(`Tehran local-midnight boundary fixture drifted: ${JSON.stringify(boundaryCivils)}`);
}

const expectedNative = fixture.candidate_entries.find((item) => Number(item.id) === Number(enabledState.candidate_entry.gfapi.id));
if (!expectedNative) failures.push('candidate fixture entry is missing');

for (const scenarioName of ['failure', 'drift', 'range']) {
  const scenario = enabled[scenarioName];
  if (!scenario || scenario.marker_leaked !== false) failures.push(`${scenarioName}: scenario evidence missing or marker leaked`);
}
if (enabled.failure?.candidateEvidence?.observations?.some((item) => item.jalali !== null)) failures.push('forced conversion failure produced Jalali output');
if (enabled.drift?.candidateEvidence?.marker_date_i18n_calls !== 0) failures.push('version drift did not fail closed before date_i18n marker');
if (enabled.range?.candidateEvidence?.observations?.some((item) => item.jalali !== null)) failures.push('out-of-range scenario produced Jalali output');
if (JSON.stringify(enabled.repeated?.fields) !== JSON.stringify(enabled.exact?.fields)) failures.push('repeated rendering was not deterministic');

if (enabled.exact?.candidateEvidence?.unrelated_wp_date_i18n !== disabled.exact?.candidateEvidence?.unrelated_wp_date_i18n) {
  failures.push('unrelated WordPress date_i18n output changed');
}
if (enabled.exact?.candidateEvidence?.unrelated_gf_format_date !== disabled.exact?.candidateEvidence?.unrelated_gf_format_date) {
  failures.push('unrelated Gravity Forms formatting output changed');
}

function canonicalCandidate(state) {
  return {
    gfapi: state.candidate_entry.gfapi,
    database: state.candidate_entry.database,
    rest: state.candidate_entry.rest,
    workflow_step: state.candidate_entry.workflow_step,
    workflow_final_status: state.candidate_entry.workflow_final_status,
    due_timestamp: state.candidate_entry.due_timestamp,
    expiration_timestamp: state.candidate_entry.expiration_timestamp,
    overdue: state.candidate_entry.overdue,
    expired: state.candidate_entry.expired,
    query: state.query,
  };
}
if (JSON.stringify(canonicalCandidate(enabledState)) !== JSON.stringify(canonicalCandidate(disabledState))) {
  failures.push('Entry Detail module state changed raw/query/workflow/deadline/expiration state');
}
if (
  enabledState.candidate_entry.gfapi.date_created !== enabledState.candidate_entry.database.date_created
  || enabledState.candidate_entry.gfapi.date_created !== enabledState.candidate_entry.rest.date_created
) {
  failures.push('candidate DB/GFAPI/REST date_created mismatch');
}
if (enabledState.candidate_entry.gfapi.workflow_timestamp !== enabledState.candidate_entry.rest.workflow_timestamp) {
  failures.push('candidate GFAPI/meta/REST workflow_timestamp mismatch');
}
if (enabledState.candidate_entry.due_timestamp === enabledState.candidate_entry.expiration_timestamp) {
  failures.push('due and expiration timestamps are not distinct');
}
if (!enabledState.csv.marker_absent || !disabledState.csv.marker_absent || enabledState.csv.sha256 !== disabledState.csv.sha256) {
  failures.push('CSV/export isolation changed or marker leaked');
}

const enabledGetter = enabled.exact?.candidateEvidence ?? {};
const disabledGetter = disabled.exact?.candidateEvidence ?? {};
if (enabledGetter.due_getter_calls !== disabledGetter.due_getter_calls || enabledGetter.expiration_getter_calls !== disabledGetter.expiration_getter_calls) {
  failures.push('operational due/expiration getter invocation counts changed with presentation enabled');
}
if (disabledGetter.nested_due_getter_calls !== 0 || disabledGetter.nested_expiration_getter_calls !== 0) {
  failures.push('disabled mode unexpectedly nested operational getters in date_i18n');
}

for (const state of [enabledState, disabledState]) {
  if (!state.timeline?.storage_equal_after_experiments || !state.timeline?.ids_order_bodies_preserved) failures.push(`${state.mode}: Timeline experiment mutated storage/order/body`);
  if (!state.timeline?.display_property_ignored) failures.push(`${state.mode}: separate Timeline display property unexpectedly affected renderer`);
  if (!state.timeline?.date_created_is_consumed) failures.push(`${state.mode}: Timeline renderer did not prove date_created is consumed`);
}
if (JSON.stringify(enabledState.timeline.stored_after) !== JSON.stringify(disabledState.timeline.stored_after)) failures.push('Timeline stored notes changed with module state');
if (JSON.stringify(enabled.timeline.headers) !== JSON.stringify(disabled.timeline.headers)) failures.push('Timeline note headers changed with Entry Detail date-family prototype');
if (JSON.stringify(enabled.timeline.bodies) !== JSON.stringify(disabled.timeline.bodies)) failures.push('Timeline note bodies changed with module state');
if (JSON.stringify(enabled.print.headers) !== JSON.stringify(disabled.print.headers)) failures.push('Print Timeline headers changed with module state');
if (JSON.stringify(enabled.print.bodies) !== JSON.stringify(disabled.print.bodies)) failures.push('Print Timeline bodies changed with module state');
for (const [key, count] of Object.entries(enabled.print.workflow_sidebar_presence ?? {})) {
  if (count !== 0 || disabled.print.workflow_sidebar_presence?.[key] !== 0) failures.push(`Print unexpectedly contains workflow-sidebar ${key}`);
}

const result = {
  schema_version: '1.0.0',
  evidence_class: 'G008_ENTRY_DETAIL_TWO_HOOK_PROTOTYPE_RECONCILIATION',
  exact_persiangravity_commit: pgrHead,
  exact_persiangravity_package_sha256: pgrPackageHash,
  exact_gravityflow_version: '3.1.0',
  exact_gravityflow_package_sha256: flowHash,
  affected_workflow_info_family: [
    'gravityflow.entry-detail.submitted',
    'gravityflow.entry-detail.last-updated',
    'gravityflow.entry-detail.due-date',
    'gravityflow.entry-detail.expiration',
  ],
  timestamp_semantics: 'date_i18n timestamp-with-offset recovered as local Gregorian civil components via gmdate; no second timezone conversion',
  native_time_preserved: !failures.some((failure) => failure.includes('native time/host formatting')),
  unrelated_date_formatting_unchanged: !failures.some((failure) => failure.includes('unrelated')),
  operational_state_equal: JSON.stringify(canonicalCandidate(enabledState)) === JSON.stringify(canonicalCandidate(disabledState)),
  timeline_storage_equal: JSON.stringify(enabledState.timeline.stored_after) === JSON.stringify(disabledState.timeline.stored_after),
  print_workflow_sidebar_absent: Object.values(enabled.print.workflow_sidebar_presence ?? {}).every((value) => value === 0),
  prototype_result: failures.length ? 'NOT_PROVEN' : 'QUALIFIED_FOR_PRODUCTION_ADAPTER',
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-entry-detail-two-hook-prototype.json'), `${JSON.stringify(result, null, 2)}\n`);
if (failures.length) throw new Error(`Entry Detail two-hook prototype failed: ${failures.join('; ')}`);
console.log('G008_ENTRY_DETAIL_TWO_HOOK_PROTOTYPE QUALIFIED');
