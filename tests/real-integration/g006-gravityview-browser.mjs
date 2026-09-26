import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.G006_GV_ARTIFACT_DIR;
const manifestPath = path.join(artifactDir || '', 'g006-gravityview-runtime.json');
const baseUrl = process.env.G006_GV_BASE_URL;
const adminUser = process.env.G006_GV_ADMIN_USER;
const adminPassword = process.env.G006_GV_ADMIN_PASSWORD;
const expectedHead = process.env.G006_PGR_SHA;

if (!artifactDir || !baseUrl || !adminUser || !adminPassword || !expectedHead || !fs.existsSync(manifestPath)) {
  throw new Error('G006 GravityView browser environment is incomplete.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (
  manifest.canonical_message_count !== 3127 ||
  manifest.canonical_keyset_sha256 !== '3b533294de818bd7e772533512424571c85e5aa7bccb78cfe06b8b6820645c95' ||
  !manifest.page_url ||
  !manifest.admin_edit_url ||
  !manifest.fixture_token
) {
  throw new Error('G006 GravityView runtime manifest does not prove the locked full census.');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1365, height: 950 } });
const diagnostics = { console: [], pageErrors: [], requestFailures: [] };
page.on('console', (msg) => diagnostics.console.push({ type: msg.type(), text: msg.text().slice(0, 1200) }));
page.on('pageerror', (error) => diagnostics.pageErrors.push(String(error?.stack || error).slice(0, 3000)));
page.on('requestfailed', (request) => diagnostics.requestFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));

async function assertHealthy(url, label) {
  const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
  if (!response || !response.ok()) throw new Error(`${label} response failed: ${response?.status()} ${url}`);
  const body = await page.locator('body').innerText();
  if (/There has been a critical error|Fatal error|Parse error/i.test(body)) {
    throw new Error(`${label} rendered a fatal/critical error.`);
  }
  return body;
}

const result = {
  schema_version: 1,
  evidence_class: 'G006_GRAVITYVIEW_REPRESENTATIVE_BROWSER',
  exact_persiangravity_head: expectedHead,
  exact_gravityview_version: manifest.versions?.gravityview,
  canonical_message_count_proven_by_runtime: manifest.canonical_message_count,
  canonical_keyset_sha256_proven_by_runtime: manifest.canonical_keyset_sha256,
  diagnostics,
};

try {
  const frontendBody = await assertHealthy(manifest.page_url, 'GravityView frontend');
  if (!frontendBody.includes(manifest.fixture_token)) {
    throw new Error('GravityView frontend did not render the real fixture entry.');
  }
  const frontendHtml = await page.locator('html').evaluate((node) => ({
    dir: node.getAttribute('dir'),
    lang: node.getAttribute('lang'),
    computedDir: getComputedStyle(node).direction,
  }));
  if (frontendHtml.computedDir !== 'rtl') {
    throw new Error(`GravityView frontend is not RTL under fa_IR: ${JSON.stringify(frontendHtml)}`);
  }
  await page.screenshot({ path: path.join(artifactDir, 'g006-gravityview-frontend.png'), fullPage: true });

  await assertHealthy(`${baseUrl}/wp-login.php`, 'WordPress login');
  await page.locator('#user_login').fill(adminUser);
  await page.locator('#user_pass').fill(adminPassword);
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.locator('#wp-submit').click(),
  ]);
  if (page.url().includes('wp-login.php')) {
    throw new Error('WordPress admin login did not complete.');
  }

  const adminBody = await assertHealthy(manifest.admin_edit_url, 'GravityView admin editor');
  const adminHtml = await page.locator('html').evaluate((node) => ({
    dir: node.getAttribute('dir'),
    lang: node.getAttribute('lang'),
    computedDir: getComputedStyle(node).direction,
  }));
  if (adminHtml.computedDir !== 'rtl') {
    throw new Error(`GravityView admin editor is not RTL under fa_IR: ${JSON.stringify(adminHtml)}`);
  }

  const representativeLabels = ['تنظیمات نما', 'افزودن فیلد', 'نوار جستجو'];
  const visibleLabels = representativeLabels.filter((label) => adminBody.includes(label));
  if (visibleLabels.length === 0) {
    throw new Error('GravityView admin editor did not expose any locked representative Persian labels.');
  }

  await page.screenshot({ path: path.join(artifactDir, 'g006-gravityview-admin.png'), fullPage: true });

  result.frontend = {
    url: page.url(),
    fixture_token_present: true,
    html: frontendHtml,
  };
  result.admin = {
    edit_url: manifest.admin_edit_url,
    html: adminHtml,
    representative_labels_seen: visibleLabels,
  };
  result.status = 'PASS';
} catch (error) {
  result.status = 'FAIL';
  result.error = String(error?.stack || error);
  await page.screenshot({ path: path.join(artifactDir, 'g006-gravityview-browser-failure.png'), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

fs.writeFileSync(
  path.join(artifactDir, 'g006-gravityview-browser.json'),
  JSON.stringify(result, null, 2) + '\n',
);

if (
  result.status !== 'PASS' ||
  diagnostics.pageErrors.length > 0 ||
  diagnostics.requestFailures.length > 0
) {
  process.exit(1);
}

console.log('PASS G006 GravityView representative frontend/admin browser verification');
