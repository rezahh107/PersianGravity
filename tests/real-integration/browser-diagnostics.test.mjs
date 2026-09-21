import assert from 'node:assert/strict';
import test from 'node:test';
import {
  evaluateOperationDiagnostics,
  formatDiagnosticGateFailure,
  snapshotDiagnostics,
} from './browser-diagnostics.mjs';

const runtimeOrigins = ['http://127.0.0.1:8080'];

function diagnostics() {
  return { pageErrors: [], requestFailures: [], requestsStarted: 0 };
}

test('clean diagnostics delta allows the same operation to pass', () => {
  const state = diagnostics();
  const baseline = snapshotDiagnostics(state);
  const gate = evaluateOperationDiagnostics('op-clean', state, baseline, runtimeOrigins);
  assert.equal(gate.ok, true);
  assert.deepEqual(gate.blocking, { pageErrors: [], requestFailures: [] });
});

test('one new pageError fails the same operation', () => {
  const state = diagnostics();
  const baseline = snapshotDiagnostics(state);
  state.pageErrors.push('ReferenceError: wp is not defined');

  const gate = evaluateOperationDiagnostics('op-pageerror', state, baseline, runtimeOrigins);
  assert.equal(gate.ok, false);
  assert.deepEqual(gate.blocking.pageErrors, ['ReferenceError: wp is not defined']);
  assert.match(formatDiagnosticGateFailure(gate), /op-pageerror.*pageErrors=.*wp is not defined/);
});

test('one new material runtime request failure fails the same operation', () => {
  const state = diagnostics();
  const baseline = snapshotDiagnostics(state);
  state.requestsStarted = 1;
  state.requestFailures.push({
    url: 'http://127.0.0.1:8080/wp-includes/js/wp-util.min.js',
    method: 'GET',
    error: 'net::ERR_FAILED',
    requestSequence: 1,
  });

  const gate = evaluateOperationDiagnostics('op-request', state, baseline, runtimeOrigins);
  assert.equal(gate.ok, false);
  assert.equal(gate.blocking.requestFailures.length, 1);
  assert.match(formatDiagnosticGateFailure(gate), /op-request.*requestFailures=.*wp-util/);
});

test('diagnostics that predate the baseline do not retroactively fail the next operation', () => {
  const state = diagnostics();
  state.pageErrors.push('old page error');
  state.requestFailures.push({
    url: 'http://127.0.0.1:8080/old.js',
    method: 'GET',
    error: 'old failure',
  });
  const baseline = snapshotDiagnostics(state);

  const gate = evaluateOperationDiagnostics('op-next', state, baseline, runtimeOrigins);
  assert.equal(gate.ok, true);
});

test('external request failures are retained globally but are not material runtime blockers', () => {
  const state = diagnostics();
  const baseline = snapshotDiagnostics(state);
  state.requestsStarted = 1;
  state.requestFailures.push({
    url: 'https://example.invalid/telemetry',
    method: 'POST',
    error: 'net::ERR_NAME_NOT_RESOLVED',
    requestSequence: 1,
  });

  const gate = evaluateOperationDiagnostics('op-external', state, baseline, runtimeOrigins);
  assert.equal(gate.ok, true);
  assert.equal(state.requestFailures.length, 1);
});

test('a request started before the baseline is retained but not attributed to the next operation when navigation aborts it', () => {
  const state = diagnostics();
  state.requestsStarted = 1;
  const baseline = snapshotDiagnostics(state);
  state.requestFailures.push({
    url: 'http://127.0.0.1:8080/wp-admin/admin-ajax.php?action=background-probe',
    method: 'GET',
    error: 'net::ERR_ABORTED',
    requestSequence: 1,
  });

  const gate = evaluateOperationDiagnostics('op-navigation', state, baseline, runtimeOrigins);
  assert.equal(gate.ok, true);
  assert.equal(state.requestFailures.length, 1);
});

test('the observed WordPress compression capability probe abort is retained but non-blocking', () => {
  const state = diagnostics();
  const baseline = snapshotDiagnostics(state);
  state.requestsStarted = 1;
  state.requestFailures.push({
    url: 'http://127.0.0.1:8080/wp-admin/admin-ajax.php?action=wp-compression-test&test=yes&_ajax_nonce=fixture',
    method: 'GET',
    error: 'net::ERR_ABORTED',
    requestSequence: 1,
  });

  const gate = evaluateOperationDiagnostics('op-wordpress-compression-probe', state, baseline, runtimeOrigins);
  assert.equal(gate.ok, true);
  assert.equal(state.requestFailures.length, 1);
});

test('the compression endpoint still blocks when the failure shape differs from the observed benign abort', () => {
  for (const failure of [
    {
      url: 'http://127.0.0.1:8080/wp-admin/admin-ajax.php?action=wp-compression-test&test=yes',
      method: 'GET',
      error: 'net::ERR_FAILED',
      requestSequence: 1,
    },
    {
      url: 'http://127.0.0.1:8080/wp-admin/admin-ajax.php?action=another-action&test=yes',
      method: 'GET',
      error: 'net::ERR_ABORTED',
      requestSequence: 1,
    },
  ]) {
    const state = diagnostics();
    const baseline = snapshotDiagnostics(state);
    state.requestsStarted = 1;
    state.requestFailures.push(failure);
    const gate = evaluateOperationDiagnostics('op-not-whitelisted', state, baseline, runtimeOrigins);
    assert.equal(gate.ok, false);
    assert.equal(gate.blocking.requestFailures.length, 1);
  }
});

test('a blocking diagnostic cannot be deferred to a suite-global synthetic failure', () => {
  const state = diagnostics();
  const firstBaseline = snapshotDiagnostics(state);
  state.pageErrors.push('SyntaxError: Unexpected token <');

  const affectedGate = evaluateOperationDiagnostics('op-affected', state, firstBaseline, runtimeOrigins);
  assert.equal(affectedGate.ok, false);
  assert.match(formatDiagnosticGateFailure(affectedGate), /op-affected/);

  const nextBaseline = snapshotDiagnostics(state);
  const nextGate = evaluateOperationDiagnostics('op-next-clean', state, nextBaseline, runtimeOrigins);
  assert.equal(nextGate.ok, true);
});
