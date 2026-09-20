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

const entriesUrl = `${baseUrl}/wp-admin/admin.php?page=gf_entries&view=entries&id=${manifest.form_id}`;
const entriesResponse = await page.goto(entriesUrl, { waitUntil: 'domcontentloaded' });
const bodyText = await page.locator('body').innerText();
const html = await page.content();
const evidencePrefix = `${artifactDir}/entries-list-${mode}`;
const navigation = {
  requested_url: entriesUrl,
  final_url: page.url(),
  response_status: entriesResponse ? entriesResponse.status() : null,
  title: await page.title(),
  body_text_length: bodyText.length,
  html_length: html.length,
  entry_list_form_count: await page.locator('#entry_list_form').count(),
};

fs.writeFileSync(`${evidencePrefix}.txt`, bodyText);
fs.writeFileSync(`${evidencePrefix}.html`, html);
fs.writeFileSync(`${evidencePrefix}-navigation.json`, JSON.stringify(navigation, null, 2));
await page.screenshot({ path: `${evidencePrefix}.png`, fullPage: true });

if (!entriesResponse || entriesResponse.status() >= 400 || bodyText.length === 0 || navigation.entry_list_form_count !== 1) {
  throw new Error(`Entries List navigation did not render a valid document: ${JSON.stringify(navigation)}`);
}

if (!Array.isArray(manifest.active_grid_columns) || !manifest.active_grid_columns.includes('date_created')) {
  throw new Error('Runtime fixture did not prove date_created as an active Entries List column.');
}

if (mode === 'enabled') {
  if (!bodyText.includes(manifest.expected_display)) {
    throw new Error(`Enabled Entries List did not visibly contain ${manifest.expected_display}`);
  }
} else {
  if (bodyText.includes(manifest.expected_display)) {
    throw new Error('Disabled Entries List still contains PersianGravity Jalali presentation.');
  }
}

await browser.close();
console.log(JSON.stringify({ mode, form_id: manifest.form_id, entry_id: manifest.entry_id, visible: true }));
