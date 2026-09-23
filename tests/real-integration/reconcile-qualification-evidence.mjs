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

function validateG009(registry, artifacts, expectedIdentity) {
  const errors = [];
  const claims = [];

  for (const product of registry.products ?? []) {
    for (const surface of product.surfaces ?? []) {
      if (surface.evidence_state === 'NATIVE_PASS') claims.push({ product, surface });
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
        if (matches[0].evidence_state !== 'NATIVE_PASS') {
          errors.push(`G-009 ${surface.id}: required scenario ${scenario} downgraded to ${matches[0].evidence_state ?? 'MISSING_STATE'}.`);
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
  'gravityflow.entry-detail.schedule-due-expiration': 'entry_detail_schedule_due_expiration',
  'gravityflow.timeline-history': 'timeline_history',
  'gravityflow.print': 'print',
};

const residualBrowserTargets = [
  'gravityflow.entry-detail.schedule-due-expiration',
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
    if (surface.id === 'gravityflow.entry-detail.schedule-due-expiration') {
      if (typeof evidence.entry_detail.due_date_native !== 'string' || evidence.entry_detail.due_date_native.length === 0) {
        errors.push(`G-008 ${surface.id} ${label}: required due-date browser observation is empty.`);
      }
    } else if (surface.id === 'gravityflow.timeline-history') {
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
  expectedIdentity
) {
  const errors = [];
  if (surface.support_state !== 'NOT_PROVEN') {
    errors.push(`G-008 ${surface.id}: FINAL_NO_ADMISSION must retain support_state NOT_PROVEN.`);
  }
  if (surface.adapter_identity !== null) {
    errors.push(`G-008 ${surface.id}: FINAL_NO_ADMISSION must not name a production adapter.`);
  }
  if (surface.runtime_evidence !== 'g008-flow-residual-no-admission.json') {
    errors.push(`G-008 ${surface.id}: FINAL_NO_ADMISSION must bind residual runtime reconciliation evidence.`);
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
    if (!contractKey || !isObject(targetContract) || Object.keys(targetContract).length === 0 || !allEvidenceFlagsTrue(targetContract)) {
      errors.push(`G-008 ${surface.id}: residual source no-admission contract is missing, empty, non-boolean, or not fully proven.`);
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
  } else {
    errors.push(...validateResidualBrowserPair(
      product,
      surface,
      residualBrowserEnabledEvidence,
      residualBrowserDisabledEvidence,
      expectedIdentity
    ));
  }

  if (!isObject(residualRuntimeEvidence) || residualRuntimeEvidence.evidence_class !== 'G008_RESIDUAL_NO_ADMISSION_RECONCILIATION') {
    errors.push(`G-008 ${surface.id}: residual enabled/disabled runtime reconciliation evidence is missing.`);
  } else {
    if (residualRuntimeEvidence.status !== 'PASS') {
      errors.push(`G-008 ${surface.id}: residual runtime reconciliation is not PASS.`);
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
          expectedIdentity
        ));
        continue;
      }

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
  sourceDiscoveryEvidence,
  g008FlowInboxAdmissionEvidence,
  g008FlowStatusAdmissionEvidence,
  g008ResidualSourceEvidence,
  g008ResidualNoAdmissionEvidence,
  g008ResidualBrowserEnabledEvidence,
  g008ResidualBrowserDisabledEvidence,
  g008FlowStatusBrowserEnabledEvidence,
  g008FlowStatusBrowserDisabledEvidence,
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

  const g009 = validateG009(g009Registry, { rtl: g009RtlEvidence, ltr: g009LtrEvidence }, expectedIdentity);
  const g008 = validateG008(
    g008Registry,
    sourceDiscoveryEvidence,
    {
      'g008-flow-inbox-admission.json': g008FlowInboxAdmissionEvidence,
      'g008-flow-status-admission.json': g008FlowStatusAdmissionEvidence,
    },
    g008ResidualSourceEvidence,
    g008ResidualNoAdmissionEvidence,
    g008ResidualBrowserEnabledEvidence,
    g008ResidualBrowserDisabledEvidence,
    g008FlowStatusBrowserEnabledEvidence,
    g008FlowStatusBrowserDisabledEvidence,
    expectedIdentity
  );
  const errors = [...g009.errors, ...g008.errors];
  if (errors.length > 0) throw new EvidenceReconciliationError(errors);

  return {
    status: 'PASS',
    g009_native_pass_claims_reconciled: g009.claims,
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
    sourceDiscoveryEvidence: readJson(path.join(artifactDir, 'source-discovery.json')),
    g008FlowInboxAdmissionEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-inbox-admission.json')),
    g008FlowStatusAdmissionEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-status-admission.json')),
    g008ResidualSourceEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-residual-source-probe.json')),
    g008ResidualNoAdmissionEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-residual-no-admission.json')),
    g008ResidualBrowserEnabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-residual-browser-enabled.json')),
    g008ResidualBrowserDisabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-residual-browser-disabled.json')),
    g008FlowStatusBrowserEnabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-status-browser-enabled.json')),
    g008FlowStatusBrowserDisabledEvidence: readJsonIfPresent(path.join(artifactDir, 'g008-flow-status-browser-disabled.json')),
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
