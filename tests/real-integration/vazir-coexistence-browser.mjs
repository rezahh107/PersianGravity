import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const profile = process.argv[2];
const locale = process.argv[3];
const allowed = ['BASELINE', 'WITH_VAZIR'];
if (!allowed.includes(profile) || !['fa_IR','en_US'].includes(locale)) {
  throw Error('Explicit BASELINE/WITH_VAZIR and fa_IR/en_US required');
}
for (const key of ['WU008_ARTIFACT_DIR','WU008_MANIFEST_PATH','WU008_ADMIN_PASSWORD','WU008_PGR_SHA','WU008_PGR_VERSION']) {
  if (!process.env[key]) throw Error('Missing authentic WU008 environment: ' + key);
}
const manifest = JSON.parse(fs.readFileSync(process.env.WU008_MANIFEST_PATH, 'utf8'));
if (manifest.persiangravity_source_commit !== process.env.WU008_PGR_SHA ||
  manifest.versions?.persiangravity !== process.env.WU008_PGR_VERSION) {
  throw Error('Incorrect installed PersianGravity exact Head or version');
}
const root = path.join(process.env.WU008_ARTIFACT_DIR, 'vazir-coexistence-evidence');
fs.mkdirSync(root, {recursive:true});
const normalized = profile.toLowerCase().replace('_','-');
const prefix = normalized + '-' + locale;
const origin = new URL(manifest.admin_url).origin;
for (const value of [manifest.page_url,manifest.login_url,manifest.gravityflow_inbox_url]) {
  if (new URL(value).origin !== origin) throw Error('Untrusted WU008 application origin');
}
const adminPage = (slug) => new URL('admin.php?page=' + slug, manifest.admin_url).href;
const pages = [
  {name:'overview',url:adminPage('persian-gravityforms'),selector:'.pgr-module-card'},
  {name:'settings',url:adminPage('persian-gravityforms-settings'),selector:'.pgr-panel'},
  {name:'scanner',url:adminPage('persian-gravityforms-scanner-profiles'),selector:'.pgr-admin'},
  {name:'system-status',url:adminPage('persian-gravityforms-system-status'),selector:'.pgr-compatibility-status'},
  {name:'gravity-form',url:manifest.page_url,selector:'.gform_wrapper'},
  {name:'gravity-flow',url:manifest.gravityflow_inbox_url,selector:'body'},
];
const browser = await chromium.launch({headless:true});
const result = {schema:'g013-vazir-coexistence-v1',exact_head:process.env.WU008_PGR_SHA,
  persiangravity_version:process.env.WU008_PGR_VERSION,
  profile,locale,vazir_version:profile==='WITH_VAZIR'?'1.5.0':null,
  expected_vazir_sha256:profile==='WITH_VAZIR'?
    '95fdc3774d6079c34c04331bb8acf980a699e02a215e322742dccbc4109802f5':null,
  environment:{wordpress:'6.8.3',php:manifest.php_version,gravity:manifest.versions,
    chromium:browser.version(),playwright:'1.55.0'},
  status:'INCOMPLETE',pages:[],known_preexisting:['PR #96 historical six System Status overflow findings'],
  limitations:['Same disposable WordPress host; profiles run sequentially with plugin activation and state restoration.',
    'Not proof of Owner deployed Vazir settings, screen-reader speech, all possible GravityView editor portals or third-party frontends.']};
try {
  for (const width of [1280,390]) {
    const context = await browser.newContext({viewport:{width,height:width===1280?900:844},deviceScaleFactor:1});
    const page = await context.newPage();
    const errors = [];
    const fontRequests = [];
    page.on('pageerror', e => errors.push(String(e).slice(0,170)));
    page.on('requestfailed', req => {
      if (/vazirmatn.*\.woff2/i.test(req.url())) fontRequests.push({status:'FAILED',url:'vazirmatn.woff2'});
    });
    page.on('response', res => {
      if (/\/vazir-font-wp\/.*\.woff2(?:\?|$)/i.test(res.url())) {
        fontRequests.push({status:res.status(),url:res.url().split('/').pop().split('?')[0]});
      }
    });
    const response = await page.goto(manifest.login_url,{waitUntil:'domcontentloaded'});
    if (!response?.ok()) throw Error('Real WordPress login page inaccessible');
    await page.locator('#user_login').fill('runtime_admin');
    await page.locator('#user_pass').fill(process.env.WU008_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL(u=>!u.pathname.includes('wp-login.php')),
      page.locator('#wp-submit').click()]);
    for (const spec of pages) {
      const call = await page.goto(spec.url,{waitUntil:'domcontentloaded'});
      if (!call?.ok()) throw Error('Authenticated page unavailable: ' + spec.name + ' ' + call?.status());
      await page.locator(spec.selector).first().waitFor({state:'attached',timeout:12000});
      const data = await page.evaluate(async () => {
        await document.fonts.ready;
        const css = (sel) => {
          const el=document.querySelector(sel);
          if(!el) return null;
          const st=getComputedStyle(el);
          const r=el.getBoundingClientRect();
          return {family:st.fontFamily,weight:st.fontWeight,direction:st.direction,
            width:Math.round(r.width),height:Math.round(r.height),overflowX:st.overflowX};
        };
        const faces=Array.from(document.fonts).filter(x=>/vazirmatn/i.test(x.family))
          .map(x=>({family:x.family,weight:x.weight,status:x.status}));
        const el=document.documentElement;
        return {direction:getComputedStyle(el).direction,viewport:innerWidth,
          document_width:el.scrollWidth,body:css('body'),headline:css('h1'),
          label:css('.gfield_label, .pgr-panel h2, .pgr-module-card h3'),
          input:css('input:not([type=hidden]), textarea, select'),
          button:css('button, input[type=submit]'),icon:css('.dashicons, .dashicons-before'),
          faces,active_control:document.activeElement?.tagName||null};
      });
      if (locale==='fa_IR' && data.direction!=='rtl' && spec.name!=='gravity-form') {
        throw Error('Expected authentic RTL for ' + spec.name);
      }
      const observed = {name:spec.name,width, ...data};
      if (spec.name==='overview' || spec.name==='gravity-form') {
        const image=prefix + '-' + spec.name + '-' + width + '.jpg';
        await page.screenshot({path:path.join(root,image),type:'jpeg',quality:76,fullPage:true});
        observed.image=image;
      }
      result.pages.push(observed);
    }
    result.pages[result.pages.length-1].font_network=fontRequests;
    result.pages[result.pages.length-1].page_errors=errors;
    await context.close();
  }
  const loaded=result.pages.some(p=>p.faces?.some(x=>x.status==='loaded'));
  const delivered=result.pages.some(p=>p.font_network?.some(x=>x.status===200));
  const fontUsed=result.pages.some(p=>[p.body,p.headline,p.label,p.input,p.button].some(x=>x && /vazirmatn/i.test(x.family)));
  if (profile==='WITH_VAZIR' && locale==='fa_IR' && (!loaded || !delivered || !fontUsed)) {
    throw Error('Real Vazirmatn network, FontFace or computed typography was not observed');
  }
  if (profile==='BASELINE' && (loaded || delivered || fontUsed)) {
    throw Error('BASELINE is contaminated by Vazir typography');
  }
  if (result.pages.length!==12) throw Error('Incomplete actual browser case matrix');
  result.status='PASS';
  console.log('G013_PROFILE_PASS '+profile+' '+locale);
} catch(error) {
  result.status='FAIL';
  result.error=String(error).slice(0,450);
  throw error;
} finally {
  fs.writeFileSync(path.join(root,prefix+'.json'),JSON.stringify(result,null,2)+'\n');
  await browser.close();
}
