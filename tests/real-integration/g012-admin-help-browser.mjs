import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const manifestPath = process.env.WU008_MANIFEST_PATH;
const password = process.env.WU008_ADMIN_PASSWORD;
const head = process.env.WU008_PGR_SHA;
if (!artifactDir || !manifestPath || !password || !head) {
  throw Error('Authenticated WU008 runtime and exact current Head are required.');
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
if (manifest.persiangravity_source_commit !== head ||
    manifest.versions?.gravityforms !== '3.1.1.1') {
  throw Error('Installed PersianGravity or authentic Gravity Forms identity mismatch.');
}
const topics = [
  'national-id', 'jalali-date', 'jalali-presentation', 'iranian-address',
  'digit-normalization', 'iranian-currency', 'structured-scanner',
];
const base = new URL(manifest.admin_url);
const overviewUrl = new URL('admin.php?page=persian-gravityforms', base).href;
const helpUrl = (lang) => new URL('admin.php?page=persian-gravityforms-help&lang=' + lang, base).href;
const evidence = {
  head, wordpress: '6.8.3', gravityforms: manifest.versions.gravityforms,
  persiangravity: manifest.versions.persiangravity,
  suite: 'G-012 authenticated licensed WordPress + Gravity Forms admin browser',
  results: [], screen_reader_spoken_output: 'NOT_VERIFIED',
};
const browser = await chromium.launch({ headless: true });
evidence.chromium = browser.version();
const context = await browser.newContext({
  viewport: { width: 1280, height: 900 },
  permissions: ['clipboard-read', 'clipboard-write'],
});
const page = await context.newPage();
const check = async (id, body) => {
  try {
    const details = await body();
    evidence.results.push({ id, status: 'PASS', details });
    console.log('PASS ' + id);
  } catch (error) {
    evidence.results.push({ id, status: 'FAIL', error: String(error?.stack || error) });
    console.error('FAIL ' + id + ': ' + String(error));
  }
};
const assert = (truth, description) => { if (!truth) throw Error(description); };
const open = async (url) => {
  const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
  assert(response?.ok(), 'HTTP error or redirect to wrong page: ' + page.url());
  await page.locator('#wpadminbar').waitFor();
  await page.locator('#adminmenu').waitFor();
  assert(!page.url().includes('wp-login.php'), 'Unauthenticated admin page');
  assert((await page.locator('body').getAttribute('class') || '').includes('wp-admin'),
    'Not the WordPress admin shell');
};
try {
  await check('AUTHENTIC_HOST', async () => {
    assert(manifest.versions.gravityforms === '3.1.1.1', 'Gravity Forms version drift');
    const login = await page.goto(manifest.login_url, { waitUntil: 'domcontentloaded' });
    assert(login?.ok(), 'Login endpoint inaccessible');
    await page.locator('#user_login').fill('runtime_admin');
    await page.locator('#user_pass').fill(password);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.locator('#wp-submit').click(),
    ]);
    await open(overviewUrl);
    assert((await page.locator('article.pgr-module-card').count()) === 7,
      'Installed PersianGravity did not render seven admin module cards');
    return { url: page.url(), modules: 7, gf: manifest.versions.gravityforms };
  });

  if (!evidence.results.at(-1) || evidence.results.at(-1).status !== 'PASS') {
    throw Error('Cannot continue without authenticated host proof.');
  }

  for (const topic of topics) {
    await check('OVERVIEW_TO_HELP_' + topic, async () => {
      await open(overviewUrl);
      const card = page.locator('article.pgr-module-card').filter({
        has: page.locator('code[dir="ltr"]').getByText(topic, { exact: true }),
      });
      assert(await card.count() === 1, 'Module card missing or duplicated: ' + topic);
      const target = card.locator('a.button[href*="persian-gravityforms-help"]');
      assert(await target.count() === 1, 'Module Help link missing: ' + topic);
      await target.click();
      assert(new URL(page.url()).hash === '#' + topic, 'Wrong Overview to Help anchor: ' + page.url());
      assert(new URL(page.url()).searchParams.get('lang') === 'fa', 'Overview default language drift');
      const section = page.locator('section#' + topic);
      assert(await section.count() === 1, 'Help anchor not present in actual DOM');
      assert(await section.locator('.pgr-help-example').count() === 1, 'Worked example not rendered');
      return { target: page.url(), exampleRendered: true };
    });
  }

  for (const lang of ['fa', 'en']) {
    await check('BILINGUAL_HELP_' + lang, async () => {
      await open(helpUrl(lang));
      const direction = await page.locator('.pgr-help__layout').getAttribute('dir');
      assert(direction === (lang === 'fa' ? 'rtl' : 'ltr'), 'Help content direction incorrect');
      const labels = lang === 'fa' ? ['سناریو', 'روش بررسی'] : ['Scenario', 'How to verify'];
      for (const topic of topics) {
        const section = page.locator('section#' + topic);
        assert(await section.count() === 1, 'Help section missing: ' + topic);
        const example = section.locator('.pgr-help-example');
        assert(await example.count() === 1, 'Worked example missing: ' + topic);
        assert(await example.locator('ol li').count() >= 3, 'Steps incomplete: ' + topic);
        assert(await example.locator('.pgr-help-sample').count() >= 2, 'Copyable samples incomplete: ' + topic);
        const text = await example.innerText();
        for (const label of labels) assert(text.includes(label), 'Missing translated label ' + label);
        const prose = await example.locator('p, ol li').allTextContents();
        assert(!prose.some((item) => item.includes(String.fromCharCode(96))),
          'Literal Markdown backtick in rendered prose: ' + topic);
        assert(!prose.some((item) => /(?:^|\s)\*[^*\s][^*\r\n]*\*/u.test(item)),
          'Literal Markdown emphasis in rendered prose: ' + topic);
      }
      const allText = await page.locator('#jalali-presentation .pgr-help-sample__code').allTextContents();
      for (const family of ['created', 'updated', 'payment', 'today']) {
        for (const suffix of ['', '_date', '_time']) {
          assert(allText.join('\n').includes('{pgr_jdate_' + family + suffix + '}'),
            'Missing G-011 token ' + family + suffix);
        }
      }
      assert(allText.join('\n').includes('{pgr_jdate_field_2}'), 'Native GF Date token example missing');
      await page.screenshot({ path: path.join(artifactDir, 'g012-help-' + lang + '-desktop.png'), fullPage: true });
      return { sections: 7, direction, sampleCount: await page.locator('.pgr-help-sample').count() };
    });
  }

  await check('CLIPBOARD_REAL_API_AND_KEYBOARD', async () => {
    await open(helpUrl('en'));
    const button = page.locator('#jalali-presentation .pgr-help-sample button[data-pgr-copy]').first();
    const sample = await button.locator('xpath=../..').locator('pre code').textContent();
    await button.focus();
    assert(await button.evaluate((element) => document.activeElement === element), 'Button not focusable');
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => {
      const el = document.querySelector('#jalali-presentation .pgr-help-sample__status');
      return el?.textContent === 'Copied.';
    });
    const copied = await page.evaluate(() => navigator.clipboard.readText());
    assert(copied === sample, 'Actual browser clipboard content mismatch');
    assert(await button.evaluate((element) => document.activeElement === element), 'Focus lost after copy');
    await page.keyboard.press('Space');
    assert((await page.evaluate(() => navigator.clipboard.readText())) === sample, 'Space activation lost');
    const status = button.locator('xpath=../..').locator('[role="status"]');
    assert(await status.getAttribute('aria-live') === 'polite', 'Status not a polite live region');
    assert(await status.getAttribute('aria-atomic') === 'true', 'Live region not atomic');
    return { keyboard: ['Enter', 'Space'], actualClipboardReadback: sample, domStatus: await status.innerText() };
  });

  await check('CLIPBOARD_DENIAL_WITH_FALLBACK', async () => {
    await open(helpUrl('fa'));
    await page.evaluate(() => {
      window.__g012Fallback = null;
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true, value: { writeText: async () => { throw Error('denied'); } },
      });
      document.execCommand = (command) => {
        if (command !== 'copy') return false;
        window.__g012Fallback = document.activeElement?.value ?? '';
        return true;
      };
    });
    const button = page.locator('#national-id button[data-pgr-copy]').first();
    const sample = await button.locator('xpath=../..').locator('pre code').textContent();
    await button.click();
    assert(await page.evaluate(() => window.__g012Fallback) === sample,
      'Injected legacy fallback did not see exact sample');
    assert(await button.locator('xpath=../..').locator('[role="status"]').innerText() === 'کپی شد.',
      'Fallback did not surface Persian status');
    return { clipboardRejection: 'injected', execCommand: 'injected success', status: 'کپی شد.' };
  });

  await check('CLIPBOARD_DENIAL_AND_FALLBACK_FAILURE', async () => {
    await open(helpUrl('en'));
    await page.evaluate(() => {
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true, value: { writeText: async () => { throw Error('permission denied'); } },
      });
      document.execCommand = () => false;
    });
    const button = page.locator('#national-id button[data-pgr-copy]').first();
    await button.focus();
    await page.keyboard.press('Enter');
    const status = button.locator('xpath=../..').locator('[role="status"]');
    await page.waitForFunction(() => {
      const el = document.querySelector('#national-id .pgr-help-sample__status');
      return el?.textContent?.startsWith('Copy failed;');
    });
    assert((await status.innerText()).startsWith('Copy failed;'), 'Failure status missing');
    assert(await button.evaluate((element) => document.activeElement === element), 'Failure moved focus');
    return { clipboardRejection: 'injected', execCommand: 'injected failure', status: await status.innerText() };
  });

  for (const lang of ['fa', 'en']) {
    for (const width of [320, 390, 768]) {
      await check('MOBILE_' + lang + '_' + width, async () => {
        await page.setViewportSize({ width, height: 800 });
        await open(helpUrl(lang));
        const layout = page.locator('.pgr-help__layout');
        assert(await layout.getAttribute('dir') === (lang === 'fa' ? 'rtl' : 'ltr'),
          'Mobile Help direction incorrect');
        const sizes = await page.evaluate(() => ({
          innerWidth: window.innerWidth,
          documentWidth: document.documentElement.scrollWidth,
          helpWidth: document.querySelector('.pgr-help')?.getBoundingClientRect().width,
          sampleWidths: [...document.querySelectorAll('.pgr-help-sample__code')]
            .map((el) => ({ clientWidth: el.clientWidth, scrollWidth: el.scrollWidth })),
        }));
        assert(sizes.documentWidth <= sizes.innerWidth + 2,
          'Document horizontal overflow (' + sizes.documentWidth + ' > ' + sizes.innerWidth + ')');
        assert(sizes.sampleWidths.length >= 14, 'Sample elements missing at mobile width');
        await page.screenshot({
          path: path.join(artifactDir, 'g012-help-' + lang + '-' + width + '.png'),
          fullPage: true,
        });
        return sizes;
      });
    }
  }
} catch (fatal) {
  evidence.fatal = String(fatal?.stack || fatal);
} finally {
  evidence.status = evidence.results.length > 0 &&
    evidence.results.every((result) => result.status === 'PASS') &&
    !evidence.fatal ? 'PASS' : 'FAIL';
  evidence.not_proven = [
    'No actual assistive-technology voice announcement: aria-live DOM behavior only.',
    'Denied clipboard branches use controlled injection; actual browser permission denial not simulated.',
    'Full seven-module sample submissions not performed by this Help-only suite.',
  ];
  fs.mkdirSync(artifactDir, { recursive: true });
  fs.writeFileSync(path.join(artifactDir, 'g012-admin-help-browser.json'),
    JSON.stringify(evidence, null, 2) + '\n');
  if (evidence.status === 'FAIL') {
    await page.screenshot({ path: path.join(artifactDir, 'g012-admin-help-failure.png'), fullPage: true }).catch(() => {});
  }
  await browser.close();
}
if (evidence.status !== 'PASS') {
  throw Error('G-012 authenticated browser acceptance failed; inspect g012-admin-help-browser.json');
}
console.log('G-012 authenticated WordPress + Gravity Forms Help browser PASS');
