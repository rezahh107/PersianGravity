import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');

const readJson = (file) => JSON.parse(fs.readFileSync(file, 'utf8'));
const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
function allTrue(value) {
  if (typeof value === 'boolean') return value;
  if (!isObject(value)) return false;
  const values = Object.values(value);
  return values.length > 0 && values.every(allTrue);
}
function exactIdentity(evidence, flow, source, label) {
  if (!isObject(evidence)) throw new Error(`${label} evidence is missing.`);
  if (evidence.exact_persiangravity_commit !== source.exact.pgr_sha) throw new Error(`${label} PersianGravity Head mismatch.`);
  if (evidence.exact_gravityflow_version !== flow.version || evidence.exact_gravityflow_package_sha256 !== flow.package_sha256) {
    throw new Error(`${label} exact Gravity Flow identity mismatch.`);
  }
}

const registry = readJson(path.join(root, 'tools/jalali/g008-system-date-surfaces.json'));
const source = readJson(path.join(artifactDir, 'g008-residual-source-probe.json'));
const enabled = readJson(path.join(artifactDir, 'g008-flow-residual-browser-enabled.json'));
const disabled = readJson(path.join(artifactDir, 'g008-flow-residual-browser-disabled.json'));
const statusEnabled = readJson(path.join(artifactDir, 'g008-flow-status-browser-enabled.json'));
const statusDisabled = readJson(path.join(artifactDir, 'g008-flow-status-browser-disabled.json'));
const schedule = readJson(path.join(artifactDir, 'g008-flow-schedule-qualification.json'));
const timelinePrint = readJson(path.join(artifactDir, 'g008-timeline-print-qualification.json'));

if (source?.evidence_class !== 'G008_RESIDUAL_EXACT_SOURCE_PROBE' || !isObject(source.exact) || !isObject(source.source_contract) || !allTrue(source.source_contract)) {
  throw new Error('Residual exact-source contract is missing, empty, non-boolean, or not fully proven.');
}

const flow = registry.products.find((product) => product.product === 'Gravity Flow');
if (!flow || flow.version !== '3.1.0' || flow.package_sha256 !== source.exact.sha256) {
  throw new Error('Residual registry/source exact Gravity Flow identity mismatch.');
}

const ids = [
  'gravityflow.status.due-date',
  'gravityflow.entry-detail.schedule',
  'gravityflow.timeline-history',
  'gravityflow.print',
];
for (const id of ids) {
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
assertBrowser(enabled, 'enabled', 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_NO_ADMISSION_BROWSER', 'Residual enabled browser');
assertBrowser(disabled, 'disabled', 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_NO_ADMISSION_BROWSER', 'Residual disabled browser');
assertBrowser(statusEnabled, 'enabled', 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER', 'Status enabled browser');
assertBrowser(statusDisabled, 'disabled', 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER', 'Status disabled browser');

const browserTargets = ['gravityflow.timeline-history', 'gravityflow.print'].sort();
for (const [label, evidence] of [['enabled', enabled], ['disabled', disabled]]) {
  if (!isObject(evidence.dispositions) || JSON.stringify(Object.keys(evidence.dispositions).sort()) !== JSON.stringify(browserTargets)) {
    throw new Error(`Residual ${label} browser target identity set is missing or unexpected.`);
  }
  for (const id of browserTargets) {
    if (evidence.dispositions[id] !== 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0') {
      throw new Error(`Residual ${label} browser disposition drifted for ${id}.`);
    }
  }
  if (!isObject(evidence.entry_detail) || typeof evidence.entry_detail.url !== 'string' || !Array.isArray(evidence.entry_detail.timeline_native) || evidence.entry_detail.timeline_native.length === 0) {
    throw new Error(`Residual ${label} Timeline observation is empty.`);
  }
  if (!isObject(evidence.print) || typeof evidence.print.url !== 'string' || !Array.isArray(evidence.print.timeline_native) || evidence.print.timeline_native.length === 0) {
    throw new Error(`Residual ${label} Print observation is empty.`);
  }
}
if (JSON.stringify(enabled.entry_detail.timeline_native) !== JSON.stringify(disabled.entry_detail.timeline_native)) {
  throw new Error('Entry Detail Timeline changed between module modes.');
}
if (JSON.stringify(enabled.print.timeline_native) !== JSON.stringify(disabled.print.timeline_native)) {
  throw new Error('Print Timeline changed between module modes.');
}

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

exactIdentity(timelinePrint, flow, source, 'Timeline/Print qualification');
if (timelinePrint.evidence_class !== 'G008_TIMELINE_PRINT_QUALIFICATION_RECONCILIATION') {
  throw new Error('Timeline/Print evidence class mismatch.');
}
if (
  timelinePrint.timeline?.initial_entry_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
  || timelinePrint.timeline?.stored_note_event_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
  || timelinePrint.timeline?.storage_unchanged !== true
  || timelinePrint.timeline?.ids_order_bodies_unchanged !== true
  || timelinePrint.timeline?.user_authored_date_looking_text_untouched !== true
  || timelinePrint.timeline?.separate_display_property_consumed !== false
  || timelinePrint.timeline?.date_created_representation_consumed_by_renderer !== true
) {
  throw new Error('Timeline final no-admission qualification is incomplete.');
}
if (
  timelinePrint.print?.field_grid_relation !== 'REUSES_ENTRY_DETAIL_FIELD_GRID'
  || timelinePrint.print?.timeline_relation !== 'REUSES_ENTRY_DETAIL_TIMELINE'
  || timelinePrint.print?.initial_event_propagation_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
  || timelinePrint.print?.stored_note_event_propagation_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
  || timelinePrint.print?.independent_date_seam_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
  || timelinePrint.print?.workflow_sidebar_due_schedule_expiration !== 'ABSENT_FROM_PRINT_RENDER_PATH'
) {
  throw new Error('Print final no-admission qualification is incomplete.');
}

const result = {
  schema_version: '1.2.0',
  evidence_class: 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: source.exact.pgr_sha,
  exact_persiangravity_package_sha256: enabled.exact_persiangravity_package_sha256,
  exact_gravityflow_version: flow.version,
  exact_gravityflow_package_sha256: flow.package_sha256,
  site_timezone: enabled.site_timezone,
  php_default_timezone: enabled.php_default_timezone,
  surfaces: Object.fromEntries(ids.map((id) => [id, 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'])),
  source_contract_proven: true,
  browser_modes: { enabled: enabled.mode, disabled: disabled.mode },
  status_browser_modes: { enabled: statusEnabled.mode, disabled: statusDisabled.mode },
  schedule_qualification: 'date/date_field/delay/empty branches qualified; no downstream value-only seam',
  timeline_qualification: 'initial and stored events require date_created downstream; display-only property ignored; storage/order/body preserved',
  print_field_grid_relation: 'REUSES_ENTRY_DETAIL_FIELD_GRID',
  print_timeline_relation: 'REUSES_ENTRY_DETAIL_TIMELINE',
  print_workflow_sidebar: 'ABSENT_FROM_PRINT_RENDER_PATH',
  print_independent_date_seam: 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
  status: 'PASS',
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-residual-no-admission.json'), `${JSON.stringify(result, null, 2)}\n`);
console.log(`G008_RESIDUAL_NO_ADMISSION_RECONCILIATION ${JSON.stringify(result)}`);
