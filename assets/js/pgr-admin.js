(function($){
  'use strict';

  function ensurePanel(){
    if ($('#pgr_nid_settings').length) return;
    var html = ''
      + '<div id="pgr_nid_settings" class="pgr-nid-settings" style="margin-top:10px">'
      + '  <h4>تنظیمات کد ملی</h4>'
      + '  <label><input type="checkbox" id="pgr_show_separator"> نمایش جداکننده</label><br/>'
      + '  <label><input type="checkbox" id="pgr_force_english"> اجبار به ارقام انگلیسی</label><br/>'
      + '  <div style="margin-top:8px">'
      + '    <label>پیام خطای غیررقمی <input type="text" id="pgr_not_digit_error" class="fieldwidth-3" /></label><br/>'
      + '    <label>پیام خطای تعداد ارقام <input type="text" id="pgr_qty_digit_error" class="fieldwidth-3" /></label><br/>'
      + '    <label>پیام خطای نامعتبر <input type="text" id="pgr_invalid_error" class="fieldwidth-3" /></label>'
      + '  </div>'
      + '  <div style="margin-top:10px;border-top:1px solid #e5e5e5;padding-top:8px">'
      + '    <h4>حریم خصوصی</h4>'
      + '    <label><input type="checkbox" id="pgr_no_store"> عدم ذخیره مقدار در ورودی</label><br/>'
      + '    <label><input type="checkbox" id="pgr_mask_on_export"> ماسک‌کردن در خروجی/اکسپورت</label><br/>'
      + '    <label><input type="checkbox" id="pgr_hash_value"> ذخیره به‌صورت هش</label>'
      + '  </div>'
      + '</div>';
    $('#field_settings').append(html);
  }

  $(document).on('gform_load_field_settings', function(event, field){
    if (field.type !== 'pgr_national_id') return;
    ensurePanel();
    $('#pgr_show_separator').prop('checked', !!field.showSeparator);
    $('#pgr_force_english').prop('checked', !!field.forceEnglish);
    $('#pgr_not_digit_error').val(field.notDigitError || '');
    $('#pgr_qty_digit_error').val(field.qtyDigitError || '');
    $('#pgr_invalid_error').val(field.isInvalidError || '');
    $('#pgr_no_store').prop('checked', !!field.noStore);
    $('#pgr_mask_on_export').prop('checked', !!field.maskOnExport);
    $('#pgr_hash_value').prop('checked', !!field.hashValue);
  });

  $(document).on('change', '#pgr_show_separator', function(){ SetFieldProperty('showSeparator', $(this).is(':checked')); });
  $(document).on('change', '#pgr_force_english', function(){ SetFieldProperty('forceEnglish', $(this).is(':checked')); });
  $(document).on('input', '#pgr_not_digit_error', function(){ SetFieldProperty('notDigitError', $(this).val()); });
  $(document).on('input', '#pgr_qty_digit_error', function(){ SetFieldProperty('qtyDigitError', $(this).val()); });
  $(document).on('input', '#pgr_invalid_error', function(){ SetFieldProperty('isInvalidError', $(this).val()); });
  $(document).on('change', '#pgr_no_store', function(){ SetFieldProperty('noStore', $(this).is(':checked')); });
  $(document).on('change', '#pgr_mask_on_export', function(){ SetFieldProperty('maskOnExport', $(this).is(':checked')); });
  $(document).on('change', '#pgr_hash_value', function(){ SetFieldProperty('hashValue', $(this).is(':checked')); });
})(jQuery);
