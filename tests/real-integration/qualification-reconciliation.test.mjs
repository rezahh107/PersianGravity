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
