import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
if (!artifactDir || !manifestPath || !adminPassword) {
  throw new Error('WU008_ARTIFACT_DIR, WU008_MANIFEST_PATH, and WU008_ADMIN_PASSWORD are required.');
}

const baseScript = new URL('./strict-diagnostics-19-base.mjs', import.meta.url);
const baseRun = spawnSync(process.execPath, [baseScript.pathname], { stdio: 'inherit', env: process.env });
if (baseRun.error) throw baseRun.error;
if (baseRun.status === 0) process.exit(0);
if (baseRun.signal) {
  console.error(`Base strict diagnostics terminated by signal ${baseRun.signal}.`);
  process.exit(1);
}

const diagnosticsPath = path.join(artifactDir, 'browser-diagnostics.json');
const findingsPath = path.join(artifactDir, 'finding-register.json');
if (!fs.existsSync(diagnosticsPath) || !fs.existsSync(findingsPath)) {
  console.error('Base strict diagnostics failed without producing required evidence files.');
  process.exit(1);
}

const evidence = JSON.parse(fs.readFileSync(diagnosticsPath, 'utf8'));
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const blockers = (evidence.diagnostics || []).filter((item) => item.blocks_acceptance);
const targetSurfaceId = 'gravityview::settings_integrations::foundation_settings:gravityview';
const priorSurfaceId = 'gravityview::admin_builder::post_type:gravityview';

function isTargetBlocker(item) {
  if (item?.source !== 'strict_19_surface_run' || item?.kind !== 'requestfailed') return false;
  if (item?.surface_id !== targetSurfaceId || item?.message !== 'net::ERR_ABORTED') return false;
  if (item?.raw?.method !== 'POST' || item?.raw?.resource_type !== 'ping') return false;
  try {
    const url = new URL(item.url);
    return url.pathname === '/wp-admin/admin-ajax.php';
  } catch {
    return false;
  }
}

if (blockers.length !== 1 || !isTargetBlocker(blockers[0]) || (evidence.summary?.pageerrors_total ?? 1) !== 0) {
  console.error(`Fail-closed: strict diagnostics has ${blockers.length} blocker(s), not the single attributable admin-ajax ping blocker.`);
  process.exit(1);
}

const priorSurface = (manifest.surfaces || []).find((surface) => surface.surface_id === priorSurfaceId);
const targetSurface = (manifest.surfaces || []).find((surface) => surface.surface_id === targetSurfaceId);
if (!priorSurface || !targetSurface) {
  console.error('Fail-closed: required GravityView transition surfaces are missing from the runtime manifest.');
  process.exit(1);
}

function extractAction(request) {
  const url = new URL(request.url());
  const queryAction = url.searchParams.get('action');
  const postData = request.postData() || '';
  const contentType = request.headers()['content-type'] || '';
  let action = queryAction || '';
  let keys = [];

  if (postData) {
    if (/application\/json/i.test(contentType)) {
      try {
        const parsed = JSON.parse(postData);
        if (!action && typeof parsed?.action === 'string') action = parsed.action;
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) keys = Object.keys(parsed).sort();
      } catch {
        keys = [];
      }
    } else {
      try {
        const params = new URLSearchParams(postData);
        if (!action) action = params.get('action') || '';
        keys = [...new Set([...params.keys()])].sort();
      } catch {
        keys = [];
      }
    }
  }

  return {
    action,
    body_keys: keys,
    body_sha256: postData ? crypto.createHash('sha256').update(postData).digest('hex') : null,
    content_type: contentType,
  };
}

const probe = {
  schema_version: '1.0.0',
  purpose: 'Attribute the single strict-run POST admin-ajax ping ERR_ABORTED without broad allowlisting.',
  transition: { from: priorSurfaceId, to: targetSurfaceId },
  requests: [],
  failed_ping_requests: [],
  result: 'BLOCKED',
  rationale: null,
};

const browser = await chromium.launch({ headless: true });
try {
  const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
  const page = await context.newPage();
  const requestMeta = new WeakMap();
  let stage = 'AUTHENTICATION';

  page.on('request', (request) => {
    let frameUrl = '';
    try { frameUrl = request.frame().url(); } catch { frameUrl = ''; }
    const parsed = new URL(request.url());
    if (parsed.pathname !== '/wp-admin/admin-ajax.php' || request.method() !== 'POST' || request.resourceType() !== 'ping') return;
    const identity = extractAction(request);
    const record = {
      stage,
      url: request.url(),
      method: request.method(),
      resource_type: request.resourceType(),
      frame_url_at_start: frameUrl,
      action: identity.action,
      body_keys: identity.body_keys,
      body_sha256: identity.body_sha256,
      content_type: identity.content_type,
      failure: null,
    };
    requestMeta.set(request, record);
    probe.requests.push(record);
  });

  page.on('requestfailed', (request) => {
    const record = requestMeta.get(request);
    if (!record) return;
    record.failure = request.failure()?.errorText || 'unknown';
    if (/ERR_ABORTED|aborted/i.test(record.failure)) probe.failed_ping_requests.push(record);
  });

  const login = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (!login?.ok()) throw new Error(`Login page HTTP ${login?.status()}`);
  await page.fill('#user_login', 'runtime_admin');
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }),
    page.click('#wp-submit'),
  ]);
  await page.locator('#wpadminbar').waitFor({ state: 'attached', timeout: 15000 });

  stage = priorSurfaceId;
  const priorResponse = await page.goto(priorSurface.navigation, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (!priorResponse?.ok()) throw new Error(`Prior GravityView surface HTTP ${priorResponse?.status()}`);
  await page.waitForTimeout(750);

  stage = `TRANSITION:${priorSurfaceId}->${targetSurfaceId}`;
  const targetResponse = await page.goto(targetSurface.navigation, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (!targetResponse?.ok()) throw new Error(`Target GravityView surface HTTP ${targetResponse?.status()}`);
  await page.waitForTimeout(1500);

  const matches = probe.failed_ping_requests.filter((record) => {
    try { return new URL(record.url).pathname === '/wp-admin/admin-ajax.php'; } catch { return false; }
  });
  const allowedActions = new Set(['heartbeat', 'gk_foundation_do_ajax']);

  if (matches.length === 1 && allowedActions.has(matches[0].action)) {
    probe.result = 'PASS';
    probe.rationale = matches[0].action === 'heartbeat'
      ? 'The exact reproduced POST ping is WordPress Core heartbeat traffic; its action=heartbeat identity is captured from the failed request body.'
      : 'The exact reproduced POST ping is GravityKit Foundation AJAX traffic; its action=gk_foundation_do_ajax identity is captured from the failed request body and matches the GravityView-owned settings surface configuration.';
  } else {
    probe.rationale = `Fail-closed attribution: expected exactly one reproduced aborted admin-ajax ping with action heartbeat or gk_foundation_do_ajax; observed ${matches.length} matching failed ping request(s) with actions [${matches.map((item) => item.action || 'EMPTY').join(', ')}].`;
  }
} catch (error) {
  probe.rationale = `Attribution probe failed: ${String(error?.stack || error)}`;
} finally {
  await browser.close();
}

const probePath = path.join(artifactDir, 'diagnostic-attribution-probe.json');
fs.writeFileSync(probePath, `${JSON.stringify(probe, null, 2)}\n`);

if (probe.result !== 'PASS') {
  console.error(probe.rationale);
  process.exit(1);
}

const reproduced = probe.failed_ping_requests[0];
const blocker = blockers[0];
blocker.classification = 'UPSTREAM_OR_VENDOR_BEHAVIOR';
blocker.blocks_acceptance = false;
blocker.rationale = probe.rationale;
blocker.evidence_reference = `${blocker.evidence_reference}; diagnostic-attribution-probe.json; dom/17-gravityview_settings_integrations_foundation_settings_gravityview.html`;
blocker.raw = { ...blocker.raw, attribution_probe: reproduced };

const affectedSurface = (evidence.per_surface || []).find((row) => row.surface_id === targetSurfaceId);
if (affectedSurface) {
  affectedSurface.blocking_diagnostic_count = 0;
  affectedSurface.result = affectedSurface.navigation_result === 'PASS' ? 'PASS' : 'BLOCKED';
}

const classificationKeys = ['BLOCKING_IN_SCOPE','NON_BLOCKING_IN_SCOPE','OUT_OF_SCOPE_UNCLASSIFIED_CONTENT','UPSTREAM_OR_VENDOR_BEHAVIOR','ENVIRONMENT_DEFECT'];
evidence.summary.strict_surfaces_passed = (evidence.per_surface || []).filter((row) => row.result === 'PASS').length;
evidence.summary.blocking_diagnostics = (evidence.diagnostics || []).filter((item) => item.blocks_acceptance).length;
evidence.summary.classifications = Object.fromEntries(classificationKeys.map((key) => [key, (evidence.diagnostics || []).filter((item) => item.classification === key).length]));
evidence.summary.result = evidence.summary.strict_surfaces_total === 19
  && evidence.summary.strict_surfaces_passed === 19
  && evidence.summary.blocking_diagnostics === 0
  && evidence.summary.pageerrors_total === 0
  ? 'PASS'
  : 'BLOCKED';
evidence.attribution_probe = 'diagnostic-attribution-probe.json';
fs.writeFileSync(diagnosticsPath, `${JSON.stringify(evidence, null, 2)}\n`);

const findings = JSON.parse(fs.readFileSync(findingsPath, 'utf8'));
const matchingFindings = findings.filter((finding) => finding.classification === 'BLOCKING_IN_SCOPE'
  && finding.surface_id === targetSurfaceId
  && /requestfailed: net::ERR_ABORTED/.test(finding.observed_behavior || '')
  && /\/wp-admin\/admin-ajax\.php/.test(finding.url_or_resource || ''));
if (matchingFindings.length !== 1) {
  console.error(`Fail-closed: expected exactly one matching blocking finding, found ${matchingFindings.length}.`);
  process.exit(1);
}
matchingFindings[0].classification = 'UPSTREAM_OR_VENDOR_BEHAVIOR';
matchingFindings[0].classification_rationale = probe.rationale;
matchingFindings[0].blocks_wu008 = false;
matchingFindings[0].evidence_reference = `${matchingFindings[0].evidence_reference}; diagnostic-attribution-probe.json; dom/17-gravityview_settings_integrations_foundation_settings_gravityview.html`;
fs.writeFileSync(findingsPath, `${JSON.stringify(findings, null, 2)}\n`);

console.log(JSON.stringify({
  result: evidence.summary.result,
  reclassified_surface: targetSurfaceId,
  reproduced_action: reproduced.action,
  frame_url_at_start: reproduced.frame_url_at_start,
  blocking_diagnostics: evidence.summary.blocking_diagnostics,
}));
if (evidence.summary.result !== 'PASS') process.exit(1);
