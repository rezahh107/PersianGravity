import fs from 'node:fs';
import path from 'node:path';

const root = process.env.WU008_ARTIFACT_DIR;
const head = process.env.WU008_PGR_SHA;
if (!root || !/^[a-f0-9]{40}$/.test(head || '')) throw Error('Missing exact WU008 evidence authority');
const dir = path.join(root, 'scanner-delete-evidence');
const docs = ['# WU008 — Authenticated Scanner Profile deletion decision', '',
  'These are **real logged-in WordPress/Chromium** captures from a disposable WU008 environment.',
  'Source commit: `' + head + '`', '',
  'The UI screenshots do not establish that a human understands the decision. Server persistence',
  'and unchanged pre-existing Gravity Forms mapping metadata are separately verified via GFAPI.', ''];
const paths = new Set();
for (const locale of ['fa_IR','en_US']) {
  const report = JSON.parse(fs.readFileSync(path.join(dir, locale + '-report.json'),'utf8'));
  const persisted = JSON.parse(fs.readFileSync(path.join(root,'scanner-delete-' + locale + '-persistence.json'),'utf8'));
  if (report.state !== 'PASS' || report.exact_head !== head || report.locale !== locale ||
      report.cases.length !== 3 || persisted.locale !== locale ||
      !persisted.deleted_profile_absent || !persisted.form_mapping_unchanged || !persisted.built_in_preserved) {
    throw Error('Invalid authentic deletion/persistence evidence for ' + locale);
  }
  let expected = [];
  for (const width of [1280,390,320]) {
    const c = report.cases.find(x => x.width === width);
    if (!c || c.state !== 'PASS' || c.js_enabled !== (width !== 320) ||
        !c.checks.includes('destructive_get_no_mutation') ||
        !c.checks.includes('keyboard_order_and_visible_focus') ||
        !c.checks.includes('built_in_non_deletable') || !c.checks.includes('no_horizontal_overflow')) {
      throw Error('Missing responsive or negative-control proof for ' + locale + ' ' + width);
    }
    if (width === 320 && !['wrong_id_nonce_rejected','missing_nonce_rejected','wrong_label_rejected',
      'built_in_post_rejected','anonymous_post_rejected','real_post_deleted',
      'repeat_post_refused','stale_get_refused','no_js_navigation_and_form'].every(x=>c.checks.includes(x))) {
      throw Error('Incomplete destructive security proof for ' + locale);
    }
    expected.push(...['list','confirmation','cancel','missing','built-in',...(width===320?['deleted','stale']:[])]
      .map(stage => locale + '-' + width + '-' + stage + '.jpg'));
  }
  if (report.screenshots.length !== expected.length || expected.some((x,i)=>report.screenshots[i]!==x)) {
    throw Error('Incomplete screenshot scenario matrix for '+locale);
  }
  for (const name of expected) {
    const filepath = path.join(dir,name);
    const content = fs.readFileSync(filepath);
    if (content.length < 1000 || content[0] !== 0xff || content[1] !== 0xd8 ||
        content.at(-2) !== 0xff || content.at(-1) !== 0xd9) throw Error('Invalid authentic screenshot '+name);
    if (paths.has(name)) throw Error('Duplicate screenshot '+name);
    paths.add(name);
  }
  fs.copyFileSync(path.join(root,'scanner-delete-' + locale + '-persistence.json'),
    path.join(dir,locale+'-persistence.json'));
  docs.push('## '+locale, '',
    '- Direction: ' + (locale==='fa_IR' ? 'RTL' : 'LTR'),
    '- Genuine registered profile removed by authenticated **POST**, and original GFAPI field metadata unchanged',
    '- Destructive GET, wrong ID, invalid/missing nonce, wrong name, built-in, anonymous and repeated POST rejected',
    '- At 320 CSS px, navigation, Cancel and Confirm work with **JavaScript disabled**', '');
  for (const width of [1280,390,320]) {
    docs.push('### '+width+' CSS px', '');
    for (const stage of ['list','confirmation','cancel','missing','built-in',...(width===320?['deleted','stale']:[])]) {
      const n = locale+'-'+width+'-'+stage+'.jpg';
      docs.push('['+stage+' — full screenshot]('+encodeURIComponent(n)+')', '',
        '!['+locale+' '+width+' '+stage+']('+encodeURIComponent(n)+')', '');
    }
  }
}
docs.push('## Limitations', '',
  '- No real users or screen-reader announcements were tested.',
  '- This is an exact disposable WordPress/Gravity Forms runtime, not production.',
  '- No form metadata was rewritten in the executed test; this is not a census of all production forms.', '');
fs.writeFileSync(path.join(dir,'README.md'),docs.join('\n'));
console.log('SCANNER_DELETE_EVIDENCE PASS: 2 locales, 3 viewport widths, 34 screenshots, 2 persisted mapping comparisons');
