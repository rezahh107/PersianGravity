import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');
fs.mkdirSync(artifactDir, { recursive: true });

const expectedSha = process.env.WU008_PGR_SHA || '';
const hashFile = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
  const full = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(full) : [full];
});
const readJson = (name, fallback = null) => {
  try { return JSON.parse(fs.readFileSync(path.join(artifactDir, name), 'utf8')); } catch { return fallback; }
};

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

const matrix = readJson('surface-evidence-matrix.json', []);
const packages = readJson('package-verification.json', []);
const rtl = readJson('rtl-bidi-evidence.json', []);
const js = readJson('js-runtime-evidence.json', { result: 'NOT_EXECUTED' });
const diagnostics = readJson('browser-diagnostics.json', { summary: { result: 'MISSING', blocking_diagnostics: 1 } });
const findings = readJson('finding-register.json', []);
const remediation = readJson('form-builder-remediation/browser-evidence.json', { result: 'MISSING' });
const installedSha = fs.existsSync(path.join(artifactDir, 'installed-persiangravity-sha.txt'))
  ? fs.readFileSync(path.join(artifactDir, 'installed-persiangravity-sha.txt'), 'utf8').trim()
  : '';

const executed = matrix.filter((r) => r.runtime_execution === 'PASS').length;
const providerPassed = matrix.filter((r) => r.admitted_message_count > 0 && r.provider_proof_status === 'PASS').length;
const zeroNa = matrix.filter((r) => r.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length;
const rtlPass = matrix.filter((r) => r.rtl_bidi_result === 'PASS').length;
const blockingFindings = findings.filter((f) => f.blocks_wu008 === true || f.blocks_acceptance === true).length;
const packagePass = Array.isArray(packages) && packages.length === 3 && packages.every((row) => row.result === 'PASS' || row.status === 'PASS' || row.verified === true);
const diagPass = diagnostics?.summary?.result === 'PASS'
  && diagnostics?.summary?.strict_surfaces_total === 19
  && diagnostics?.summary?.strict_surfaces_passed === 19
  && diagnostics?.summary?.blocking_diagnostics === 0
  && diagnostics?.summary?.pageerrors_total === 0;
const remediationPass = remediation?.result === 'PASS'
  && remediation?.pgr_scanner_editor_defined === true
  && remediation?.editor_control_activated === true
  && remediation?.scanner_controls?.initial_mapping_rows === 7
  && remediation?.scanner_controls?.restored_mapping_rows === 7
  && remediation?.interaction_state?.profile === 'sayad_v01'
  && remediation?.provider_proof?.status === 'PASS'
  && remediation?.rtl_bidi?.status === 'PASS'
  && Array.isArray(remediation?.page_errors)
  && remediation.page_errors.length === 0;
const shaPass = expectedSha !== '' && installedSha === expectedSha;

const gates = {
  installed_persiangravity_sha_exact: shaPass,
  package_verification_3_of_3: packagePass,
  matrix_exactly_19_rows: matrix.length === 19,
  runtime_execution_19_of_19: executed === 19,
  positive_provider_proof_18_of_18: providerPassed === 18,
  zero_admission_not_applicable_1_of_1: zeroNa === 1,
  rtl_bidi_19_of_19: rtlPass === 19 && Array.isArray(rtl) && rtl.length === 19,
  js_provider_json_boundary: js.result === 'PASS',
  diagnostics_fail_closed: diagPass,
  form_builder_remediation_functional: remediationPass,
  no_blocking_findings: blockingFindings === 0,
  required_files_present: missing.length === 0,
};
const overall = Object.values(gates).every(Boolean) ? 'PASS' : 'BLOCKED';

const classificationKeys = ['BLOCKING_IN_SCOPE','NON_BLOCKING_IN_SCOPE','OUT_OF_SCOPE_UNCLASSIFIED_CONTENT','UPSTREAM_OR_VENDOR_BEHAVIOR','ENVIRONMENT_DEFECT'];
const findingCounts = Object.fromEntries(classificationKeys.map((key) => [key, findings.filter((f) => f.classification === key).length]));

const readme = `# WU-008 final post-remediation real-browser revalidation\n\n- Result: **${overall}**\n- Product under test: \`${expectedSha || 'UNKNOWN'}\`\n- Installed product SHA exact: ${shaPass ? 'PASS' : 'FAIL'}\n- Licensed packages: ${packagePass ? '3/3 PASS' : 'BLOCKED'}\n- Runtime execution: ${executed}/19\n- Positive-admission provider proof: ${providerPassed}/18\n- Zero-admission provider proof: ${zeroNa}/1 NOT_APPLICABLE_ZERO_ADMISSION\n- RTL/BiDi: ${rtlPass}/19\n- Form Builder remediation functional proof: ${remediationPass ? 'PASS' : 'BLOCKED'}\n- JS/provider JSON authority boundary: ${js.result || 'UNKNOWN'}\n- Strict browser diagnostics: ${diagnostics?.summary?.result || 'UNKNOWN'}; blocking=${diagnostics?.summary?.blocking_diagnostics ?? 'UNKNOWN'}; pageerrors=${diagnostics?.summary?.pageerrors_total ?? 'UNKNOWN'}\n- Blocking findings: ${blockingFindings}\n- Vendor authenticity: NOT_PROVEN\n\nThis is evidence-only. A green workflow is not acceptance evidence by itself; inspect the 19-row matrix, classified browser diagnostics, focused Form Builder evidence, finding register, screenshots/DOM captures, and SHA256SUMS.\n`;
fs.writeFileSync(path.join(artifactDir, 'README.md'), readme);

const beforeIndex = walk(artifactDir).filter((file) => !file.endsWith('/evidence-index.json') && !file.endsWith('/SHA256SUMS'));
const index = {
  schema_version: '2.0.0',
  overall_result: overall,
  exact_persiangravity_commit: expectedSha || null,
  installed_persiangravity_commit: installedSha || null,
  gates,
  summary: {
    packages_verified: packagePass ? 3 : packages.filter?.((row) => row.result === 'PASS' || row.status === 'PASS' || row.verified === true).length || 0,
    surfaces_executed: executed,
    surfaces_total: 19,
    positive_provider_proofs_passed: providerPassed,
    positive_provider_proofs_total: 18,
    zero_admission_provider_not_applicable: zeroNa,
    rtl_pass: rtlPass,
    js_result: js.result || null,
    form_builder_remediation_result: remediation.result || null,
    browser_diagnostics: diagnostics.summary || null,
    finding_counts: findingCounts,
    blocking_findings: blockingFindings,
    missing_required_files: missing,
  },
  files: beforeIndex.sort().map((file) => ({
    path: path.relative(artifactDir, file).replaceAll(path.sep, '/'),
    bytes: fs.statSync(file).size,
    sha256: hashFile(file),
  })),
};
fs.writeFileSync(path.join(artifactDir, 'evidence-index.json'), `${JSON.stringify(index, null, 2)}\n`);

const allFiles = walk(artifactDir).filter((file) => !file.endsWith('/SHA256SUMS')).sort();
fs.writeFileSync(
  path.join(artifactDir, 'SHA256SUMS'),
  `${allFiles.map((file) => `${hashFile(file)}  ${path.relative(artifactDir, file).replaceAll(path.sep, '/')}`).join('\n')}\n`,
);

console.log(JSON.stringify({ overall, gates, findingCounts, missing }));
if (overall !== 'PASS') process.exit(1);
