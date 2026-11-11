import fs from 'node:fs';
import path from 'node:path';

export const profiles=['baseline-fa_IR','baseline-en_US','with-vazir-fa_IR','with-vazir-en_US'];
export const names=['perks-admin','gv-block-editor'];
export const widths=[1280,390];
const allowedEditorStages=new Set(["EDITOR_NAVIGATE","EDITOR_BLOCK_WAIT","EDITOR_BLOCK_FOUND","EDITOR_BLOCK_SELECT","EDITOR_CANVAS_LOCATE","EDITOR_CANVAS_CLICK","EDITOR_LIST_OPEN","EDITOR_LIST_ITEM","EDITOR_LIST_CLICK","EDITOR_BLOCK_SELECTED","EDITOR_SIDEBAR_OPEN","EDITOR_SELECT_LOOKUP","EDITOR_SELECT_FOCUS","EDITOR_DROPDOWN_WAIT","EDITOR_OPTION_WAIT","EDITOR_VIEW_COMMIT","EDITOR_DATEPICKER_LOOKUP","EDITOR_DATEPICKER_CLICK","EDITOR_DATEPICKER_CLOSE","EDITOR_COMPLETE"]);
export const claims={
  'perks-admin':['REAL_PERKS_STANDALONE_SETTINGS','KEYBOARD_TAB_TOGGLE','MANAGE_RESTORED'],
  'gv-block-editor':['REAL_GV_GUTENBERG_BLOCK',
    'REAL_REACT_SELECT_PORTAL','GUTENBERG_VIEW_SELECTION','REAL_GV_DATEPICKER_POPUP']
};
const must=(yes,message)=>{if(!yes)throw Error('G013_EXTENDED_EVIDENCE_REJECTED: '+message);};
const isObj=x=>x&&typeof x==='object'&&!Array.isArray(x);
const caseKey=c=>c.name+'/'+c.width;
export function validateReport(doc,key,head,{files=false,dir=null}={}){
  must(isObj(doc)&&doc.schema==='g013-extended-browser-v2'&&doc.head===head&&
    doc.profile===(key.startsWith('baseline-')?'BASELINE':'WITH_VAZIR')&&
    doc.locale===(key.endsWith('fa_IR')?'fa_IR':'en_US'),'profile identity '+key);
  must(isObj(doc.environment)&&doc.environment.wordpress==='6.8.3'&&
    doc.environment.playwright==='1.55.0'&&
    /^[a-f0-9]{64}$/.test(doc.environment.snapshot||''),'runtime snapshot identity');
  must(isObj(doc.environment.gravity)&&
    doc.environment.gravity.gravityforms==='3.1.1.1'&&
    doc.environment.gravity.gravityflow==='3.1.0'&&
    doc.environment.gravity.gravityview==='3.3.4'&&
    doc.environment.gravity.gravityperks==='2.3.16'&&
    doc.environment.gravity.gpadvancedselect==='1.1.21',
    'required exact licensed version identities');
  must(Array.isArray(doc.cases)&&doc.cases.length===4,'missing true executed scenarios');
  const seen=new Set();
  for(const c of doc.cases){
    must(isObj(c)&&names.includes(c.name)&&widths.includes(c.width),'unknown scenario');
    const k=caseKey(c);
    must(!seen.has(k),'duplicate scenario '+k);
    seen.add(k);
    must(c.status==='PASS','scenario did not genuinely pass '+key+'/'+k);
    must(Array.isArray(c.observed)&&claims[c.name].every(x=>c.observed.includes(x)),
      'missing authentic interaction proof '+key+'/'+k);
    must(isObj(c.font)&&Number.isFinite(c.font.documentWidth)&&c.font.documentWidth>=1&&c.font.documentWidth<=200000&&
      typeof c.font.loadedFace==='boolean'&&typeof c.font.hasVazir==='boolean',
      'missing measured font '+key+'/'+k);
    if(key.startsWith('baseline-'))must(!c.font.loadedFace&&!c.font.hasVazir,
      'contaminated baseline font '+key+'/'+k);
    if(key.startsWith('with-vazir-')&&key.endsWith('fa_IR'))
      must(c.font.loadedFace&&c.font.hasVazir,
        'missing authentic WITH_VAZIR Persian font '+key+'/'+k);
    must(Array.isArray(c.geometry)&&c.geometry.length>=2,
      'missing nested geometry '+key+'/'+k);
    for(const g of c.geometry){
      must(isObj(g)&&typeof g.id==='string'&&
        ['manage_tab','install_tab','react_select','react_portal']
          .includes(g.id),'unexpected geometry id');
      for(const num of ['left','right','width','height','viewport','scrollWidth','clientWidth'])
        must(Number.isFinite(g[num]),'missing numeric geometry');
      must(g.viewport===c.width&&g.width>0&&g.height>0,
        'wrong real owning viewport geometry');
      must(g.left>=-1&&g.right<=c.width+1,'inaccessible actual control');
      must(g.scrollWidth>=0&&g.clientWidth>=0,'broken nested dimensions');
    }
    const file=key+'-'+c.name+'-'+c.width+'.jpg';
    must(c.screenshot===file,'untrusted image path '+k);
    if(files){
      const bytes=fs.readFileSync(path.join(dir,file));
      must(bytes.length>100&&bytes[0]===0xff&&bytes[1]===0xd8&&
        bytes.at(-2)===0xff&&bytes.at(-1)===0xd9,'corrupt genuine screenshot');
    }
  }
  must(seen.size===4&&doc.status==='PASS','expanded run aggregate not PASS');
}
export function validateAll(dir,head,{files=true}={}){
  must(/^[a-f0-9]{40}$/.test(head),'missing source Head');
  const data=new Map();
  for(const key of profiles){
    const file=path.join(dir,key+'.json');
    must(fs.existsSync(file),'missing required browser profile '+key);
    const doc=JSON.parse(fs.readFileSync(file,'utf8'));
    validateReport(doc,key,head,{files,dir});
    data.set(key,doc);
  }
  const snapshots=new Set([...data.values()].map(x=>x.environment.snapshot));
  must(snapshots.size===1,'different BASELINE/WITH_VAZIR DB identity');
  // Independently recompute every actual nested position, never accept a
  // producer's own descriptive PASS without checking the paired metrics.
  for(const locale of ['fa_IR','en_US']){
    const b=data.get('baseline-'+locale),v=data.get('with-vazir-'+locale);
    for(const c of b.cases){
      const other=v.cases.find(x=>caseKey(x)===caseKey(c));
      must(other,'missing paired browser scene');
      must(!(other.font.documentWidth>c.width&&c.font.documentWidth<=c.width),
        'WITH_VAZIR-only document overflow '+locale+'/'+caseKey(c));
      const o=new Map(other.geometry.map(x=>[x.id,x]));
      for(const old of c.geometry){
        const now=o.get(old.id);
        must(now,'missing paired nested control');
        const previouslyReachable=old.left>=-1&&old.right<=c.width+1;
        const nowClipped=now.left < -1||now.right > c.width+1 ||
          now.scrollWidth>now.clientWidth&&old.scrollWidth<=old.clientWidth;
        must(!(previouslyReachable&&nowClipped),
          'Vazir-only nested control clipping '+locale+'/'+caseKey(c));
      }
    }
  }
  return {profiles:4,scenarios:16};
}
// No raw DOM, IDs or free-form strings leave the private runner. Only the
// fixed diagnostic schema and bounded integer/enum values may reach Actions.
function safeSelectionDiagnostic(value){
  if(!isObj(value)||value.schema!=='g013-gutenberg-selection-v1')return '';
  const count=x=>Number.isSafeInteger(x)&&x>=0?String(Math.min(x,20))+(x>20?'+':''):'?';
  const cat=(x,allowed)=>allowed.includes(x)?x:'?';
  const sel=x=>cat(x,['TARGET','OTHER','NONE','UNAVAILABLE']);
  const click=x=>cat(x,['NOT_ATTEMPTED','COMPLETED','TIMEOUT','ERROR']);
  return '[blocks='+count(value.rootCount)+'/'+count(value.targetCount)+
    ',ready='+cat(value.ready,['YES','NO','UNKNOWN'])+
    ',editor='+cat(value.postType,['PAGE','OTHER','UNKNOWN'])+
    ',id='+String(value.clientIdValidated===true?'VALID':'UNKNOWN')+
    ',frames='+count(value.candidateFrames)+
    ',canvas='+count(value.canvasMatches)+'/'+count(value.canvasVisible)+'/'+count(value.canvasPointerHit)+
    ',canvasClick='+click(value.canvasClick)+
    ',list='+String(value.listVisible===true?'VISIBLE':'NOT_VISIBLE')+
    ',items='+count(value.listItems)+
    ',roles='+count(value.listRoleTreeitem)+'/'+count(value.listRoleButton)+'/'+count(value.listRoleGroup)+
    ',classItems='+count(value.listClassItems)+
    ',identity='+count(value.listExactTargetMatches)+'/'+count(value.listTargetTreeitems)+
    ',selection='+sel(value.selectionBefore)+'/'+sel(value.selectionAfterCanvas)+'/'+sel(value.selectionAfterList)+
    ',listClick='+click(value.listClick)+']';
}
export function safeSummary(dir){
  return profiles.map(key=>{
    let kind='NOT_CAPTURED',d=null;
    try{
      d=JSON.parse(fs.readFileSync(path.join(dir,key+'.json'),'utf8'));
      if(d.schema==='g013-extended-browser-v2'&&Array.isArray(d.cases)){
        const v=d.cases.map(x=>x.status);
        kind=d.status==='PASS'&&v.length===4&&v.every(x=>x==='PASS')?'PASS':
          v.includes('FAIL')?'FAIL':'NOT_PROVEN';
      }
    }catch{}
    const failed=d?.cases?.find?.(c=>isObj(c)&&names.includes(c.name)&&widths.includes(c.width)&&c.status!=='PASS');
    const bounded=failed?'@'+failed.name+'-'+failed.width:'';
    const stage=failed?.name==='gv-block-editor'&&allowedEditorStages.has(failed?.stage)?':'+failed.stage:'';
    const diagnostic=failed?.name==='gv-block-editor'?safeSelectionDiagnostic(failed.selectionDiagnostic):'';
    return key+'='+kind+bounded+stage+diagnostic;
  }).join(' ');
}
if(import.meta.url===new URL('file://'+process.argv[1]).href){
  const action=process.argv[2],dir=process.argv[3],head=process.argv[4];
  if(action==='safe'){
    console.log('G013 expanded '+safeSummary(dir));
  }else if(action==='check'){
    const result=validateAll(dir,head);
    console.log('G013_EXPANDED_INDEPENDENT_GATE_PASS exact Head '+result.scenarios+' authentic cases');
  }else process.exitCode=2;
}
