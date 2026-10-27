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
      const link = message.locator('#g011-browser-link');
      const href = await link.getAttribute('href');
      const date = await link.getAttribute('data-created');
      const preserve = await link.getAttribute('data-preserve');
      const label = await link.innerText();
      const relativeText = await message.locator('#g011-relative-paths').innerText();
      const nativeRelativePaths = typeof date === 'string' &&
        ['/archive/', './archive/', '../archive/', 'archive/']
          .every((prefix) => relativeText.includes(prefix + date));
      const visibleCreatedLabel = /Created=[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(relativeText);
      const tagLedText = await message.locator('#g011-tagled').innerText();
      const nativeTagLed = typeof date === 'string' && [
        date + '/detail',
        date + '#details',
        'receipt-' + date + '.pdf',
      ].every((token) => tagLedText.includes(token));
      const checks = {
        selected: text.includes('G011_BROWSER_OK'),
        native_href: typeof href === 'string' && typeof date === 'string' &&
          href === '/?day=' + date + '&keep=1' && /^\d{4}\/\d{2}\/\d{2}$/.test(date),
        native_data: typeof date === 'string' && !/[۰-۹]/u.test(date),
        visible_link: /[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(label),
        unrelated_data: preserve === 'fixed',
        relative_paths_native: nativeRelativePaths,
        human_created_label: visibleCreatedLabel,
        tag_led_native: nativeTagLed,
        jalali: /[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(text),
        untouched: text.includes('2026-03-21 unchanged'),
        native_gregorian: /20[0-9]{2}[-/][0-9]{2}[-/][0-9]{2}/u.test(text),
      };
      if (Object.entries(checks).some(([key, value]) => key !== 'native_gregorian' && !value)) {
        throw new Error(mode + ': confirmation contract failed: ' + JSON.stringify(checks));
      }
      if (errors.length) throw new Error(mode + ': browser runtime error: ' + errors.join(', '));
      results[mode] = { submission: 'CONFIRMED', jalali: true, native_href: true, native_data: true, visible_link: true, unrelated_data: true, relative_paths_native: true, human_created_label: true, tag_led_native: true, untouched_text: true, pageerror_count: 0 };
    } finally {
      await page.close();
    }
  }
  // Owner-approved Matrix B: conditional selection also works in BOTH HTTP
  // and AJAX, not merely an independent conditional unit/CLI invocation.
  for (const mode of ['ordinary', 'ajax']) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    try {
      const response = await page.goto(manifest.urls[mode], { waitUntil: 'domcontentloaded' });
      if (!response || response.status() !== 200) throw new Error(mode + ': conditional page not served');
      await page.locator('input[name="input_1"]').first().fill('special');
      await page.locator('form[id^="gform_"] input[type="submit"], form[id^="gform_"] button[type="submit"]').first().click();
      const message = page.locator('.gform_confirmation_message').first();
      await message.waitFor({ state: 'visible', timeout: 20000 });
      const text = await message.innerText();
      const selected = text.includes('G011_CONDITIONAL') && !text.includes('G011_BROWSER_OK');
      const converted = /[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u.test(text);
      if (!selected || !converted || errors.length) {
        throw new Error(mode + ': conditional text verification failed: ' +
          JSON.stringify({ selected, converted, errors }));
      }
      results['conditional_' + mode] = {
        submission: 'CONFIRMED', selected: 'CONDITIONAL', jalali: true, pageerror_count: 0,
      };
    } finally {
      await page.close();
    }
  }
  fs.writeFileSync(dir + '/g011-browser.json', JSON.stringify({ contract: 'G011_AUTHENTIC_CHROMIUM', results }, null, 2));
  console.log('G011 Chromium ordinary/AJAX confirmation assertions OK');
} finally {
  await browser.close();
}
