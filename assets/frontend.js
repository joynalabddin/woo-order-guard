(function ($) {
    'use strict';
    function normalize(value) {
        var digits = String(value || '').replace(/\D+/g, '');
        if (digits.indexOf('880') === 0) digits = '0' + digits.substring(3);
        if (digits.indexOf('00880') === 0) digits = '0' + digits.substring(5);
        return digits.substring(0, 20);
    }
    function markPhone() {
        var $field = $('#billing_phone');
        if (!$field.length) $field = $('#shipping_phone');
        if (!$field.length) return;
        var value = normalize($field.val());
        var valid = /^01[3-9][0-9]{8}$/.test(value);
        $field.toggleClass('djog-phone-invalid', value.length > 0 && !valid);
        $field.attr('aria-invalid', value.length > 0 && !valid ? 'true' : 'false');
    }
    $(document.body).on('input change', '#billing_phone, #shipping_phone', markPhone);
    $(document.body).on('updated_checkout', markPhone);
    $(markPhone);
}(jQuery));
