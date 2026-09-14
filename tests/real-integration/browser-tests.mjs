import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const expectedSha = process.env.WU008_PGR_SHA;
if (!artifactDir || !manifestPath || !adminPassword || !expectedSha) {
  throw new Error('WU008_ARTIFACT_DIR, WU008_MANIFEST_PATH, WU008_ADMIN_PASSWORD, and WU008_PGR_SHA are required.');
}
fs.mkdirSync(path.join(artifactDir, 'screenshots'), { recursive: true });
fs.mkdirSync(path.join(artifactDir, 'dom'), { recursive: true });
fs.mkdirSync(path.join(artifactDir, 'runtime'), { recursive: true });

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const surfaces = Array.isArray(manifest.surfaces) ? manifest.surfaces : [];
if (surfaces.length !== 19) throw new Error(`Runtime manifest must contain exactly 19 surfaces, got ${surfaces.length}.`);
if (manifest.persiangravity_actual_sha !== expectedSha || expectedSha !== 'd3d6460a07a2c38b483dac664603a443ce430da0') {
  throw new Error(`Exact PersianGravity product identity mismatch: manifest=${manifest.persiangravity_actual_sha} env=${expectedSha}`);
}
if (manifest.locale !== 'fa_IR' || manifest.rtl !== true) throw new Error('Runtime manifest is not fa_IR RTL.');

const zeroAdmissionId = 'gravityflow::entry_management_runtime::entry_detail_sidebar:gravityflow';
const origin = new URL(manifest.admin_url).origin;
const runtimeEventsPath = path.join(artifactDir, 'surface-runtime-events.jsonl');

function readJsonLines(file) {
  if (!fs.existsSync(file)) return [];
  return fs.readFileSync(file, 'utf8').split(/\r?\n/).filter(Boolean).map((line) => {
    try { return JSON.parse(line); } catch { return { type: 'invalid_jsonl_line', raw: line.slice(0, 1000) }; }
  });
}
function csvCell(value) {
  const text = value == null ? '' : (typeof value === 'string' ? value : JSON.stringify(value));
  return `"${text.replaceAll('"', '""')}"`;
}
function surfaceCsv(rows) {
  const columns = ['product','surface_id','fixture_setup','navigation','admitted_message_count','admitted_runtime_identity','observed_provider_result','provider_proof_status','fallback_control_observation','rtl_bidi_result','evidence_reference','overall_surface_status'];
  return [columns.join(','), ...rows.map((row) => columns.map((column) => csvCell(row[column])).join(','))].join('\n') + '\n';
}
function addFinding(findings, finding) {
  findings.push({ id: `WU008-FND-${String(findings.length + 1).padStart(3, '0')}`, ...finding });
}

async function observeDom(page) {
  return page.evaluate(() => {
    const visible = (el) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return style.visibility !== 'hidden' && style.display !== 'none' && rect.width > 0 && rect.height > 0;
    };
    const countVisible = (selector) => Array.from(document.querySelectorAll(selector)).filter(visible).length;
    const samples = (selector, limit = 8) => Array.from(document.querySelectorAll(selector)).filter(visible).slice(0, limit).map((el) => ({
      tag: el.tagName.toLowerCase(),
      text: (el.innerText || el.value || el.getAttribute('aria-label') || el.getAttribute('title') || '').trim().slice(0, 300),
      direction: getComputedStyle(el).direction,
      classes: typeof el.className === 'string' ? el.className.slice(0, 300) : '',
    }));
    const selectors = {
      labels: 'label,.gfield_label,.form-table th',
      buttons: 'button,input[type="submit"],input[type="button"],input[type="reset"],a.button,.button',
      text_inputs: 'input[type="text"],input[type="email"],input[type="url"],input[type="search"],input[type="number"],input:not([type])',
      textareas: 'textarea',
      date_time_inputs: 'input[type="date"],input[type="datetime-local"],input[type="time"],input.hasDatepicker,.datepicker',
      native_selects: 'select',
      enhanced_custom_selects: '.select2-container,.chosen-container,[role="combobox"],.gform-dropdown__control',
      dropdowns: '[role="listbox"],.dropdown-menu,.gform-dropdown',
      tables: 'table',
      pagination: '.pagination,.tablenav-pages,.gv-pagination,.gform-page-footer,.gform-pagination',
      action_menus: '.row-actions,.actions,[role="menu"],.bulk-actions',
      tabs: '[role="tab"],.nav-tab,.ui-tabs-nav a,.gf-tabs a',
      toolbars: '[role="toolbar"],.gform-toolbar,.wp-filter,.subsubsub',
      directional_icons: '[class*="arrow"],[class*="chevron"],[class*="caret"],.dashicons-arrow-left,.dashicons-arrow-right',
    };
    const controls = {};
    const controlSamples = {};
    for (const [name, selector] of Object.entries(selectors)) {
      controls[name] = countVisible(selector);
      controlSamples[name] = samples(selector);
    }
    const allVisible = Array.from(document.querySelectorAll('body *')).filter(visible);
    const mixedLtr = allVisible.filter((el) => {
      const text = (el.childElementCount === 0 ? (el.textContent || '') : '').trim();
      return text && (/https?:\/\//i.test(text) || /\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b/.test(text) || /\bv?\d+(?:\.\d+){1,3}\b/.test(text));
    }).slice(0, 20).map((el) => ({ text: (el.textContent || '').trim().slice(0, 300), direction: getComputedStyle(el).direction, tag: el.tagName.toLowerCase() }));
    const clipping = allVisible.filter((el) => {
      const style = getComputedStyle(el);
      const classes = typeof el.className === 'string' ? el.className : '';
      if (/screen-reader|sr-only|visually-hidden|hidden/i.test(classes) || el.getAttribute('aria-hidden') === 'true') return false;
      if (!['hidden', 'clip'].includes(style.overflowX) && !['hidden', 'clip'].includes(style.overflow)) return false;
      return el.scrollWidth > el.clientWidth + 2;
    }).slice(0, 20).map((el) => ({
      tag: el.tagName.toLowerCase(), text: (el.textContent || '').trim().slice(0, 250), clientWidth: el.clientWidth, scrollWidth: el.scrollWidth,
      classes: typeof el.className === 'string' ? el.className.slice(0, 300) : '',
    }));
    const html = document.documentElement;
    const body = document.body;
    return {
      url: location.href, title: document.title,
      html: { dir: html.getAttribute('dir'), lang: html.getAttribute('lang'), computed_direction: getComputedStyle(html).direction },
      body_class: body?.className || '', body_direction: body ? getComputedStyle(body).direction : null,
      controls, control_samples: controlSamples, mixed_ltr_technical_samples: mixedLtr, clipping_candidates: clipping,
      viewport: { width: innerWidth, height: innerHeight },
      page_width: { client: html.clientWidth, scroll: html.scrollWidth, overflow_px: Math.max(0, html.scrollWidth - html.clientWidth) },
      body_text_excerpt: (body?.innerText || '').slice(0, 5000),
    };
  });
}

function buildRtlResult(dom) {
  const dimensions = {
    overall_direction: dom.html.dir === 'rtl' && dom.html.computed_direction === 'rtl' ? 'PASS' : 'FAIL',
    logical_visual_flow: dom.html.dir === 'rtl' && dom.body_direction === 'rtl' ? 'PASS' : 'FAIL',
  };
  const mapping = {
    labels:'labels', buttons:'buttons', form_controls:'text_inputs', textareas:'textareas', date_time_inputs:'date_time_inputs', native_selects:'native_selects',
    enhanced_custom_selects:'enhanced_custom_selects', dropdowns:'dropdowns', tables:'tables', pagination:'pagination', action_menus:'action_menus', tabs:'tabs', toolbars:'toolbars', directional_icons:'directional_icons',
  };
  for (const [dimension, key] of Object.entries(mapping)) dimensions[dimension] = dom.controls[key] > 0 ? 'PASS' : 'NOT_APPLICABLE';
  dimensions.mixed_ltr_technical_values = dom.mixed_ltr_technical_samples.length > 0 ? 'PASS' : 'NOT_APPLICABLE';
  const severeClipping = dom.clipping_candidates.filter((item) => (item.scrollWidth - item.clientWidth) > 120 && (item.text || '').length > 40);
  dimensions.clipping_overlap_alignment = dom.page_width.overflow_px <= 32 && severeClipping.length === 0 ? 'PASS' : 'FAIL';
  dimensions.order_reversal = dom.html.dir === 'rtl' ? 'PASS' : 'FAIL';
  return {
    result: Object.values(dimensions).some((status) => status === 'FAIL') ? 'FAIL' : 'PASS',
    dimensions,
    observations: { page_width: dom.page_width, clipping_candidates: dom.clipping_candidates, severe_clipping_candidates: severeClipping, mixed_ltr_technical_samples: dom.mixed_ltr_technical_samples, control_counts: dom.controls },
  };
}

function jsBoundary(events, requests) {
  const requestEnds = events.filter((event) => event.type === 'request_end');
  const scriptHooks = events.filter((event) => event.type === 'load_script_translations' || event.type === 'pre_load_script_translations');
  const registered = requestEnds.flatMap((event) => Array.isArray(event.script_translation_handles) ? event.script_translation_handles : []);
  const pgrJsonNetwork = requests.filter((url) => /persian-gravityforms\/languages\/providers\/.*\.json(?:\?|$)/i.test(url));
  const pgrFileHooks = scriptHooks.filter((event) => event.file_is_pgr_provider_json === true || (typeof event.file === 'string' && /persian-gravityforms\/languages\/providers\/.*\.json/i.test(event.file)));
  const unexpectedTranslationsPath = registered.filter((item) => typeof item.translations_path === 'string' && /persian-gravityforms\/languages\/providers/i.test(item.translations_path));
  return {
    status: pgrJsonNetwork.length === 0 && pgrFileHooks.length === 0 && unexpectedTranslationsPath.length === 0 ? 'PASS' : 'FAIL',
    pgr_provider_json_network_requests: pgrJsonNetwork,
    pgr_provider_json_hook_events: pgrFileHooks,
    unexpected_pgr_translations_path_handles: unexpectedTranslationsPath,
    observed_target_domain_script_handles: registered,
    observed_script_translation_hook_events: scriptHooks,
  };
}

async function ensureAdminLogin(page) {
  const response = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (!response || !response.ok()) throw new Error(`Login page failed with ${response?.status()}`);
  await page.locator('#user_login').fill('runtime_admin');
  await page.locator('#user_pass').fill(adminPassword);
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }), page.locator('#wp-submit').click()]);
  if (!page.url().startsWith(manifest.admin_url)) throw new Error(`Login did not establish admin session: ${page.url()}`);
  await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 15000 });
}

const browser = await chromium.launch({ headless: true });
const browserVersion = browser.version();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, locale: 'fa-IR' });
const pageDiagnostics = { console: [], page_errors: [], request_failures: [] };
const requestsBySurface = new Map(surfaces.map((surface) => [surface.key, []]));
let currentSurface = null;
page.on('console', (msg) => pageDiagnostics.console.push({ surface_key: currentSurface, type: msg.type(), text: msg.text().slice(0, 2000) }));
page.on('pageerror', (error) => pageDiagnostics.page_errors.push({ surface_key: currentSurface, error: String(error?.stack || error).slice(0, 5000) }));
page.on('request', (request) => { if (currentSurface && requestsBySurface.has(currentSurface)) requestsBySurface.get(currentSurface).push(request.url()); });
page.on('requestfailed', (request) => pageDiagnostics.request_failures.push({ surface_key: currentSurface, url: request.url(), error: request.failure()?.errorText || 'unknown' }));

const matrix = [];
const rtlEvidence = [];
const findings = [];
const jsSurfaces = [];
let authenticated = false;
try {
  try {
    await ensureAdminLogin(page);
    authenticated = true;
  } catch (error) {
    addFinding(findings, {
      surface_id: null, observed_behavior: `WordPress admin authentication precondition failed: ${String(error?.message || error)}`,
      affected_identity_or_control: 'runtime_admin login', evidence_reference: 'runtime/browser-diagnostics.json',
      reproduction_steps: ['Open manifest login_url','Authenticate as runtime_admin','Expect authenticated wp-admin shell'],
      classification: 'ENVIRONMENT_DEFECT', classification_rationale: 'The disposable evidence environment could not establish the common authenticated browser precondition.', blocks_wu008: true,
    });
  }

  for (const surface of surfaces) {
    currentSurface = surface.key;
    const screenshotRel = `screenshots/${surface.key}.png`;
    const domRel = `dom/${surface.key}.html`;
    const obsRel = `runtime/${surface.key}-observation.json`;
    let navigationOk = false;
    let navigationError = null;
    let dom = null;
    let rtl = null;
    let bodyText = '';
    try {
      await page.context().addCookies([{ name: 'wu008_surface', value: surface.key, url: origin, httpOnly: false, secure: false, sameSite: 'Lax' }]);
      const response = await page.goto(surface.navigation, { waitUntil: 'domcontentloaded', timeout: 40000 });
      if (!response || !response.ok()) throw new Error(`HTTP navigation failed: ${response?.status()}`);
      await page.waitForTimeout(800);
      if (surface.key === 's01') {
        const form = page.locator(`#gform_${manifest.form_id}`);
        await form.waitFor({ state: 'attached', timeout: 15000 });
        const submit = form.locator('input[type="submit"],button[type="submit"]').first();
        if (await submit.count()) {
          await submit.click();
          await page.waitForLoadState('domcontentloaded', { timeout: 20000 }).catch(() => {});
          await page.waitForTimeout(600);
        }
      }
      if (new URL(surface.navigation).pathname.includes('/wp-admin/') && !page.url().includes('/wp-admin/')) throw new Error(`Expected authenticated wp-admin surface but landed at ${page.url()}`);
      bodyText = await page.locator('body').innerText().catch(() => '');
      if (/There has been a critical error|Fatal error|Uncaught (?:Error|Exception)/i.test(bodyText)) throw new Error('Rendered surface contains a fatal/critical runtime error.');
      navigationOk = true;
    } catch (error) {
      navigationError = String(error?.stack || error);
    }

    try {
      dom = await observeDom(page);
      rtl = buildRtlResult(dom);
      fs.writeFileSync(path.join(artifactDir, domRel), await page.content());
      await page.screenshot({ path: path.join(artifactDir, screenshotRel), fullPage: true });
    } catch (error) {
      if (!navigationError) navigationError = `Evidence capture failed: ${String(error?.stack || error)}`;
      rtl = { result: 'FAIL', dimensions: { evidence_capture: 'FAIL' }, observations: { error: String(error?.message || error) } };
      await page.screenshot({ path: path.join(artifactDir, screenshotRel), fullPage: true }).catch(() => {});
      fs.writeFileSync(path.join(artifactDir, domRel), await page.content().catch(() => '<!-- DOM unavailable -->'));
    }

    await page.waitForTimeout(250);
    const events = readJsonLines(runtimeEventsPath).filter((event) => event.surface_key === surface.key);
    const admittedEvents = events.filter((event) => event.type === 'admitted_gettext');
    const matchingAdmitted = admittedEvents.filter((event) => event.expected_match === true);
    const mismatchedAdmitted = admittedEvents.filter((event) => event.expected_match !== true);
    const fallbackEvents = events.filter((event) => event.type === 'non_admitted_gettext');
    const translationFiles = events.filter((event) => event.type === 'translation_file');
    const providerFileObserved = translationFiles.some((event) => event.persiangravity_provider_file === true);
    const requests = [...new Set(requestsBySurface.get(surface.key) || [])];
    const js = jsBoundary(events, requests);
    jsSurfaces.push({ surface_id: surface.surface_id, surface_key: surface.key, ...js });

    let providerProofStatus;
    let admittedIdentity = null;
    let observedProvider = null;
    if (surface.admitted_message_count === 0) {
      providerProofStatus = admittedEvents.length === 0 ? 'NOT_APPLICABLE_ZERO_ADMISSION' : 'FAIL';
      if (admittedEvents.length > 0) addFinding(findings, {
        surface_id: surface.surface_id, observed_behavior: 'The intentional zero-admission surface emitted a surface-admitted identity event.', affected_identity_or_control: admittedEvents[0]?.original || null,
        evidence_reference: `${obsRel}; ${screenshotRel}`, reproduction_steps: [`Navigate to ${surface.navigation}`,'Inspect WU008 surface runtime observer events'],
        classification: 'BLOCKING_IN_SCOPE', classification_rationale: 'Accepted authority contains zero identities; an admitted hit would contradict the bounded provider authority.', blocks_wu008: true,
      });
    } else if (matchingAdmitted.length > 0) {
      providerProofStatus = 'PASS';
      admittedIdentity = matchingAdmitted[0].original;
      observedProvider = matchingAdmitted[0].translation;
    } else {
      providerProofStatus = 'BLOCKED';
      addFinding(findings, {
        surface_id: surface.surface_id,
        observed_behavior: navigationOk ? `Positive Content Admission (${surface.admitted_message_count}) was reachable as a real surface, but no surface-bound admitted gettext identity was observed at runtime.` : `Surface navigation failed before positive-admission provider proof could be collected: ${navigationError}`,
        affected_identity_or_control: mismatchedAdmitted[0]?.original || null, evidence_reference: `${obsRel}; ${screenshotRel}; ${domRel}`,
        reproduction_steps: [`Navigate to ${surface.navigation} with wu008_surface=${surface.key}`,'Inspect surface-runtime-events.jsonl for admitted_gettext expected_match=true'],
        classification: navigationOk ? 'BLOCKING_IN_SCOPE' : 'ENVIRONMENT_DEFECT', classification_rationale: navigationOk ? 'Positive-admission surface executed but required runtime identity proof was absent; WU-008 must fail closed.' : 'Evidence harness/runtime could not reach the intended surface.', blocks_wu008: true,
      });
    }

    if (mismatchedAdmitted.length > 0) addFinding(findings, {
      surface_id: surface.surface_id, observed_behavior: 'An admitted identity resolved differently from the exact PersianGravity surface authority.', affected_identity_or_control: mismatchedAdmitted[0].original,
      evidence_reference: `${obsRel}; surface-runtime-events.jsonl`, reproduction_steps: [`Navigate to ${surface.navigation}`,`Observe admitted identity ${mismatchedAdmitted[0].original}`],
      classification: 'BLOCKING_IN_SCOPE', classification_rationale: 'The admitted identity was actually invoked on its accepted surface but runtime value did not match PersianGravity authority.', blocks_wu008: true,
    });
    if (rtl?.result === 'FAIL') addFinding(findings, {
      surface_id: surface.surface_id, observed_behavior: 'RTL/BiDi rendered-surface evaluation failed one or more required dimensions.', affected_identity_or_control: Object.entries(rtl.dimensions).filter(([,status]) => status === 'FAIL').map(([name]) => name),
      evidence_reference: `${obsRel}; ${screenshotRel}; ${domRel}`, reproduction_steps: [`Navigate to ${surface.navigation}`,'Inspect html/body direction, controls, clipping and mixed-direction samples'],
      classification: 'BLOCKING_IN_SCOPE', classification_rationale: 'WU-008 requires explicit rendered RTL/BiDi treatment for every accepted surface.', blocks_wu008: true,
    });
    if (js.status === 'FAIL') addFinding(findings, {
      surface_id: surface.surface_id, observed_behavior: 'Unexpected PersianGravity provider JSON/script-translation attachment was observed despite zero approved target-product JS handles.', affected_identity_or_control: [...js.pgr_provider_json_network_requests,...js.pgr_provider_json_hook_events.map((e)=>e.handle),...js.unexpected_pgr_translations_path_handles.map((e)=>e.handle)],
      evidence_reference: `${obsRel}; js-runtime-evidence.json`, reproduction_steps: [`Navigate to ${surface.navigation}`,'Inspect target-domain script handles, translation hook events and network requests'],
      classification: 'BLOCKING_IN_SCOPE', classification_rationale: 'Runtime behavior contradicts zero-handle/zero-provider-JSON authority.', blocks_wu008: true,
    });
    if (!navigationOk) addFinding(findings, {
      surface_id: surface.surface_id, observed_behavior: `Real-browser surface navigation failed: ${navigationError}`, affected_identity_or_control: 'surface reachability',
      evidence_reference: `${screenshotRel}; ${domRel}; runtime/browser-diagnostics.json`, reproduction_steps: [`Navigate to ${surface.navigation}`], classification: 'ENVIRONMENT_DEFECT',
      classification_rationale: 'Disposable harness failed to reach the intended target surface; this is not classified as a PersianGravity production defect without runtime reachability.', blocks_wu008: true,
    });

    const fallback = fallbackEvents.length > 0 ? {
      status: 'PASS', original: fallbackEvents[0].original, observed_translation: fallbackEvents[0].translation, unchanged_from_source: fallbackEvents[0].unchanged,
      rationale: 'Identity is absent from the PersianGravity aggregate admitted set, so its observed value is upstream/vendor/source fallback rather than PersianGravity provider authority.',
    } : { status: 'NOT_APPLICABLE', rationale: 'No representative non-admitted target-domain gettext call was exercised during this surface request; no fallback claim is inferred.' };
    const overall = navigationOk && (providerProofStatus === 'PASS' || providerProofStatus === 'NOT_APPLICABLE_ZERO_ADMISSION') && rtl?.result === 'PASS' && js.status === 'PASS' && mismatchedAdmitted.length === 0 ? 'PASS' : 'BLOCKED';

    fs.writeFileSync(path.join(artifactDir, obsRel), JSON.stringify({
      surface, navigation_ok: navigationOk, navigation_error: navigationError, final_url: page.url(), provider_file_observed: providerFileObserved,
      admitted_events: admittedEvents.slice(0,100), fallback_events: fallbackEvents.slice(0,50), translation_file_events: translationFiles.slice(0,50), rtl, js, dom, request_urls: requests.slice(0,500),
    }, null, 2) + '\n');
    rtlEvidence.push({ product: surface.product, surface_id: surface.surface_id, surface_key: surface.key, result: rtl.result, dimensions: rtl.dimensions, evidence_reference: `${screenshotRel}; ${domRel}; ${obsRel}`, observations: rtl.observations });
    matrix.push({
      product: surface.product, surface_id: surface.surface_id, fixture_setup: surface.fixture, navigation: surface.navigation, admitted_message_count: surface.admitted_message_count,
      admitted_runtime_identity: admittedIdentity, observed_provider_result: observedProvider, provider_proof_status: providerProofStatus, provider_file_observed: providerFileObserved,
      fallback_control_observation: fallback, rtl_bidi_result: rtl.result, js_boundary_result: js.status, evidence_reference: `${screenshotRel}; ${domRel}; ${obsRel}`, overall_surface_status: overall,
    });
  }
} finally {
  currentSurface = null;
  const environmentManifest = {
    schema_version: '2.0.0', evidence_class: 'REAL_LICENSED_HEADLESS_BROWSER_RUNTIME', persiangravity_expected_sha: expectedSha,
    persiangravity_actual_sha: manifest.persiangravity_actual_sha, product_under_test_verified_exact: manifest.persiangravity_actual_sha === expectedSha,
    wordpress_version: manifest.versions.wordpress, php_version: manifest.php_version || process.env.WU008_PHP_VERSION || null, locale: manifest.locale, rtl: manifest.rtl,
    browser: { name: 'Chromium', version: browserVersion, engine: 'Chromium', engine_version: browserVersion }, playwright_version: process.env.WU008_PLAYWRIGHT_VERSION || '1.55.0', node_version: process.version,
    runner: { os_platform: os.platform(), os_release: os.release(), arch: os.arch(), github_runner_os: process.env.RUNNER_OS || null, github_runner_arch: process.env.RUNNER_ARCH || null, github_run_id: process.env.GITHUB_RUN_ID || null, github_run_attempt: process.env.GITHUB_RUN_ATTEMPT || null, github_sha_harness: process.env.GITHUB_SHA || null, github_ref: process.env.GITHUB_REF || null },
    product_versions: { gravityforms: manifest.versions.gravityforms, gravityflow: manifest.versions.gravityflow, gravityview: manifest.versions.gravityview, persiangravity: manifest.versions.persiangravity },
    isolation_reset_strategy: { database:'Fresh MariaDB 11.4.8 GitHub Actions service database; discarded with runner.', wordpress_filesystem:'Fresh /tmp/wu008-wordpress WordPress 6.8.3 tree; discarded with runner.', uploads:'Fresh wp-content/uploads inside disposable runner.', cache:'No persistent application cache configured.', fixtures:'Created deterministically by tests/real-integration/setup-runtime.php.' },
    authority_summary: manifest.authority_summary, js_authority: manifest.js_authority, dependency_boundary: manifest.dependency_boundary, vendor_authenticity: 'NOT_PROVEN',
  };
  fs.writeFileSync(path.join(artifactDir,'environment-manifest.json'), JSON.stringify(environmentManifest,null,2)+'\n');
  fs.writeFileSync(path.join(artifactDir,'surface-evidence-matrix.json'), JSON.stringify({schema_version:'2.0.0',surfaces:matrix},null,2)+'\n');
  fs.writeFileSync(path.join(artifactDir,'surface-evidence-matrix.csv'), surfaceCsv(matrix));
  fs.writeFileSync(path.join(artifactDir,'rtl-bidi-evidence.json'), JSON.stringify({schema_version:'2.0.0',surfaces:rtlEvidence},null,2)+'\n');
  const globalJsonRequests = [...new Set(jsSurfaces.flatMap((surface) => surface.pgr_provider_json_network_requests || []))];
  const jsResult = jsSurfaces.length === 19 && jsSurfaces.every((surface) => surface.status === 'PASS') ? 'PASS' : 'FAIL';
  fs.writeFileSync(path.join(artifactDir,'js-runtime-evidence.json'), JSON.stringify({schema_version:'2.0.0',accepted_authority:{native_js_translation_handles:0,provider_translation_json_catalogs:0},result:jsResult,surfaces:jsSurfaces,unexpected_persiangravity_provider_json_requests:globalJsonRequests},null,2)+'\n');
  fs.writeFileSync(path.join(artifactDir,'finding-register.json'), JSON.stringify({schema_version:'2.0.0',findings},null,2)+'\n');
  fs.writeFileSync(path.join(artifactDir,'runtime','browser-diagnostics.json'), JSON.stringify(pageDiagnostics,null,2)+'\n');
  fs.writeFileSync(path.join(artifactDir,'browser-results.json'), JSON.stringify({schema_version:'2.0.0',exact_persiangravity_commit:expectedSha,authenticated,surfaces_executed:matrix.length,surfaces_passed:matrix.filter((row)=>row.overall_surface_status==='PASS').length,positive_admission_provider_pass:matrix.filter((row)=>row.admitted_message_count>0&&row.provider_proof_status==='PASS').length,zero_admission_provider_na:matrix.filter((row)=>row.provider_proof_status==='NOT_APPLICABLE_ZERO_ADMISSION').length,rtl_pass:rtlEvidence.filter((row)=>row.result==='PASS').length,js_result:jsResult,findings_count:findings.length},null,2)+'\n');
  await browser.close();
}

const allPass = matrix.length === 19
  && matrix.every((row) => row.overall_surface_status === 'PASS')
  && matrix.filter((row) => row.admitted_message_count > 0 && row.provider_proof_status === 'PASS').length === 18
  && matrix.filter((row) => row.surface_id === zeroAdmissionId && row.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length === 1
  && rtlEvidence.length === 19 && rtlEvidence.every((row) => row.result === 'PASS')
  && jsSurfaces.length === 19 && jsSurfaces.every((row) => row.status === 'PASS');
for (const row of matrix) console.log(`${row.overall_surface_status} ${row.surface_id} provider=${row.provider_proof_status} rtl=${row.rtl_bidi_result} js=${row.js_boundary_result}`);
if (!allPass) process.exitCode = 1;
