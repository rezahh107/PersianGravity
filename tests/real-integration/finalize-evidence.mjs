import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const expectedSha = process.env.WU008_PGR_SHA;
if (!artifactDir || !expectedSha) throw new Error('WU008_ARTIFACT_DIR and WU008_PGR_SHA are required.');
fs.mkdirSync(artifactDir, { recursive: true });

const hashFile = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
  const full = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(full) : [full];
});
const readJson = (name) => JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8'));

const required = [
  'environment-manifest.json',
  'package-verification.json',
  'surface-evidence-matrix.json',
  'surface-evidence-matrix.csv',
  'rtl-bidi-evidence.json',
  'js-runtime-evidence.json',
  'browser-diagnostics.json',
  'finding-register.json',
  'installed-persiangravity-sha.txt',
  'form-builder-remediation/browser-evidence.json',
  'form-builder-remediation/form-builder.png',
  'form-builder-remediation/form-builder.html',
];
const missing = required.filter((name) => !fs.existsSync(path.join(artifactDir, name)));

let matrix = [];
let findings = [];
let js = { result: 'NOT_EXECUTED' };
let diagnostics = { result: 'NOT_EXECUTED', summary: {} };
let packageVerification = { packages: [] };
let remediation = { result: 'NOT_EXECUTED' };
try { matrix = readJson('surface-evidence-matrix.json'); } catch {}
try { findings = readJson('finding-register.json'); } catch {}
try { js = readJson('js-runtime-evidence.json'); } catch {}
try { diagnostics = readJson('browser-diagnostics.json'); } catch {}
try { packageVerification = readJson('package-verification.json'); } catch {}
try { remediation = readJson('form-builder-remediation/browser-evidence.json'); } catch {}

const installedSha = fs.existsSync(path.join(artifactDir, 'installed-persiangravity-sha.txt'))
  ? fs.readFileSync(path.join(artifactDir, 'installed-persiangravity-sha.txt'), 'utf8').trim()
  : '';
const exactShaPass = installedSha === expectedSha;

const packageAuthority = {
  'Gravity Forms': { size: Number(process.env.WU008_GF_SIZE), sha256: process.env.WU008_GF_SHA256, version: process.env.WU008_GF_VERSION },
  'Gravity Flow': { size: Number(process.env.WU008_FLOW_SIZE), sha256: process.env.WU008_FLOW_SHA256, version: process.env.WU008_FLOW_VERSION },
  'GravityView': { size: Number(process.env.WU008_VIEW_SIZE), sha256: process.env.WU008_VIEW_SHA256, version: process.env.WU008_VIEW_VERSION },
};
const packages = Array.isArray(packageVerification.packages) ? packageVerification.packages : [];
const packageChecks = Object.entries(packageAuthority).map(([product, authority]) => {
  const item = packages.find((candidate) => candidate.product === product);
  const pass = Boolean(item)
    && item.verification === 'PASS'
    && item.archive_integrity === 'PASS'
    && Number(item.actual_size) === authority.size
    && item.actual_sha256 === authority.sha256
    && item.actual_version === authority.version;
  return { product, pass, authority, observed: item || null };
});
const packagesPass = packageChecks.length === 3 && packageChecks.every((item) => item.pass) && packageVerification.vendor_authenticity === 'NOT_PROVEN';

const uniqueSurfaceIds = new Set(matrix.map((row) => row.surface_id));
const matrixExactly19 = Array.isArray(matrix) && matrix.length === 19 && uniqueSurfaceIds.size === 19;
const executed = matrix.filter((row) => row.runtime_execution === 'PASS').length;
const positiveSurfaces = matrix.filter((row) => Number(row.admitted_message_count) > 0);
const providerPassed = positiveSurfaces.filter((row) => row.provider_proof_status === 'PASS').length;
const zeroRows = matrix.filter((row) => Number(row.admitted_message_count) === 0);
const zeroNa = zeroRows.filter((row) => row.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length;
const rtlPass = matrix.filter((row) => row.rtl_bidi_result === 'PASS').length;
const allSurfacePass = matrixExactly19 && matrix.every((row) => row.overall_surface_status === 'PASS');

const remediationPass = remediation.result === 'PASS'
  && remediation.exact_remediation_sha === expectedSha
  && remediation.gravityforms_version === '3.1.1.1'
  && remediation.pgr_scanner_editor_defined === true
  && remediation.editor_control_activated === true
  && remediation.scanner_controls?.initial_mapping_rows === 7
  && remediation.scanner_controls?.restored_mapping_rows === 7
  && remediation.interaction_state?.profile === 'sayad_v01'
  && String(remediation.interaction_state?.mappings?.qr_version || '') !== ''
  && remediation.provider_proof?.status === 'PASS'
  && remediation.rtl_bidi?.status === 'PASS'
  && Array.isArray(remediation.product_page_errors) && remediation.product_page_errors.length === 0
  && Array.isArray(remediation.material_local_request_failures) && remediation.material_local_request_failures.length === 0;

const diagnosticsPass = diagnostics.result === 'PASS'
  && diagnostics.summary?.all_records_dispositioned === true
  && Number(diagnostics.summary?.blocking_or_unresolved || 0) === 0;
const validClasses = new Set(['BLOCKING_IN_SCOPE','NON_BLOCKING_IN_SCOPE','OUT_OF_SCOPE_UNCLASSIFIED_CONTENT','UPSTREAM_OR_VENDOR_BEHAVIOR','ENVIRONMENT_DEFECT']);
const findingsValid = Array.isArray(findings) && findings.every((finding) => validClasses.has(finding.classification));
const blockingFindings = findings.filter((finding) => finding.blocks_wu008 === true || finding.blocks_acceptance === true);
const findingCounts = Object.fromEntries([...validClasses].map((name) => [name, findings.filter((finding) => finding.classification === name).length]));
const jsPass = js.result === 'PASS'
  && Object.values(js.accepted_script_maps || {}).every((items) => Array.isArray(items) && items.length === 0)
  && Array.isArray(js.provider_translation_json_catalogs_at_runtime) && js.provider_translation_json_catalogs_at_runtime.length === 0
  && Array.isArray(js.observed_persiangravity_provider_json_network_requests) && js.observed_persiangravity_provider_json_network_requests.length === 0
  && Array.isArray(js.observed_persiangravity_script_translation_mutations) && js.observed_persiangravity_script_translation_mutations.length === 0;

const gate = {
  required_files_present: missing.length === 0,
  exact_persiangravity_sha: exactShaPass,
  licensed_packages_3_of_3: packagesPass,
  surface_matrix_exactly_19: matrixExactly19,
  runtime_execution_19_of_19: executed === 19,
  positive_provider_proof_18_of_18: positiveSurfaces.length === 18 && providerPassed === 18,
  zero_admission_1_of_1: zeroRows.length === 1 && zeroNa === 1,
  rtl_bidi_19_of_19: rtlPass === 19,
  surface_status_19_of_19: allSurfacePass,
  form_builder_remediation_functional: remediationPass,
  js_provider_json_boundary: jsPass,
  diagnostics_fully_dispositioned: diagnosticsPass,
  finding_register_valid: findingsValid,
  blocking_findings_zero: blockingFindings.length === 0,
};
const overall = Object.values(gate).every(Boolean) ? 'PASS' : 'BLOCKED';

const readme = `# Final 19-surface PersianGravity real-browser revalidation\n\n- Result: **${overall}**\n- Exact PersianGravity product-under-test SHA: \`${expectedSha}\`\n- Installed SHA match: ${exactShaPass ? 'PASS' : 'FAIL'}\n- Licensed packages: ${packageChecks.filter((item) => item.pass).length}/3 exact verification; vendor authenticity remains **NOT_PROVEN**\n- Surface runtime execution: ${executed}/19\n- Positive-admission provider proofs: ${providerPassed}/18\n- Zero-admission Gravity Flow sidebar: ${zeroNa}/1 \`NOT_APPLICABLE_ZERO_ADMISSION\`\n- RTL/BiDi: ${rtlPass}/19\n- Structured Scanner Form Builder remediation functional proof: ${remediationPass ? 'PASS' : 'FAIL'}\n- JS/provider JSON authority boundary: ${jsPass ? 'PASS' : 'FAIL'}\n- Meaningful browser diagnostics: ${diagnostics.summary?.meaningful_diagnostics ?? 'unknown'}; blocking/unresolved: ${diagnostics.summary?.blocking_or_unresolved ?? 'unknown'}\n- Blocking findings: ${blockingFindings.length}\n- Missing required evidence files: ${missing.length ? missing.join(', ') : 'none'}\n\nEvery promoted page error, browser console error/assertion, request failure, and HTTP >=400 diagnostic is dispositioned in \`browser-diagnostics.json\`; unexplained diagnostics are incompatible with PASS. The package is evidence-only and does not establish vendor authenticity or project closure.\n`;
fs.writeFileSync(path.join(artifactDir, 'README.md'), readme);

const beforeIndex = walk(artifactDir).filter((file) => !file.endsWith('/evidence-index.json') && !file.endsWith('/SHA256SUMS'));
const index = {
  schema_version: '2.0.0',
  overall_result: overall,
  exact_persiangravity_commit: expectedSha,
  installed_persiangravity_commit: installedSha || null,
  vendor_authenticity: 'NOT_PROVEN',
  final_gate: gate,
  summary: {
    packages_passed: packageChecks.filter((item) => item.pass).length,
    packages_total: 3,
    surfaces_executed: executed,
    surfaces_total: 19,
    positive_provider_proofs_passed: providerPassed,
    positive_provider_proofs_total: 18,
    zero_admission_provider_not_applicable: zeroNa,
    rtl_pass: rtlPass,
    form_builder_remediation: remediationPass ? 'PASS' : 'FAIL',
    js_result: js.result || null,
    diagnostic_result: diagnostics.result || null,
    meaningful_diagnostics: diagnostics.summary?.meaningful_diagnostics ?? null,
    diagnostic_blocking_or_unresolved: diagnostics.summary?.blocking_or_unresolved ?? null,
    finding_counts: findingCounts,
    blocking_findings: blockingFindings.length,
  },
  package_checks: packageChecks,
  files: beforeIndex.sort().map((file) => ({
    path: path.relative(artifactDir, file).replaceAll(path.sep, '/'),
    bytes: fs.statSync(file).size,
    sha256: hashFile(file),
  })),
};
fs.writeFileSync(path.join(artifactDir, 'evidence-index.json'), JSON.stringify(index, null, 2) + '\n');

const allFiles = walk(artifactDir).filter((file) => !file.endsWith('/SHA256SUMS')).sort();
fs.writeFileSync(path.join(artifactDir, 'SHA256SUMS'), allFiles.map((file) => `${hashFile(file)}  ${path.relative(artifactDir, file).replaceAll(path.sep, '/')}`).join('\n') + '\n');

console.log(JSON.stringify({ overall, gate, findingCounts, blockingFindings: blockingFindings.length, missing }));
if (overall !== 'PASS') process.exit(1);
