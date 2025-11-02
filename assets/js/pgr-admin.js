
(function($) {
    $(document).on('gform_load_field_settings', function(event, form_id, field_id) {
        // Add custom settings for National ID field
        var field = $('#field_' + form_id + '_' + field_id);
        if (field.data('type') === 'pgr_national_id') {
            // Handle settings like showLocation, showSeparator, forceEnglish, etc.
        }
    });
})(jQuery);
