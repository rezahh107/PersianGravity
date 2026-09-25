import assert from 'node:assert/strict';
import test from 'node:test';
import {
  EvidenceReconciliationError,
  deriveG008SourceRequirements,
  reconcileQualificationEvidence,
} from './reconcile-qualification-evidence.mjs';

const identity = {
  head: '1'.repeat(40),
  tree: '2'.repeat(40),
  persiangravityPackageSha256: '3'.repeat(64),
};

const timelinePrintSourceFingerprints = {
  flow_entry_detail: 'a7634c5604184502457bcb22cdf1ade892e84c888cc60996aea8a810ced7680a',
  flow_common: 'a8844f4b6ac37eed1a2cc1e904418f6ed8c6b4480e982c5a809e895a6f9e0cc8',
  flow_print: 'df969bf8a37ed4f5619e0e8b2a753dd1133fa0c7551b158d740248c67fcb95c6',
  gf_common: 'ac4ed495ee02a119a4fd08c77279f0db0905e6f20f59c472d5e6bffc801ca355',
};

function flowProduct(input) {
  return input.g008Registry.products.find((product) => product.product === 'Gravity Flow');
}

function fixtures() {
  const g009Registry = {
    native_pass_runtime_requirements: {
      profiles: {
        rtl: [{ width: 1280, height: 900 }, { width: 390, height: 844 }],
        ltr: [{ width: 1280, height: 900 }, { width: 390, height: 844 }],
      },
    },
    products: [
      {
        product: 'Gravity Forms', version: '3.1.1.1', package_sha256: 'a'.repeat(64),
        surfaces: [
          { id: 'gravityforms.frontend-form', evidence_state: 'NATIVE_PASS' },
          { id: 'gravityforms.gform-admin-frontend-reachability', evidence_state: 'NOT_PROVEN' },
        ],
      },
      {
        product: 'Gravity Flow', version: '3.1.0', package_sha256: 'b'.repeat(64),
        surfaces: [{ id: 'gravityflow.frontend-inbox-ag-grid', evidence_state: 'NATIVE_PASS' }],
      },
      {
        product: 'GravityView', version: '3.3.4', package_sha256: 'c'.repeat(64),
        surfaces: [{ id: 'gravityview.admin-list', evidence_state: 'NATIVE_PASS' }],
      },
      { product: 'Gravity Perks', version: '2.3.16', package_sha256: 'd'.repeat(64), surfaces: [{ id: 'gravityperks.family-baseline', evidence_state: 'NATIVE_PASS' }] },
      { product: 'GP File Upload Pro', version: '1.5.13', package_sha256: 'e'.repeat(64), surfaces: [{ id: 'gp-file-upload-pro.frontend', evidence_state: 'NATIVE_PASS' }] },
      { product: 'GP Advanced Select', version: '1.1.21', package_sha256: 'f'.repeat(64), surfaces: [{ id: 'gp-advanced-select.tom-select', evidence_state: 'ADAPTER_REQUIRED_AND_VERIFIED' }] },
    ],
  };

  const g008Registry = {
    products: [
      {
        product: 'Gravity Forms', version: '3.1.1.1', package_sha256: 'a'.repeat(64), surfaces: [],
      },
      {
        product: 'Gravity Flow', version: '3.1.0', package_sha256: 'b'.repeat(64), surfaces: [
          { id: 'gravityflow.inbox.date-created', raw_source: 'date_created raw compare value with date_created_human_readable display value', presentation_seam: 'gravityflow_inbox_field_value receives display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
          { id: 'gravityflow.inbox.last-updated', raw_source: 'last_updated raw compare value with last_updated_human_readable display value', presentation_seam: 'gravityflow_inbox_field_value receives display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
          { id: 'gravityflow.inbox.due-date', raw_source: 'due_date raw compare value with due_date_human_readable display value', presentation_seam: 'gravityflow_inbox_field_value receives display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
          { id: 'gravityflow.status.date-created', raw_source: 'date_created sortable/raw column', presentation_seam: 'gravityflow_field_value_status_table filters the display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
          { id: 'gravityflow.status.workflow-timestamp', raw_source: 'workflow_timestamp sortable/raw field', presentation_seam: 'gravityflow_field_value_status_table filters the display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
          { id: 'gravityflow.status.due-date', raw_source: 'due_date', presentation_seam: 'NOT_PROVEN', discovery_state: 'NOT_PROVEN', support_state: 'NOT_PROVEN', adapter_identity: null },
        ],
      },
    ],
  };

  const vendorVersions = { gravityforms: '3.1.1.1', gravityflow: '3.1.0', gravityview: '3.3.4', gravityperks: '2.3.16', gpfileuploadpro: '1.5.13', gpadvancedselect: '1.1.21' };
  const packageShas = { gravityforms: 'a'.repeat(64), gravityflow: 'b'.repeat(64), gravityview: 'c'.repeat(64), gravityperks: 'd'.repeat(64), gpfileuploadpro: 'e'.repeat(64), gpadvancedselect: 'f'.repeat(64), persiangravity: identity.persiangravityPackageSha256 };
  const makeG009 = (profile) => ({
    program: 'G-009', profile,
    exact_persiangravity_commit: identity.head,
    exact_package_sha256: structuredClone(packageShas),
    vendor_versions: structuredClone(vendorVersions),
    results: g009Registry.products.flatMap((product) => product.surfaces
      .filter((surface) => ['NATIVE_PASS', 'ADAPTER_REQUIRED_AND_VERIFIED'].includes(surface.evidence_state))
      .flatMap((surface) => g009Registry.native_pass_runtime_requirements.profiles[profile].map((viewport) => ({
        id: surface.id, evidence_state: surface.evidence_state, observed: { viewport: structuredClone(viewport) },
      })))),
  });
  const g009RtlEvidence = makeG009('rtl');
  g009RtlEvidence.results.push({ id: 'gravityforms.gform-admin-frontend-reachability', evidence_state: 'NOT_PROVEN', observed: {} });
  const g009LtrEvidence = makeG009('ltr');

  const g009PerksSourceEvidence = {
    evidence_class: 'G009_EXACT_INSTALLED_GRAVITY_PERKS_SOURCE_PROBE',
    exact_persiangravity_commit: identity.head,
    exact_versions: {
      gravityperks: '2.3.16',
      gpfileuploadpro: '1.5.13',
      gpadvancedselect: '1.1.21',
    },
    exact_package_sha256: {
      gravityperks: 'd'.repeat(64),
      gpfileuploadpro: 'e'.repeat(64),
      gpadvancedselect: 'f'.repeat(64),
    },
    observations: {
      file_upload_pro: {
        wp_localize_script_gpfup_constants_line: 395,
        gettext_select_files_line: 397,
        gettext_drop_files_here_line: 398,
        gettext_or_line: 399,
      },
      advanced_select: {
        exact_style_handle_line: 550,
        exact_style_asset_line: 551,
        change_listener_plugin_line: 121,
      },
    },
  };

  const sourceDiscoveryEvidence = {
    evidence_class: 'EXACT_INSTALLED_VENDOR_SOURCE_DISCOVERY',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_tree: identity.tree,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_versions: { gravityflow: '3.1.0' },
    exact_package_sha256: { gravityflow: 'b'.repeat(64) },
    references: { gravityflow: {} },
  };
  for (const surface of flowProduct({ g008Registry }).surfaces.filter((surface) => surface.discovery_state === 'SOURCE_PROVEN')) {
    const requirements = deriveG008SourceRequirements(surface);
    sourceDiscoveryEvidence.references.gravityflow[requirements.seam] = [{ file: 'fixture.php', line: 10, operation: 'apply_filters' }];
    for (const needle of requirements.needles) {
      sourceDiscoveryEvidence.references.gravityflow[needle] ??= [{ file: 'fixture.php', line: 20, operation: 'reference' }];
    }
  }

  return {
    g009Registry,
    g008Registry,
    g009RtlEvidence,
    g009LtrEvidence,
    g009PerksSourceEvidence,
    sourceDiscoveryEvidence,
    g008FlowInboxAdmissionEvidence: null,
    g008FlowStatusAdmissionEvidence: null,
    g008EntryDetailAdmissionEvidence: null,
    g008ResidualSourceEvidence: null,
    g008ResidualNoAdmissionEvidence: null,
    g008ResidualBrowserEnabledEvidence: null,
    g008ResidualBrowserDisabledEvidence: null,
    g008FlowStatusBrowserEnabledEvidence: null,
    g008FlowStatusBrowserDisabledEvidence: null,
    g008FlowScheduleQualificationEvidence: null,
    g008TimelinePrintQualificationEvidence: null,
    expectedIdentity: structuredClone(identity),
  };
}

function admitFlowInbox(input) {
  const targets = flowProduct(input).surfaces.filter((surface) => [
    'gravityflow.inbox.date-created',
    'gravityflow.inbox.last-updated',
    'gravityflow.inbox.due-date',
  ].includes(surface.id));
  for (const surface of targets) {
    surface.discovery_state = 'RUNTIME_PROVEN';
    surface.support_state = 'ADMITTED_VERIFIED';
    surface.runtime_evidence = 'g008-flow-inbox-admission.json';
  }
  input.g008FlowInboxAdmissionEvidence = {
    evidence_class: 'G008_GRAVITY_FLOW_INBOX_ADMISSION_RECONCILIATION',
    hard_gate_result: 'PASS',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    surfaces: Object.fromEntries(targets.map((surface) => [surface.id, 'ADMITTED_VERIFIED'])),
  };
}

function admitFlowStatus(input) {
  const targets = flowProduct(input).surfaces.filter((surface) => [
    'gravityflow.status.date-created',
    'gravityflow.status.workflow-timestamp',
  ].includes(surface.id));
  for (const surface of targets) {
    surface.discovery_state = 'RUNTIME_PROVEN';
    surface.support_state = 'ADMITTED_VERIFIED';
    surface.runtime_evidence = 'g008-flow-status-admission.json';
  }
  input.g008FlowStatusAdmissionEvidence = {
    evidence_class: 'G008_GRAVITY_FLOW_STATUS_ADMISSION_RECONCILIATION',
    hard_gate_result: 'PASS',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    surfaces: Object.fromEntries(targets.map((surface) => [surface.id, 'ADMITTED_VERIFIED'])),
  };
}

function admitFlowEntryDetail(input) {
  const flow = flowProduct(input);
  const ids = [
    'gravityflow.entry-detail.submitted',
    'gravityflow.entry-detail.last-updated',
    'gravityflow.entry-detail.due-date',
    'gravityflow.entry-detail.expiration',
  ];
  const targets = ids.map((id) => {
    let surface = flow.surfaces.find((item) => item.id === id);
    if (!surface) {
      surface = {
        id,
        raw_source: id,
        presentation_seam: 'gravityflow_date_format_entry_detail composed with exact marked date_i18n',
        discovery_state: 'RUNTIME_PROVEN',
        support_state: 'ADMITTED_VERIFIED',
        adapter_identity: 'PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter',
        runtime_evidence: 'g008-entry-detail-admission.json',
      };
      flow.surfaces.push(surface);
    }
    surface.discovery_state = 'RUNTIME_PROVEN';
    surface.support_state = 'ADMITTED_VERIFIED';
    surface.adapter_identity = 'PGR_Gravity_Flow_Entry_Detail_Jalali_Presentation_Adapter';
    surface.runtime_evidence = 'g008-entry-detail-admission.json';
    return surface;
  });

  input.g008EntryDetailAdmissionEvidence = {
    evidence_class: 'G008_GRAVITY_FLOW_ENTRY_DETAIL_ADMISSION_RECONCILIATION',
    hard_gate_result: 'PASS',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    source_contract_proven: true,
    production_browser_enabled_mode: 'enabled',
    production_browser_disabled_mode: 'disabled',
    native_time_preserved: true,
    unrelated_date_formatting_unchanged: true,
    raw_db_gfapi_rest_equal: true,
    workflow_deadline_expiration_state_equal: true,
    operational_getter_counts_equal: true,
    zero_nested_operational_reentry: true,
    csv_export_isolated: true,
    repeated_rendering_deterministic: true,
    marker_leak_free: true,
    surfaces: Object.fromEntries(targets.map((surface) => [surface.id, 'ADMITTED_VERIFIED'])),
  };
}

function closeFlowResiduals(input) {
  const flow = flowProduct(input);
  const statusDue = flow.surfaces.find((surface) => surface.id === 'gravityflow.status.due-date');
  const schedule = {
    id: 'gravityflow.entry-detail.schedule',
    raw_source: 'host-owned type-specific schedule authority',
    presentation_seam: 'FINAL_NO_ADMISSION direct queued-step schedule render',
    discovery_state: 'SOURCE_PROVEN',
    support_state: 'NOT_PROVEN',
    adapter_identity: null,
  };
  const residuals = [statusDue, schedule];
  for (const surface of residuals) {
    surface.discovery_state = 'SOURCE_PROVEN';
    surface.support_state = 'NOT_PROVEN';
    surface.adapter_identity = null;
    surface.exact_version_disposition = 'FINAL_NO_ADMISSION';
    surface.runtime_evidence = surface.id === 'gravityflow.entry-detail.schedule'
      ? 'g008-flow-schedule-qualification.json'
      : 'g008-flow-residual-no-admission.json';
    if (!flow.surfaces.includes(surface)) flow.surfaces.push(surface);
  }

  input.g008ResidualSourceEvidence = {
    evidence_class: 'G008_RESIDUAL_EXACT_SOURCE_PROBE',
    exact: { pgr_sha: identity.head, version: '3.1.0', sha256: 'b'.repeat(64) },
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

  input.g008ResidualNoAdmissionEvidence = {
    evidence_class: 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION',
    status: 'PASS',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    site_timezone: 'Asia/Tehran',
    php_default_timezone: 'UTC',
    source_contract_proven: true,
    status_browser_modes: { enabled: 'enabled', disabled: 'disabled' },
    surfaces: Object.fromEntries(residuals.map((surface) => [surface.id, 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'])),
  };

  const makeStatusBrowser = (mode) => ({
    evidence_class: 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER',
    mode,
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    site_timezone: 'Asia/Tehran',
    php_default_timezone: 'UTC',
    rows: [{ id: 1, due_date: 'March 21, 2030' }],
    residual_no_admission: {
      surface: 'gravityflow.status.due-date',
      disposition: 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
      enabled_and_disabled_expect_native: true,
    },
  });
  input.g008FlowStatusBrowserEnabledEvidence = makeStatusBrowser('enabled');
  input.g008FlowStatusBrowserDisabledEvidence = makeStatusBrowser('disabled');

  input.g008FlowScheduleQualificationEvidence = {
    evidence_class: 'G008_FLOW_SCHEDULE_BRANCH_QUALIFICATION_RECONCILIATION',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    source_contract_proven: true,
    enabled_disabled_native_equality: true,
    operational_getter_counts_equal: true,
    disposition: 'FINAL_NO_ADMISSION_FOR_EXACT_3_1_0',
  };
}

function admitTimelinePrint(input) {
  const flow = flowProduct(input);
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
  flow.surfaces.push(timeline, print);

  input.g008TimelinePrintQualificationEvidence = {
    evidence_class: 'G008_TIMELINE_PRINT_PRODUCTION_ADMISSION_RECONCILIATION',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    exact_gravityforms_version: '3.1.1.1',
    exact_gravityforms_package_sha256: 'a'.repeat(64),
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

function expectFailure(input, pattern) {
  assert.throws(() => reconcileQualificationEvidence(input), (error) => {
    assert.equal(error instanceof EvidenceReconciliationError, true);
    assert.match(error.message, pattern);
    return true;
  });
}

test('positive control: evidence matching declared G-009/G-008 claims passes without promotion', () => {
  const input = fixtures();
  const beforeG009 = structuredClone(input.g009Registry);
  const beforeG008 = structuredClone(input.g008Registry);
  const result = reconcileQualificationEvidence(input);
  assert.deepEqual(result, {
    status: 'PASS',
    g009_runtime_claims_reconciled: 6,
    g008_source_proven_claims_reconciled: 5,
    g008_runtime_admitted_claims_reconciled: 0,
    g008_final_no_admission_claims_reconciled: 0,
    g008_runtime_qualified_not_admitted_claims_reconciled: 0,
  });
  assert.deepEqual(input.g009Registry, beforeG009);
  assert.deepEqual(input.g008Registry, beforeG008);
  assert.equal(flowProduct(input).surfaces.every((surface) => surface.support_state === 'NOT_PROVEN'), true);
});

test('G-009 downgrade falsification rejects NOT_PROVEN for one required runtime scenario', () => {
  const input = fixtures();
  input.g009RtlEvidence.results.find((item) => item.id === 'gravityforms.frontend-form' && item.observed.viewport?.width === 390).evidence_state = 'NOT_PROVEN';
  expectFailure(input, /gravityforms\.frontend-form.*rtl 390x844.*expected NATIVE_PASS, found NOT_PROVEN/);
});

test('G-009 missing-evidence falsification rejects a missing required surface scenario', () => {
  const input = fixtures();
  input.g009LtrEvidence.results = input.g009LtrEvidence.results.filter((item) => !(item.id === 'gravityview.admin-list' && item.observed.viewport?.width === 1280));
  expectFailure(input, /gravityview\.admin-list.*ltr 1280x900.*found 0/);
});

test('G-009 rejects duplicated mode identity and missing exact Perks source evidence', () => {
  const duplicated = fixtures();
  duplicated.g009LtrEvidence = structuredClone(duplicated.g009RtlEvidence);
  expectFailure(duplicated, /G-009 ltr: artifact program\/profile identity mismatch/);

  const missingSource = fixtures();
  missingSource.g009PerksSourceEvidence = null;
  expectFailure(missingSource, /Gravity Perks exact installed source probe is missing/);
});

test('G-009 rejects exact Perks source drift and adapter runtime downgrade', () => {
  const drifted = fixtures();
  drifted.g009PerksSourceEvidence.exact_package_sha256.gpadvancedselect = '0'.repeat(64);
  expectFailure(drifted, /gp-advanced-select\.tom-select.*source probe package SHA-256 mismatch/);

  const downgraded = fixtures();
  downgraded.g009RtlEvidence.results.find((item) => item.id === 'gp-advanced-select.tom-select' && item.observed.viewport?.width === 390).evidence_state = 'NOT_PROVEN';
  expectFailure(downgraded, /gp-advanced-select\.tom-select.*rtl 390x844.*expected ADAPTER_REQUIRED_AND_VERIFIED, found NOT_PROVEN/);
});

test('G-008 discovery-regression falsification rejects a lost asserted source seam/reference', () => {
  const input = fixtures();
  input.sourceDiscoveryEvidence.references.gravityflow.gravityflow_inbox_field_value = [];
  expectFailure(input, /gravityflow\.inbox\.date-created.*required apply_filters seam gravityflow_inbox_field_value is missing/);
});

test('G-008 runtime admission claims require exact matching admission evidence', () => {
  const input = fixtures();
  admitFlowInbox(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_source_proven_claims_reconciled, 5);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 3);

  const downgraded = fixtures();
  admitFlowInbox(downgraded);
  downgraded.g008FlowInboxAdmissionEvidence.surfaces['gravityflow.inbox.last-updated'] = 'NOT_PROVEN';
  expectFailure(downgraded, /gravityflow\.inbox\.last-updated.*did not admit the committed surface claim/);

  const missing = fixtures();
  admitFlowInbox(missing);
  missing.g008FlowInboxAdmissionEvidence = null;
  expectFailure(missing, /gravityflow\.inbox\.date-created.*required runtime admission evidence is missing/);
});

test('G-008 Status runtime admission claims require exact matching Status evidence', () => {
  const input = fixtures();
  admitFlowStatus(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_source_proven_claims_reconciled, 5);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 2);

  const downgraded = fixtures();
  admitFlowStatus(downgraded);
  downgraded.g008FlowStatusAdmissionEvidence.surfaces['gravityflow.status.workflow-timestamp'] = 'NOT_PROVEN';
  expectFailure(downgraded, /gravityflow\.status\.workflow-timestamp.*did not admit the committed surface claim/);

  const missing = fixtures();
  admitFlowStatus(missing);
  missing.g008FlowStatusAdmissionEvidence = null;
  expectFailure(missing, /gravityflow\.status\.date-created.*required runtime admission evidence is missing/);
});

test('G-008 Entry Detail production admission requires the complete bounded hard gate', () => {
  const input = fixtures();
  admitFlowEntryDetail(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 4);
  assert.equal(result.g008_source_proven_claims_reconciled, 9);

  const missing = fixtures();
  admitFlowEntryDetail(missing);
  missing.g008EntryDetailAdmissionEvidence = null;
  expectFailure(missing, /gravityflow\.entry-detail\.submitted.*required runtime admission evidence is missing/);

  const incomplete = fixtures();
  admitFlowEntryDetail(incomplete);
  incomplete.g008EntryDetailAdmissionEvidence.zero_nested_operational_reentry = false;
  expectFailure(incomplete, /gravityflow\.entry-detail\.submitted.*Entry Detail production admission contract is incomplete/);

  const downgraded = fixtures();
  admitFlowEntryDetail(downgraded);
  downgraded.g008EntryDetailAdmissionEvidence.surfaces['gravityflow.entry-detail.expiration'] = 'NOT_PROVEN';
  expectFailure(downgraded, /gravityflow\.entry-detail\.expiration.*did not admit the committed surface claim/);
});

test('combined Inbox and Status admissions reconcile five bounded runtime claims', () => {
  const input = fixtures();
  admitFlowInbox(input);
  admitFlowStatus(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 5);
});

test('G-008 residual final-no-admission authority contains only Status due-date and Entry Detail schedule', () => {
  const input = fixtures();
  closeFlowResiduals(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_final_no_admission_claims_reconciled, 2);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 0);
  assert.deepEqual(Object.keys(input.g008ResidualNoAdmissionEvidence.surfaces).sort(), [
    'gravityflow.entry-detail.schedule',
    'gravityflow.status.due-date',
  ]);

  const polluted = fixtures();
  closeFlowResiduals(polluted);
  polluted.g008ResidualNoAdmissionEvidence.surfaces['gravityflow.timeline-history'] = 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0';
  expectFailure(polluted, /residual runtime target set.*includes an admitted surface/);
});

test('G-008 residual source contract gate rejects missing, empty, false and non-boolean evidence', () => {
  const missing = fixtures();
  closeFlowResiduals(missing);
  delete missing.g008ResidualSourceEvidence.source_contract;
  expectFailure(missing, /gravityflow\.status\.due-date.*source contract is missing or empty/);

  const empty = fixtures();
  closeFlowResiduals(empty);
  empty.g008ResidualSourceEvidence.source_contract.status_due_date = {};
  expectFailure(empty, /gravityflow\.status\.due-date.*source contract is missing or empty/);

  const falseFlag = fixtures();
  closeFlowResiduals(falseFlag);
  falseFlag.g008ResidualSourceEvidence.source_contract.status_due_date.overdue_uses_same_due_getter = false;
  expectFailure(falseFlag, /gravityflow\.status\.due-date.*required source flag overdue_uses_same_due_getter is false/);

  const nonBoolean = fixtures();
  closeFlowResiduals(nonBoolean);
  nonBoolean.g008ResidualSourceEvidence.source_contract.entry_detail_schedule_due_expiration.schedule_has_no_value_filter = 'true';
  expectFailure(nonBoolean, /gravityflow\.entry-detail\.schedule.*required source flag schedule_has_no_value_filter must be boolean/);
});

test('G-008 residual runtime gate rejects missing/duplicated Status browser and package drift', () => {
  const missing = fixtures();
  closeFlowResiduals(missing);
  missing.g008FlowStatusBrowserEnabledEvidence = null;
  expectFailure(missing, /enabled Status browser: browser evidence is missing/);

  const wrongMode = fixtures();
  closeFlowResiduals(wrongMode);
  wrongMode.g008FlowStatusBrowserDisabledEvidence.mode = 'enabled';
  expectFailure(wrongMode, /disabled Status browser: mode must be disabled/);

  const wrongHash = fixtures();
  closeFlowResiduals(wrongHash);
  wrongHash.g008FlowStatusBrowserDisabledEvidence.exact_gravityflow_package_sha256 = '0'.repeat(64);
  expectFailure(wrongHash, /disabled Status browser: Gravity Flow package SHA-256 mismatch/);

  const sourceOnly = fixtures();
  closeFlowResiduals(sourceOnly);
  sourceOnly.g008ResidualNoAdmissionEvidence = null;
  expectFailure(sourceOnly, /residual enabled\/disabled runtime reconciliation evidence is missing/);
});

test('G-008 Timeline and Print committed admissions require the dedicated production artifact', () => {
  const input = fixtures();
  admitTimelinePrint(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 2);
  assert.equal(result.g008_source_proven_claims_reconciled, 7);

  const missing = fixtures();
  admitTimelinePrint(missing);
  missing.g008TimelinePrintQualificationEvidence = null;
  expectFailure(missing, /gravityflow\.timeline-history.*dedicated Timeline\/Print admission evidence is missing/);

  const wrongClass = fixtures();
  admitTimelinePrint(wrongClass);
  wrongClass.g008TimelinePrintQualificationEvidence.evidence_class = 'G008_TIMELINE_PRINT_QUALIFICATION_RECONCILIATION';
  expectFailure(wrongClass, /gravityflow\.timeline-history.*evidence class mismatch/);
});

test('G-008 Timeline/Print admission falsifies Head and package identity drift', () => {
  const headDrift = fixtures();
  admitTimelinePrint(headDrift);
  headDrift.g008TimelinePrintQualificationEvidence.exact_persiangravity_commit = '9'.repeat(40);
  expectFailure(headDrift, /gravityflow\.timeline-history.*PersianGravity source commit mismatch/);

  const pgrPackageDrift = fixtures();
  admitTimelinePrint(pgrPackageDrift);
  pgrPackageDrift.g008TimelinePrintQualificationEvidence.exact_persiangravity_package_sha256 = '9'.repeat(64);
  expectFailure(pgrPackageDrift, /gravityflow\.timeline-history.*PersianGravity package SHA-256 mismatch/);

  const flowPackageDrift = fixtures();
  admitTimelinePrint(flowPackageDrift);
  flowPackageDrift.g008TimelinePrintQualificationEvidence.exact_gravityflow_package_sha256 = '9'.repeat(64);
  expectFailure(flowPackageDrift, /gravityflow\.timeline-history.*Gravity Flow identity mismatch/);

  const gfPackageDrift = fixtures();
  admitTimelinePrint(gfPackageDrift);
  gfPackageDrift.g008TimelinePrintQualificationEvidence.exact_gravityforms_package_sha256 = '9'.repeat(64);
  expectFailure(gfPackageDrift, /gravityflow\.timeline-history.*Gravity Forms identity mismatch/);
});

test('G-008 Timeline/Print admission falsifies Timeline admission and Print inheritance drift', () => {
  const timelineAdmission = fixtures();
  admitTimelinePrint(timelineAdmission);
  timelineAdmission.g008TimelinePrintQualificationEvidence.timeline.initial_entry_disposition = 'NOT_PROVEN';
  expectFailure(timelineAdmission, /gravityflow\.timeline-history.*Timeline production admission contract is incomplete/);

  const printInheritance = fixtures();
  admitTimelinePrint(printInheritance);
  printInheritance.g008TimelinePrintQualificationEvidence.print.timeline_relation = 'INDEPENDENT';
  expectFailure(printInheritance, /gravityflow\.timeline-history.*Print verified-Timeline inheritance contract is incomplete/);

  const noIndependentSeam = fixtures();
  admitTimelinePrint(noIndependentSeam);
  noIndependentSeam.g008TimelinePrintQualificationEvidence.print.independent_date_seam_disposition = 'INDEPENDENT_SEAM';
  expectFailure(noIndependentSeam, /Print verified-Timeline inheritance contract is incomplete/);
});

test('G-008 Timeline/Print admission falsifies registry disposition and storage/body preservation drift', () => {
  const registryTimeline = fixtures();
  admitTimelinePrint(registryTimeline);
  flowProduct(registryTimeline).surfaces.find((surface) => surface.id === 'gravityflow.timeline-history').exact_version_disposition = 'FINAL_NO_ADMISSION';
  expectFailure(registryTimeline, /registry disposition is not exact-version admitted/);

  const registryPrint = fixtures();
  admitTimelinePrint(registryPrint);
  flowProduct(registryPrint).surfaces.find((surface) => surface.id === 'gravityflow.print').exact_version_disposition = 'FINAL_NO_ADMISSION';
  expectFailure(registryPrint, /registry disposition is not admitted by verified Timeline inheritance/);

  const storage = fixtures();
  admitTimelinePrint(storage);
  storage.g008TimelinePrintQualificationEvidence.timeline.storage_unchanged = false;
  expectFailure(storage, /Timeline production admission contract is incomplete/);

  const bodies = fixtures();
  admitTimelinePrint(bodies);
  bodies.g008TimelinePrintQualificationEvidence.timeline.ids_order_bodies_unchanged = false;
  expectFailure(bodies, /Timeline production admission contract is incomplete/);

  const bodyModes = fixtures();
  admitTimelinePrint(bodyModes);
  bodyModes.g008TimelinePrintQualificationEvidence.timeline.body_vector_mode_equality_proven = false;
  expectFailure(bodyModes, /Timeline production admission contract is incomplete/);
});

test('G-008 Timeline/Print admission falsifies source fingerprint and explicit failure drift', () => {
  const fingerprint = fixtures();
  admitTimelinePrint(fingerprint);
  fingerprint.g008TimelinePrintQualificationEvidence.source_fingerprints.flow_common = '0'.repeat(64);
  expectFailure(fingerprint, /source fingerprint identity mismatch/);

  const failed = fixtures();
  admitTimelinePrint(failed);
  failed.g008TimelinePrintQualificationEvidence.failures.push('synthetic failure');
  expectFailure(failed, /qualification contains failures/);
});

test('combined residual closure plus Timeline/Print admission keeps authority split clean', () => {
  const input = fixtures();
  closeFlowResiduals(input);
  admitTimelinePrint(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_final_no_admission_claims_reconciled, 2);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 2);
  assert.equal(result.g008_source_proven_claims_reconciled, 9);
});

test('exact identity mismatch rejects both vendor package drift and PersianGravity source drift', () => {
  const packageMismatch = fixtures();
  packageMismatch.g009RtlEvidence.exact_package_sha256.gravityforms = '0'.repeat(64);
  expectFailure(packageMismatch, /Gravity Forms package SHA-256 mismatch/);

  const sourceMismatch = fixtures();
  sourceMismatch.sourceDiscoveryEvidence.exact_persiangravity_commit = '9'.repeat(40);
  expectFailure(sourceMismatch, /G-008 source discovery: PersianGravity source commit mismatch/);

  const runtimeMismatch = fixtures();
  admitFlowInbox(runtimeMismatch);
  runtimeMismatch.g008FlowInboxAdmissionEvidence.exact_gravityflow_package_sha256 = '0'.repeat(64);
  expectFailure(runtimeMismatch, /gravityflow\.inbox\.date-created.*runtime Gravity Flow package SHA-256 mismatch/);
});

test('deliberate NOT_PROVEN claims remain legal and are not promoted by reconciliation', () => {
  const input = fixtures();
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.status, 'PASS');
  const notProven = input.g009Registry.products.flatMap((product) => product.surfaces).filter((surface) => surface.evidence_state === 'NOT_PROVEN');
  assert.deepEqual(notProven.map((surface) => surface.id), [
    'gravityforms.gform-admin-frontend-reachability',
  ]);
  assert.equal(notProven.every((surface) => surface.evidence_state === 'NOT_PROVEN'), true);
});
