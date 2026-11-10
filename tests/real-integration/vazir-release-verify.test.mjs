import { test } from 'node:test';
import assert from 'node:assert/strict';
import { VAZIR, validateHeader, validateArchiveNames, validateManifest, validateOfficial } from './vazir-release-verify.mjs';
const fonts=['300','400','500','700','900'].map(x=>'vazir-font-wp/assets/fonts/vazirmatn-'+x+'.woff2');
const list=[VAZIR.entrypoint,...fonts,'vazir-font-wp/assets/fonts/OFL.txt'];
const manifest={schema_version:1,mode:'publish',plugin_version:'1.5.0',
  plugin_entrypoint:VAZIR.entrypoint,vazirmatn_release:'v33.003',
  zip_filename:VAZIR.zip,zip_bytes:VAZIR.bytes,sha256:VAZIR.sha,
  validation:'PASS',clean_install_smoke:'PASS',publication:'PUBLISHED_AND_VERIFIED',
  source_sha:'a'.repeat(40)};
test('correct release fixture contracts accepted',()=>{
  assert.doesNotThrow(()=>validateHeader(' * Version: 1.5.0\n'));
  assert.doesNotThrow(()=>validateArchiveNames(list));
  assert.doesNotThrow(()=>validateManifest(manifest));
});
test('tampered or missing official artifact fails before installation',()=>{
  assert.throws(()=>validateOfficial('/tmp/g013-nonexistent-vazir-fixture'));
  assert.throws(()=>validateManifest({...manifest,sha256:'0'.repeat(64)}));
  assert.throws(()=>validateManifest({...manifest,publication:'QUALIFIED_NOT_PUBLISHED'}));
  assert.throws(()=>validateManifest({...manifest,plugin_version:'1.5.1'}));
  assert.throws(()=>validateManifest({...manifest,zip_bytes:123}));
});
test('malformed archive structure and wrong installed version are rejected',()=>{
  for(const filename of ['../vazir-font-wp/vazir-font-wp.php',
    'vazir-font-wp/../../bad.txt','evil-archive/vazir-font-wp.php','vazir-font-wp/evil.zip']) {
    assert.throws(()=>validateArchiveNames([...list,filename]));
  }
  assert.throws(()=>validateArchiveNames([VAZIR.entrypoint]));
  assert.throws(()=>validateArchiveNames(fonts));
  assert.throws(()=>validateHeader(' * Version: 1.4.0\n'));
});
