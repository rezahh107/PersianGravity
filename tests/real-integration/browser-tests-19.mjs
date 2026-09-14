import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
if (!artifactDir || !manifestPath || !adminPassword) throw new Error('WU008 artifact/manifest/admin password environment is required.');

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const authority = JSON.parse(fs.readFileSync(path.join(artifactDir, 'surface-authority.json'), 'utf8'));
if (manifest.exact_persiangravity_commit_actual !== process.env.WU008_PGR_SHA) throw new Error('Product-under-test SHA mismatch in runtime manifest.');
if (!Array.isArray(manifest.surfaces) || manifest.surfaces.length !== 19) throw new Error('Runtime manifest must contain exactly 19 surfaces.');

const screenshotsDir = path.join(artifactDir, 'screenshots');
const domDir = path.join(artifactDir, 'dom');
fs.mkdirSync(screenshotsDir, { recursive: true });
fs.mkdirSync(domDir, { recursive: true });

const sanitize = (value) => value.replace(/[^A-Za-z0-9._-]+/g, '_');
const withSurface = (url, surfaceId) => {
  const u = new URL(url);
  u.searchParams.set('wu008_surface', surfaceId);
  return u.toString();
};
const tracePath = (surfaceId) => path.join(artifactDir, 'request-traces', `${sanitize(surfaceId)}.json`);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function authorityKey(msgid, context = '') { return `${context}\u0004${msgid}`; }
function makeAuthorityMap(entries) {
  const map = new Map();
  for (const entry of entries || []) map.set(authorityKey(entry.msgid, entry.context || ''), entry.translations || []);
  return map;
}
const aggregateMaps = Object.fromEntries(Object.entries(authority.aggregate).map(([domain, entries]) => [domain, makeAuthorityMap(entries)]));

async function evaluateRtl(page) {
  return page.evaluate(() => {
    const visible = (el) => {
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
    };
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
      toolbars: '[role="toolbar"],.components-toolbar,.wp-editor-tools,.gform-toolbar',
      directional_icons: '[class*="arrow"],[class*="caret"],.dashicons-arrow-left,.dashicons-arrow-right,.dashicons-arrow-left-alt,.dashicons-arrow-right-alt',
      technical_ltr: 'code,pre,a[href^="http"],input[type="email"],input[type="url"]',
    };
    const html = document.documentElement;
    const body = document.body;
    const viewportWidth = window.innerWidth;
    const result = {
      html_dir: html.getAttribute('dir'),
      html_lang: html.getAttribute('lang'),
      body_direction: getComputedStyle(body).direction,
      page_horizontal_overflow_px: Math.max(0, body.scrollWidth - viewportWidth),
      dimensions: {},
    };
    for (const [name, selector] of Object.entries(selectors)) {
      const nodes = [...document.querySelectorAll(selector)].filter(visible).slice(0, 80);
      if (nodes.length === 0) {
        result.dimensions[name] = { status: 'NOT_APPLICABLE', count: 0, reason: 'No visible matching controls on this surface.' };
        continue;
      }
      const samples = nodes.slice(0, 8).map((el) => {
        const rect = el.getBoundingClientRect();
        const style = getComputedStyle(el);
        return {
          tag: el.tagName,
          class: typeof el.className === 'string' ? el.className.slice(0, 180) : '',
          text: ((el.innerText || el.value || el.getAttribute('aria-label') || '') + '').trim().slice(0, 160),
          direction: style.direction,
          unicode_bidi: style.unicodeBidi,
          left: Math.round(rect.left), right: Math.round(rect.right), width: Math.round(rect.width),
          clipped_horizontally: rect.right > viewportWidth + 8 || rect.left < -8,
          internal_overflow: el.scrollWidth > el.clientWidth + 8,
        };
      });
      const hardClip = samples.some((s) => s.clipped_horizontally);
      result.dimensions[name] = { status: hardClip ? 'FAIL' : 'PASS', count: nodes.length, samples };
    }
    result.status = result.html_dir === 'rtl' && result.body_direction === 'rtl' && result.page_horizontal_overflow_px <= 24 && !Object.values(result.dimensions).some((d) => d.status === 'FAIL') ? 'PASS' : 'FAIL';
    return result;
  });
}

function findFallback(surface, trace) {
  const aggregate = aggregateMaps[surface.domain];
  if (!aggregate) return { status: 'NOT_APPLICABLE', reason: 'No aggregate provider authority map.' };
  const call = (trace.gettext || []).find((row) => row.domain === surface.domain && !aggregate.has(authorityKey(row.msgid, row.context || '')));
  if (!call) return { status: 'NOT_APPLICABLE', reason: 'No non-admitted target-domain gettext call observed on this request.' };
  return { status: 'PASS_NON_ADMITTED_NOT_IN_PROVIDER_AUTHORITY', msgid: call.msgid, context: call.context || '', observed_translation: call.translation };
}

function analyzeTrace(surface, trace) {
  const surfaceAuthority = authority.surfaces[surface.surface_id];
  if (!surfaceAuthority) throw new Error(`Missing authority for ${surface.surface_id}`);
  if (surface.admitted_message_count === 0) {
    return {
      provider_proof_status: 'NOT_APPLICABLE_ZERO_ADMISSION',
      admitted_runtime_identity: null,
      provider_file_observed: false,
      fallback_control: findFallback(surface, trace),
    };
  }
  const surfaceMap = makeAuthorityMap(surfaceAuthority.entries);
  let matched = null;
  for (const call of trace.gettext || []) {
    if (call.domain !== surface.domain) continue;
    const allowed = surfaceMap.get(authorityKey(call.msgid, call.context || ''));
    if (!allowed) continue;
    if (allowed.includes(call.translation)) {
      matched = { msgid: call.msgid, context: call.context || '', translation: call.translation, kind: call.kind };
      break;
    }
  }
  const providerFileObserved = (trace.translation_files || []).some((row) => row.domain === surface.domain && /persian-gravityforms\/languages\/providers\/.+\.(mo|l10n\.php)$/.test(row.file || ''));
  return {
    provider_proof_status: matched && providerFileObserved ? 'PASS' : 'BLOCKED',
    admitted_runtime_identity: matched,
    provider_file_observed: providerFileObserved,
    fallback_control: findFallback(surface, trace),
  };
}

async function assertReachability(page, surface, response) {
  if (!response || !response.ok()) throw new Error(`HTTP navigation failed: ${response?.status()}`);
  const bodyText = await page.locator('body').innerText().catch(() => '');
  if (/There has been a critical error|Fatal error|Parse error/i.test(bodyText)) throw new Error('Fatal/critical error observed in rendered surface.');
  if (/wp-login\.php/.test(new URL(page.url()).pathname)) throw new Error('Surface navigation unexpectedly redirected to login.');
  if (surface.navigation.includes('/wp-admin/')) {
    await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 15000 });
    await page.locator('#adminmenu').waitFor({ state: 'attached', timeout: 15000 });
  }
}

async function maybeExerciseSurface(page, surface) {
  if (surface.surface_id === 'gravityforms::frontend_runtime::shortcode:gravityform') {
    const form = page.locator(`#gform_${manifest.fixtures.form_id}`);
    await form.waitFor({ timeout: 15000 }).catch(() => {});
    const submit = form.locator('input[type="submit"],button[type="submit"]').first();
    if (await submit.count()) {
      await submit.click().catch(() => {});
      await page.waitForLoadState('domcontentloaded').catch(() => {});
    }
  }
}

function alternateNavigation(surface) {
  if (surface.surface_id === 'gravityforms::frontend_runtime::block:gravityforms/form') {
    return new URL(`post.php?post=${manifest.fixtures.gf_block_page_id}&action=edit`, manifest.admin_url).toString();
  }
  if (surface.surface_id === 'gravityview::frontend_runtime::block:gk-gravityview-blocks/view') {
    return new URL(`post.php?post=${manifest.fixtures.gv_block_page_id}&action=edit`, manifest.admin_url).toString();
  }
  if (surface.surface_id === 'gravityview::frontend_runtime::widget:gravityview_widget_search') {
    return new URL(`post.php?post=${manifest.fixtures.view_id}&action=edit`, manifest.admin_url).toString();
  }
  return null;
}

async function readTrace(surfaceId) {
  const file = tracePath(surfaceId);
  for (let i = 0; i < 30; i++) {
    if (fs.existsSync(file)) return JSON.parse(fs.readFileSync(file, 'utf8'));
    await sleep(100);
  }
  throw new Error(`Request trace not produced for ${surfaceId}`);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
const page = await context.newPage();
const diagnostics = { console: [], page_errors: [], request_failures: [], provider_json_requests: [] };
page.on('console', (msg) => diagnostics.console.push({ type: msg.type(), text: msg.text().slice(0, 1600), url: page.url() }));
page.on('pageerror', (error) => diagnostics.page_errors.push({ error: String(error?.stack || error).slice(0, 4000), url: page.url() }));
page.on('requestfailed', (req) => diagnostics.request_failures.push({ url: req.url(), error: req.failure()?.errorText || 'unknown' }));
page.on('request', (req) => {
  if (/persian-gravityforms\/languages\/providers\/.*-fa_IR-.*\.json/i.test(req.url())) diagnostics.provider_json_requests.push(req.url());
});

const envPath = path.join(artifactDir, 'environment-manifest.json');
const env = JSON.parse(fs.readFileSync(envPath, 'utf8'));
env.browser = { name: 'Chromium', version: browser.version(), engine: 'Chromium', playwright_runtime: process.version };
env.runner.node_version = process.version;
env.runner.platform = process.platform;
env.runner.arch = process.arch;
env.runner.hostname = os.hostname();
fs.writeFileSync(envPath, JSON.stringify(env, null, 2) + '\n');

const matrix = [];
const rtlEvidence = [];
const findings = [];
let authError = null;

try {
  const loginResponse = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
  if (!loginResponse || !loginResponse.ok()) throw new Error(`Login page failed: ${loginResponse?.status()}`);
  await page.fill('#user_login', 'runtime_admin');
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);
  await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 15000 });
} catch (error) {
  authError = String(error?.stack || error);
}

let index = 0;
for (const surface of manifest.surfaces) {
  index += 1;
  const baseRow = {
    product: surface.product,
    surface_id: surface.surface_id,
    fixture_setup: manifest.fixtures,
    navigation: surface.navigation,
    admitted_message_count: surface.admitted_message_count,
    admitted_runtime_identity_selected_for_proof: null,
    observed_provider_result: null,
    provider_proof_status: surface.admitted_message_count === 0 ? 'NOT_APPLICABLE_ZERO_ADMISSION' : 'BLOCKED',
    fallback_control_observation: null,
    rtl_bidi_result: 'FAIL',
    evidence_references: [],
    overall_surface_status: 'BLOCKED',
  };
  if (authError) {
    baseRow.runtime_execution = 'BLOCKED_AUTHENTICATION';
    baseRow.runtime_error = authError;
    matrix.push(baseRow);
    findings.push({ classification: 'ENVIRONMENT_DEFECT', surface_id: surface.surface_id, observed_behavior: 'Shared browser authentication failed.', evidence_reference: 'surface-evidence-matrix.json', reproduction_steps: ['Open manifest login URL', 'Authenticate as runtime_admin'], classification_rationale: 'Harness precondition failed before product surface execution.', blocks_wu008: true });
    continue;
  }

  let usedUrl = surface.navigation;
  let response;
  let trace;
  let provider;
  let runtimeStatus = 'PASS';
  let runtimeError = null;

  try {
    fs.rmSync(tracePath(surface.surface_id), { force: true });
    response = await page.goto(withSurface(usedUrl, surface.surface_id), { waitUntil: 'domcontentloaded', timeout: 30000 });
    await assertReachability(page, surface, response);
    await maybeExerciseSurface(page, surface);
    trace = await readTrace(surface.surface_id);
    provider = analyzeTrace(surface, trace);

    if (surface.admitted_message_count > 0 && provider.provider_proof_status !== 'PASS') {
      const alternate = alternateNavigation(surface);
      if (alternate) {
        fs.rmSync(tracePath(surface.surface_id), { force: true });
        usedUrl = alternate;
        response = await page.goto(withSurface(alternate, surface.surface_id), { waitUntil: 'domcontentloaded', timeout: 30000 });
        await assertReachability(page, { ...surface, navigation: alternate }, response);
        trace = await readTrace(surface.surface_id);
        provider = analyzeTrace(surface, trace);
      }
    }
  } catch (error) {
    runtimeStatus = 'FAIL';
    runtimeError = String(error?.stack || error);
  }

  let rtl = { status: 'FAIL', reason: 'Surface did not reach RTL evaluation.' };
  if (runtimeStatus === 'PASS') {
    try { rtl = await evaluateRtl(page); } catch (error) { rtl = { status: 'FAIL', error: String(error?.stack || error) }; }
  }

  const prefix = `${String(index).padStart(2, '0')}-${sanitize(surface.surface_id).slice(0, 110)}`;
  const screenshot = path.join('screenshots', `${prefix}.png`);
  const dom = path.join('dom', `${prefix}.html`);
  await page.screenshot({ path: path.join(artifactDir, screenshot), fullPage: true }).catch(() => {});
  fs.writeFileSync(path.join(artifactDir, dom), await page.content().catch(() => '<!-- unavailable -->') + '\n');

  const providerAcceptable = surface.admitted_message_count === 0 ? provider?.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION' : provider?.provider_proof_status === 'PASS';
  const overall = runtimeStatus === 'PASS' && providerAcceptable && rtl.status === 'PASS' ? 'PASS' : 'BLOCKED';
  const row = {
    ...baseRow,
    navigation: usedUrl,
    runtime_execution: runtimeStatus,
    runtime_error: runtimeError,
    admitted_runtime_identity_selected_for_proof: provider?.admitted_runtime_identity || null,
    observed_provider_result: provider?.admitted_runtime_identity?.translation || null,
    provider_file_observed: provider?.provider_file_observed ?? null,
    provider_proof_status: provider?.provider_proof_status || baseRow.provider_proof_status,
    fallback_control_observation: provider?.fallback_control || null,
    rtl_bidi_result: rtl.status,
    evidence_references: [screenshot, dom, path.relative(artifactDir, tracePath(surface.surface_id))],
    overall_surface_status: overall,
  };
  matrix.push(row);
  rtlEvidence.push({ surface_id: surface.surface_id, result: rtl.status, details: rtl, screenshot });

  if (runtimeStatus !== 'PASS') {
    findings.push({ classification: 'ENVIRONMENT_DEFECT', surface_id: surface.surface_id, observed_behavior: runtimeError || 'Runtime navigation failed.', evidence_reference: screenshot, reproduction_steps: [`Navigate to ${usedUrl}`], classification_rationale: 'Surface could not be reached/evaluated; classify as harness/environment until product failure is isolated.', blocks_wu008: true });
  } else if (!providerAcceptable) {
    findings.push({ classification: 'BLOCKING_IN_SCOPE', surface_id: surface.surface_id, observed_behavior: 'Positive-admission surface produced no surface-bound admitted provider proof in the real runtime request.', affected_identity_control: null, evidence_reference: path.relative(artifactDir, tracePath(surface.surface_id)), reproduction_steps: [`Navigate to ${usedUrl}`, 'Inspect request-local gettext trace against exact surface admission record'], classification_rationale: 'Required provider identity proof is absent after a successful real surface request.', blocks_wu008: true });
  }
  if (rtl.status === 'FAIL') {
    findings.push({ classification: 'BLOCKING_IN_SCOPE', surface_id: surface.surface_id, observed_behavior: 'RTL/BiDi automated rendered-control evaluation failed.', affected_identity_control: rtl, evidence_reference: screenshot, reproduction_steps: [`Navigate to ${usedUrl}`, 'Inspect screenshot and rtl-bidi-evidence.json'], classification_rationale: 'Every accepted surface requires RTL/BiDi evaluation.', blocks_wu008: true });
  }
}

const surfaceIds = matrix.map((r) => r.surface_id);
if (surfaceIds.length !== 19 || new Set(surfaceIds).size !== 19) throw new Error('Surface matrix accounting is not exactly 19 unique records.');

const allTraces = matrix.map((row) => {
  const p = tracePath(row.surface_id);
  return fs.existsSync(p) ? JSON.parse(fs.readFileSync(p, 'utf8')) : null;
}).filter(Boolean);
const jsMutations = allTraces.flatMap((trace) => (trace.js_mutations || []).map((m) => ({ surface_id: trace.surface_id, ...m })));
const jsEvidence = {
  accepted_script_maps: manifest.script_maps,
  provider_translation_json_catalogs_at_runtime: manifest.provider_translation_json_catalogs,
  observed_persiangravity_provider_json_network_requests: diagnostics.provider_json_requests,
  observed_persiangravity_script_translation_mutations: jsMutations,
  result: Object.values(manifest.script_maps).every((items) => items.length === 0)
    && manifest.provider_translation_json_catalogs.length === 0
    && diagnostics.provider_json_requests.length === 0
    && jsMutations.length === 0 ? 'PASS' : 'FAIL',
};
if (jsEvidence.result === 'FAIL') {
  findings.push({ classification: 'BLOCKING_IN_SCOPE', surface_id: null, observed_behavior: 'Runtime JS/catalog authority contradicted accepted zero-handle/zero-provider-JSON boundary.', evidence_reference: 'js-runtime-evidence.json', reproduction_steps: ['Execute all 19 surfaces and inspect script translation observer/network evidence.'], classification_rationale: 'PersianGravity must not fabricate target-product JS translation authority.', blocks_wu008: true });
}

const packageJsonl = path.join(artifactDir, 'package-verification.jsonl');
const packageVerification = fs.existsSync(packageJsonl)
  ? fs.readFileSync(packageJsonl, 'utf8').trim().split(/\n+/).filter(Boolean).map((line) => JSON.parse(line))
  : [];
fs.writeFileSync(path.join(artifactDir, 'package-verification.json'), JSON.stringify({ vendor_authenticity: 'NOT_PROVEN', packages: packageVerification }, null, 2) + '\n');
fs.writeFileSync(path.join(artifactDir, 'surface-evidence-matrix.json'), JSON.stringify(matrix, null, 2) + '\n');

const csvColumns = ['product','surface_id','navigation','admitted_message_count','admitted_runtime_identity_selected_for_proof','observed_provider_result','provider_proof_status','fallback_control_observation','rtl_bidi_result','runtime_execution','overall_surface_status','evidence_references'];
const csvEscape = (value) => `"${String(value ?? '').replaceAll('"','""')}"`;
const csv = [csvColumns.join(','), ...matrix.map((row) => csvColumns.map((column) => csvEscape(typeof row[column] === 'object' ? JSON.stringify(row[column]) : row[column])).join(','))].join('\n') + '\n';
fs.writeFileSync(path.join(artifactDir, 'surface-evidence-matrix.csv'), csv);
fs.writeFileSync(path.join(artifactDir, 'rtl-bidi-evidence.json'), JSON.stringify(rtlEvidence, null, 2) + '\n');
fs.writeFileSync(path.join(artifactDir, 'js-runtime-evidence.json'), JSON.stringify(jsEvidence, null, 2) + '\n');
fs.writeFileSync(path.join(artifactDir, 'finding-register.json'), JSON.stringify(findings, null, 2) + '\n');
fs.writeFileSync(path.join(artifactDir, 'browser-diagnostics.json'), JSON.stringify(diagnostics, null, 2) + '\n');

const summary = {
  surfaces_executed: matrix.filter((r) => r.runtime_execution === 'PASS').length,
  surfaces_total: 19,
  positive_admission_surfaces: matrix.filter((r) => r.admitted_message_count > 0).length,
  positive_provider_proofs_passed: matrix.filter((r) => r.admitted_message_count > 0 && r.provider_proof_status === 'PASS').length,
  zero_admission_provider_na: matrix.filter((r) => r.provider_proof_status === 'NOT_APPLICABLE_ZERO_ADMISSION').length,
  rtl_pass: matrix.filter((r) => r.rtl_bidi_result === 'PASS').length,
  rtl_fail: matrix.filter((r) => r.rtl_bidi_result === 'FAIL').length,
  js_result: jsEvidence.result,
  blocking_findings: findings.filter((f) => f.blocks_wu008).length,
  overall_result: matrix.every((r) => r.overall_surface_status === 'PASS') && jsEvidence.result === 'PASS' && packageVerification.length === 3 ? 'PASS' : 'BLOCKED',
};
fs.writeFileSync(path.join(artifactDir, 'browser-results.json'), JSON.stringify({ schema_version: '2.0.0', exact_persiangravity_commit: process.env.WU008_PGR_SHA, summary, matrix, diagnostics }, null, 2) + '\n');

await browser.close();
for (const row of matrix) console.log(`${row.overall_surface_status} ${row.surface_id} provider=${row.provider_proof_status} rtl=${row.rtl_bidi_result}`);
console.log(JSON.stringify(summary));
if (summary.overall_result !== 'PASS') process.exit(1);
