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
      { product: 'Gravity Perks', version: '2.3.16', package_sha256: 'd'.repeat(64), surfaces: [{ id: 'gravityperks.family-baseline', evidence_state: 'NOT_PROVEN' }] },
      { product: 'GP File Upload Pro', version: '1.5.13', package_sha256: 'e'.repeat(64), surfaces: [{ id: 'gp-file-upload-pro.frontend', evidence_state: 'NOT_PROVEN' }] },
      { product: 'GP Advanced Select', version: '1.1.21', package_sha256: 'f'.repeat(64), surfaces: [{ id: 'gp-advanced-select.tom-select', evidence_state: 'NOT_PROVEN' }] },
    ],
  };

  const g008Registry = {
    products: [{
      product: 'Gravity Flow', version: '3.1.0', package_sha256: 'b'.repeat(64), surfaces: [
        { id: 'gravityflow.inbox.date-created', raw_source: 'date_created raw compare value with date_created_human_readable display value', presentation_seam: 'gravityflow_inbox_field_value receives display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
        { id: 'gravityflow.inbox.last-updated', raw_source: 'last_updated raw compare value with last_updated_human_readable display value', presentation_seam: 'gravityflow_inbox_field_value receives display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
        { id: 'gravityflow.inbox.due-date', raw_source: 'due_date raw compare value with due_date_human_readable display value', presentation_seam: 'gravityflow_inbox_field_value receives display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
        { id: 'gravityflow.status.date-created', raw_source: 'date_created sortable/raw column', presentation_seam: 'gravityflow_field_value_status_table filters the display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
        { id: 'gravityflow.status.workflow-timestamp', raw_source: 'workflow_timestamp sortable/raw field', presentation_seam: 'gravityflow_field_value_status_table filters the display value', discovery_state: 'SOURCE_PROVEN', support_state: 'NOT_PROVEN' },
        { id: 'gravityflow.status.due-date', raw_source: 'due_date', presentation_seam: 'NOT_PROVEN', discovery_state: 'NOT_PROVEN', support_state: 'NOT_PROVEN' },
      ],
    }],
  };

  const vendorVersions = { gravityforms: '3.1.1.1', gravityflow: '3.1.0', gravityview: '3.3.4' };
  const packageShas = { gravityforms: 'a'.repeat(64), gravityflow: 'b'.repeat(64), gravityview: 'c'.repeat(64), persiangravity: identity.persiangravityPackageSha256 };
  const makeG009 = (profile) => ({
    program: 'G-009', profile,
    exact_persiangravity_commit: identity.head,
    exact_package_sha256: structuredClone(packageShas),
    vendor_versions: structuredClone(vendorVersions),
    results: g009Registry.products.flatMap((product) => product.surfaces
      .filter((surface) => surface.evidence_state === 'NATIVE_PASS')
      .flatMap((surface) => g009Registry.native_pass_runtime_requirements.profiles[profile].map((viewport) => ({
        id: surface.id, evidence_state: 'NATIVE_PASS', observed: { viewport: structuredClone(viewport) },
      })))),
  });
  const g009RtlEvidence = makeG009('rtl');
  g009RtlEvidence.results.push({ id: 'gravityforms.gform-admin-frontend-reachability', evidence_state: 'NOT_PROVEN', observed: {} });
  const g009LtrEvidence = makeG009('ltr');

  const sourceDiscoveryEvidence = {
    evidence_class: 'EXACT_INSTALLED_VENDOR_SOURCE_DISCOVERY',
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_tree: identity.tree,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_versions: { gravityflow: '3.1.0' },
    exact_package_sha256: { gravityflow: 'b'.repeat(64) },
    references: { gravityflow: {} },
  };
  for (const surface of g008Registry.products[0].surfaces.filter((surface) => surface.discovery_state === 'SOURCE_PROVEN')) {
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
    sourceDiscoveryEvidence,
    g008FlowInboxAdmissionEvidence: null,
    g008FlowStatusAdmissionEvidence: null,
    g008ResidualSourceEvidence: null,
    g008ResidualNoAdmissionEvidence: null,
    g008ResidualBrowserEnabledEvidence: null,
    g008ResidualBrowserDisabledEvidence: null,
    g008FlowStatusBrowserEnabledEvidence: null,
    g008FlowStatusBrowserDisabledEvidence: null,
    expectedIdentity: structuredClone(identity),
  };
}

function admitFlowInbox(input) {
  const targets = input.g008Registry.products[0].surfaces.filter((surface) => [
    'gravityflow.inbox.date-created',
    'gravityflow.inbox.last-updated',
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
  const targets = input.g008Registry.products[0].surfaces.filter((surface) => [
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

function closeFlowResiduals(input) {
  const flow = input.g008Registry.products[0];
  const existingStatusDue = flow.surfaces.find((surface) => surface.id === 'gravityflow.status.due-date');
  const residuals = [
    existingStatusDue,
    {
      id: 'gravityflow.entry-detail.schedule-due-expiration',
      raw_source: 'workflow-owned operational timestamps',
      presentation_seam: 'FINAL_NO_ADMISSION direct operational render',
      discovery_state: 'SOURCE_PROVEN',
      support_state: 'NOT_PROVEN',
      adapter_identity: null,
    },
    {
      id: 'gravityflow.timeline-history',
      raw_source: 'history timestamps',
      presentation_seam: 'FINAL_NO_ADMISSION direct history render',
      discovery_state: 'SOURCE_PROVEN',
      support_state: 'NOT_PROVEN',
      adapter_identity: null,
    },
    {
      id: 'gravityflow.print',
      raw_source: 'dependent Entry Detail/Timeline rendering',
      presentation_seam: 'FINAL_NO_ADMISSION dependent rendering only',
      discovery_state: 'SOURCE_PROVEN',
      support_state: 'NOT_PROVEN',
      adapter_identity: null,
    },
  ];
  for (const surface of residuals) {
    surface.discovery_state = 'SOURCE_PROVEN';
    surface.support_state = 'NOT_PROVEN';
    surface.adapter_identity = null;
    surface.exact_version_disposition = 'FINAL_NO_ADMISSION';
    surface.runtime_evidence = 'g008-flow-residual-no-admission.json';
    if (!flow.surfaces.includes(surface)) flow.surfaces.push(surface);
  }
  input.g008ResidualSourceEvidence = {
    evidence_class: 'G008_RESIDUAL_EXACT_SOURCE_PROBE',
    exact: {
      pgr_sha: identity.head,
      version: '3.1.0',
      sha256: 'b'.repeat(64),
    },
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
        'workflow_info_exposes_format_pattern_filter_only',
        'format_pattern_filter_precedes_due_and_expiration',
        'due_is_direct_operational_getter_render',
        'expiration_is_direct_operational_getter_render',
        'below_workflow_hook_is_after_direct_date_output',
        'date_format_hook_is_format_string_only_and_precedes_operational_values',
        'due_has_no_downstream_value_filter',
        'expiration_has_no_downstream_value_filter',
        'schedule_reads_operational_getter_directly',
        'schedule_prints_directly',
        'schedule_has_no_value_filter',
        'schedule_getter_is_operational_filter',
        'expiration_getter_is_operational_filter',
        'schedule_validation_uses_same_getter',
        'expiration_state_uses_same_getter',
        'shared_format_hook_scopes_submitted_last_updated_due_expiration',
        'flow_format_date_delegates_to_gravityforms',
        'gravityforms_format_date_reaches_date_i18n',
        'wordpress_date_i18n_exposes_supported_filter',
        'wordpress_date_i18n_treats_numeric_input_as_local_timestamp_with_offset',
        'schedule_date_branch_uses_configured_civil_date',
        'schedule_date_field_and_delay_localize_operational_timestamp',
        'schedule_date_timestamp_reads_configured_date',
        'schedule_date_field_timestamp_reads_configured_field_and_offset',
        'schedule_delay_timestamp_uses_step_timestamp_and_offset',
        'step_timestamp_reads_step_scoped_entry_meta',
        'queued_step_status_calls_schedule_renderer',
      ].map((key) => [key, true])),
      timeline_history: Object.fromEntries([
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
      ].map((key) => [key, true])),
      print: Object.fromEntries([
        'reuses_entry_detail_grid',
        'optional_timeline_reuses_entry_detail_timeline',
        'no_print_specific_date_formatter',
        'print_style_hook_is_not_date_seam',
        'workflow_sidebar_not_rendered_by_print',
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
    browser_modes: { enabled: 'enabled', disabled: 'disabled' },
    surfaces: Object.fromEntries(residuals.map((surface) => [surface.id, 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0'])),
  };

  const residualDispositions = {
    'gravityflow.entry-detail.schedule-due-expiration': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
    'gravityflow.timeline-history': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
    'gravityflow.print': 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
  };
  const makeResidualBrowser = (mode) => ({
    evidence_class: 'AUTHENTIC_GRAVITY_FLOW_RESIDUAL_NO_ADMISSION_BROWSER',
    mode,
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    site_timezone: 'Asia/Tehran',
    php_default_timezone: 'UTC',
    entry_detail: {
      url: 'https://example.test/entry',
      due_date_native: 'March 21, 2026',
      timeline_native: ['March 21, 2026 at 1:45 am'],
    },
    print: {
      url: 'https://example.test/print',
      timeline_native: ['March 21, 2026 at 1:45 am'],
    },
    dispositions: structuredClone(residualDispositions),
  });
  input.g008ResidualBrowserEnabledEvidence = makeResidualBrowser('enabled');
  input.g008ResidualBrowserDisabledEvidence = makeResidualBrowser('disabled');

  const makeStatusBrowser = (mode) => ({
    evidence_class: 'AUTHENTIC_GRAVITY_FLOW_STATUS_BROWSER',
    mode,
    exact_persiangravity_commit: identity.head,
    exact_persiangravity_package_sha256: identity.persiangravityPackageSha256,
    exact_gravityflow_version: '3.1.0',
    exact_gravityflow_package_sha256: 'b'.repeat(64),
    site_timezone: 'Asia/Tehran',
    php_default_timezone: 'UTC',
    rows: [{ id: 1, due_date: 'March 21, 2026' }],
    residual_no_admission: {
      surface: 'gravityflow.status.due-date',
      disposition: 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0',
      enabled_and_disabled_expect_native: true,
    },
  });
  input.g008FlowStatusBrowserEnabledEvidence = makeStatusBrowser('enabled');
  input.g008FlowStatusBrowserDisabledEvidence = makeStatusBrowser('disabled');
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
    g009_native_pass_claims_reconciled: 3,
    g008_source_proven_claims_reconciled: 5,
    g008_runtime_admitted_claims_reconciled: 0,
    g008_final_no_admission_claims_reconciled: 0,
  });
  assert.deepEqual(input.g009Registry, beforeG009);
  assert.deepEqual(input.g008Registry, beforeG008);
  assert.equal(input.g008Registry.products[0].surfaces.every((surface) => surface.support_state === 'NOT_PROVEN'), true);
});

test('G-009 downgrade falsification rejects NOT_PROVEN for one required runtime scenario', () => {
  const input = fixtures();
  input.g009RtlEvidence.results.find((item) => item.id === 'gravityforms.frontend-form' && item.observed.viewport?.width === 390).evidence_state = 'NOT_PROVEN';
  expectFailure(input, /gravityforms\.frontend-form.*rtl 390x844.*downgraded to NOT_PROVEN/);
});

test('G-009 missing-evidence falsification rejects a missing required surface scenario', () => {
  const input = fixtures();
  input.g009LtrEvidence.results = input.g009LtrEvidence.results.filter((item) => !(item.id === 'gravityview.admin-list' && item.observed.viewport?.width === 1280));
  expectFailure(input, /gravityview\.admin-list.*ltr 1280x900.*found 0/);
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
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 2);

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

test('combined Inbox and Status admissions reconcile four bounded runtime claims', () => {
  const input = fixtures();
  admitFlowInbox(input);
  admitFlowStatus(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 4);
});

test('G-008 final no-admission claims require exact source plus enabled/disabled runtime reconciliation', () => {
  const input = fixtures();
  closeFlowResiduals(input);
  const result = reconcileQualificationEvidence(input);
  assert.equal(result.g008_final_no_admission_claims_reconciled, 4);
  assert.equal(result.g008_runtime_admitted_claims_reconciled, 0);

  const sourceOnly = fixtures();
  closeFlowResiduals(sourceOnly);
  sourceOnly.g008ResidualNoAdmissionEvidence = null;
  expectFailure(sourceOnly, /gravityflow\.status\.due-date.*residual enabled\/disabled runtime reconciliation evidence is missing/);

  const promoted = fixtures();
  closeFlowResiduals(promoted);
  promoted.g008Registry.products[0].surfaces.find((surface) => surface.id === 'gravityflow.timeline-history').support_state = 'ADMITTED_VERIFIED';
  expectFailure(promoted, /gravityflow\.timeline-history.*FINAL_NO_ADMISSION must retain support_state NOT_PROVEN/);

  const driftedSource = fixtures();
  closeFlowResiduals(driftedSource);
  driftedSource.g008ResidualSourceEvidence.source_contract.timeline_history.header_formats_note_date_directly = false;
  expectFailure(driftedSource, /gravityflow\.timeline-history.*required source flag header_formats_note_date_directly is false/);
});

test('G-008 residual source contract gate rejects missing, empty, missing-flag, false and non-boolean evidence', () => {
  const missing = fixtures();
  closeFlowResiduals(missing);
  delete missing.g008ResidualSourceEvidence.source_contract;
  expectFailure(missing, /gravityflow\.status\.due-date.*source contract is missing or empty/);

  const empty = fixtures();
  closeFlowResiduals(empty);
  empty.g008ResidualSourceEvidence.source_contract.status_due_date = {};
  expectFailure(empty, /gravityflow\.status\.due-date.*source contract is missing or empty/);

  const flagMissing = fixtures();
  closeFlowResiduals(flagMissing);
  delete flagMissing.g008ResidualSourceEvidence.source_contract.timeline_history.timeline_reads_gravityforms_notes;
  expectFailure(flagMissing, /gravityflow\.timeline-history.*required source flag timeline_reads_gravityforms_notes is missing/);

  const falseFlag = fixtures();
  closeFlowResiduals(falseFlag);
  falseFlag.g008ResidualSourceEvidence.source_contract.print.workflow_sidebar_not_rendered_by_print = false;
  expectFailure(falseFlag, /gravityflow\.print.*required source flag workflow_sidebar_not_rendered_by_print is false/);

  const nonBoolean = fixtures();
  closeFlowResiduals(nonBoolean);
  nonBoolean.g008ResidualSourceEvidence.source_contract.entry_detail_schedule_due_expiration.schedule_has_no_value_filter = 'true';
  expectFailure(nonBoolean, /gravityflow\.entry-detail\.schedule-due-expiration.*required source flag schedule_has_no_value_filter must be boolean/);
});

test('G-008 residual browser gate rejects missing or duplicated mode evidence', () => {
  const missingEnabled = fixtures();
  closeFlowResiduals(missingEnabled);
  missingEnabled.g008ResidualBrowserEnabledEvidence = null;
  expectFailure(missingEnabled, /enabled residual browser: browser evidence is missing/);

  const missingDisabled = fixtures();
  closeFlowResiduals(missingDisabled);
  missingDisabled.g008ResidualBrowserDisabledEvidence = null;
  expectFailure(missingDisabled, /disabled residual browser: browser evidence is missing/);

  const enabledCopiedToDisabled = fixtures();
  closeFlowResiduals(enabledCopiedToDisabled);
  enabledCopiedToDisabled.g008ResidualBrowserDisabledEvidence = structuredClone(enabledCopiedToDisabled.g008ResidualBrowserEnabledEvidence);
  expectFailure(enabledCopiedToDisabled, /disabled residual browser: mode must be disabled/);

  const disabledCopiedToEnabled = fixtures();
  closeFlowResiduals(disabledCopiedToEnabled);
  disabledCopiedToEnabled.g008ResidualBrowserEnabledEvidence = structuredClone(disabledCopiedToEnabled.g008ResidualBrowserDisabledEvidence);
  expectFailure(disabledCopiedToEnabled, /enabled residual browser: mode must be enabled/);

  const wrongMode = fixtures();
  closeFlowResiduals(wrongMode);
  wrongMode.g008FlowStatusBrowserDisabledEvidence.mode = 'enabled';
  expectFailure(wrongMode, /disabled Status browser: mode must be disabled/);
});

test('G-008 residual browser gate rejects empty observations and target identity drift', () => {
  const emptyObservation = fixtures();
  closeFlowResiduals(emptyObservation);
  emptyObservation.g008ResidualBrowserEnabledEvidence.entry_detail = {};
  expectFailure(emptyObservation, /Entry Detail observation is empty/);

  const missingTarget = fixtures();
  closeFlowResiduals(missingTarget);
  delete missingTarget.g008ResidualBrowserEnabledEvidence.dispositions['gravityflow.timeline-history'];
  expectFailure(missingTarget, /residual target identity set is missing, duplicated, or unexpected/);

  const wrongTarget = fixtures();
  closeFlowResiduals(wrongTarget);
  delete wrongTarget.g008ResidualBrowserEnabledEvidence.dispositions['gravityflow.print'];
  wrongTarget.g008ResidualBrowserEnabledEvidence.dispositions['gravityflow.print-wrong'] = 'FINAL_NO_ADMISSION_GRAVITY_FLOW_3_1_0';
  expectFailure(wrongTarget, /residual target identity set is missing, duplicated, or unexpected/);
});

test('G-008 residual browser gate rejects package drift and source-only runtime promotion', () => {
  const wrongHash = fixtures();
  closeFlowResiduals(wrongHash);
  wrongHash.g008ResidualBrowserDisabledEvidence.exact_gravityflow_package_sha256 = '0'.repeat(64);
  expectFailure(wrongHash, /disabled residual browser: Gravity Flow package SHA-256 mismatch/);

  const sourceOnly = fixtures();
  closeFlowResiduals(sourceOnly);
  sourceOnly.g008ResidualBrowserEnabledEvidence = null;
  sourceOnly.g008ResidualBrowserDisabledEvidence = null;
  sourceOnly.g008FlowStatusBrowserEnabledEvidence = null;
  sourceOnly.g008FlowStatusBrowserDisabledEvidence = null;
  expectFailure(sourceOnly, /browser evidence is missing/);
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
    'gravityperks.family-baseline',
    'gp-file-upload-pro.frontend',
    'gp-advanced-select.tom-select',
  ]);
  assert.equal(notProven.every((surface) => surface.evidence_state === 'NOT_PROVEN'), true);
});
