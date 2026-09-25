import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const mode = process.env.WU008_GV_MODE;
const expectedHead = process.env.WU008_PGR_SHA;
if (!artifactDir || !manifestPath || !['enabled', 'disabled', 'english', 'drift'].includes(mode) || !expectedHead) {
  throw new Error('GravityView browser qualification environment is incomplete.');
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
    throw new Error('GravityView qualification surface contains a fatal/critical error.');
  }
}

async function snapshot() {
  return page.evaluate((tokens) => {
    const rows = [...document.querySelectorAll('tr')].filter((row) => tokens.some((token) => (row.textContent || '').includes(token)));
    const mapped = rows.map((row) => {
      const token = tokens.find((candidate) => (row.textContent || '').includes(candidate)) || '';
      const spans = [...row.querySelectorAll('.wu008-gv-system-date')].map((node) => ({
        field: node.getAttribute('data-field'),
        entry: Number(node.getAttribute('data-entry')),
        raw: node.getAttribute('data-raw'),
        native: node.getAttribute('data-native'),
        text: (node.textContent || '').trim(),
      }));
      return {
        token,
        text: (row.textContent || '').replace(/\s+/g, ' ').trim(),
        cells: [...row.querySelectorAll('td')].map((cell) => (cell.textContent || '').replace(/\s+/g, ' ').trim()),
        spans,
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
  const spans = snap.rows.flatMap((row) => row.spans);
  if (shouldPresent) {
    if (spans.length !== expectedEntries.length * 2) throw new Error(`Expected ${expectedEntries.length * 2} bounded date spans, got ${spans.length}`);
    for (const span of spans) {
      const expected = fixture.entries.find((entry) => Number(entry.id) === span.entry);
      if (!expected) throw new Error(`Unknown entry identity in presentation span: ${span.entry}`);
      const expectedRaw = span.field === 'date_created' ? expected.date_created : expected.date_updated;
      const expectedJalali = span.field === 'date_created' ? expected.expected_created_jalali : expected.expected_updated_jalali;
      if (span.raw !== expectedRaw) throw new Error(`Raw source mismatch for ${span.field}/${span.entry}`);
      if (span.text !== expectedJalali) throw new Error(`Jalali presentation mismatch for ${span.field}/${span.entry}: ${span.text}`);
      if (!span.native || span.native === span.text) throw new Error(`Native GravityView output was not captured separately for ${span.field}/${span.entry}`);
    }
  } else {
    if (spans.length !== 0) {
      throw new Error(`Native/fail-closed mode unexpectedly rendered ${spans.length} prototype spans.`);
    }
    for (const expected of expectedEntries) {
      const row = snap.rows.find((item) => item.token === expected.token);
      if (!row) throw new Error(`Missing native GravityView row for ${expected.token}`);
      if (!row.cells.includes(expected.expected_created_native)) {
        throw new Error(`Native date_created output mismatch for ${expected.token}: ${JSON.stringify(row.cells)}`);
      }
      if (!row.cells.includes(expected.expected_updated_native)) {
        throw new Error(`Native date_updated output mismatch for ${expected.token}: ${JSON.stringify(row.cells)}`);
      }
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

async function submitSearchForm(form) {
  const submit = form.locator('button[type="submit"], input[type="submit"]').first();
  if ((await submit.count()) > 0) {
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      submit.click(),
    ]);
    return;
  }
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    form.evaluate((node) => node.requestSubmit()),
  ]);
}

async function entryDateFilteredTokens(localDate, expectedEntry) {
  await open(fixture.page_url);
  await page.waitForSelector('input[name="gv_start"]', { timeout: 20000 });

  const input = page.locator('input[name="gv_start"]').first();
  const form = input.locator('xpath=ancestor::form[1]');
  if ((await form.count()) !== 1) {
    throw new Error('GravityView entry_date control is not associated with exactly one form.');
  }

  await input.fill(localDate);
  const submittedControl = await input.evaluate((node) => ({
    tag: node.tagName.toLowerCase(),
    type: node.getAttribute('type'),
    name: node.getAttribute('name'),
    id: node.getAttribute('id'),
    value: 'value' in node ? node.value : null,
    formAction: node.form?.getAttribute('action') ?? null,
    formMethod: node.form?.getAttribute('method') ?? null,
  }));
  if (submittedControl.name !== 'gv_start' || submittedControl.value !== localDate) {
    throw new Error(`GravityView entry_date control rejected ${localDate}: ${JSON.stringify(submittedControl)}`);
  }

  await submitSearchForm(form);
  await page.waitForFunction(
    (token) => document.body?.innerText.includes(token),
    expectedEntry.token,
    { timeout: 20000 },
  );

  const snap = await snapshot();
  assertPresentation(snap, mode === 'enabled', [expectedEntry]);
  const tokens = snap.rows.map((row) => row.token);
  return {
    path: 'host-search-bar-entry_date',
    tokens,
    entry_ids: tokens.map((token) => Number(fixture.entries.find((entry) => entry.token === token)?.id)),
    snapshot: snap,
    url: page.url(),
    submittedControl,
  };
}

async function directSystemFilterTokens(field, value, expectedEntry) {
  const url = new URL(fixture.page_url);
  url.searchParams.set('gv_search_view', String(fixture.view_id));
  url.searchParams.set(`filter_${field}`, value);
  await open(url.toString(), [expectedEntry.token]);
  const snap = await snapshot();
  assertPresentation(snap, mode === 'enabled', [expectedEntry]);
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

  if (mode === 'enabled' && initial.html.dir !== 'rtl') throw new Error(`Expected RTL enabled surface, got ${initial.html.dir}`);
  if (mode === 'english' && initial.html.dir !== 'ltr') throw new Error(`Expected LTR English control, got ${initial.html.dir}`);

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
  const charlie = fixture.entries.find((entry) => entry.key === 'charlie');
  if (!bravo || !charlie) throw new Error('Expected deterministic GravityView filter fixtures are missing.');
  const createdFilter = await entryDateFilteredTokens('2026-03-21', bravo);
  const updatedFilter = await directSystemFilterTokens('date_updated', '2026-03-21', charlie);

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
  if (JSON.stringify(updatedFilter.tokens) !== JSON.stringify([charlie.token])) {
    throw new Error(`date_updated exact request-filter result mismatch: ${JSON.stringify(updatedFilter.tokens)}`);
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
    date_updated_raw_2026_03_21: updatedFilter,
  };
  result.status = 'PASS';

  await page.goto(fixture.page_url, { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: path.join(artifactDir, `g008-gravityview-${mode}.png`), fullPage: true });
} catch (error) {
  result.status = 'FAIL';
  result.error = String(error?.stack || error);
  await page.screenshot({ path: path.join(artifactDir, `g008-gravityview-${mode}-failure.png`), fullPage: true }).catch(() => {});
} finally {
  await browser.close();
}

fs.writeFileSync(
  path.join(artifactDir, `g008-gravityview-browser-${mode}.json`),
  JSON.stringify(result, null, 2) + '\n',
);

if (result.status !== 'PASS' || diagnostics.pageErrors.length > 0 || diagnostics.requestFailures.length > 0) {
  process.exit(1);
}
console.log(`PASS GravityView G-008 browser qualification mode=${mode}`);
