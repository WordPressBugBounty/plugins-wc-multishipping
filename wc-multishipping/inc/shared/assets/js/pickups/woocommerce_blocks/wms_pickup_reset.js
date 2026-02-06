(function (window, $) {
    'use strict';

    if (typeof $ === 'undefined') {
        return;
    }

    var lastShippingMethod = null;

    function getSelectedShippingMethod() {
        var blockPackage = document.getElementsByClassName('wc-block-components-shipping-rates-control__package')[0];
        if (blockPackage) {
            var checkedOption = blockPackage.getElementsByClassName('wc-block-components-radio-control__option-checked')[0];
            var value = checkedOption && checkedOption.firstChild && checkedOption.firstChild.value;
            if (value) {
                return value;
            }
        }

        var traditionalSelected = document.querySelector('input[name^="shipping_method"]:checked');
        if (traditionalSelected) {
            return traditionalSelected.value;
        }

        return null;
    }

    function getProviderFromMethod(method) {
        if (!method) return null;
        if (method.indexOf('mondial_relay') !== -1) return 'mondial_relay';
        if (method.indexOf('chronopost') !== -1) return 'chronopost';
        if (method.indexOf('ups') !== -1) return 'ups';
        return null;
    }

    function resetPickupUI() {
        var pickupSelected = $('#wms_pickup_selected');
        if (pickupSelected.length) {
            var defaultText;
            // Try localized strings from PHP first, then wp.i18n, then fallback
            if (typeof wms_data !== 'undefined' && wms_data.strings && wms_data.strings.please_select_pickup) {
                defaultText = wms_data.strings.please_select_pickup;
            } else if (typeof WMS !== 'undefined' && WMS.i18n && typeof WMS.i18n.__ === 'function') {
                defaultText = WMS.i18n.__('Please select a pickup point', 'wc-multishipping');
            } else {
                defaultText = 'Please select a pickup point';
            }
            pickupSelected.text(defaultText);
        }

        $('#wms_pickup_point').val('');
        $('#wms_pickup_info').val('');
        $('#wms_ajax_error').hide().empty();
    }

    function clearPickupSession(provider) {
        var wmsNonce = $('#wms_nonce').val();
        var hasWMS = typeof WMS !== 'undefined';
        var ajaxUrl = hasWMS && WMS.ajaxurl ? WMS.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : null);

        if (!provider || !wmsNonce || !ajaxUrl) {
            return;
        }

        $.post(ajaxUrl, {
            action: 'wms_clear_pickup_point',
            pickup_provider: provider,
            wms_nonce: wmsNonce
        }).fail(function (xhr, status, error) {
            console.error('[wms_pickup_reset] clearPickupSession AJAX error', { xhr: xhr, status: status, error: error });
        });
    }

    function handleShippingChange() {
        var selected = getSelectedShippingMethod();
        if (!selected) {
            lastShippingMethod = null;
            return;
        }

        if (lastShippingMethod === null) {
            lastShippingMethod = selected;
            return;
        }

        if (selected === lastShippingMethod) {
            return;
        }

        lastShippingMethod = selected;

        var provider = getProviderFromMethod(selected);

        resetPickupUI();

        if (provider) {
            clearPickupSession(provider);
        }
    }

    $(function () {
        $(document.body)
            .on('updated_shipping_method', handleShippingChange)
            .on('updated_wc_div', handleShippingChange)
            .on('updated_checkout', handleShippingChange);

        $(document.body).on('change', '.wc-block-components-shipping-rates-control__package, input[name^="shipping_method"]', handleShippingChange);

        handleShippingChange();
    });

})(window, jQuery);
