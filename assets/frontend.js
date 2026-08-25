(function ($) {
    'use strict';
    var validationErrorId = 'djog-billing-phone';

    function normalize(value) {
        var digits = String(value || '').replace(/\D+/g, '');
        if (digits.indexOf('880') === 0) digits = '0' + digits.substring(3);
        if (digits.indexOf('00880') === 0) digits = '0' + digits.substring(5);
        return digits.substring(0, 20);
    }

    function isValid(value) {
        return /^01[3-9][0-9]{8}$/.test(normalize(value));
    }

    function markPhone() {
        var $field = $('#billing_phone, #billing-phone').first();
        if (!$field.length) $field = $('#shipping_phone, #shipping-phone').first();
        if (!$field.length) return;
        var value = normalize($field.val());
        var valid = isValid(value);
        $field.toggleClass('djog-phone-invalid', value.length > 0 && !valid);
        $field.attr('aria-invalid', value.length > 0 && !valid ? 'true' : 'false');
    }

    function syncBlockValidation() {
        if (!window.wp || !wp.data || !window.wc || !wc.wcBlocksData || !wc.wcBlocksData.validationStore) return;
        var field = document.querySelector('#billing-phone, #billing_phone, #shipping-phone, #shipping_phone');
        if (!field) return;
        var value = normalize(field.value);
        var dispatcher = wp.data.dispatch(wc.wcBlocksData.validationStore);
        if (value.length > 0 && !isValid(value)) {
            dispatcher.setValidationErrors({
                [validationErrorId]: {
                    message: (window.djogFrontend && djogFrontend.message) || 'Please enter a valid 11-digit Bangladesh mobile number.',
                    hidden: false
                }
            });
        } else {
            dispatcher.clearValidationError(validationErrorId);
        }
    }

    $(document.body).on('input change', '#billing_phone, #shipping_phone, #billing-phone, #shipping-phone', function () {
        markPhone();
        syncBlockValidation();
    });
    $(document.body).on('updated_checkout', function () {
        markPhone();
        syncBlockValidation();
    });
    $(markPhone);
    $(syncBlockValidation);
}(jQuery));
