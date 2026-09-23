import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export class EvidenceReconciliationError extends Error {
  constructor(errors) {
    super(`Qualification evidence reconciliation failed:\n- ${errors.join('\n- ')}`);
    this.name = 'EvidenceReconciliationError';
    this.errors = errors;
  }
}

function productKey(name) {
  return String(name).toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function isObject(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function exactIdentityErrors(evidence, expectedIdentity, label) {
  const errors = [];
  if (evidence.exact_persiangravity_commit !== expectedIdentity.head) {
    errors.push(`${label}: PersianGravity source commit mismatch.`);
  }
  if (Object.hasOwn(evidence, 'exact_persiangravity_tree') && evidence.exact_persiangravity_tree !== expectedIdentity.tree) {
    errors.push(`${label}: PersianGravity source tree mismatch.`);
  }
  const packageSha = evidence.exact_persiangravity_package_sha256
    ?? evidence.exact_package_sha256?.persiangravity
    ?? null;
  if (packageSha !== expectedIdentity.persiangravityPackageSha256) {
    errors.push(`${label}: PersianGravity package SHA-256 mismatch.`);
  }
  return errors;
}

function validateRegistryRuntimeRequirements(registry) {
  const requirements = registry.native_pass_runtime_requirements;
  if (!isObject(requirements) || !isObject(requirements.profiles)) {
    throw new EvidenceReconciliationError(['G-009 registry is missing native_pass_runtime_requirements.profiles.']);
  }
  const requiredProfileNames = ['ltr', 'rtl'];
  const profileNames = Object.keys(requirements.profiles).sort();
  if (JSON.stringify(profileNames) !== JSON.stringify(requiredProfileNames)) {
    throw new EvidenceReconciliationError(['G-009 registry native-pass requirements must declare exactly rtl and ltr profiles.']);
  }
  for (const profile of requiredProfileNames) {
    const scenarios = requirements.profiles[profile];
    if (!Array.isArray(scenarios) || scenarios.length === 0) {
      throw new EvidenceReconciliationError([`G-009 registry profile ${profile} has no required runtime scenarios.`]);
    }
    for (const scenario of scenarios) {
      if (!Number.isInteger(scenario?.width) || !Number.isInteger(scenario?.height) || scenario.width <= 0 || scenario.height <= 0) {
        throw new EvidenceReconciliationError([`G-009 registry profile ${profile} has an invalid viewport requirement.`]);
      }
    }
  }
  return requirements.profiles;
}

function validateG009(registry, artifacts, expectedIdentity, perksSourceEvidence) {
  const errors = [];
  const claims = [];

  for (const product of registry.products ?? []) {
    for (const surface of product.surfaces ?? []) {
      if (['NATIVE_PASS', 'ADAPTER_REQUIRED_AND_VERIFIED'].includes(surface.evidence_state)) claims.push({ product, surface });
    }
  }

  const perksClaims = claims.filter(({ product }) => ['Gravity Perks', 'GP File Upload Pro', 'GP Advanced Select'].includes(product.product));
  if (perksClaims.length > 0) {
    if (!isObject(perksSourceEvidence)) {
      errors.push('G-009 Gravity Perks exact installed source probe is missing.');
    } else {
      if (perksSourceEvidence.evidence_class !== 'G009_EXACT_INSTALLED_GRAVITY_PERKS_SOURCE_PROBE') {
        errors.push('G-009 Gravity Perks source probe evidence class mismatch.');
      }
      if (perksSourceEvidence.exact_persiangravity_commit !== expectedIdentity.head) {
        errors.push('G-009 Gravity Perks source probe PersianGravity Head mismatch.');
      }
      for (const { product, surface } of perksClaims) {
        const key = productKey(product.product);
        if (perksSourceEvidence.exact_versions?.[key] !== product.version) {
          errors.push(`G-009 ${surface.id}: exact installed source probe version mismatch.`);
        }
        if (perksSourceEvidence.exact_package_sha256?.[key] !== product.package_sha256) {
          errors.push(`G-009 ${surface.id}: exact installed source probe package SHA-256 mismatch.`);
        }
        if (surface.id === 'gp-file-upload-pro.frontend') {
          const source = perksSourceEvidence.observations?.file_upload_pro;
          for (const field of ['wp_localize_script_gpfup_constants_line', 'gettext_select_files_line', 'gettext_drop_files_here_line', 'gettext_or_line']) {
            if (!Number.isInteger(source?.[field]) || source[field] <= 0) {
              errors.push(`G-009 ${surface.id}: exact PHP gettext/wp_localize_script source contract is incomplete (${field}).`);
            }
          }
        }
        if (surface.id === 'gp-advanced-select.tom-select') {
          const source = perksSourceEvidence.observations?.advanced_select;
          for (const field of ['exact_style_handle_line', 'exact_style_asset_line']) {
            if (!Number.isInteger(source?.[field]) || source[field] <= 0) {
              errors.push(`G-009 ${surface.id}: exact vendor handle/style source contract is incomplete (${field}).`);
            }
          }
        }
      }
    }
  }

  const requirements = validateRegistryRuntimeRequirements(registry);
  for (const [profile, requiredViewports] of Object.entries(requirements)) {
    const evidence = artifacts[profile];
    if (!isObject(evidence)) {
      errors.push(`G-009 ${profile}: evidence artifact is missing.`);
      continue;
    }
    if (evidence.program !== 'G-009' || evidence.profile !== profile) {
      errors.push(`G-009 ${profile}: artifact program/profile identity mismatch.`);
    }
    errors.push(...exactIdentityErrors(evidence, expectedIdentity, `G-009 ${profile}`));

    for (const { product, surface } of claims) {
      const key = productKey(product.product);
      if (evidence.vendor_versions?.[key] !== product.version) {
        errors.push(`G-009 ${profile} ${surface.id}: ${product.product} version identity mismatch.`);
      }
      if (evidence.exact_package_sha256?.[key] !== product.package_sha256) {
        errors.push(`G-009 ${profile} ${surface.id}: ${product.product} package SHA-256 mismatch.`);
      }

      for (const viewport of requiredViewports) {
        const matches = (evidence.results ?? []).filter((item) => (
          item?.id === surface.id
          && item?.observed?.viewport?.width === viewport.width
          && item?.observed?.viewport?.height === viewport.height
        ));
        const scenario = `${profile} ${viewport.width}x${viewport.height}`;
        if (matches.length !== 1) {
          errors.push(`G-009 ${surface.id}: required scenario ${scenario} expected exactly one result, found ${matches.length}.`);
          continue;
        }
        if (matches[0].evidence_state !== surface.evidence_state) {
          errors.push(`G-009 ${surface.id}: required scenario ${scenario} expected ${surface.evidence_state}, found ${matches[0].evidence_state ?? 'MISSING_STATE'}.`);
        }
      }
    }
  }

  return { errors, claims: claims.length };
}

export function deriveG008SourceRequirements(surface) {
  const seam = String(surface.presentation_seam ?? '').match(/\b(gravityflow_[a-z0-9_]+)\b/)?.[1] ?? null;
  const rawNeedle = String(surface.id ?? '').split('.').at(-1)?.replaceAll('-', '_') ?? null;
  const humanReadable = [...String(surface.raw_source ?? '').matchAll(/\b([a-z0-9_]+_human_readable)\b/g)].map((match) => match[1]);
  const needles = [...new Set([rawNeedle, ...humanReadable].filter(Boolean))];
  if (!seam || needles.length === 0) return null;
  return { seam, needles };
}

function validateG008RuntimeClaim(product, surface, runtimeEvidenceByFile, expectedIdentity) {
  const errors = [];
  const evidenceClasses = {
    'g008-flow-inbox-admission.json': 'G008_GRAVITY_FLOW_INBOX_ADMISSION_RECONCILIATION',
    'g008-flow-status-admission.json': 'G008_GRAVITY_FLOW_STATUS_ADMISSION_RECONCILIATION',
    'g008-entry-detail-admission.json': 'G008_GRAVITY_FLOW_ENTRY_DETAIL_ADMISSION_RECONCILIATION',
  };
  const evidenceFile = surface.runtime_evidence;
  const expectedClass = evidenceClasses[evidenceFile];
  if (!expectedClass) {
    return [`G-008 ${surface.id}: unsupported runtime evidence reference ${evidenceFile ?? 'MISSING'}.`];
  }
  const runtimeEvidence = runtimeEvidenceByFile?.[evidenceFile];
  if (!isObject(runtimeEvidence)) {
    return [`G-008 ${surface.id}: required runtime admission evidence is missing.`];
  }
  if (runtimeEvidence.evidence_class !== expectedClass) {
    errors.push(`G-008 ${surface.id}: runtime admission evidence class mismatch.`);
  }
  if (runtimeEvidence.hard_gate_result !== 'PASS') {
    errors.push(`G-008 ${surface.id}: runtime admission hard gate is not PASS.`);
  }
  errors.push(...exactIdentityErrors(runtimeEvidence, expectedIdentity, `G-008 ${surface.id} runtime admission`));
  if (runtimeEvidence.exact_gravityflow_version !== product.version) {
    errors.push(`G-008 ${surface.id}: runtime Gravity Flow version identity mismatch.`);
  }
  if (runtimeEvidence.exact_gravityflow_package_sha256 !== product.package_sha256) {
    errors.push(`G-008 ${surface.id}: runtime Gravity Flow package SHA-256 mismatch.`);
  }
  if (runtimeEvidence.surfaces?.[surface.id] !== 'ADMITTED_VERIFIED') {
    errors.push(`G-008 ${surface.id}: runtime evidence did not admit the committed surface claim.`);
  }
  if (evidenceFile === 'g008-entry-detail-admission.json') {
    if (
      runtimeEvidence.source_contract_proven !== true
      || runtimeEvidence.native_time_preserved !== true
      || runtimeEvidence.unrelated_date_formatting_unchanged !== true
      || runtimeEvidence.raw_db_gfapi_rest_equal !== true
      || runtimeEvidence.workflow_deadline_expiration_state_equal !== true
      || runtimeEvidence.operational_getter_counts_equal !== true
      || runtimeEvidence.zero_nested_operational_reentry !== true
      || runtimeEvidence.csv_export_isolated !== true
      || runtimeEvidence.repeated_rendering_deterministic !== true
      || runtimeEvidence.marker_leak_free !== true
      || runtimeEvidence.production_browser_enabled_mode !== 'enabled'
      || runtimeEvidence.production_browser_disabled_mode !== 'disabled'
    ) {
      errors.push(`G-008 ${surface.id}: Entry Detail production admission contract is incomplete.`);
    }
  }
  return errors;
}

function allEvidenceFlagsTrue(value) {
  if (typeof value === 'boolean') return value;
  if (!isObject(value)) return false;
  const values = Object.values(value);
  return values.length > 0 && values.every(allEvidenceFlagsTrue);
}

const residualSourceContractBySurface = {
  'gravityflow.status.due-date': 'status_due_date',
  'gravityflow.entry-detail.schedule': 'entry_detail_schedule_due_expiration',
  'gravityflow.timeline-history': 'timeline_history',
  'gravityflow.print': 'print',
};

const residualRequiredSourceFlags = {
  status_due_date: [
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
  ],
  entry_detail_schedule_due_expiration: [
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
  ],
  timeline_history: [
    'header_formats_note_date_directly',
    'note_body_is_separate_escaped_content',
    'timeline_reads_gravityforms_notes',
    'timeline_inserts_initial_entry_event',
    'initial_event_uses_entry_date_created',
    'timeline_order_is_host_owned',
    'timeline_full_array_filter_runs_after_host_reverse',
    'only_timeline_data_filter_mutates_note_array',
    'common_text_timeline_reuses_note_dates',
    'gravityforms_notes_are_persisted_in_utc',
    'gravityforms_notes_return_raw_date_created',
  ],
  print: [
    'reuses_entry_detail_grid',
    'optional_timeline_reuses_entry_detail_timeline',
    'no_print_specific_date_formatter',
    'print_style_hook_is_not_date_seam',
    'workflow_sidebar_not_rendered_by_print',
  ],
};

function validateRequiredBooleanFlags(contract, requiredFlags, label) {
  const errors = [];
  if (!isObject(contract) || Object.keys(contract).length === 0) {
    return [`${label}: source contract is missing or empty.`];
  }
  for (const flag of requiredFlags ?? []) {
    if (!Object.hasOwn(contract, flag)) {
      errors.push(`${label}: required source flag ${flag} is missing.`);
      continue;
    }
    if (typeof contract[flag] !== 'boolean') {
      errors.push(`${label}: required source flag ${flag} must be boolean.`);
      continue;
    }
    if (contract[flag] !== true) {
      errors.push(`${label}: required source flag ${flag} is false.`);
    }
  }
  if (!allEvidenceFlagsTrue(contract)) {
    errors.push(`${label}: source contract contains an empty, non-boolean, or false evidence value.`);
  }
  return errors;
}

const residualBrowserTargets = [
  'gravityflow.timeline-history',
  'gravityflow.print',
];

function exactStringSet(values, expected) {
  if (!Array.isArray(values)) return false;
  const actual = [...values].sort();
  const wanted = [...expected].sort();
  return JSON.stringify(actual) === JSON.stringify(wanted);
}

function browserIdentityErrors(evidence, expectedMode, expectedClass, product, expectedIdentity, label) {
  const errors = [];
  if (!isObject(evidence)) return [`${label}: browser evidence is missing.`];
  if (evidence.evidence_class !== expectedClass) errors.push(`${label}: evidence class mismatch.`);
  if (evidence.mode !== expectedMode) errors.push(`${label}: mode must be ${expectedMode}.`);
  errors.push(...exactIdentityErrors(evidence, expectedIdentity, label));
  if (evidence.exact_gravityflow_version !== product.version) errors.push(`${label}: Gravity Flow version mismatch.`);
  if (evidence.exact_gravityflow_package_sha256 !== product.package_sha256) errors.push(`${label}: Gravity Flow package SHA-256 mismatch.`);
  if (evidence.site_timezone !== 'Asia/Tehran') errors.push(`${label}: site timezone identity mismatch.`);
  if (evidence.php_default_timezone !== 'UTC') errors.push(`${label}: PHP default timezone identity mismatch.`);
  return errors;
}

function validateResidualBrowserPair(product, surface, enabled, disabled, expectedIdentity) {
  const errors = [];
  errors.push(...browserIdentityErrors(
    enabled,
    'enabled',
    'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_NO_ADMISSION_BROWSER',
    product,
    expectedIdentity,
    `G-008 ${surface.id} enabled residual browser`
  ));
  errors.push(...browserIdentityErrors(
    disabled,
    'disabled',
    'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_NO_ADMISSION_BROWSER',
    product,
    expectedIdentity,
    `G-008 ${surface.id} disabled residual browser`
  ));
  if (!isObject(enabled) || !isObject(disabled)) return errors;

  for (const [label, evidence] of [['enabled', enabled], ['disabled', disabled]]) {
    if (!isObject(evidence.dispositions) || !exactStringSet(Object.keys(evidence.dispositions), residualBrowserTargets)) {
      errors.push(`G-008 ${surface.id} ${label}: residual target identity set is missing, duplicated, or unexpected.`);
      continue;
    }
    if (evidence.dispositions[surface.id] !== 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0') {
      errors.push(`G-008 ${surface.id} ${label}: residual target disposition is missing or wrong.`);
    }
    if (!isObject(evidence.entry_detail) || typeof evidence.entry_detail.url !== 'string' || evidence.entry_detail.url.length === 0) {
      errors.push(`G-008 ${surface.id} ${label}: Entry Detail observation is empty.`);
    }
    if (!isObject(evidence.print) || typeof evidence.print.url !== 'string' || evidence.print.url.length === 0) {
      errors.push(`G-008 ${surface.id} ${label}: Print observation is empty.`);
    }
    if (surface.id === 'gravityflow.timeline-history') {
      if (!Array.isArray(evidence.entry_detail.timeline_native) || evidence.entry_detail.timeline_native.length === 0) {
        errors.push(`G-008 ${surface.id} ${label}: required Timeline browser observations are empty.`);
      }
    } else if (surface.id === 'gravityflow.print') {
      if (!Array.isArray(evidence.print.timeline_native) || evidence.print.timeline_native.length === 0) {
        errors.push(`G-008 ${surface.id} ${label}: required Print Timeline observations are empty.`);
      }
    }
  }
  return errors;
}

function validateStatusDueBrowserPair(product, surface, enabled, disabled, expectedIdentity) {
  const errors = [];
  errors.push(...browserIdentityErrors(
    enabled,
    'enabled',
    'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER',
    product,
    expectedIdentity,
    `G-008 ${surface.id} enabled Status browser`
  ));
  errors.push(...browserIdentityErrors(
    disabled,
    'disabled',
    'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER',
    product,
    expectedIdentity,
    `G-008 ${surface.id} disabled Status browser`
  ));
  if (!isObject(enabled) || !isObject(disabled)) return errors;

  for (const [label, evidence] of [['enabled', enabled], ['disabled', disabled]]) {
    if (!Array.isArray(evidence.rows) || evidence.rows.length === 0 || evidence.rows.some((row) => typeof row?.due_date !== 'string')) {
      errors.push(`G-008 ${surface.id} ${label}: Status due-date browser observations are empty or malformed.`);
    }
    if (
      evidence.residual_no_admission?.surface !== surface.id
      || evidence.residual_no_admission?.disposition !== 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'
      || evidence.residual_no_admission?.enabled_and_disabled_expect_native !== true
    ) {
      errors.push(`G-008 ${surface.id} ${label}: Status residual target identity/disposition is missing or wrong.`);
    }
  }
  return errors;
}

function validateG008FinalNoAdmission(
  product,
  surface,
  residualSourceEvidence,
  residualRuntimeEvidence,
  residualBrowserEnabledEvidence,
  residualBrowserDisabledEvidence,
  statusBrowserEnabledEvidence,
  statusBrowserDisabledEvidence,
  scheduleQualificationEvidence,
  timelinePrintQualificationEvidence,
  expectedIdentity
) {
  const errors = [];
  if (surface.support_state !== 'NOT_PROVEN') {
    errors.push(`G-008 ${surface.id}: FINAL_NO_ADMISSION must retain support_state NOT_PROVEN.`);
  }
  if (surface.adapter_identity !== null) {
    errors.push(`G-008 ${surface.id}: FINAL_NO_ADMISSION must not name a production adapter.`);
  }
  const allowedNoAdmissionEvidence = surface.id === 'gravityflow.entry-detail.schedule'
    ? 'g008-flow-schedule-qualification.json'
    : 'g008-flow-residual-no-admission.json';
  if (surface.runtime_evidence !== allowedNoAdmissionEvidence) {
    errors.push(`G-008 ${surface.id}: FINAL_NO_ADMISSION runtime evidence reference mismatch.`);
  }
  if (!isObject(residualSourceEvidence) || residualSourceEvidence.evidence_class !== 'G008_RESIDUAL_EXACT_SOURCE_PROBE') {
    errors.push(`G-008 ${surface.id}: exact residual source evidence is missing.`);
  } else {
    if (residualSourceEvidence.exact?.pgr_sha !== expectedIdentity.head) {
      errors.push(`G-008 ${surface.id}: residual source PersianGravity Head mismatch.`);
    }
    if (residualSourceEvidence.exact?.version !== product.version) {
      errors.push(`G-008 ${surface.id}: residual source Gravity Flow version mismatch.`);
    }
    if (residualSourceEvidence.exact?.sha256 !== product.package_sha256) {
      errors.push(`G-008 ${surface.id}: residual source Gravity Flow package SHA-256 mismatch.`);
    }
    const contractKey = residualSourceContractBySurface[surface.id];
    const targetContract = contractKey ? residualSourceEvidence.source_contract?.[contractKey] : null;
    if (!contractKey) {
      errors.push(`G-008 ${surface.id}: residual source contract identity is not declared.`);
    } else {
      errors.push(...validateRequiredBooleanFlags(
        targetContract,
        residualRequiredSourceFlags[contractKey],
        `G-008 ${surface.id}`
      ));
    }
  }
  if (surface.id === 'gravityflow.status.due-date') {
    errors.push(...validateStatusDueBrowserPair(
      product,
      surface,
      statusBrowserEnabledEvidence,
      statusBrowserDisabledEvidence,
      expectedIdentity
    ));
  } else if (surface.id === 'gravityflow.entry-detail.schedule') {
    if (!isObject(scheduleQualificationEvidence)) {
      errors.push(`G-008 ${surface.id}: schedule qualification evidence is missing.`);
    } else {
      if (scheduleQualificationEvidence.evidence_class !== 'G008_FLOW_SCHEDULE_BRANCH_QUALIFICATION_RECONCILIATION') {
        errors.push(`G-008 ${surface.id}: schedule evidence class mismatch.`);
      }
      errors.push(...exactIdentityErrors(scheduleQualificationEvidence, expectedIdentity, `G-008 ${surface.id} schedule qualification`));
      if (
        scheduleQualificationEvidence.exact_gravityflow_version !== product.version
        || scheduleQualificationEvidence.exact_gravityflow_package_sha256 !== product.package_sha256
      ) {
        errors.push(`G-008 ${surface.id}: schedule exact Gravity Flow identity mismatch.`);
      }
      if (
        scheduleQualificationEvidence.source_contract_proven !== true
        || scheduleQualificationEvidence.enabled_disabled_native_equality !== true
        || scheduleQualificationEvidence.operational_getter_counts_equal !== true
        || scheduleQualificationEvidence.disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
      ) {
        errors.push(`G-008 ${surface.id}: schedule runtime qualification is incomplete.`);
      }
    }
  } else {
    errors.push(...validateResidualBrowserPair(
      product,
      surface,
      residualBrowserEnabledEvidence,
      residualBrowserDisabledEvidence,
      expectedIdentity
    ));
    if (!isObject(timelinePrintQualificationEvidence)) {
      errors.push(`G-008 ${surface.id}: Timeline/Print qualification evidence is missing.`);
    } else {
      if (timelinePrintQualificationEvidence.evidence_class !== 'G008_TIMELINE_PRINT_QUALIFICATION_RECONCILIATION') {
        errors.push(`G-008 ${surface.id}: Timeline/Print evidence class mismatch.`);
      }
      errors.push(...exactIdentityErrors(timelinePrintQualificationEvidence, expectedIdentity, `G-008 ${surface.id} Timeline/Print qualification`));
      if (
        timelinePrintQualificationEvidence.exact_gravityflow_version !== product.version
        || timelinePrintQualificationEvidence.exact_gravityflow_package_sha256 !== product.package_sha256
      ) {
        errors.push(`G-008 ${surface.id}: Timeline/Print exact Gravity Flow identity mismatch.`);
      }
      if (surface.id === 'gravityflow.timeline-history') {
        if (
          timelinePrintQualificationEvidence.timeline?.initial_entry_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
          || timelinePrintQualificationEvidence.timeline?.stored_note_event_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
          || timelinePrintQualificationEvidence.timeline?.storage_unchanged !== true
          || timelinePrintQualificationEvidence.timeline?.ids_order_bodies_unchanged !== true
          || timelinePrintQualificationEvidence.timeline?.separate_display_property_consumed !== false
          || timelinePrintQualificationEvidence.timeline?.date_created_representation_consumed_by_renderer !== true
        ) {
          errors.push(`G-008 ${surface.id}: Timeline runtime qualification is incomplete.`);
        }
      } else if (surface.id === 'gravityflow.print') {
        if (
          timelinePrintQualificationEvidence.print?.independent_date_seam_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
          || timelinePrintQualificationEvidence.print?.stored_note_event_propagation_disposition !== 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0'
          || timelinePrintQualificationEvidence.print?.workflow_sidebar_due_schedule_expiration !== 'ABSENT_FROM_PRINT_RENDER_PATH'
        ) {
          errors.push(`G-008 ${surface.id}: Print runtime qualification is incomplete.`);
        }
      }
    }
  }

  if (!isObject(residualRuntimeEvidence) || residualRuntimeEvidence.evidence_class !== 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION') {
    errors.push(`G-008 ${surface.id}: residual enabled/disabled runtime reconciliation evidence is missing.`);
  } else {
    if (residualRuntimeEvidence.status !== 'PASS') {
      errors.push(`G-008 ${surface.id}: residual runtime reconciliation is not PASS.`);
    }
    if (residualRuntimeEvidence.source_contract_proven !== true) {
      errors.push(`G-008 ${surface.id}: residual runtime reconciliation does not declare source_contract_proven=true.`);
    }
    if (
      residualRuntimeEvidence.browser_modes?.enabled !== 'enabled'
      || residualRuntimeEvidence.browser_modes?.disabled !== 'disabled'
    ) {
      errors.push(`G-008 ${surface.id}: residual runtime browser mode identity is missing or duplicated.`);
    }
    if (residualRuntimeEvidence.site_timezone !== 'Asia/Tehran' || residualRuntimeEvidence.php_default_timezone !== 'UTC') {
      errors.push(`G-008 ${surface.id}: residual runtime timezone identity mismatch.`);
    }
    if (residualRuntimeEvidence.exact_persiangravity_commit !== expectedIdentity.head) {
      errors.push(`G-008 ${surface.id}: residual runtime PersianGravity Head mismatch.`);
    }
    if (residualRuntimeEvidence.exact_persiangravity_package_sha256 !== expectedIdentity.persiangravityPackageSha256) {
      errors.push(`G-008 ${surface.id}: residual runtime PersianGravity package SHA-256 mismatch.`);
    }
    if (residualRuntimeEvidence.exact_gravityflow_version !== product.version || residualRuntimeEvidence.exact_gravityflow_package_sha256 !== product.package_sha256) {
      errors.push(`G-008 ${surface.id}: residual runtime exact Gravity Flow identity mismatch.`);
    }
    if (residualRuntimeEvidence.surfaces?.[surface.id] !== 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0') {
      errors.push(`G-008 ${surface.id}: residual runtime evidence does not close the committed no-admission claim.`);
    }
  }
  return errors;
}

function validateG008(
  registry,
  sourceEvidence,
  runtimeEvidenceByFile,
  residualSourceEvidence,
  residualRuntimeEvidence,
  residualBrowserEnabledEvidence,
  residualBrowserDisabledEvidence,
  statusBrowserEnabledEvidence,
  statusBrowserDisabledEvidence,
  scheduleQualificationEvidence,
  timelinePrintQualificationEvidence,
  expectedIdentity
) {
  const errors = [];
  const sourceClaims = [];
  const runtimeClaims = [];
  const finalNoAdmissionClaims = [];

  if (!isObject(sourceEvidence)) {
    return { errors: ['G-008 source-discovery evidence artifact is missing.'], sourceClaims: 0, runtimeClaims: 0, finalNoAdmissionClaims: 0 };
  }
  if (sourceEvidence.evidence_class !== 'EXACT_INSTALLED_VENDOR_SOURCE_DISCOVERY') {
    errors.push('G-008 source-discovery evidence class mismatch.');
  }
  errors.push(...exactIdentityErrors(sourceEvidence, expectedIdentity, 'G-008 source discovery'));

  for (const product of registry.products ?? []) {
    for (const surface of product.surfaces ?? []) {
      const sourceClaim = surface.discovery_state === 'SOURCE_PROVEN'
        || Boolean(surface.runtime_evidence);
      if (!sourceClaim) continue;

      sourceClaims.push({ product, surface });
      const key = productKey(product.product);
      if (sourceEvidence.exact_versions?.[key] !== product.version) {
        errors.push(`G-008 ${surface.id}: ${product.product} version identity mismatch.`);
      }
      if (sourceEvidence.exact_package_sha256?.[key] !== product.package_sha256) {
        errors.push(`G-008 ${surface.id}: ${product.product} package SHA-256 mismatch.`);
      }

      if (surface.exact_version_disposition === 'FINAL_NO_ADMISSION') {
        finalNoAdmissionClaims.push({ product, surface });
        errors.push(...validateG008FinalNoAdmission(
          product,
          surface,
          residualSourceEvidence,
          residualRuntimeEvidence,
          residualBrowserEnabledEvidence,
          residualBrowserDisabledEvidence,
          statusBrowserEnabledEvidence,
          statusBrowserDisabledEvidence,
          scheduleQualificationEvidence,
          timelinePrintQualificationEvidence,
          expectedIdentity
        ));
        continue;
      }

      if (surface.runtime_evidence !== 'g008-entry-detail-admission.json') {
        const requirements = deriveG008SourceRequirements(surface);
        if (!requirements) {
          errors.push(`G-008 ${surface.id}: committed source-backed claim does not expose a derivable source seam/reference contract.`);
          continue;
        }
        const refs = sourceEvidence.references?.[key] ?? {};
        const seamRefs = refs[requirements.seam];
        if (!Array.isArray(seamRefs) || !seamRefs.some((ref) => ref?.operation === 'apply_filters')) {
          errors.push(`G-008 ${surface.id}: required apply_filters seam ${requirements.seam} is missing from exact source discovery.`);
        }
        for (const needle of requirements.needles) {
          if (!Array.isArray(refs[needle]) || refs[needle].length === 0) {
            errors.push(`G-008 ${surface.id}: required source reference ${needle} is missing from exact source discovery.`);
          }
        }
      }

      if (surface.support_state === 'ADMITTED_VERIFIED' && surface.runtime_evidence) {
        runtimeClaims.push({ product, surface });
        errors.push(...validateG008RuntimeClaim(product, surface, runtimeEvidenceByFile, expectedIdentity));
      }
    }
  }

  return { errors, sourceClaims: sourceClaims.length, runtimeClaims: runtimeClaims.length, finalNoAdmissionClaims: finalNoAdmissionClaims.length };
}

export function reconcileQualificationEvidence({
  g009Registry,
  g008Registry,
  g009RtlEvidence,
  g009LtrEvidence,
  g009PerksSourceEvidence,
  sourceDiscoveryEvidence,
  g008FlowInboxAdmissionEvidence,
  g008FlowStatusAdmissionEvidence,
  g008EntryDetailAdmissionEvidence,
  g008ResidualSourceEvidence,
  g008ResidualNoAdmissionEvidence,
  g008ResidualBrowserEnabledEvidence,
  g008ResidualBrowserDisabledEvidence,
  g008FlowStatusBrowserEnabledEvidence,
  g008FlowStatusBrowserDisabledEvidence,
  g008FlowScheduleQualificationEvidence,
  g008TimelinePrintQualificationEvidence,
  expectedIdentity,
}) {
  if (!/^[a-f0-9]{40}$/.test(expectedIdentity?.head ?? '')) {
    throw new EvidenceReconciliationError(['Expected PersianGravity Head SHA is missing or invalid.']);
  }
  if (!/^[a-f0-9]{40}$/.test(expectedIdentity?.tree ?? '')) {
    throw new EvidenceReconciliationError(['Expected PersianGravity tree SHA is missing or invalid.']);
  }
  if (!/^[a-f0-9]{64}$/.test(expectedIdentity?.persiangravityPackageSha256 ?? '')) {
    throw new EvidenceReconciliationError(['Expected PersianGravity package SHA-256 is missing or invalid.']);
  }

  const g009 = validateG009(g009Registry, { rtl: g009RtlEvidence, ltr: g009LtrEvidence }, expectedIdentity, g009PerksSourceEvidence);
  const g008 = validateG008(
    g008Registry,
    sourceDiscoveryEvidence,
    {
      'g008-flow-inbox-admission.json': g008FlowInboxAdmissionEvidence,
      'g008-flow-status-admission.json': g008FlowStatusAdmissionEvidence,
      'g008-entry-detail-admission.json': g008EntryDetailAdmissionEvidence,
    },
    g008ResidualSourceEvidence,
    g008ResidualNoAdmissionEvidence,
    g008ResidualBrowserEnabledEvidence,
    g008ResidualBrowserDisabledEvidence,
    g008FlowStatusBrowserEnabledEvidence,
    g008FlowStatusBrowserDisabledEvidence,
    g008FlowScheduleQualificationEvidence,
    g008TimelinePrintQualificationEvidence,
    expectedIdentity
  );
  const errors = [...g009.errors, ...g008.errors];
  if (errors.length > 0) throw new EvidenceReconciliationError(errors);

  return {
    status: 'PASS',
    g009_runtime_claims_reconciled: g009.claims,
    g008_source_proven_claims_reconciled: g008.sourceClaims,
    g008_runtime_admitted_claims_reconciled: g008.runtimeClaims,
    g008_final_no_admission_claims_reconciled: g008.finalNoAdmissionClaims,
  };
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function readJsonIfPresent(file) {
  return fs.existsSync(file) ? readJson(file) : null;
}

function runCli() {
  const artifactDir = process.env.WU008_ARTIFACT_DIR;
  if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');
  const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
  const summary = reconcileQualificationEvidence({
    g009Registry: readJson(path.join(repoRoot, 'tools/compatibility/g009-surfaces.json')),
    g008Registry: readJson(path.join(repoRoot, 'tools/jalali/g008-system-date-surfaces.json')),
    g009RtlEvidence: readJson(path.join(artifactDir, 'g009-evidence-rtl.json')),
    g009LtrEvidence: readJson(path.join(artifactDir, 'g009-evidence-ltr.json')),
    g009PerksSourceEvidence: readJsonIfPresent(path.join(artifactDir, 'g009-perks-source-probe.json')),
    sourceDiscoveryEvidence: readJson(path.join(artifactDir, 'source-discovery.json')),
    g008FlowInboxAdmissionEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-inbox-admission.json')),
    g008FlowStatusAdmissionEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-status-admission.json')),
    g008EntryDetailAdmissionEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-entry-detail-admission.json')),
    g008ResidualSourceEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-residual-source-probe.json')),
    g008ResidualNoAdmissionEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-residual-no-admission.json')),
    g008ResidualBrowserEnabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-residual-browser-enabled.json')),
    g008ResidualBrowserDisabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-residual-browser-disabled.json')),
    g008FlowStatusBrowserEnabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-status-browser-enabled.json')),
    g008FlowStatusBrowserDisabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-status-browser-disabled.json')),
    g008FlowScheduleQualificationEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-schedule-qualification.json')),
    g008TimelinePrintQualificationEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-timeline-print-qualification.json')),
    expectedIdentity: {
      head: process.env.WU008_PGR_SHA,
      tree: process.env.WU008_PGR_TREE,
      persiangravityPackageSha256: process.env.WU008_PGR_PACKAGE_SHA256,
    },
  });
  console.log(`QUALIFICATION_EVIDENCE_RECONCILIATION ${JSON.stringify(summary)}`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    runCli();
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exit(1);
  }
}
