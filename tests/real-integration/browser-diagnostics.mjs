export function snapshotDiagnostics(diagnostics) {
  return {
    pageErrors: diagnostics.pageErrors.length,
    requestFailures: diagnostics.requestFailures.length,
    requestsStarted: diagnostics.requestsStarted ?? 0,
  };
}

function isKnownNonBlockingRuntimeAbort(failure) {
  if (!failure || failure.error !== 'net::ERR_ABORTED' || typeof failure.url !== 'string') return false;

  let url;
  try {
    url = new URL(failure.url);
  } catch {
    return false;
  }

  return url.pathname.endsWith('/wp-admin/admin-ajax.php')
    && url.searchParams.get('action') === 'wp-compression-test'
    && url.searchParams.get('test') === 'yes';
}

function isMaterialRuntimeRequestFailure(failure, runtimeOrigins) {
  if (!failure || typeof failure.url !== 'string') return true;

  let origin;
  try {
    origin = new URL(failure.url).origin;
  } catch {
    return true;
  }

  if (!runtimeOrigins.includes(origin)) return false;
  if (isKnownNonBlockingRuntimeAbort(failure)) return false;

  return true;
}

export function evaluateOperationDiagnostics(operationId, diagnostics, baseline, runtimeOrigins) {
  const newPageErrors = diagnostics.pageErrors.slice(baseline.pageErrors);
  const newRequestFailures = diagnostics.requestFailures
    .slice(baseline.requestFailures)
    .filter((failure) => isMaterialRuntimeRequestFailure(failure, runtimeOrigins))
    .filter((failure) => failure.requestSequence == null || failure.requestSequence > baseline.requestsStarted);

  return {
    operationId,
    ok: newPageErrors.length === 0 && newRequestFailures.length === 0,
    blocking: {
      pageErrors: newPageErrors,
      requestFailures: newRequestFailures,
    },
  };
}

export function formatDiagnosticGateFailure(gate) {
  const parts = [];
  if (gate.blocking.pageErrors.length > 0) {
    parts.push(`pageErrors=${JSON.stringify(gate.blocking.pageErrors)}`);
  }
  if (gate.blocking.requestFailures.length > 0) {
    parts.push(`requestFailures=${JSON.stringify(gate.blocking.requestFailures)}`);
  }
  return `${gate.operationId} emitted blocking diagnostics: ${parts.join(' ')}`;
}
