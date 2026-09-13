export function snapshotDiagnostics(diagnostics) {
  return {
    pageErrors: diagnostics.pageErrors.length,
    requestFailures: diagnostics.requestFailures.length,
  };
}

function isMaterialRuntimeRequestFailure(failure, runtimeOrigins) {
  if (!failure || typeof failure.url !== 'string') return true;

  let origin;
  try {
    origin = new URL(failure.url).origin;
  } catch {
    return true;
  }

  return runtimeOrigins.includes(origin);
}

export function evaluateOperationDiagnostics(operationId, diagnostics, baseline, runtimeOrigins) {
  const newPageErrors = diagnostics.pageErrors.slice(baseline.pageErrors);
  const newRequestFailures = diagnostics.requestFailures
    .slice(baseline.requestFailures)
    .filter((failure) => isMaterialRuntimeRequestFailure(failure, runtimeOrigins));

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
