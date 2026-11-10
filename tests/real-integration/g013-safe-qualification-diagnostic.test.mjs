import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
import {diagnosticSummary} from './g013-safe-qualification-diagnostic.mjs';

test('actual full profile captures and the first bounded comparison error are reported safely',()=>{
  const input=[
    'G013_PROFILE_CAPTURED BASELINE fa_IR GRID=FAIL',
    'G013_PROFILE_CAPTURED BASELINE en_US GRID=PASS',
    'G013_PROFILE_CAPTURED WITH_VAZIR fa_IR GRID=FAIL',
    'G013_PROFILE_CAPTURED WITH_VAZIR en_US GRID=PASS',
    'Error: G013_EVIDENCE_REJECTED: missing or WITH_VAZIR-changed native Grid mode fa_IR/gravity-flow/390',
  ].join('\n');
  assert.equal(diagnosticSummary(input),
    'G013 safe_diagnostic=GRID_MODE_DIFFERENCE captured_profiles=4/4 baseline-fa_IR=FAIL baseline-en_US=PASS with-vazir-fa_IR=FAIL with-vazir-en_US=PASS');
});
test('untrusted arbitrary contents cannot escape the closed summary',()=>{
  const privateToken='SYNTHETIC_PRIVATE_CANARY_1234';
  const s=diagnosticSummary('Error: G013_PUBLIC_BOUNDARY_REJECTED: attacker '+privateToken+'\n'+
    'vazir font failure cookie='+privateToken+'\n'+
    'G013_PROFILE_CAPTURED BASELINE fa_IR GRID=PASS\n'+
    'G013_PROFILE_CAPTURED BASELINE fa_IR GRID=FAIL');
  assert.ok(!s.includes(privateToken));
  assert.match(s,/safe_diagnostic=OTHER_PUBLIC_BOUNDARY/);
  assert.match(s,/captured_profiles=1\/4/);
  assert.ok(!s.includes('cookie='));
});
test('missing/unreadable private log remains a sanitized diagnostic',()=>{
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),'g013-safe-'));
  try {
    const exe=fileURLToPath(new URL('./g013-safe-qualification-diagnostic.mjs',import.meta.url));
    const result=spawnSync(process.execPath,[exe,path.join(dir,'missing.log')],{encoding:'utf8'});
    assert.equal(result.status,0,result.stderr);
    assert.match(result.stdout,/safe_diagnostic=NO_RECOGNIZED_ERROR captured_profiles=0\/4/);
    assert.ok(!result.stdout.includes(dir));
  } finally {fs.rmSync(dir,{recursive:true,force:true});}
});
