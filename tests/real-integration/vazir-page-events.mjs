// Per-navigation evidence must not outlive the actual page being measured.
export function observePageEvents(page) {
  const errors=[],consoleErrors=[],fonts=[];
  const onError=error=>errors.push(String(error).slice(0,170));
  const onConsole=msg=>{
    if(msg.type()==='error')consoleErrors.push(String(msg.text()).slice(0,170));
  };
  const onFailed=request=>{
    if(/vazirmatn.*\.woff2/i.test(request.url()))
      fonts.push({status:'FAILED',url:request.url().split('/').pop().split('?')[0]});
  };
  const onResponse=response=>{
    if(/\/vazir-font-wp\/.*\.woff2(?:\?|$)/i.test(response.url()))
      fonts.push({status:response.status(),url:response.url().split('/').pop().split('?')[0]});
  };
  page.on('pageerror',onError);
  page.on('console',onConsole);
  page.on('requestfailed',onFailed);
  page.on('response',onResponse);
  return {
    snapshot:()=>({font_network:fonts.slice(),page_errors:errors.slice(),console_errors:consoleErrors.slice()}),
    stop:()=>{
      page.off('pageerror',onError);
      page.off('console',onConsole);
      page.off('requestfailed',onFailed);
      page.off('response',onResponse);
    },
  };
}
