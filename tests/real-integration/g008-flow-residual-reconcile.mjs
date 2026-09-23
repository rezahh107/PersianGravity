import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}
function allTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object') return Object.values(value).every(allTrue);
  return true;
}

const registry = readJson(path.join(root, 'tools/jalali/g008-system-date-surfaces.json'));
const source = readJson(path.join(artifactDir, 'g008-residual-source-probe.json'));
const enabled = readJson(path.join(artifactDir, 'g008-flow-residual-browser-enabled.json'));
const disabled = readJson(path.join(artifactDir, 'g008-flow-residual-browser-disabled.json'));
const statusEnabled = readJson(path.join(artifactDir, 'g008-flow-status-browser-enabled.json'));
const statusDisabled = readJson(path.join(artifactDir, 'g008-flow-status-browser-disabled.json'));

const flow = registry.products.find((product) => product.product === 'Gravity Flow');
if (!flow || flow.version !== '3.1.0' || flow.package_sha256 !== source.exact.sha256) {
  throw new Error('Residual registry/source exact Gravity Flow identity mismatch.');
}
const ids = [
  'gravityflow.status.due-date',
  'gravityflow.entry-detail.schedule-due-expiration',
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
if (!allTrue(source.source_contract)) {
  throw new Error('Residual exact-source no-admission contract is not fully proven.');
}

for (const evidence of [enabled, disabled]) {
  if (evidence.exact_persiangravity_commit !== source.exact.pgr_sha) throw new Error('Residual browser/source Head mismatch.');
  if (evidence.exact_gravityflow_version !== flow.version || evidence.exact_gravityflow_package_sha256 !== flow.package_sha256) {
    throw new Error('Residual browser exact Gravity Flow identity mismatch.');
  }
}
if (JSON.stringify(enabled.entry_detail.timeline_native) !== JSON.stringify(disabled.entry_detail.timeline_native)) {
  throw new Error('Entry Detail timeline changed between enabled and disabled modes.');
}
if (enabled.entry_detail.due_date_native !== disabled.entry_detail.due_date_native) {
  throw new Error('Entry Detail due-date display changed between enabled and disabled modes.');
}
if (JSON.stringify(enabled.print.timeline_native) !== JSON.stringify(disabled.print.timeline_native)) {
  throw new Error('Print timeline changed between enabled and disabled modes.');
}
if (enabled.print.propagation !== disabled.print.propagation) {
  throw new Error('Print propagation classification changed between modes.');
}
for (const evidence of [statusEnabled, statusDisabled]) {
  if (evidence.residual_no_admission?.surface !== 'gravityflow.status.due-date' || evidence.residual_no_admission?.disposition !== 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0') {
    throw new Error('Status due-date no-admission browser evidence is missing.');
  }
}

const result = {
  schema_version: '1.0.0',
  evidence_class: 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: source.exact.pgr_sha,
  exact_persiangravity_package_sha256: enabled.exact_persiangravity_package_sha256,
  exact_gravityflow_version: flow.version,
  exact_gravityflow_package_sha256: flow.package_sha256,
  surfaces: Object.fromEntries(ids.map((id) => [id, 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'])),
  source_contract_proven: true,
  enabled_disabled_native_equality: true,
  print_propagation: 'native Entry Detail/Timeline reuse; no independent Print calendar seam',
  status: 'PASS',
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-residual-no-admission.json'), `${JSON.stringify(result, null, 2)}\n`);
console.log(`G008_RESIDUAL_NO_ADMISSION_RECONCILIATION ${JSON.stringify(result)}`);
