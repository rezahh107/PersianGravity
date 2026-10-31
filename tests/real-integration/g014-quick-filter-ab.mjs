// Diagnostic-only A/B probe. Does NOT replace or relax G014 acceptance.
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const mode = process.argv[2];
if (!['off', 'on'].includes(mode)) throw Error('Mode must be off or on');
const manifest = JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH, 'utf8'));
const beta = manifest.g008_flow_entries?.find(entry => entry.key === 'beta');
if (Number(beta?.id) !== 27 || Number(beta?.expected_due_timestamp) !== 1900275300) {
  throw Error('Authentic G008 beta fixture identity drift');
}
if (manifest.persiangravity_source_commit !== process.env.WU008_PGR_SHA ||
    manifest.versions?.gravityflow !== '3.1.0') throw Error('Exact host/Head mismatch');
const dir = process.env.G014_AB_EVIDENCE_DIR;
if (!dir) throw Error('Missing AB output directory');
fs.mkdirSync(dir, { recursive: true });
const browser = await chromium.launch({ headless: true });
const output = {
  schema: 'g014-native-input-event-ab-v1',
  exact_head: process.env.WU008_PGR_SHA,
  flow_version: manifest.versions.gravityflow,
  mode, siteLocale: 'fa_IR', userLocale: 'fa_IR',
  expectedBeta: { id: 27, due: 1900275300 },
  trials: [],
};
try {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const login = await context.newPage();
  const response = await login.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
  if (!response?.ok()) throw Error('Login unavailable for A/B probe');
  await login.locator('#user_login').fill('runtime_admin');
  await login.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
  await Promise.all([
    login.waitForURL(url => !url.pathname.includes('wp-login.php')),
    login.locator('#wp-submit').click(),
  ]);
  await login.close();

  for (const method of ['fill', 'sequential']) {
    for (const query of ['1900275300', 'G014-NONEXISTENT-QUERY-984321']) {
      const page = await context.newPage();
      const trial = { method, query, status: 'INCOMPLETE' };
      output.trials.push(trial);
      try {
        const rsp = await page.goto(manifest.gravityflow_frontend_inbox_url, { waitUntil: 'domcontentloaded' });
        if (!rsp?.ok()) throw Error('Frontend Inbox unavailable');
        const scope = '[data-js="gflow-inbox"]';
        await page.locator(scope + ' .ag-root-wrapper').waitFor({ timeout: 20000 });
        await page.waitForFunction(() => {
          const host = document.querySelector('[data-js="gflow-inbox"]');
          const opts = window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options;
          return opts?.rowData?.length === 36 && document.querySelectorAll(
            '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row').length > 0;
        }, null, { timeout: 20000 });
        const header = page.locator(scope + ' .ag-header-cell[col-id="id"]').first();
        await header.click();
        await header.click();
        await page.waitForTimeout(200);
        await page.evaluate(() => {
          const host = document.querySelector('[data-js="gflow-inbox"]');
          const search = document.querySelector('[data-js="gflow-inbox-search"]');
          if (!host || !search || !host.dataset.gridId) throw Error('Native input/grid identity absent');
          window.__g014AB = { events: [], filterChanges: 0 };
          for (const type of ['keydown', 'keyup', 'input', 'change', 'search']) {
            search.addEventListener(type, event => {
              window.__g014AB.events.push({
                type, key: event.key || null, value: search.value,
              });
            }, { capture: true });
          }
          const api = window.gflow_config?.grids?.[host.dataset.gridId]?.grid_options?.api;
          if (typeof api?.addEventListener === 'function') {
            api.addEventListener('filterChanged', () => { window.__g014AB.filterChanges++; });
          }
        });
        const snapshot = () => page.evaluate(() => {
          const host = document.querySelector('[data-js="gflow-inbox"]');
          const input = document.querySelector('[data-js="gflow-inbox-search"]');
          const opts = window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options;
          const api = opts?.api;
          const visibleRows = [...document.querySelectorAll(
            '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row')]
            .map(row => ({ id: Number(row.getAttribute('row-id')), top: row.getBoundingClientRect().top,
              width: row.getBoundingClientRect().width, height: row.getBoundingClientRect().height }))
            .filter(row => row.width > 0 && row.height > 0 && Number.isSafeInteger(row.id))
            .sort((a, b) => a.top - b.top).map(row => row.id);
          const filtered = [];
          if (typeof api?.forEachNodeAfterFilterAndSort === 'function') {
            api.forEachNodeAfterFilterAndSort(node => filtered.push(Number(node.data?.id)));
          }
          const grid = host?.querySelector('.ag-root-wrapper');
          return {
            gridId: host?.dataset.gridId || null,
            inputValue: input?.value ?? null,
            configuredRowCount: opts?.rowData?.length ?? null,
            rawBeta: opts?.rowData?.filter(row => Number(row.id) === 27)
              .map(row => ({ id: row.id, due_date: row.due_date,
                due_date_human_readable: row.due_date_human_readable })) || [],
            visibleRowIds: visibleRows,
            visibleCount: visibleRows.length,
            nativeApiAvailable: !!api,
            nativeDisplayedCount: typeof api?.getDisplayedRowCount === 'function'
              ? api.getDisplayedRowCount() : null,
            nativeFilteredIds: typeof api?.forEachNodeAfterFilterAndSort === 'function' ? filtered : null,
            paginationPage: typeof api?.paginationGetCurrentPage === 'function'
              ? api.paginationGetCurrentPage() : null,
            paginationPages: typeof api?.paginationGetTotalPages === 'function'
              ? api.paginationGetTotalPages() : null,
            rtl: grid?.classList.contains('ag-rtl') ?? null,
            loading: !!host?.querySelector('.ag-overlay-loading-center,.ag-overlay-loading-wrapper'),
            error: !!host?.querySelector('.ag-overlay-error'),
            events: window.__g014AB?.events || [],
            filterChanges: window.__g014AB?.filterChanges ?? null,
          };
        });
        trial.before = await snapshot();
        if (trial.before.configuredRowCount !== 36 || trial.before.rawBeta.length !== 1 ||
            Number(trial.before.rawBeta[0].due_date) !== 1900275300)
          throw Error('Authentic populated fixture mismatch');
        const input = page.locator('[data-js="gflow-inbox-search"]').first();
        await input.click();
        if (method === 'fill') await input.fill(query);
        else await input.pressSequentially(query);
        await page.waitForTimeout(650);
        trial.after = await snapshot();
        await input.fill('');
        await page.waitForTimeout(350);
        trial.afterFillClear = await snapshot();
        await input.click();
        await input.press('ControlOrMeta+A');
        await input.press('Backspace');
        await page.waitForTimeout(350);
        trial.afterKeyboardClear = await snapshot();
        trial.status = 'OBSERVED';
      } catch (error) {
        trial.status = 'ERROR';
        trial.error = String(error?.stack || error);
      } finally {
        await page.close();
      }
    }
  }
  await context.close();
} finally {
  fs.writeFileSync(path.join(dir, 'ab-' + mode + '.json'), JSON.stringify(output, null, 2) + '\n');
  await browser.close();
}
console.log('G014_AB_NATIVE_OBSERVATIONS ' + mode + ' ' +
  JSON.stringify(output.trials.map(t => ({
    method: t.method, query: t.query, status: t.status,
    gridId: t.after?.gridId,
    visible: t.after?.visibleRowIds,
    filtered: t.after?.nativeFilteredIds,
    filterChanges: t.after?.filterChanges,
    keyup: t.after?.events.filter(e => e.type === 'keyup').length,
    clearedByFill: t.afterFillClear?.nativeFilteredIds?.length,
    clearedByKeyboard: t.afterKeyboardClear?.nativeFilteredIds?.length,
    error: t.error,
  }))));
if (output.trials.some(t => t.status !== 'OBSERVED'))
  throw Error('Incomplete native A/B observations; inspect uploaded JSON');
