import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

/**
 * Two real frontend submissions against configured GF 3.1.1.1 confirmations.
 */
const dir = process.env.G011_ARTIFACT_DIR;
assert.ok(dir, 'G011_ARTIFACT_DIR is required');
const fixture = JSON.parse(fs.readFileSync(path.join(dir, 'g011-browser-fixture.json'), 'utf8'));
const browser = await chromium.launch({ headless: true });
const results = [];
try {
  for (const mode of ['ordinary', 'ajax']) {
    const page = await browser.newPage({ locale: 'fa-IR' });
    const url = fixture.pages[mode];
    assert.ok(url.startsWith('http://127.0.0.1:8080/'), 'Unexpected fixture URL: ' + url);
    const errors = [];
    page.on('pageerror', (error) => errors.push(String(error)));
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    const form = page.locator('#gform_' + fixture.form_id);
    await form.waitFor({ timeout: 20000 });
    await page.locator('#gform_submit_button_' + fixture.form_id).click();
    const message = page.locator('[id^="gform_confirmation_message_"]');
    await message.waitFor({ state: 'visible', timeout: 30000 });
    const bodyText = await message.innerText();
    assert.match(bodyText, /created=[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u, mode + ' did not render Jalali created date: ' + bodyText);
    assert.match(bodyText, /today=[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u, mode + ' did not render Jalali today');
    assert.match(bodyText, /raw=20[0-9]{2}-[0-9]{2}-[0-9]{2} /u, mode + ' raw UTC output changed');
    assert.ok(bodyText.includes('Unrelated HTML is unchanged.'), mode + ' unrelated HTML missing');
    assert.ok(!bodyText.includes('PGRG011'), mode + ' escaped token markers');
    if (mode === 'ajax') {
      assert.equal(page.url(), url, 'AJAX confirmation unexpectedly navigated');
    }
    await page.screenshot({ path: path.join(dir, 'g011-' + mode + '.png'), fullPage: true });
    results.push({ mode, result: 'VERIFIED', url_unchanged: page.url() === url, page_errors: errors });
    await page.close();
  }
  fs.writeFileSync(path.join(dir, 'g011-browser-results.json'), JSON.stringify(results, null, 2));
  console.log('G011_BROWSER_ORDINARY_AJAX_PASS');
} finally {
  await browser.close();
}
