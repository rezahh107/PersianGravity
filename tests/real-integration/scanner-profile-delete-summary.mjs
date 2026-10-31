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

function rgb(css) {
  if (typeof css !== 'string' || !/^rgba?\(/.test(css)) throw Error('Missing computed CSS color');
  const channels = css.match(/\d+/g)?.slice(0,3).map(Number);
  if (channels?.length !== 3) throw Error('Malformed computed CSS color: ' + css);
  return channels;
}
function brightness(css) {
  const c = rgb(css).map(n => {
    const s = n / 255;
    return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return c[0] * 0.2126 + c[1] * 0.7152 + c[2] * 0.0722;
}
function ratio(a,b) {
  const [lighter,darker] = [brightness(a),brightness(b)].sort((x,y)=>y-x);
  return (lighter + 0.05) / (darker + 0.05);
}
function verifiedVisual(c,locale,width) {
  const invalid = why => { throw Error('Computed destructive-button regression: ' + locale + '/' + width + ': ' + why); };
  const v = c.visual;
  if (!c.checks.includes('destructive_visual_default_hover_focus_contrast') ||
      v?.authority !== 'chromium-computed-style-wordpress') invalid('visual proof absent');
  for (const phase of ['default','hover','focus']) {
    const confirm = v.confirm?.[phase], cancel = v.cancel?.[phase];
    if (!confirm || !cancel) invalid('missing '+phase+' computed-state pair');
    const [r,g,b] = rgb(confirm.background);
    if (r < 100 || r <= 1.8*g || r <= 1.6*b) invalid('Confirm is not destructive red: '+phase);
    if (ratio(confirm.foreground,confirm.background) < 4.5) invalid('Confirm contrast <4.5: '+phase);
    const cancelRGB=rgb(cancel.background);
    if (Math.max(...cancelRGB)-Math.min(...cancelRGB)>24) invalid('Cancel is not neutral: '+phase);
    if (ratio(cancel.foreground,cancel.background)<4.5) invalid('Cancel contrast <4.5: '+phase);
  }
  if (ratio(v.confirm.default.background,v.cancel.default.background) < 3 ||
      ratio(v.confirm.focus.background,v.cancel.focus.background)<3) invalid('button backgrounds indistinguishable');
  if (ratio(v.confirm.hover.background,v.confirm.default.background)<1.1) invalid('hover state invisible');
  for (const type of ['confirm','cancel']) {
    const focus = v[type].focus;
    if (focus.focusVisible!==true || focus.outline==='none' || !(focus.outlineWidth>=2)) {
      invalid(type+' keyboard focus is invisible');
    }
  }
  for (const [phase, key] of [['default','Default'],['hover','Hover'],['focus','Focus']]) {
    if (typeof v.contrast?.['confirm'+key]!=='number' ||
        typeof v.contrast?.['cancel'+key]!=='number' ||
        Math.abs(ratio(v.confirm[phase].foreground,v.confirm[phase].background)-v.contrast['confirm'+key])>0.03 ||
        Math.abs(ratio(v.cancel[phase].foreground,v.cancel[phase].background)-v.contrast['cancel'+key])>0.03) {
      invalid('contrast report does not match computed colors: '+phase);
    }
  }
  if (typeof v.contrast?.backgrounds!=='number' ||
      Math.abs(ratio(v.confirm.default.background,v.cancel.default.background)-v.contrast.backgrounds)>0.03) {
    invalid('background comparison does not match computed colors');
  }
}

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
        !c.checks.includes('built_in_non_deletable') || !c.checks.includes('snapshot_nonce_bound') ||
        !c.checks.includes('no_horizontal_overflow')) {
      throw Error('Missing responsive or negative-control proof for ' + locale + ' ' + width);
    }
    verifiedVisual(c,locale,width);
    if (width === 320 && !['wrong_id_nonce_rejected','missing_nonce_rejected','wrong_label_rejected',
      'built_in_post_rejected','anonymous_post_rejected','real_post_deleted',
      'repeat_post_refused','stale_get_refused','wrong_snapshot_nonce_rejected',
      'missing_snapshot_rejected','no_js_navigation_and_form'].every(x=>c.checks.includes(x))) {
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
    '- The destructive Confirm has a computed solid-red default/hover/keyboard-focus treatment, WCAG AA text contrast (>=4.5:1), and visibly differs from the neutral Cancel in every tested viewport',
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
