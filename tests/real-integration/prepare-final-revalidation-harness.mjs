import fs from 'node:fs';

function replaceOnce(file, from, to, label) {
  const source = fs.readFileSync(file, 'utf8');
  const first = source.indexOf(from);
  if (first < 0 || source.indexOf(from, first + from.length) >= 0) {
    throw new Error(`${label}: expected exactly one source fragment in ${file}`);
  }
  fs.writeFileSync(file, source.slice(0, first) + to + source.slice(first + from.length));
}

const requestProvenance = (name) => `${name}.method(), post_data: ${name}.postData(), resource_type: ${name}.resourceType(), navigation_request: ${name}.isNavigationRequest()`;

const browserFile = 'tests/real-integration/browser-tests-19.mjs';
replaceOnce(
  browserFile,
  `const page = await context.newPage();\nconst diagnostics = { console: [], page_errors: [], request_failures: [], provider_json_requests: [] };\npage.on('console', (msg) => diagnostics.console.push({ type: msg.type(), text: msg.text().slice(0, 1600), url: page.url() }));\npage.on('pageerror', (error) => diagnostics.page_errors.push({ error: String(error?.stack || error).slice(0, 4000), url: page.url() }));\npage.on('requestfailed', (req) => diagnostics.request_failures.push({ url: req.url(), error: req.failure()?.errorText || 'unknown' }));\npage.on('request', (req) => {\n  if (/persian-gravityforms\\/languages\\/providers\\/.*-fa_IR-.*\\.json/i.test(req.url())) diagnostics.provider_json_requests.push(req.url());\n});`,
  `const page = await context.newPage();\nlet activeSurfaceId = 'GLOBAL_BROWSER_CONTEXT';\nconst currentSurfaceId = () => {\n  try { return new URL(page.url()).searchParams.get('wu008_surface') || activeSurfaceId; } catch { return activeSurfaceId; }\n};\nconst diagnostics = { console: [], page_errors: [], request_failures: [], http_failures: [], provider_json_requests: [] };\npage.on('console', (msg) => diagnostics.console.push({ surface_id: currentSurfaceId(), type: msg.type(), text: msg.text().slice(0, 1600), url: page.url(), location: msg.location() }));\npage.on('pageerror', (error) => diagnostics.page_errors.push({ surface_id: currentSurfaceId(), error: String(error?.stack || error).slice(0, 4000), url: page.url() }));\npage.on('requestfailed', (req) => diagnostics.request_failures.push({ surface_id: currentSurfaceId(), url: req.url(), page_url: page.url(), method: req.method(), post_data: req.postData(), resource_type: req.resourceType(), navigation_request: req.isNavigationRequest(), error: req.failure()?.errorText || 'unknown' }));\npage.on('response', (response) => {\n  if (response.status() >= 400) {\n    const req = response.request();\n    diagnostics.http_failures.push({ surface_id: currentSurfaceId(), url: response.url(), page_url: page.url(), method: req.method(), post_data: req.postData(), resource_type: req.resourceType(), navigation_request: req.isNavigationRequest(), status: response.status(), status_text: response.statusText() });\n  }\n});\npage.on('request', (req) => {\n  if (/persian-gravityforms\\/languages\\/providers\\/.*-fa_IR-.*\\.json/i.test(req.url())) diagnostics.provider_json_requests.push(req.url());\n});`,
  'browser diagnostics instrumentation',
);
replaceOnce(
  browserFile,
  `for (const surface of manifest.surfaces) {\n  index += 1;`,
  `for (const surface of manifest.surfaces) {\n  index += 1;\n  activeSurfaceId = surface.surface_id;`,
  'browser surface attribution',
);

const rtlFile = 'tests/real-integration/rtl-recheck-19.mjs';
replaceOnce(
  rtlFile,
  `const page = await context.newPage();\nconst recheckDiagnostics = { page_errors: [], request_failures: [] };\npage.on('pageerror', (error) => recheckDiagnostics.page_errors.push({ url: page.url(), error: String(error?.stack || error).slice(0, 3000) }));\npage.on('requestfailed', (request) => recheckDiagnostics.request_failures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));`,
  `const page = await context.newPage();\nlet activeSurfaceId = 'GLOBAL_BROWSER_CONTEXT';\nconst currentSurfaceId = () => {\n  try { return new URL(page.url()).searchParams.get('wu008_surface') || activeSurfaceId; } catch { return activeSurfaceId; }\n};\nconst recheckDiagnostics = { console: [], page_errors: [], request_failures: [], http_failures: [] };\npage.on('console', (message) => recheckDiagnostics.console.push({ surface_id: currentSurfaceId(), type: message.type(), text: message.text().slice(0, 1600), url: page.url(), location: message.location() }));\npage.on('pageerror', (error) => recheckDiagnostics.page_errors.push({ surface_id: currentSurfaceId(), url: page.url(), error: String(error?.stack || error).slice(0, 3000) }));\npage.on('requestfailed', (request) => recheckDiagnostics.request_failures.push({ surface_id: currentSurfaceId(), url: request.url(), page_url: page.url(), method: request.method(), post_data: request.postData(), resource_type: request.resourceType(), navigation_request: request.isNavigationRequest(), error: request.failure()?.errorText || 'unknown' }));\npage.on('response', (response) => {\n  if (response.status() >= 400) {\n    const request = response.request();\n    recheckDiagnostics.http_failures.push({ surface_id: currentSurfaceId(), url: response.url(), page_url: page.url(), method: request.method(), post_data: request.postData(), resource_type: request.resourceType(), navigation_request: request.isNavigationRequest(), status: response.status(), status_text: response.statusText() });\n  }\n});`,
  'rtl diagnostics instrumentation',
);
replaceOnce(
  rtlFile,
  `for (const row of matrix) {\n  index += 1;`,
  `for (const row of matrix) {\n  index += 1;\n  activeSurfaceId = row.surface_id;`,
  'rtl surface attribution',
);

const scannerFile = 'tests/real-integration/scanner-editor-remediation.mjs';
replaceOnce(
  scannerFile,
  `const requestFailures = [];\n\npage.on('pageerror', (error) => pageErrors.push({ message: String(error?.message || error), stack: String(error?.stack || '') }));\npage.on('console', (message) => consoleMessages.push({ type: message.type(), text: message.text().slice(0, 2000) }));\npage.on('requestfailed', (request) => {\n  requestFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' });\n});`,
  `const requestFailures = [];\nconst httpFailures = [];\nconst scannerSurfaceId = 'gravityforms::admin_builder::admin_page:gf_edit_forms';\n\npage.on('pageerror', (error) => pageErrors.push({ surface_id: scannerSurfaceId, url: page.url(), message: String(error?.message || error), stack: String(error?.stack || '') }));\npage.on('console', (message) => consoleMessages.push({ surface_id: scannerSurfaceId, type: message.type(), text: message.text().slice(0, 2000), url: page.url(), location: message.location() }));\npage.on('requestfailed', (request) => {\n  requestFailures.push({ surface_id: scannerSurfaceId, url: request.url(), page_url: page.url(), method: request.method(), post_data: request.postData(), resource_type: request.resourceType(), navigation_request: request.isNavigationRequest(), error: request.failure()?.errorText || 'unknown' });\n});\npage.on('response', (response) => {\n  if (response.status() >= 400) {\n    const request = response.request();\n    httpFailures.push({ surface_id: scannerSurfaceId, url: response.url(), page_url: page.url(), method: request.method(), post_data: request.postData(), resource_type: request.resourceType(), navigation_request: request.isNavigationRequest(), status: response.status(), status_text: response.statusText() });\n  }\n});`,
  'scanner diagnostics instrumentation',
);
const scannerSource = fs.readFileSync(scannerFile, 'utf8');
const scannerNeedle = `    request_failures: requestFailures,`;
const scannerMatches = scannerSource.split(scannerNeedle).length - 1;
if (scannerMatches !== 2) throw new Error(`scanner evidence diagnostics: expected 2 insertion points, got ${scannerMatches}`);
fs.writeFileSync(scannerFile, scannerSource.replaceAll(scannerNeedle, `    request_failures: requestFailures,\n    http_failures: httpFailures,`));

console.log('Final revalidation harness diagnostics instrumentation applied fail-closed with request provenance.');
