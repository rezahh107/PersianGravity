// G013 runner-local evidence contract. Raw captures stay inside the runner.
import fs from 'node:fs';
import path from 'node:path';

export const G013_SHA = '95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5';
export const G013_PROFILES = ['baseline-fa_IR','baseline-en_US','with-vazir-fa_IR','with-vazir-en_US'];
export const G013_SURFACES = ['overview','settings','scanner','system-status','gravity-form','gravity-flow','flow-frontend','gravityview','gravityperks'];
export const G013_WIDTHS = [1280,390];
export const G013_SHOTS = ['overview','gravity-form','gravityview','gravityperks'];
const HEAD = /^[a-f0-9]{40}$/;
const DIGEST = /^[a-f0-9]{64}$/;
const fail = reason => { throw Error('G013_PUBLIC_BOUNDARY_REJECTED: '+reason); };
const ensure = (v,reason) => { if(!v) fail(reason); };
const isObj = v => v !== null && typeof v === 'object' && !Array.isArray(v);
const finite = (n,lo=0,hi=200000) => typeof n==='number' && Number.isFinite(n) && n>=lo && n<=hi;
const array = (v,where,max=100) => { ensure(Array.isArray(v)&&v.length<=max,where+' array');return v; };
const fields = (v,where,allowed,required=[]) => {
  ensure(isObj(v),where+' object');
  // Never echo attacker-supplied property names into public Actions logs.
  for(const k of Object.keys(v))ensure(allowed.includes(k),where+' unexpected field');
  for(const k of required)ensure(Object.hasOwn(v,k),where+' missing '+k);
  return v;
};
const choice = (v,choices,where) => ensure(choices.includes(v),where+' enum');
const text = (v,pattern,where,max=240) => ensure(typeof v==='string'&&v.length>0&&v.length<=max&&
  !/[\x00-\x1f\x7f]/.test(v)&&pattern.test(v),where+' unsafe text');
const asciiText = (v,where,max=240) => text(v,/^[a-zA-Z0-9 _.,'"()\-]+$/,where,max);
// These fields originate from browser.version(), version headers, computed CSS,
// FontFace.weight, or DOM.tagName. Permit their real value languages, NOT free text.
// Browser versions in pinned WU008 are dotted numerics (for example
// Chromium 140.0.7339.16 and Gravity Forms 3.1.1.1). Unknown vendor
// representations must fail closed and be reviewed, never copied verbatim.
const version = (v,where) => text(v,/^[0-9]{1,7}(?:\.[0-9]{1,7}){1,3}$/,where,35);
const numericWeight = v => /^(?:1000|[1-9][0-9]{0,2})(?:\.[0-9]{1,3})?$/.test(v) &&
  Number(v)>=1 && Number(v)<=1000;
const fontWeight = (v,where,{range=false,relative=false}={}) => {
  ensure(typeof v==='string'&&v.length>0&&v.length<=22&&
    !/[^\x20-\x7e]/.test(v),where+' unsafe weight');
  if(v==='normal'||v==='bold'||relative&&['lighter','bolder'].includes(v))return;
  const values=v.split(' ');
  ensure(values.length<= (range?2:1) && values.every(numericWeight) &&
    (values.length!==2 || Number(values[0])<=Number(values[1])),where+' unsafe weight');
};
const tagNames=new Set(['BODY','HTML','INPUT','BUTTON','SELECT','TEXTAREA','A','DIV',
  'IFRAME','OPTION','SUMMARY','SPAN','LABEL','H1','H2','H3','P','LI','IMG','FORM','TD','TH']);
const expectedPinnedGravity=Object.freeze({gravityforms:'3.1.1.1',gravityflow:'3.1.0',
  gravityview:'3.3.4',gravityperks:'2.3.16',gpfileuploadpro:'1.5.13',
  gpadvancedselect:'1.1.21'});
// Computed font-family is a comma-separated CSS stack, not arbitrary free text.
// These tokens were observed in the pinned Gravity baseline or are the pinned
// Vazirmatn/generic/icon families. Unexpected new fonts stop public admission.
const allowedFamilies = new Set([
  'vazirmatn','tahoma','arial','sans-serif','serif','monospace',
  'times new roman','-apple-system','blinkmacsystemfont','segoe ui',
  'roboto','oxygen-sans','ubuntu','cantarell','helvetica neue','inter',
  'gflow-icons-common','dashicons','fontawesome','font awesome 5 free',
  'material icons','system-ui'
]);
const fontFamily = (v,where) => {
  asciiText(v,where,250);
  const members=v.split(',').map(x=>x.trim().replace(/^["']|["']$/g,'').toLowerCase());
  ensure(members.length>0&&members.length<=18&&members.every(x=>allowedFamilies.has(x)),
    where+' unrecognized font family');
};
const allowedSelectors = new Set([
  '.dashicons','.menu-icon','.menu-image','[class^="dashicons-"]',
  '[class*=" dashicons-"]','[class^="fa-"]','[class*=" fa-"]',
  '.material-icons','[data-icon]:before'
]);
const allowedStyleSheets = new Set([
  '/wp-admin/load-styles.php',
  'assets/css/dist/admin-components.min.css','assets/css/dist/admin-css-utilities.min.css',
  'assets/css/dist/admin-icons.min.css','assets/css/dist/admin.min.css',
  'assets/css/dist/common-css-utilities.min.css','assets/css/dist/font-awesome.min.css',
  'assets/css/dist/gravity-forms-common-icons.min.css','assets/css/dist/gravity-forms-orbital-theme.min.css',
  'assets/css/dist/gravity-forms-theme-foundation.min.css','assets/css/dist/gravity-forms-theme-framework.min.css',
  'assets/css/dist/gravity-forms-theme-reset.min.css','assets/css/dist/theme.min.css',
  'gravityview/assets/css/admin-global.css','gravityview/assets/css/font.css',
  'gravityview/templates/css/gv-default-styles-rtl.css','gravityview/templates/css/gv-default-styles.css',
  'gravityview/templates/css/table-view.css','includes/integrations/css/gravity-view-flow-fields.css',
  'includes/locking/css/locking-list.min.css','includes/locking/css/locking.min.css',
  'lib/fancybox/dist/fancybox.css','persian-gravityforms/assets/css/pgr-admin.css',
  'plugins/gravityflow/css/entry-detail.min.css','plugins/gravityflow/css/frontend.min.css',
  'plugins/gravityflow/css/gravityflow-icon.css','plugins/gravityflow/css/inbox.min.css',
  'plugins/gravityflow/css/status.min.css','query-filters/assets/css/date-range-picker.css',
  'vazir-font-wp/assets/css/vazir-fonts.css','vazir-font-wp/assets/css/admin.css'
]);
const numeric = (v,where,lo=0,hi=200000) => ensure(finite(v,lo,hi),where+' number');
const triDir = (v,where) => choice(v,['rtl','ltr',null],where);
const style = (v,where) => {
  if(v===null)return;
  fields(v,where,['family','weight','direction','width','height','overflowX'],['family','weight','direction','width','height','overflowX']);
  fontFamily(v.family,where+'.family');
  fontWeight(v.weight,where+'.weight',{relative:true});
  choice(v.direction,['rtl','ltr'],where+'.direction');
  numeric(v.width,where+'.width');numeric(v.height,where+'.height');
  choice(v.overflowX,['visible','hidden','clip','auto','scroll'],where+'.overflowX');
};
const rect = (v,where) => {
  fields(v,where,['x','y','width','height'],['x','y','width','height']);
  numeric(v.x,where+'.x',-200000);numeric(v.y,where+'.y',-200000);
  numeric(v.width,where+'.width');numeric(v.height,where+'.height');
};
const diagnosis = (v,where,locale) => {
  fields(v,where,['html','body','visible_inbox','grid_mode','grid_nodes','loaded_stylesheets','viewport','document_width','body_width','html_root_ltr_anomaly'],
    ['html','body','visible_inbox','grid_mode','grid_nodes','loaded_stylesheets','viewport','document_width','body_width']);
  fields(v.html,where+'.html',['lang','dir_attr','computed'],['lang','dir_attr','computed']);
  choice(v.html.lang,locale==='fa_IR'?['fa-IR']:['en-US'],where+'.html.lang');
  triDir(v.html.dir_attr,where+'.html.dir_attr');triDir(v.html.computed,where+'.html.computed');
  fields(v.body,where+'.body',['dir_attr','computed'],['dir_attr','computed']);
  triDir(v.body.dir_attr,where+'.body.dir_attr');triDir(v.body.computed,where+'.body.computed');
  choice(v.grid_mode,['rtl','ltr',null],where+'.grid_mode');
  numeric(v.grid_nodes,where+'.grid_nodes',0,30);
  for(const [i,e] of array(v.visible_inbox,where+'.visible_inbox',5).entries()){
    fields(e,where+'.visible_inbox['+i+']',['selector','direction','css_direction','rect','scroll_width','client_width'],
      ['selector','direction','css_direction','rect','scroll_width','client_width']);
    choice(e.selector,['[data-js="gflow-inbox"]','.gravityflow-inbox','.ag-root-wrapper','.wrap','#wpbody-content'],where+'.selector');
    choice(e.direction,['rtl','ltr'],where+'.direction');choice(e.css_direction,['rtl','ltr'],where+'.css_direction');
    rect(e.rect,where+'.rect');numeric(e.scroll_width,where+'.scroll_width');numeric(e.client_width,where+'.client_width');
  }
  for(const [i,s] of array(v.loaded_stylesheets,where+'.loaded_stylesheets',100).entries())
    ensure(allowedStyleSheets.has(s),where+'.loaded_stylesheets['+i+'] unapproved stylesheet');
  fields(v.viewport,where+'.viewport',['width','height'],['width','height']);
  choice(v.viewport.width,G013_WIDTHS,where+'.viewport.width');numeric(v.viewport.height,where+'.viewport.height',1,5000);
  numeric(v.document_width,where+'.document_width',1);numeric(v.body_width,where+'.body_width');
  if(Object.hasOwn(v,'html_root_ltr_anomaly'))ensure(v.html_root_ltr_anomaly===true,where+'.html_root_ltr_anomaly');
};
const known = {
  previous:'PR #96 historical six System Status overflow findings',
  limitations:[
    'Same disposable WordPress host; profiles run sequentially with plugin activation and state restoration.',
    'Not proof of Owner deployed Vazir settings, screen-reader speech, all possible GravityView editor portals or third-party frontends.'
  ],
  interpretation:'Measured observations only; font differences are expected, not automatically defects.',
  historic:'PR #96 historical findings predate PR #97 responsive repair.',
  unproven:['Owner live settings','unreached vendor portals','screen-reader speech','other vendor versions']
};
const equals = (a,b) => JSON.stringify(a)===JSON.stringify(b);
function page(v,where,locale,key) {
  fields(v,where,['name','width','rtl_diagnostic','direction','viewport','document_width','body','headline','label','input','button','icon','faces','active_control','interaction','font_network','page_errors','console_errors','image','grid_mode'],
    ['name','width','direction','viewport','document_width','body','headline','label','input','button','faces','font_network','page_errors','console_errors']);
  choice(v.name,G013_SURFACES,where+'.name');choice(v.width,G013_WIDTHS,where+'.width');
  ensure(v.viewport===v.width,where+'.viewport mismatch');numeric(v.document_width,where+'.document_width',1);
  choice(v.direction,['rtl','ltr'],where+'.direction');
  ensure(isObj(v.body),where+'.body missing');
  for(const name of ['body','headline','label','input','button','icon'])if(Object.hasOwn(v,name))style(v[name],where+'.'+name);
  if(Object.hasOwn(v,'rtl_diagnostic'))diagnosis(v.rtl_diagnostic,where+'.rtl_diagnostic',locale);
  if(Object.hasOwn(v,'active_control')) {
    if(v.active_control!==null)ensure(tagNames.has(v.active_control),where+'.active_control unsafe DOM tag');
  }
  if(Object.hasOwn(v,'interaction') && v.interaction!==null){
    fields(v.interaction,where+'.interaction',['type','tokens_seen','file_preview','selected_value','focus_verified'],['type']);
    if(v.interaction.type==='authentic-gravityview'){
      fields(v.interaction,where+'.interaction',['type','tokens_seen'],['type','tokens_seen']);
      numeric(v.interaction.tokens_seen,where+'.tokens_seen',1,100);
    } else if(v.interaction.type==='authentic-file-upload-and-tom-select'){
      fields(v.interaction,where+'.interaction',['type','file_preview','selected_value','focus_verified'],
        ['type','file_preview','selected_value','focus_verified']);
      ensure(v.interaction.file_preview===true&&v.interaction.focus_verified===true,where+'.interaction flags');
      choice(v.interaction.selected_value,['NONEMPTY','EMPTY'],where+'.selected_value');
    } else fail(where+'.interaction.type');
  }
  for(const [i,face] of array(v.faces,where+'.faces',50).entries()){
    fields(face,where+'.faces['+i+']',['family','weight','status'],['family','weight','status']);
    ensure(typeof face.family==='string'&&/^(?:Vazirmatn|'Vazirmatn'|"Vazirmatn")$/i.test(face.family)&&
      !/[^\x20-\x7e]/.test(face.family),where+'.faces.family');
    fontWeight(face.weight,where+'.faces.weight',{range:true});
    choice(face.status,['unloaded','loading','loaded','error'],where+'.faces.status');
  }
  for(const [i,req] of array(v.font_network,where+'.font_network',100).entries()){
    fields(req,where+'.font_network['+i+']',['url','status'],['url','status']);
    text(req.url,/^vazirmatn-(?:300|400|500|700|900)\.woff2$/i,where+'.font url',125);
    ensure(req.status==='FAILED'||Number.isInteger(req.status)&&req.status>=100&&req.status<=599,where+'.font status');
  }
  // Console/page error messages can include tokens, credentials, or private input.
  // Complete public evidence requires empty arrays. Failed/incomplete runs are quarantined.
  ensure(array(v.page_errors,where+'.page_errors',0).length===0,where+'.page_errors must be empty');
  ensure(array(v.console_errors,where+'.console_errors',0).length===0,where+'.console_errors must be empty');
  const shot=G013_SHOTS.includes(v.name)?key+'-'+v.name+'-'+v.width+'.jpg':null;
  ensure((v.image??null)===shot,where+'.image mapping');
  if(['gravity-flow','flow-frontend'].includes(v.name)){
    fields(v.grid_mode,where+'.grid_mode',['expected','actual','status'],['expected','actual','status']);
    const expected=locale==='fa_IR'?'rtl':'ltr';
    ensure(v.grid_mode.expected===expected,where+'.grid expected');
    choice(v.grid_mode.actual,['rtl','ltr',null],where+'.grid actual');
    const status=v.grid_mode.actual===null?'NOT_PROVEN':v.grid_mode.actual===expected?'PASS':'FAIL';
    ensure(v.grid_mode.status===status,where+'.grid status disguised');
  } else ensure(!Object.hasOwn(v,'grid_mode'),where+'.unexpected grid_mode');
}
export function assertG013PublicJSON(name,doc,head) {
  ensure(HEAD.test(head||''),'exact Head invalid');
  const where=name;
  if(name==='official-release.json'){
    fields(doc,where,['status','version','release_tag','name','zip_sha256','bytes','entrypoint','source'],
      ['status','version','release_tag','zip_sha256','entrypoint']);
    ensure(doc.status==='PASS'&&doc.version==='1.5.0'&&doc.release_tag==='v1.5.0'&&
      doc.zip_sha256===G013_SHA&&doc.entrypoint==='vazir-font-wp/vazir-font-wp.php',where+' official identity');
    if(Object.hasOwn(doc,'name'))ensure(doc.name==='vazir-font-wp-1.5.0.zip',where+'.name');
    if(Object.hasOwn(doc,'bytes'))ensure(doc.bytes===300139,where+'.bytes');
    if(Object.hasOwn(doc,'source'))ensure(doc.source==='https://github.com/rezahh107/Vazir/releases/tag/v1.5.0',where+'.source');
  } else if(name==='vazir-settings.json'){
    fields(doc,where,['version','active','options'],['version','active','options']);
    ensure(doc.version==='1.5.0'&&doc.active===true,where+' activation');
    fields(doc.options,where+'.options',
      ['enable_frontend','enable_admin','enable_gravity_forms','font_weights','exclude_selectors'],
      ['enable_frontend','enable_admin','enable_gravity_forms','font_weights']);
    ensure(['enable_frontend','enable_admin','enable_gravity_forms'].every(k=>doc.options[k]===true),where+' required enabled settings');
    const weights=array(doc.options.font_weights,where+'.font_weights',5);
    ensure(weights.length===5&&new Set(weights).size===5&&['300','400','500','700','900'].every(w=>weights.includes(w)),where+'.font_weights');
    if(Object.hasOwn(doc.options,'exclude_selectors'))for(const [i,v] of array(doc.options.exclude_selectors,where+'.exclude_selectors',50).entries())
      ensure(allowedSelectors.has(v),where+'.exclude_selectors['+i+'] not an official default');
  } else if(G013_PROFILES.some(k=>name===k+'.json')){
    const key=name.slice(0,-5),font=key.startsWith('with-vazir'),locale=key.endsWith('fa_IR')?'fa_IR':'en_US';
    fields(doc,where,['schema','exact_head','persiangravity_version','profile','locale','vazir_version','expected_vazir_sha256','environment','status','grid_acceptance','pages','known_preexisting','limitations'],
      ['schema','exact_head','persiangravity_version','profile','locale','vazir_version','expected_vazir_sha256','environment','status','grid_acceptance','pages']);
    ensure(doc.schema==='g013-vazir-coexistence-v1'&&doc.exact_head===head&&doc.status==='PASS'&&
      doc.profile===(font?'WITH_VAZIR':'BASELINE')&&doc.locale===locale&&
      doc.vazir_version===(font?'1.5.0':null)&&doc.expected_vazir_sha256===(font?G013_SHA:null),where+' identity');
    version(doc.persiangravity_version,where+'.version');
    fields(doc.environment,where+'.environment',['wordpress','php','gravity','chromium','playwright','db_snapshot_sha256'],['wordpress','chromium','db_snapshot_sha256']);
    const snapshotDigest=doc.environment.db_snapshot_sha256;
    ensure(typeof snapshotDigest==='string'&&DIGEST.test(snapshotDigest),where+' DB snapshot');
    for(const k of ['wordpress','php','chromium','playwright'])if(Object.hasOwn(doc.environment,k))
      version(doc.environment[k],where+'.environment.'+k);
    if(Object.hasOwn(doc.environment,'gravity')){
      fields(doc.environment.gravity,where+'.environment.gravity',
        ['gravityforms','gravityflow','gravityview','persiangravity','gravityperks','gpfileuploadpro','gpadvancedselect']);
      for(const [k,v] of Object.entries(doc.environment.gravity)) {
        version(v,where+'.environment.gravity.'+k);
        if(Object.hasOwn(expectedPinnedGravity,k))
          ensure(v===expectedPinnedGravity[k],where+'.environment.gravity.'+k+' not pinned');
      }
      if(Object.hasOwn(doc.environment.gravity,'persiangravity'))
        ensure(doc.environment.gravity.persiangravity===doc.persiangravity_version,
          where+'.environment gravity/PGR version mismatch');
    }
    if(Object.hasOwn(doc,'known_preexisting'))ensure(equals(doc.known_preexisting,[known.previous]),where+'.known_preexisting');
    if(Object.hasOwn(doc,'limitations'))ensure(equals(doc.limitations,known.limitations),where+'.limitations');
    choice(doc.grid_acceptance,['PASS','FAIL','NOT_PROVEN'],where+'.grid_acceptance');
    const pages=array(doc.pages,where+'.pages',18);
    ensure(pages.length===18,where+' page count');
    const seen=new Set();
    for(const p of pages){page(p,where+'.page',locale,key);const id=p.name+'@'+p.width;ensure(!seen.has(id),where+' duplicate page '+id);seen.add(id);}
    ensure(seen.size===18&&G013_SURFACES.every(s=>G013_WIDTHS.every(w=>seen.has(s+'@'+w))),where+' coverage');
    const grids=pages.filter(p=>['gravity-flow','flow-frontend'].includes(p.name));
    const grid=grids.some(p=>p.grid_mode.status==='FAIL')?'FAIL':grids.some(p=>p.grid_mode.status==='NOT_PROVEN')?'NOT_PROVEN':'PASS';
    ensure(doc.grid_acceptance===grid,where+' native Grid assessment');
  } else if(name==='comparison.json'){
    fields(doc,where,['schema','exact_head','status','profiles','vazir_version','vazir_sha256','comparisons','interpretation','grid_acceptance','historic_system_status','unproven'],
      ['schema','exact_head','status','profiles','vazir_version','vazir_sha256','comparisons','interpretation','grid_acceptance','historic_system_status','unproven']);
    ensure(doc.schema==='g013-vazir-compare-v1'&&doc.exact_head===head&&doc.status==='PASS'&&
      doc.vazir_version==='1.5.0'&&doc.vazir_sha256===G013_SHA&&equals(doc.profiles,G013_PROFILES),where+' identity');
    ensure(doc.interpretation===known.interpretation&&doc.historic_system_status===known.historic&&
      equals(doc.unproven,known.unproven),where+' explanatory text');
    fields(doc.grid_acceptance,where+'.grid_acceptance',G013_PROFILES,G013_PROFILES);
    for(const k of G013_PROFILES)choice(doc.grid_acceptance[k],['PASS','FAIL','NOT_PROVEN'],where+'.grid_acceptance.'+k);
    const comparisons=array(doc.comparisons,where+'.comparisons',36);ensure(comparisons.length===36,where+' 36 pairs');
    const seen=new Set();
    for(const p of comparisons){
      fields(p,where+'.pair',['locale','page','width','baseline_document_width','with_vazir_document_width','baseline_family','with_vazir_family','classification'],
        ['locale','page','width','baseline_document_width','with_vazir_document_width','baseline_family','with_vazir_family','classification']);
      choice(p.locale,['fa_IR','en_US'],where+'.locale');choice(p.page,G013_SURFACES,where+'.page');choice(p.width,G013_WIDTHS,where+'.width');
      numeric(p.baseline_document_width,where+'.baseline_document_width',1);
      numeric(p.with_vazir_document_width,where+'.with_vazir_document_width',1);
      fontFamily(p.baseline_family,where+'.baseline_family');
      fontFamily(p.with_vazir_family,where+'.with_vazir_family');
      choice(p.classification,['PRESENT_IN_BOTH','WITH_VAZIR_ONLY_OVERFLOW','PREEXISTING_BASELINE_ONLY','NO_DOCUMENT_OVERFLOW_OBSERVED'],where+'.classification');
      const id=p.locale+'@'+p.page+'@'+p.width;ensure(!seen.has(id),where+' duplicate comparison');seen.add(id);
    }
  } else fail('unknown JSON document '+name);
  return true;
}
const jsonFiles=['official-release.json','vazir-settings.json',
  ...G013_PROFILES.map(x=>x+'.json'),'comparison.json'];
const expectedFiles=new Set(['README.md',...jsonFiles]);
for(const key of G013_PROFILES)for(const width of G013_WIDTHS)for(const shot of G013_SHOTS)
  expectedFiles.add(key+'-'+shot+'-'+width+'.jpg');
function expectedReadme(head) {
  const lines=['# G013 real Vazir coexistence — browser evidence','',
    'Exact PersianGravity Head: '+head,'Official Vazir v1.5.0: '+G013_SHA,
    'Four authenticated profiles, nine surfaces, two viewports: 72 observations / 36 paired comparisons.','',
    'Screenshots (1280px and 390px):',''];
  for(const key of G013_PROFILES)for(const width of G013_WIDTHS)for(const name of G013_SHOTS){
    const image=key+'-'+name+'-'+width+'.jpg';lines.push('- ['+image+']('+image+')');
  }
  lines.push('','Measurements: comparison.json. These files do not prove browser authenticity independently.');
  return lines.join('\n')+'\n';
}
function safeFile(dir,name,min=1,max=4000000) {
  const full=path.join(dir,name);
  ensure(fs.existsSync(full),'missing '+name);
  const st=fs.lstatSync(full);
  ensure(st.isFile()&&!st.isSymbolicLink()&&st.size>=min&&st.size<=max,'unsafe file '+name);
  return full;
}
export function auditG013PublicDirectory(dir,head,{withComparison=true,withFiles=true}={}) {
  ensure(HEAD.test(head||''),'invalid Head');
  ensure(fs.existsSync(dir)&&fs.lstatSync(dir).isDirectory()&&!fs.lstatSync(dir).isSymbolicLink(),'directory unsafe');
  const needed=withComparison?jsonFiles:jsonFiles.filter(x=>x!=='comparison.json');
  const objects=new Map();
  for(const name of needed){
    let value;
    try {value=JSON.parse(fs.readFileSync(safeFile(dir,name,2,1800000),'utf8'));}
    catch {fail('unparseable JSON in approved document '+name);}
    assertG013PublicJSON(name,value,head);objects.set(name,value);
  }
  // All profiles run against one restored WU008 host and one Chromium build.
  // An unexpected differing numeric-looking runtime value is not provenance.
  const first=objects.get(G013_PROFILES[0]+'.json');
  const common=['wordpress','php','gravity','chromium','playwright','db_snapshot_sha256'];
  for(const key of G013_PROFILES.slice(1)){
    const other=objects.get(key+'.json');
    ensure(other.persiangravity_version===first.persiangravity_version,
      'PGR version drift between profiles');
    for(const name of common)ensure(equals(other.environment[name],first.environment[name]),
      'runtime environment drift '+name+' in '+key);
  }
  if(withComparison){
    for(const key of G013_PROFILES)ensure(objects.get('comparison.json').grid_acceptance[key]===objects.get(key+'.json').grid_acceptance,
      'paired grid classification mismatch');
  }
  if(withFiles){
    const entries=fs.readdirSync(dir);
    ensure(entries.length===expectedFiles.size && entries.every(x=>expectedFiles.has(x)),'unknown/missing evidence file');
    const actual=fs.readFileSync(safeFile(dir,'README.md',50,10000),'utf8');
    ensure(actual===expectedReadme(head),'untrusted README');
    for(const f of expectedFiles)if(f.endsWith('.jpg')){
      const bytes=fs.readFileSync(safeFile(dir,f,100,4000000));
      ensure(bytes[0]===0xff&&bytes[1]===0xd8&&bytes.at(-2)===0xff&&bytes.at(-1)===0xd9,'invalid image '+f);
    }
  }
  return objects;
}
// A complete, exact-Head Persian capture can prove a real RTL failure even
// if a later profile or the overall qualification fails for a different reason.
function hasPersianGridFailure(dir,head) {
  return ['baseline-fa_IR.json','with-vazir-fa_IR.json'].some(name=>{
    try {
      const doc=JSON.parse(fs.readFileSync(safeFile(dir,name,2,1800000),'utf8'));
      assertG013PublicJSON(name,doc,head);
      return doc.grid_acceptance==='FAIL';
    } catch {
      return false;
    }
  });
}
if(process.argv[1]&&import.meta.url===new URL('file://'+path.resolve(process.argv[1])).href) {
  const [op,dir,head]=process.argv.slice(2);
  if(op==='rtl-failed'&&hasPersianGridFailure(dir,head))console.log('FAIL');
}
