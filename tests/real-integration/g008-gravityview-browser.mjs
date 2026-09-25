import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const manifestPath = process.env.WU008_MANIFEST_PATH;
const artifactDir = process.env.WU008_ARTIFACT_DIR;
const mode = process.env.WU008_G008_GV_MODE;
if (!manifestPath || !artifactDir || !['enabled', 'disabled', 'version-drift', 'english'].includes(mode || '')) {
  throw new Error('GravityView browser qualification requires manifest/artifact paths and a valid mode.');
}
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const fixtures = manifest.g008_gravityview_entries;
if (!Array.isArray(fixtures) || fixtures.length !== 3) throw new Error('GravityView fixture manifest is invalid.');

const byId = new Map(fixtures.map((fixture) => [Number(fixture.id), fixture]));
const expectedCreatedAsc = [...fixtures].sort((a, b) => a.date_created.localeCompare(b.date_created) || a.id - b.id).map((x) => Number(x.id));
const expectedCreatedDesc = [...expectedCreatedAsc].reverse();
const expectedUpdatedAsc = [...fixtures].sort((a, b) => a.date_updated.localeCompare(b.date_updated) || a.id - b.id).map((x) => Number(x.id));
const expectedUpdatedDesc = [...expectedUpdatedAsc].reverse();

function urlWith(params = {}) {
  const url = new URL(manifest.g008_gravityview_page_url);
  for (const [key, value] of Object.entries(params)) url.searchParams.set(key, value);
  return url.toString();
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function assertEqual(actual, expected, message) {
  if (JSON.stringify(actual) !== JSON.stringify(expected)) {
    throw new Error(`${message}\nexpected=${JSON.stringify(expected)}\nactual=${JSON.stringify(actual)}`);
  }
}

async function capture(page, name, params = {}) {
  const pageErrors = [];
  const consoleErrors = [];
  const onPageError = (error) => pageErrors.push(String(error?.stack || error));
  const onConsole = (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  };
  page.on('pageerror', onPageError);
  page.on('console', onConsole);
  await page.goto(urlWith(params), { waitUntil: 'networkidle' });

  const table = page.locator('table').filter({ hasText: 'Qualification Marker' }).first();
  await table.waitFor({ state: 'visible' });

  const surface = await table.evaluate((node) => {
    const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const headers = [...node.querySelectorAll('thead th')].map((cell) => normalize(cell.textContent));
    const rows = [...node.querySelectorAll('tbody tr')].map((row) => {
      const cells = [...row.querySelectorAll('th,td')].map((cell) => ({
        text: normalize(cell.textContent),
        id: cell.getAttribute('id') || '',
        class: cell.getAttribute('class') || '',
        dataLabel: cell.getAttribute('data-label') || '',
        colspan: cell.getAttribute('colspan') || '',
      }));
      return {
        class: row.getAttribute('class') || '',
        dataRow: row.getAttribute('data-row') || '',
        cells,
      };
    });
    const links = [...node.querySelectorAll('a')].map((link) => ({
      text: normalize(link.textContent),
      href: link.getAttribute('href') || '',
      target: link.getAttribute('target') || '',
      rel: link.getAttribute('rel') || '',
      class: link.getAttribute('class') || '',
    }));
    const controls = [...node.querySelectorAll('input,select,button')].map((control) => ({
      tag: control.tagName.toLowerCase(),
      type: control.getAttribute('type') || '',
      name: control.getAttribute('name') || '',
      value: control.getAttribute('value') || '',
      class: control.getAttribute('class') || '',
    }));
    return { headers, rows, links, controls };
  });

  const html = await page.locator('html').evaluate((el) => ({ lang: el.lang || '', dir: el.dir || '' }));
  const bodyText = await page.locator('body').innerText();
  await page.screenshot({ path: path.join(artifactDir, `g008-gravityview-${mode}-${name}.png`), fullPage: true });
  page.off('pageerror', onPageError);
  page.off('console', onConsole);
  return {
    name,
    url: page.url(),
    html,
    surface,
    body_sha256: await page.locator('body').evaluate(async (el) => {
      const bytes = new TextEncoder().encode(el.innerText);
      const digest = await crypto.subtle.digest('SHA-256', bytes);
      return [...new Uint8Array(digest)].map((b) => b.toString(16).padStart(2, '0')).join('');
    }),
    body_contains_php_error: /Fatal error|Warning:|Notice:|Deprecated:/i.test(bodyText),
    page_errors: pageErrors,
    console_errors: consoleErrors,
  };
}

function rowIds(snapshot) {
  return snapshot.surface.rows.map((row) => Number(row.cells[0]?.text)).filter(Number.isInteger);
}

function verifyPresentation(snapshot, expectedKind) {
  assertEqual(snapshot.surface.headers.length, 4, `${snapshot.name}: unexpected column count`);
  assertEqual(snapshot.surface.rows.length, 3, `${snapshot.name}: unexpected entry row count`);
  for (const row of snapshot.surface.rows) {
    const entryId = Number(row.cells[0]?.text);
    const fixture = byId.get(entryId);
    assert(fixture, `${snapshot.name}: unknown entry id ${entryId}`);
    assertEqual(row.cells[1]?.text, fixture.marker, `${snapshot.name}: user-authored marker changed for ${entryId}`);
    const createdExpected = expectedKind === 'jalali' ? fixture.expected_date_created_jalali : fixture.expected_date_created_native;
    const updatedExpected = expectedKind === 'jalali' ? fixture.expected_date_updated_jalali : fixture.expected_date_updated_native;
    assertEqual(row.cells[2]?.text, createdExpected, `${snapshot.name}: date_created presentation mismatch for ${entryId}`);
    assertEqual(row.cells[3]?.text, updatedExpected, `${snapshot.name}: date_updated presentation mismatch for ${entryId}`);
  }
  assertEqual(snapshot.page_errors, [], `${snapshot.name}: browser page error`);
  assert(!snapshot.body_contains_php_error, `${snapshot.name}: PHP error text reached browser`);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();

try {
  const expectedKind = mode === 'enabled' ? 'jalali' : 'native';
  const base = await capture(page, 'base');
  verifyPresentation(base, expectedKind);

  if (mode === 'english') {
    assert(/^en(?:-|$)/i.test(base.html.lang), `English control did not expose an en language: ${base.html.lang}`);
    assert(base.html.dir !== 'rtl', `English control unexpectedly rendered RTL.`);
  } else {
    assert(/^fa(?:-|$)/i.test(base.html.lang), `${mode}: Persian control did not expose a fa language: ${base.html.lang}`);
    assert(base.html.dir === 'rtl', `${mode}: Persian control did not render RTL.`);
  }

  const repeated = await capture(page, 'repeat');
  verifyPresentation(repeated, expectedKind);
  assertEqual(repeated.surface, base.surface, `${mode}: repeated rendering changed the GravityView table surface`);

  const createdAsc = await capture(page, 'sort-created-asc', { 'sort[date_created]': 'asc' });
  const createdDesc = await capture(page, 'sort-created-desc', { 'sort[date_created]': 'desc' });
  const updatedAsc = await capture(page, 'sort-updated-asc', { 'sort[date_updated]': 'asc' });
  const updatedDesc = await capture(page, 'sort-updated-desc', { 'sort[date_updated]': 'desc' });
  assertEqual(rowIds(createdAsc), expectedCreatedAsc, `${mode}: date_created ASC did not follow raw native ordering`);
  assertEqual(rowIds(createdDesc), expectedCreatedDesc, `${mode}: date_created DESC did not follow raw native ordering`);
  assertEqual(rowIds(updatedAsc), expectedUpdatedAsc, `${mode}: date_updated ASC did not follow raw native ordering`);
  assertEqual(rowIds(updatedDesc), expectedUpdatedDesc, `${mode}: date_updated DESC did not follow raw native ordering`);

  const createdFilter = await capture(page, 'filter-created', { filter_date_created: '2026-03-19', 'filter_date_created|op': 'is' });
  const updatedFilter = await capture(page, 'filter-updated', { filter_date_updated: '2026-03-22', 'filter_date_updated|op': 'is' });
  const expectedCreatedFilter = fixtures.filter((x) => x.date_created.startsWith('2026-03-19')).map((x) => Number(x.id));
  const expectedUpdatedFilter = fixtures.filter((x) => x.date_updated.startsWith('2026-03-22')).map((x) => Number(x.id));
  assertEqual(rowIds(createdFilter), expectedCreatedFilter, `${mode}: date_created search/filter identities changed`);
  assertEqual(rowIds(updatedFilter), expectedUpdatedFilter, `${mode}: date_updated search/filter identities changed`);

  const evidence = {
    schema_version: '1.0.0',
    evidence_class: 'AUTHENTIC_GRAVITYVIEW_BROWSER_QUALIFICATION',
    mode,
    expected_presentation: expectedKind,
    gravityview_version: '3.3.4',
    view_id: Number(manifest.g008_gravityview_view_id),
    form_id: Number(manifest.g008_gravityview_form_id),
    base,
    repeated,
    queries: {
      created_asc: { ids: rowIds(createdAsc), expected: expectedCreatedAsc },
      created_desc: { ids: rowIds(createdDesc), expected: expectedCreatedDesc },
      updated_asc: { ids: rowIds(updatedAsc), expected: expectedUpdatedAsc },
      updated_desc: { ids: rowIds(updatedDesc), expected: expectedUpdatedDesc },
      created_filter: { ids: rowIds(createdFilter), expected: expectedCreatedFilter },
      updated_filter: { ids: rowIds(updatedFilter), expected: expectedUpdatedFilter },
    },
  };
  fs.writeFileSync(path.join(artifactDir, `g008-gravityview-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
  process.stdout.write(`${JSON.stringify({ mode, row_ids: rowIds(base), html: base.html, queries: evidence.queries })}\n`);
} finally {
  await browser.close();
}
