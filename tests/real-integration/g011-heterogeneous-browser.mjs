/**
 * Two actual GF submissions, not GFFormDisplay::handle_confirmation mocks:
 * Ordinary selects Conditional; AJAX selects Default.
 */
import fs from 'node:fs';
import { chromium } from 'playwright';
const dir = process.env.WU008_ARTIFACT_DIR;
const fixture = JSON.parse(fs.readFileSync(dir + '/g011-heterogeneous-browser-fixture.json', 'utf8'));
const browser = await chromium.launch({ headless: true });
const output = { head: fixture.head, host: fixture.host, status: 'NOT_VERIFIED', cases: {} };
const requireTrue = (ok, message) => { if (!ok) throw Error(message); };
async function submit(mode, selector) {
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  try {
    const response = await page.goto(fixture.urls[mode], { waitUntil: 'domcontentloaded' });
    requireTrue(response?.status() === 200, mode + ': page unavailable');
    await page.locator('input[name="input_1"]').first().fill(selector);
    const date = page.locator('input[name="input_2"]').first();
    requireTrue(await date.count() === 1, mode + ': native Date field missing');
    await date.fill(fixture.field_input);
    await page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click();
    const confirmation = page.locator('.gform_confirmation_message').first();
    await confirmation.waitFor({ state: 'visible', timeout: 25000 });
    const file = dir + '/g011-hetero-browser-' + selector + '.json';
    requireTrue(fs.existsSync(file), mode + ': server selected confirmation evidence missing');
    const observed = JSON.parse(fs.readFileSync(file, 'utf8'));
    const human = {
      full: await confirmation.locator('strong').first().innerText(),
      datetime: await confirmation.locator('em').first().innerText(),
      year: await confirmation.locator('b').first().innerText(),
      raw: await confirmation.locator('i').first().innerText(),
      opaque: await confirmation.locator('code').first().innerText(),
      href: await confirmation.locator('a[href]').first().getAttribute('href'),
      data: await confirmation.locator('a[data-greg]').first().getAttribute('data-greg'),
    };
    const expectedIdentity = selector === 'special' ? 'g011-het-conditional' : 'g011-het-default';
    const checks = {
      actual_saved_entry: observed.entry_id > 0 && observed.form_id === fixture.form_id,
      actual_selected_confirmation: observed.selected_confirmation === expectedIdentity,
      expected_transport: observed.ajax === (mode === 'ajax'),
      host_identity: observed.host === '3.1.1.1' &&
        observed.gf_sha256 === fixture.gf_sha256 && observed.head === fixture.head,
      full_date_jalali: human.full === observed.expected_jalali,
      datetime_jalali: human.datetime.includes(observed.expected_jalali) &&
        human.datetime.includes('در') &&
        /\d{1,2}:\d{2}/u.test(human.datetime),
      date_field_component_jalali: human.year === '۱۴۰۵',
      unsupported_raw_is_native: human.raw === observed.raw_utc,
      opaque_filename_native: human.opaque === 'receipt-' + observed.local_gregorian + '.pdf',
      native_href: human.href === '/?d=' + observed.local_gregorian + '&keep=1',
      native_data: human.data === observed.local_gregorian,
      third_party_text_preserved: await page.locator('.external-prefix').count() === 1 &&
        (await page.locator('.external-prefix').innerText()) === 'THIRD_PARTY_VISIBLE',
      three_admitted_occurrences: observed.candidate_admitted &&
        observed.spans.length === 3 && observed.candidate_reason === 'typed_source_unique_static_alignment',
      no_second_native_render: observed.shared_merge_callback_count === 1,
      downstream_composition: observed.callback_trace.includes('20-after-jalali') &&
        observed.callback_trace.at(-1) === '30-post',
      no_unowned_mutation: observed.checks.byte_exact_unowned,
      no_runtime_pageerror: errors.length === 0,
      all_server_checks: Object.values(observed.checks).every(Boolean),
    };
    const failed = Object.keys(checks).filter(key => !checks[key]);
    output.cases[mode] = {
      status: failed.length ? 'FAIL' : 'PASS', selector,
      entry_id: observed.entry_id, selected_confirmation: observed.selected_confirmation,
      raw_utc: observed.raw_utc, wp_timezone: observed.wp_timezone,
      human, checks, failed,
      aligned_spans: observed.spans,
    };
    requireTrue(!failed.length, mode + ': ' + failed.join(','));
  } finally {
    await page.close();
  }
}
try {
  await submit('ordinary', 'special');
  await submit('ajax', 'normal');
  output.status = 'PASS';
} catch (err) {
  output.status = 'FAIL';
  output.error = String(err);
  throw err;
} finally {
  fs.writeFileSync(dir + '/g011-heterogeneous-browser.json', JSON.stringify(output, null, 2));
  await browser.close();
}