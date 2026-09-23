import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');

const read = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));
const fixture = read('g008-flow-schedule-fixture.json');
const source = read('g008-residual-source-probe.json');
const enabled = read('g008-flow-schedule-browser-enabled.json');
const disabled = read('g008-flow-schedule-browser-disabled.json');

const failures = [];
const expectedHead = process.env.WU008_PGR_SHA;
const expectedPgrPackage = process.env.WU008_PGR_PACKAGE_SHA256;
const expectedFlowHash = process.env.WU008_FLOW_SHA256;

function identity(evidence, mode, label) {
  if (evidence.evidence_class !== 'AUTHENTIC_G008_FLOW_SCHEDULE_BRANCH_BROWSER') failures.push(`${label}: evidence class mismatch`);
  if (evidence.mode !== mode) failures.push(`${label}: mode mismatch`);
  if (evidence.exact_persiangravity_commit !== expectedHead) failures.push(`${label}: PersianGravity Head mismatch`);
  if (evidence.exact_persiangravity_package_sha256 !== expectedPgrPackage) failures.push(`${label}: PersianGravity package mismatch`);
  if (evidence.exact_gravityflow_version !== '3.1.0' || evidence.exact_gravityflow_package_sha256 !== expectedFlowHash) failures.push(`${label}: exact Flow identity mismatch`);
  if (evidence.site_timezone !== 'Asia/Tehran' || evidence.php_default_timezone !== 'UTC') failures.push(`${label}: timezone identity mismatch`);
}
identity(enabled, 'enabled', 'enabled');
identity(disabled, 'disabled', 'disabled');

if (
  fixture.evidence_class !== 'AUTHENTIC_G008_FLOW_SCHEDULE_BRANCH_FIXTURE'
  || fixture.gravityflow_version !== '3.1.0'
  || fixture.site_timezone !== 'Asia/Tehran'
  || fixture.php_default_timezone !== 'UTC'
) {
  failures.push('schedule fixture identity mismatch');
}

const contract = source?.source_contract?.entry_detail_schedule_due_expiration;
const requiredFlags = [
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
];
for (const flag of requiredFlags) {
  if (contract?.[flag] !== true) failures.push(`exact source contract missing ${flag}`);
}

const expectedKeys = ['date', 'date_field', 'delay', 'date_field_empty'];
const enabledMap = new Map((enabled.observations ?? []).map((item) => [item.key, item]));
const disabledMap = new Map((disabled.observations ?? []).map((item) => [item.key, item]));
if (enabledMap.size !== expectedKeys.length || disabledMap.size !== expectedKeys.length) failures.push('schedule observation count mismatch');

for (const key of expectedKeys) {
  const on = enabledMap.get(key);
  const off = disabledMap.get(key);
  if (!on || !off) {
    failures.push(`schedule branch ${key} missing`);
    continue;
  }
  if (JSON.stringify({
    schedule_type: on.schedule_type,
    schedule_timestamp: on.schedule_timestamp,
    step_timestamp: on.step_timestamp,
    date_field_value: on.date_field_value,
    is_queued: on.is_queued,
    expected_display: on.expected_display,
    rendered: on.rendered,
    scheduled_field_count: on.scheduled_field_count,
  }) !== JSON.stringify({
    schedule_type: off.schedule_type,
    schedule_timestamp: off.schedule_timestamp,
    step_timestamp: off.step_timestamp,
    date_field_value: off.date_field_value,
    is_queued: off.is_queued,
    expected_display: off.expected_display,
    rendered: off.rendered,
    scheduled_field_count: off.scheduled_field_count,
  })) {
    failures.push(`schedule branch ${key} changed with module state`);
  }
  if (on.getter_probe?.nested_date_i18n !== 0 || off.getter_probe?.nested_date_i18n !== 0) {
    failures.push(`schedule branch ${key} nested operational getter through date_i18n`);
  }
  if (on.getter_probe?.total !== off.getter_probe?.total) {
    failures.push(`schedule branch ${key} operational getter invocation count changed`);
  }
}

const dateBranch = enabledMap.get('date');
const fieldBranch = enabledMap.get('date_field');
const delayBranch = enabledMap.get('delay');
const emptyBranch = enabledMap.get('date_field_empty');

if (!dateBranch || dateBranch.schedule_type !== 'date' || dateBranch.rendered !== dateBranch.expected_display) {
  failures.push('explicit date schedule branch was not authentically rendered');
}
if (!fieldBranch || fieldBranch.schedule_type !== 'date_field' || fieldBranch.rendered !== fieldBranch.expected_display) {
  failures.push('date_field schedule branch was not authentically rendered');
}
if (!delayBranch || delayBranch.schedule_type !== 'delay' || delayBranch.rendered !== delayBranch.expected_display) {
  failures.push('delay schedule branch was not authentically rendered');
}
if (
  !emptyBranch
  || emptyBranch.schedule_timestamp !== false
  || emptyBranch.is_queued !== false
  || emptyBranch.rendered !== null
  || emptyBranch.scheduled_field_count !== 0
) {
  failures.push('empty date_field schedule native absence semantics were not proven');
}

const result = {
  schema_version: '1.0.0',
  evidence_class: 'G008_FLOW_SCHEDULE_BRANCH_QUALIFICATION_RECONCILIATION',
  exact_persiangravity_commit: expectedHead,
  exact_persiangravity_package_sha256: expectedPgrPackage,
  exact_gravityflow_version: '3.1.0',
  exact_gravityflow_package_sha256: expectedFlowHash,
  source_contract_proven: failures.every((failure) => !failure.startsWith('exact source contract')),
  branches: Object.fromEntries(expectedKeys.map((key) => [key, enabledMap.get(key) ?? null])),
  enabled_disabled_native_equality: failures.every((failure) => !failure.includes('changed with module state')),
  operational_getter_counts_equal: failures.every((failure) => !failure.includes('invocation count')),
  downstream_value_only_seam: 'NONE_IN_EXACT_3_1_0_DISPLAY_QUEUED_STEP_DETAILS',
  disposition: failures.length ? 'NOT_PROVEN' : 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
  failures,
};
fs.writeFileSync(path.join(artifactDir, 'g008-flow-schedule-qualification.json'), `${JSON.stringify(result, null, 2)}\n`);
if (failures.length) throw new Error(`G008 schedule qualification failed: ${failures.join('; ')}`);
console.log('G008_FLOW_SCHEDULE_QUALIFICATION FINAL_NO_ADMISSION_FOR_EXACT_3_1_0');
