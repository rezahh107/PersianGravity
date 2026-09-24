import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const mode = process.env.WU008_G008_MODE;
if (!artifactDir || !manifestPath || !adminPassword || !['enabled', 'disabled', 'english'].includes(mode)) {
  throw new Error('Entry Detail candidate browser requires artifact/manifest/admin password and enabled|disabled|english mode.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const fixture = (manifest.g008_flow_entry_detail_candidate_entries ?? []).find(
  (entry) => Number(entry.id) === Number(manifest.g008_flow_entry_detail_candidate_entry_id)
);
const rangeFixture = (manifest.g008_flow_entry_detail_candidate_entries ?? []).find(
  (entry) => Number(entry.id) === Number(manifest.g008_flow_entry_detail_range_entry_id)
);
const timelineFixture = manifest.g008_flow_timeline_multi_note ?? [];
if (!fixture || !rangeFixture || !manifest.g008_flow_entry_detail_candidate_url || !manifest.g008_flow_entry_detail_range_url || timelineFixture.length < 5) {
  throw new Error('Entry Detail adversarial/Timeline production fixture is incomplete.');
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
  target.searchParams.delete('pgr_g008_candidate_case');
  target.searchParams.set('pgr_g008_production_probe', '1');
  return target.toString();
}

const STATUS_ROOT = '#gravityflow-status-box-container > #submitcomment > #minor-publishing.gravityflow-status-box';

function asciiDigits(value) {
  const persian = '۰۱۲۳۴۵۶۷۸۹';
  return value.replace(/[۰-۹]/g, (digit) => String(persian.indexOf(digit)));
}

async function readField(selector) {
  const locator = page.locator(selector);
  if (await locator.count() !== 1) throw new Error(`Expected exactly one ${selector} field.`);
  return (await locator.textContent())?.trim() ?? '';
}

async function readMachineSnapshot() {
  const root = page.locator(STATUS_ROOT);
  if (await root.count() !== 1) throw new Error('Expected exactly one qualified Gravity Flow status-box container.');
  return root.evaluate((element) => {
    const nodes = [element, ...element.querySelectorAll('*')];
    const elementAttributes = nodes.map((node, index) => ({
      index,
      tag: node.tagName.toLowerCase(),
      attributes: Object.fromEntries([...node.attributes].map((attribute) => [attribute.name, attribute.value])),
    }));
    const controls = [...element.querySelectorAll('input, select, textarea, button')].map((control) => ({
      tag: control.tagName.toLowerCase(),
      type: control.getAttribute('type'),
      name: control.getAttribute('name'),
      value_attribute: control.getAttribute('value'),
      value_property: control.value ?? null,
      checked: 'checked' in control ? Boolean(control.checked) : null,
      selected_index: 'selectedIndex' in control ? control.selectedIndex : null,
    }));
    const links = [...element.querySelectorAll('a')].map((link) => ({
      href_attribute: link.getAttribute('href'),
      href_property: link.href,
    }));
    return { element_attributes: elementAttributes, controls, links };
  });
}

async function readPresentationSnapshot() {
  const fields = {
    entry_id: await readField('.gravityflow-status-box-field-entry-id .gravityflow-status-box-field-value'),
    submitted: await readField('.gravityflow-status-box-field-submitted-time .gravityflow-status-box-field-value'),
    last_updated: await readField('.gravityflow-status-box-field-last-updated .gravityflow-status-box-field-value'),
    due: await readField('.gravityflow-status-box-field-due-date .gravityflow-status-box-field-value'),
    expiration: await readField('.gravityflow-status-box-field-expires .gravityflow-status-box-field-value'),
  };
  const visibleStatusFields = (await page.locator(`${STATUS_ROOT} .gravityflow-status-box-field`).allTextContents())
    .map((value) => value.trim())
    .filter(Boolean);
  return {
    fields,
    visible_status_fields: visibleStatusFields,
    machine: await readMachineSnapshot(),
    digit_script_count: await page.locator('script[src*="pgr-flow-entry-detail-persian-digits.js"]').count(),
    direction: await page.locator(STATUS_ROOT).evaluate((element) => ({
      computed: getComputedStyle(element).direction,
      root_dir_attribute: element.getAttribute('dir'),
      html_dir_attribute: document.documentElement.getAttribute('dir'),
    })),
  };
}

async function capture(url, candidateCase) {
  const response = await page.goto(withCase(url, candidateCase), { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Entry Detail ${candidateCase} request failed: ${response?.status()}`);
  await page.locator('.gravityflow-status-box-field-submitted-time').waitFor({ timeout: 15000 });
  const bodyText = await page.locator('body').innerText();
  if (bodyText.includes('PGRG008ENTRYDETAILMARKER') || bodyText.includes('PGRTIMELINE')) {
    throw new Error(`Entry Detail ${candidateCase} leaked a presentation marker.`);
  }
  const presentation = await readPresentationSnapshot();
  const candidateEvidence = await page.evaluate(() => window.pgrG008EntryDetailCandidateEvidence ?? null);
  if (!candidateEvidence) throw new Error(`Entry Detail ${candidateCase} callback evidence is missing.`);
  return { url: page.url(), ...presentation, candidateEvidence, marker_leaked: false };
}

async function captureProduction(url) {
  const response = await page.goto(withProductionProbe(url), { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw new Error(`Entry Detail production request failed: ${response?.status()}`);
  await page.locator('.gravityflow-status-box-field-submitted-time').waitFor({ timeout: 15000 });
  const bodyText = await page.locator('body').innerText();
  if (bodyText.includes('PGRG008ENTRYDETAILMARKER') || bodyText.includes('PGRJALALIENTRYDETAIL:') || bodyText.includes('PGRTIMELINE')) {
    throw new Error('Entry Detail production request leaked a presentation marker.');
  }
  const presentation = await readPresentationSnapshot();
  const candidateEvidence = await page.evaluate(() => window.pgrG008EntryDetailCandidateEvidence ?? null);
  if (!candidateEvidence || candidateEvidence.case !== 'production') {
    throw new Error('Entry Detail production instrumentation evidence is missing.');
  }
  return { url: page.url(), ...presentation, candidateEvidence, marker_leaked: false };
}

async function readTimeline(selectorPrefix) {
  const scope = page.locator(selectorPrefix);
  await scope.locator('.gravityflow-note-meta').first().waitFor({ timeout: 15000 });
  return scope.evaluate((element) => {
    const bodyCandidates = [...element.querySelectorAll('.gravityflow-note-body')];
    const rowCandidates = bodyCandidates.filter((candidate) => {
      const headers = [...candidate.querySelectorAll('.gravityflow-note-meta')];
      const bodyLeaves = [...candidate.querySelectorAll('.gravityflow-note-body')]
        .filter((body) => !body.querySelector('.gravityflow-note-body'));
      return headers.length === 1 && bodyLeaves.length === 1;
    });
    const rows = rowCandidates.map((row, index) => {
      const headers = [...row.querySelectorAll('.gravityflow-note-meta')];
      const bodyLeaves = [...row.querySelectorAll('.gravityflow-note-body')]
        .filter((body) => !body.querySelector('.gravityflow-note-body'));
      if (headers.length !== 1 || bodyLeaves.length !== 1) {
        throw new Error(`Timeline row ${index} is ambiguous: ${headers.length} headers, ${bodyLeaves.length} body leaves.`);
      }
      return {
        index,
        header: headers[0].textContent?.trim() ?? '',
        body: bodyLeaves[0].textContent?.trim() ?? '',
      };
    });
    return {
      headers: rows.map((row) => row.header),
      bodies: rows.map((row) => row.body),
      rows,
      collector: {
        row_count: rows.length,
        body_candidate_count: bodyCandidates.length,
      },
    };
  });
}

function assertTimelinePresentation(snapshot, label) {
  if (snapshot.headers.length !== timelineFixture.length || snapshot.bodies.length !== timelineFixture.length || snapshot.collector?.row_count !== timelineFixture.length) {
    throw new Error(`${label} row/header/body count drifted: ${JSON.stringify({ headers: snapshot.headers.length, bodies: snapshot.bodies.length, rows: snapshot.collector?.row_count, expected: timelineFixture.length })}`);
  }
  const expectedBodies = timelineFixture.map((item) => item.value);
  if (JSON.stringify(snapshot.bodies) !== JSON.stringify(expectedBodies)) {
    throw new Error(`${label} bodies do not map one-to-one to the authoritative fixture: ${JSON.stringify({ actual: snapshot.bodies, expected: expectedBodies })}`);
  }
  for (let index = 0; index < timelineFixture.length; index += 1) {
    if (snapshot.bodies[index].includes(snapshot.headers[index])) {
      throw new Error(`${label} body ${index} contains its Timeline header; collector captured an enclosing wrapper.`);
    }
  }
  const expectedNative = timelineFixture.map((item) => item.expected_header);
  if (mode === 'disabled') {
    if (JSON.stringify(snapshot.headers) !== JSON.stringify(expectedNative)) {
      throw new Error(`${label} disabled headers are not exact native host output: ${JSON.stringify({ actual: snapshot.headers, expectedNative })}`);
    }
  } else if (mode === 'enabled') {
    for (let index = 0; index < timelineFixture.length; index += 1) {
      const row = timelineFixture[index];
      const header = snapshot.headers[index] ?? '';
      if (!header.startsWith(row.expected_jalali_date)) {
        throw new Error(`${label} row ${row.id} did not start with the independently expected Jalali date: ${header}`);
      }
      if (row.expected_native_time_tail && !header.includes(row.expected_native_time_tail)) {
        throw new Error(`${label} row ${row.id} changed native time output: ${JSON.stringify({ header, expected: row.expected_native_time_tail })}`);
      }
    }
  }
  for (const stored of manifest.g008_flow_timeline_stored_notes ?? []) {
    if (!snapshot.bodies.includes(stored.value)) {
      throw new Error(`${label} changed or omitted stored Timeline body ${stored.id}.`);
    }
  }
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
  const visibleDates = [exact.fields.submitted, exact.fields.last_updated, exact.fields.due, exact.fields.expiration];
  for (let i = 0; i < visibleDates.length; i += 1) {
    if (!visibleDates[i].includes(outputs[i])) {
      throw new Error(`Workflow-info date field ${i} did not consume the expected marker conversion output.`);
    }
  }
  if (exact.digit_script_count !== 1 || production.digit_script_count !== 1) {
    throw new Error('Persian workflow-info digit adapter was not loaded exactly once.');
  }
  if (exact.visible_status_fields.some((value) => /[0-9]/.test(value))) {
    throw new Error(`Persian workflow-info still contains ASCII digits: ${JSON.stringify(exact.visible_status_fields)}`);
  }
  if (production.fields.submitted.includes('11:59') || !production.fields.submitted.includes('۱۱:۵۹')) {
    throw new Error(`Submitted time digit regression: ${production.fields.submitted}`);
  }
  if (production.fields.last_updated.includes('12:01') || !production.fields.last_updated.includes('۱۲:۰۱')) {
    throw new Error(`Last Updated time digit regression: ${production.fields.last_updated}`);
  }
  if (/[0-9]/.test(production.fields.entry_id) || !/[۰-۹]/.test(production.fields.entry_id)) {
    throw new Error(`Visible Entry ID was not Persian-shaped: ${production.fields.entry_id}`);
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
    throw new Error(`Production adapter output differs from the qualified workflow-info prototype: ${JSON.stringify({ production: production.fields, prototype: exact.fields })}`);
  }
  if ((production.candidateEvidence.production_observations ?? []).length !== 4) {
    throw new Error('Production workflow-info marker observations are incomplete.');
  }
  if (production.candidateEvidence.nested_due_getter_calls !== 0 || production.candidateEvidence.nested_expiration_getter_calls !== 0) {
    throw new Error('Production workflow-info presentation re-entered an operational due/expiration getter.');
  }
} else if (mode === 'disabled') {
  const native = [
    fixture.expected_submitted_native,
    fixture.expected_updated_native,
    fixture.expected_due_native,
    fixture.expected_expiration_native,
  ];
  const exactDates = [exact.fields.submitted, exact.fields.last_updated, exact.fields.due, exact.fields.expiration];
  const productionDates = [production.fields.submitted, production.fields.last_updated, production.fields.due, production.fields.expiration];
  if (JSON.stringify(exactDates) !== JSON.stringify(native)) {
    throw new Error(`Module-disabled Entry Detail output was not exact native fallback: ${JSON.stringify({ actual: exact.fields, native })}`);
  }
  if (exact.candidateEvidence.marker_date_i18n_calls !== 0) {
    throw new Error('Module-disabled request unexpectedly used the workflow-info marker path.');
  }
  if (JSON.stringify(productionDates) !== JSON.stringify(native)) {
    throw new Error(`Module-disabled production request was not exact native fallback: ${JSON.stringify({ actual: production.fields, native })}`);
  }
  if (production.candidateEvidence.production_marker_date_i18n_calls !== 0) {
    throw new Error('Module-disabled production request unexpectedly used the workflow-info production marker path.');
  }
  if (exact.digit_script_count !== 0 || production.digit_script_count !== 0) {
    throw new Error('Module-disabled request loaded the Persian digit adapter.');
  }
  if (!/[0-9]/.test(production.fields.entry_id) || /[۰-۹]/.test(production.fields.entry_id)) {
    throw new Error(`Module-disabled visible Entry ID was not native ASCII: ${production.fields.entry_id}`);
  }
} else {
  if (exact.candidateEvidence.marker_date_i18n_calls !== 4 || production.candidateEvidence.production_marker_date_i18n_calls !== 4) {
    throw new Error('English control did not keep the independently enabled workflow-info G008 date adapter active.');
  }
  if (exact.digit_script_count !== 0 || production.digit_script_count !== 0) {
    throw new Error('English/non-Persian control loaded the Persian digit adapter.');
  }
  if (!production.fields.submitted.includes('11:59') || production.fields.submitted.includes('۱۱:۵۹')) {
    throw new Error(`English Submitted time did not remain ASCII: ${production.fields.submitted}`);
  }
  if (!production.fields.last_updated.includes('12:01') || production.fields.last_updated.includes('۱۲:۰۱')) {
    throw new Error(`English Last Updated time did not remain ASCII: ${production.fields.last_updated}`);
  }
  if (!/[0-9]/.test(production.fields.entry_id) || /[۰-۹]/.test(production.fields.entry_id)) {
    throw new Error(`English visible Entry ID did not remain ASCII: ${production.fields.entry_id}`);
  }
}

let failure = null;
let drift = null;
let range = null;
let repeated = null;
if (mode === 'enabled') {
  failure = await capture(manifest.g008_flow_entry_detail_candidate_url, 'failure');
  if (JSON.stringify([
    asciiDigits(failure.fields.submitted),
    asciiDigits(failure.fields.last_updated),
    asciiDigits(failure.fields.due),
    asciiDigits(failure.fields.expiration),
  ]) !== JSON.stringify([
    fixture.expected_submitted_native,
    fixture.expected_updated_native,
    fixture.expected_due_native,
    fixture.expected_expiration_native,
  ])) {
    throw new Error('Forced workflow-info conversion failure did not preserve native host semantics under presentation-only digit shaping.');
  }
  if ((failure.candidateEvidence.observations ?? []).some((item) => item.jalali !== null)) {
    throw new Error('Forced workflow-info conversion failure unexpectedly produced Jalali output.');
  }

  drift = await capture(manifest.g008_flow_entry_detail_candidate_url, 'drift');
  if (JSON.stringify([
    asciiDigits(drift.fields.submitted),
    asciiDigits(drift.fields.last_updated),
    asciiDigits(drift.fields.due),
    asciiDigits(drift.fields.expiration),
  ]) !== JSON.stringify([
    fixture.expected_submitted_native,
    fixture.expected_updated_native,
    fixture.expected_due_native,
    fixture.expected_expiration_native,
  ])) {
    throw new Error('Forced workflow-info date-adapter drift did not preserve native host semantics under presentation-only digit shaping.');
  }
  if (drift.candidateEvidence.marker_date_i18n_calls !== 0) {
    throw new Error('Workflow-info version-drift request unexpectedly reached the marker date_i18n path.');
  }

  range = await capture(manifest.g008_flow_entry_detail_range_url, 'range');
  if (JSON.stringify([
    asciiDigits(range.fields.submitted),
    asciiDigits(range.fields.last_updated),
    asciiDigits(range.fields.due),
    asciiDigits(range.fields.expiration),
  ]) !== JSON.stringify([
    rangeFixture.expected_submitted_native,
    rangeFixture.expected_updated_native,
    rangeFixture.expected_due_native,
    rangeFixture.expected_expiration_native,
  ])) {
    throw new Error('Out-of-range workflow-info dates did not preserve native host semantics under presentation-only digit shaping.');
  }
  if ((range.candidateEvidence.observations ?? []).some((item) => item.jalali !== null)) {
    throw new Error('Out-of-range workflow-info fixture unexpectedly produced Jalali output.');
  }

  repeated = await capture(manifest.g008_flow_entry_detail_candidate_url, 'exact');
  if (JSON.stringify(repeated.fields) !== JSON.stringify(exact.fields)) {
    throw new Error('Repeated Entry Detail workflow-info rendering was not deterministic.');
  }
}

const timelineUrl = withCase(manifest.g008_flow_entry_detail_candidate_url, 'exact');
const timelineResponse = await page.goto(timelineUrl, { waitUntil: 'domcontentloaded' });
if (!timelineResponse?.ok()) throw new Error(`Timeline Entry Detail request failed: ${timelineResponse?.status()}`);
const timeline = await readTimeline('.gravityflow-timeline');
const timelineBodyText = await page.locator('body').innerText();
if (timelineBodyText.includes('PGRTIMELINE')) throw new Error('Timeline leaked an owned production marker.');
assertTimelinePresentation(timeline, 'Entry Detail Timeline');

const duplicateIds = (manifest.g008_flow_timeline_duplicate_ids ?? []).map(Number);
const duplicateRaw = manifest.g008_flow_timeline_duplicate_raw;
const duplicateRows = timelineFixture.filter((item) => item.date_created === duplicateRaw);
if (duplicateIds.length < 2 || new Set(duplicateIds).size !== duplicateIds.length || duplicateRows.length < 2) {
  throw new Error('Duplicate-timestamp Timeline fixture did not preserve distinct note identity.');
}

let repeatedTimeline = null;
if (mode !== 'english') {
  const repeatedResponse = await page.goto(timelineUrl, { waitUntil: 'domcontentloaded' });
  if (!repeatedResponse?.ok()) throw new Error(`Repeated Timeline request failed: ${repeatedResponse?.status()}`);
  repeatedTimeline = await readTimeline('.gravityflow-timeline');
  if (JSON.stringify(repeatedTimeline) !== JSON.stringify(timeline)) {
    throw new Error('Repeated Timeline rendering was not deterministic.');
  }
}

const printResponse = await page.goto(manifest.g008_flow_entry_detail_candidate_print_url, { waitUntil: 'domcontentloaded' });
if (!printResponse?.ok()) throw new Error(`Print request failed: ${printResponse?.status()}`);
const print = await readTimeline('#view-container');
const printBodyText = await page.locator('body').innerText();
if (printBodyText.includes('PGRTIMELINE')) throw new Error('Print leaked an owned Timeline production marker.');
assertTimelinePresentation(print, 'Print Timeline');
if (JSON.stringify(print.headers) !== JSON.stringify(timeline.headers)) {
  throw new Error('Print did not inherit the exact verified Timeline header presentation.');
}
if (JSON.stringify(print.bodies) !== JSON.stringify(timeline.bodies)) {
  throw new Error('Print did not preserve the same Timeline body/order presentation.');
}

const printDigitScriptCount = await page.locator('script[src*="pgr-flow-entry-detail-persian-digits.js"]').count();
if (printDigitScriptCount !== 0) {
  throw new Error('Print unexpectedly loaded the workflow-info Persian digit adapter.');
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
  schema_version: '2.0.0',
  evidence_class: 'AUTHENTIC_G008_ENTRY_DETAIL_AND_TIMELINE_PRODUCTION_BROWSER',
  mode,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  exact_persiangravity_package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
  exact_gravityflow_version: process.env.WU008_FLOW_VERSION || null,
  exact_gravityflow_package_sha256: process.env.WU008_FLOW_SHA256 || null,
  exact_gravityforms_version: process.env.WU008_GF_VERSION || null,
  exact_gravityforms_package_sha256: process.env.WU008_GF_SHA256 || null,
  site_timezone: manifest.g008_flow_site_timezone || null,
  php_default_timezone: manifest.g008_flow_php_default_timezone || null,
  exact,
  production,
  failure,
  drift,
  range,
  repeated,
  timeline: {
    ...timeline,
    repeated: repeatedTimeline,
    fixture: timelineFixture,
    duplicate_timestamp: duplicateRaw,
    duplicate_ids: duplicateIds,
    marker_leaked: false,
  },
  print: {
    ...print,
    workflow_sidebar_presence: printSidebarPresence,
    digit_script_count: printDigitScriptCount,
    marker_leaked: false,
    relation: 'PRINT_INHERITS_VERIFIED_TIMELINE_PRESENTATION',
  },
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g008-entry-detail-candidate-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
await browser.close();
console.log(`G008_ENTRY_DETAIL_TIMELINE_BROWSER_${mode.toUpperCase()} PASS`);
