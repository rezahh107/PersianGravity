import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const mode = process.env.WU008_GV_MODE;
const expectedHead = process.env.WU008_PGR_SHA;
if (!artifactDir || !manifestPath || !['enabled', 'disabled', 'english', 'drift'].includes(mode) || !expectedHead) {
  throw new Error('GravityView production browser environment is incomplete.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const fixture = manifest.g008_gravityview;
if (!fixture?.page_url || !Array.isArray(fixture.entries) || fixture.entries.length !== 3) {
  throw new Error('GravityView browser fixture manifest is incomplete.');
}

const expectedSort = (field, direction) => [...fixture.entries]
  .sort((a, b) => {
    const cmp = String(a[field]).localeCompare(String(b[field]));
    return direction === 'asc' ? cmp : -cmp;
  })
  .map((entry) => entry.token);

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const diagnostics = { console: [], pageErrors: [], requestFailures: [] };
page.on('console', (msg) => diagnostics.console.push({ type: msg.type(), text: msg.text().slice(0, 1200) }));
page.on('pageerror', (error) => diagnostics.pageErrors.push(String(error?.stack || error).slice(0, 3000)));
page.on('requestfailed', (request) => diagnostics.requestFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));

async function open(url, expectedTokens = fixture.entries.map((entry) => entry.token)) {
  const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
  if (!response || !response.ok()) throw new Error(`GravityView response failed: ${response?.status()} ${url}`);
  await page.waitForFunction(
    (tokens) => tokens.every((token) => document.body?.innerText.includes(token)),
    expectedTokens,
    { timeout: 20000 },
  );
  if (/There has been a critical error|Fatal error/i.test(await page.locator('body').innerText())) {
    throw new Error('GravityView production surface contains a fatal/critical error.');
  }
}

async function snapshot() {
  return page.evaluate((tokens) => {
    const rows = [...document.querySelectorAll('tr')].filter((row) => tokens.some((token) => (row.textContent || '').includes(token)));
    const mapped = rows.map((row) => {
      const token = tokens.find((candidate) => (row.textContent || '').includes(candidate)) || '';
      const diagnosticNodes = row.querySelectorAll('.wu008-gv-system-date, [data-raw], [data-native]').length;
      return {
        token,
        text: (row.textContent || '').replace(/\s+/g, ' ').trim(),
        cells: [...row.querySelectorAll('td')].map((cell) => (cell.textContent || '').replace(/\s+/g, ' ').trim()),
        diagnosticNodes,
        attributes: [...row.attributes].map((attr) => [attr.name, attr.value]).sort(),
      };
    });
    const sortLinks = [...document.querySelectorAll('a.gv-sort')].map((a) => ({
      href: a.getAttribute('href'),
      ariaLabel: a.getAttribute('aria-label'),
      className: a.className,
    }));
    const searchControls = [...document.querySelectorAll('input, select, button')].map((node) => ({
      tag: node.tagName.toLowerCase(),
      type: node.getAttribute('type'),
      name: node.getAttribute('name'),
      id: node.getAttribute('id'),
      value: 'value' in node ? node.value : null,
      dataField: node.getAttribute('data-field'),
      dataFieldId: node.getAttribute('data-field-id'),
      formAction: node.form?.getAttribute('action') ?? null,
      formMethod: node.form?.getAttribute('method') ?? null,
    })).filter((control) =>
      [control.name, control.id, control.dataField, control.dataFieldId]
        .some((value) => typeof value === 'string' && /date_created|date_updated|filter_|gv_start|gv_end|gv_search_view/i.test(value))
    );
    const forms = [...document.querySelectorAll('form')].map((form) => ({
      action: form.getAttribute('action'),
      method: form.getAttribute('method'),
      className: form.className,
      id: form.id,
      controls: [...form.querySelectorAll('input, select, button')].map((node) => ({
        tag: node.tagName.toLowerCase(),
        type: node.getAttribute('type'),
        name: node.getAttribute('name'),
        id: node.getAttribute('id'),
        value: 'value' in node ? node.value : null,
        dataField: node.getAttribute('data-field'),
        dataFieldId: node.getAttribute('data-field-id'),
      })),
    })).filter((form) => form.controls.some((control) =>
      [control.name, control.id, control.dataField, control.dataFieldId]
        .some((value) => typeof value === 'string' && /date_created|date_updated|filter_|gv_start|gv_end|gv_search_view/i.test(value))
    ));
    return {
      html: {
        dir: document.documentElement.getAttribute('dir'),
        computedDir: getComputedStyle(document.documentElement).direction,
        lang: document.documentElement.getAttribute('lang'),
      },
      rows: mapped,
      sortLinks,
      searchControls,
      forms,
    };
  }, fixture.entries.map((entry) => entry.token));
}

function assertTokensUnchanged(snap, expectedEntries = fixture.entries) {
  for (const expected of expectedEntries) {
    const row = snap.rows.find((item) => item.token === expected.token);
    if (!row) throw new Error(`Missing GravityView row for token ${expected.token}`);
    if (!row.text.includes(expected.token)) throw new Error(`User-authored token changed: ${expected.token}`);
  }
  const expectedTokens = new Set(expectedEntries.map((entry) => entry.token));
  const extras = snap.rows.filter((row) => !expectedTokens.has(row.token));
  if (extras.length) throw new Error(`GravityView result set contains unexpected rows: ${extras.map((row) => row.token).join(', ')}`);
}

function assertPresentation(snap, shouldPresent, expectedEntries = fixture.entries) {
  assertTokensUnchanged(snap, expectedEntries);
  for (const row of snap.rows) {
    if (row.diagnosticNodes !== 0) {
      throw new Error(`Production GravityView output exposed qualification/diagnostic markup for ${row.token}.`);
    }
  }
  for (const expected of expectedEntries) {
    const row = snap.rows.find((item) => item.token === expected.token);
    if (!row) throw new Error(`Missing GravityView row for ${expected.token}`);
    const expectedCreated = shouldPresent ? expected.expected_created_jalali : expected.expected_created_native;
    const expectedUpdated = shouldPresent ? expected.expected_updated_jalali : expected.expected_updated_native;
    if (!row.cells.includes(expectedCreated)) {
      throw new Error(`date_created presentation mismatch for ${expected.token}: ${JSON.stringify(row.cells)}`);
    }
    if (!row.cells.includes(expectedUpdated)) {
      throw new Error(`date_updated presentation mismatch for ${expected.token}: ${JSON.stringify(row.cells)}`);
    }
  }
}

async function sortedTokens(field, direction) {
  const url = new URL(fixture.page_url);
  url.searchParams.set(`sort[${field}]`, direction);
  await open(url.toString());
  const snap = await snapshot();
  assertPresentation(snap, mode === 'enabled');
  const tokens = snap.rows.map((row) => row.token);
  return {
    tokens,
    entry_ids: tokens.map((token) => Number(fixture.entries.find((entry) => entry.token === token)?.id)),
    snapshot: snap,
    url: page.url(),
  };
}

async function entryDateFilteredTokens(localDate, expectedEntry) {
  const url = new URL(fixture.page_url);
  url.searchParams.set('gv_search_view', String(fixture.view_id));
  url.searchParams.set('gv_start', localDate);
  await open(url.toString(), [expectedEntry.token]);
  const snap = await snapshot();
  assertPresentation(snap, mode === 'enabled', [expectedEntry]);
  const tokens = snap.rows.map((row) => row.token);
  return {
    path: 'exact-search-request-entry_date',
    request_key: 'gv_start',
    request_value: localDate,
    tokens,
    entry_ids: tokens.map((token) => Number(fixture.entries.find((entry) => entry.token === token)?.id)),
    snapshot: snap,
    url: page.url(),
  };
}

async function directSystemFilterTokens(field, value, expectedEntries) {
  const url = new URL(fixture.page_url);
  url.searchParams.set('gv_search_view', String(fixture.view_id));
  url.searchParams.set(`filter_${field}`, value);
  await open(url.toString(), expectedEntries.map((entry) => entry.token));
  const snap = await snapshot();
  assertPresentation(snap, mode === 'enabled', expectedEntries);
  const tokens = snap.rows.map((row) => row.token);
  return {
    path: 'exact-search-request-system-filter',
    request_key: `filter_${field}`,
    request_value: value,
    tokens,
    entry_ids: tokens.map((token) => Number(fixture.entries.find((entry) => entry.token === token)?.id)),
    snapshot: snap,
    url: page.url(),
  };
}

const result = {
  schema_version: '1.0.0',
  mode,
  exact_persiangravity_head: expectedHead,
  exact_gravityview_version: manifest.versions?.gravityview,
  exact_gravityview_sha256: manifest.package_sha256?.gravityview,
  diagnostics,
};

try {
  await open(fixture.page_url);
  const initial = await snapshot();
  const shouldPresent = mode === 'enabled';
  assertPresentation(initial, shouldPresent);

  if (mode === 'enabled' && initial.html.computedDir !== 'rtl') throw new Error(`Expected RTL enabled surface, got ${initial.html.computedDir}`);
  if (mode === 'english' && initial.html.dir === 'rtl') throw new Error('English control unexpectedly rendered RTL.');
  if (mode === 'english' && !/^en(?:-|$)/i.test(initial.html.lang || '')) throw new Error(`Expected English document language, got ${initial.html.lang}`);

  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForFunction(
    (tokens) => tokens.every((token) => document.body?.innerText.includes(token)),
    fixture.entries.map((entry) => entry.token),
    { timeout: 20000 },
  );
  const repeated = await snapshot();
  assertPresentation(repeated, shouldPresent);

  const createdAsc = await sortedTokens('date_created', 'asc');
  const createdDesc = await sortedTokens('date_created', 'desc');
  const updatedAsc = await sortedTokens('date_updated', 'asc');
  const updatedDesc = await sortedTokens('date_updated', 'desc');

  const bravo = fixture.entries.find((entry) => entry.key === 'bravo');
  if (!bravo) throw new Error('Expected deterministic GravityView date_created filter fixture is missing.');
  const createdFilter = await entryDateFilteredTokens('03/21/2026', bravo);
  const updatedFilter = await directSystemFilterTokens('date_updated', '2026-03-21 20:31:00', fixture.entries);

  const checks = [
    ['date_created asc', createdAsc.tokens, expectedSort('date_created', 'asc')],
    ['date_created desc', createdDesc.tokens, expectedSort('date_created', 'desc')],
    ['date_updated asc', updatedAsc.tokens, expectedSort('date_updated', 'asc')],
    ['date_updated desc', updatedDesc.tokens, expectedSort('date_updated', 'desc')],
  ];
  for (const [name, actual, expected] of checks) {
    if (JSON.stringify(actual) !== JSON.stringify(expected)) {
      throw new Error(`${name} GravityView ordering mismatch: actual=${JSON.stringify(actual)} expected=${JSON.stringify(expected)}`);
    }
  }
  if (JSON.stringify(createdFilter.tokens) !== JSON.stringify([bravo.token])) {
    throw new Error(`date_created search/filter result mismatch: ${JSON.stringify(createdFilter.tokens)}`);
  }
  const nativeUnscopedUpdatedTokens = fixture.entries.map((entry) => entry.token);
  if (JSON.stringify(updatedFilter.tokens) !== JSON.stringify(nativeUnscopedUpdatedTokens)) {
    throw new Error(`date_updated direct request must remain the exact 3.3.4 unscoped/no-op boundary: ${JSON.stringify(updatedFilter.tokens)}`);
  }

  result.initial = initial;
  result.repeated = repeated;
  result.sorting = {
    date_created_asc: createdAsc,
    date_created_desc: createdDesc,
    date_updated_asc: updatedAsc,
    date_updated_desc: updatedDesc,
  };
  result.filtering = {
    date_created_local_2026_03_21: createdFilter,
    date_updated_direct_request_noop: updatedFilter,
  };
  result.status = 'PASS';

  await page.goto(fixture.page_url, { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: path.join(artifactDir, `g008-gravityview-production-${mode}.png`), fullPage: true });
} catch (error) {
  result.status = 'FAIL';
  result.error = String(error?.stack || error);
  await page.screenshot({ path: path.join(artifactDir, `g008-gravityview-production-${mode}-failure.png`), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

fs.writeFileSync(
  path.join(artifactDir, `g008-gravityview-production-browser-${mode}.json`),
  JSON.stringify(result, null, 2) + '\n',
);

if (result.status !== 'PASS' || diagnostics.pageErrors.length > 0 || diagnostics.requestFailures.length > 0) {
  process.exit(1);
}
console.log(`PASS GravityView G-008 production browser mode=${mode}`);
