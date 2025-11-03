(function($){
  'use strict';

  function toEnglishDigits(s){
    if(!s) return '';
    var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    var ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    return String(s)
      .replace(/[۰-۹]/g, function(d){ return String(fa.indexOf(d)); })
      .replace(/[٠-٩]/g, function(d){ return String(ar.indexOf(d)); });
  }
  function digitsOnly(s){ return String(s||'').replace(/\D+/g,''); }
  function formatWithSeparator(d){
    if(d.length<=3) return d;
    if(d.length<=9) return d.slice(0,3)+'-'+d.slice(3);
    return d.slice(0,3)+'-'+d.slice(3,9)+'-'+d.slice(9,10);
  }

  function bind($input){
    if ($input.data('pgr-bound')) return;
    $input.data('pgr-bound', true);

    var showSep = $input.data('show-separator') == 1 || $input.data('show-separator') === true;
    var forceEn = $input.data('force-english') == 1 || $input.data('force-english') === true;

    $input.on('input', function(){
      var v = $(this).val();
      if (forceEn) v = toEnglishDigits(v);
      var d = digitsOnly(v).slice(0,10);
      $(this).val(showSep ? formatWithSeparator(d) : d);
    });

    // initial
    $input.trigger('input');
  }

  $(function(){
    $('input[data-field-type="pgr_national_id"]').each(function(){ bind($(this)); });
    $(document).on('gform_post_render', function(e, formId){
      $('#gform_' + formId).find('input[data-field-type="pgr_national_id"]').each(function(){ bind($(this)); });
    });
  });
})(jQuery);
