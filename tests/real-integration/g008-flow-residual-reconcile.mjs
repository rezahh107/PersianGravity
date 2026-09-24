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

const residualIds = ['gravityflow.status.due-date', 'gravityflow.entry-detail.schedule'];
for (const id of residualIds) {
  const surface = flow.surfaces.find((item) => item.id === id);
  if (!surface) throw new Error(`Missing residual registry surface ${id}.`);
  if (surface.support_state !== 'NOT_PROVEN' || surface.adapter_identity !== null || surface.exact_version_disposition !== 'FINAL_NO_ADMISSION') {
    throw new Error(`Residual registry disposition drifted for ${id}.`);
  }
}
const timelineSurface = flow.surfaces.find((item) => item.id === 'gravityflow.timeline-history');
const printSurface = flow.surfaces.find((item) => item.id === 'gravityflow.print');
if (
  !timelineSurface || timelineSurface.support_state !== 'RUNTIME_PROVEN + ADMITTED_VERIFIED'
  || timelineSurface.adapter_identity !== 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter'
  || timelineSurface.exact_version_disposition !== 'ADMITTED_FOR_EXACT_VERSION'
) {
  throw new Error('Timeline production admission registry record is missing or drifted.');
}
if (
  !printSurface || printSurface.support_state !== 'RUNTIME_PROVEN + ADMITTED_VERIFIED'
  || printSurface.adapter_identity !== 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter (inherited renderer)'
  || printSurface.exact_version_disposition !== 'ADMITTED_BY_VERIFIED_TIMELINE_INHERITANCE'
) {
  throw new Error('Print Timeline inheritance registry record is missing or drifted.');
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
assertBrowser(enabled, 'enabled', 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_BROWSER', 'Residual enabled browser');
assertBrowser(disabled, 'disabled', 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_BROWSER', 'Residual disabled browser');
assertBrowser(statusEnabled, 'enabled', 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER', 'Status enabled browser');
assertBrowser(statusDisabled, 'disabled', 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER', 'Status disabled browser');

if (enabled.entry_detail?.presentation !== 'JALALI' || disabled.entry_detail?.presentation !== 'NATIVE') {
  throw new Error('Residual Timeline mode presentation did not reflect enabled Jalali / disabled native behavior.');
}
if (JSON.stringify(disabled.entry_detail?.timeline) !== JSON.stringify(disabled.print?.timeline)) {
  throw new Error('Disabled Print does not inherit native Timeline rendering.');
}
if (JSON.stringify(enabled.entry_detail?.timeline) !== JSON.stringify(enabled.print?.timeline)) {
  throw new Error('Enabled Print does not inherit Jalali Timeline rendering.');
}
if (enabled.print?.relation !== 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION' || disabled.print?.relation !== 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION') {
  throw new Error('Residual Print inheritance relation is missing.');
}
if (JSON.stringify(enabled.entry_detail?.timeline) === JSON.stringify(disabled.entry_detail?.timeline)) {
  throw new Error('Residual enabled Timeline unexpectedly equals disabled native Timeline.');
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
if (timelinePrint.evidence_class !== 'G008_TIMELINE_PRINT_PRODUCTION_ADMISSION_RECONCILIATION') {
  throw new Error('Timeline/Print production evidence class mismatch.');
}
if (
  timelinePrint.timeline?.initial_entry_disposition !== 'RUNTIME_PROVEN + ADMITTED_VERIFIED'
  || timelinePrint.timeline?.stored_note_event_disposition !== 'RUNTIME_PROVEN + ADMITTED_VERIFIED'
  || timelinePrint.timeline?.storage_unchanged !== true
  || timelinePrint.timeline?.ids_order_bodies_unchanged !== true
  || timelinePrint.timeline?.duplicate_timestamp_identity_proven !== true
  || timelinePrint.timeline?.user_authored_date_looking_text_untouched !== true
  || timelinePrint.timeline?.separate_display_property_consumed !== false
  || timelinePrint.timeline?.date_created_representation_consumed_by_renderer !== true
  || timelinePrint.timeline?.adapter_hook_lifecycle_proven !== true
) {
  throw new Error('Timeline production admission qualification is incomplete.');
}
if (
  timelinePrint.print?.field_grid_relation !== 'REUSES_ENTRY_DETAIL_FIELD_GRID'
  || timelinePrint.print?.timeline_relation !== 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION'
  || timelinePrint.print?.initial_event_propagation_disposition !== 'RUNTIME_PROVEN + ADMITTED_VERIFIED_BY_TIMELINE_INHERITANCE'
  || timelinePrint.print?.stored_note_event_propagation_disposition !== 'RUNTIME_PROVEN + ADMITTED_VERIFIED_BY_TIMELINE_INHERITANCE'
  || timelinePrint.print?.independent_date_seam_disposition !== 'NO_INDEPENDENT_PRINT_DATE_SEAM_REQUIRED'
  || timelinePrint.print?.workflow_sidebar_due_schedule_expiration !== 'ABSENT_FROM_PRINT_RENDER_PATH'
) {
  throw new Error('Print Timeline inheritance admission is incomplete.');
}

const result = {
  schema_version: '2.0.0',
  evidence_class: 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: source.exact.pgr_sha,
  exact_persiangravity_package_sha256: enabled.exact_persiangravity_package_sha256,
  exact_gravityflow_version: flow.version,
  exact_gravityflow_package_sha256: flow.package_sha256,
  site_timezone: enabled.site_timezone,
  php_default_timezone: enabled.php_default_timezone,
  surfaces: Object.fromEntries(residualIds.map((id) => [id, 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'])),
  admitted_surfaces: {
    'gravityflow.timeline-history': 'RUNTIME_PROVEN + ADMITTED_VERIFIED',
    'gravityflow.print': 'ADMITTED_BY_VERIFIED_TIMELINE_INHERITANCE',
  },
  source_contract_proven: true,
  browser_modes: { enabled: enabled.mode, disabled: disabled.mode },
  status_browser_modes: { enabled: statusEnabled.mode, disabled: statusDisabled.mode },
  schedule_qualification: 'date/date_field/delay/empty branches qualified; no downstream value-only seam',
  timeline_qualification: 'historical date_created consumption preserved; downstream formatter-context seam now production-admitted',
  print_field_grid_relation: 'REUSES_ENTRY_DETAIL_FIELD_GRID',
  print_timeline_relation: 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION',
  print_workflow_sidebar: 'ABSENT_FROM_PRINT_RENDER_PATH',
  status: 'PASS',
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-residual-no-admission.json'), `${JSON.stringify(result, null, 2)}\n`);
console.log(`G008_RESIDUAL_RECONCILIATION ${JSON.stringify(result)}`);
