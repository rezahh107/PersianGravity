import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');

const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));
const baseline = read('g008-flow-inbox-fixture-baseline.json');
const enabledState = read('g008-flow-status-state-enabled.json');
const disabledState = read('g008-flow-status-state-disabled.json');
const enabledBrowser = read('g008-flow-status-browser-enabled.json');
const disabledBrowser = read('g008-flow-status-browser-disabled.json');
const source = read('source-discovery.json');

function allContractValuesTrue(value) {
  if (typeof value === 'boolean') return value;
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const values = Object.values(value);
  return values.length > 0 && values.every(allContractValuesTrue);
}

function canonicalBaseline(entries) {
  return entries.map((entry) => ({
    id: Number(entry.id),
    date_created: String(entry.date_created),
    workflow_timestamp: Number(entry.workflow_timestamp),
    workflow_step: Number(entry.workflow_step),
    workflow_final_status: String(entry.workflow_final_status),
    assignees: [...entry.assignees].sort(),
  })).sort((a, b) => a.id - b.id);
}

function canonicalState(entries) {
  return entries.map((entry) => ({
    id: Number(entry.id),
    date_created: String(entry.gfapi_date_created),
    workflow_timestamp: Number(entry.gfapi_workflow_timestamp),
    workflow_step: Number(entry.workflow_step),
    workflow_final_status: String(entry.workflow_final_status),
    assignees: [...entry.assignees].sort(),
  })).sort((a, b) => a.id - b.id);
}

function sortedIds(entries, key, direction) {
  const multiplier = direction === 'asc' ? 1 : -1;
  return [...entries].sort((a, b) => {
    if (key === 'workflow_timestamp') return (Number(a[key]) - Number(b[key])) * multiplier;
    return String(a[key]).localeCompare(String(b[key])) * multiplier;
  }).map((entry) => Number(entry.id));
}

function rowMap(rows) {
  return new Map(rows.map((row) => [Number(row.id), row]));
}

const baselineEntries = canonicalBaseline(baseline.entries);
const enabledEntries = canonicalState(enabledState.entries);
const disabledEntries = canonicalState(disabledState.entries);
const expectedIds = baselineEntries.map((entry) => entry.id);
const alphaId = Number(baseline.entries.find((entry) => entry.key === 'alpha').id);
const failures = [];

if (baseline.gravityflow_version !== '3.1.0') failures.push('baseline Gravity Flow version is not exact 3.1.0');
if (enabledState.gravityflow_version !== '3.1.0' || disabledState.gravityflow_version !== '3.1.0') failures.push('Status runtime Gravity Flow version drifted');
if (!enabledState.adapter_registered || disabledState.adapter_registered) failures.push('Status adapter registration did not follow module state');
if (enabledState.site_timezone !== 'Asia/Tehran' || disabledState.site_timezone !== 'Asia/Tehran') failures.push('Status site timezone drifted');
if (enabledState.php_default_timezone !== 'UTC' || disabledState.php_default_timezone !== 'UTC') failures.push('Status PHP default timezone drifted from UTC');
if (JSON.stringify(enabledEntries) !== JSON.stringify(baselineEntries)) failures.push('enabled Status presentation mutated GFAPI/workflow/assignment state');
if (JSON.stringify(disabledEntries) !== JSON.stringify(baselineEntries)) failures.push('disabled Status presentation mutated GFAPI/workflow/assignment state');

for (const state of [enabledState, disabledState]) {
  for (const entry of state.entries) {
    if (entry.gfapi_date_created !== entry.db_date_created || entry.gfapi_date_created !== entry.rest_date_created) {
      failures.push(`${state.mode} Status date_created storage/REST mismatch for entry ${entry.id}`);
    }
    if (
      Number(entry.gfapi_workflow_timestamp) !== Number(entry.db_workflow_timestamp)
      || Number(entry.gfapi_workflow_timestamp) !== Number(entry.rest_workflow_timestamp)
    ) {
      failures.push(`${state.mode} Status workflow_timestamp storage/REST mismatch for entry ${entry.id}`);
    }
  }
}

const expectedSorts = {
  date_created_asc: sortedIds(baselineEntries, 'date_created', 'asc'),
  date_created_desc: sortedIds(baselineEntries, 'date_created', 'desc'),
  workflow_timestamp_asc: sortedIds(baselineEntries, 'workflow_timestamp', 'asc'),
  workflow_timestamp_desc: sortedIds(baselineEntries, 'workflow_timestamp', 'desc'),
};
for (const [name, expected] of Object.entries(expectedSorts)) {
  if (JSON.stringify(enabledState.status_queries?.[name]?.ids) !== JSON.stringify(expected)) failures.push(`enabled Status ${name} query sort changed`);
  if (JSON.stringify(disabledState.status_queries?.[name]?.ids) !== JSON.stringify(expected)) failures.push(`disabled Status ${name} query sort changed`);
  if (JSON.stringify(enabledBrowser.sort?.[name]) !== JSON.stringify(expected)) failures.push(`enabled Status ${name} browser sort changed`);
  if (JSON.stringify(disabledBrowser.sort?.[name]) !== JSON.stringify(expected)) failures.push(`disabled Status ${name} browser sort changed`);
}

for (const state of [enabledState, disabledState]) {
  const defaultIds = [...(state.status_queries?.default?.ids ?? [])].sort((a, b) => a - b);
  if (JSON.stringify(defaultIds) !== JSON.stringify([...expectedIds].sort((a, b) => a - b))) failures.push(`${state.mode} Status default query identity changed`);
  if (state.status_queries?.default?.total !== expectedIds.length) failures.push(`${state.mode} Status default query count changed`);
  if (JSON.stringify(state.status_queries?.civil_day_2026_03_21?.ids) !== JSON.stringify([alphaId])) failures.push(`${state.mode} Status local civil-day filter changed`);
}
if (JSON.stringify(enabledBrowser.civil_day_filter?.visible_entry_ids) !== JSON.stringify([alphaId])) failures.push('enabled Status browser civil-day filter changed');
if (JSON.stringify(disabledBrowser.civil_day_filter?.visible_entry_ids) !== JSON.stringify([alphaId])) failures.push('disabled Status browser civil-day filter changed');

if (!enabledState.csv_raw_sources_present || !disabledState.csv_raw_sources_present) failures.push('Status CSV lost raw source values');
if (!enabledState.csv_jalali_absent || !disabledState.csv_jalali_absent) failures.push('Status CSV contains Jalali presentation values');
if (enabledState.csv_sha256 !== disabledState.csv_sha256) failures.push('Status CSV changed with presentation module state');
if (!enabledState.late_table_to_csv_native || !disabledState.late_table_to_csv_native) failures.push('late table-to-csv mutation did not remain native');
if (!enabledState.late_csv_to_table_jalali) failures.push('late csv-to-table mutation did not reach admitted Jalali table presentation');
if (!disabledState.late_csv_to_table_native) failures.push('late csv-to-table mutation did not preserve module-disabled native table presentation');

const enabledRows = rowMap(enabledBrowser.rows);
const disabledRows = rowMap(disabledBrowser.rows);
for (const fixture of baseline.entries) {
  const id = Number(fixture.id);
  const enabled = enabledRows.get(id);
  const disabled = disabledRows.get(id);
  if (!enabled || !disabled) {
    failures.push(`Status browser row missing for entry ${id}`);
    continue;
  }
  if (enabled.date_created !== fixture.expected_created_jalali || enabled.workflow_timestamp !== fixture.expected_updated_jalali) {
    failures.push(`enabled Status Jalali display mismatch for entry ${id}`);
  }
  if (disabled.date_created !== fixture.expected_created_native || disabled.workflow_timestamp !== fixture.expected_updated_native) {
    failures.push(`disabled Status native display mismatch for entry ${id}`);
  }
}

if (
  enabledBrowser.exact_gravityflow_version !== '3.1.0'
  || disabledBrowser.exact_gravityflow_version !== '3.1.0'
  || enabledBrowser.exact_gravityflow_package_sha256 !== process.env.WU008_FLOW_SHA256
  || disabledBrowser.exact_gravityflow_package_sha256 !== process.env.WU008_FLOW_SHA256
) {
  failures.push('Status browser exact Gravity Flow package identity drifted');
}

const statusSource = source?.classifications?.g008?.flow_status;
const sourceContract = statusSource?.source_contract;
if (!sourceContract) {
  failures.push('exact-package Status source/timezone/context contract is missing');
} else if (!allContractValuesTrue(sourceContract)) {
  failures.push('exact-package Status source/timezone/context contract contains an unproven requirement');
}

const result = {
  schema_version: '1.0.0',
  evidence_class: 'G008_GRAVITY_FLOW_STATUS_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: enabledBrowser.exact_persiangravity_commit,
  exact_persiangravity_package_sha256: enabledBrowser.exact_persiangravity_package_sha256,
  exact_gravityflow_version: enabledBrowser.exact_gravityflow_version,
  exact_gravityflow_package_sha256: enabledBrowser.exact_gravityflow_package_sha256,
  source_timezone: {
    date_created: 'UTC_Y_M_D_H_I_S_GRAVITY_FORMS_ENTRY_CONTRACT',
    workflow_timestamp: 'UNIX_EPOCH_ABSOLUTE_INSTANT',
    native_flow_numeric_intermediate_timezone: 'UTC_PHP_DEFAULT_PROVEN_AT_RUNTIME',
    target_timezone: 'WORDPRESS_SITE_TIMEZONE_ASIA_TEHRAN_FIXTURE',
  },
  source_contract_proven: Boolean(sourceContract && allContractValuesTrue(sourceContract)),
  presentation_isolation: {
    gfapi_state_equal: JSON.stringify(enabledEntries) === JSON.stringify(disabledEntries),
    database_and_rest_match_gfapi: failures.every((failure) => !failure.includes('storage/REST mismatch')),
    status_query_equal: JSON.stringify(enabledState.status_queries) === JSON.stringify(disabledState.status_queries),
    sort_equal: JSON.stringify(enabledBrowser.sort) === JSON.stringify(disabledBrowser.sort),
    civil_day_filter_equal: JSON.stringify(enabledBrowser.civil_day_filter) === JSON.stringify(disabledBrowser.civil_day_filter),
    csv_equal: enabledState.csv_sha256 === disabledState.csv_sha256,
    csv_native_raw: enabledState.csv_raw_sources_present && enabledState.csv_jalali_absent,
    late_table_to_csv_native: enabledState.late_table_to_csv_native && disabledState.late_table_to_csv_native,
    late_csv_to_table_jalali: enabledState.late_csv_to_table_jalali,
    late_csv_to_table_disabled_native: disabledState.late_csv_to_table_native,
  },
  surfaces: {
    'gravityflow.status.date-created': failures.length ? 'NOT_PROVEN' : 'ADMITTED_VERIFIED',
    'gravityflow.status.workflow-timestamp': failures.length ? 'NOT_PROVEN' : 'ADMITTED_VERIFIED',
  },
  hard_gate_result: failures.length ? 'FAIL' : 'PASS',
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-status-admission.json'), `${JSON.stringify(result, null, 2)}\n`);
if (failures.length) throw new Error(`G008 Flow Status admission failed: ${failures.join('; ')}`);
console.log('G008_FLOW_STATUS_ADMISSION PASS');
