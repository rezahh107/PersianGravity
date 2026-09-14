import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.PGR_SCANNER_ARTIFACT_DIR;
const adminPassword = process.env.PGR_SCANNER_ADMIN_PASSWORD;
if (!artifactDir || !adminPassword) throw new Error('PGR_SCANNER_ARTIFACT_DIR and PGR_SCANNER_ADMIN_PASSWORD are required.');

const manifest = JSON.parse(fs.readFileSync(path.join(artifactDir, 'runtime-manifest.json'), 'utf8'));
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
const page = await context.newPage();
const pageErrors = [];
const consoleMessages = [];
const requestFailures = [];

page.on('pageerror', (error) => pageErrors.push({ message: String(error?.message || error), stack: String(error?.stack || '') }));
page.on('console', (message) => consoleMessages.push({ type: message.type(), text: message.text().slice(0, 2000) }));
page.on('requestfailed', (request) => {
  requestFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' });
});

async function readJsonEventually(file, attempts = 30) {
  for (let i = 0; i < attempts; i += 1) {
    if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
    await page.waitForTimeout(100);
  }
  throw new Error(`Evidence file not produced: ${file}`);
}

let evidence;
let failure = null;
try {
  const login = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (!login?.ok()) throw new Error(`Login page HTTP ${login?.status()}`);
  await page.fill('#user_login', 'runtime_admin');
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
    page.click('#wp-submit'),
  ]);
  await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 15000 });

  const response = await page.goto(manifest.form_builder_url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (!response?.ok()) throw new Error(`Form Builder HTTP ${response?.status()}`);
  await page.locator('#adminmenu').waitFor({ state: 'attached', timeout: 15000 });
  await page.locator(`#field_${manifest.scanner_field_id}`).waitFor({ state: 'attached', timeout: 15000 });
  await page.waitForTimeout(1000);

  const editorDefined = await page.evaluate(() => typeof window.PGRScannerEditor === 'object' && typeof window.PGRScannerEditor.setProfile === 'function' && typeof window.PGRScannerEditor.setMapping === 'function');
  if (!editorDefined) throw new Error('PGRScannerEditor was not defined by the real Form Builder script block.');

  const editorControlActivated = await page.evaluate((scannerId) => {
    const button = document.querySelector(`#gfield_edit_${scannerId}`);
    if (!button) return false;
    button.click();
    return true;
  }, manifest.scanner_field_id);
  if (!editorControlActivated) throw new Error('Structured Scanner Form Builder edit control was not found.');
  await page.locator('#advanced_tab_toggle').click();
  await page.locator('#advanced_tab').waitFor({ state: 'visible', timeout: 15000 });
  await page.locator('#pgr_scanner_profile').waitFor({ state: 'visible', timeout: 15000 });
  await page.locator('[data-pgr-scanner-mapping="qr_version"]').waitFor({ state: 'visible', timeout: 15000 });

  const initialRows = await page.locator('[data-pgr-scanner-mapping]').count();
  if (initialRows !== 7) throw new Error(`Expected 7 Sayad mapping controls, observed ${initialRows}.`);

  await page.selectOption('#pgr_scanner_profile', '');
  const clearedProfile = await page.evaluate((scannerId) => {
    const scanner = window.form?.fields?.find((field) => Number(field.id) === Number(scannerId));
    return { profile: scanner?.scanner_profile, mappings: scanner?.scanner_mappings };
  }, manifest.scanner_field_id);
  if (clearedProfile.profile !== '') throw new Error(`Profile control did not update scanner model to blank: ${JSON.stringify(clearedProfile)}`);

  await page.selectOption('#pgr_scanner_profile', 'sayad_v01');
  await page.locator('[data-pgr-scanner-mapping="qr_version"]').waitFor({ state: 'visible', timeout: 15000 });
  const restoredRows = await page.locator('[data-pgr-scanner-mapping]').count();
  if (restoredRows !== 7) throw new Error(`Restored Sayad profile did not initialize 7 mapping rows; observed ${restoredRows}.`);

  const targetValue = String(manifest.mapping_target_field_id);
  const targetOptionExists = await page.locator(`[data-pgr-scanner-mapping="qr_version"] option[value="${targetValue}"]`).count();
  if (!targetOptionExists) throw new Error('Expected text-field mapping target is absent from Scanner mapping control.');
  await page.selectOption('[data-pgr-scanner-mapping="qr_version"]', targetValue);

  const interactionState = await page.evaluate((scannerId) => {
    const scanner = window.form?.fields?.find((field) => Number(field.id) === Number(scannerId));
    return {
      profile: scanner?.scanner_profile ?? null,
      mappings: scanner?.scanner_mappings ?? null,
      editorDefined: typeof window.PGRScannerEditor === 'object',
    };
  }, manifest.scanner_field_id);
  if (interactionState.profile !== 'sayad_v01' || String(interactionState.mappings?.qr_version) !== targetValue) {
    throw new Error(`Scanner profile/mapping interaction did not update the real Form Builder model: ${JSON.stringify(interactionState)}`);
  }

  const rtl = await page.evaluate(() => {
    const html = document.documentElement;
    const body = document.body;
    const menu = document.querySelector('#adminmenuwrap');
    const profile = document.querySelector('#pgr_scanner_profile');
    const mappings = [...document.querySelectorAll('[data-pgr-scanner-mapping]')];
    const visible = (node) => {
      if (!node) return false;
      const style = getComputedStyle(node);
      const rect = node.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    };
    const inViewport = (node) => {
      const rect = node.getBoundingClientRect();
      return rect.left >= -8 && rect.right <= window.innerWidth + 8;
    };
    const codeNodes = [...document.querySelectorAll('.pgr-scanner-editor-mapping-key')];
    return {
      htmlDir: html.getAttribute('dir'),
      bodyHasRtlClass: body.classList.contains('rtl'),
      adminMenuOnRight: menu ? menu.getBoundingClientRect().left > window.innerWidth / 2 : false,
      profileVisible: visible(profile),
      profileDirection: profile ? getComputedStyle(profile).direction : null,
      controlsClipped: [profile, ...mappings].filter(visible).some((node) => !inViewport(node)),
      mappingCodesLtr: codeNodes.length > 0 && codeNodes.every((node) => node.getAttribute('dir') === 'ltr'),
      mappingControlCount: mappings.length,
    };
  });
  if (rtl.htmlDir !== 'rtl' || !rtl.bodyHasRtlClass || !rtl.adminMenuOnRight || !rtl.profileVisible || rtl.controlsClipped || !rtl.mappingCodesLtr) {
    throw new Error(`RTL/BiDi regression check failed: ${JSON.stringify(rtl)}`);
  }

  const trace = await readJsonEventually(path.join(artifactDir, 'provider-trace.json'));
  const providerIdentity = (trace.gettext || []).find((row) => row.msgid === manifest.provider_identity.msgid && row.translation === manifest.provider_identity.translation);
  const providerFile = (trace.translation_files || []).find((row) => row.domain === 'gravityforms' && /persian-gravityforms\/languages\/providers\/gravityforms\/gravityforms-fa_IR\.(?:l10n\.php|mo)$/.test(row.file || ''));
  if (!providerIdentity || !providerFile) throw new Error('Existing surface-bound Gravity Forms Persian provider proof was not observed.');

  const productPageErrors = pageErrors.filter((item) => /SyntaxError|PGRScannerEditor|pgr_structured_scanner|persian[- ]gravity/i.test(`${item.message}\n${item.stack}`));
  if (productPageErrors.length > 0) throw new Error(`PersianGravity-caused page error observed: ${JSON.stringify(productPageErrors)}`);

  const localRequestFailures = requestFailures.filter((item) => {
    try { return new URL(item.url).hostname === '127.0.0.1'; } catch { return false; }
  });
  if (localRequestFailures.length > 0) throw new Error(`Local WordPress request failures observed: ${JSON.stringify(localRequestFailures)}`);

  evidence = {
    result: 'PASS',
    exact_remediation_sha: manifest.remediation_sha_installed,
    gravityforms_version: manifest.gravityforms_version,
    locale: manifest.locale,
    form_builder_loaded: true,
    pgr_scanner_editor_defined: editorDefined,
    editor_control_activated: editorControlActivated,
    scanner_controls: { initial_mapping_rows: initialRows, restored_mapping_rows: restoredRows },
    interaction_state: interactionState,
    provider_proof: { status: 'PASS', identity: providerIdentity, provider_file: providerFile.file },
    rtl_bidi: { status: 'PASS', ...rtl },
    page_errors: pageErrors,
    product_page_errors: productPageErrors,
    console_messages: consoleMessages,
    request_failures: requestFailures,
    browser: { name: 'Chromium', version: browser.version() },
  };
} catch (error) {
  failure = String(error?.stack || error);
  evidence = {
    result: 'FAIL',
    failure,
    page_errors: pageErrors,
    console_messages: consoleMessages,
    request_failures: requestFailures,
    browser: { name: 'Chromium', version: browser.version() },
  };
}

await page.screenshot({ path: path.join(artifactDir, 'form-builder.png'), fullPage: true }).catch(() => {});
fs.writeFileSync(path.join(artifactDir, 'form-builder.html'), `${await page.content().catch(() => '<!-- unavailable -->')}\n`);
fs.writeFileSync(path.join(artifactDir, 'browser-evidence.json'), `${JSON.stringify(evidence, null, 2)}\n`);
await browser.close();

if (failure) {
  console.error(failure);
  process.exit(1);
}
console.log(JSON.stringify({ result: evidence.result, interaction: evidence.interaction_state, provider: evidence.provider_proof.status, rtl: evidence.rtl_bidi.status, page_errors: evidence.page_errors.length }));
