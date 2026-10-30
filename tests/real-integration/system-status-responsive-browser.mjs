import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const artifact = process.env.WU008_ARTIFACT_DIR;
const manifestFile = process.env.WU008_MANIFEST_PATH;
const password = process.env.WU008_ADMIN_PASSWORD;
const head = process.env.WU008_PGR_SHA;
const locale = process.env.WU008_ADMIN_UI_LOCALE;
const mode = process.env.WU008_ADMIN_UI_MODE;
if (!artifact || !manifestFile || !password || !/^[a-f0-9]{40}$/.test(head || '') ||
    !['fa_IR', 'en_US'].includes(locale) || !['normal', 'disabled'].includes(mode)) {
  throw Error('WU008 authentic admin runtime, exact Head, locale and module state required');
}
const runtime = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
if (runtime.persiangravity_source_commit !== head) throw Error('Installed source Head mismatch');
const dir = path.join(artifact, 'system-status-scroll-evidence');
fs.mkdirSync(dir, {recursive: true});
const output = {head, locale, mode, runtime: {versions: runtime.versions, php: runtime.php_version},
  browser: null, cases: [], negative_control: 'NOT_RUN', status: 'INCOMPLETE'};
const fileKey = mode + '-' + locale;
const browser = await chromium.launch({headless:true});
output.browser = {chromium:browser.version(), device_scale_factor:1, licensed_wordpress:true};
const region = '.pgr-compatibility-status__table-scroll';
const fail = message => {throw Error('SYSTEM_STATUS_SCROLL: '+message)};
const assert = (ok,message)=> {if(!ok) fail(message)};
function getMeasurements() {
  const node = document.querySelector('.pgr-compatibility-status__table-scroll');
  const table = node?.querySelector('table.widefat.striped');
  const box = node?.getBoundingClientRect();
  return {
    viewport_width:innerWidth, document_scroll_width:document.documentElement.scrollWidth,
    document_client_width:document.documentElement.clientWidth,
    region_client_width:node?.clientWidth ?? null, region_scroll_width:node?.scrollWidth ?? null,
    region_scroll_left:node?.scrollLeft ?? null, region_left:box?.left ?? null,
    region_right:box?.right ?? null, overflow_x:node?getComputedStyle(node).overflowX:null,
    direction:node?getComputedStyle(node).direction:null,
    focusable:node?.tabIndex === 0, region_role:node?.getAttribute('role'),
    labelled_by:node?.getAttribute('aria-labelledby'),
    header_count:table?.querySelectorAll('thead th').length ?? 0,
    row_count:table?.querySelectorAll('tbody tr').length ?? 0,
    data_cell_counts:[...table?.querySelectorAll('tbody tr')||[]].map(tr=>tr.querySelectorAll('td').length),
    focus_outline:node ? getComputedStyle(node).outlineStyle : null,
  };
}
let hadFailure=false;
try {
  for (const width of [1280,390,320]) {
    const height=width===1280?900:width===390?844:700;
    const context=await browser.newContext({viewport:{width,height},deviceScaleFactor:1,hasTouch:true});
    const page=await context.newPage();
    const item={width,height,status:'FAILED'};
    try {
      const login=new URL(runtime.login_url);
      const loginResult=await page.goto(login.href,{waitUntil:'domcontentloaded'});
      assert(loginResult?.ok(),'WordPress login page failed');
      await page.locator('#user_login').fill('runtime_admin');
      await page.locator('#user_pass').fill(password);
      await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),
        page.locator('#wp-submit').click()]);
      assert(await page.locator('#wpadminbar').count()===1,'Not authenticated in WordPress');
      const statusURL=new URL('admin.php?page=persian-gravityforms-system-status',runtime.admin_url);
      const response=await page.goto(statusURL.href,{waitUntil:'domcontentloaded'});
      assert(response?.ok()&&!page.url().includes('wp-login.php'),'System Status route failed');
      assert(await page.locator(region).count()===1,'Owned scroll region missing');
      assert(await page.locator('.pgr-compatibility-status table.widefat').count()===1,
        'Semantic diagnostics table missing');
      const originalText=await page.locator('.pgr-compatibility-status table').innerText();
      const initial=await page.evaluate(getMeasurements);
      assert(initial.direction===(locale==='fa_IR'?'rtl':'ltr'),'Incorrect locale direction');
      assert(initial.header_count===5 && initial.row_count>0 &&
        initial.data_cell_counts.every(c=>c===5),'Missing diagnostics columns or rows');
      assert(initial.focusable && initial.region_role==='region' && initial.labelled_by &&
        await page.locator('#'+initial.labelled_by).count()===1,'Unlabelled/unfocusable scroll region');
      assert(initial.document_scroll_width<=width,'Document horizontal overflow at '+width+
        ': '+initial.document_scroll_width);
      assert(initial.overflow_x==='auto','Region does not own overflow');
      if(width<760)assert(initial.region_scroll_width>initial.region_client_width+100,
        'Table not horizontally scrollable');
      item.initial=initial;
      item.screenshot='status-after-'+fileKey+'-'+width+'.jpg';
      await page.screenshot({path:path.join(dir,item.screenshot),fullPage:true,type:'jpeg',quality:85});
      if(width<760){
        const lastColumn=await page.locator(region).evaluate(el=>{
          el.scrollLeft=getComputedStyle(el).direction==='rtl'?-el.scrollWidth:el.scrollWidth;
          const bounds=el.getBoundingClientRect();
          const last=el.querySelector('thead th:last-child')?.getBoundingClientRect();
          const reachable=!!last && last.left>=bounds.left-2 && last.right<=bounds.right+2;
          el.scrollLeft=0;
          return {reachable,last_left:last?.left,last_right:last?.right};
        });
        assert(lastColumn.reachable,'Last diagnostics column cannot be reached within scroll region');
        item.last_column=lastColumn;
        await page.locator(region).scrollIntoViewIfNeeded();
        await page.keyboard.press('Tab'); // Establish keyboard modality before region focus.
        await page.locator(region).focus();
        assert(await page.locator(region).evaluate(el=>document.activeElement===el),
          'Scroll container did not accept focus');
        const key=locale==='fa_IR'?'ArrowLeft':'ArrowRight';
        for(let i=0;i<8;i++)await page.keyboard.press(key);
        await page.waitForTimeout(180);
        const keyboard=await page.evaluate(getMeasurements);
        assert(Math.abs(keyboard.region_scroll_left)>2,'Arrow keys did not scroll '+locale);
        assert(keyboard.focus_outline!=='none','Visible keyboard focus outline missing');
        item.keyboard={key,scroll_left:keyboard.region_scroll_left,outline:keyboard.focus_outline};
        await page.locator(region).evaluate(el=>el.scrollLeft=0);
        const bounds=await page.locator(region).boundingBox();
        assert(bounds && bounds.width>0,'Scroll container not visible');
        await page.mouse.move(bounds.x+bounds.width/2,bounds.y+Math.min(24,bounds.height/2));
        const intendedDelta=locale==='fa_IR'?-290:290;
        await page.mouse.wheel(intendedDelta,0);
        await page.waitForTimeout(140);
        let pointer=await page.evaluate(getMeasurements);
        let pointerDelta=intendedDelta;
        if(Math.abs(pointer.region_scroll_left)<=2){
          // Browser/OS horizontal-wheel sign can differ from document RTL.
          // Test the opposite native wheel direction rather than setting scrollLeft.
          await page.mouse.wheel(-intendedDelta,0);
          await page.waitForTimeout(140);
          pointer=await page.evaluate(getMeasurements);
          pointerDelta=-intendedDelta;
        }
        assert(Math.abs(pointer.region_scroll_left)>2,
          'Pointer wheel did not scroll '+locale+' in either native direction');
        item.pointer={scroll_left:pointer.region_scroll_left, delta_x:pointerDelta};
        await page.locator(region).evaluate(el=>el.scrollLeft=0);
        const touch=await context.newCDPSession(page);
        const x=bounds.x+bounds.width/2, y=bounds.y+Math.min(bounds.height/2,75);
        const trySwipe=async(sign)=>{
          await touch.send('Input.dispatchTouchEvent',{type:'touchStart',
            touchPoints:[{x:x-sign*70,y}]});
          for(let i=1;i<=7;i++)await touch.send('Input.dispatchTouchEvent',{
            type:'touchMove',touchPoints:[{x:x-sign*70+sign*i*20,y}]});
          await touch.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
          await page.waitForTimeout(200);
          return page.evaluate(getMeasurements);
        };
        let swipeSign=locale==='fa_IR'?1:-1;
        let touched=await trySwipe(swipeSign);
        if(Math.abs(touched.region_scroll_left)<=2){
          swipeSign=-swipeSign;
          touched=await trySwipe(swipeSign);
        }
        assert(Math.abs(touched.region_scroll_left)>2,'Touch swipe did not scroll '+locale+
          ' in either native direction');
        item.touch={scroll_left:touched.region_scroll_left, swipe_sign:swipeSign};
      }
      // Synthetic stress stays in this disposable browser DOM; never changes WP storage.
      await page.locator(region).evaluate(el=>{
        const row=el.querySelector('tbody tr');
        const first=row?.querySelectorAll('td')[0]?.querySelector('code');
        const reason=row?.querySelectorAll('td')[2]?.querySelector('code');
        if(!first||!reason)throw Error('Diagnostic identifier/reason markup unavailable');
        first.textContent='gravityflow.compatibility.very_long_technical_identifier_'.repeat(6);
        reason.textContent='LONG_REASON_IDENTIFIER_with_representative_status_details_'.repeat(6);
        const summary=row.querySelectorAll('td')[2]?.firstChild;
        if(summary?.nodeType===Node.TEXT_NODE)
          summary.textContent='Representative compatibility warning: source version and callback mismatch. '.repeat(5);
      });
      const stressed=await page.evaluate(getMeasurements);
      assert(stressed.document_scroll_width<=width,'Long identifiers escaped table region');
      assert(stressed.header_count===5 && stressed.row_count===initial.row_count,
        'Stress changed table structure');
      item.long_values={document_scroll_width:stressed.document_scroll_width,
        region_scroll_width:stressed.region_scroll_width};
      if(width===320){
        item.stress_screenshot='status-stress-'+fileKey+'-320.jpg';
        await page.screenshot({path:path.join(dir,item.stress_screenshot),fullPage:true,type:'jpeg',quality:84});
      }
      await page.reload({waitUntil:'domcontentloaded'});
      assert(await page.locator('.pgr-compatibility-status table').innerText()===originalText,
        'Synthetic stress unexpectedly persisted across reload');
      if(width===320){
        // Real product DOM is intentionally mutated only in the isolated browser.
        const negative=await page.locator(region).evaluate(el=>{
          el.style.overflowX='visible';
          return {document_width:document.documentElement.scrollWidth,
            viewport_width:innerWidth,overflow:getComputedStyle(el).overflowX};
        });
        assert(negative.document_width>width+2 && negative.overflow==='visible',
          'Removing containment did not reproduce the original horizontal overflow');
        item.negative=negative;
        item.negative_screenshot='status-negative-uncontained-'+fileKey+'-320.jpg';
        await page.screenshot({path:path.join(dir,item.negative_screenshot),
          fullPage:true,type:'jpeg',quality:80});
        await page.locator(region).evaluate(el=>el.style.removeProperty('overflow-x'));
        const restored=await page.evaluate(getMeasurements);
        assert(restored.document_scroll_width<=width,'Containment restoration failed');
        output.negative_control='DETECTED_AND_RESTORED';
      }
      item.status='PASS';
    } catch(error){
      hadFailure=true;
      item.error=String(error?.stack||error);
    }finally{
      output.cases.push(item);
      await context.close();
    }
  }
}finally{
  output.status=hadFailure?'INCOMPLETE':'CAPTURED';
  fs.writeFileSync(path.join(dir,'status-'+fileKey+'.json'),JSON.stringify(output,null,2)+'\n');
  await browser.close();
}
if(hadFailure)fail('Authenticated System Status responsive test failed: see status-'+fileKey+'.json');
console.log('SYSTEM_STATUS_SCROLL_PASS '+fileKey+' cases='+output.cases.length);
