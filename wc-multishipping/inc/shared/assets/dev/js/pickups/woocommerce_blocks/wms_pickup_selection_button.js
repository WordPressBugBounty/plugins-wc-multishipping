/**
 * WMS Pickup Selection Button Handler
 * Manages pickup widget initialization for WooCommerce Blocks
 */
(function(window, $) {
    'use strict';

    // Wait for dependencies
    if (typeof $ === 'undefined') {
        console.error('WMS: jQuery is not loaded');
        return;
    }

    /**
     * Check if a widget function is available
     */
    function isWidgetAvailable(widgetName) {
        return typeof window[widgetName] === 'function';
    }

    /**
     * Wait for a widget to be available with timeout
     */
    function waitForWidget(widgetName, callback, timeout = 5000) {
        const startTime = Date.now();
        const checkInterval = setInterval(function() {
            if (isWidgetAvailable(widgetName)) {
                clearInterval(checkInterval);
                callback();
            } else if (Date.now() - startTime > timeout) {
                clearInterval(checkInterval);
                console.error('WMS: Timeout waiting for ' + widgetName);
            }
        }, 100);
    }

    /**
     * Initialize event listeners
     */
    function init() {
        $(document.body).on('updated_shipping_method', function () {
            set_wms_popup_class();
        }).on('updated_wc_div', function () {
            set_wms_popup_class();
        }).on('updated_checkout', function () {
            set_wms_popup_class();
        });

        $(document).ready(function () {
            set_wms_popup_class();
        });

        $(document.body).on('change', '.wc-block-components-shipping-rates-control__package', function () {
            set_wms_popup_class();
        });
    }

    /**
     * Set up pickup modal based on selected shipping method
     */
    function set_wms_popup_class() {
    let wms_buttons = document.getElementsByClassName('wms_pickup_selection_button');
    if (wms_buttons.length === 0) return;

    for (let wms_button of wms_buttons) {

        let selected_shipping_method = document.getElementsByClassName('wc-block-components-shipping-rates-control__package')[0]?.getElementsByClassName('wc-block-components-radio-control__option-checked')[0]?.firstChild?.value;
        if (undefined == selected_shipping_method) return;


        let shipping_provider_modal_class = '';
        if (-1 != selected_shipping_method.indexOf("mondial_relay")) {
            jQuery('#wms_shipping_provider').val('mondial_relay');
            shipping_provider_modal_class = wms_button.getAttribute('mondial_relay_modal_id');
        } else if (-1 != selected_shipping_method.indexOf("chronopost")) {
            jQuery('#wms_shipping_provider').val('chronopost');
            shipping_provider_modal_class = wms_button.getAttribute('chronopost_modal_id');
        } else if (-1 != selected_shipping_method.indexOf("ups")) {
            jQuery('#wms_shipping_provider').val('ups');
            shipping_provider_modal_class = wms_button.getAttribute('ups_modal_id');
        } else {
            return;
        }


        $(wms_button).removeAttr("wms-backbone-set");
        $(wms_button).removeClass();
        $(wms_button).addClass(shipping_provider_modal_class).addClass('wms_pickup_selection_button');
        wms_button.replaceWith(wms_button.cloneNode(true));

        // Initialize the appropriate widget with dependency check
        if (-1 != shipping_provider_modal_class.indexOf("google")) {
            if (isWidgetAvailable('set_wms_google_maps_pickup_modal')) {
                set_wms_google_maps_pickup_modal('wms_pickup_selection_button', shipping_provider_modal_class);
            } else {
                waitForWidget('set_wms_google_maps_pickup_modal', function() {
                    set_wms_google_maps_pickup_modal('wms_pickup_selection_button', shipping_provider_modal_class);
                });
            }
        } else if (-1 != shipping_provider_modal_class.indexOf("openstreetmap")) {
            if (isWidgetAvailable('set_wms_openstreetmap_pickup_modal')) {
                set_wms_openstreetmap_pickup_modal('wms_pickup_selection_button', shipping_provider_modal_class);
            } else {
                waitForWidget('set_wms_openstreetmap_pickup_modal', function() {
                    set_wms_openstreetmap_pickup_modal('wms_pickup_selection_button', shipping_provider_modal_class);
                });
            }
        } else if (-1 != shipping_provider_modal_class.indexOf("mondial_relay")) {
            if (isWidgetAvailable('set_wms_mondial_relay_pickup_modal')) {
                set_wms_mondial_relay_pickup_modal('wms_pickup_selection_button', shipping_provider_modal_class);
            } else {
                waitForWidget('set_wms_mondial_relay_pickup_modal', function() {
                    set_wms_mondial_relay_pickup_modal('wms_pickup_selection_button', shipping_provider_modal_class);
                });
            }
        }
    }
}

    // Expose function globally for React components
    window.set_wms_popup_class = set_wms_popup_class;

    // Initialize when DOM is ready
    $(init);

})(window, jQuery);
