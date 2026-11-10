import fs from 'node:fs';
import path from 'node:path';

export const profiles=['baseline-fa_IR','baseline-en_US','with-vazir-fa_IR','with-vazir-en_US'];
export const names=['perks-admin','fup-crop','gv-block-editor'];
export const widths=[1280,390];
const allowedCropStages=new Set([
  'CROP_PAGE_READY','PORTAL_LOOKUP','IMAGE_PREPARED','MODAL_OPENING',
  'PORTAL_DUPLICATE','PORTAL_INIT_MISSING','PORTAL_CROP_DISABLED',
  'PORTAL_VUE_UNMOUNTED','PORTAL_TARGET_REPLACED','PORTAL_STATE_UNRESOLVED',
  'SIBLING_OWNER_UNINITIALIZED','SIBLING_OWNER_NOT_REQUIRED','SIBLING_OWNER_MOUNT_COUNT','SIBLING_OWNER_TARGET_DETACHED','SIBLING_OWNER_MODAL_ABSENT','SIBLING_OWNER_MODAL_DUPLICATE','SIBLING_OWNER_FIELD_MISMATCH','SIBLING_OWNER_CROPPER_HIDDEN','SIBLING_OWNER_ACTION_UNREACHABLE','PRIMARY_OWNER_UNINITIALIZED','PRIMARY_OWNER_NOT_REQUIRED','PRIMARY_OWNER_MOUNT_COUNT','PRIMARY_OWNER_TARGET_DETACHED','PRIMARY_OWNER_MODAL_ABSENT','PRIMARY_OWNER_MODAL_DUPLICATE','PRIMARY_OWNER_FIELD_MISMATCH','PRIMARY_OWNER_CROPPER_HIDDEN','PRIMARY_OWNER_ACTION_UNREACHABLE',
  'SIBLING_TRANSITION_WAIT','PRIMARY_TRANSITION_WAIT',
  'SIBLING_INPUT_WAIT','SIBLING_SELECT_FILE','SIBLING_MODAL_WAIT','SIBLING_MODAL_VISIBLE','SIBLING_CROPPER_WAIT','SIBLING_ACTIONS_WAIT','SIBLING_OWNER_CHECK','PRIMARY_SELECT_FILE','PRIMARY_MODAL_WAIT','PRIMARY_MODAL_VISIBLE','PRIMARY_CROPPER_WAIT','PRIMARY_ACTIONS_WAIT','PRIMARY_OWNER_CHECK',
  'CROP_MODAL_SHOWN','CROPPER_RENDERED','ACTIONS_VISIBLE','ACTIONS_REACHABLE',
  'ESCAPE_DISMISS','ESCAPE_CLOSED','CROP_RETRY','CROP_SAVE',
  'CROP_SAVE_MODAL_WAIT','UPLOAD_PROGRESS_WAIT','UPLOAD_FILE_WAIT','UPLOAD_FILE_NAME_CHECK','UPLOAD_NATIVE_CHECK',
  'CROP_SAVE_MORE_FILES_PENDING','CROP_SAVE_CANVAS_UNREADY_AT_CLICK','CROP_SAVE_CANVAS_UNREADY_AFTER_CLICK',
  'CROP_SAVE_RUNTIME_UNAVAILABLE','CROP_SAVE_CURRENT_FILE_MISSING','CROP_SAVE_BATCH_INDEX_MISMATCH','CROP_SAVE_EDITOR_REMAINED_OPEN','CROP_SAVE_MODAL_REMAINED_VISIBLE','CROP_SAVE_UNRESOLVED',
  'UPLOAD_COMPLETE','FILE_VISIBLE','REEDIT_OPENING','VALIDATION_SUBMIT',
  'VALIDATION_RERENDERED'
]);
// Closed, test-only classifier. No DOM values or vendor text are returned.
export function classifyPortalProbe(s){
  if(!s||!Number.isInteger(s.selectorCount)||s.selectorCount<0||s.selectorCount>100)
    return 'PORTAL_STATE_UNRESOLVED';
  if(s.selectorCount>1)return 'PORTAL_DUPLICATE';
  if(s.localizedCrop!==true||s.instanceFound!==true)return 'PORTAL_INIT_MISSING';
  if(s.instanceCrop!==true||s.instanceCropRequired!==true)return 'PORTAL_CROP_DISABLED';
  if(s.vueMounted!==true)return 'PORTAL_VUE_UNMOUNTED';
  if(s.selectorCount===0&&Number.isInteger(s.added)&&s.added>0&&
    Number.isInteger(s.removed)&&s.removed>0&&s.mountTarget===true&&
    s.targetConnected===true&&s.targetRetainsId===false)
    return 'PORTAL_TARGET_REPLACED';
  return 'PORTAL_STATE_UNRESOLVED';
}

/**
 * This is the real vendor field's mounted portal, not its disposable ID.
 * Called inside authenticated Chromium. Return fixed-shape booleans/counts
 * only; no DOM, credentials, image bytes, labels, or URLs escape the page.
 */
export function inspectCropSurfaceInPage({formId,fieldId}){
  const instance=window['GPFUP_'+formId+'_'+fieldId];
  const result={initialized:!!instance,required:instance?.enableCrop===true&&
    instance?.cropRequired===true,mountCount:0,targetConnected:false,
    activeCount:0,ownerMatched:false,cropperVisible:false,
    cancelAccessible:false,saveAccessible:false};
  const visible=el=>{
    if(!el||el.nodeType!==1||!el.isConnected)return false;
    const style=getComputedStyle(el),r=el.getBoundingClientRect();
    return style.display!=='none'&&style.visibility!=='hidden'&&
      Number(style.opacity)!==0&&r.width>0&&r.height>0;
  };
  const accessible=el=>{
    if(!visible(el)||el.disabled)return false;
    const r=el.getBoundingClientRect();
    return r.left>=-1&&r.right<=innerWidth+1&&r.top>=-1&&r.bottom<=innerHeight+1;
  };
  const active=[...document.querySelectorAll('.cropper__lightbox')].filter(visible);
  result.activeCount=Math.min(100,active.length);
  const queue=instance?.vm?[instance.vm]:[],seen=new Set(),mounts=[];
  const mountTo='#gpfup-cropper-portal-'+formId+'-'+fieldId;
  while(queue.length&&seen.size<64){
    const child=queue.shift();
    if(!child||seen.has(child))continue;
    seen.add(child);
    if(child.$options?.name==='MountingPortal'&&child.mountTo===mountTo)mounts.push(child);
    for(const nested of (child.$children||[]))queue.push(nested);
  }
  result.mountCount=Math.min(100,mounts.length);
  if(mounts.length!==1)return result;
  // The vendor uses target-slim. PortalTarget.$el can remain a Vue comment
  // even when its transported child transitions into a visible modal.
  // The exact GPFUPCropper instance, not the disposable mount/target root,
  // owns the real final element and carries authoritative field props.
  const crops=[...seen].filter(child=>child.$options?.name==='GPFUPCropper'&&
    String(child.formId)===String(formId)&&String(child.fieldId)===String(fieldId));
  result.ownerComponentCount=Math.min(100,crops.length);
  if(crops.length!==1)return result;
  const finalElement=crops[0].$el;
  result.targetConnected=finalElement?.nodeType===1&&finalElement.isConnected===true;
  if(active.length!==1)return result;
  const modal=active[0];
  result.ownerMatched=result.targetConnected&&finalElement===modal;
  const cropper=modal.querySelector('.vue-advanced-cropper');
  result.cropperVisible=visible(cropper);
  const cancel=modal.querySelectorAll('button.gpfup__cancel');
  const save=modal.querySelectorAll('button.gpfup__crop');
  result.cancelAccessible=cancel.length===1&&accessible(cancel[0]);
  result.saveAccessible=save.length===1&&accessible(save[0]);
  return result;
}

/** Closed functional oracle. Independent unit tests falsify every gate. */
export function classifyCropSurface(o){
  if(!o||o.initialized!==true)return 'CROP_UNINITIALIZED';
  if(o.required!==true)return 'CROP_NOT_REQUIRED';
  if(o.mountCount!==1)return 'CROP_MOUNT_UNAVAILABLE';
  // target-slim legitimately has no element root until the modal is open.
  if(!Number.isInteger(o.activeCount)||o.activeCount===0)return 'CROP_MODAL_ABSENT';
  if(o.activeCount!==1)return 'CROP_MODAL_DUPLICATE';
  // A different field may have the only active modal while this field's
  // target-slim root is still a detached comment. Reject field confusion.
  if(o.ownerMatched!==true)return 'CROP_WRONG_FIELD';
  if(o.targetConnected!==true)return 'CROP_MOUNT_UNAVAILABLE';
  if(o.cropperVisible!==true)return 'CROP_SURFACE_HIDDEN';
  if(o.cancelAccessible!==true||o.saveAccessible!==true)return 'CROP_ACTION_UNREACHABLE';
  return 'READY';
}

/**
 * Diagnostic-only classification of a timed-out Crop/Save. The oracle still
 * fails. Never publish the underlying (private) browser state.
 */
export function classifyCropSaveTimeout(s){
  if(!s||s.instanceFound!==true||s.storeFound!==true)return 'CROP_SAVE_RUNTIME_UNAVAILABLE';
  if(s.editorOpen===true){
    if(s.currentFilePresent!==true)return 'CROP_SAVE_CURRENT_FILE_MISSING';
    if(Number.isInteger(s.addedCount)&&s.addedCount>0&&
      (!Number.isInteger(s.currentIndex)||s.currentIndex<1||s.currentIndex>s.addedCount))
      return 'CROP_SAVE_BATCH_INDEX_MISMATCH';
    if(s.addedCount>1&&s.currentIndex<s.addedCount)
      return 'CROP_SAVE_MORE_FILES_PENDING';
    if(s.preCanvasReady===false)return 'CROP_SAVE_CANVAS_UNREADY_AT_CLICK';
    if(s.canvasReady===false)return 'CROP_SAVE_CANVAS_UNREADY_AFTER_CLICK';
    return 'CROP_SAVE_EDITOR_REMAINED_OPEN';
  }
  if(s.editorOpen===false&&s.modalStillVisible===true)
    return 'CROP_SAVE_MODAL_REMAINED_VISIBLE';
  return 'CROP_SAVE_UNRESOLVED';
}

/** Verify the authoritative Gravity Forms completed-upload hidden field. */
export function classifyNativeUpload(state,expected){
  if(!state||state.present!==true||!Number.isInteger(state.count)||state.count<0)
    return 'UPLOAD_INPUT_INVALID';
  if(expected==='EMPTY')
    return state.count===0?'PASS':'CANCEL_STILL_UPLOADED';
  if(expected==='CONFIRMED')
    return state.count===1&&state.confirmed===true?'PASS':'UPLOAD_UNCONFIRMED';
  return 'UPLOAD_EXPECTATION_INVALID';
}
export const claims={
  'perks-admin':['REAL_PERKS_STANDALONE_SETTINGS','KEYBOARD_TAB_TOGGLE','MANAGE_RESTORED'],
  'fup-crop':['REAL_MULTI_FIELD_MODAL_ISOLATION','CANCEL_BUTTON_DISCARDS',
    'REAL_CROP_MODAL','ESCAPE_CANCEL_DISCARDS',
    'REAL_CROP_SAVED_AND_UPLOADED','RERENDERED_PREVIEW_REEDIT',
    'AUTHENTIC_GF_SERVER_VALIDATION','POST_VALIDATION_FILE_REHYDRATED'],
  'gv-block-editor':['REAL_GV_GUTENBERG_BLOCK',
    'REAL_REACT_SELECT_PORTAL','GUTENBERG_VIEW_SELECTION','REAL_GV_DATEPICKER_POPUP']
};
const must=(yes,message)=>{if(!yes)throw Error('G013_EXTENDED_EVIDENCE_REJECTED: '+message);};
const isObj=x=>x&&typeof x==='object'&&!Array.isArray(x);
const caseKey=c=>c.name+'/'+c.width;
export function validateReport(doc,key,head,{files=false,dir=null}={}){
  must(isObj(doc)&&doc.schema==='g013-extended-browser-v1'&&doc.head===head&&
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
    doc.environment.gravity.gpfileuploadpro==='1.5.13'&&
    doc.environment.gravity.gpadvancedselect==='1.1.21',
    'required exact licensed version identities');
  must(Array.isArray(doc.cases)&&doc.cases.length===6,'missing true executed scenarios');
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
        ['crop_cancel','crop_save','manage_tab','install_tab','react_select','react_portal']
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
  must(seen.size===6&&doc.status==='PASS','expanded run aggregate not PASS');
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
      // Interaction order is part of the real browser evidence: repeated IDs
      // identify different observations and must never overwrite each other.
      must(c.geometry.length===other.geometry.length,
        'mismatched paired geometry count '+locale+'/'+caseKey(c));
      for(let i=0;i<c.geometry.length;i++){
        const old=c.geometry[i],now=other.geometry[i];
        must(old.id===now.id,
          'mismatched paired geometry identity '+locale+'/'+caseKey(c)+'/'+i);
        const previouslyReachable=old.left>=-1&&old.right<=c.width+1;
        const nowClipped=now.left < -1||now.right > c.width+1 ||
          now.scrollWidth>now.clientWidth&&old.scrollWidth<=old.clientWidth;
        must(!(previouslyReachable&&nowClipped),
          'Vazir-only nested control clipping '+locale+'/'+caseKey(c));
      }
    }
  }
  return {profiles:4,scenarios:24};
}
export function safeSummary(dir){
  return profiles.map(key=>{
    let kind='NOT_CAPTURED',d=null;
    try{
      d=JSON.parse(fs.readFileSync(path.join(dir,key+'.json'),'utf8'));
      if(d.schema==='g013-extended-browser-v1'&&Array.isArray(d.cases)){
        const v=d.cases.map(x=>x.status);
        kind=d.status==='PASS'&&v.length===6&&v.every(x=>x==='PASS')?'PASS':
          v.includes('FAIL')?'FAIL':'NOT_PROVEN';
      }
    }catch{}
    const failed=d?.cases?.find?.(c=>isObj(c)&&names.includes(c.name)&&widths.includes(c.width)&&c.status!=='PASS');
    const bounded=failed?'@'+failed.name+'-'+failed.width:'';
    const stage=failed?.name==='fup-crop'&&allowedCropStages.has(failed?.stage)?':'+failed.stage:'';
    return key+'='+kind+bounded+stage;
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
