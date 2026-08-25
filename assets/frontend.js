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

    function getPhoneField() {
        return document.querySelector('#billing-phone, #billing_phone, #shipping-phone, #shipping_phone');
    }

    function getMessage() {
        return (window.djogFrontend && djogFrontend.message) || 'Please enter a valid 11-digit Bangladesh mobile number.';
    }

    function markPhone(field, value) {
        if (!field) return;
        var invalid = value.length > 0 && !isValid(value);
        field.classList.toggle('djog-phone-invalid', invalid);
        field.setAttribute('aria-invalid', invalid ? 'true' : 'false');
    }

    function visibleAlert(field, invalid) {
        var existing = document.getElementById('djog-block-error');
        if (!invalid) {
            if (existing) existing.remove();
            return;
        }
        if (!existing) {
            existing = document.createElement('div');
            existing.id = 'djog-block-error';
            existing.className = 'djog-checkout-error';
            existing.setAttribute('role', 'alert');
            var form = field && field.closest('form, .wc-block-checkout, .wc-block-components-checkout-place-order');
            var target = form || document.querySelector('.wc-block-checkout__form') || document.body;
            target.insertBefore(existing, target.firstChild);
        }
        existing.innerHTML = '<span class="djog-checkout-error__icon" aria-hidden="true">🛡</span>' + getMessage();
    }

    function syncBlockValidation() {
        var field = getPhoneField();
        if (!field) return;
        var value = normalize(field.value);
        var invalid = value.length > 0 && !isValid(value);
        markPhone(field, value);
        visibleAlert(field, invalid);

        if (!window.wp || !wp.data || !window.wc || !wc.wcBlocksData || !wc.wcBlocksData.validationStore) return;
        var dispatcher = wp.data.dispatch(wc.wcBlocksData.validationStore);
        if (invalid) {
            dispatcher.setValidationErrors({
                [validationErrorId]: { message: getMessage(), hidden: false }
            });
        } else {
            dispatcher.clearValidationError(validationErrorId);
        }
    }

    $(document.body).on('input change', '#billing_phone, #shipping_phone, #billing-phone, #shipping-phone', syncBlockValidation);
    $(document.body).on('updated_checkout', syncBlockValidation);
    $(document.body).on('click', 'button, .wc-block-components-checkout-place-order-button', function () {
        window.setTimeout(syncBlockValidation, 0);
    });
    $(syncBlockValidation);
}(jQuery));
