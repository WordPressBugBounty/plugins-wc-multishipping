jQuery(function ($) {
    $(document.body)
        .on("updated_shipping_method", function () {
            set_wms_mondial_relay_pickup_modal(); // this is needed when a new shipping method is chosen
        })
        .on("updated_wc_div", function () {
            set_wms_mondial_relay_pickup_modal(); // this is needed when checkout is updated (new item quantity...)
        })
        .on("updated_checkout", function () {
            set_wms_mondial_relay_pickup_modal(); // this is needed when checkout is loaded or updated (new item quantity...)
        });
});

// Declare modal as a global variable
var modal;
var loader;
var __ = wp.i18n.__;
var sprintf = wp.i18n.sprintf;

/**
   * Get selected shipping method ID from checkout
   */
function getSelectedShippingMethod() {
    // Try WooCommerce Blocks first
    const blockSelected = document.getElementsByClassName('wc-block-components-shipping-rates-control__package')[0]?.getElementsByClassName('wc-block-components-radio-control__option-checked')[0]?.firstChild?.value;
    if (blockSelected) {
        return blockSelected;
    }

    // Try traditional checkout
    const traditionalSelected = document.querySelector('input[name^="shipping_method"]:checked');
    if (traditionalSelected) {
        return traditionalSelected.value;
    }

    // Try admin order screen (WMS custom shipping methods radios)
    const adminSelected = document.querySelector('input[name="wms_shipping_method_to_select"]:checked');
    if (adminSelected) {
        return adminSelected.value;
    }

    return null;
}

/**
 * Get user address from checkout fields
 * @param {boolean} isShipToDifferent - Whether shipping to different address
 * @returns {Object} User address {zipcode, country}
 */
function getUserAddress(isShipToDifferent) {
    const address = {
        zipcode: "",
        country: "FR",
    };

    // Get zipcode
    const shippingZipcode = document.getElementById("shipping_postcode");
    const billingZipcode = document.getElementById("billing_postcode");
    const shippingZipcodeAlt = document.getElementById("shipping-postcode");
    const billingZipcodeAlt = document.getElementById("billing-postcode");

    if (shippingZipcode && isShipToDifferent) {
        address.zipcode = shippingZipcode.value;
    } else if (billingZipcode) {
        address.zipcode = billingZipcode.value;
    } else if (shippingZipcodeAlt) {
        address.zipcode = shippingZipcodeAlt.value;
    } else if (billingZipcodeAlt) {
        address.zipcode = billingZipcodeAlt.value;
    }

    // Get country
    const shippingCountry = document.getElementById("shipping_country");
    const billingCountry = document.getElementById("billing_country");
    const shippingCountryAlt =
        document.getElementById("shipping-country")?.querySelector("input") ??
        document.getElementById("shipping-country");
    const billingCountryAlt =
        document.getElementById("billing-country")?.querySelector("input") ??
        document.getElementById("billing-country");

    if (shippingCountry && isShipToDifferent) {
        address.country = shippingCountry.value;
    } else if (billingCountry) {
        address.country = billingCountry.value;
    } else if (shippingCountryAlt) {
        address.country = shippingCountryAlt.value || shippingCountryAlt;
    } else if (billingCountryAlt) {
        address.country = billingCountryAlt.value || billingCountryAlt;
    }

    return address;
}

function set_wms_mondial_relay_pickup_modal(
    pickup_selection_button = "wms_pickup_open_modal_mondial_relay",
    shipping_provider_modal_class = ""
) {
    let wms_buttons = document.getElementsByClassName(pickup_selection_button);
    if (wms_buttons.length === 0) return;

    for (let wms_button of wms_buttons) {
        if (wms_button.getAttribute("wms-backbone-set") != null) continue;
        wms_button.addEventListener("click", function (e) {
            e.preventDefault();

            jQuery(this).WCBackboneModal({
                template:
                    0 < shipping_provider_modal_class.length
                        ? shipping_provider_modal_class
                        : this.getAttribute("wms-pickup-modal-id"),
            });

            modal = document.getElementById(
                0 < shipping_provider_modal_class.length
                    ? shipping_provider_modal_class
                    : this.getAttribute("wms-pickup-modal-id")
            );
            loader = modal.querySelector(".wc-backbone-modal-loader");

            init_mondial_relay_map();
        });
        wms_button.setAttribute("wms-backbone-set", true);
    }
}

function init_mondial_relay_map() {
    let pickup_id;
    let pickup_name;
    let pickup_address;
    let pickup_zipcode;
    let pickup_city;
    let pickup_country;

    let is_ship_to_different_address_checked =
        document.getElementById("ship-to-different-address-checkbox")?.checked ||
        false;

    // Get user address with validation
    const userAddress = getUserAddress(is_ship_to_different_address_checked);

    // Use user zipcode if valid, otherwise use default
    const zipcode_to_display =
        userAddress.zipcode && userAddress.zipcode !== "75001"
            ? userAddress.zipcode
            : "75001";
    const country_to_display = userAddress.country || "FR";

    // Get selected shipping method ID for specific method handling
    const selectedShippingMethod = getSelectedShippingMethod();
    const isMondialRelayLockers =
        typeof selectedShippingMethod === "string" &&
        selectedShippingMethod.includes("mondial_relay_lockers");
    console.log(isMondialRelayLockers);

    jQuery(".wms_pickup_modal_map").MR_ParcelShopPicker({
        Target: "#wms_pickup_point",
        Brand: "CC21X9MZ",
        Country: country_to_display,
        PostCode: zipcode_to_display,
        Responsive: true,
        NbResults: 19,
        ColLivMod: isMondialRelayLockers ? "APM" : "",
        OnParcelShopSelected: function (data) {
            pickup_id = data.ID;
            pickup_name = data.Nom;
            pickup_address = data.Adresse1;
            pickup_zipcode = data.CP;
            pickup_city = data.Ville;
            pickup_country = data.Pays;

            // Ensure hidden input uses the raw Mondial Relay ID (no country prefix)
            const pickupPointInput = document.getElementById("wms_pickup_point");
            if (pickupPointInput) {
                pickupPointInput.value = pickup_id;
            }

            // Update sidebar with selected pickup point
            updateSelectedPickupInfo(data);
        },
    });

    jQuery("#wms_select_point").on("click", function () {
        if (undefined == pickup_name) {
            modal.querySelector(".modal-close").click();
            return;
        }

        let pickup_info = [
            pickup_name,
            pickup_address,
            pickup_city + " " + pickup_zipcode,
            pickup_country,
        ];
        let error_div = jQuery("#wms_ajax_error");
        let pickup_desc_div = jQuery("#wms_pickup_selected");

        let shipping_provider = jQuery("#wms_shipping_provider").val();
        let wms_nonce = jQuery("#wms_nonce").val();

        if (
            confirm(
                sprintf(
                    __("Merci de confirmer votre choix: %s", "wc-multishipping"),
                    "\n\n" + pickup_info.join("\n")
                )
            )
        ) {
            jQuery.ajax({
                url: WMS.ajaxurl,
                type: "POST",
                dataType: "json",
                data: {
                    action: "wms_select_pickup_point",
                    pickup_id: pickup_id,
                    pickup_name: pickup_name,
                    pickup_address: pickup_address,
                    pickup_zipcode: pickup_zipcode,
                    pickup_city: pickup_city,
                    pickup_country: pickup_country,
                    pickup_provider: shipping_provider,
                    wms_nonce: wms_nonce,
                },
                beforeSend: function () {
                    error_div.hide();
                },
                success: function (response) {
                    if (response.error === false) {
                        pickup_desc_div.html(
                            "<div>" + pickup_info.join("</div><div>") + "</div>"
                        );
                        jQuery("#wms_pickup_info").val(JSON.stringify(pickup_info));
                        modal.querySelector(".modal-close").click();

                        for (const oneElement of jQuery(
                            ".wc-block-components-shipping-address"
                        )) {
                            if (jQuery(oneElement).html().indexOf("Livraison à") != -1)
                                jQuery(oneElement).html(
                                    "Livraison à : " + pickup_info.join("\n")
                                );
                        }

                        jQuery("body").trigger("update_checkout");
                    } else {
                        error_div.html(response.error_message);
                        error_div.show();
                    }
                },
            });
        } else {
            return;
        }
    });
}

/**
 * Update sidebar with selected pickup point information
 * @param {Object} data - Mondial Relay pickup point data
 */
function updateSelectedPickupInfo(data) {
    const infoContainer = document.getElementById(
        "wms_mondial_relay_selected_info"
    );
    const submitButton = document.getElementById("wms_select_point");

    if (!infoContainer || !data) return;

    // Remove empty state
    infoContainer.classList.remove("wms_selected_info_empty");
    infoContainer.classList.add("wms_selected_info_filled");

    // Build info HTML
    const infoHTML = `
        <div class="wms_selected_name">${data.Nom || ""}</div>
        <div class="wms_selected_address">${data.Adresse1 || ""}</div>
        ${data.Adresse2
            ? `<div class="wms_selected_address">${data.Adresse2}</div>`
            : ""
        }
        <div class="wms_selected_city">${data.CP || ""} ${data.Ville || ""
        }</div>
        <div class="wms_selected_country">${data.Pays || ""}</div>
        <div class="wms_selected_id">ID: ${data.ID || ""}</div>
    `;

    infoContainer.innerHTML = infoHTML;

    // Enable submit button
    if (submitButton) {
        submitButton.disabled = false;
    }
}

jQuery(function ($) {
    set_wms_mondial_relay_pickup_modal();
});
