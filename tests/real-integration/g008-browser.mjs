import fs from 'node:fs';
import process from 'node:process';
import { chromium } from 'playwright';

const baseUrl = process.env.G008_BASE_URL;
const password = process.env.G008_ADMIN_PASSWORD;
const artifactDir = process.env.G008_ARTIFACT_DIR;
const mode = process.env.G008_BROWSER_MODE || 'enabled';
if (!baseUrl || !password || !artifactDir) throw new Error('Missing G008 browser environment.');

const manifest = JSON.parse(fs.readFileSync(`${artifactDir}/g008-runtime.json`, 'utf8'));
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();

await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
await page.fill('#user_login', 'runtime_admin');
await page.fill('#user_pass', password);
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('#wp-submit'),
]);

await page.goto(`${baseUrl}/wp-admin/admin.php?page=gf_entries&id=${manifest.form_id}`, { waitUntil: 'networkidle' });
const bodyText = await page.locator('body').innerText();

if (mode === 'enabled') {
  if (!bodyText.includes(manifest.expected_display)) {
    throw new Error(`Enabled Entries List did not visibly contain ${manifest.expected_display}`);
  }
  await page.screenshot({ path: `${artifactDir}/entries-list-enabled.png`, fullPage: true });
} else {
  if (bodyText.includes(manifest.expected_display)) {
    throw new Error('Disabled Entries List still contains PersianGravity Jalali presentation.');
  }
  await page.screenshot({ path: `${artifactDir}/entries-list-disabled.png`, fullPage: true });
}

await browser.close();
console.log(JSON.stringify({ mode, form_id: manifest.form_id, entry_id: manifest.entry_id, visible: true }));
