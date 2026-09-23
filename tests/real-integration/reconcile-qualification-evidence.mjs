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

function validateG008(registry, sourceEvidence, runtimeEvidenceByFile, expectedIdentity) {
  const errors = [];
  const sourceClaims = [];
  const runtimeClaims = [];

  if (!isObject(sourceEvidence)) {
    return { errors: ['G-008 source-discovery evidence artifact is missing.'], sourceClaims: 0, runtimeClaims: 0 };
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

  return { errors, sourceClaims: sourceClaims.length, runtimeClaims: runtimeClaims.length };
}

export function reconcileQualificationEvidence({
  g009Registry,
  g008Registry,
  g009RtlEvidence,
  g009LtrEvidence,
  sourceDiscoveryEvidence,
  g008FlowInboxAdmissionEvidence,
  g008FlowStatusAdmissionEvidence,
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
    expectedIdentity
  );
  const errors = [...g009.errors, ...g008.errors];
  if (errors.length > 0) throw new EvidenceReconciliationError(errors);

  return {
    status: 'PASS',
    g009_native_pass_claims_reconciled: g009.claims,
    g008_source_proven_claims_reconciled: g008.sourceClaims,
    g008_runtime_admitted_claims_reconciled: g008.runtimeClaims,
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
