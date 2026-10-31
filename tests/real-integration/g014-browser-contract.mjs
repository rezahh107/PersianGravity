// Pure contracts for the G-014 browser runner; no WordPress or Playwright dependency.
// A URL object is not accepted by Playwright 1.55.0 Page.goto.
export function settingsNavigationUrl(adminUrl) {
  const base = new URL(adminUrl);
  const target = new URL('admin.php?page=persian-gravityforms-settings', base);
  if (target.origin !== base.origin) throw Error('Untrusted settings origin');
  return target.toString();
}

// Every return value includes a primitive boolean; returning a snapshot object
// directly from waitForFunction was the original false-ready bug.
export function classifyInboxReadiness(snapshot, { requireRows = false } = {}) {
  if (!snapshot || typeof snapshot !== 'object')
    return { ready: false, state: 'loading', reason: 'No Grid snapshot' };
  if (snapshot.errorOverlay)
    return { ready: false, state: 'error', reason: 'Visible native Grid error' };
  if (!snapshot.hostVisible || !snapshot.rootVisible || !snapshot.hasConfig ||
      !snapshot.configIdentityMatches || !snapshot.hasRootMode ||
      snapshot.headers < 1 || snapshot.columnDefs < 1 || snapshot.loadingActive)
    return { ready: false, state: 'loading', reason: 'Grid not operational' };
  if (snapshot.rows > 0 && snapshot.configRows > 0)
    return { ready: true, state: 'operational_populated', reason: '' };
  if (snapshot.rows === 0 && snapshot.emptyOverlay)
    return requireRows
      ? { ready: false, state: 'fixture_empty', reason: 'Populated fixture required' }
      : { ready: true, state: 'operational_empty', reason: '' };
  return { ready: false, state: 'loading', reason: 'Rows/overlay not settled' };
}
