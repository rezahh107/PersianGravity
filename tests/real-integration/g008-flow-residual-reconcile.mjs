import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');

const readJson = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));
const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
function exactIdentity(evidence, flow, source, label) {
  if (!isObject(evidence)) throw new Error(`${label} evidence is missing.`);
  if (evidence.exact_persiangravity_commit !== source.exact.pgr_sha) throw new Error(`${label} PersianGravity Head mismatch.`);
  if (evidence.exact_gravityflow_version !== flow.version || evidence.exact_gravityflow_package_sha256 !== flow.package_sha256) {
    throw new Error(`${label} exact Gravity Flow identity mismatch.`);
  }
}
function requireTrueFlags(contract, flags, label) {
  if (!isObject(contract)) throw new Error(`${label} source contract is missing.`);
  for (const flag of flags) {
    if (contract[flag] !== true) throw new Error(`${label} source contract missing ${flag}.`);
  }
}

const registry = readJson(path.join(root, 'tools/jalali/g008-system-date-surfaces.json'));
const source = readJson(path.join(artifactDir, 'g008-residual-source-probe.json'));
const statusEnabled = readJson(path.join(artifactDir, 'g008-flow-status-browser-enabled.json'));
const statusDisabled = readJson(path.join(artifactDir, 'g008-flow-status-browser-disabled.json'));
const schedule = readJson(path.join(artifactDir, 'g008-flow-schedule-qualification.json'));

if (source?.evidence_class !== 'G008_RESIDUAL_EXACT_SOURCE_PROBE' || !isObject(source.exact) || !isObject(source.source_contract)) {
  throw new Error('Residual exact-source evidence is missing or malformed.');
}
requireTrueFlags(source.source_contract.status_due_date, [
  'table_reads_operational_due_getter_directly',
  'table_formats_due_inside_column_method',
  'table_echoes_direct_output',
  'table_native_empty_uses_dash_entity',
  'table_has_no_status_value_filter',
  'table_has_no_entry_url_proof_seam',
  'export_has_separate_due_branch',
  'export_uses_generic_status_filter',
  'due_getter_is_operational_filter',
  'overdue_uses_same_due_getter',
], 'Status due-date');
requireTrueFlags(source.source_contract.entry_detail_schedule_due_expiration, [
  'schedule_reads_operational_getter_directly',
  'schedule_prints_directly',
  'schedule_has_no_value_filter',
  'schedule_getter_is_operational_filter',
  'schedule_validation_uses_same_getter',
  'schedule_date_branch_uses_configured_civil_date',
  'schedule_date_field_and_delay_localize_operational_timestamp',
  'schedule_date_timestamp_reads_configured_date',
  'schedule_date_field_timestamp_reads_configured_field_and_offset',
  'schedule_delay_timestamp_uses_step_timestamp_and_offset',
  'step_timestamp_reads_step_scoped_entry_meta',
  'queued_step_status_calls_schedule_renderer',
], 'Entry Detail schedule');

const flow = registry.products.find((product) => product.product === 'Gravity Flow');
if (!flow || flow.version !== '3.1.0' || flow.package_sha256 !== source.exact.sha256) {
  throw new Error('Residual registry/source exact Gravity Flow identity mismatch.');
}

const residualIds = ['gravityflow.status.due-date', 'gravityflow.entry-detail.schedule'];
for (const id of residualIds) {
  const surface = flow.surfaces.find((item) => item.id === id);
  if (!surface) throw new Error(`Missing residual registry surface ${id}.`);
  if (surface.support_state !== 'NOT_PROVEN' || surface.adapter_identity !== null || surface.exact_version_disposition !== 'FINAL_NO_ADMISSION') {
    throw new Error(`Residual registry disposition drifted for ${id}.`);
  }
}
if (flow.surfaces.some((item) => item.id === 'gravityflow.entry-detail.schedule-due-expiration')) {
  throw new Error('Legacy combined Entry Detail residual record is still present.');
}

function assertBrowser(evidence, mode, expectedClass, label) {
  exactIdentity(evidence, flow, source, label);
  if (evidence.evidence_class !== expectedClass) throw new Error(`${label} evidence class mismatch.`);
  if (evidence.mode !== mode) throw new Error(`${label} mode must be ${mode}.`);
  if (evidence.site_timezone !== 'Asia/Tehran' || evidence.php_default_timezone !== 'UTC') throw new Error(`${label} timezone identity mismatch.`);
}
assertBrowser(statusEnabled, 'enabled', 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER', 'Status enabled browser');
assertBrowser(statusDisabled, 'disabled', 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER', 'Status disabled browser');

for (const [label, evidence] of [['enabled', statusEnabled], ['disabled', statusDisabled]]) {
  if (!Array.isArray(evidence.rows) || evidence.rows.length === 0 || evidence.rows.some((row) => typeof row?.due_date !== 'string')) {
    throw new Error(`Status ${label} due-date observations are empty or malformed.`);
  }
  if (
    evidence.residual_no_admission?.surface !== 'gravityflow.status.due-date'
    || evidence.residual_no_admission?.disposition !== 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'
    || evidence.residual_no_admission?.enabled_and_disabled_expect_native !== true
  ) {
    throw new Error(`Status ${label} due-date target identity is missing.`);
  }
}

exactIdentity(schedule, flow, source, 'Schedule qualification');
if (
  schedule.evidence_class !== 'G008_FLOW_SCHEDULE_BRANCH_QUALIFICATION_RECONCILIATION'
  || schedule.source_contract_proven !== true
  || schedule.enabled_disabled_native_equality !== true
  || schedule.operational_getter_counts_equal !== true
  || schedule.disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
) {
  throw new Error('Schedule final no-admission qualification is incomplete.');
}

const result = {
  schema_version: '2.0.0',
  evidence_class: 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: source.exact.pgr_sha,
  exact_persiangravity_package_sha256: statusEnabled.exact_persiangravity_package_sha256,
  exact_gravityflow_version: flow.version,
  exact_gravityflow_package_sha256: flow.package_sha256,
  site_timezone: statusEnabled.site_timezone,
  php_default_timezone: statusEnabled.php_default_timezone,
  surfaces: Object.fromEntries(residualIds.map((id) => [id, 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'])),
  source_contract_proven: true,
  status_browser_modes: { enabled: statusEnabled.mode, disabled: statusDisabled.mode },
  schedule_qualification: 'date/date_field/delay/empty branches qualified; no downstream value-only seam',
  status: 'PASS',
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-residual-no-admission.json'), `${JSON.stringify(result, null, 2)}\n`);
console.log(`G008_RESIDUAL_RECONCILIATION ${JSON.stringify(result)}`);
