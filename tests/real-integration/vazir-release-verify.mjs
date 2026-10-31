import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';

export const VAZIR = Object.freeze({
  version: '1.5.0',
  zip: 'vazir-font-wp-1.5.0.zip',
  bytes: 300139,
  sha: '95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5',
  manifestSha: 'b08602d22366b286b480b8150c4765f15b6141a66f5605edabda0b7839235b62',
  checksumSha: 'fcfb07df6a4475aa9ebb73245271350f24d9e0578c84a09de015ee0319ee871c',
  entrypoint: 'vazir-font-wp/vazir-font-wp.php',
});
export function assert(ok, message) { if (!ok) throw Error('VAZIR_RELEASE_REJECTED: ' + message); }
export function validateHeader(text) {
  const match = text.match(/^\s*\*\s*Version:\s*([^\s]+)/m);
  assert(match && match[1] === VAZIR.version, 'plugin main-file version mismatch');
}
export function validateArchiveNames(names) {
  assert(Array.isArray(names) && names.includes(VAZIR.entrypoint), 'missing canonical entrypoint');
  const weights = ['light','regular','medium','bold','black'];
  for (const name of names) {
    assert(name.startsWith('vazir-font-wp/') && !name.includes('..') &&
      !name.includes('\\') && !name.startsWith('/') && !/[\x00-\x1f]/.test(name),
      'unsafe archive entry: ' + name);
    assert(!/\.(?:zip|exe|phar)$/i.test(name), 'nested executable/archive entry');
  }
  const fonts = names.filter(name => /^vazir-font-wp\/assets\/fonts\/vazirmatn-.*\.woff2$/i.test(name));
  assert(fonts.length >= weights.length, 'expected self-hosted WOFF2 assets absent');
}
export function validateManifest(m) {
  assert(m && typeof m === 'object' && !Array.isArray(m), 'invalid release manifest');
  assert(m.schema_version === 1 && m.mode === 'publish', 'wrong release manifest schema/mode');
  assert(m.plugin_version === VAZIR.version && m.plugin_entrypoint === VAZIR.entrypoint,
    'release version/entrypoint mismatch');
  assert(m.zip_filename === VAZIR.zip && m.zip_bytes === VAZIR.bytes &&
    m.sha256 === VAZIR.sha, 'release manifest ZIP identity mismatch');
  assert(m.vazirmatn_release === 'v33.003', 'unexpected bundled font release');
  assert(m.validation === 'PASS' && m.clean_install_smoke === 'PASS',
    'release validation/smoke not qualified');
  assert(['PUBLISHED','PUBLISHED_AND_VERIFIED'].includes(m.publication),
    'release not in published state');
  assert(/^[0-9a-f]{40}$/.test(m.source_sha || ''), 'unbound Vazir release source');
}
function digest(file) { return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex'); }
function checkFile(dir, filename, sha) {
  const full = path.join(dir, filename);
  assert(fs.existsSync(full) && fs.lstatSync(full).isFile() &&
    !fs.lstatSync(full).isSymbolicLink(), 'missing or unsafe artifact ' + filename);
  assert(digest(full) === sha, 'SHA-256 mismatch: ' + filename);
  return full;
}
export function validateOfficial(dir) {
  const zip = checkFile(dir, VAZIR.zip, VAZIR.sha);
  assert(fs.statSync(zip).size === VAZIR.bytes, 'official ZIP size mismatch');
  const manifestFile = checkFile(dir, 'release-manifest.json', VAZIR.manifestSha);
  const checksumFile = checkFile(dir, VAZIR.zip + '.sha256', VAZIR.checksumSha);
  const checksum = fs.readFileSync(checksumFile, 'utf8').trim();
  assert(checksum.split(/\s+/)[0] === VAZIR.sha &&
    checksum.includes(VAZIR.zip), 'official checksum file mismatch');
  validateManifest(JSON.parse(fs.readFileSync(manifestFile, 'utf8')));
  execFileSync('unzip', ['-tqq', zip], {stdio:'pipe'});
  const entries = execFileSync('unzip', ['-Z1', zip], {encoding:'utf8'}).trim().split('\n');
  validateArchiveNames(entries);
  validateHeader(execFileSync('unzip', ['-p', zip, VAZIR.entrypoint], {encoding:'utf8'}));
  const result = {status:'PASS', version:VAZIR.version, release_tag:'v1.5.0',
    name:VAZIR.zip, zip_sha256:VAZIR.sha, bytes:VAZIR.bytes,
    entrypoint:VAZIR.entrypoint, source:'https://github.com/rezahh107/Vazir/releases/tag/v1.5.0'};
  return result;
}
if (process.argv[1] && import.meta.url === new URL('file://' + path.resolve(process.argv[1])).href) {
  try {
    const dir = process.argv[2];
    assert(dir && path.isAbsolute(dir), 'explicit absolute package path required');
    console.log(JSON.stringify(validateOfficial(dir)));
  } catch (error) {
    console.error(String(error));
    process.exitCode = 1;
  }
}
