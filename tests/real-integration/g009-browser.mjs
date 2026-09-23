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
for (const field of ['page_url', 'login_url', 'admin_url', 'gravityflow_frontend_inbox_url', 'gravityperks_frontend_url']) {
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
  const dirAttributePass = profile === 'rtl'
    ? observed.doc.htmlDirAttribute === 'rtl'
    : observed.doc.htmlDirAttribute === null || observed.doc.htmlDirAttribute === 'ltr';
  const directionPass = dirAttributePass
    && observed.doc.htmlDirection === expectedDirection
    && observed.doc.bodyDirection === expectedDirection
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
    const interactiveDescendants = await surface.locator('input:visible, button:visible, select:visible, [tabindex]:visible').evaluateAll((nodes) => nodes.slice(0, 50).map((el) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return {
        tagName: el.tagName.toLowerCase(),
        className: typeof el.className === 'string' ? el.className.slice(0, 180) : null,
        direction: style.direction,
        textAlign: style.textAlign,
        rect: { left: rect.left, right: rect.right, width: rect.width, height: rect.height },
      };
    }));
    return { viewport, docBefore, docAfter, bodyText, gformAdminPresent, gridPresent, visibleBefore, experiment, focusPass, interactiveDescendants };
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

  const causalitySupported = profile === 'rtl' && Boolean(
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
    profile === 'rtl'
      ? 'Disposable browser experiment disables and restores only the loaded gform_admin stylesheet link on the authentic Flow frontend request.'
      : 'LTR control observes the authentic Flow frontend request with gform_admin left untouched.',
    {
      viewport,
      gformAdminPresent: observed.gformAdminPresent,
      cssCausality: profile === 'rtl' ? (causalitySupported ? 'SUPPORTED' : 'NOT_PROVEN') : 'NOT_APPLICABLE_LTR_CONTROL',
      experiment: observed.experiment,
      rootDirection: { html: observed.docBefore.htmlDirection, body: observed.docBefore.bodyDirection },
      visibleSurfaceDirection: observed.visibleBefore.direction,
      gridPresent: observed.gridPresent,
      interactiveDescendants: observed.interactiveDescendants,
      materialVisibleDefectInExercisedSurface: visibleMaterialDefectObserved ? 'OBSERVED_OR_UNRESOLVED' : 'NOT_OBSERVED',
      productionDisposition: visibleMaterialDefectObserved ? 'NO_REPAIR_ADMITTED' : 'NO_REPAIR_NO_ADMISSION',
    },
    causalitySupported
      ? 'Exact-request stylesheet reachability plus runtime root-direction CSS causality, while the exercised visible vendor surface remains independently measured.'
      : 'Exact-request stylesheet reachability/control behavior and exercised visible/dynamic-descendant observations without mutating production behavior.',
    'That gform_admin is unnecessary, that it can safely be dequeued, or that a supported permanent repair seam exists.',
  );
  await page.screenshot({ path: path.join(artifactDir, `g009-${profile}-gravityflow-inbox-${viewport.width}.png`), fullPage: true });
  await page.close();
}


async function qualifyGravityPerks(browser, viewport) {
  const page = await browser.newPage({ viewport });
  bindDiagnostics(page);
  const formId = Number(manifest.gravityperks_form_id);
  const fileFieldId = Number(manifest.gravityperks_file_upload_field_id);
  const advancedFieldId = Number(manifest.gravityperks_advanced_select_field_id);
  if (!Number.isInteger(formId) || !Number.isInteger(fileFieldId) || !Number.isInteger(advancedFieldId)) {
    throw new Error('Gravity Perks fixture identities are missing from the runtime manifest.');
  }

  const observed = await gated(page, `G009-PERKS-${profile}-${viewport.width}`, async () => {
    const response = await page.goto(manifest.gravityperks_frontend_url, { waitUntil: 'domcontentloaded' });
    if (!response?.ok()) throw new Error(`Gravity Perks frontend response failed: ${response?.status()}`);

    const fileRootSelector = `#field_${formId}_${fileFieldId}`;
    const advancedRootSelector = `#field_${formId}_${advancedFieldId}`;
    await page.locator(`${fileRootSelector} .gpfup__droparea`).waitFor({ timeout: 15000 });
    await page.locator(`${advancedRootSelector} .ts-wrapper`).waitFor({ timeout: 15000 });

    const doc = await documentMetrics(page);
    const localized = await page.evaluate(() => ({
      selectFiles: window.GPFUP_CONSTANTS?.STRINGS?.select_files ?? null,
      dropFilesHere: window.GPFUP_CONSTANTS?.STRINGS?.drop_files_here ?? null,
      or: window.GPFUP_CONSTANTS?.STRINGS?.or ?? null,
    }));
    const droparea = page.locator(`${fileRootSelector} .gpfup__droparea`).first();
    const selectButton = page.locator(`${fileRootSelector} .gpfup__select-files`).first();
    const dropareaText = (await droparea.innerText()).replace(/\s+/g, ' ').trim();
    const selectButtonText = (await selectButton.innerText()).trim();
    const dropareaState = await computedLocator(droparea);
    const fileInput = page.locator(`${fileRootSelector} input[type="file"]`).first();
    if (!(await fileInput.count())) throw new Error('Authentic File Upload Pro file input is missing.');
    const filename = 'گزارش-ID-1234.txt';
    await fileInput.setInputFiles({ name: filename, mimeType: 'text/plain', buffer: Buffer.from('WU008 G009 exact package browser evidence\n') });
    const filenameNode = page.locator(`${fileRootSelector} .gpfup__filename`).first();
    await filenameNode.waitFor({ timeout: 15000 });
    const filenameText = (await filenameNode.innerText()).trim();
    const filenameState = await filenameNode.evaluate((el) => {
      const style = getComputedStyle(el);
      return { direction: style.direction, unicodeBidi: style.unicodeBidi, textAlign: style.textAlign };
    });

    const styleLink = page.locator('link#gp-advanced-select-tom-select-css').first();
    const styleHandlePresent = (await styleLink.count()) === 1;
    const inlineStyle = page.locator('style#gp-advanced-select-tom-select-inline-css').first();
    const inlineStylePresent = (await inlineStyle.count()) === 1;
    const inlineStyleText = inlineStylePresent ? await inlineStyle.textContent() : null;
    const wrapper = page.locator(`${advancedRootSelector} .ts-wrapper`).first();
    const control = page.locator(`${advancedRootSelector} .ts-control`).first();
    const wrapperClasses = await wrapper.getAttribute('class');
    const controlState = await control.evaluate((el) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return {
        direction: style.direction,
        textAlign: style.textAlign,
        paddingLeft: style.paddingLeft,
        paddingRight: style.paddingRight,
        paddingLeftPx: Number.parseFloat(style.paddingLeft) || 0,
        paddingRightPx: Number.parseFloat(style.paddingRight) || 0,
        backgroundPosition: style.backgroundPosition,
        backgroundPositionX: style.backgroundPositionX,
        rect: { left: rect.left, right: rect.right, width: rect.width, height: rect.height },
        viewport: { width: innerWidth, height: innerHeight },
      };
    });
    const wrappersOutsideFixture = await page.locator('.ts-wrapper').evaluateAll((nodes, rootSelector) => (
      nodes.filter((node) => !node.closest(rootSelector)).length
    ), advancedRootSelector);

    const search = page.locator(`${advancedRootSelector} .ts-control input`).first();
    await search.focus();
    const focusBefore = await search.evaluate((el) => el === document.activeElement);
    await search.fill('');
    await search.pressSequentially('Beta');
    await page.locator(`${advancedRootSelector} .ts-dropdown .option:visible`).first().waitFor({ timeout: 10000 });
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    const originalSelect = page.locator(`#input_${formId}_${advancedFieldId}`);
    const selectedValue = await originalSelect.inputValue();
    const selectedText = (await page.locator(`${advancedRootSelector} .ts-control .item`).first().innerText()).trim();
    await search.focus();
    await page.keyboard.press('Tab');
    const focusAdvanced = await page.evaluate(() => document.activeElement !== document.body && document.activeElement !== document.documentElement);

    return {
      viewport,
      doc,
      fileUpload: {
        localizedGlobal: localized,
        dropareaText,
        selectButtonText,
        droparea: dropareaState,
        filename,
        filenameText,
        filenameState,
      },
      advancedSelect: {
        styleHandle: 'gp-advanced-select-tom-select',
        styleHandlePresent,
        inlineStylePresent,
        inlineStyleText,
        wrapperClasses,
        control: controlState,
        wrappersOutsideFixture,
        focusBefore,
        focusAdvanced,
        selectedValue,
        selectedText,
      },
    };
  });

  const expectedStrings = profile === 'rtl'
    ? { selectFiles: 'انتخاب فایل‌ها', dropFilesHere: 'فایل‌ها را اینجا رها کنید', or: 'یا' }
    : { selectFiles: 'select files', dropFilesHere: 'Drop files here', or: 'or' };
  const stringsPass = JSON.stringify(observed.fileUpload.localizedGlobal) === JSON.stringify(expectedStrings)
    && observed.fileUpload.selectButtonText === expectedStrings.selectFiles
    && observed.fileUpload.dropareaText.includes(expectedStrings.dropFilesHere)
    && observed.fileUpload.dropareaText.includes(expectedStrings.or);
  const fileGeometryPass = rectVisible(observed.fileUpload.droparea);
  const filenamePass = observed.fileUpload.filenameText === observed.fileUpload.filename
    && observed.fileUpload.filenameText.includes('ID-1234');
  const fileDirectionPass = observed.fileUpload.droparea.direction === expectedDirection;
  const fupState = stringsPass && fileGeometryPass && filenamePass && fileDirectionPass ? 'NATIVE_PASS' : 'NOT_PROVEN';

  result(
    'gp-file-upload-pro.frontend',
    fupState,
    `Authentic GP File Upload Pro frontend at ${viewport.width}x${viewport.height} under ${profile}`,
    { viewport, ...observed.fileUpload },
    'Real PHP-localized browser strings, authentic uploader DOM, responsive RTL/LTR geometry, and exact mixed Persian/technical filename preservation.',
    'Every upload provider/storage backend, exhaustive accessibility, or every file type and failure path.',
  );

  const advanced = observed.advancedSelect;
  const rtlAdapterPass = profile === 'rtl'
    ? Boolean(
      advanced.styleHandlePresent
      && advanced.inlineStylePresent
      && advanced.inlineStyleText?.includes('.ts-wrapper.rtl')
      && advanced.wrapperClasses?.split(/\s+/).includes('rtl')
      && advanced.control.direction === 'rtl'
      && advanced.control.paddingLeftPx > advanced.control.paddingRightPx
    )
    : Boolean(
      advanced.styleHandlePresent
      && !advanced.inlineStylePresent
      && !advanced.wrapperClasses?.split(/\s+/).includes('rtl')
      && advanced.control.direction === 'ltr'
    );
  const interactionPass = advanced.focusBefore
    && advanced.focusAdvanced
    && advanced.selectedValue === 'beta-email'
    && advanced.selectedText.includes('Beta');
  const advancedGeometryPass = rectVisible(advanced.control);
  const advancedState = rtlAdapterPass && interactionPass && advancedGeometryPass && advanced.wrappersOutsideFixture === 0
    ? 'ADAPTER_REQUIRED_AND_VERIFIED'
    : 'NOT_PROVEN';

  result(
    'gp-advanced-select.tom-select',
    advancedState,
    `Authentic GP Advanced Select Tom Select at ${viewport.width}x${viewport.height} under ${profile}`,
    { viewport, ...advanced },
    profile === 'rtl'
      ? 'Exact vendor style handle/DOM plus the current-Head bounded adapter, mirrored caret padding, RTL control direction, and real search/keyboard/selection/focus behavior.'
      : 'Exact vendor style handle with no PersianGravity inline adapter in the LTR control, preserving native direction and real search/keyboard/selection/focus behavior.',
    'Future package versions, changed vendor handles/DOM, unrelated Tom Select implementations, or exhaustive accessibility.',
  );

  const familyState = fupState === 'NATIVE_PASS' && advancedState === 'ADAPTER_REQUIRED_AND_VERIFIED'
    ? 'NATIVE_PASS'
    : 'NOT_PROVEN';
  result(
    'gravityperks.family-baseline',
    familyState,
    `Exact admitted Gravity Perks family baseline at ${viewport.width}x${viewport.height} under ${profile}`,
    {
      viewport,
      familyRuntimePresent: true,
      fileUploadSurface: fupState,
      advancedSelectSurface: advancedState,
      localeDirection: { lang: observed.doc.lang, html: observed.doc.htmlDirection, body: observed.doc.bodyDirection },
    },
    'The exact admitted Gravity Perks 2.3.16 family runtime with the two specifically admitted Perks exercised on this profile.',
    'Any unlisted Perk, future package/version, or product-wide compatibility beyond the exercised surfaces.',
  );

  await page.screenshot({ path: path.join(artifactDir, `g009-${profile}-gravityperks-${viewport.width}.png`), fullPage: true });
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
    await qualifyGravityPerks(browser, viewport);
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
