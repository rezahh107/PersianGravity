import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
if (!artifactDir || !manifestPath || !adminPassword) {
  throw new Error('WU008_ARTIFACT_DIR, WU008_MANIFEST_PATH, and WU008_ADMIN_PASSWORD are required.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (!Array.isArray(manifest.surfaces) || manifest.surfaces.length !== 19) {
  throw new Error('Runtime manifest must contain exactly 19 surfaces.');
}

const runtimeOrigin = new URL(manifest.admin_url || manifest.login_url).origin;
const legacyDiagnosticsPath = path.join(artifactDir, 'browser-diagnostics.json');
let providerRunRaw = null;
if (fs.existsSync(legacyDiagnosticsPath)) {
  providerRunRaw = JSON.parse(fs.readFileSync(legacyDiagnosticsPath, 'utf8'));
  fs.writeFileSync(
    path.join(artifactDir, 'browser-diagnostics-provider-run.json'),
    `${JSON.stringify(providerRunRaw, null, 2)}\n`,
  );
}

const diagnostics = [];
const perSurface = [];

function surfaceFromUrl(url, fallback = 'UNKNOWN') {
  try {
    return new URL(url).searchParams.get('wu008_surface') || fallback;
  } catch {
    return fallback;
  }
}

function isSameOrigin(url) {
  try {
    return new URL(url).origin === runtimeOrigin;
  } catch {
    return false;
  }
}

function isKnownCompressionProbe(url, message = '') {
  try {
    const parsed = new URL(url);
    return parsed.origin === runtimeOrigin
      && parsed.pathname === '/wp-admin/admin-ajax.php'
      && parsed.searchParams.get('action') === 'wp-compression-test'
      && parsed.searchParams.get('test') === 'yes'
      && /ERR_ABORTED|aborted/i.test(message);
  } catch {
    return false;
  }
}

function isKnownFavicon404(url, status = null) {
  try {
    const parsed = new URL(url);
    return parsed.origin === runtimeOrigin && parsed.pathname === '/favicon.ico' && Number(status) === 404;
  } catch {
    return false;
  }
}

function classify(kind, url, message = '', status = null) {
  if (kind === 'pageerror') {
    return {
      classification: 'BLOCKING_IN_SCOPE',
      blocks_acceptance: true,
      rationale: 'An uncaught page-level JavaScript exception occurred during an accepted surface execution; no unexplained pageerror is compatible with PASS.',
    };
  }
  if (kind === 'requestfailed' && isKnownCompressionProbe(url, message)) {
    return {
      classification: 'UPSTREAM_OR_VENDOR_BEHAVIOR',
      blocks_acceptance: false,
      rationale: 'WordPress core intentionally aborts its wp-compression-test probe after collecting the result; this exact request shape is upstream behavior.',
    };
  }
  if (kind === 'http_failure' && isKnownFavicon404(url, status)) {
    return {
      classification: 'UPSTREAM_OR_VENDOR_BEHAVIOR',
      blocks_acceptance: false,
      rationale: 'The disposable WordPress theme does not provide a favicon; a favicon.ico 404 is unrelated to the accepted product surface.',
    };
  }
  if (kind === 'console_error' && /favicon\.ico/i.test(url || '') && /404|Failed to load resource/i.test(message)) {
    return {
      classification: 'UPSTREAM_OR_VENDOR_BEHAVIOR',
      blocks_acceptance: false,
      rationale: 'Console error is the browser report for the known disposable-theme favicon.ico 404.',
    };
  }
  if (isSameOrigin(url)) {
    return {
      classification: 'BLOCKING_IN_SCOPE',
      blocks_acceptance: true,
      rationale: 'A same-origin application diagnostic occurred during an accepted surface execution and is not covered by a proven upstream exception.',
    };
  }
  return {
    classification: 'ENVIRONMENT_DEFECT',
    blocks_acceptance: true,
    rationale: 'A material browser failure occurred outside the disposable application origin and cannot be safely classified as non-blocking from this run.',
  };
}

function recordDiagnostic({ source, kind, surfaceId, url = '', message = '', status = null, reproduction = null, raw = null }) {
  const disposition = classify(kind, url, message, status);
  diagnostics.push({
    source,
    kind,
    surface_id: surfaceId || 'UNKNOWN',
    url,
    message,
    status,
    classification: disposition.classification,
    rationale: disposition.rationale,
    blocks_acceptance: disposition.blocks_acceptance,
    reproduction_context: reproduction,
    evidence_reference: 'browser-diagnostics.json',
    raw,
  });
}

function ingestProviderRun(raw) {
  if (!raw) return;
  for (const item of raw.page_errors || raw.pageErrors || []) {
    const url = typeof item === 'object' ? (item.url || '') : '';
    const message = typeof item === 'object' ? (item.error || item.message || JSON.stringify(item)) : String(item);
    recordDiagnostic({ source: 'provider_runtime_run', kind: 'pageerror', surfaceId: surfaceFromUrl(url, 'UNKNOWN_FROM_PROVIDER_RUN'), url, message, raw: item });
  }
  for (const item of raw.console || []) {
    if ((item.type || '') !== 'error') continue;
    const url = item.url || item.location?.url || '';
    recordDiagnostic({ source: 'provider_runtime_run', kind: 'console_error', surfaceId: surfaceFromUrl(url, 'UNKNOWN_FROM_PROVIDER_RUN'), url, message: item.text || '', raw: item });
  }
  for (const item of raw.request_failures || raw.requestFailures || []) {
    const url = item.url || '';
    recordDiagnostic({ source: 'provider_runtime_run', kind: 'requestfailed', surfaceId: surfaceFromUrl(url, 'UNKNOWN_FROM_PROVIDER_RUN'), url, message: item.error || '', raw: item });
  }
}

ingestProviderRun(providerRunRaw);

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1050 } });
const page = await context.newPage();
let currentSurfaceId = 'AUTHENTICATION';
let currentReproduction = 'Authenticate to WordPress admin';

page.on('pageerror', (error) => {
  recordDiagnostic({
    source: 'strict_19_surface_run',
    kind: 'pageerror',
    surfaceId: currentSurfaceId,
    url: page.url(),
    message: String(error?.stack || error),
    reproduction: currentReproduction,
  });
});
page.on('console', (msg) => {
  if (msg.type() !== 'error') return;
  const location = msg.location();
  recordDiagnostic({
    source: 'strict_19_surface_run',
    kind: 'console_error',
    surfaceId: currentSurfaceId,
    url: location?.url || page.url(),
    message: msg.text(),
    reproduction: currentReproduction,
    raw: location,
  });
});
page.on('requestfailed', (request) => {
  recordDiagnostic({
    source: 'strict_19_surface_run',
    kind: 'requestfailed',
    surfaceId: currentSurfaceId,
    url: request.url(),
    message: request.failure()?.errorText || 'unknown',
    reproduction: currentReproduction,
    raw: { method: request.method(), resource_type: request.resourceType() },
  });
});
page.on('response', (response) => {
  const status = response.status();
  if (status < 400) return;
  const url = response.url();
  if (!isSameOrigin(url) && status < 500) return;
  recordDiagnostic({
    source: 'strict_19_surface_run',
    kind: 'http_failure',
    surfaceId: currentSurfaceId,
    url,
    message: `HTTP ${status} ${response.statusText()}`,
    status,
    reproduction: currentReproduction,
    raw: { request_method: response.request().method(), resource_type: response.request().resourceType() },
  });
});

let authFailure = null;
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
} catch (error) {
  authFailure = String(error?.stack || error);
  diagnostics.push({
    source: 'strict_19_surface_run',
    kind: 'navigation_failure',
    surface_id: 'AUTHENTICATION',
    url: page.url(),
    message: authFailure,
    status: null,
    classification: 'ENVIRONMENT_DEFECT',
    rationale: 'Authenticated browser precondition failed before accepted-surface execution.',
    blocks_acceptance: true,
    reproduction_context: currentReproduction,
    evidence_reference: 'browser-diagnostics.json',
  });
}

for (const surface of manifest.surfaces) {
  const before = diagnostics.length;
  currentSurfaceId = surface.surface_id;
  currentReproduction = `Navigate authentic accepted surface ${surface.surface_id}`;
  let navigationResult = 'PASS';
  let navigationError = null;
  const target = new URL(surface.navigation);
  target.searchParams.set('wu008_surface', surface.surface_id);
  if (authFailure) {
    navigationResult = 'BLOCKED_AUTHENTICATION';
    navigationError = authFailure;
  } else {
    try {
      const response = await page.goto(target.toString(), { waitUntil: 'domcontentloaded', timeout: 30000 });
      if (!response) throw new Error('Navigation returned no main response.');
      if (!response.ok()) throw new Error(`Main navigation HTTP ${response.status()}`);
      await page.waitForTimeout(750);
      const text = await page.locator('body').innerText().catch(() => '');
      if (/There has been a critical error|Fatal error|Parse error/i.test(text)) {
        throw new Error('Fatal/critical error text observed in rendered surface.');
      }
      if (/wp-login\.php/i.test(new URL(page.url()).pathname)) {
        throw new Error('Accepted surface unexpectedly redirected to login.');
      }
    } catch (error) {
      navigationResult = 'FAIL';
      navigationError = String(error?.stack || error);
      diagnostics.push({
        source: 'strict_19_surface_run',
        kind: 'navigation_failure',
        surface_id: surface.surface_id,
        url: target.toString(),
        message: navigationError,
        status: null,
        classification: 'ENVIRONMENT_DEFECT',
        rationale: 'The authentic surface could not be reached cleanly in the strict diagnostic pass.',
        blocks_acceptance: true,
        reproduction_context: currentReproduction,
        evidence_reference: 'browser-diagnostics.json',
      });
    }
  }
  const newDiagnostics = diagnostics.slice(before);
  perSurface.push({
    surface_id: surface.surface_id,
    navigation: target.toString(),
    navigation_result: navigationResult,
    navigation_error: navigationError,
    meaningful_diagnostic_count: newDiagnostics.length,
    blocking_diagnostic_count: newDiagnostics.filter((item) => item.blocks_acceptance).length,
    result: navigationResult === 'PASS' && newDiagnostics.every((item) => !item.blocks_acceptance) ? 'PASS' : 'BLOCKED',
  });
}

await browser.close();

const remediationEvidencePath = path.join(artifactDir, 'form-builder-remediation', 'browser-evidence.json');
let remediationEvidence = null;
if (fs.existsSync(remediationEvidencePath)) {
  remediationEvidence = JSON.parse(fs.readFileSync(remediationEvidencePath, 'utf8'));
  const surfaceId = 'gravityforms::admin_builder::admin_page:gf_edit_forms';
  for (const item of remediationEvidence.page_errors || []) {
    recordDiagnostic({ source: 'form_builder_remediation_run', kind: 'pageerror', surfaceId, url: remediationEvidence.form_builder_url || '', message: item.message || item.stack || JSON.stringify(item), reproduction: 'Open Structured Scanner through real Gravity Forms Form Builder and exercise profile/mapping controls.', raw: item });
  }
  for (const item of remediationEvidence.console_messages || []) {
    if ((item.type || '') !== 'error') continue;
    recordDiagnostic({ source: 'form_builder_remediation_run', kind: 'console_error', surfaceId, url: remediationEvidence.form_builder_url || '', message: item.text || '', reproduction: 'Open Structured Scanner through real Gravity Forms Form Builder and exercise profile/mapping controls.', raw: item });
  }
  for (const item of remediationEvidence.request_failures || []) {
    recordDiagnostic({ source: 'form_builder_remediation_run', kind: 'requestfailed', surfaceId, url: item.url || '', message: item.error || '', reproduction: 'Open Structured Scanner through real Gravity Forms Form Builder and exercise profile/mapping controls.', raw: item });
  }
} else {
  diagnostics.push({
    source: 'form_builder_remediation_run',
    kind: 'missing_evidence',
    surface_id: 'gravityforms::admin_builder::admin_page:gf_edit_forms',
    url: '',
    message: 'Focused Form Builder remediation evidence file was not produced.',
    status: null,
    classification: 'ENVIRONMENT_DEFECT',
    rationale: 'Mandatory functional remediation evidence is missing.',
    blocks_acceptance: true,
    reproduction_context: 'Run scanner-editor-remediation.mjs against the same exact product runtime.',
    evidence_reference: 'browser-diagnostics.json',
  });
}

let findings = [];
const findingsPath = path.join(artifactDir, 'finding-register.json');
if (fs.existsSync(findingsPath)) {
  try { findings = JSON.parse(fs.readFileSync(findingsPath, 'utf8')); } catch { findings = []; }
}
for (const item of diagnostics) {
  findings.push({
    classification: item.classification,
    surface_id: item.surface_id,
    observed_behavior: `${item.kind}: ${item.message}`,
    url_or_resource: item.url,
    evidence_reference: item.evidence_reference,
    reproduction_steps: [item.reproduction_context || 'See browser-diagnostics.json reproduction_context.'],
    classification_rationale: item.rationale,
    blocks_wu008: Boolean(item.blocks_acceptance),
  });
}
fs.writeFileSync(findingsPath, `${JSON.stringify(findings, null, 2)}\n`);

const summary = {
  strict_surfaces_total: perSurface.length,
  strict_surfaces_passed: perSurface.filter((row) => row.result === 'PASS').length,
  diagnostics_total: diagnostics.length,
  blocking_diagnostics: diagnostics.filter((item) => item.blocks_acceptance).length,
  pageerrors_total: diagnostics.filter((item) => item.kind === 'pageerror').length,
  console_errors_total: diagnostics.filter((item) => item.kind === 'console_error').length,
  request_failures_total: diagnostics.filter((item) => item.kind === 'requestfailed').length,
  http_failures_total: diagnostics.filter((item) => item.kind === 'http_failure').length,
  classifications: Object.fromEntries(['BLOCKING_IN_SCOPE','NON_BLOCKING_IN_SCOPE','OUT_OF_SCOPE_UNCLASSIFIED_CONTENT','UPSTREAM_OR_VENDOR_BEHAVIOR','ENVIRONMENT_DEFECT'].map((key) => [key, diagnostics.filter((item) => item.classification === key).length])),
  result: perSurface.length === 19 && perSurface.every((row) => row.result === 'PASS') && diagnostics.every((item) => !item.blocks_acceptance) ? 'PASS' : 'BLOCKED',
};

fs.writeFileSync(legacyDiagnosticsPath, `${JSON.stringify({
  schema_version: '2.0.0',
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  provider_run_raw: providerRunRaw,
  form_builder_remediation_result: remediationEvidence?.result || 'MISSING',
  per_surface: perSurface,
  diagnostics,
  summary,
}, null, 2)}\n`);

console.log(JSON.stringify(summary));
if (summary.result !== 'PASS') process.exit(1);
