import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import {
  evaluateOperationDiagnostics,
  formatDiagnosticGateFailure,
  snapshotDiagnostics,
} from './browser-diagnostics.mjs';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const profile = process.env.WU008_G009_PROFILE;
if (!artifactDir || !manifestPath || !adminPassword || !['rtl', 'ltr'].includes(profile)) {
  throw new Error('WU008 artifact/manifest/admin password and WU008_G009_PROFILE=rtl|ltr are required.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
for (const field of ['page_url', 'login_url', 'admin_url', 'gravityflow_frontend_inbox_url']) {
  if (typeof manifest[field] !== 'string' || manifest[field] === '') throw new Error(`Manifest field ${field} is required.`);
}

const expectedDirection = profile === 'rtl' ? 'rtl' : 'ltr';
const expectedLocale = profile === 'rtl' ? 'fa-IR' : 'en-US';
const allowedStates = new Set([
  'NATIVE_PASS',
  'ADAPTER_REQUIRED_AND_VERIFIED',
  'NOT_PROVEN',
  'NOT_APPLICABLE',
  'FAIL_CLOSED_VERSION_DRIFT',
]);
const runtimeOrigin = new URL(manifest.page_url).origin;
const results = [];
const diagnostics = { console: [], pageErrors: [], requestFailures: [], requestsStarted: 0 };
const requestSequences = new WeakMap();

function result(id, state, scenario, observed, proves, doesNotProve) {
  if (!allowedStates.has(state)) throw new Error(`Illegal G-009 state: ${state}`);
  results.push({ id, evidence_state: state, scenario, observed, proves, does_not_prove: doesNotProve });
}

function bindDiagnostics(page) {
  page.on('console', (msg) => diagnostics.console.push({ type: msg.type(), text: msg.text().slice(0, 1200) }));
  page.on('pageerror', (error) => diagnostics.pageErrors.push(String(error?.stack || error).slice(0, 3000)));
  page.on('request', (request) => {
    diagnostics.requestsStarted += 1;
    requestSequences.set(request, diagnostics.requestsStarted);
  });
  page.on('requestfailed', (request) => diagnostics.requestFailures.push({
    url: request.url().slice(0, 1000),
    method: request.method(),
    error: request.failure()?.errorText || 'unknown',
    requestSequence: requestSequences.get(request) ?? null,
  }));
}

async function gated(page, id, fn) {
  const baseline = snapshotDiagnostics(diagnostics);
  let value;
  let failure;
  try {
    value = await fn();
  } catch (error) {
    failure = error;
  }
  await page.waitForTimeout(75).catch(() => {});
  const gate = evaluateOperationDiagnostics(id, diagnostics, baseline, [runtimeOrigin]);
  if (failure || !gate.ok) {
    const message = failure ? String(failure?.stack || failure) : formatDiagnosticGateFailure(gate);
    throw new Error(`${id}: ${message}`);
  }
  return value;
}

async function computed(page, selector) {
  return page.locator(selector).first().evaluate((el) => {
    const style = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return {
      direction: style.direction,
      textAlign: style.textAlign,
      paddingInlineStart: style.paddingInlineStart,
      paddingInlineEnd: style.paddingInlineEnd,
      unicodeBidi: style.unicodeBidi,
      rect: { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, width: rect.width, height: rect.height },
      viewport: { width: innerWidth, height: innerHeight },
    };
  });
}

async function computedLocator(locator) {
  return locator.first().evaluate((el) => {
    const style = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return {
      tagName: el.tagName.toLowerCase(),
      id: el.id || null,
      className: typeof el.className === 'string' ? el.className.slice(0, 300) : null,
      direction: style.direction,
      textAlign: style.textAlign,
      paddingInlineStart: style.paddingInlineStart,
      paddingInlineEnd: style.paddingInlineEnd,
      unicodeBidi: style.unicodeBidi,
      rect: { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, width: rect.width, height: rect.height },
      viewport: { width: innerWidth, height: innerHeight },
    };
  });
}

function rectVisible(state) {
  return state.rect.width > 0 && state.rect.left >= -1 && state.rect.right <= state.viewport.width + 1;
}

async function documentMetrics(page) {
  return page.evaluate(() => ({
    htmlDirAttribute: document.documentElement.getAttribute('dir'),
    htmlDirection: getComputedStyle(document.documentElement).direction,
    bodyDirection: getComputedStyle(document.body).direction,
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
    lang: document.documentElement.lang,
  }));
}

async function login(page) {
  const response = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Login page failed: ${response?.status()}`);
  await page.fill('#user_login', 'runtime_admin');
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);
  await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 10000 });
}

async function qualifyGravityForms(browser, viewport) {
  const page = await browser.newPage({ viewport });
  bindDiagnostics(page);
  const observed = await gated(page, `G009-GF-${profile}-${viewport.width}`, async () => {
    const response = await page.goto(manifest.page_url, { waitUntil: 'domcontentloaded' });
    if (!response?.ok()) throw new Error(`GF frontend response failed: ${response?.status()}`);
    const form = `#gform_${manifest.form_id}`;
    await page.locator(form).waitFor({ timeout: 15000 });
    const doc = await documentMetrics(page);
    const formState = await computed(page, form);
    const input = page.locator(`${form} input[type="text"]`).first();
    await input.focus();
    await input.fill('شناسه ID-1234 user@example.invalid');
    if (!(await input.evaluate((el) => el === document.activeElement))) throw new Error('GF text control did not retain focus.');
    const inputState = await input.evaluate((el) => {
      const style = getComputedStyle(el);
      return { direction: style.direction, textAlign: style.textAlign, unicodeBidi: style.unicodeBidi };
    });
    await page.keyboard.press('Tab');
    const focusAdvanced = await page.evaluate(() => document.activeElement !== document.body && document.activeElement !== document.documentElement);
    return { viewport, doc, form: formState, input: inputState, focusAdvanced, typedValue: await input.inputValue() };
  });
  const directionPass = observed.doc.htmlDirAttribute === expectedDirection
    && observed.doc.htmlDirection === expectedDirection
    && observed.doc.lang.toLowerCase().startsWith(expectedLocale.split('-')[0].toLowerCase())
    && observed.form.direction === expectedDirection;
  const geometryPass = rectVisible(observed.form) && observed.doc.scrollWidth <= observed.doc.clientWidth + 2;
  const focusPass = observed.focusAdvanced && observed.typedValue.includes('ID-1234') && observed.typedValue.includes('user@example.invalid');
  result(
    'gravityforms.frontend-form',
    directionPass && geometryPass && focusPass ? 'NATIVE_PASS' : 'NOT_PROVEN',
    `Authentic Gravity Forms frontend at ${viewport.width}x${viewport.height} under ${profile}`,
    observed,
    'Computed document/form direction, bounded responsive geometry, exact preservation of mixed Persian/technical tokens, and basic focus/keyboard/input behavior for the exact runtime.',
    'Full accessibility conformance, every Gravity Forms field type, or visual BiDi ordering for every possible technical token.',
  );
  await page.screenshot({ path: path.join(artifactDir, `g009-${profile}-gravityforms-${viewport.width}.png`), fullPage: true });
  await page.close();
}

async function qualifyFlow(browser, viewport) {
  const page = await browser.newPage({ viewport });
  bindDiagnostics(page);
  await gated(page, `G009-FLOW-AUTH-${profile}-${viewport.width}`, () => login(page));
  const observed = await gated(page, `G009-FLOW-${profile}-${viewport.width}`, async () => {
    const response = await page.goto(manifest.gravityflow_frontend_inbox_url, { waitUntil: 'domcontentloaded' });
    if (!response?.ok()) throw new Error(`Flow frontend Inbox response failed: ${response?.status()}`);
    await page.waitForTimeout(800);
    const docBefore = await documentMetrics(page);
    const bodyText = (await page.locator('body').innerText()).slice(0, 4000);
    if (/There has been a critical error|Fatal error/i.test(bodyText)) throw new Error('Flow frontend Inbox contains a fatal/critical error.');
    const styleLink = page.locator('link#gform_admin-css, link[href*="gravityforms"][href*="admin.min.css"]').first();
    const gformAdminPresent = await styleLink.count() > 0;
    const gridPresent = await page.locator('.ag-root, .ag-root-wrapper').count() > 0;
    const surface = gridPresent
      ? page.locator('.ag-root-wrapper:visible, .ag-root:visible').first()
      : page.locator('.gravityflow-inbox:visible, .gravityflow-workflow-ui:visible, main:visible, body').first();
    const visibleBefore = await computedLocator(surface);
    let experiment = null;
    if (profile === 'rtl' && gformAdminPresent) {
      experiment = await styleLink.evaluate(async (link) => {
        const before = {
          html: getComputedStyle(document.documentElement).direction,
          body: getComputedStyle(document.body).direction,
        };
        link.disabled = true;
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        const disabled = {
          html: getComputedStyle(document.documentElement).direction,
          body: getComputedStyle(document.body).direction,
        };
        link.disabled = false;
        await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        const restored = {
          html: getComputedStyle(document.documentElement).direction,
          body: getComputedStyle(document.body).direction,
        };
        return { before, disabled, restored };
      });
    }
    const docAfter = await documentMetrics(page);
    const focusable = page.locator('input:visible, button:visible, select:visible, a[href]:visible').first();
    let focusPass = null;
    if (await focusable.count()) {
      await focusable.focus();
      focusPass = await focusable.evaluate((el) => el === document.activeElement);
    }
    return { viewport, docBefore, docAfter, bodyText, gformAdminPresent, gridPresent, visibleBefore, experiment, focusPass };
  });

  const visibleDirectionPass = observed.visibleBefore.direction === expectedDirection;
  const localePass = observed.docBefore.lang.toLowerCase().startsWith(expectedLocale.split('-')[0].toLowerCase());
  const geometryPass = rectVisible(observed.visibleBefore) && observed.docAfter.scrollWidth <= observed.docAfter.clientWidth + 2;
  const state = visibleDirectionPass && localePass && geometryPass && observed.focusPass !== false ? 'NATIVE_PASS' : 'NOT_PROVEN';
  result(
    'gravityflow.frontend-inbox-ag-grid',
    observed.gridPresent ? state : 'NOT_PROVEN',
    `Authentic Gravity Flow frontend Inbox at ${viewport.width}x${viewport.height} under ${profile}`,
    observed,
    observed.gridPresent
      ? 'Visible Inbox/AG Grid direction, responsive geometry and basic focus behavior without replacing vendor-owned grid state.'
      : 'Authentic frontend Inbox request and shell behavior; no AG Grid instance was deterministically present in this fixture.',
    observed.gridPresent
      ? 'Workflow semantics, exhaustive grid interactions, or every dynamically inserted control.'
      : 'AG Grid-specific geometry/search/sort/filter/pager behavior because the deterministic fixture did not render a grid instance.',
  );

  if (profile === 'rtl') {
    const causalitySupported = Boolean(
      observed.gformAdminPresent
      && observed.experiment
      && observed.experiment.before.html === 'ltr'
      && observed.experiment.disabled.html === 'rtl'
      && observed.experiment.restored.html === 'ltr'
    );
    const visibleMaterialDefectObserved = !visibleDirectionPass || !geometryPass || observed.focusPass === false;
    result(
      'gravityforms.gform-admin-frontend-reachability',
      'NOT_PROVEN',
      'Disposable browser experiment disables and restores only the loaded gform_admin stylesheet link on the authentic Flow frontend request.',
      {
        gformAdminPresent: observed.gformAdminPresent,
        cssCausality: causalitySupported ? 'SUPPORTED' : 'NOT_PROVEN',
        experiment: observed.experiment,
        visibleSurfaceDirection: observed.visibleBefore.direction,
        gridPresent: observed.gridPresent,
        materialVisibleDefectInExercisedSurface: visibleMaterialDefectObserved ? 'OBSERVED_OR_UNRESOLVED' : 'NOT_OBSERVED',
        productionDisposition: 'NOT_PROVEN',
      },
      causalitySupported
        ? 'Runtime CSS causality for root direction on this exact request while keeping production behavior untouched.'
        : 'Only the exact request/style presence and exercised visible-surface observations; root-direction causality was not established.',
      'That gform_admin is unnecessary, that it can safely be dequeued, that every dynamic control is unaffected, or that a supported permanent repair seam exists.',
    );
  }
  await page.screenshot({ path: path.join(artifactDir, `g009-${profile}-gravityflow-inbox-${viewport.width}.png`), fullPage: true });
  await page.close();
}

async function qualifyGravityView(browser, viewport) {
  const page = await browser.newPage({ viewport });
  bindDiagnostics(page);
  await gated(page, `G009-VIEW-AUTH-${profile}-${viewport.width}`, () => login(page));
  const observed = await gated(page, `G009-VIEW-${profile}-${viewport.width}`, async () => {
    const adminResponse = await page.goto(manifest.admin_url, { waitUntil: 'domcontentloaded' });
    if (!adminResponse?.ok()) throw new Error(`Admin response failed: ${adminResponse?.status()}`);
    const links = await page.locator('#adminmenu a[href]').evaluateAll((nodes) => nodes.map((node) => node.href));
    const target = links.find((href) => {
      try {
        const url = new URL(href);
        return url.searchParams.get('post_type') === 'gravityview' || url.searchParams.get('page') === 'gravityview_all_views';
      } catch { return false; }
    });
    if (!target) throw new Error('GravityView admin list was not discoverable from native navigation.');
    const response = await page.goto(target, { waitUntil: 'domcontentloaded' });
    if (!response?.ok()) throw new Error(`GravityView response failed: ${response?.status()}`);
    const current = new URL(page.url());
    if (current.searchParams.get('post_type') !== 'gravityview') throw new Error(`Unexpected GravityView destination: ${page.url()}`);
    const doc = await documentMetrics(page);
    const table = page.locator('.wp-list-table').first();
    if (!(await table.count())) throw new Error('Native GravityView list table is missing.');
    const tableState = await computed(page, '.wp-list-table');
    const search = page.locator('#post-search-input').first();
    let searchState = null;
    let focusPass = null;
    let searchValue = null;
    if (await search.count()) {
      searchState = await search.evaluate((el) => {
        const style = getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        return {
          direction: style.direction,
          textAlign: style.textAlign,
          unicodeBidi: style.unicodeBidi,
          rect: { left: rect.left, right: rect.right, width: rect.width },
          viewportWidth: innerWidth,
        };
      });
      await search.focus();
      await search.fill('View-ID-123 user@example.invalid');
      searchValue = await search.inputValue();
      await page.keyboard.press('Tab');
      focusPass = await page.evaluate(() => document.activeElement !== document.body && document.activeElement !== document.documentElement);
    }
    return { viewport, target: page.url(), doc, table: tableState, search: searchState, searchValue, focusPass };
  });
  const directionPass = observed.doc.htmlDirection === expectedDirection
    && observed.doc.lang.toLowerCase().startsWith(expectedLocale.split('-')[0].toLowerCase())
    && observed.table.direction === expectedDirection;
  const tableHasGeometry = observed.table.rect.width > 0;
  const searchVisible = !observed.search || (observed.search.rect.width > 0 && observed.search.rect.left >= -1 && observed.search.rect.right <= observed.search.viewportWidth + 1);
  const technicalTokenPass = !observed.searchValue || (observed.searchValue.includes('View-ID-123') && observed.searchValue.includes('user@example.invalid'));
  const state = directionPass && tableHasGeometry && searchVisible && technicalTokenPass && observed.focusPass !== false ? 'NATIVE_PASS' : 'NOT_PROVEN';
  result(
    'gravityview.admin-list',
    state,
    `Authentic GravityView native admin list at ${viewport.width}x${viewport.height} under ${profile}`,
    observed,
    'Native list-table direction and geometry plus search-control visibility, exact technical-token preservation, and basic keyboard focus progression.',
    'GravityView frontend templates, all admin screens, business/query behavior, complete accessibility conformance, or visual BiDi ordering for every technical token.',
  );
  await page.screenshot({ path: path.join(artifactDir, `g009-${profile}-gravityview-${viewport.width}.png`), fullPage: true });
  await page.close();
}

const browser = await chromium.launch({ headless: true });
try {
  for (const viewport of [{ width: 1280, height: 900 }, { width: 390, height: 844 }]) {
    await qualifyGravityForms(browser, viewport);
    await qualifyFlow(browser, viewport);
  }
  await qualifyGravityView(browser, { width: 1280, height: 900 });
  await qualifyGravityView(browser, { width: 390, height: 844 });
} finally {
  await browser.close();
}

const expectedLangPrefix = expectedLocale.split('-')[0];
const evidence = {
  schema_version: '1.0.0',
  program: 'G-009',
  profile,
  expected_direction: expectedDirection,
  expected_locale_prefix: expectedLangPrefix,
  exact_persiangravity_commit: manifest.persiangravity_source_commit || null,
  exact_persiangravity_version: manifest.versions?.persiangravity || null,
  exact_package_sha256: manifest.package_sha256 || null,
  vendor_versions: manifest.versions || null,
  results,
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g009-evidence-${profile}.json`), `${JSON.stringify(evidence, null, 2)}\n`);

for (const item of results) console.log(`${item.evidence_state} ${item.id} ${item.scenario}`);
if (results.some((item) => item.evidence_state === 'FAIL_CLOSED_VERSION_DRIFT')) process.exit(1);
