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

const expectedByToken = new Map(fixture.entries.map((entry) => [entry.token, entry]));
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

async function open(url) {
  const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
  if (!response || !response.ok()) throw new Error(`GravityView response failed: ${response?.status()} ${url}`);
  await page.waitForFunction(
    (tokens) => tokens.every((token) => document.body?.innerText.includes(token)),
    fixture.entries.map((entry) => entry.token),
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
    return {
      html: {
        dir: document.documentElement.getAttribute('dir'),
        lang: document.documentElement.getAttribute('lang'),
      },
      rows: mapped,
      sortLinks,
    };
  }, fixture.entries.map((entry) => entry.token));
}

function assertTokensUnchanged(snap) {
  for (const expected of fixture.entries) {
    const row = snap.rows.find((item) => item.token === expected.token);
    if (!row) throw new Error(`Missing GravityView row for token ${expected.token}`);
    if (!row.text.includes(expected.token)) throw new Error(`User-authored token changed: ${expected.token}`);
  }
}

function assertPresentation(snap, shouldPresent) {
  assertTokensUnchanged(snap);
  const spans = snap.rows.flatMap((row) => row.spans);
  if (shouldPresent) {
    if (spans.length !== 6) throw new Error(`Expected six bounded date spans, got ${spans.length}`);
    for (const span of spans) {
      const expected = fixture.entries.find((entry) => Number(entry.id) === span.entry);
      if (!expected) throw new Error(`Unknown entry identity in presentation span: ${span.entry}`);
      const expectedRaw = span.field === 'date_created' ? expected.date_created : expected.date_updated;
      const expectedJalali = span.field === 'date_created' ? expected.expected_created_jalali : expected.expected_updated_jalali;
      if (span.raw !== expectedRaw) throw new Error(`Raw source mismatch for ${span.field}/${span.entry}`);
      if (span.text !== expectedJalali) throw new Error(`Jalali presentation mismatch for ${span.field}/${span.entry}: ${span.text}`);
      if (!span.native || span.native === span.text) throw new Error(`Native GravityView output was not captured separately for ${span.field}/${span.entry}`);
    }
  } else if (spans.length !== 0) {
    throw new Error(`Native/fail-closed mode unexpectedly rendered ${spans.length} prototype spans.`);
  }
}

async function sortedTokens(field, direction) {
  const url = new URL(fixture.page_url);
  url.searchParams.set(`sort[${field}]`, direction);
  await open(url.toString());
  const snap = await snapshot();
  assertPresentation(snap, mode === 'enabled');
  return { tokens: snap.rows.map((row) => row.token), snapshot: snap, url: page.url() };
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

  result.initial = initial;
  result.repeated = repeated;
  result.sorting = {
    date_created_asc: createdAsc,
    date_created_desc: createdDesc,
    date_updated_asc: updatedAsc,
    date_updated_desc: updatedDesc,
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
