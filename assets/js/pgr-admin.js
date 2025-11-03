(function($){
  // Apply global defaults when a new field of our type is added.
  $(document).on('gform_field_added', function(e, field){
    if(!field || field.type !== 'pgr_national_id'){ return; }
    var d = window.PGRDefaults || {};
    if(typeof field.forceEnglish   === 'undefined'){ SetFieldProperty('forceEnglish', !!d.forceEnglish); }
    if(typeof field.liveValidation === 'undefined'){ SetFieldProperty('liveValidation', !!d.liveValidation); }
  });

  // Small UX touch: show a hint when loading field settings
  $(document).on('gform_load_field_settings', function(event, field, form){
    if(!field || field.type !== 'pgr_national_id'){ return; }
    var $panel = $('#field_settings');
    if($panel.find('.pgr-nid-hint').length === 0){
      $('<div class="pgr-nid-hint gf_setting gf_setting_info" style="margin:8px 0;">'
        + '<em>National ID expects 10 digits. Dashes are not required and will be ignored.</em>'
        + '</div>').insertAfter($panel.find('.error_message_setting'));
    }
  });
})(jQuery);
