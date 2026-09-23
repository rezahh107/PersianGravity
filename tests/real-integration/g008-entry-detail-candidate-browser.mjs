import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const mode = process.env.WU008_G008_MODE;
if (!artifactDir || !manifestPath || !adminPassword || !['enabled', 'disabled'].includes(mode)) {
  throw new Error('Entry Detail candidate browser requires artifact/manifest/admin password and enabled|disabled mode.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const fixture = (manifest.g008_flow_entry_detail_candidate_entries ?? []).find(
  (entry) => Number(entry.id) === Number(manifest.g008_flow_entry_detail_candidate_entry_id)
);
const rangeFixture = (manifest.g008_flow_entry_detail_candidate_entries ?? []).find(
  (entry) => Number(entry.id) === Number(manifest.g008_flow_entry_detail_range_entry_id)
);
if (!fixture || !rangeFixture || !manifest.g008_flow_entry_detail_candidate_url || !manifest.g008_flow_entry_detail_range_url) {
  throw new Error('Entry Detail adversarial fixture is incomplete.');
}

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });
const diagnostics = { consoleErrors: [], pageErrors: [], requestFailures: [] };
page.on('console', (message) => {
  if (message.type() === 'error') diagnostics.consoleErrors.push(message.text().slice(0, 1200));
});
page.on('pageerror', (error) => diagnostics.pageErrors.push(String(error?.stack || error).slice(0, 3000)));
page.on('requestfailed', (request) => diagnostics.requestFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));

async function login() {
  const response = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Login page failed: ${response?.status()}`);
  await page.fill('#user_login', 'runtime_admin');
  await page.fill('#user_pass', adminPassword);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);
  await page.locator('#wpadminbar').waitFor({ timeout: 10000 });
}

function withCase(url, candidateCase) {
  const target = new URL(url);
  target.searchParams.set('pgr_g008_candidate_case', candidateCase);
  return target.toString();
}

function withProductionProbe(url) {
  const target = new URL(url);
  target.searchParams.set('pgr_g008_production_probe', '1');
  return target.toString();
}

async function readField(selector) {
  const locator = page.locator(selector);
  if (await locator.count() !== 1) throw new Error(`Expected exactly one ${selector} field.`);
  return (await locator.textContent())?.trim() ?? '';
}

async function capture(url, candidateCase) {
  const response = await page.goto(withCase(url, candidateCase), { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Entry Detail ${candidateCase} request failed: ${response?.status()}`);
  await page.locator('.gravityflow-status-box-field-submitted-time').waitFor({ timeout: 15000 });
  const bodyText = await page.locator('body').innerText();
  if (bodyText.includes('PGRG008ENTRYDETAILMARKER')) {
    throw new Error(`Entry Detail ${candidateCase} leaked the presentation marker.`);
  }
  const fields = {
    submitted: await readField('.gravityflow-status-box-field-submitted-time .gravityflow-status-box-field-value'),
    last_updated: await readField('.gravityflow-status-box-field-last-updated .gravityflow-status-box-field-value'),
    due: await readField('.gravityflow-status-box-field-due-date .gravityflow-status-box-field-value'),
    expiration: await readField('.gravityflow-status-box-field-expires .gravityflow-status-box-field-value'),
  };
  const candidateEvidence = await page.evaluate(() => window.pgrG008EntryDetailCandidateEvidence ?? null);
  if (!candidateEvidence) throw new Error(`Entry Detail ${candidateCase} callback evidence is missing.`);
  return { url: page.url(), fields, candidateEvidence, marker_leaked: false };
}

async function captureProduction(url) {
  const response = await page.goto(withProductionProbe(url), { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Entry Detail production request failed: ${response?.status()}`);
  await page.locator('.gravityflow-status-box-field-submitted-time').waitFor({ timeout: 15000 });
  const bodyText = await page.locator('body').innerText();
  if (bodyText.includes('PGRG008ENTRYDETAILMARKER') || bodyText.includes('PGRJALALIENTRYDETAIL:')) {
    throw new Error('Entry Detail production request leaked a presentation marker.');
  }
  const fields = {
    submitted: await readField('.gravityflow-status-box-field-submitted-time .gravityflow-status-box-field-value'),
    last_updated: await readField('.gravityflow-status-box-field-last-updated .gravityflow-status-box-field-value'),
    due: await readField('.gravityflow-status-box-field-due-date .gravityflow-status-box-field-value'),
    expiration: await readField('.gravityflow-status-box-field-expires .gravityflow-status-box-field-value'),
  };
  const candidateEvidence = await page.evaluate(() => window.pgrG008EntryDetailCandidateEvidence ?? null);
  if (!candidateEvidence || candidateEvidence.case !== 'production') {
    throw new Error('Entry Detail production instrumentation evidence is missing.');
  }
  return { url: page.url(), fields, candidateEvidence, marker_leaked: false };
}

await login();

const exact = await capture(manifest.g008_flow_entry_detail_candidate_url, 'exact');
const production = await captureProduction(manifest.g008_flow_entry_detail_candidate_url);
if (mode === 'enabled') {
  if (exact.candidateEvidence.marker_date_i18n_calls !== 4) {
    throw new Error(`Expected four workflow-info marker date calls, got ${exact.candidateEvidence.marker_date_i18n_calls}.`);
  }
  const observations = exact.candidateEvidence.observations ?? [];
  if (observations.length !== 4 || observations.some((item) => typeof item.jalali !== 'string' || !item.jalali)) {
    throw new Error('Exact-version enabled prototype did not convert all four workflow-info date-family calls.');
  }
  const outputs = observations.map((item) => item.output);
  const visible = Object.values(exact.fields);
  for (let i = 0; i < visible.length; i += 1) {
    if (!visible[i].includes(outputs[i])) {
      throw new Error(`Workflow-info field ${i} did not consume the expected marker conversion output.`);
    }
  }
  if (exact.candidateEvidence.nested_due_getter_calls !== 0 || exact.candidateEvidence.nested_expiration_getter_calls !== 0) {
    throw new Error('Presentation callback re-entered an operational due/expiration getter.');
  }
  if (production.candidateEvidence.marker_date_i18n_calls !== 0) {
    throw new Error('Production request unexpectedly used the isolated prototype marker.');
  }
  if (production.candidateEvidence.production_marker_date_i18n_calls !== 4) {
    throw new Error(`Production adapter did not consume exactly four workflow-info date calls: ${production.candidateEvidence.production_marker_date_i18n_calls}`);
  }
  if (JSON.stringify(production.fields) !== JSON.stringify(exact.fields)) {
    throw new Error(`Production adapter output differs from the qualified prototype: ${JSON.stringify({ production: production.fields, prototype: exact.fields })}`);
  }
  if ((production.candidateEvidence.production_observations ?? []).length !== 4) {
    throw new Error('Production marker observations are incomplete.');
  }
  if (production.candidateEvidence.nested_due_getter_calls !== 0 || production.candidateEvidence.nested_expiration_getter_calls !== 0) {
    throw new Error('Production presentation re-entered an operational due/expiration getter.');
  }
} else {
  const native = [
    fixture.expected_submitted_native,
    fixture.expected_updated_native,
    fixture.expected_due_native,
    fixture.expected_expiration_native,
  ];
  if (JSON.stringify(Object.values(exact.fields)) !== JSON.stringify(native)) {
    throw new Error(`Module-disabled Entry Detail output was not exact native fallback: ${JSON.stringify({ actual: exact.fields, native })}`);
  }
  if (exact.candidateEvidence.marker_date_i18n_calls !== 0) {
    throw new Error('Module-disabled request unexpectedly used the marker path.');
  }
  if (JSON.stringify(Object.values(production.fields)) !== JSON.stringify(native)) {
    throw new Error(`Module-disabled production request was not exact native fallback: ${JSON.stringify({ actual: production.fields, native })}`);
  }
  if (production.candidateEvidence.production_marker_date_i18n_calls !== 0) {
    throw new Error('Module-disabled production request unexpectedly used the production marker path.');
  }
}

let failure = null;
let drift = null;
let range = null;
let repeated = null;
if (mode === 'enabled') {
  failure = await capture(manifest.g008_flow_entry_detail_candidate_url, 'failure');
  if (JSON.stringify(Object.values(failure.fields)) !== JSON.stringify([
    fixture.expected_submitted_native,
    fixture.expected_updated_native,
    fixture.expected_due_native,
    fixture.expected_expiration_native,
  ])) {
    throw new Error('Forced conversion failure did not reproduce exact native host output.');
  }
  if ((failure.candidateEvidence.observations ?? []).some((item) => item.jalali !== null)) {
    throw new Error('Forced conversion failure unexpectedly produced Jalali output.');
  }

  drift = await capture(manifest.g008_flow_entry_detail_candidate_url, 'drift');
  if (JSON.stringify(Object.values(drift.fields)) !== JSON.stringify([
    fixture.expected_submitted_native,
    fixture.expected_updated_native,
    fixture.expected_due_native,
    fixture.expected_expiration_native,
  ])) {
    throw new Error('Forced exact-version drift did not fail closed to native host output.');
  }
  if (drift.candidateEvidence.marker_date_i18n_calls !== 0) {
    throw new Error('Version-drift request unexpectedly reached the marker date_i18n path.');
  }

  range = await capture(manifest.g008_flow_entry_detail_range_url, 'range');
  if (JSON.stringify(Object.values(range.fields)) !== JSON.stringify([
    rangeFixture.expected_submitted_native,
    rangeFixture.expected_updated_native,
    rangeFixture.expected_due_native,
    rangeFixture.expected_expiration_native,
  ])) {
    throw new Error('Out-of-range dates did not fall back to exact native host output.');
  }
  if ((range.candidateEvidence.observations ?? []).some((item) => item.jalali !== null)) {
    throw new Error('Out-of-range fixture unexpectedly produced Jalali output.');
  }

  repeated = await capture(manifest.g008_flow_entry_detail_candidate_url, 'exact');
  if (JSON.stringify(repeated.fields) !== JSON.stringify(exact.fields)) {
    throw new Error('Repeated Entry Detail rendering was not deterministic.');
  }
}

const timelineResponse = await page.goto(withCase(manifest.g008_flow_entry_detail_candidate_url, 'exact'), { waitUntil: 'domcontentloaded' });
if (!timelineResponse?.ok()) throw new Error(`Timeline Entry Detail request failed: ${timelineResponse?.status()}`);
await page.locator('.gravityflow-timeline .gravityflow-note-meta').first().waitFor({ timeout: 15000 });
const timelineHeaders = (await page.locator('.gravityflow-timeline .gravityflow-note-meta').allTextContents()).map((value) => value.trim()).filter(Boolean);
const timelineBodies = (await page.locator('.gravityflow-timeline .gravityflow-note-body').allTextContents()).map((value) => value.trim());
const expectedHeaders = (manifest.g008_flow_timeline_multi_note ?? []).map((item) => item.expected_header);
if (JSON.stringify(timelineHeaders) !== JSON.stringify(expectedHeaders)) {
  throw new Error(`Multi-note Timeline headers drifted: ${JSON.stringify({ timelineHeaders, expectedHeaders })}`);
}
for (const stored of manifest.g008_flow_timeline_stored_notes ?? []) {
  if (!timelineBodies.includes(stored.value)) {
    throw new Error(`Stored Timeline body changed or disappeared for note ${stored.id}.`);
  }
}

const printResponse = await page.goto(manifest.g008_flow_entry_detail_candidate_print_url, { waitUntil: 'domcontentloaded' });
if (!printResponse?.ok()) throw new Error(`Print request failed: ${printResponse?.status()}`);
await page.locator('#view-container .gravityflow-note-meta').first().waitFor({ timeout: 15000 });
const printHeaders = (await page.locator('#view-container .gravityflow-note-meta').allTextContents()).map((value) => value.trim()).filter(Boolean);
const printBodies = (await page.locator('#view-container .gravityflow-note-body').allTextContents()).map((value) => value.trim());
if (JSON.stringify(printHeaders) !== JSON.stringify(expectedHeaders)) {
  throw new Error('Print Timeline did not reuse the same native multi-note headers.');
}
for (const stored of manifest.g008_flow_timeline_stored_notes ?? []) {
  if (!printBodies.includes(stored.value)) throw new Error(`Print changed or omitted stored note body ${stored.id}.`);
}
const printSidebarPresence = {
  submitted: await page.locator('#view-container .gravityflow-status-box-field-submitted-time').count(),
  last_updated: await page.locator('#view-container .gravityflow-status-box-field-last-updated').count(),
  due: await page.locator('#view-container .gravityflow-status-box-field-due-date').count(),
  schedule: await page.locator('#view-container .gravityflow-status-box-field-scheduled-date').count(),
  expiration: await page.locator('#view-container .gravityflow-status-box-field-expires').count(),
};
if (Object.values(printSidebarPresence).some((count) => count !== 0)) {
  throw new Error(`Print unexpectedly rendered workflow-sidebar dates: ${JSON.stringify(printSidebarPresence)}`);
}

if (diagnostics.pageErrors.length || diagnostics.requestFailures.length) {
  throw new Error(`Entry Detail adversarial browser diagnostics failed: ${JSON.stringify(diagnostics)}`);
}

const evidence = {
  schema_version: '1.0.0',
  evidence_class: 'AUTHENTIC_G008_ENTRY_DETAIL_TWO_HOOK_QUALIFICATION_BROWSER',
  mode,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  exact_persiangravity_package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
  exact_gravityflow_version: process.env.WU008_FLOW_VERSION || null,
  exact_gravityflow_package_sha256: process.env.WU008_FLOW_SHA256 || null,
  site_timezone: manifest.g008_flow_site_timezone || null,
  php_default_timezone: manifest.g008_flow_php_default_timezone || null,
  exact,
  production,
  failure,
  drift,
  range,
  repeated,
  timeline: {
    headers: timelineHeaders,
    bodies: timelineBodies,
  },
  print: {
    headers: printHeaders,
    bodies: printBodies,
    workflow_sidebar_presence: printSidebarPresence,
  },
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g008-entry-detail-candidate-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
await browser.close();
console.log(`G008_ENTRY_DETAIL_TWO_HOOK_BROWSER_${mode.toUpperCase()} PASS`);
