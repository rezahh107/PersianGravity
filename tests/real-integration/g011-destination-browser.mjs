import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const mode = process.argv[2];
if (!['enabled', 'disabled'].includes(mode)) throw Error('Pass enabled or disabled explicitly.');
const artifactDir = process.env.WU008_ARTIFACT_DIR;
const fixture = JSON.parse(fs.readFileSync(path.join(artifactDir, 'g011-destination-fixture.json'), 'utf8'));
if (!/^[0-9a-f]{40}$/.test(fixture.head) || fixture.gf_version !== '3.1.1.1') {
  throw Error('Locked source/host fixture mismatch.');
}
const browser = await chromium.launch({ headless: true });
const evidence = { head: fixture.head, gf_version: fixture.gf_version, module_state: mode, status: 'FAIL', results: [] };
const expectedJalali = '۱۴۰۴/۱۲/۲۹';
const pDigits = /^[۰-۹]{4}[/][۰-۹]{2}[/][۰-۹]{2}$/u;
function assert(condition, message) { if (!condition) throw Error(message); }
function values(receipt, key) { return receipt.pairs.filter((pair) => pair.key === key).map((pair) => pair.value); }
function exact(receipt, key, expected) {
  const received = values(receipt, key);
  assert(received.length === 1 && received[0] === expected,
    `server ${key}: expected ${JSON.stringify(expected)}, got ${JSON.stringify(received)}`);
}
try {
  for (const ajax of ['ordinary', 'ajax']) {
    for (const route of ['page', 'redirect']) {
      const page = await browser.newPage();
      const errors = [];
      page.on('pageerror', (error) => errors.push(String(error)));
      try {
        const initial = await page.goto(fixture.pages[ajax], { waitUntil: 'domcontentloaded' });
        assert(initial?.ok(), `source form HTTP failure: ${ajax}/${route}`);
        await page.locator(`#input_${fixture.form_id}_1`).fill(route);
        await page.locator(`#input_${fixture.form_id}_2`).fill('03/20/2026');
        await page.locator(`#gform_submit_button_${fixture.form_id}`).click();
        // A successful PHP-composed URL is NOT sufficient: this is real browser
        // navigation followed by a server-rendered WordPress destination receipt.
        const receiver = page.locator('#g011-destination-receipt');
        await receiver.waitFor({ timeout: 40000 });
        const receipt = JSON.parse(await receiver.textContent());
        const destination = new URL(page.url());
        assert(receipt.marker === 'G011_SERVER_RECEIPT', 'server receipt marker missing');
        assert(receipt.post_id === fixture.destination_id, 'server resolved the wrong destination post');
        assert(receipt.http_method === 'GET', 'destination request was not a GET');
        assert(destination.origin === new URL(fixture.destination_url).origin, 'unexpected destination origin');
        assert(destination.searchParams.get('page_id') === String(fixture.destination_id), 'destination URL page_id mismatch');
        assert(values(receipt, 'page_id').length === 1, 'server did not receive native destination page_id');
        const j = values(receipt, 'jalali');
        assert(j.length === 1, `server jalali parameter count ${j.length}`);
        if (mode === 'enabled') {
          assert(j[0] === expectedJalali, `enabled Jalali server mismatch ${j[0]}`);
          const created = values(receipt, 'created');
          assert(created.length === 1 && pDigits.test(created[0]), `created Jalali date not converted: ${created}`);
          assert(receipt.raw_query.includes(encodeURIComponent(expectedJalali)), 'raw query lacks UTF-8 encoded Jalali value');
        } else {
          exact(receipt, 'jalali', '{pgr_jdate_field_2}');
          exact(receipt, 'created', '{pgr_jdate_created_date}');
        }
        exact(receipt, 'native', '03/20/2026');
        exact(receipt, 'person', route);
        exact(receipt, 'literal', 'keep&safe');
        exact(receipt, 'space', 'two words');
        const entry = values(receipt, 'entry');
        assert(entry.length === 1 && /^[1-9][0-9]*$/.test(entry[0]), `native entry_id not received: ${entry}`);
        const repetitions = values(receipt, 'repeat');
        const expectedRepeats = route === 'redirect' ? ['base', 'first', 'second'] : ['first', 'second'];
        assert(JSON.stringify(repetitions) === JSON.stringify(expectedRepeats),
          `duplicate query parameter loss/reorder: ${JSON.stringify(repetitions)}`);
        if (route === 'redirect') exact(receipt, 'existing', 'one&two');
        else assert(values(receipt, 'existing').length === 0, 'Page unexpectedly inherited Redirect parameter');
        assert(receipt.php_repeat === 'second', 'PHP last-duplicate behavior changed');
        assert(receipt.raw_query.includes('literal=keep%26safe'), 'literal URL encoding lost');
        assert(errors.length === 0, `browser errors: ${errors.join('; ')}`);
        evidence.results.push({ ajax, route, status: 'PASS', entry_id: Number(entry[0]),
          browser_url: page.url(), destination_receipt: receipt, browser_pageerrors: errors });
      } catch (error) {
        evidence.results.push({ ajax, route, status: 'FAIL', error: String(error?.stack || error),
          browser_url: page.url(), browser_pageerrors: errors });
        await page.screenshot({ path: path.join(artifactDir, `g011-destination-${mode}-${ajax}-${route}-failure.png`), fullPage: true }).catch(() => {});
        throw error;
      } finally {
        await page.close();
      }
    }
  }
  assert(evidence.results.length === 4, 'Not all Page/Redirect paths executed');
  evidence.status = 'PASS';
} catch (error) {
  evidence.failure = String(error?.stack || error);
} finally {
  fs.writeFileSync(path.join(artifactDir, `g011-destination-browser-${mode}.json`), JSON.stringify(evidence, null, 2) + '\n');
  await browser.close();
}
if (evidence.status !== 'PASS') throw Error(`G011 Page/Redirect ${mode} failed: ${evidence.failure}`);
console.log(`G011 real Page/Redirect browser ${mode}: PASS 4/4`);
