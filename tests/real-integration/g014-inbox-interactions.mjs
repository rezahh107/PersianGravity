// Bounded browser-only acceptance of native Gravity Flow/AG Grid interaction
// and saved-state ownership. Requires the separate, authentically populated fixture.
// This is the same validation boundary used by the real G014 interaction runner.
export function requireG014RawDueTimestamp(rawDueFilter) {
  const target=typeof rawDueFilter==='number'&&Number.isSafeInteger(rawDueFilter)
    ? String(rawDueFilter) : rawDueFilter;
  if(typeof target!=='string'||!/^[0-9]{10}$/.test(target))
    throw Error('Missing verified raw due-date filter fixture');
  return target;
}

// Gravity Flow 3.1.0's genuine Inbox Quick Filter listens to keyup, not input.
// Playwright fill() does not send keyup; preserve native keyboard semantics.
export async function enterNativeQuickFilter(search, target) {
  await search.click();
  await search.pressSequentially(target);
}

export async function exerciseNativeInboxInteractions(page, rawDueFilter, expectedEntryId) {
  const scope='[data-js="gflow-inbox"]';
  const header=id=>page.locator(`${scope} .ag-header-cell[col-id="${id}"]`).first();
  const rowIds=()=>page.locator(`${scope} .ag-center-cols-container .ag-row`).evaluateAll(nodes=>
    nodes.map(n=>({id:Number(n.getAttribute('row-id')),top:n.getBoundingClientRect().top,
      visible:n.getBoundingClientRect().height>0})).filter(r=>r.visible&&Number.isFinite(r.id))
      .sort((a,b)=>a.top-b.top).map(r=>r.id));
  const gridData=await page.evaluate(()=>{
    const el=document.querySelector('[data-js="gflow-inbox"]');
    const opts=window.gflow_config?.grids?.[el?.dataset.gridId]?.grid_options;
    return {id:el?.dataset.gridId,rows:opts?.rowData?.length,pageSize:opts?.paginationPageSize};
  });
  if(!gridData.id||gridData.rows<25||!(gridData.pageSize>0)||
    gridData.rows<=gridData.pageSize)throw Error('G014 interactive fixture must span native pages: '+JSON.stringify(gridData));
  const report={fixture:gridData};

  const firstBefore=await rowIds();
  // The native vendor marks ID sortable, but AG Grid version-specific CSS class
  // names are not a sorting contract. Assert raw Entry ID order and actual rows.
  const rawIds=await page.evaluate(()=>{
    const host=document.querySelector('[data-js="gflow-inbox"]');
    return window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options?.rowData
      ?.map(entry=>Number(entry.id))||[];
  });
  if(rawIds.some(id=>!Number.isSafeInteger(id))||new Set(rawIds).size!==rawIds.length)
    throw Error('Native sort fixture IDs must be unique integers');
  const expectedOrders={
    ascending:[...rawIds].sort((a,b)=>a-b).slice(0,gridData.pageSize),
    descending:[...rawIds].sort((a,b)=>b-a).slice(0,gridData.pageSize),
  };
  const expectOrder=async (allowed,label)=>{
    const sorted=Object.fromEntries(
      Object.entries(expectedOrders).filter(([key])=>allowed.includes(key)));
    try {
      await page.waitForFunction(orders=>{
        const nodes=[...document.querySelectorAll(
          '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row')];
        const actual=nodes.map(el=>({id:Number(el.getAttribute('row-id')),top:el.getBoundingClientRect().top}))
          .filter(v=>Number.isSafeInteger(v.id)&&Number.isFinite(v.top))
          .sort((a,b)=>a.top-b.top).map(v=>v.id);
        return Object.values(orders).some(expected=>
          JSON.stringify(actual)===JSON.stringify(expected));
      },sorted,{timeout:12000,polling:200});
    }catch(e) {
      throw Error('Native sort '+label+' did not match expected row order: '+
        JSON.stringify({actual:await rowIds(),expected:sorted,error:String(e)}));
    }
    const actual=await rowIds();
    const direction=Object.keys(sorted).find(key=>
      JSON.stringify(sorted[key])===JSON.stringify(actual));
    if(!direction)throw Error('Native sort result changed after readiness');
    return {direction,ids:actual};
  };
  await header('id').click();
  const firstSort=await expectOrder(['ascending','descending'],'first');
  await header('id').click();
  const secondSort=await expectOrder(
    [firstSort.direction==='ascending'?'descending':'ascending'],'reverse');
  report.sort={before:firstBefore,first:firstSort,second:secondSort};
  if(JSON.stringify(firstSort.ids)===JSON.stringify(secondSort.ids))
    throw Error('Native sort did not reverse visible row identity');

  const search=page.locator('[data-js="gflow-inbox-search"]').first();
  await search.waitFor({timeout:10000});
  // Search a real native row ID; no synthetic text renderer or extra bindings.
  // Use the unique raw due-date value from WU008's authentic beta fixture.
  const target=requireG014RawDueTimestamp(rawDueFilter);
  if(!Number.isInteger(Number(expectedEntryId))||Number(expectedEntryId)<1)throw Error('Missing beta fixture Entry ID');
  await enterNativeQuickFilter(search, target);
  await page.waitForFunction(t=>{
    const nodes=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-center-cols-container .ag-row')];
    return nodes.length===1&&Number(nodes[0].getAttribute('row-id'))===Number(t);
  },expectedEntryId,{timeout:12000});
  report.filter={query:target,isolated:await rowIds()};
  await search.fill('');
  await page.waitForFunction(()=>document.querySelectorAll(
    '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row').length>1,null,{timeout:10000});

  const pageBefore=await rowIds();
  const next=page.locator(`${scope} .ag-paging-button[ref="btNext"]`).first();
  if(!(await next.count())||await next.isDisabled())throw Error('Native next-page control absent or disabled');
  await next.click();
  await page.waitForFunction(before=>{
    const cells=[...document.querySelectorAll('[data-js="gflow-inbox"] .ag-center-cols-container .ag-row')];
    return cells.length>0&&!before.includes(Number(cells[0].getAttribute('row-id')));
  },pageBefore,{timeout:12000});
  report.pagination={first:pageBefore,second:await rowIds()};
  const prev=page.locator(`${scope} .ag-paging-button[ref="btPrevious"]`).first();
  await prev.click();
  await page.waitForFunction(id=>Number(document.querySelector(
    '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row')?.getAttribute('row-id'))===id,
    pageBefore[0],{timeout:12000});

  // Vendor Task::column_config_map() locks id (nonresizable/unmovable);
  // exercise two explicitly resizable/movable native columns instead.
  const widths=async()=>header('created_by').evaluate(n=>Math.round(n.getBoundingClientRect().width));
  const beforeWidth=await widths();
  const resize=header('created_by').locator('.ag-header-cell-resize').first();
  await resize.waitFor({timeout:10000});
  // The real Flow 3.1.0 / AG Grid handle is not necessarily hit-testable at
  // its bounding-box centre (observed in the OFF/ag-ltr control). Find an
  // actual native pointer target, not a neighbouring header or loading layer.
  // The expansion axis follows the physical side of that handle, not the
  // WordPress document direction or a hard-coded positive mouse movement.
  const geometry=await page.evaluate(()=>{
    const host=document.querySelector('[data-js="gflow-inbox"]');
    const cell=host?.querySelector('.ag-header-cell[col-id="created_by"]');
    const handle=cell?.querySelector('.ag-header-cell-resize');
    const opts=window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options;
    const definition=opts?.columnDefs?.find(c=>c.field==='created_by');
    if(!cell||!handle||!definition||definition.resizable===false)
      throw Error('Native created_by resize is not admitted by vendor column definitions');
    const h=cell.getBoundingClientRect(),r=handle.getBoundingClientRect();
    if(h.width<=0||r.width<=0||r.height<=0)
      throw Error('Native resize handle has no measurable layout');
    const y=r.top+r.height/2,centre=r.left+r.width/2;
    const targetAt=x=>{
      const target=document.elementFromPoint(x,y);
      return target===handle||handle.contains(target);
    };
    let x=targetAt(centre)?centre:null;
    if(x===null)for(let candidate=r.left+0.5;candidate<r.right;candidate+=0.5){
      if(targetAt(candidate)){x=candidate;break;}
    }
    if(x===null)throw Error('Native resize handle is obscured or not pointer-hit-testable');
    const handleCentre=r.left+r.width/2,headerCentre=h.left+h.width/2;
    if(Math.abs(handleCentre-headerCentre)<h.width/4)
      throw Error('Native resize handle side is ambiguous');
    const physicalExpansion=handleCentre<headerCentre?-1:1;
    return {x,y,physicalExpansion,headerLeft:h.left,headerRight:h.right,
      headerWidth:h.width,handleLeft:r.left,handleRight:r.right,
      gridClass:host.querySelector('.ag-root-wrapper')?.className||null,
      columnDefinition:{field:definition.field,resizable:definition.resizable,
        minWidth:definition.minWidth??null,maxWidth:definition.maxWidth??null}};
  });
  const endX=geometry.x+geometry.physicalExpansion*55;
  await page.mouse.move(geometry.x,geometry.y);
  await page.mouse.down();
  await page.mouse.move(endX,geometry.y,{steps:8});
  await page.mouse.up();
  const afterWidth=await widths();
  const growth=afterWidth-beforeWidth;
  // Require a material native *expansion* with the expected 55px mouse drag.
  // A no-op, min-width clamp, wrong handle or unrelated shrink cannot pass.
  if(growth<35||growth>75)
    throw Error('Native column resize did not expand from a genuine handle: '+
      JSON.stringify({beforeWidth,afterWidth,growth,geometry}));
  report.resize={beforeWidth,afterWidth,growth,geometry,dragEndX:endX};

  const visualColumnOrder=()=>page.locator(`${scope} .ag-header-cell[col-id]`).evaluateAll(nodes=>
    nodes.map(el=>({id:el.getAttribute('col-id'),x:el.getBoundingClientRect().left}))
      .filter(v=>Number.isFinite(v.x)).sort((a,b)=>a.x-b.x).map(v=>v.id));
  const beforeOrder=await visualColumnOrder();
  // A single dragTo() between element centres is not a faithful native AG Grid
  // column move: its default pointer path may never cross an insertion edge.
  // Admit the vendor's movable columns and use a hit-tested header LABEL,
  // then drag through the physical far side of the destination column.
  const dragGeometry=await page.evaluate(()=>{
    const host=document.querySelector('[data-js="gflow-inbox"]');
    const opts=window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options;
    const from=host?.querySelector('.ag-header-cell[col-id="created_by"]');
    const to=host?.querySelector('.ag-header-cell[col-id="workflow_step"]');
    const sourceDefinition=opts?.columnDefs?.find(c=>c.field==='created_by');
    const destinationDefinition=opts?.columnDefs?.find(c=>c.field==='workflow_step');
    if(!from||!to||!sourceDefinition||!destinationDefinition)
      throw Error('Expected native movable column definitions are missing');
    const constraints={
      source:{suppressMovable:sourceDefinition.suppressMovable??null,
        lockPosition:sourceDefinition.lockPosition??null},
      destination:{suppressMovable:destinationDefinition.suppressMovable??null,
        lockPosition:destinationDefinition.lockPosition??null},
      suppressMovableColumns:opts?.suppressMovableColumns??null
    };
    if(opts.suppressMovableColumns===true||sourceDefinition.suppressMovable===true||
      !!sourceDefinition.lockPosition||!!destinationDefinition.lockPosition)
      throw Error('Vendor declares native column moving disallowed: '+JSON.stringify(constraints));
    const sourceRect=from.getBoundingClientRect(),targetRect=to.getBoundingClientRect();
    const label=from.querySelector('.ag-header-cell-label');
    if(!label)throw Error('Native header drag label missing');
    const l=label.getBoundingClientRect();
    if(l.width<=0||sourceRect.width<=0||targetRect.width<=0)
      throw Error('Native drag header has no layout');
    let start=null;
    for(const fraction of [.5,.35,.65,.2,.8]){
      const x=l.left+l.width*fraction,y=l.top+l.height/2;
      const hit=document.elementFromPoint(x,y);
      if(hit&&from.contains(hit)&&!hit.closest('.ag-header-cell-resize')){
        start={x,y,hit:hit.className||hit.tagName};break;
      }
    }
    if(!start)throw Error('Native header drag label is obscured');
    const sign=targetRect.left>sourceRect.left?1:-1;
    const finish={
      x:sign>0?targetRect.right-Math.min(12,targetRect.width/10):
        targetRect.left+Math.min(12,targetRect.width/10),
      y:targetRect.top+targetRect.height/2
    };
    return {start,finish,sign,constraints,
      source:{left:sourceRect.left,right:sourceRect.right,width:sourceRect.width},
      target:{left:targetRect.left,right:targetRect.right,width:targetRect.width}};
  });
  await page.evaluate(()=>{
    window.__g014DragDown=null;
    document.addEventListener('mousedown',event=>{
      const el=event.target;
      window.__g014DragDown={col:el.closest?.('.ag-header-cell')?.getAttribute('col-id')||null,
        tag:el.tagName,cls:String(el.className||'').slice(0,130)};
    },{once:true,capture:true});
  });
  await page.mouse.move(dragGeometry.start.x,dragGeometry.start.y);
  await page.mouse.down();
  await page.mouse.move(dragGeometry.start.x+dragGeometry.sign*28,
    dragGeometry.start.y,{steps:6});
  await page.mouse.move(dragGeometry.finish.x,dragGeometry.finish.y,{steps:22});
  await page.mouse.up();
  await page.waitForTimeout(300);
  const afterOrder=await visualColumnOrder();
  const nativeDown=await page.evaluate(()=>window.__g014DragDown||null);
  if(JSON.stringify(beforeOrder)===JSON.stringify(afterOrder)){
    // Diagnostic control only; keyboard movement does not substitute for the
    // required real mouse drag and can never make this assertion pass.
    await header('created_by').focus();
    await page.keyboard.press('Shift+ArrowRight');
    await page.waitForTimeout(200);
    const afterKeyboard=await visualColumnOrder();
    const detail={beforeOrder,afterOrder,afterKeyboard,nativeDown,dragGeometry};
    console.log('G014_NATIVE_DRAG_FAILURE_DIAGNOSTIC '+JSON.stringify(detail));
    throw Error('Native header drag did not reorder columns: '+JSON.stringify(detail));
  }
  // Reordering can legitimately reflow native column geometry. Persisted
  // state must restore the stable state *after the final user interaction*,
  // not the obsolete snapshot captured before the subsequent drag.
  const postDragWidth=await widths();
  if(postDragWidth-beforeWidth<30)
    throw Error('Native drag discarded previously resized width: '+JSON.stringify(
      {beforeWidth,afterWidth,postDragWidth,beforeOrder,afterOrder}));
  report.drag={before:beforeOrder,after:afterOrder,nativeDown,dragGeometry,
    widthBeforeDrag:afterWidth,widthAfterDrag:postDragWidth};

  // Native saved-state readiness is not established by an early header alone.
  // Observe each lifecycle boundary before accepting or rejecting restoration.
  const stateSnapshot=()=>page.evaluate(()=>{
    const host=document.querySelector('[data-js="gflow-inbox"]');
    const opts=window.gflow_config?.grids?.[host?.dataset.gridId]?.grid_options;
    const root=host?.querySelector('.ag-root-wrapper');
    const viewport=host?.querySelector('.ag-center-cols-viewport');
    const column=opts?.columnDefs?.find(c=>c.field==='created_by');
    let apiState=null;
    if(typeof opts?.api?.getColumnState==='function'){
      try{
        apiState=opts.api.getColumnState().map(c=>({
          colId:c.colId,width:c.width,flex:c.flex,hide:c.hide,pinned:c.pinned
        }));
      }catch(e){apiState={error:String(e).slice(0,130)};}
    }
    return {
      rows:host?.querySelectorAll('.ag-center-cols-container .ag-row').length,
      rootClass:root?.className,rootWidth:root?.getBoundingClientRect().width,
      viewportWidth:viewport?.clientWidth,
      documentClient:document.documentElement.clientWidth,
      headers:[...host?.querySelectorAll('.ag-header-cell[col-id]')||[]].map(n=>({
        id:n.getAttribute('col-id'),width:Math.round(n.getBoundingClientRect().width)})),
      definition:column?{field:column.field,width:column.width,flex:column.flex,
        minWidth:column.minWidth,maxWidth:column.maxWidth,resizable:column.resizable}:null,
      defaultColDef:opts?.defaultColDef?{
        width:opts.defaultColDef.width,flex:opts.defaultColDef.flex,
        minWidth:opts.defaultColDef.minWidth}:null,
      apiState,
      // Keys only, never log potentially sensitive saved Grid values.
      storageKeys:Object.keys(localStorage).filter(k=>/grid|flow|inbox|column/i.test(k)).slice(0,20)
    };
  });
  const beforeReload=await stateSnapshot();
  await page.waitForTimeout(800);
  const preReloadSettled=await stateSnapshot();
  await page.reload({waitUntil:'domcontentloaded'});
  await page.locator(`${scope} .ag-root-wrapper`).first().waitFor({timeout:20000});
  await page.waitForFunction(()=>document.querySelectorAll(
    '[data-js="gflow-inbox"] .ag-header-cell').length>0,null,{timeout:20000});
  await page.waitForFunction(()=>document.querySelectorAll(
    '[data-js="gflow-inbox"] .ag-center-cols-container .ag-row').length>0,
    null,{timeout:18000});
  const reloadedSnapshots=[{phase:'rows_ready',state:await stateSnapshot()}];
  for(const delayMs of [250,500,900]){
    await page.waitForTimeout(delayMs);
    reloadedSnapshots.push({phase:'settle_'+delayMs+'ms',state:await stateSnapshot()});
  }
  const reloadWidth=await widths();
  const reloadOrder=await visualColumnOrder();
  report.reload={width:reloadWidth,order:reloadOrder,beforeReload,preReloadSettled,
    reloadedSnapshots,restored:false};
  if(Math.abs(reloadWidth-postDragWidth)>3||JSON.stringify(reloadOrder)!==JSON.stringify(afterOrder))
    throw Error('Native saved Grid columns not restored after settled reload: '+JSON.stringify(
      {afterWidth,postDragWidth,reloadWidth,afterOrder,reloadOrder,beforeReload,
        preReloadSettled,reloadedSnapshots}));
  report.reload.restored=true;
  return report;
}
