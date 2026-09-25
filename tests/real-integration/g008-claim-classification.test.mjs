import assert from 'node:assert/strict';
import test from 'node:test';
import {
  EvidenceReconciliationError,
  reconcileQualificationEvidence,
} from './reconcile-qualification-evidence.mjs';

const identity = {
  head: '1'.repeat(40),
  tree: '2'.repeat(40),
  persiangravityPackageSha256: '3'.repeat(64),
};

const gfSha = 'a'.repeat(64);
const flowSha = 'b'.repeat(64);
const timelinePrintSourceFingerprints = {
  flow_entry_detail: 'a7634c5604184502457bcb22cdf1ade892e84c888cc60996aea8a810ced7680a',
  flow_common: 'a8844f4b6ac37eed1a2cc1e904418f6ed8c6b4480e982c5a809e895a6f9e0cc8',
  flow_print: 'df969bf8a37ed4f5619e0e8b2a753dd1133fa0c7551b158d740248c67fcb95c6',
  gf_common: 'ac4ed495ee02a119a4fd08c77279f0db0905e6f20f59c472d5e6bffc801ca355',
};

const ordinaryFamilies = [
  {
    label: 'Inbox',
    id: 'gravityflow.inbox.date-created',
    runtimeEvidence: 'g008-flow-inbox-admission.json',
  },
  {
    label: 'Status',
    id: 'gravityflow.status.date-created',
    runtimeEvidence: 'g008-flow-status-admission.json',
  },
  {
    label: 'Entry Detail',
    id: 'gravityflow.entry-detail.submitted',
    runtimeEvidence: 'g008-entry-detail-admission.json',
  },
];

const dedicatedIds = [
  'gravityflow.timeline-history',
  'gravityflow.print',
];

function g009Evidence(profile) {
  return {
    program: 'G-009',
    profile,
    exact_persiangravity_commit: identity.head,
    exact_package_sha256: { persiangravity: identity.persiangravityPackageSha256 },
    vendor_versions: {},
    results: [],
  };
}

function runtimeEvidence(evidenceClass, surfaceId, extra = {}) {
  return {
    evidence_class: evidenceClass,
    hard_gate_result: 'PASS',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: flowSha,
    surfaces: { [surfaceId]: 'ADMITTED_VERIFIED' },
    ...extra,
  };
}

function timelinePrintEvidence() {
  return {
    evidence_class: 'G008_TIMELINE_PRINT_PRODUCTION_ADMISSION_RECONCILIATION',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: flowSha,
    exact_gravityforms_version: '3.1.1.1',
    exact_gravityforms_package_sha256: gfSha,
    source_fingerprints: structuredClone(timelinePrintSourceFingerprints),
    timeline: {
      initial_entry_disposition: 'RUNTIME_PROVEN + ADMITTED_VERIFIED',
      stored_note_event_disposition: 'RUNTIME_PROVEN + ADMITTED_VERIFIED',
      supported_formats: ['F j, Y', 'Y-m-d'],
      unsupported_formats_native: true,
      storage_unchanged: true,
      ids_order_bodies_unchanged: true,
      duplicate_timestamp_identity_proven: true,
      user_authored_date_looking_text_untouched: true,
      separate_display_property_consumed: false,
      date_created_representation_consumed_by_renderer: true,
      adapter_hook_lifecycle_proven: true,
      native_disabled_fallback_proven: true,
      enabled_jalali_presentation_proven: true,
      body_vector_mode_equality_proven: true,
      fixture_row_mapping_proven: true,
      marker_non_leakage_proven: true,
      operational_state_unchanged: true,
    },
    print: {
      field_grid_relation: 'REUSES_ENTRY_DETAIL_FIELD_GRID',
      timeline_relation: 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION',
      initial_event_propagation_disposition: 'RUNTIME_PROVEN + ADMITTED_VERIFIED_BY_TIMELINE_INHERITANCE',
      stored_note_event_propagation_disposition: 'RUNTIME_PROVEN + ADMITTED_VERIFIED_BY_TIMELINE_INHERITANCE',
      independent_date_seam_disposition: 'NO_INDEPENDENT_PRINT_DATE_SEAM_REQUIRED',
      workflow_sidebar_due_schedule_expiration: 'ABSENT_FROM_PRINT_RENDER_PATH',
      body_vector_inheritance_proven: true,
      native_disabled_inheritance_proven: true,
      marker_non_leakage_proven: true,
    },
    failures: [],
  };
}

function residualSourceEvidence() {
  return {
    evidence_class: 'G008_RESIDUAL_EXACT_SOURCE_PROBE',
    exact: { pgr_sha: identity.head, version: '3.1.0', sha256: flowSha },
    source_contract: {
      status_due_date: Object.fromEntries([
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
      ].map((key) => [key, true])),
      entry_detail_schedule_due_expiration: Object.fromEntries([
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
      ].map((key) => [key, true])),
    },
  };
}

function statusBrowser(mode) {
  return {
    evidence_class: 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER',
    mode,
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: flowSha,
    site_timezone: 'Asia/Tehran',
    php_default_timezone: 'UTC',
    rows: [{ id: 1, due_date: 'March 21, 2030' }],
    residual_no_admission: {
      surface: 'gravityflow.status.due-date',
      disposition: 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
      enabled_and_disabled_expect_native: true,
    },
  };
}

function makeInput() {
  const inbox = {
    id: 'gravityflow.inbox.date-created',
    raw_source: 'date_created raw compare value with date_created_human_readable display value',
    presentation_seam: 'gravityflow_inbox_field_value receives display value',
    discovery_state: 'RUNTIME_PROVEN',
    support_state: 'ADMITTED_VERIFIED',
    adapter_identity: 'PGR_Gravity_Flow_Inbox_Jalali_Presentation_Adapter',
    runtime_evidence: 'g008-flow-inbox-admission.json',
  };
  const status = {
    id: 'gravityflow.status.date-created',
    raw_source: 'date_created sortable raw column',
    presentation_seam: 'gravityflow_field_value_status_table filters display value',
    discovery_state: 'RUNTIME_PROVEN',
    support_state: 'ADMITTED_VERIFIED',
    adapter_identity: 'PGR_Gravity_Flow_Status_Jalali_Presentation_Adapter',
    runtime_evidence: 'g008-flow-status-admission.json',
  };
  const entryDetail = {
    id: 'gravityflow.entry-detail.submitted',
    raw_source: 'submitted workflow-info date',
    presentation_seam: 'gravityflow_date_format_entry_detail composed with marked date_i18n',
    discovery_state: 'RUNTIME_PROVEN',
    support_state: 'ADMITTED_VERIFIED',
    adapter_identity: 'PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter',
    runtime_evidence: 'g008-entry-detail-admission.json',
  };
  const timeline = {
    id: 'gravityflow.timeline-history',
    raw_source: 'authoritative history date_created timestamps',
    presentation_seam: 'exact qualified Timeline renderer context',
    discovery_state: 'RUNTIME_PROVEN',
    support_state: 'ADMITTED_VERIFIED',
    adapter_identity: 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter',
    runtime_evidence: 'g008-timeline-print-qualification.json',
    exact_version_disposition: 'ADMITTED_FOR_EXACT_VERSION',
  };
  const print = {
    id: 'gravityflow.print',
    raw_source: 'inherited verified Timeline renderer',
    presentation_seam: 'INHERITS_VERIFIED_TIMELINE_RENDERER; no independent Print date seam',
    discovery_state: 'RUNTIME_PROVEN',
    support_state: 'ADMITTED_VERIFIED',
    adapter_identity: 'PGR_Gravity_Flow_Timeline_Jalali_Presentation_Adapter',
    runtime_evidence: 'g008-timeline-print-qualification.json',
    exact_version_disposition: 'ADMITTED_BY_VERIFIED_TIMELINE_INHERITANCE',
  };
  const statusDue = {
    id: 'gravityflow.status.due-date',
    raw_source: 'due_date',
    presentation_seam: 'FINAL_NO_ADMISSION direct due-date render',
    discovery_state: 'SOURCE_PROVEN',
    support_state: 'NOT_PROVEN',
    adapter_identity: null,
    exact_version_disposition: 'FINAL_NO_ADMISSION',
    runtime_evidence: 'g008-flow-residual-no-admission.json',
  };
  const schedule = {
    id: 'gravityflow.entry-detail.schedule',
    raw_source: 'host-owned type-specific schedule authority',
    presentation_seam: 'FINAL_NO_ADMISSION direct queued-step schedule render',
    discovery_state: 'SOURCE_PROVEN',
    support_state: 'NOT_PROVEN',
    adapter_identity: null,
    exact_version_disposition: 'FINAL_NO_ADMISSION',
    runtime_evidence: 'g008-flow-schedule-qualification.json',
  };

  const references = {
    gravityflow_inbox_field_value: [{ file: 'fixture.php', line: 10, operation: 'apply_filters' }],
    date_created: [{ file: 'fixture.php', line: 20, operation: 'reference' }],
    date_created_human_readable: [{ file: 'fixture.php', line: 21, operation: 'reference' }],
    gravityflow_field_value_status_table: [{ file: 'fixture.php', line: 30, operation: 'apply_filters' }],
    gravityflow_date_format_entry_detail: [{ file: 'fixture.php', line: 40, operation: 'apply_filters' }],
    submitted: [{ file: 'fixture.php', line: 41, operation: 'reference' }],
  };

  const input = {
    g009Registry: {
      native_pass_runtime_requirements: {
        profiles: {
          rtl: [{ width: 1280, height: 900 }],
          ltr: [{ width: 1280, height: 900 }],
        },
      },
      products: [],
    },
    g008Registry: {
      products: [
        { product: 'Gravity Forms', version: '3.1.1.1', package_sha256: gfSha, surfaces: [] },
        {
          product: 'Gravity Flow',
          version: '3.1.0',
          package_sha256: flowSha,
          surfaces: [inbox, status, entryDetail, timeline, print, statusDue, schedule],
        },
      ],
    },
    g009RtlEvidence: g009Evidence('rtl'),
    g009LtrEvidence: g009Evidence('ltr'),
    g009PerksSourceEvidence: null,
    sourceDiscoveryEvidence: {
      evidence_class: 'EXACT_INSTALLED_VENDOR_SOURCE_DISCOVERY',
      exact_persiangravity_commit: identity.head,
      exact_persiangravity_tree: identity.tree,
      exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
      exact_versions: { gravityflow: '3.1.0' },
      exact_package_sha256: { gravityflow: flowSha },
      references: { gravityflow: references },
    },
    g008FlowInboxAdmissionEvidence: runtimeEvidence(
      'G008_GRAVITY_FLOW_INBOX_ADMISSION_RECONCILIATION',
      inbox.id
    ),
    g008FlowStatusAdmissionEvidence: runtimeEvidence(
      'G008_GRAVITY_FLOW_STATUS_ADMISSION_RECONCILIATION',
      status.id
    ),
    g008EntryDetailAdmissionEvidence: runtimeEvidence(
      'G008_GRAVITY_FLOW_ENTRY_DETAIL_ADMISSION_RECONCILIATION',
      entryDetail.id,
      {
        source_contract_proven: true,
        native_time_preserved: true,
        unrelated_date_formatting_unchanged: true,
        raw_db_gfapi_rest_equal: true,
        workflow_deadline_expiration_state_equal: true,
        operational_getter_counts_equal: true,
        zero_nested_operational_reentry: true,
        csv_export_isolated: true,
        repeated_rendering_deterministic: true,
        marker_leak_free: true,
        production_browser_enabled_mode: 'enabled',
        production_browser_disabled_mode: 'disabled',
      }
    ),
    g008ResidualSourceEvidence: residualSourceEvidence(),
    g008ResidualNoAdmissionEvidence: {
      evidence_class: 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION',
      status: 'PASS',
      exact_persiangravity_commit: identity.head,
      exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
      exact_gravityflow_version: '3.1.0',
      exact_gravityflow_package_sha256: flowSha,
      site_timezone: 'Asia/Tehran',
      php_default_timezone: 'UTC',
      source_contract_proven: true,
      status_browser_modes: { enabled: 'enabled', disabled: 'disabled' },
      surfaces: {
        'gravityflow.status.due-date': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
        'gravityflow.entry-detail.schedule': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
      },
    },
    g008ResidualBrowserEnabledEvidence: null,
    g008ResidualBrowserDisabledEvidence: null,
    g008FlowStatusBrowserEnabledEvidence: statusBrowser('enabled'),
    g008FlowStatusBrowserDisabledEvidence: statusBrowser('disabled'),
    g008FlowScheduleQualificationEvidence: {
      evidence_class: 'G008_FLOW_SCHEDULE_BRANCH_QUALIFICATION_RECONCILIATION',
      exact_persiangravity_commit: identity.head,
      exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
      exact_gravityflow_version: '3.1.0',
      exact_gravityflow_package_sha256: flowSha,
      source_contract_proven: true,
      enabled_disabled_native_equality: true,
      operational_getter_counts_equal: true,
      disposition: 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
    },
    g008TimelinePrintQualificationEvidence: timelinePrintEvidence(),
    expectedIdentity: structuredClone(identity),
  };
  return input;
}

function flowSurface(input, id) {
  const flow = input.g008Registry.products.find((product) => product.product === 'Gravity Flow');
  const surface = flow.surfaces.find((candidate) => candidate.id === id);
  assert.ok(surface, `fixture is missing ${id}`);
  return surface;
}

function keepOnlyDedicatedSurface(input, id) {
  const flow = input.g008Registry.products.find((product) => product.product === 'Gravity Flow');
  flow.surfaces = flow.surfaces.filter((surface) => !dedicatedIds.includes(surface.id) || surface.id === id);
  return flowSurface(input, id);
}

function expectFailure(input, pattern) {
  assert.throws(() => reconcileQualificationEvidence(input), (error) => {
    assert.equal(error instanceof EvidenceReconciliationError, true);
    assert.match(error.message, pattern);
    return true;
  });
}

test('positive control: all current G-008 claim families coexist without omission', () => {
  const input = makeInput();
  const result = reconcileQualificationEvidence(input);
  assert.deepEqual(result, {
    status: 'PASS',
    g009_runtime_claims_reconciled: 0,
    g008_source_proven_claims_reconciled: 7,
    g008_runtime_admitted_claims_reconciled: 5,
    g008_final_no_admission_claims_reconciled: 2,
    g008_runtime_qualified_not_admitted_claims_reconciled: 0,
  });
  for (const id of [...ordinaryFamilies.map((family) => family.id), ...dedicatedIds]) {
    assert.equal(flowSurface(input, id).support_state, 'ADMITTED_VERIFIED', id);
  }
  assert.equal(flowSurface(input, 'gravityflow.status.due-date').exact_version_disposition, 'FINAL_NO_ADMISSION');
  assert.equal(flowSurface(input, 'gravityflow.entry-detail.schedule').exact_version_disposition, 'FINAL_NO_ADMISSION');
});

for (const family of ordinaryFamilies) {
  test(`${family.label}: valid runtime admission still passes`, () => {
    const input = makeInput();
    assert.equal(reconcileQualificationEvidence(input).status, 'PASS');
  });

  test(`${family.label}: deleting runtime_evidence cannot suppress validation`, () => {
    const input = makeInput();
    delete flowSurface(input, family.id).runtime_evidence;
    expectFailure(input, new RegExp(`${family.id.replaceAll('.', '\\.')}: unsupported runtime evidence reference MISSING`));
  });

  test(`${family.label}: null runtime_evidence cannot suppress validation`, () => {
    const input = makeInput();
    flowSurface(input, family.id).runtime_evidence = null;
    expectFailure(input, new RegExp(`${family.id.replaceAll('.', '\\.')}: unsupported runtime evidence reference MISSING`));
  });

  test(`${family.label}: unsupported runtime_evidence fails closed`, () => {
    const input = makeInput();
    flowSurface(input, family.id).runtime_evidence = 'g008-unsupported-admission.json';
    expectFailure(input, new RegExp(`${family.id.replaceAll('.', '\\.')}: unsupported runtime evidence reference g008-unsupported-admission\\.json`));
  });

  test(`${family.label}: discovery-state downgrade remains a runtime claim and fails`, () => {
    const input = makeInput();
    flowSurface(input, family.id).discovery_state = 'SOURCE_PROVEN';
    expectFailure(input, new RegExp(`${family.id.replaceAll('.', '\\.')}: committed runtime admission discovery_state must be RUNTIME_PROVEN`));
  });

  test(`${family.label}: support-state downgrade remains a runtime claim and fails`, () => {
    const input = makeInput();
    flowSurface(input, family.id).support_state = 'NOT_PROVEN';
    expectFailure(input, new RegExp(`${family.id.replaceAll('.', '\\.')}: committed runtime admission support_state must be ADMITTED_VERIFIED`));
  });
}

for (const id of dedicatedIds) {
  const label = id === 'gravityflow.timeline-history' ? 'Timeline' : 'Print';

  test(`${label}: valid dedicated admission still passes independently`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id);
    assert.equal(reconcileQualificationEvidence(input).status, 'PASS');
  });

  test(`${label}: deleting runtime_evidence cannot bypass dedicated routing`, () => {
    const input = makeInput();
    const surface = keepOnlyDedicatedSurface(input, id);
    delete surface.runtime_evidence;
    expectFailure(input, new RegExp(`${id.replaceAll('.', '\\.')}.*dedicated Timeline/Print runtime evidence reference mismatch`));
  });

  test(`${label}: null runtime_evidence cannot bypass dedicated routing`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id).runtime_evidence = null;
    expectFailure(input, new RegExp(`${id.replaceAll('.', '\\.')}.*dedicated Timeline/Print runtime evidence reference mismatch`));
  });

  test(`${label}: wrong runtime_evidence cannot bypass dedicated routing`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id).runtime_evidence = 'g008-wrong-timeline-evidence.json';
    expectFailure(input, new RegExp(`${id.replaceAll('.', '\\.')}.*dedicated Timeline/Print runtime evidence reference mismatch`));
  });

  test(`${label}: discovery-state downgrade still reaches dedicated validator`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id).discovery_state = 'SOURCE_PROVEN';
    expectFailure(input, new RegExp(`${id.replaceAll('.', '\\.')}.*committed registry claim is not RUNTIME_PROVEN \\+ ADMITTED_VERIFIED`));
  });

  test(`${label}: support-state downgrade still reaches dedicated validator`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id).support_state = 'NOT_PROVEN';
    expectFailure(input, new RegExp(`${id.replaceAll('.', '\\.')}.*committed registry claim is not RUNTIME_PROVEN \\+ ADMITTED_VERIFIED`));
  });

  test(`${label}: adapter drift still fails through dedicated validator`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id).adapter_identity = 'Wrong_Adapter';
    expectFailure(input, new RegExp(`${id.replaceAll('.', '\\.')}.*committed Timeline adapter identity mismatch`));
  });

  test(`${label}: disposition drift still fails through dedicated validator`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id).exact_version_disposition = 'FINAL_NO_ADMISSION';
    const pattern = id === 'gravityflow.timeline-history'
      ? /gravityflow\.timeline-history.*registry disposition is not exact-version admitted/
      : /gravityflow\.print.*registry disposition is not admitted by verified Timeline inheritance/;
    expectFailure(input, pattern);
  });

  test(`${label}: dedicated evidence drift still fails`, () => {
    const input = makeInput();
    keepOnlyDedicatedSurface(input, id);
    if (id === 'gravityflow.timeline-history') {
      input.g008TimelinePrintQualificationEvidence.timeline.storage_unchanged = false;
      expectFailure(input, /gravityflow\.timeline-history.*Timeline production admission contract is incomplete/);
    } else {
      input.g008TimelinePrintQualificationEvidence.print.timeline_relation = 'INDEPENDENT';
      expectFailure(input, /gravityflow\.print.*Print verified-Timeline inheritance contract is incomplete/);
    }
  });
}

test('legitimate SOURCE_PROVEN + NOT_PROVEN claim remains legal without promotion', () => {
  const input = makeInput();
  const surface = flowSurface(input, 'gravityflow.inbox.date-created');
  surface.discovery_state = 'SOURCE_PROVEN';
  surface.support_state = 'NOT_PROVEN';
  delete surface.runtime_evidence;
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.status, 'PASS');
  assert.equal(result.g008_source_proven_claims_reconciled, 7);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 4);
  assert.equal(surface.support_state, 'NOT_PROVEN');
});

test('deliberately unproven surface with no admission signal remains legal', () => {
  const input = makeInput();
  const flow = input.g008Registry.products.find((product) => product.product === 'Gravity Flow');
  flow.surfaces.push({
    id: 'gravityflow.deliberately-unproven',
    raw_source: 'unknown',
    presentation_seam: 'NOT_PROVEN',
    discovery_state: 'NOT_PROVEN',
    support_state: 'NOT_PROVEN',
    adapter_identity: null,
  });
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.status, 'PASS');
  assert.equal(result.g008_source_proven_claims_reconciled, 7);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 5);
  assert.equal(result.g008_final_no_admission_claims_reconciled, 2);
});

test('Status due-date and Entry Detail schedule remain residual FINAL_NO_ADMISSION claims', () => {
  const input = makeInput();
  const before = [
    structuredClone(flowSurface(input, 'gravityflow.status.due-date')),
    structuredClone(flowSurface(input, 'gravityflow.entry-detail.schedule')),
  ];
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_final_no_admission_claims_reconciled, 2);
  assert.deepEqual(flowSurface(input, before[0].id), before[0]);
  assert.deepEqual(flowSurface(input, before[1].id), before[1]);
});
