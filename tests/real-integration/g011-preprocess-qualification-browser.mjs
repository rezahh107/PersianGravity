/**
 * Single real ordinary HTTP submission. Stop at first decisive callback
 * input-observation falsifier; do not broaden to AJAX/reentrancy after it.
 */
import fs from 'node:fs';
import { chromium } from 'playwright';

const dir = process.env.WU008_ARTIFACT_DIR;
if (!dir) throw new Error('WU008_ARTIFACT_DIR required');
const manifest = JSON.parse(fs.readFileSync(dir + '/g011-confirmation.json', 'utf8'));
const browser = await chromium.launch({ headless: true });
try {
  const page = await browser.newPage();
  const response = await page.goto(manifest.urls.ordinary, { waitUntil: 'domcontentloaded' });
  if (!response || response.status() !== 200) throw new Error('Normal form GET did not return 200');
  await page.locator('input[name="input_1"]').first().fill('G011 Ordinary Provenance Falsifier');
  await page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click();
  const element = page.locator('.gform_confirmation_message').first();
  await element.waitFor({ state: 'visible', timeout: 20000 });
  const text = await element.innerText();
  const rendered = { ordinary_submission: true, selected_confirmation: text.includes('G011_BROWSER_OK'),
    marker_leaked_to_visible_dom: text.includes('PGR_G011_PREPROCESS_Q_OPAQUE_6EFC42_END'),
    body_contains_jalali: /[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(text) };
  fs.writeFileSync(dir + '/g011-preprocess-browser.json', JSON.stringify({ contract: 'G011_PREPROCESS_FALSIFIER', rendered }, null, 2));
  if (!rendered.selected_confirmation || rendered.marker_leaked_to_visible_dom) {
    throw new Error('Selected confirmation or marker cleanup failed: ' + JSON.stringify(rendered));
  }
  console.log('G011 real ordinary submission rendered; evaluate callback observation in PHP fixture');
} finally {
  await browser.close();
}
