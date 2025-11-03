(function(){
  function normalizeDigits(str){
    if(typeof str !== 'string') return str;
    var fa = '۰۱۲۳۴۵۶۷۸۹'.split('');
    var ar = '٠١٢٣٤٥٦٧٨٩'.split('');
    for(var i=0;i<10;i++){ str = str.replace(new RegExp(fa[i],'g'), String(i)); }
    for(var j=0;j<10;j++){ str = str.replace(new RegExp(ar[j],'g'), String(j)); }
    return str;
  }
  function cleanNumeric(str){
    return (str||'').replace(/\D+/g,'');
  }
  function isValidNID(nid){
    nid = cleanNumeric(normalizeDigits(String(nid||'')));
    if(nid.length !== 10) return false;
    if(/^(\d)\1{9}$/.test(nid)) return false;
    var sum = 0;
    for(var i=0;i<9;i++){ sum += parseInt(nid[i],10) * (10 - i); }
    var rem = sum % 11;
    var calc = (rem < 2) ? rem : 11 - rem;
    return calc === parseInt(nid[9],10);
  }

  function enhance(el){
    if(!el) return;
    var force = el.getAttribute('data-pgr-force-english') === 'true';
    var live  = el.getAttribute('data-pgr-live-validation') === 'true';
    var hintId = el.getAttribute('aria-describedby');

    function setInvalidState(bad){
      if(bad){
        el.setAttribute('aria-invalid','true');
        if(hintId){
          var hint = document.getElementById(hintId);
          if(hint){ hint.textContent = 'National ID must be 10 digits (checksum validated).'; }
        }
      } else {
        el.removeAttribute('aria-invalid');
      }
    }

    el.addEventListener('input', function(){
      var v = el.value;
      if(force){
        var nv = normalizeDigits(v);
        if(nv !== v){
          var pos = el.selectionStart;
          el.value = nv;
          try{ el.setSelectionRange(pos, pos); }catch(_){}
        }
      }
      if(live){
        var ok = isValidNID(el.value);
        // Only mark invalid when length is 10 and checksum fails; otherwise keep neutral
        setInvalidState( (cleanNumeric(el.value).length === 10) && !ok );
      }
    }, {passive:true});
  }

  document.addEventListener('DOMContentLoaded', function(){
    var inputs = document.querySelectorAll('input[data-pgr-force-english]');
    for(var i=0;i<inputs.length;i++){ enhance(inputs[i]); }
  });
})();
