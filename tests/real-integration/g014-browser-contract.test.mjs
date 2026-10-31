import assert from 'node:assert/strict';
import { test } from 'node:test';
import { settingsNavigationUrl, classifyInboxReadiness } from './g014-browser-contract.mjs';

test('historical URL object is rejected; corrected same-origin navigation is a string', () => {
  const adminUrl = 'http://127.0.0.1:8080/wp-admin/';
  const goto = url => {
    if (typeof url !== 'string') throw TypeError('page.goto: url: expected string, got object');
    return new URL(url).pathname;
  };
  assert.throws(() => goto(new URL('admin.php?page=persian-gravityforms-settings', adminUrl)), /expected string/);
  const fixed = settingsNavigationUrl(adminUrl);
  assert.equal(typeof fixed, 'string');
  assert.equal(goto(fixed), '/wp-admin/admin.php');
  assert.equal(new URL(fixed).searchParams.get('page'), 'persian-gravityforms-settings');
});

const operational = {
  hostVisible: true, rootVisible: true, hasConfig: true,
  configIdentityMatches: true, hasRootMode: true,
  headers: 5, columnDefs: 5, rows: 3, configRows: 3,
  loadingActive: false, errorOverlay: false, emptyOverlay: false,
};

test('a populated Grid becomes ready only with real identity and no loading', () => {
  assert.deepEqual(classifyInboxReadiness(operational), {
    ready: true, state: 'operational_populated', reason: '',
  });
  for (const missing of [
    { rootVisible: false }, { hasConfig: false },
    { configIdentityMatches: false }, { loadingActive: true },
    { hasRootMode: false }, { headers: 0 },
  ]) {
    const result = classifyInboxReadiness({ ...operational, ...missing });
    assert.equal(result.ready, false);
    assert.equal(result.state, 'loading');
  }
  const failed = classifyInboxReadiness({ ...operational, errorOverlay: true });
  assert.equal(failed.ready, false);
  assert.equal(failed.state, 'error');
});

test('empty operational Inbox is ready, while a populated-fixture gate rejects it', () => {
  const empty = { ...operational, rows: 0, configRows: 0, emptyOverlay: true };
  assert.equal(classifyInboxReadiness(empty).state, 'operational_empty');
  assert.equal(classifyInboxReadiness(empty).ready, true);
  const strict = classifyInboxReadiness(empty, { requireRows: true });
  assert.equal(strict.ready, false);
  assert.equal(strict.state, 'fixture_empty');
  assert.equal(classifyInboxReadiness({ ...empty, emptyOverlay: false }).ready, false);
  assert.equal(classifyInboxReadiness({ ...empty, loadingActive: true }).ready, false);
  assert.equal(classifyInboxReadiness(null).ready, false);
});
