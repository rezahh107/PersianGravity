/**
 * EC-PG83-F01: authentic Chromium HTTP + AJAX qualification for the actual
 * production gform_confirmation Adapter. No test-only candidate adapters.
 */
import fs from 'node:fs';
import { chromium } from 'playwright';

const dir = process.env.WU008_ARTIFACT_DIR;
if (!dir) throw new Error('EC-PG83-F01 requires WU008_ARTIFACT_DIR');
const fixture = JSON.parse(fs.readFileSync(dir + '/g011-ec-pg83-f01-production.json', 'utf8'));
const report = {
  contract: 'EC-PG83-F01-BROWSER-PRODUCTION-V1',
  head: process.env.WU008_PGR_SHA,
  environment: 'CHROMIUM_REAL_HTTP_AJAX',
  cases: {},
  verdict: 'NOT_PROVEN',
};
let browser;
try {
  browser = await chromium.launch({ headless: true });
  for (const selected of ['default', 'conditional']) {
    for (const mode of ['ordinary', 'ajax']) {
      const id = selected + '_' + mode;
      const page = await browser.newPage();
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      try {
        const response = await page.goto(fixture.urls[mode], { waitUntil: 'domcontentloaded' });
        if (!response || response.status() !== 200) throw new Error('HTTP page unavailable');
        await page.locator('input[name="input_1"]').first().fill(selected === 'conditional' ? 'special' : 'ordinary');
        await page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click();
        const message = page.locator('.gform_confirmation_message').first();
        await message.waitFor({ state: 'visible', timeout: 20000 });
        const text = await message.innerText();
        const html = await message.innerHTML();
        const date = await message.locator('[data-created]').first().getAttribute('data-created');
        const tests = {
          selected: text.includes(selected === 'default' ? 'F01_DEFAULT' : 'F01_CONDITIONAL') &&
            !text.includes(selected === 'default' ? 'F01_CONDITIONAL' : 'F01_DEFAULT'),
          source_native_date: typeof date === 'string' && /^\d{4}\/\d{2}\/\d{2}$/.test(date),
          extensionless: typeof date === 'string' && text.includes('receipt-' + date + ' '),
          relative_fragment: typeof date === 'string' && text.includes('./archive/report#release-' + date + ' '),
          existing_path: typeof date === 'string' && text.includes('/archive/' + date + ' '),
          existing_pdf: typeof date === 'string' && text.includes('receipt-' + date + '.pdf '),
          existing_tagled_fragment: typeof date === 'string' && text.includes(date + '#details'),
          existing_opaque: typeof date === 'string' && html.includes('<code>opaque ' + date + '</code>'),
          human_jalali: /Created=[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(text),
          page_errors: errors.length === 0,
        };
        report.cases[id] = {
          status: Object.values(tests).every(Boolean) ? 'PASS' : 'FAILED',
          assertions: tests,
          date,
          visible_text: text,
          html,
          page_errors: errors,
        };
      } catch (error) {
        report.cases[id] = { status: 'NOT_PROVEN', error: String(error) };
      } finally {
        await page.close();
      }
    }
  }
} catch (error) {
  report.setup_error = String(error);
} finally {
  if (browser) await browser.close();
}
report.verdict = Object.keys(report.cases).length === 4 &&
  Object.values(report.cases).every(c => c.status === 'PASS') ? 'PASS' :
  Object.values(report.cases).some(c => c.status === 'FAILED') ? 'CONFIRMED_FINDING' : 'NOT_PROVEN';
fs.writeFileSync(dir + '/g011-ec-pg83-f01-browser.json', JSON.stringify(report, null, 2));
console.log('EC-PG83-F01 genuine Chromium qualification: ' + report.verdict);
