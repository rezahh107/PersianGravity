import fs from 'node:fs';
import path from 'node:path';

const dir = path.join(process.env.WU008_ARTIFACT_DIR || '', 'admin-ui-evidence');
if (!process.env.WU008_ARTIFACT_DIR || !fs.existsSync(dir)) throw Error('UI evidence directory missing.');
const combinations = ['normal-fa_IR', 'normal-en_US', 'disabled-fa_IR', 'disabled-en_US'];
const manifests = combinations.map(name => {
  const file = path.join(dir, 'manifest-' + name + '.json');
  if (!fs.existsSync(file)) throw Error('Required locale/state evidence missing: ' + name);
  const data = JSON.parse(fs.readFileSync(file, 'utf8'));
  if (data.status !== 'CAPTURED' || data.cases.some(test => test.state === 'CAPTURE_FAILED')) {
    throw Error('Incomplete capture in ' + name);
  }
  return data;
});
const escaping = value => String(value).replaceAll('|', '\\|').replaceAll('\n', ' ');
const md = [
  '# WU008 — Authentic PersianGravity admin UI evidence',
  '',
  'Captured by licensed disposable WU008 WordPress/Chromium, not a mock or static HTML rendering.',
  'Each thumbnail opens the underlying full-page JPEG in this permanent evidence branch.',
  'A browser capture is not a claim that every associated interaction works.',
  '',
  '## Exact context',
  '',
  '| Field | Observed |',
  '| --- | --- |',
  '| Source Head | `' + manifests[0].exact_head + '` |',
  '| PHP | `' + manifests[0].runtime.php + '` |',
  '| WordPress | `' + manifests[0].runtime.wordpress + '` |',
  '| Plugin versions | `' + escaping(JSON.stringify(manifests[0].runtime.versions)) + '` |',
  '| Browser | Chromium `' + manifests[0].runtime.chromium + '` / Playwright `' + manifests[0].runtime.playwright + '` |',
  '| DPR | 1 |',
  '| Locale & directions | WordPress fa_IR/RTL; en_US/LTR, separately captured |',
  '| Viewports | 1280x900, 390x844, focused 320x700 |',
  '',
  '## State matrix and clickable contact sheets',
  '',
];
for (const data of manifests) {
  md.push('### ' + data.locale + ' — ' + data.mode, '');
  md.push('| Screen / width | Screenshot | Overflow detector |');
  md.push('| --- | --- | --- |');
  for (const item of data.cases) {
    md.push('| ' + escaping(item.name + ' @ ' + item.width) +
      ' | [<img src="' + encodeURI(item.screenshot) + '" width="300" alt="' +
      escaping(item.name) + ' screenshot" />](' + encodeURI(item.screenshot) + ')' +
      ' | ' + escaping(item.state) + ' (scrollWidth=' +
      (item.measured?.document_scroll_width ?? '?') + ') |');
  }
  md.push('');
  md.push('**Diagnostics classification:** ' +
    'VISUAL_FINDING means an overflow candidate was observed; it is not silently accepted as a UI pass. ' +
    'CAPTURED_NO_DETECTED_OVERFLOW means only the measured page/element overflow checks were negative.', '');
}
md.push('## Detection negative controls', '');
for (const data of manifests.filter(v => v.mode === 'normal')) {
  md.push('- ' + data.locale + ': ' + (data.negative_control?.state || 'MISSING') +
    ', synthetic fixed 800px element in an **isolated HTML page** at 320px. ' +
    'This is not a PersianGravity production UI screenshot.');
  if (data.negative_control?.state !== 'DETECTED') throw Error('Negative control missing for ' + data.locale);
}
md.push('', '## What remains unverified', '');
for (const gap of manifests[0].not_proven) md.push('- ' + gap);
md.push('- Effective font-family is recorded from real computed CSS per screen in manifest JSON; ' +
  'computed font-family does not prove glyph-by-glyph font fallback.');
md.push('- Both normal and temporarily disabled test states are in an isolated database; ' +
  'module switches are restored by the CI workflow.', '');
md.push('## Machine-readable measurements', '');
for (const name of combinations) md.push('- [manifest-' + name + '.json](manifest-' + name + '.json)');
md.push('');
fs.writeFileSync(path.join(dir, 'CONTACT_SHEET.md'), md.join('\n'));
console.log('Contact sheets assembled: ' + manifests.reduce((n, v) => n + v.cases.length, 0) + ' actual screenshots.');
