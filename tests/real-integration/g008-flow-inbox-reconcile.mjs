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
    }))
    .sort((a, b) => a.id - b.id);
}

function rawRows(evidence) {
  return evidence.rows
    .map((row) => ({ id: Number(row.id), date_created: Number(row.date_created), last_updated: Number(row.last_updated) }))
    .sort((a, b) => a.id - b.id);
}

const baselineEntries = canonicalEntries(baseline.entries);
const enabledEntries = canonicalEntries(enabledState.entries);
const disabledEntries = canonicalEntries(disabledState.entries);
const expectedIds = baselineEntries.map((entry) => entry.id).sort((a, b) => a - b);
const failures = [];

if (baseline.gravityflow_version !== '3.1.0') failures.push('baseline Gravity Flow version is not exact 3.1.0');
if (enabledState.gravityflow_version !== '3.1.0' || disabledState.gravityflow_version !== '3.1.0') failures.push('runtime Gravity Flow version drifted');
if (!enabledState.adapter_registered || disabledState.adapter_registered) failures.push('adapter registration did not follow module state');
if (enabledState.site_timezone !== 'Asia/Tehran' || disabledState.site_timezone !== 'Asia/Tehran') failures.push('site timezone drifted');
if (enabledState.php_default_timezone !== 'UTC' || disabledState.php_default_timezone !== 'UTC') failures.push('PHP/WordPress default timezone drifted from UTC');
if (JSON.stringify(enabledEntries) !== JSON.stringify(baselineEntries)) failures.push('enabled presentation mutated raw/workflow/assignment state');
if (JSON.stringify(disabledEntries) !== JSON.stringify(baselineEntries)) failures.push('disabled presentation mutated raw/workflow/assignment state');
if (JSON.stringify(enabledState.query_ids) !== JSON.stringify(expectedIds) || JSON.stringify(disabledState.query_ids) !== JSON.stringify(expectedIds)) failures.push('Inbox query result identity changed');
if (enabledState.query_total !== expectedIds.length || disabledState.query_total !== expectedIds.length) failures.push('Inbox query count changed');
if (JSON.stringify(rawRows(enabledBrowser)) !== JSON.stringify(rawRows(disabledBrowser))) failures.push('AG Grid raw compare values changed with presentation module state');
if (JSON.stringify(enabledBrowser.quick_filter.visible_entry_ids) !== JSON.stringify(disabledBrowser.quick_filter.visible_entry_ids)) failures.push('AG Grid quick-filter result changed with presentation module state');
if (JSON.stringify(enabledBrowser.sort.date_created) !== JSON.stringify(disabledBrowser.sort.date_created)) failures.push('date_created AG Grid sort behavior changed');
if (JSON.stringify(enabledBrowser.sort.last_updated) !== JSON.stringify(disabledBrowser.sort.last_updated)) failures.push('last_updated AG Grid sort behavior changed');

const semanticProbe = source?.classifications?.g008?.flow_inbox?.source_semantics_probe;
if (!semanticProbe) failures.push('exact-package source semantic probe is missing');

const result = {
  schema_version: '1.0.0',
  evidence_class: 'G008_GRAVITY_FLOW_INBOX_ADMISSION_RECONCILIATION',
  exact_persiangravity_commit: enabledBrowser.exact_persiangravity_commit,
  exact_persiangravity_package_sha256: enabledBrowser.exact_persiangravity_package_sha256,
  exact_gravityflow_version: enabledBrowser.exact_gravityflow_version,
  exact_gravityflow_package_sha256: enabledBrowser.exact_gravityflow_package_sha256,
  source_timezone: {
    date_created: 'UTC_Y_M_D_H_I_S_GRAVITY_FORMS_ENTRY_CONTRACT',
    last_updated: 'UNIX_EPOCH_ABSOLUTE_INSTANT',
    native_flow_intermediate_timezone: 'UTC_PHP_DEFAULT_PROVEN_AT_RUNTIME',
    target_timezone: 'WORDPRESS_SITE_TIMEZONE_ASIA_TEHRAN_FIXTURE',
  },
  presentation_isolation: {
    raw_state_equal: JSON.stringify(enabledEntries) === JSON.stringify(disabledEntries),
    ag_grid_compare_values_equal: JSON.stringify(rawRows(enabledBrowser)) === JSON.stringify(rawRows(disabledBrowser)),
    query_ids_equal: JSON.stringify(enabledState.query_ids) === JSON.stringify(disabledState.query_ids),
    sort_equal: JSON.stringify(enabledBrowser.sort) === JSON.stringify(disabledBrowser.sort),
    filter_equal: JSON.stringify(enabledBrowser.quick_filter) === JSON.stringify(disabledBrowser.quick_filter),
  },
  surfaces: {
    'gravityflow.inbox.date-created': failures.length ? 'NOT_PROVEN' : 'ADMITTED_VERIFIED',
    'gravityflow.inbox.last-updated': failures.length ? 'NOT_PROVEN' : 'ADMITTED_VERIFIED',
  },
  hard_gate_result: failures.length ? 'FAIL' : 'PASS',
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-inbox-admission.json'), `${JSON.stringify(result, null, 2)}\n`);
if (failures.length) throw new Error(`G008 Flow Inbox admission failed: ${failures.join('; ')}`);
console.log('G008_FLOW_INBOX_ADMISSION PASS');
