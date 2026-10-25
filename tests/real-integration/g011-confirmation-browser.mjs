/**
 * Authentic HTTP/Chromium G-011 ordinary + AJAX confirmation smoke.
 * Reads only the bounded fixture manifest, never a vendor source file.
 */
import fs from 'node:fs';
import { chromium } from 'playwright';

const dir = process.env.WU008_ARTIFACT_DIR;
if (!dir) throw new Error('WU008_ARTIFACT_DIR required');
const manifest = JSON.parse(fs.readFileSync(dir + '/g011-confirmation.json', 'utf8'));
const browser = await chromium.launch({ headless: true });
const results = {};
try {
  for (const mode of ['ordinary', 'ajax']) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    try {
      const response = await page.goto(manifest.urls[mode], { waitUntil: 'domcontentloaded' });
      if (!response || response.status() !== 200) throw new Error(mode + ': page not served');
      await page.locator('input[name="input_1"]').first().fill('G011 Browser Test');
      await page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click();
      const message = page.locator('.gform_confirmation_message').first();
      await message.waitFor({ state: 'visible', timeout: 20000 });
      const text = await message.innerText();
      const checks = {
        selected: text.includes('G011_BROWSER_OK'),
        jalali: /[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(text),
        untouched: text.includes('2026-03-21 unchanged'),
        native_gregorian: /20[0-9]{2}[-/][0-9]{2}[-/][0-9]{2}/u.test(text),
      };
      if (!checks.selected || !checks.jalali || !checks.untouched) {
        throw new Error(mode + ': confirmation contract failed: ' + JSON.stringify(checks));
      }
      if (errors.length) throw new Error(mode + ': browser runtime error: ' + errors.join(', '));
      results[mode] = { submission: 'CONFIRMED', jalali: true, untouched_text: true, pageerror_count: 0 };
    } finally {
      await page.close();
    }
  }
  fs.writeFileSync(dir + '/g011-browser.json', JSON.stringify({ contract: 'G011_AUTHENTIC_CHROMIUM', results }, null, 2));
  console.log('G011 Chromium ordinary/AJAX confirmation assertions OK');
} finally {
  await browser.close();
}
