/**
 * Real Playwright submissions. No simulated handle_confirmation calls here.
 * Oracle is captured from the actual submitted GF Entry at the server seam.
 */
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const dir = process.env.WU008_ARTIFACT_DIR;
const manifest = JSON.parse(fs.readFileSync(dir + '/g011-browserqual-fixture.json', 'utf8'));
const out = { contract: 'G011_POSTNATIVE_REAL_BROWSER_V1', head: manifest.head, cases: {} };
const browser = await chromium.launch({ headless: true });
const assert = (ok, reason) => { if (!ok) throw new Error(reason); };
const wp = (command) => execFileSync('php', [
  process.env.WU008_WP_CLI, '--path=' + process.env.WU008_WP_PATH, 'eval', command,
], { encoding: 'utf8', timeout: 20000 });
const record = (mode) => JSON.parse(fs.readFileSync(
  dir + '/g011-browserqual-' + mode + '.json', 'utf8'
));
const nativePairs = [
  ['native_ordinary', 'candidate_ordinary'],
  ['native_ajax', 'candidate_ajax'],
  ['native_changed_date', 'changed_date'],
  ['native_changed_html', 'changed_html'],
  ['native_ambiguous', 'ambiguous'],
];
const visited = [];

async function submit(route, mode, { conditional = false } = {}) {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  const ajax = route === 'ajax';
  try {
    const response = await page.goto(manifest.urls[route], { waitUntil: 'domcontentloaded' });
    assert(response?.status() === 200, mode + ': fixture page not HTTP 200');
    await page.locator('input[name="input_1"]').first().fill(conditional ? 'special' : 'ordinary');
    await page.locator('input[name="input_2"]').first().fill(mode);
    await page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click();
    const message = page.locator('.gform_confirmation_message').first();
    await message.waitFor({ state: 'visible', timeout: 25000 });
    const evidencePath = dir + '/g011-browserqual-' + mode + '.json';
    if (!fs.existsSync(evidencePath)) {
      const submissionPath = dir + '/g011-browserqual-submitted-' + mode + '.json';
      const submitted = fs.existsSync(submissionPath) ?
        JSON.parse(fs.readFileSync(submissionPath, 'utf8')) : null;
      const observed = {
        confirmation_visible: true,
        submitted,
        rendered_classes: await message.getAttribute('class'),
        human_occurrences: await message.locator('p.human-a').count(),
        selected_conditional: await message.locator('#g011-q-conditional').count(),
        form_ids: await page.locator('form[id^="gform_"]').evaluateAll(els => els.map(x => x.id)),
      };
      out.cases[mode] = { status: 'NOT_VERIFIED', reason: 'missing_confirmation_boundary_evidence', observed };
      throw new Error(mode + ': missing server confirmation trace; diagnostic=' + JSON.stringify(observed));
    }
    const r = record(mode);
    const checks = {
      entry_persisted: Number.isInteger(r.entry_id) && r.entry_id > 0,
      correct_form: r.form_id === manifest.form_id,
      selected_identity: r.selected_id === (conditional ? manifest.conditional_id : manifest.default_id),
      ajax_path: r.ajax === ajax,
      native_snapshot: r.persisted_entry_same_in_callback && r.persisted_form_same_in_callback,
      shared_callback_count: r.native_callback_count === 1,
      third_party_side_effect_count: r.third_party_pre_side_effect_calls === 1,
      no_secondary_render: r.native_callback_count === 1,
      outside_spans_preserved: r.outside_spans_byte_exact,
      no_page_errors: errors.length === 0,
    };
    const nativeOnly = mode.startsWith('native_') || conditional ||
      mode === 'module_disabled' || mode === 'nonfa';
    const positive = mode.startsWith('candidate_') && !nativeOnly;
    if (conditional) {
      const text = await message.innerText();
      checks.conditional_selected = text.includes('CONDITIONAL_NATIVE') &&
        !text.includes('Submitted on');
      checks.conditional_native = text.includes(r.gregorian) &&
        r.native_fallback_byte_exact && r.spans.length === 0;
    } else {
      const a = await message.locator('p.human-a strong').last().innerText();
      const b = await message.locator('p.human-b em').innerText();
      const machine = await message.locator('p.machine').innerText();
      const data = await message.locator('.multi-g011').getAttribute('data-day');
      const href = await message.locator('#g011-q-link').getAttribute('href');
      const rootText = await message.locator('.multi-g011').innerText();
      const dateForHtml = mode.includes('changed_date') ? '2031/11/30' : r.gregorian;
      const expectedHuman = positive ? r.jalali : dateForHtml;
      checks.both_humans = a === expectedHuman && b === expectedHuman;
      checks.fixed_literal = rootText.includes('fixed G=' + manifest.fixed_gregorian_literal);
      checks.native_file = machine.includes('receipt-' + dateForHtml + '.tar.gz') &&
        machine.includes('report_' + dateForHtml + '-v2.pdf');
      checks.native_path = machine.includes('../record/' + dateForHtml + '/detail');
      checks.native_data = data === dateForHtml;
      checks.native_href = href === '/download/' + dateForHtml + '?keep=1';
      checks.conversion_liveness = positive ? r.admitted && r.spans.length === 2 &&
        r.native_sha256 !== r.output_sha256 : !r.admitted &&
        r.native_fallback_byte_exact;
      if (mode.includes('ambiguous')) {
        checks.ambiguous_html_kept = await message.locator('p.human-a').count() === 2;
      }
      if (mode.includes('changed_html')) {
        checks.third_party_html_kept = await message.locator('p.human-b i').count() === 1;
      }
    }
    const failed = Object.keys(checks).filter(k => !checks[k]);
    out.cases[mode] = {
      route, entry_id: r.entry_id, status: failed.length ? 'FAIL' : 'PASS',
      checks, reason: r.reason, selected_id: r.selected_id,
      native_sha256: r.native_sha256, output_sha256: r.output_sha256,
      pre_trace: r.native_callback_inputs,
      callback_count: r.native_callback_count,
      side_effect_calls: r.third_party_pre_side_effect_calls,
      output_spans: r.spans,
    };
    visited.push(mode);
    assert(!failed.length, mode + ': ' + failed.join(', '));
  } finally {
    await page.close();
  }
}

async function submitUnrelated() {
  const page = await browser.newPage();
  try {
    await page.goto(manifest.urls.unrelated, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="input_1"]').fill('other');
    await page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click();
    const m = page.locator('.gform_confirmation_message').first();
    await m.waitFor({ state: 'visible', timeout: 25000 });
    const correct = (await m.innerText()).includes('UNRELATED_NATIVE_KEEP') &&
      await m.locator('#g011-q-unrelated').count() === 1;
    out.cases.unrelated = { status: correct ? 'PASS' : 'FAIL', native: correct };
    assert(correct, 'unrelated form was unexpectedly modified');
  } finally { await page.close(); }
}
async function submitTransport(type) {
  const page = await browser.newPage();
  try {
    await page.goto(manifest.transport[type].url, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="input_1"]').fill('transport');
    await Promise.all([
      page.waitForURL(url => url.searchParams.has('day'), { timeout: 25000 }),
      page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click(),
    ]);
    const u = new URL(page.url());
    const date = u.searchParams.get('day');
    const valid = /^\d{4}\/\d{2}\/\d{2}$/.test(date ?? '') &&
      u.searchParams.get('keep') === '1';
    out.cases['transport_' + type] = { status: valid ? 'PASS' : 'FAIL', native: valid, day: date };
    assert(valid, type + ' navigation did not retain native Gregorian date parameter');
  } finally { await page.close(); }
}
try {
  for (const [nativeMode, candidateMode] of nativePairs) {
    const route = nativeMode.endsWith('ajax') ? 'ajax' : 'ordinary';
    await submit(route, nativeMode);
    await submit(route, candidateMode);
    const n = out.cases[nativeMode], c = out.cases[candidateMode];
    const equalInputs = JSON.stringify(n.pre_trace) === JSON.stringify(c.pre_trace);
    const sideEffects = n.side_effect_calls === c.side_effect_calls;
    n.matched_candidate = candidateMode;
    c.matched_native = nativeMode;
    c.shared_pre_inputs_equal = equalInputs;
    c.third_party_side_effects_equal = sideEffects;
    assert(equalInputs && sideEffects && n.callback_count === c.callback_count,
      'native/candidate callback input, count or side-effect mismatch: ' + candidateMode);
  }
  await submit('ordinary', 'conditional', { conditional: true });
  const originalLanguage = wp('echo get_option("WPLANG", "");').trim();
  try {
    wp('PGR_Module_Registry::set_enabled("jalali_presentation", false);');
    await submit('ordinary', 'module_disabled');
  } finally {
    wp('PGR_Module_Registry::set_enabled("jalali_presentation", true);');
  }
  try {
    wp('update_option("WPLANG", "en_US");');
    await submit('ordinary', 'nonfa');
  } finally {
    wp('update_option("WPLANG", ' + JSON.stringify(originalLanguage) + ');');
  }
  await submitUnrelated();
  for (const type of ['page', 'redirect']) await submitTransport(type);
  out.status = 'PASS';
} catch (e) {
  out.status = 'FAIL';
  out.error = e.message;
  throw e;
} finally {
  out.executed_modes = visited;
  fs.writeFileSync(dir + '/g011-browserqual-browser.json', JSON.stringify(out, null, 2));
  await browser.close();
}
