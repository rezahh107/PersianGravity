import fs from 'node:fs';
import path from 'node:path';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
const baseUrl = process.env.WU008_BASE_URL;
if (!artifactDir || !baseUrl) throw new Error('WU008_ARTIFACT_DIR and WU008_BASE_URL are required.');
const runtimeOrigin = new URL(baseUrl).origin;
const allowedClassifications = new Set([
  'BLOCKING_IN_SCOPE',
  'NON_BLOCKING_IN_SCOPE',
  'OUT_OF_SCOPE_UNCLASSIFIED_CONTENT',
  'UPSTREAM_OR_VENDOR_BEHAVIOR',
  'ENVIRONMENT_DEFECT',
]);

function readJson(file) {
  if (!fs.existsSync(file)) throw new Error(`Required evidence file missing: ${file}`);
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}
function parsedUrl(value) {
  try { return new URL(value); } catch { return null; }
}
function surfaceFromUrl(value) {
  const url = parsedUrl(value);
  return url?.searchParams.get('wu008_surface') || null;
}
function productRelated(text = '', url = '') {
  return /PGRScannerEditor|pgr[_-]|persian[-_/ ]gravity|persian-gravityforms|structured scanner/i.test(`${text}\n${url}`);
}
function isCompressionProbeAbort(record) {
  const url = parsedUrl(record.url_resource);
  return record.kind === 'request_failure'
    && url?.origin === runtimeOrigin
    && url.pathname === '/wp-admin/admin-ajax.php'
    && url.searchParams.get('action') === 'wp-compression-test'
    && url.searchParams.get('test') === 'yes'
    && /ERR_ABORTED/i.test(record.message_status || '');
}
function isDisposableFavicon404(record) {
  const url = parsedUrl(record.url_resource);
  return record.kind === 'http_failure'
    && url?.origin === runtimeOrigin
    && url.pathname === '/favicon.ico'
    && Number(record.status) === 404;
}

const mainRaw = readJson(path.join(artifactDir, 'browser-diagnostics.json'));
const rtlRaw = readJson(path.join(artifactDir, 'rtl-recheck-diagnostics.json'));
const scannerFile = path.join(artifactDir, 'form-builder-remediation', 'browser-evidence.json');
const scannerRaw = readJson(scannerFile);
const matrix = readJson(path.join(artifactDir, 'surface-evidence-matrix.json'));
if (!Array.isArray(matrix) || matrix.length !== 19 || new Set(matrix.map((row) => row.surface_id)).size !== 19) {
  throw new Error('Diagnostic audit requires the exact 19-row surface matrix.');
}

function normalize(sourceRun, evidenceReference, raw, fixedSurfaceId = null) {
  const records = [];
  const add = (kind, item, messageStatus, resource, status = null) => {
    const pageUrl = item.page_url || item.url || null;
    records.push({
      source_run: sourceRun,
      kind,
      surface_id: fixedSurfaceId || item.surface_id || surfaceFromUrl(pageUrl) || 'GLOBAL_BROWSER_CONTEXT',
      url_resource: resource || pageUrl || '',
      page_url: pageUrl,
      message_status: String(messageStatus || ''),
      status,
      method: item.method || null,
      evidence_reference: evidenceReference,
      raw: item,
    });
  };
  for (const item of raw.page_errors || []) {
    add('pageerror', item, item.error || `${item.message || ''}\n${item.stack || ''}`, item.url || item.page_url || '');
  }
  for (const item of raw.request_failures || []) {
    add('request_failure', item, item.error || 'unknown request failure', item.url || '', null);
  }
  for (const item of raw.http_failures || []) {
    add('http_failure', item, `${item.status || ''} ${item.status_text || ''}`.trim(), item.url || '', item.status ?? null);
  }
  const consoleItems = raw.console || raw.console_messages || [];
  for (const item of consoleItems) {
    if (item.type !== 'error' && item.type !== 'assert') continue;
    const locationUrl = item.location && typeof item.location === 'object' ? item.location.url : '';
    add('console_error', item, item.text || 'console error', locationUrl || item.url || item.page_url || '');
  }
  return records;
}

const records = [
  ...normalize('surface-runtime', 'browser-diagnostics.json#raw.surface_runtime', mainRaw),
  ...normalize('rtl-recheck', 'browser-diagnostics.json#raw.rtl_recheck', rtlRaw),
  ...normalize('form-builder-remediation', 'form-builder-remediation/browser-evidence.json', scannerRaw, 'gravityforms::admin_builder::admin_page:gf_edit_forms'),
];

const nonBlockingResourceKeys = new Set();
for (const record of records) {
  if (isCompressionProbeAbort(record)) nonBlockingResourceKeys.add(`${record.surface_id}|${record.url_resource}`);
  if (isDisposableFavicon404(record)) nonBlockingResourceKeys.add(`${record.surface_id}|${record.url_resource}`);
}

function disposition(record) {
  const url = parsedUrl(record.url_resource);
  const sameOrigin = url?.origin === runtimeOrigin;
  const pgrRelated = productRelated(record.message_status, record.url_resource);

  if (isCompressionProbeAbort(record)) {
    return {
      classification: 'UPSTREAM_OR_VENDOR_BEHAVIOR',
      blocks_acceptance: false,
      rationale: 'The failed request is WordPress core\'s explicit wp-compression-test admin-ajax probe and Chromium reported the expected aborted probe transport; it is not a PersianGravity resource or application request.',
    };
  }
  if (isDisposableFavicon404(record)) {
    return {
      classification: 'ENVIRONMENT_DEFECT',
      blocks_acceptance: false,
      rationale: 'The disposable evidence theme has no favicon; a missing /favicon.ico is environment-only and does not execute PersianGravity or any accepted product surface behavior.',
    };
  }
  if (record.kind === 'console_error' && /Failed to load resource/i.test(record.message_status)) {
    const correlated = records.find((candidate) => candidate.surface_id === record.surface_id
      && candidate.kind === 'http_failure'
      && !candidate.classification
      && nonBlockingResourceKeys.has(`${candidate.surface_id}|${candidate.url_resource}`));
    if (correlated) {
      return {
        classification: correlated.url_resource.endsWith('/favicon.ico') ? 'ENVIRONMENT_DEFECT' : 'UPSTREAM_OR_VENDOR_BEHAVIOR',
        blocks_acceptance: false,
        rationale: 'This console network message correlates to a separately dispositioned non-product resource diagnostic on the same browser surface.',
      };
    }
  }
  if (pgrRelated) {
    return {
      classification: 'BLOCKING_IN_SCOPE',
      blocks_acceptance: true,
      rationale: 'The diagnostic names or targets PersianGravity/PGR/Structured Scanner runtime authority, so it is an in-scope product diagnostic and blocks this evidence pass.',
    };
  }
  if (record.kind === 'pageerror') {
    return {
      classification: 'OUT_OF_SCOPE_UNCLASSIFIED_CONTENT',
      blocks_acceptance: true,
      rationale: 'An uncaught page-level JavaScript exception was observed, but the evidence does not prove upstream/vendor/environment ownership. The run fails closed rather than hiding or guessing the cause.',
    };
  }
  if ((record.kind === 'request_failure' || record.kind === 'http_failure') && sameOrigin) {
    return {
      classification: 'OUT_OF_SCOPE_UNCLASSIFIED_CONTENT',
      blocks_acceptance: true,
      rationale: 'A failed same-origin disposable WordPress application request was observed and is not one of the narrowly proven non-product exceptions. Ownership is not established, so acceptance fails closed.',
    };
  }
  if (record.kind === 'console_error') {
    return {
      classification: 'OUT_OF_SCOPE_UNCLASSIFIED_CONTENT',
      blocks_acceptance: true,
      rationale: 'A browser console error was observed without evidence proving product, vendor, or environment ownership. Acceptance fails closed until it can be classified from evidence.',
    };
  }
  return {
    classification: 'OUT_OF_SCOPE_UNCLASSIFIED_CONTENT',
    blocks_acceptance: true,
    rationale: 'The diagnostic is meaningful but its ownership is not established from this evidence. Acceptance fails closed.',
  };
}

for (const record of records) {
  const result = disposition(record);
  record.classification = result.classification;
  record.classification_rationale = result.rationale;
  record.blocks_acceptance = result.blocks_acceptance;
  record.reproduction_context = `${record.source_run}; surface=${record.surface_id}; page=${record.page_url || 'not-recorded'}`;
  if (!allowedClassifications.has(record.classification)) throw new Error(`Invalid diagnostic classification: ${record.classification}`);
}

const findingsPath = path.join(artifactDir, 'finding-register.json');
const priorFindings = readJson(findingsPath).filter((finding) => finding.diagnostic_source !== 'final-browser-audit');
const diagnosticFindings = records.map((record) => ({
  diagnostic_source: 'final-browser-audit',
  classification: record.classification,
  surface_id: record.surface_id,
  diagnostic_kind: record.kind,
  url_resource: record.url_resource,
  message_status: record.message_status,
  reproduction_context: record.reproduction_context,
  evidence_reference: record.evidence_reference,
  observed_behavior: `${record.kind}: ${record.message_status}`.slice(0, 3500),
  classification_rationale: record.classification_rationale,
  blocks_acceptance: record.blocks_acceptance,
  blocks_wu008: record.blocks_acceptance,
}));
const findings = [...priorFindings, ...diagnosticFindings];
fs.writeFileSync(findingsPath, JSON.stringify(findings, null, 2) + '\n');

const classificationCounts = Object.fromEntries([...allowedClassifications].map((name) => [name, records.filter((record) => record.classification === name).length]));
const blocking = records.filter((record) => record.blocks_acceptance);
const audited = {
  schema_version: '3.0.0',
  result: blocking.length === 0 ? 'PASS' : 'BLOCKED',
  runtime_origin: runtimeOrigin,
  policy: {
    meaningful_console_types: ['error', 'assert'],
    unexplained_pageerror_compatible_with_pass: false,
    unexplained_same_origin_failure_compatible_with_pass: false,
    raw_console_non_error_messages_preserved_but_not_promoted_to_acceptance_diagnostics: true,
  },
  summary: {
    meaningful_diagnostics: records.length,
    pageerrors: records.filter((record) => record.kind === 'pageerror').length,
    console_errors: records.filter((record) => record.kind === 'console_error').length,
    request_failures: records.filter((record) => record.kind === 'request_failure').length,
    http_failures: records.filter((record) => record.kind === 'http_failure').length,
    classification_counts: classificationCounts,
    blocking_or_unresolved: blocking.length,
    all_records_dispositioned: records.every((record) => allowedClassifications.has(record.classification)),
  },
  records,
  raw: {
    surface_runtime: mainRaw,
    rtl_recheck: rtlRaw,
    form_builder_remediation: scannerRaw,
  },
};
fs.writeFileSync(path.join(artifactDir, 'browser-diagnostics.json'), JSON.stringify(audited, null, 2) + '\n');
console.log(JSON.stringify(audited.summary));
if (audited.result !== 'PASS') process.exit(1);
