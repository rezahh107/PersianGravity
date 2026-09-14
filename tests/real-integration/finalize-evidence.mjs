import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');
fs.mkdirSync(artifactDir, { recursive: true });
const readJson = (name, fallback = null) => {
  const file = path.join(artifactDir, name);
  if (!fs.existsSync(file)) return fallback;
  try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch { return fallback; }
};
const hashFile = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');

const packageJsonl = path.join(artifactDir, 'package-verification.jsonl');
const packages = fs.existsSync(packageJsonl) ? fs.readFileSync(packageJsonl, 'utf8').split(/\r?\n/).filter(Boolean).map((line) => JSON.parse(line)) : [];
const packageVerification = {
  schema_version: '2.0.0', vendor_authenticity: 'NOT_PROVEN', expected_products: 3, verified_products: packages.length,
  verification: packages.length === 3 && packages.every((item) => item.verification === 'PASS') ? 'PASS' : 'FAIL', packages,
};
fs.writeFileSync(path.join(artifactDir, 'package-verification.json'), JSON.stringify(packageVerification, null, 2) + '\n');

const env = readJson('environment-manifest.json', {});
const matrixDoc = readJson('surface-evidence-matrix.json', { surfaces: [] });
const matrix = Array.isArray(matrixDoc?.surfaces) ? matrixDoc.surfaces : [];
const rtlDoc = readJson('rtl-bidi-evidence.json', { surfaces: [] });
const rtl = Array.isArray(rtlDoc?.surfaces) ? rtlDoc.surfaces : [];
const js = readJson('js-runtime-evidence.json', {});
const findingsDoc = readJson('finding-register.json', { findings: [] });
const findings = Array.isArray(findingsDoc?.findings) ? findingsDoc.findings : [];
const blockingFindings = findings.filter((finding) => finding.blocks_wu008 === true || finding.classification === 'BLOCKING_IN_SCOPE' || finding.classification === 'ENVIRONMENT_DEFECT');
const positive = matrix.filter((row) => Number(row.admitted_message_count) > 0);
const zero = matrix.filter((row) => Number(row.admitted_message_count) === 0);
const acceptance = {
  exact_product_sha: env.persiangravity_actual_sha === 'd3d6460a07a2c38b483dac664603a443ce430da0' && env.product_under_test_verified_exact === true,
  packages: packageVerification.verification === 'PASS',
  surfaces_executed: matrix.length === 19,
  surfaces_passed: matrix.length === 19 && matrix.every((row) => row.overall_surface_status === 'PASS'),
  positive_provider_proof: positive.length === 18 && positive.every((row) => row.provider_proof_status === 'PASS'),
  zero_admission: zero.length === 1 && zero[0]?.surface_id === 'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow' && zero[0]?.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION',
  rtl: rtl.length === 19 && rtl.every((row) => row.result === 'PASS'),
  js: js.result === 'PASS',
  blocking_findings: blockingFindings.length === 0,
};
const overall = Object.values(acceptance).every(Boolean) ? 'PASS' : 'BLOCKED';
const classificationCounts = {};
for (const finding of findings) classificationCounts[finding.classification] = (classificationCounts[finding.classification] || 0) + 1;
const summary = {
  overall_result: overall, exact_persiangravity_sha: env.persiangravity_actual_sha || null, package_verification: packageVerification.verification,
  surfaces_executed: matrix.length, surfaces_passed: matrix.filter((row) => row.overall_surface_status === 'PASS').length,
  positive_admission_surfaces: positive.length, positive_provider_proof_pass: positive.filter((row) => row.provider_proof_status === 'PASS').length,
  zero_admission_provider_na: zero.filter((row) => row.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length,
  fallback_controls_observed: matrix.filter((row) => row.fallback_control_observation?.status === 'PASS').length,
  rtl_pass: rtl.filter((row) => row.result === 'PASS').length, rtl_fail: rtl.filter((row) => row.result === 'FAIL').length,
  js_runtime_result: js.result || 'NOT_EXECUTED', findings_by_classification: classificationCounts, blocking_findings: blockingFindings.map((finding) => finding.id), acceptance,
};
fs.writeFileSync(path.join(artifactDir, 'acceptance-summary.json'), JSON.stringify(summary, null, 2) + '\n');

const readme = `# WU-008 Licensed Runtime Evidence\n\nOverall result: **${overall}**\n\n- Product under test: PersianGravity \`${env.persiangravity_actual_sha || 'NOT_PROVEN'}\`\n- WordPress: ${env.wordpress_version || 'NOT_PROVEN'} / locale ${env.locale || 'NOT_PROVEN'}\n- Browser: ${env.browser?.name || 'NOT_PROVEN'} ${env.browser?.version || ''}\n- Licensed package verification: ${packageVerification.verification} (${packages.length}/3)\n- Surface records: ${matrix.length}/19 executed; ${matrix.filter((row) => row.overall_surface_status === 'PASS').length}/19 PASS\n- Positive-admission provider proof: ${positive.filter((row) => row.provider_proof_status === 'PASS').length}/18 PASS\n- Zero-admission provider proof: ${zero.filter((row) => row.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length}/1 NOT_APPLICABLE_ZERO_ADMISSION\n- RTL/BiDi: ${rtl.filter((row) => row.result === 'PASS').length}/19 PASS\n- JS/provider-JSON boundary: ${js.result || 'NOT_EXECUTED'}\n- Blocking findings: ${blockingFindings.length}\n\nThis artifact is evidence for the bounded 19 accepted surfaces only. It does not prove complete localization of Gravity Forms, Gravity Flow, GravityView, or the wider Gravity ecosystem. Vendor authenticity remains **NOT_PROVEN**.\n\n## Review order\n\n1. \`environment-manifest.json\`\n2. \`package-verification.json\`\n3. \`surface-evidence-matrix.json\` / \`.csv\`\n4. \`rtl-bidi-evidence.json\`\n5. \`js-runtime-evidence.json\`\n6. \`finding-register.json\`\n7. \`runtime/\`, \`dom/\`, and \`screenshots/\`\n8. \`SHA256SUMS\`\n`;
fs.writeFileSync(path.join(artifactDir, 'README.md'), readme);

function walk(dir, base = dir) {
  const out = [];
  for (const name of fs.readdirSync(dir).sort()) {
    const abs = path.join(dir, name);
    const rel = path.relative(base, abs).replaceAll(path.sep, '/');
    const stat = fs.statSync(abs);
    if (stat.isDirectory()) out.push(...walk(abs, base)); else out.push({ abs, rel, size: stat.size });
  }
  return out;
}
let files = walk(artifactDir).filter(({ rel }) => !['evidence-index.json','SHA256SUMS'].includes(rel));
const index = { schema_version: '2.0.0', generated_utc: new Date().toISOString(), overall_result: overall, files: files.map(({ abs, rel, size }) => ({ path: rel, size_bytes: size, sha256: hashFile(abs) })) };
fs.writeFileSync(path.join(artifactDir, 'evidence-index.json'), JSON.stringify(index, null, 2) + '\n');
files = walk(artifactDir).filter(({ rel }) => rel !== 'SHA256SUMS');
fs.writeFileSync(path.join(artifactDir, 'SHA256SUMS'), files.map(({ abs, rel }) => `${hashFile(abs)}  ${rel}`).join('\n') + '\n');
console.log(JSON.stringify(summary, null, 2));
if (process.argv.includes('--gate') && overall !== 'PASS') process.exitCode = 1;
