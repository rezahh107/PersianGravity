// Bounded browser-only acceptance of native Gravity Flow/AG Grid interaction
// and saved-state ownership. Requires the separate, authentically populated fixture.
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
  await header('id').click();
  await page.waitForFunction(()=>!!document.querySelector(
    '[data-js="gflow-inbox"] .ag-header-cell[col-id="id"].ag-header-cell-sorted-asc,'
    +'[data-js="gflow-inbox"] .ag-header-cell[col-id="id"].ag-header-cell-sorted-desc'
  ),null,{timeout:10000});
  const sortFirst=await rowIds();
  await header('id').click();
  await page.waitForFunction(()=>document.querySelector(
    '[data-js="gflow-inbox"] .ag-header-cell[col-id="id"]')?.getAttribute('aria-sort')==='descending',
    null,{timeout:10000}).catch(()=>{}); // Compare actual row order below, not aria implementation detail.
  const sortSecond=await rowIds();
  if(JSON.stringify(sortFirst)===JSON.stringify(sortSecond))throw Error('Native sort did not reverse visible row identity');
  report.sort={before:firstBefore,first:sortFirst,second:sortSecond};

  const search=page.locator('[data-js="gflow-inbox-search"]').first();
  await search.waitFor({timeout:10000});
  // Search a real native row ID; no synthetic text renderer or extra bindings.
  // Use the unique raw due-date value from WU008's authentic beta fixture.
  const target=String(rawDueFilter);
  if(!/^\\d{10}$/.test(target))throw Error('Missing verified raw due-date filter fixture');
  if(!Number.isInteger(Number(expectedEntryId))||Number(expectedEntryId)<1)throw Error('Missing beta fixture Entry ID');
  await search.fill(target);
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
  const rect=await resize.boundingBox();
  if(!rect)throw Error('Native column resize handle has no layout');
  await page.mouse.move(rect.x+rect.width/2,rect.y+rect.height/2);
  await page.mouse.down();
  await page.mouse.move(rect.x+rect.width/2+55,rect.y+rect.height/2,{steps:8});
  await page.mouse.up();
  const afterWidth=await widths();
  if(Math.abs(afterWidth-beforeWidth)<8)throw Error('Native column resize did not change width');
  report.resize={beforeWidth,afterWidth};

  const visualColumnOrder=()=>page.locator(`${scope} .ag-header-cell[col-id]`).evaluateAll(nodes=>
    nodes.map(el=>({id:el.getAttribute('col-id'),x:el.getBoundingClientRect().left}))
      .filter(v=>Number.isFinite(v.x)).sort((a,b)=>a.x-b.x).map(v=>v.id));
  const beforeOrder=await visualColumnOrder();
  await header('created_by').dragTo(header('workflow_step'),{force:true});
  const afterOrder=await visualColumnOrder();
  if(JSON.stringify(beforeOrder)===JSON.stringify(afterOrder))throw Error('Native header drag did not reorder columns');
  report.drag={before:beforeOrder,after:afterOrder};

  await page.reload({waitUntil:'domcontentloaded'});
  await page.locator(`${scope} .ag-root-wrapper`).first().waitFor({timeout:20000});
  await page.waitForFunction(()=>document.querySelectorAll(
    '[data-js="gflow-inbox"] .ag-header-cell').length>0,null,{timeout:20000});
  const reloadWidth=await widths();
  const reloadOrder=await visualColumnOrder();
  if(Math.abs(reloadWidth-afterWidth)>3||JSON.stringify(reloadOrder)!==JSON.stringify(afterOrder))
    throw Error('Native saved Grid columns not restored after reload: '+JSON.stringify(
      {afterWidth,reloadWidth,afterOrder,reloadOrder}));
  report.reload={width:reloadWidth,order:reloadOrder,restored:true};
  return report;
}
