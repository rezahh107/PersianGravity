import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';

// Test-only diagnostic. The G013 raw log stays runner-private. The output
// language is a closed enum; no raw browser/user/vendor text can reach Actions.
const profiles=['baseline-fa_IR','baseline-en_US','with-vazir-fa_IR','with-vazir-en_US'];
const reasonCodes=[
  [/missing or WITH_VAZIR-changed native Grid mode|unobserved or WITH_VAZIR-changed native Grid mode/, 'GRID_MODE_DIFFERENCE'],
  [/English native Grid control is not LTR/, 'ENGLISH_GRID_MODE'],
  [/profile database snapshots differ|environment drift|runtime environment drift/, 'PROFILE_ENVIRONMENT_DIFFERENCE'],
  [/unrecognized font family/, 'UNADMITTED_FONT_FAMILY'],
  [/unsafe weight/, 'UNADMITTED_FONT_WEIGHT'],
  [/unexpected field/, 'UNAPPROVED_EVIDENCE_FIELD'],
  [/unsafe text|unsafe DOM|invalid activeElement/, 'UNSAFE_EVIDENCE_VALUE'],
  [/font_network|font evidence|font or error observation|non-200 WOFF2|browser error/, 'FONT_OR_BROWSER_OBSERVATION'],
  [/incorrect|duplicate|missing browser case|18 observations required|unpaired browser case/, 'EVIDENCE_COVERAGE'],
  [/grid status|Grid assessment|native Grid result/, 'GRID_STATUS_INCONSISTENT'],
  [/wrong|profile identity|plugin identity|release identity|settings/, 'IDENTITY_OR_SETTINGS'],
];
export function diagnosticSummary(raw) {
  const input=typeof raw==='string'?raw:'';
  const observations=new Map();
  for(const match of input.matchAll(/^G013_PROFILE_CAPTURED (BASELINE|WITH_VAZIR) (fa_IR|en_US) GRID=(PASS|FAIL|NOT_PROVEN)\r?$/gm)){
    const name=(match[1]==='BASELINE'?'baseline-':'with-vazir-')+match[2];
    if(profiles.includes(name))observations.set(name,match[3]);
  }
  const tokens=[...input.matchAll(/(?:G013_EVIDENCE_REJECTED|G013_PUBLIC_BOUNDARY_REJECTED): ([^\r\n]{0,280})/g)];
  const last=tokens.at(-1);
  const category=!last?'NO_RECOGNIZED_ERROR':
    reasonCodes.find(([regex])=>regex.test(last[1]))?.[1]||
    (input.slice(last.index,last.index+35).includes('PUBLIC_BOUNDARY')?'OTHER_PUBLIC_BOUNDARY':'OTHER_EVIDENCE_BOUNDARY');
  const values=profiles.map(key=>key+'='+(observations.get(key)||'NOT_CAPTURED'));
  return 'G013 safe_diagnostic='+category+' captured_profiles='+observations.size+'/4 '+values.join(' ');
}
if(process.argv[1] && path.resolve(process.argv[1])===fileURLToPath(import.meta.url)){
  let raw='';
  try {
    const target=process.argv[2];
    if(target && path.isAbsolute(target) && fs.statSync(target).isFile() && fs.statSync(target).size<=1000000)
      raw=fs.readFileSync(target,'utf8');
  } catch { /* Unreadable private log is not a reason to publish its path/details. */ }
  console.log(diagnosticSummary(raw));
}
