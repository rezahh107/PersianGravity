import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const adminPassword = process.env.WU008_ADMIN_PASSWORD;
const mode = process.env.WU008_G008_MODE;
if (!artifactDir || !manifestPath || !adminPassword || !['enabled', 'disabled'].includes(mode)) {
  throw new Error('WU008 artifact/manifest/admin password and WU008_G008_MODE=enabled|disabled are required.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (!manifest.g008_flow_inbox_url || !Array.isArray(manifest.g008_flow_entries) || manifest.g008_flow_entries.length < 3) {
  throw new Error('G008 Flow Inbox manifest fixture is incomplete.');
}

const expectedById = new Map(manifest.g008_flow_entries.map((entry) => [Number(entry.id), entry]));
const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
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

function rawCreatedTimestamp(dateCreated) {
  const milliseconds = Date.parse(`${dateCreated.replace(' ', 'T')}Z`);
  if (!Number.isFinite(milliseconds)) throw new Error(`Invalid manifest UTC date_created: ${dateCreated}`);
  return Math.floor(milliseconds / 1000);
}

async function visibleRowIds() {
  return page.locator('.ag-center-cols-container .ag-row').evaluateAll((nodes) => nodes
    .map((node) => {
      const rect = node.getBoundingClientRect();
      return {
        id: Number(node.getAttribute('row-id')),
        top: rect.top,
        visible: rect.width > 0 && rect.height > 0,
      };
    })
    .filter((row) => row.visible && Number.isFinite(row.id) && Number.isFinite(row.top))
    .sort((a, b) => a.top - b.top)
    .map((row) => row.id));
}

function sortedIds(rows, key, direction) {
  const multiplier = direction === 'asc' ? 1 : -1;
  return [...rows]
    .sort((a, b) => (Number(a[key]) - Number(b[key])) * multiplier)
    .map((row) => Number(row.id));
}

async function assertSort(rows, columnId) {
  const header = page.locator(`.ag-header-cell[col-id="${columnId}"]`).first();
  await header.waitFor({ timeout: 10000 });
  await header.click();
  await page.waitForTimeout(150);
  const first = await visibleRowIds();
  await header.click();
  await page.waitForTimeout(150);
  const second = await visibleRowIds();
  const asc = sortedIds(rows, columnId, 'asc');
  const desc = sortedIds(rows, columnId, 'desc');
  const firstIsAsc = JSON.stringify(first) === JSON.stringify(asc);
  const firstIsDesc = JSON.stringify(first) === JSON.stringify(desc);
  if (!firstIsAsc && !firstIsDesc) {
    throw new Error(`${columnId} first visual sort order does not follow raw compare values: ${JSON.stringify({ first, asc, desc })}`);
  }
  const expectedSecond = firstIsAsc ? desc : asc;
  if (JSON.stringify(second) !== JSON.stringify(expectedSecond)) {
    throw new Error(`${columnId} reverse visual sort order does not follow raw compare values.`);
  }
  return { first, second, first_direction: firstIsAsc ? 'asc' : 'desc' };
}

await login();
const navigation = await page.goto(manifest.g008_flow_inbox_url, { waitUntil: 'domcontentloaded' });
if (!navigation?.ok()) throw new Error(`G008 Inbox request failed: ${navigation?.status()}`);
const grid = page.locator('[data-js="gflow-inbox"]').first();
await grid.waitFor({ timeout: 15000 });
await page.locator('.ag-root-wrapper, .ag-root').first().waitFor({ timeout: 15000 });
await page.waitForTimeout(300);

const embedded = await page.evaluate(() => {
  const gridElement = document.querySelector('[data-js="gflow-inbox"]');
  const gridId = gridElement?.dataset?.gridId || 'inbox_default';
  const gridConfig = window.gflow_config?.grids?.[gridId]?.grid_options;
  return {
    gridId,
    rows: Array.isArray(gridConfig?.rowData) ? gridConfig.rowData : null,
    columnDefs: Array.isArray(gridConfig?.columnDefs)
      ? gridConfig.columnDefs.map((column) => ({ field: column.field ?? null, displayKey: column.displayKey ?? null }))
      : null,
  };
});
if (!Array.isArray(embedded.rows) || embedded.rows.length !== manifest.g008_flow_entries.length) {
  throw new Error(`Expected ${manifest.g008_flow_entries.length} embedded Inbox rows, received ${embedded.rows?.length}.`);
}
if (!Array.isArray(embedded.columnDefs)) {
  throw new Error('Exact Gravity Flow Inbox column definitions were not embedded in gflow_config.');
}
const dateCreatedColumn = embedded.columnDefs.find((column) => column.field === 'date_created');
const lastUpdatedColumn = embedded.columnDefs.find((column) => column.field === 'last_updated');
if (dateCreatedColumn?.displayKey !== 'date_created_human_readable') {
  throw new Error(`date_created display seam drifted: ${JSON.stringify(dateCreatedColumn)}`);
}
if (lastUpdatedColumn?.displayKey !== 'last_updated_human_readable') {
  throw new Error(`last_updated display seam drifted: ${JSON.stringify(lastUpdatedColumn)}`);
}

const rows = embedded.rows.map((row) => ({ ...row, id: Number(row.id), date_created: Number(row.date_created), last_updated: Number(row.last_updated) }));
for (const row of rows) {
  const fixture = expectedById.get(row.id);
  if (!fixture) throw new Error(`Unexpected Inbox row ${row.id}.`);
  if (row.date_created !== rawCreatedTimestamp(fixture.date_created)) {
    throw new Error(`date_created raw compare value changed for entry ${row.id}.`);
  }
  if (row.last_updated !== Number(fixture.workflow_timestamp)) {
    throw new Error(`last_updated raw compare value changed for entry ${row.id}.`);
  }
  if (mode === 'enabled') {
    if (row.date_created_human_readable !== fixture.expected_created_jalali) {
      throw new Error(`Enabled date_created display mismatch for entry ${row.id}: ${row.date_created_human_readable}`);
    }
    if (row.last_updated_human_readable !== fixture.expected_updated_jalali) {
      throw new Error(`Enabled last_updated display mismatch for entry ${row.id}: ${row.last_updated_human_readable}`);
    }
  } else {
    if (!row.date_created_human_readable || row.date_created_human_readable === fixture.expected_created_jalali) {
      throw new Error(`Disabled date_created did not return native Gravity Flow presentation for entry ${row.id}.`);
    }
    if (!row.last_updated_human_readable || row.last_updated_human_readable === fixture.expected_updated_jalali) {
      throw new Error(`Disabled last_updated did not return native Gravity Flow presentation for entry ${row.id}.`);
    }
  }
}

const dateCreatedSort = await assertSort(rows, 'date_created');
const lastUpdatedSort = await assertSort(rows, 'last_updated');
const search = page.locator('[data-js="gflow-inbox-search"]').first();
await search.waitFor({ timeout: 10000 });
await search.fill('beta');
await page.waitForTimeout(150);
const betaId = Number(manifest.g008_flow_entries.find((entry) => entry.key === 'beta').id);
const filteredIds = await visibleRowIds();
if (JSON.stringify(filteredIds) !== JSON.stringify([betaId])) {
  throw new Error(`Quick filter changed unexpectedly: ${JSON.stringify(filteredIds)}`);
}
await search.fill('');
await page.waitForTimeout(100);

if (diagnostics.pageErrors.length || diagnostics.requestFailures.length) {
  throw new Error(`Browser diagnostics failed: ${JSON.stringify(diagnostics)}`);
}

const evidence = {
  schema_version: '1.2.0',
  evidence_class: 'AUTHENTIC_GRAVITY_FLOW_INBOX_BROWSER',
  mode,
  exact_persiangravity_commit: process.env.WU008_PGR_SHA || null,
  exact_persiangravity_package_sha256: process.env.WU008_PGR_PACKAGE_SHA256 || null,
  exact_gravityflow_version: process.env.WU008_FLOW_VERSION || null,
  exact_gravityflow_package_sha256: process.env.WU008_FLOW_SHA256 || null,
  site_timezone: manifest.g008_flow_site_timezone,
  php_default_timezone: manifest.g008_flow_php_default_timezone,
  grid_id: embedded.gridId,
  column_defs: embedded.columnDefs,
  rows,
  sort: { date_created: dateCreatedSort, last_updated: lastUpdatedSort },
  quick_filter: { query: 'beta', visible_entry_ids: filteredIds },
  diagnostics,
};
fs.writeFileSync(path.join(artifactDir, `g008-flow-inbox-browser-${mode}.json`), `${JSON.stringify(evidence, null, 2)}\n`);
await page.screenshot({ path: path.join(artifactDir, `g008-flow-inbox-${mode}.png`), fullPage: true });
await browser.close();
console.log(`G008_FLOW_INBOX_BROWSER_${mode.toUpperCase()} PASS`);
