import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) throw new Error('WU008_ARTIFACT_DIR is required.');
fs.mkdirSync(artifactDir, { recursive: true });

const hashFile = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
  const full = path.join(dir, entry.name);
  return entry.isDirectory() ? walk(full) : [full];
});

const required = [
  'environment-manifest.json',
  'package-verification.json',
  'surface-evidence-matrix.json',
  'surface-evidence-matrix.csv',
  'rtl-bidi-evidence.json',
  'js-runtime-evidence.json',
  'finding-register.json',
];
const missing = required.filter((name) => !fs.existsSync(path.join(artifactDir, name)));

let matrix = [];
let findings = [];
let js = { result: 'NOT_EXECUTED' };
let browserResults = null;
try { matrix = JSON.parse(fs.readFileSync(path.join(artifactDir, 'surface-evidence-matrix.json'), 'utf8')); } catch {}
try { findings = JSON.parse(fs.readFileSync(path.join(artifactDir, 'finding-register.json'), 'utf8')); } catch {}
try { js = JSON.parse(fs.readFileSync(path.join(artifactDir, 'js-runtime-evidence.json'), 'utf8')); } catch {}
try { browserResults = JSON.parse(fs.readFileSync(path.join(artifactDir, 'browser-results.json'), 'utf8')); } catch {}

const executed = matrix.filter((r) => r.runtime_execution === 'PASS').length;
const providerPassed = matrix.filter((r) => r.admitted_message_count > 0 && r.provider_proof_status === 'PASS').length;
const zeroNa = matrix.filter((r) => r.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length;
const rtlPass = matrix.filter((r) => r.rtl_bidi_result === 'PASS').length;
const blocking = findings.filter((f) => f.blocks_wu008).length;
const overall = missing.length === 0 && matrix.length === 19 && executed === 19 && providerPassed === 18 && zeroNa === 1 && rtlPass === 19 && js.result === 'PASS' && blocking === 0 ? 'PASS' : 'BLOCKED';

const readme = `# WU-008 licensed real-browser evidence\n\n- Result: **${overall}**\n- Product under test: \`${process.env.WU008_PGR_SHA || 'UNKNOWN'}\`\n- Surface runtime execution: ${executed}/19\n- Positive-admission provider proofs: ${providerPassed}/18\n- Zero-admission provider N/A: ${zeroNa}/1\n- RTL/BiDi PASS: ${rtlPass}/19\n- JS/catalog boundary: ${js.result || 'UNKNOWN'}\n- Blocking findings: ${blocking}\n- Missing required artifact files at finalization: ${missing.length ? missing.join(', ') : 'none'}\n\nThis artifact is evidence-only. Vendor package authenticity remains NOT_PROVEN. A green GitHub Actions job is not itself the evidence; inspect the matrix, request traces, DOM captures, screenshots, findings, and hashes.\n`;
fs.writeFileSync(path.join(artifactDir, 'README.md'), readme);

const beforeIndex = walk(artifactDir).filter((file) => !file.endsWith('/evidence-index.json') && !file.endsWith('/SHA256SUMS'));
const index = {
  schema_version: '1.0.0',
  overall_result: overall,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  summary: {
    surfaces_executed: executed,
    surfaces_total: 19,
    positive_provider_proofs_passed: providerPassed,
    positive_provider_proofs_total: 18,
    zero_admission_provider_not_applicable: zeroNa,
    rtl_pass: rtlPass,
    js_result: js.result || null,
    blocking_findings: blocking,
    browser_summary: browserResults?.summary || null,
  },
  files: beforeIndex.sort().map((file) => ({
    path: path.relative(artifactDir, file).replaceAll(path.sep, '/'),
    bytes: fs.statSync(file).size,
    sha256: hashFile(file),
  })),
};
fs.writeFileSync(path.join(artifactDir, 'evidence-index.json'), JSON.stringify(index, null, 2) + '\n');

const allFiles = walk(artifactDir).filter((file) => !file.endsWith('/SHA256SUMS')).sort();
const sums = allFiles.map((file) => `${hashFile(file)}  ${path.relative(artifactDir, file).replaceAll(path.sep, '/')}`).join('\n') + '\n';
fs.writeFileSync(path.join(artifactDir, 'SHA256SUMS'), sums);

console.log(JSON.stringify({ overall, executed, providerPassed, zeroNa, rtlPass, blocking, missing }));
if (overall !== 'PASS') process.exitCode = 1;
