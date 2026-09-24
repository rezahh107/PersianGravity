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
const english = read('g008-entry-detail-candidate-browser-english.json');

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
requireIdentity(english, 'English browser');
requireIdentity(enabledState, 'enabled state');
requireIdentity(disabledState, 'disabled state');

if (enabled.mode !== 'enabled' || disabled.mode !== 'disabled' || english.mode !== 'english') failures.push('browser mode identity mismatch');
if (enabledState.mode !== 'enabled' || disabledState.mode !== 'disabled') failures.push('state mode identity mismatch');

const contract = source?.source_contract?.entry_detail_schedule_due_expiration;
const requiredContractFlags = [
  'exact_status_box_wrapper',
  'workflow_info_precedes_step_status_in_exact_wrapper',
  'workflow_info_uses_bounded_human_field_nodes',
  'entry_id_href_keeps_ascii_numeric_authority',
  'below_workflow_info_hook_is_supported_post_value_seam',
  'queued_step_reuses_bounded_human_field_nodes',
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
const enabledProduction = enabled.production;
const disabledProduction = disabled.production;
const englishProduction = english.production;
if (!enabledProduction || !disabledProduction || !englishProduction) failures.push('production browser observations are missing');
if (enabledProduction?.marker_leaked !== false || disabledProduction?.marker_leaked !== false) failures.push('production marker leaked into visible output');
if (enabledProduction?.candidateEvidence?.marker_date_i18n_calls !== 0) failures.push('production request used isolated prototype marker');
if (enabledProduction?.candidateEvidence?.production_marker_date_i18n_calls !== 4) failures.push('production adapter did not consume exactly four workflow-info dates');
if (disabledProduction?.candidateEvidence?.production_marker_date_i18n_calls !== 0) failures.push('disabled production request reached production marker path');
if (JSON.stringify(enabledProduction?.fields) !== JSON.stringify(enabled.exact?.fields)) {
  failures.push('production adapter output differs from qualified prototype output');
}
if (JSON.stringify(disabledProduction?.fields) !== JSON.stringify(disabled.exact?.fields)) {
  failures.push('disabled production output differs from exact native output');
}
if (JSON.stringify(enabledProduction?.machine) !== JSON.stringify(disabledProduction?.machine)) {
  failures.push('presentation digit shaping changed DOM attributes, links, form controls or machine values');
}
if (enabledProduction?.digit_script_count !== 1 || disabledProduction?.digit_script_count !== 0 || englishProduction?.digit_script_count !== 0) {
  failures.push('digit presentation adapter activation did not stay bounded to enabled Persian UI');
}
if (enabledProduction?.visible_status_fields?.some((value) => /[0-9]/.test(value))) {
  failures.push('enabled Persian workflow-info still contains ASCII digits');
}
if (
  typeof enabledProduction?.fields?.submitted !== 'string'
  || enabledProduction.fields.submitted.includes('11:59')
  || !enabledProduction.fields.submitted.includes('۱۱:۵۹')
) {
  failures.push('Submitted mixed-digit regression remains visible');
}
if (
  typeof enabledProduction?.fields?.last_updated !== 'string'
  || enabledProduction.fields.last_updated.includes('12:01')
  || !enabledProduction.fields.last_updated.includes('۱۲:۰۱')
) {
  failures.push('Last Updated mixed-digit regression remains visible');
}
if (
  typeof englishProduction?.fields?.submitted !== 'string'
  || !englishProduction.fields.submitted.includes('11:59')
  || englishProduction.fields.submitted.includes('۱۱:۵۹')
) {
  failures.push('English/non-Persian Submitted time did not remain ASCII');
}
if (
  typeof englishProduction?.fields?.entry_id !== 'string'
  || !/[0-9]/.test(englishProduction.fields.entry_id)
  || /[۰-۹]/.test(englishProduction.fields.entry_id)
) {
  failures.push('English/non-Persian visible Entry ID did not remain ASCII');
}
if (
  enabledProduction?.candidateEvidence?.due_getter_calls !== disabledProduction?.candidateEvidence?.due_getter_calls
  || enabledProduction?.candidateEvidence?.expiration_getter_calls !== disabledProduction?.candidateEvidence?.expiration_getter_calls
) {
  failures.push('production adapter changed operational due/expiration getter invocation counts');
}
if (
  enabledProduction?.candidateEvidence?.nested_due_getter_calls !== 0
  || enabledProduction?.candidateEvidence?.nested_expiration_getter_calls !== 0
  || disabledProduction?.candidateEvidence?.nested_due_getter_calls !== 0
  || disabledProduction?.candidateEvidence?.nested_expiration_getter_calls !== 0
) {
  failures.push('production adapter nested operational getter through date_i18n');
}
if (
  enabledProduction?.candidateEvidence?.unrelated_wp_date_i18n !== disabledProduction?.candidateEvidence?.unrelated_wp_date_i18n
  || enabledProduction?.candidateEvidence?.unrelated_gf_format_date !== disabledProduction?.candidateEvidence?.unrelated_gf_format_date
) {
  failures.push('production adapter changed unrelated WordPress/Gravity Forms date formatting');
}

const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
const asciiDigits = (value) => typeof value === 'string'
  ? value.replace(/[۰-۹]/g, (digit) => String(persianDigits.indexOf(digit)))
  : value;

const productionObservations = enabledProduction?.candidateEvidence?.production_observations ?? [];
if (productionObservations.length !== 4) failures.push('production marker observation count is not four');

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
  const reconstructedNative = asciiDigits(enabledVisible.replace(observation.jalali, observation.native_fallback));
  if (reconstructedNative !== disabledVisible) {
    failures.push(`enabled ${key}: native time/host formatting was not preserved around date replacement`);
  }

  const utcCivil = new Date(Number(observation.timestamp_with_offset) * 1000).toISOString().slice(0, 19).replace('T', ' ');
  if (utcCivil !== observation.local_civil) {
    failures.push(`enabled ${key}: timestamp-with-offset was not interpreted as local civil via UTC components`);
  }
}

const prototypeCivils = enabledObservations.map((item) => item.local_civil);
const productionCivils = productionObservations.map((item) => item.local_civil);
if (JSON.stringify(productionCivils) !== JSON.stringify(prototypeCivils)) {
  failures.push(`production timestamp-with-offset civil dates differ from qualified prototype: ${JSON.stringify({ productionCivils, prototypeCivils })}`);
}
const boundaryCivils = prototypeCivils;
if (
  boundaryCivils[0] !== '2030-03-20 23:59:00'
  || boundaryCivils[1] !== '2030-03-21 00:01:00'
  || boundaryCivils[2] !== '2030-03-21 00:03:00'
  || boundaryCivils[3] !== '2030-03-21 23:59:00'
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
  !/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/.test(enabledState.candidate_entry.gfapi.date_created)
) {
  failures.push('raw date_created stopped being canonical ASCII');
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

const entryIdAscii = String(enabledState.candidate_entry.gfapi.id);
const enabledLinks = enabledProduction?.machine?.links ?? [];
if (!enabledLinks.some((link) => typeof link.href_attribute === 'string' && link.href_attribute.includes(`lid=${entryIdAscii}`))) {
  failures.push('visible Entry ID link did not retain the native ASCII entry-id query value');
}
if (enabledLinks.some((link) => /[۰-۹]/.test(String(link.href_attribute)) || /[۰-۹]/.test(String(link.href_property)))) {
  failures.push('Persian digit glyphs leaked into workflow-info URLs/query parameters');
}
if (
  JSON.stringify(enabledProduction?.direction) !== JSON.stringify(disabledProduction?.direction)
  || enabledProduction?.direction?.computed !== 'rtl'
) {
  failures.push('digit shaping changed or failed the existing Persian RTL/BiDi direction contract');
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
if (enabled.print.digit_script_count !== 0 || disabled.print.digit_script_count !== 0 || english.print.digit_script_count !== 0) {
  failures.push('Print unexpectedly loaded the workflow-info digit adapter');
}
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

const admittedSurfaces = {
  'gravityflow.entry-detail.submitted': 'ADMITTED_VERIFIED',
  'gravityflow.entry-detail.last-updated': 'ADMITTED_VERIFIED',
  'gravityflow.entry-detail.due-date': 'ADMITTED_VERIFIED',
  'gravityflow.entry-detail.expiration': 'ADMITTED_VERIFIED',
};
const admission = {
  schema_version: '1.0.0',
  evidence_class: 'G008_GRAVITY_FLOW_ENTRY_DETAIL_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: pgrHead,
  exact_persiangravity_package_sha256: pgrPackageHash,
  exact_gravityflow_version: '3.1.0',
  exact_gravityflow_package_sha256: flowHash,
  site_timezone: 'Asia/Tehran',
  php_default_timezone: 'UTC',
  affected_workflow_info_family: Object.keys(admittedSurfaces),
  surfaces: admittedSurfaces,
  source_contract_proven: requiredContractFlags.every((key) => contract?.[key] === true),
  production_browser_enabled_mode: enabled.mode,
  production_browser_disabled_mode: disabled.mode,
  production_browser_english_mode: english.mode,
  native_time_preserved: result.native_time_preserved,
  visible_digits_persian: !failures.some((failure) => failure.includes('ASCII digits') || failure.includes('mixed-digit')),
  visible_entry_id_persian_machine_link_ascii: !failures.some((failure) => failure.includes('Entry ID link') || failure.includes('visible Entry ID')),
  dom_machine_values_equal: !failures.some((failure) => failure.includes('DOM attributes')),
  english_digits_native_ascii: !failures.some((failure) => failure.includes('English/non-Persian')),
  rtl_bidi_unchanged: !failures.some((failure) => failure.includes('RTL/BiDi')),
  unrelated_date_formatting_unchanged: result.unrelated_date_formatting_unchanged,
  raw_db_gfapi_rest_equal: !failures.some((failure) => failure.includes('DB/GFAPI/REST')),
  workflow_deadline_expiration_state_equal: result.operational_state_equal,
  operational_getter_counts_equal: !failures.some((failure) => failure.includes('getter invocation counts')),
  zero_nested_operational_reentry: !failures.some((failure) => failure.includes('nested operational getter')),
  csv_export_isolated: !failures.some((failure) => failure.includes('CSV/export')),
  repeated_rendering_deterministic: !failures.some((failure) => failure.includes('Repeated rendering')),
  marker_leak_free: !failures.some((failure) => failure.includes('marker leaked')),
  hard_gate_result: failures.length ? 'FAIL' : 'PASS',
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-entry-detail-admission.json'), `${JSON.stringify(admission, null, 2)}\n`);

if (failures.length) throw new Error(`Entry Detail two-hook prototype/production qualification failed: ${failures.join('; ')}`);
console.log('G008_ENTRY_DETAIL_TWO_HOOK_PROTOTYPE QUALIFIED');
console.log('G008_ENTRY_DETAIL_PRODUCTION_ADMISSION PASS');
