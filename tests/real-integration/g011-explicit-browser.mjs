import fs from 'node:fs';
import { chromium } from 'playwright';

const dir = process.env.WU008_ARTIFACT_DIR;
const fixture = JSON.parse(fs.readFileSync(dir + '/g011-explicit-production-runtime.json', 'utf8'));
const browser = await chromium.launch({ headless: true });
const results = [];
try {
  for (const scenario of [
    { mode: 'ordinary', name: 'ordinary', marker: 'G011_DEFAULT' },
    { mode: 'ordinary', name: 'special', marker: 'G011_CONDITIONAL' },
    { mode: 'ajax', name: 'special', marker: 'G011_CONDITIONAL' },
  ]) {
    const page = await browser.newPage();
    const response = await page.goto(fixture.pages[scenario.mode], { waitUntil: 'domcontentloaded' });
    if (!response || response.status() >= 400) throw Error('G011 page HTTP error: ' + scenario.mode);
    const formId = fixture.form_id;
    await page.locator('#input_' + formId + '_1').fill(scenario.name);
    await page.locator('#input_' + formId + '_2').fill('03/20/2026');
    await page.locator('#gform_submit_button_' + formId).click();
    await page.locator('#gform_confirmation_message_' + formId).waitFor({ timeout: 30000 });
    const confirmation = await page.locator('#gform_confirmation_message_' + formId).innerText();
    for (const required of [
      scenario.marker,
      'Visit=۱۴۰۴/۱۲/۲۹',
      'Native=03/20/2026',
      'Created=۱۴۰',
      'Wrong={pgr_jdate_field_1}',
      'Unknown={pgr_jdate_field_99}',
      'Untouched=keep & safe',
    ]) {
      if (!confirmation.includes(required)) {
        throw Error('G011 browser ' + scenario.mode + '/' + scenario.name + ' missing ' + required + ': ' + confirmation.slice(0, 350));
      }
    }
    const wrongMarker = scenario.marker === 'G011_DEFAULT' ? 'G011_CONDITIONAL' : 'G011_DEFAULT';
    if (confirmation.includes(wrongMarker)) throw Error('G011 wrong confirmation selected.');
    results.push({ mode: scenario.mode, choice: scenario.name, status: 'PASS', url: page.url() });
    await page.close();
  }
} finally {
  fs.writeFileSync(dir + '/g011-explicit-browser.json',
    JSON.stringify({ head: fixture.head, gf_version: fixture.gf_version, results }, null, 2));
  await browser.close();
}
if (results.length !== 3) throw Error('G011 browser only passed ' + results.length + '/3 paths.');
console.log('G011 real browser submission PASS 3/3');
