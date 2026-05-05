/**
 * Frontend scripts for Persian Gravity Forms
 * Handles digit normalization and live validation of National ID
 *
 * @package PersianGravityFormsRefactor
 * @since   3.0.0
 */

(function() {
    'use strict';

    // Mapping of Persian and Arabic digits to English
    var persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    var arabicDigits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * Normalize Persian/Arabic digits to English digits
     *
     * @param {string} str Input string
     * @return {string} Normalized string
     */
    function normalizeDigits(str) {
        if (typeof str !== 'string') return str;
        var result = str;
        for (var i = 0; i < 10; i++) {
            var regexPersian = new RegExp(persianDigits[i], 'g');
            var regexArabic  = new RegExp(arabicDigits[i], 'g');
            result = result.replace(regexPersian, String(i)).replace(regexArabic, String(i));
        }
        return result;
    }

    /**
     * Remove all non-digit characters
     *
     * @param {string} str Input string
     * @return {string} Only digits
     */
    function cleanNumeric(str) {
        return (str || '').replace(/\D+/g, '');
    }

    /**
     * Validate Iranian National ID (10 digits, mod 11 checksum)
     *
     * @param {string} nid National ID
     * @return {boolean}
     */
    function isValidNID(nid) {
        var clean = cleanNumeric(normalizeDigits(String(nid || '')));
        if (clean.length !== 10) return false;
        // Reject all identical digits
        if (/^(\d)\1{9}$/.test(clean)) return false;
        var sum = 0;
        for (var i = 0; i < 9; i++) {
            sum += parseInt(clean[i], 10) * (10 - i);
        }
        var rem = sum % 11;
        var calc = (rem < 2) ? rem : 11 - rem;
        return calc === parseInt(clean[9], 10);
    }

    /**
     * Enhance a single National ID input field with live validation and digit normalization
     *
     * @param {HTMLInputElement} el The input element
     */
    function enhanceField(el) {
        if (!el || el._pgrEnhanced) return;
        el._pgrEnhanced = true;

        var forceEnglish = el.getAttribute('data-pgr-force-english') === 'true';
        var liveValidation = el.getAttribute('data-pgr-live-validation') === 'true';

        // Helper to show/hide validation message (optional, could use built-in HTML5 or custom span)
        function setInvalidState(bad) {
            if (bad) {
                el.setAttribute('aria-invalid', 'true');
                // If there is a custom location span, we could use it for messages, but we'll rely on browser validation message.
                // For simplicity, we set custom validity message.
                if (el.validationMessage === '') {
                    el.setCustomValidity('کد ملی وارد شده معتبر نیست');
                }
            } else {
                el.setAttribute('aria-invalid', 'false');
                el.setCustomValidity('');
            }
        }

        // Input event handler for live validation and digit conversion
        el.addEventListener('input', function() {
            var originalValue = el.value;
            var normalized = forceEnglish ? normalizeDigits(originalValue) : originalValue;

            if (forceEnglish && normalized !== originalValue) {
                // Preserve cursor position
                var cursorPos = el.selectionStart;
                el.value = normalized;
                try {
                    el.setSelectionRange(cursorPos, cursorPos);
                } catch (e) {}
            }

            if (liveValidation) {
                var currentValue = el.value;
                var clean = cleanNumeric(currentValue);
                var valid = isValidNID(clean);
                // Only mark invalid if exactly 10 digits and checksum fails
                if (clean.length === 10 && !valid) {
                    setInvalidState(true);
                } else {
                    setInvalidState(false);
                }
            }
        }, { passive: true });
    }

    // Initialize all matching inputs when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            var inputs = document.querySelectorAll('input[data-pgr-force-english], input.pgr_national_id');
            for (var i = 0; i < inputs.length; i++) {
                enhanceField(inputs[i]);
            }
        });
    } else {
        var inputs = document.querySelectorAll('input[data-pgr-force-english], input.pgr_national_id');
        for (var i = 0; i < inputs.length; i++) {
            enhanceField(inputs[i]);
        }
    }
})();
