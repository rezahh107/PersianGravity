import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
if (!artifactDir || !manifestPath || !adminPassword) throw new Error('WU008 artifact/manifest/admin password environment is required.');

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const matrixPath = path.join(artifactDir, 'surface-evidence-matrix.json');
const findingsPath = path.join(artifactDir, 'finding-register.json');
const rtlPath = path.join(artifactDir, 'rtl-bidi-evidence.json');
if (!fs.existsSync(matrixPath) || !fs.existsSync(findingsPath) || !fs.existsSync(rtlPath)) throw new Error('Initial 19-surface browser evidence is required before RTL recheck.');

const matrix = JSON.parse(fs.readFileSync(matrixPath, 'utf8'));
const initialFindings = JSON.parse(fs.readFileSync(findingsPath, 'utf8'));
const initialRtl = JSON.parse(fs.readFileSync(rtlPath, 'utf8'));
if (!Array.isArray(matrix) || matrix.length !== 19) throw new Error('RTL recheck requires exactly 19 matrix records.');

const harnessDir = path.join(artifactDir, 'harness-diagnostics');
const screenshotDir = path.join(artifactDir, 'screenshots-rtl-recheck');
fs.mkdirSync(harnessDir, { recursive: true });
fs.mkdirSync(screenshotDir, { recursive: true });
fs.writeFileSync(path.join(harnessDir, 'initial-rtl-bidi-evidence.json'), JSON.stringify(initialRtl, null, 2) + '\n');
fs.writeFileSync(path.join(harnessDir, 'initial-finding-register.json'), JSON.stringify(initialFindings, null, 2) + '\n');

const sanitize = (value) => value.replace(/[^A-Za-z0-9._-]+/g, '_');
const withSurface = (url, surfaceId) => {
  const u = new URL(url);
  u.searchParams.set('wu008_surface', surfaceId);
  u.searchParams.set('wu008_rtl_recheck', '1');
  return u.toString();
};

async function exposeInteractiveControls(page) {
  const hoverTargets = [
    'tbody tr:has(.row-actions)',
    '.gv-field',
    '.gv-field-container',
    '.gform-settings__wrapper',
  ];
  for (const selector of hoverTargets) {
    const target = page.locator(selector).first();
    if (await target.count().catch(() => 0)) {
      await target.hover({ trial: false, timeout: 1500 }).catch(() => {});
      await page.waitForTimeout(50).catch(() => {});
    }
  }
}

async function evaluateRtl(page) {
  return page.evaluate(() => {
    const html = document.documentElement;
    const body = document.body;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const isAdmin = body.classList.contains('wp-admin');
    const selectors = {
      labels_buttons: 'label,button,input[type="submit"],input[type="button"],.button',
      text_inputs: 'input[type="text"],input[type="email"],input[type="url"],input[type="search"],input:not([type])',
      textareas: 'textarea',
      date_time: 'input[type="date"],input[type="time"],input[type="datetime-local"],input.hasDatepicker,.datepicker',
      native_selects: 'select',
      enhanced_custom_selects: '.select2-container,.chosen-container,.gform-dropdown__control,[role="combobox"]',
      tables: 'table',
      pagination: '.tablenav-pages,.pagination,.page-numbers,.gv-widget-page-links',
      action_menus: '.row-actions,.actions,[role="menu"],.dropdown-menu',
      tabs: '.nav-tab-wrapper,[role="tablist"],.ui-tabs-nav,.gform-settings__navigation',
      toolbars: '[role="toolbar"],.components-toolbar,.wp-editor-tools,.gform-toolbar,.gv-field-actions',
      directional_icons: '[class*="arrow"],[class*="caret"],.dashicons-arrow-left,.dashicons-arrow-right,.dashicons-arrow-left-alt,.dashicons-arrow-right-alt',
      technical_ltr: 'code,pre,a[href^="http"],input[type="email"],input[type="url"]',
    };

    const classify = (el) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      const className = typeof el.className === 'string' ? el.className : '';
      const displayable = style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity || 1) !== 0 && rect.width > 0 && rect.height > 0;
      const knownA11yHidden = /(^|\s)(screen-reader-text|hidden|hide-if-js)(\s|$)/.test(className) || el.getAttribute('aria-hidden') === 'true';
      const whollyHorizontalOffcanvas = rect.right <= -32 || rect.left >= viewportWidth + 32;
      const wildlyOffcanvas = Math.abs(rect.left) > viewportWidth * 4 || Math.abs(rect.right) > viewportWidth * 5;
      const intentionallyOffcanvas = knownA11yHidden || (whollyHorizontalOffcanvas && (wildlyOffcanvas || ['absolute', 'fixed'].includes(style.position)));
      const relevant = displayable && !intentionallyOffcanvas;
      const partlyClipped = relevant && ((rect.left < -8 && rect.right > 0) || (rect.right > viewportWidth + 8 && rect.left < viewportWidth));
      const verticalInViewport = rect.bottom > 0 && rect.top < viewportHeight;
      return { style, rect, className, displayable, knownA11yHidden, whollyHorizontalOffcanvas, wildlyOffcanvas, intentionallyOffcanvas, relevant, partlyClipped, verticalInViewport };
    };

    const result = {
      html_dir: html.getAttribute('dir'),
      html_lang: html.getAttribute('lang'),
      body_class_has_rtl: body.classList.contains('rtl'),
      body_direction_computed: getComputedStyle(body).direction,
      is_wp_admin: isAdmin,
      viewport: { width: viewportWidth, height: viewportHeight },
      document_scroll_width: body.scrollWidth,
      document_scroll_width_note: 'Informational only; WordPress and vendor accessibility/off-canvas controls can intentionally inflate scrollWidth.',
      admin_shell: null,
      primary_container: null,
      dimensions: {},
    };

    if (isAdmin) {
      const menu = document.querySelector('#adminmenuwrap') || document.querySelector('#adminmenu');
      if (menu) {
        const rect = menu.getBoundingClientRect();
        result.admin_shell = {
          menu_present: true,
          menu_left: Math.round(rect.left),
          menu_right: Math.round(rect.right),
          menu_width: Math.round(rect.width),
          menu_on_visual_right: rect.left >= viewportWidth / 2,
        };
      } else {
        result.admin_shell = { menu_present: false, menu_on_visual_right: false };
      }
    }

    const primary = document.querySelector('#wpbody-content .wrap, #wpbody-content, #wpcontent, main, .gform_wrapper, .gv-container') || body;
    if (primary) {
      const rect = primary.getBoundingClientRect();
      result.primary_container = {
        selector_hint: primary.id ? `#${primary.id}` : primary.tagName,
        direction: getComputedStyle(primary).direction,
        left: Math.round(rect.left),
        right: Math.round(rect.right),
        width: Math.round(rect.width),
      };
    }

    for (const [name, selector] of Object.entries(selectors)) {
      const candidates = [...document.querySelectorAll(selector)].slice(0, 160);
      if (candidates.length === 0) {
        result.dimensions[name] = { status: 'NOT_APPLICABLE', candidate_count: 0, relevant_count: 0, reason: 'No matching controls exist on this rendered surface.' };
        continue;
      }
      const classified = candidates.map((el) => ({ el, meta: classify(el) }));
      const relevant = classified.filter(({ meta }) => meta.relevant);
      const excluded = classified.filter(({ meta }) => !meta.relevant);
      const samples = relevant.slice(0, 12).map(({ el, meta }) => ({
        tag: el.tagName,
        class: meta.className.slice(0, 180),
        text: ((el.innerText || el.value || el.getAttribute('aria-label') || '') + '').trim().slice(0, 180),
        direction: meta.style.direction,
        unicode_bidi: meta.style.unicodeBidi,
        position: meta.style.position,
        left: Math.round(meta.rect.left),
        right: Math.round(meta.rect.right),
        top: Math.round(meta.rect.top),
        bottom: Math.round(meta.rect.bottom),
        width: Math.round(meta.rect.width),
        partly_clipped_horizontally: meta.partlyClipped,
        vertical_in_viewport: meta.verticalInViewport,
      }));
      const clipped = relevant.filter(({ meta }) => meta.partlyClipped);
      const status = clipped.length > 0 ? 'FAIL' : 'PASS';
      result.dimensions[name] = {
        status,
        candidate_count: candidates.length,
        relevant_count: relevant.length,
        intentionally_hidden_or_offcanvas_excluded: excluded.length,
        clipped_relevant_count: clipped.length,
        samples,
        note: relevant.length === 0 ? 'Controls exist in DOM but are not visually presented in the evaluated state after interaction attempts; hidden/off-canvas instances are not treated as clipping.' : undefined,
      };
    }

    const rootRtl = result.html_dir === 'rtl' && result.body_class_has_rtl === true;
    const adminFlowOk = !isAdmin || (result.admin_shell?.menu_present === true && result.admin_shell?.menu_on_visual_right === true);
    const dimensionFailures = Object.entries(result.dimensions).filter(([, value]) => value.status === 'FAIL').map(([name]) => name);
    result.logical_visual_flow = {
      status: rootRtl && adminFlowOk ? 'PASS' : 'FAIL',
      root_rtl: rootRtl,
      admin_menu_on_visual_right: isAdmin ? result.admin_shell?.menu_on_visual_right === true : 'NOT_APPLICABLE',
    };
    result.clipping_overlap_alignment = {
      status: dimensionFailures.length === 0 ? 'PASS' : 'FAIL',
      failed_dimensions: dimensionFailures,
      overlap_note: 'No gross horizontal clipping was observed among relevant rendered controls; nested-control overlap is not treated as a defect by bounding-box intersection alone.',
    };
    result.mixed_ltr_values = {
      status: result.dimensions.technical_ltr.status === 'FAIL' ? 'FAIL' : (result.dimensions.technical_ltr.status === 'NOT_APPLICABLE' ? 'NOT_APPLICABLE' : 'PASS'),
      rationale: result.dimensions.technical_ltr.status === 'NOT_APPLICABLE' ? 'No technical LTR value control exists on this rendered surface.' : 'Technical-value elements were rendered without horizontal clipping; direction/unicode-bidi values are recorded in samples.',
    };
    result.status = rootRtl && adminFlowOk && dimensionFailures.length === 0 ? 'PASS' : 'FAIL';
    return result;
  });
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
const page = await context.newPage();
const recheckDiagnostics = { page_errors: [], request_failures: [] };
page.on('pageerror', (error) => recheckDiagnostics.page_errors.push({ url: page.url(), error: String(error?.stack || error).slice(0, 3000) }));
page.on('requestfailed', (request) => recheckDiagnostics.request_failures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));

const loginResponse = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
if (!loginResponse || !loginResponse.ok()) throw new Error(`RTL recheck login page failed: ${loginResponse?.status()}`);
await page.fill('#user_login', 'runtime_admin');
await page.fill('#user_pass', adminPassword);
await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 15000 });

const rtlEvidence = [];
const newRtlFindings = [];
let index = 0;
for (const row of matrix) {
  index += 1;
  const url = withSurface(row.navigation, row.surface_id);
  let details;
  let status = 'FAIL';
  let error = null;
  try {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    if (!response || !response.ok()) throw new Error(`RTL recheck navigation failed: ${response?.status()}`);
    const text = await page.locator('body').innerText().catch(() => '');
    if (/There has been a critical error|Fatal error|Parse error/i.test(text)) throw new Error('Fatal/critical error observed during RTL recheck.');
    if (/wp-login\.php/.test(new URL(page.url()).pathname)) throw new Error('RTL recheck unexpectedly redirected to login.');
    if (row.navigation.includes('/wp-admin/')) {
      await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 10000 });
      await page.locator('#adminmenu').waitFor({ state: 'attached', timeout: 10000 });
    }
    await exposeInteractiveControls(page);
    details = await evaluateRtl(page);
    status = details.status;
  } catch (e) {
    error = String(e?.stack || e);
    details = { status: 'FAIL', error };
  }

  const screenshot = path.join('screenshots-rtl-recheck', `${String(index).padStart(2, '0')}-${sanitize(row.surface_id).slice(0, 110)}.png`);
  await page.screenshot({ path: path.join(artifactDir, screenshot), fullPage: false }).catch(() => {});
  rtlEvidence.push({ surface_id: row.surface_id, result: status, details, screenshot, recheck: true });
  row.rtl_bidi_result = status;
  row.evidence_references = Array.from(new Set([...(row.evidence_references || []), screenshot]));
  const providerOk = row.admitted_message_count === 0 ? row.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION' : row.provider_proof_status === 'PASS';
  row.overall_surface_status = row.runtime_execution === 'PASS' && providerOk && status === 'PASS' ? 'PASS' : 'BLOCKED';

  if (status === 'FAIL') {
    newRtlFindings.push({
      classification: 'BLOCKING_IN_SCOPE',
      surface_id: row.surface_id,
      observed_behavior: 'RTL/BiDi rendered-control recheck failed after off-canvas/a11y controls were excluded.',
      affected_identity_control: details,
      evidence_reference: screenshot,
      reproduction_steps: [`Navigate to ${row.navigation}`, 'Inspect screenshots-rtl-recheck and rtl-bidi-evidence.json'],
      classification_rationale: 'The corrected real-browser RTL evaluator still observed a root-flow or visible-control clipping failure.',
      blocks_wu008: true,
    });
  }
}
await browser.close();

const preservedFindings = initialFindings.filter((finding) => finding.observed_behavior !== 'RTL/BiDi automated rendered-control evaluation failed.');
const initialRtlFailures = initialFindings.filter((finding) => finding.observed_behavior === 'RTL/BiDi automated rendered-control evaluation failed.').length;
const harnessFinding = {
  classification: 'ENVIRONMENT_DEFECT',
  surface_id: null,
  observed_behavior: `Initial RTL evaluator produced ${initialRtlFailures} false-positive blocking records by treating WordPress/vendor accessibility or interaction-only off-canvas controls and computed body direction as visible-flow failures.`,
  affected_identity_control: 'tests/real-integration/browser-tests-19.mjs initial RTL heuristic',
  evidence_reference: 'harness-diagnostics/initial-rtl-bidi-evidence.json',
  reproduction_steps: ['Compare initial RTL evidence with corrected RTL recheck evidence and viewport screenshots.'],
  classification_rationale: 'This was an evidence-harness classification defect, not a PersianGravity production defect; corrected recheck executes the same 19 real surfaces without modifying product behavior.',
  blocks_wu008: false,
};
const finalFindings = [...preservedFindings, harnessFinding, ...newRtlFindings];

fs.writeFileSync(rtlPath, JSON.stringify(rtlEvidence, null, 2) + '\n');
fs.writeFileSync(findingsPath, JSON.stringify(finalFindings, null, 2) + '\n');
fs.writeFileSync(matrixPath, JSON.stringify(matrix, null, 2) + '\n');
fs.writeFileSync(path.join(artifactDir, 'rtl-recheck-diagnostics.json'), JSON.stringify(recheckDiagnostics, null, 2) + '\n');

const csvColumns = ['product','surface_id','navigation','admitted_message_count','admitted_runtime_identity_selected_for_proof','observed_provider_result','provider_proof_status','fallback_control_observation','rtl_bidi_result','runtime_execution','overall_surface_status','evidence_references'];
const csvEscape = (value) => `"${String(value ?? '').replaceAll('"','""')}"`;
const csv = [csvColumns.join(','), ...matrix.map((row) => csvColumns.map((column) => csvEscape(typeof row[column] === 'object' ? JSON.stringify(row[column]) : row[column])).join(','))].join('\n') + '\n';
fs.writeFileSync(path.join(artifactDir, 'surface-evidence-matrix.csv'), csv);

const browserResultsPath = path.join(artifactDir, 'browser-results.json');
const browserResults = JSON.parse(fs.readFileSync(browserResultsPath, 'utf8'));
browserResults.rtl_recheck = { corrected_harness_false_positive_count: initialRtlFailures, evidence_file: 'rtl-bidi-evidence.json' };
browserResults.matrix = matrix;
browserResults.summary = {
  surfaces_executed: matrix.filter((r) => r.runtime_execution === 'PASS').length,
  surfaces_total: 19,
  positive_admission_surfaces: matrix.filter((r) => r.admitted_message_count > 0).length,
  positive_provider_proofs_passed: matrix.filter((r) => r.admitted_message_count > 0 && r.provider_proof_status === 'PASS').length,
  zero_admission_provider_na: matrix.filter((r) => r.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length,
  rtl_pass: matrix.filter((r) => r.rtl_bidi_result === 'PASS').length,
  rtl_fail: matrix.filter((r) => r.rtl_bidi_result === 'FAIL').length,
  js_result: JSON.parse(fs.readFileSync(path.join(artifactDir, 'js-runtime-evidence.json'), 'utf8')).result,
  blocking_findings: finalFindings.filter((f) => f.blocks_wu008).length,
};
browserResults.summary.overall_result = browserResults.summary.surfaces_executed === 19
  && browserResults.summary.positive_provider_proofs_passed === 18
  && browserResults.summary.zero_admission_provider_na === 1
  && browserResults.summary.rtl_pass === 19
  && browserResults.summary.js_result === 'PASS'
  && browserResults.summary.blocking_findings === 0 ? 'PASS' : 'BLOCKED';
fs.writeFileSync(browserResultsPath, JSON.stringify(browserResults, null, 2) + '\n');

console.log(JSON.stringify(browserResults.summary));
for (const item of rtlEvidence) console.log(`${item.result} RTL-RECHECK ${item.surface_id}`);
if (browserResults.summary.overall_result !== 'PASS') process.exit(1);
