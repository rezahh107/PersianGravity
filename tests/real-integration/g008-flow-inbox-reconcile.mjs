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

function dueOperationalState(entries) {
  return entries
    .map((entry) => ({
      id: Number(entry.id),
      workflow_step_timestamp: Number(entry.workflow_step_timestamp ?? 0),
      due_date_enabled: Boolean(entry.due_date_enabled),
      due_date_timestamp: Number(entry.due_date_timestamp),
      due_date_type: String(entry.due_date_type ?? ''),
      due_date_delay_offset: Number(entry.due_date_delay_offset ?? 0),
      due_date_delay_unit: String(entry.due_date_delay_unit ?? ''),
      overdue: Boolean(entry.overdue),
      supports_due_date: Boolean(entry.supports_due_date),
      due_date_highlight_type: String(entry.due_date_highlight_type ?? ''),
      due_date_highlight_color: String(entry.due_date_highlight_color ?? ''),
      scheduled: Boolean(entry.scheduled),
      schedule_timestamp: Number(entry.schedule_timestamp ?? 0),
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

function dueInvocationEvidence(evidence) {
  const raw = evidence?.operational_due_filter_invocations;
  if (!raw || typeof raw !== 'object') return null;
  const byEntry = Object.entries(raw.by_entry ?? {})
    .map(([entryId, count]) => ({ entry_id: Number(entryId), count: Number(count) }))
    .sort((a, b) => a.entry_id - b.entry_id);
  return {
    mode: String(raw.mode ?? ''),
    total: Number(raw.total),
    nested_inbox_presentation: Number(raw.nested_inbox_presentation),
    by_entry: byEntry,
  };
}

function allContractValuesTrue(value) {
  if (typeof value === 'boolean') return value;
  if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
  const values = Object.values(value);
  return values.length > 0 && values.every(allContractValuesTrue);
}

function probeText(probe, key) {
  const lines = new Map();
  for (const occurrence of probe?.[key] ?? []) {
    for (const item of occurrence.source ?? []) lines.set(Number(item.line), String(item.text));
  }
  return [...lines.entries()].sort((a, b) => a[0] - b[0]).map(([line, text]) => `${line}:${text}`).join('\n');
}

function deriveDueDateSourceContract(flowInboxEvidence, sourceEvidence) {
  const probe = flowInboxEvidence?.due_date_source_probe;
  const exactDueContract = flowInboxEvidence?.due_date_source_contract;
  if (!probe || !exactDueContract) return null;
  const task = probeText(probe, 'task_model');
  const step = probeText(probe, 'step');
  const customSchedule = probeText(probe, 'custom_schedule_setting');
  const refs = sourceEvidence?.references?.gravityflow ?? {};
  const inboxFilterRefs = refs.gravityflow_inbox_field_value ?? [];
  const dueHumanRefs = refs.due_date_human_readable ?? [];
  const dueRefs = refs.due_date ?? [];
  const taskFilterLine = inboxFilterRefs
    .filter((ref) => ref.file === 'includes/inbox/models/class-task.php' && ref.operation === 'apply_filters')
    .map((ref) => Number(ref.line))
    .sort((a, b) => a - b)[0] ?? 0;
  const taskDueDisplayLine = dueHumanRefs
    .filter((ref) => ref.file === 'includes/inbox/models/class-task.php')
    .map((ref) => Number(ref.line))
    .filter((line) => line >= 400)
    .sort((a, b) => a - b)[0] ?? 0;

  return {
    exact_source_contract_present_and_proven: allContractValuesTrue(exactDueContract),
    raw_representation_and_sentinel: {
      raw_compare_uses_current_step_timestamp: task.includes("case 'due_date':") && task.includes('$value = $step->get_due_date_timestamp();'),
      raw_no_due_is_zero: task.includes('$value = 0;'),
      display_uses_same_current_step_timestamp: task.includes("case 'due_date_human_readable':") && task.includes("format_date( date( 'Y-m-d H:i:s', $step->get_due_date_timestamp() ), '', true, true )"),
      display_no_due_is_dash: task.includes("$value = '-';"),
      raw_and_display_are_distinct_grid_identities: task.includes("'due_date'                    => array(") && task.includes("'displayKey'   => 'due_date_human_readable'") && task.includes("'due_date_human_readable'     => array("),
      raw_compare_type_is_date: task.includes("'compareType'  => 'date'"),
    },
    utc_instant_semantics: {
      getter_disabled_state_returns_false: step.includes('if ( ! $this->due_date )') && step.includes('return false;'),
      getter_returns_due_timestamp: step.includes('return $due_date_timestamp;'),
      getter_filter_contract_declares_utc_timestamp: step.includes('The current expiration timestamp (UTC).'),
      getter_filter_is_supported_host_seam: step.includes("apply_filters( 'gravityflow_step_due_date_timestamp'"),
    },
    deadline_and_overdue: {
      overdue_reads_same_getter: step.includes('$step_due_date = $this->get_due_date_timestamp();'),
      overdue_compares_epoch_to_time: step.includes('if ( (int) $step_due_date < (int) time() )'),
      inbox_highlight_reads_is_overdue: task.includes('$step->is_overdue()') && task.includes('$step->due_date_highlight_color'),
    },
    workflow_scheduling_trace: {
      due_reference_census_present: dueRefs.length > 0,
      getter_supports_date_date_field_and_delay_modes: step.includes("case 'date':") && step.includes("case 'date_field':") && step.includes("case 'delay':") && step.includes("get_timestamp_delay( 'due_date' )"),
      exact_delay_and_schedule_separation_contract_proven: Boolean(exactDueContract?.authoritative_getter_and_timezone?.delay_mode_starts_from_step_timestamp) && Boolean(exactDueContract?.scheduling_separation?.schedule_validation_reads_schedule_timestamp_not_due_timestamp) && Boolean(exactDueContract?.scheduling_separation?.schedule_getter_uses_schedule_namespace),
      schedule_custom_due_occurrences_are_settings_only: customSchedule.includes("'name'    => 'due_date'") && customSchedule.includes('gravity_flow()->settings_checkbox') && !customSchedule.includes('process_workflow('),
      no_due_reference_is_an_inbox_presentation_filter_mutation: !dueRefs.some((ref) => ref.operation === 'add_filter'),
    },
    presentation_seam: {
      exact_filter_apply_site_present: taskFilterLine > 0,
      raw_due_passes_presentation_filter: Boolean(exactDueContract?.presentation_seam?.raw_due_passes_presentation_filter),
      raw_due_column_precedes_display_column: Boolean(exactDueContract?.presentation_seam?.raw_due_column_precedes_display_column),
      row_values_follow_column_iteration_order: Boolean(exactDueContract?.presentation_seam?.row_values_follow_column_iteration_order),
      display_is_computed_before_presentation_filter: taskDueDisplayLine > 0 && taskFilterLine > taskDueDisplayLine,
      filter_receives_display_form_id_field_id_and_entry: Boolean(flowInboxEvidence?.source_contract?.presentation_seam?.filter_receives_display_form_id_field_id_and_entry),
    },
  };
}

const baselineEntries = canonicalEntries(baseline.entries);
const enabledEntries = canonicalEntries(enabledState.entries);
const disabledEntries = canonicalEntries(disabledState.entries);
const enabledDueOperational = dueOperationalState(enabledState.entries);
const disabledDueOperational = dueOperationalState(disabledState.entries);
const enabledDueInvocations = dueInvocationEvidence(enabledBrowser);
const disabledDueInvocations = dueInvocationEvidence(disabledBrowser);
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
if (!enabledDueInvocations || !disabledDueInvocations) {
  dueFailures.push('request-local operational due-date invocation evidence is missing');
} else {
  if (enabledDueInvocations.mode !== 'enabled' || disabledDueInvocations.mode !== 'disabled') dueFailures.push('due-date invocation evidence mode identity drifted');
  if (!Number.isInteger(enabledDueInvocations.total) || enabledDueInvocations.total <= 0 || !Number.isInteger(disabledDueInvocations.total) || disabledDueInvocations.total <= 0) {
    dueFailures.push('due-date invocation totals are invalid');
  }
  if (enabledDueInvocations.total !== disabledDueInvocations.total) {
    dueFailures.push(`presentation module added operational due-date filter executions: enabled=${enabledDueInvocations.total}, disabled=${disabledDueInvocations.total}`);
  }
  if (JSON.stringify(enabledDueInvocations.by_entry) !== JSON.stringify(disabledDueInvocations.by_entry)) {
    dueFailures.push('per-entry operational due-date filter invocation counts changed with presentation module state');
  }
  if (enabledDueInvocations.nested_inbox_presentation !== 0 || disabledDueInvocations.nested_inbox_presentation !== 0) {
    dueFailures.push(`operational due-date filter re-entered from Inbox presentation seam: ${JSON.stringify({ enabled: enabledDueInvocations, disabled: disabledDueInvocations })}`);
  }
}
if (JSON.stringify(enabledDueOperational) !== JSON.stringify(disabledDueOperational)) dueFailures.push('due-date timing/support/highlight/schedule state changed across module modes');
if (!enabledDueOperational.some((entry) => entry.due_date_enabled && entry.due_date_type === 'delay' && entry.due_date_delay_offset === 1 && entry.due_date_delay_unit === 'days' && entry.workflow_step_timestamp > 0 && entry.supports_due_date && !entry.scheduled && entry.schedule_timestamp === 0)) {
  dueFailures.push('deterministic delay step-timing/support/schedule fixture is missing or drifted');
}

const expectedDue = baselineEntries.map((entry) => ({ id: entry.id, due_date_timestamp: entry.due_date_timestamp, overdue: entry.overdue }));
const enabledDue = enabledEntries.map((entry) => ({ id: entry.id, due_date_timestamp: entry.due_date_timestamp, overdue: entry.overdue }));
const disabledDue = disabledEntries.map((entry) => ({ id: entry.id, due_date_timestamp: entry.due_date_timestamp, overdue: entry.overdue }));
if (JSON.stringify(enabledDue) !== JSON.stringify(expectedDue) || JSON.stringify(disabledDue) !== JSON.stringify(expectedDue)) {
  dueFailures.push('authoritative due-date timestamp or overdue classification changed across module modes');
}
if (!baselineEntries.some((entry) => entry.due_date_timestamp === 0 && entry.due_date_enabled === false)) dueFailures.push('no-due-date sentinel fixture is missing');
if (!baselineEntries.some((entry) => entry.due_date_timestamp > 0 && entry.overdue === true)) dueFailures.push('overdue due-date fixture is missing');
if (!baselineEntries.some((entry) => entry.due_date_timestamp > 0 && entry.overdue === false)) dueFailures.push('future due-date fixture is missing');

const flowInboxEvidence = source?.classifications?.g008?.flow_inbox;
const semanticProbe = flowInboxEvidence?.source_semantics_probe;
const sourceContract = flowInboxEvidence?.source_contract;
const dueSourceContract = deriveDueDateSourceContract(flowInboxEvidence, source);
if (!semanticProbe) baseFailures.push('exact-package source semantic probe is missing');
if (!sourceContract) {
  baseFailures.push('exact-package source/timezone contract is missing');
} else if (!allContractValuesTrue(sourceContract)) {
  baseFailures.push('existing Inbox source/timezone contract contains an unproven requirement');
}
if (!dueSourceContract) {
  dueFailures.push('exact-package due-date source/deadline provenance is missing');
} else if (!allContractValuesTrue(dueSourceContract)) {
  dueFailures.push(`exact-package due-date source/deadline/scheduling contract contains an unproven requirement: ${JSON.stringify(dueSourceContract)}`);
}

const failures = [...baseFailures, ...dueFailures];
const result = {
  schema_version: '1.5.0',
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
  due_date_source_contract: dueSourceContract,
  due_date_contract_proven: Boolean(dueSourceContract && allContractValuesTrue(dueSourceContract)),
  operational_due_filter_invocations: {
    enabled: enabledDueInvocations,
    disabled: disabledDueInvocations,
    equal_total: Boolean(enabledDueInvocations && disabledDueInvocations && enabledDueInvocations.total === disabledDueInvocations.total),
    added_by_presentation: enabledDueInvocations && disabledDueInvocations ? enabledDueInvocations.total - disabledDueInvocations.total : null,
  },
  presentation_isolation: {
    raw_state_equal: JSON.stringify(enabledEntries) === JSON.stringify(disabledEntries),
    due_timestamp_and_overdue_equal: JSON.stringify(enabledDue) === JSON.stringify(disabledDue),
    due_timing_support_highlight_schedule_state_equal: JSON.stringify(enabledDueOperational) === JSON.stringify(disabledDueOperational),
    ag_grid_compare_values_equal: JSON.stringify(rawRows(enabledBrowser)) === JSON.stringify(rawRows(disabledBrowser)),
    query_ids_equal: JSON.stringify(enabledState.query_ids) === JSON.stringify(disabledState.query_ids),
    sort_equal: JSON.stringify(enabledBrowser.sort) === JSON.stringify(disabledBrowser.sort),
    filter_equal: JSON.stringify(enabledBrowser.quick_filter) === JSON.stringify(disabledBrowser.quick_filter),
    operational_due_filter_invocation_count_equal: Boolean(enabledDueInvocations && disabledDueInvocations && enabledDueInvocations.total === disabledDueInvocations.total),
    zero_nested_operational_reentry: Boolean(enabledDueInvocations && disabledDueInvocations && enabledDueInvocations.nested_inbox_presentation === 0 && disabledDueInvocations.nested_inbox_presentation === 0),
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
