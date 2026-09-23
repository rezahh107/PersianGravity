import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');

const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));
const baseline = read('g008-flow-inbox-fixture-baseline.json');
const enabledState = read('g008-flow-inbox-state-enabled.json');
const disabledState = read('g008-flow-inbox-state-disabled.json');
const enabledBrowser = read('g008-flow-inbox-browser-enabled.json');
const disabledBrowser = read('g008-flow-inbox-browser-disabled.json');
const source = read('source-discovery.json');

function canonicalEntries(entries) {
  return entries
    .map((entry) => ({
      id: Number(entry.id),
      date_created: entry.date_created,
      workflow_timestamp: Number(entry.workflow_timestamp),
      workflow_step: Number(entry.workflow_step),
      workflow_final_status: entry.workflow_final_status,
      assignees: [...entry.assignees].sort(),
      due_date_enabled: Boolean(entry.due_date_enabled),
      due_date_timestamp: Number(entry.due_date_timestamp),
      overdue: Boolean(entry.overdue),
    }))
    .sort((a, b) => a.id - b.id);
}

function rawRows(evidence) {
  return evidence.rows
    .map((row) => ({
      id: Number(row.id),
      date_created: Number(row.date_created),
      last_updated: Number(row.last_updated),
      due_date: Number(row.due_date),
    }))
    .sort((a, b) => a.id - b.id);
}

function allContractValuesTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object') return Object.values(value).every(allContractValuesTrue);
  return true;
}

const baselineEntries = canonicalEntries(baseline.entries);
const enabledEntries = canonicalEntries(enabledState.entries);
const disabledEntries = canonicalEntries(disabledState.entries);
const expectedIds = baselineEntries.map((entry) => entry.id).sort((a, b) => a - b);
const baseFailures = [];
const dueFailures = [];

if (baseline.gravityflow_version !== '3.1.0') baseFailures.push('baseline Gravity Flow version is not exact 3.1.0');
if (enabledState.gravityflow_version !== '3.1.0' || disabledState.gravityflow_version !== '3.1.0') baseFailures.push('runtime Gravity Flow version drifted');
if (!enabledState.adapter_registered || disabledState.adapter_registered) baseFailures.push('adapter registration did not follow module state');
if (enabledState.site_timezone !== 'Asia/Tehran' || disabledState.site_timezone !== 'Asia/Tehran') baseFailures.push('site timezone drifted');
if (enabledState.php_default_timezone !== 'UTC' || disabledState.php_default_timezone !== 'UTC') baseFailures.push('PHP/WordPress default timezone drifted from UTC');
if (JSON.stringify(enabledEntries) !== JSON.stringify(baselineEntries)) baseFailures.push('enabled presentation mutated raw/workflow/assignment/deadline state');
if (JSON.stringify(disabledEntries) !== JSON.stringify(baselineEntries)) baseFailures.push('disabled presentation mutated raw/workflow/assignment/deadline state');
if (JSON.stringify(enabledState.query_ids) !== JSON.stringify(expectedIds) || JSON.stringify(disabledState.query_ids) !== JSON.stringify(expectedIds)) baseFailures.push('Inbox query result identity changed');
if (enabledState.query_total !== expectedIds.length || disabledState.query_total !== expectedIds.length) baseFailures.push('Inbox query count changed');
if (JSON.stringify(rawRows(enabledBrowser)) !== JSON.stringify(rawRows(disabledBrowser))) baseFailures.push('AG Grid raw compare values changed with presentation module state');
if (JSON.stringify(enabledBrowser.quick_filter.visible_entry_ids) !== JSON.stringify(disabledBrowser.quick_filter.visible_entry_ids)) dueFailures.push('due_date AG Grid quick-filter result changed with presentation module state');
if (JSON.stringify(enabledBrowser.sort.date_created) !== JSON.stringify(disabledBrowser.sort.date_created)) baseFailures.push('date_created AG Grid sort behavior changed');
if (JSON.stringify(enabledBrowser.sort.last_updated) !== JSON.stringify(disabledBrowser.sort.last_updated)) baseFailures.push('last_updated AG Grid sort behavior changed');
if (JSON.stringify(enabledBrowser.sort.due_date) !== JSON.stringify(disabledBrowser.sort.due_date)) dueFailures.push('due_date AG Grid sort behavior changed');

const expectedDue = baselineEntries.map((entry) => ({ id: entry.id, due_date_timestamp: entry.due_date_timestamp, overdue: entry.overdue }));
const enabledDue = enabledEntries.map((entry) => ({ id: entry.id, due_date_timestamp: entry.due_date_timestamp, overdue: entry.overdue }));
const disabledDue = disabledEntries.map((entry) => ({ id: entry.id, due_date_timestamp: entry.due_date_timestamp, overdue: entry.overdue }));
if (JSON.stringify(enabledDue) !== JSON.stringify(expectedDue) || JSON.stringify(disabledDue) !== JSON.stringify(expectedDue)) {
  dueFailures.push('authoritative due-date timestamp or overdue classification changed across module modes');
}
if (!baselineEntries.some((entry) => entry.due_date_timestamp === 0 && entry.due_date_enabled === false)) {
  dueFailures.push('no-due-date sentinel fixture is missing');
}
if (!baselineEntries.some((entry) => entry.due_date_timestamp > 0 && entry.overdue === true)) {
  dueFailures.push('overdue due-date fixture is missing');
}
if (!baselineEntries.some((entry) => entry.due_date_timestamp > 0 && entry.overdue === false)) {
  dueFailures.push('future due-date fixture is missing');
}

const flowInboxEvidence = source?.classifications?.g008?.flow_inbox;
const semanticProbe = flowInboxEvidence?.source_semantics_probe;
const sourceContract = flowInboxEvidence?.source_contract;
const dueSourceContract = sourceContract?.due_date;
if (!semanticProbe) baseFailures.push('exact-package source semantic probe is missing');
if (!sourceContract) {
  baseFailures.push('exact-package source/timezone contract is missing');
} else if (!allContractValuesTrue({
  date_created: sourceContract.date_created,
  last_updated: sourceContract.last_updated,
  timezone_and_formatting: sourceContract.timezone_and_formatting,
  presentation_seam: sourceContract.presentation_seam,
})) {
  baseFailures.push('existing Inbox source/timezone contract contains an unproven requirement');
}
if (!dueSourceContract) {
  dueFailures.push('exact-package due-date source/deadline contract is missing');
} else if (!allContractValuesTrue(dueSourceContract)) {
  dueFailures.push('exact-package due-date source/deadline contract contains an unproven requirement');
}

const failures = [...baseFailures, ...dueFailures];
const result = {
  schema_version: '1.2.0',
  evidence_class: 'G008_GRAVITY_FLOW_INBOX_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: enabledBrowser.exact_persiangravity_commit,
  exact_persiangravity_package_sha256: enabledBrowser.exact_persiangravity_package_sha256,
  exact_gravityflow_version: enabledBrowser.exact_gravityflow_version,
  exact_gravityflow_package_sha256: enabledBrowser.exact_gravityflow_package_sha256,
  source_timezone: {
    date_created: 'UTC_Y_M_D_H_I_S_GRAVITY_FORMS_ENTRY_CONTRACT',
    last_updated: 'UNIX_EPOCH_ABSOLUTE_INSTANT',
    due_date: 'UNIX_EPOCH_UTC_ABSOLUTE_INSTANT_FROM_CURRENT_STEP_GET_DUE_DATE_TIMESTAMP',
    native_flow_intermediate_timezone: 'UTC_PHP_DEFAULT_PROVEN_AT_RUNTIME',
    target_timezone: 'WORDPRESS_SITE_TIMEZONE_ASIA_TEHRAN_FIXTURE',
  },
  source_contract_proven: Boolean(sourceContract && allContractValuesTrue(sourceContract)),
  due_date_contract_proven: Boolean(dueSourceContract && allContractValuesTrue(dueSourceContract)),
  presentation_isolation: {
    raw_state_equal: JSON.stringify(enabledEntries) === JSON.stringify(disabledEntries),
    due_timestamp_and_overdue_equal: JSON.stringify(enabledDue) === JSON.stringify(disabledDue),
    ag_grid_compare_values_equal: JSON.stringify(rawRows(enabledBrowser)) === JSON.stringify(rawRows(disabledBrowser)),
    query_ids_equal: JSON.stringify(enabledState.query_ids) === JSON.stringify(disabledState.query_ids),
    sort_equal: JSON.stringify(enabledBrowser.sort) === JSON.stringify(disabledBrowser.sort),
    filter_equal: JSON.stringify(enabledBrowser.quick_filter) === JSON.stringify(disabledBrowser.quick_filter),
  },
  surfaces: {
    'gravityflow.inbox.date-created': baseFailures.length ? 'NOT_PROVEN' : 'ADMITTED_VERIFIED',
    'gravityflow.inbox.last-updated': baseFailures.length ? 'NOT_PROVEN' : 'ADMITTED_VERIFIED',
    'gravityflow.inbox.due-date': failures.length ? 'NOT_PROVEN' : 'ADMITTED_VERIFIED',
  },
  hard_gate_result: failures.length ? 'FAIL' : 'PASS',
  base_failures: baseFailures,
  due_date_failures: dueFailures,
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-inbox-admission.json'), `${JSON.stringify(result, null, 2)}\n`);
if (failures.length) throw new Error(`G008 Flow Inbox admission failed: ${failures.join('; ')}`);
console.log('G008_FLOW_INBOX_ADMISSION PASS');
