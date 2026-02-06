/**
 * WMS OpenStreetMap Pickup Widget - Improved Version
 * Manages pickup point selection with OpenStreetMap integration
 */
(function (window, $, L, wp) {
  "use strict";

  // Check dependencies
  if (
    typeof $ === "undefined" ||
    typeof L === "undefined" ||
    typeof wp === "undefined"
  ) {
    console.error("WMS OpenStreetMap: Missing dependencies");
    return;
  }

  const __ = wp.i18n.__;
  const sprintf = wp.i18n.sprintf;

  // Private state (no global pollution)
  const state = {
    modal: null,
    loader: null,
    markers: [],
    map: null,
    listingContainer: null,
    isInitialized: false,
  };

  /**
   * Validate address data before making AJAX request
   */
  function validateAddress(address) {
    const errors = [];

    // Validate zipcode (required)
    if (!address.zipcode || address.zipcode.trim() === "") {
      errors.push(__("Le code postal est requis", "wc-multishipping"));
    } else if (address.zipcode.length < 4) {
      errors.push(
        __(
          "Le code postal doit contenir au moins 4 caractères",
          "wc-multishipping"
        )
      );
    }

    // Validate city (optional but recommended)
    if (!address.city || address.city.trim() === "") {
      console.warn("WMS: City is empty, search might be less accurate");
    }

    // Validate country
    if (!address.country || address.country.trim() === "") {
      errors.push(__("Le pays est requis", "wc-multishipping"));
    }

    return {
      isValid: errors.length === 0,
      errors: errors,
    };
  }

  /**
   * Get address from modal inputs
   */
  function getAddressFromModal() {
    if (!state.modal) return null;

    const country =
      state.modal.querySelector(
        ".wms_pickup_modal_address_country_select select"
      )?.value || "FR";
    const zipcode =
      state.modal.querySelector(".wms_pickup_modal_address_zipcode_input")
        ?.value || "";
    const city =
      state.modal.querySelector(".wms_pickup_modal_address_city_input")
        ?.value || "";

    return {
      country: country.trim(),
      zipcode: zipcode.trim(),
      city: city.trim(),
    };
  }

  /**
   * Get user's address from checkout form
   */
  function getUserAddress() {
    const isShipToDifferent =
      document.getElementById("ship-to-different-address-checkbox")?.checked ||
      false;

    // Priority order for address fields
    const addressFields = {
      city: [
        { id: "shipping_city", condition: isShipToDifferent },
        { id: "billing_city", condition: true },
        { id: "shipping-city", condition: true },
        { id: "billing-city", condition: true },
      ],
      zipcode: [
        { id: "shipping_postcode", condition: isShipToDifferent },
        { id: "billing_postcode", condition: true },
        { id: "shipping-postcode", condition: true },
        { id: "billing-postcode", condition: true },
      ],
      country: [
        { id: "shipping_country", condition: isShipToDifferent },
        { id: "billing_country", condition: true },
        { id: "shipping-country", condition: true },
        { id: "billing-country", condition: true },
      ],
    };

    const result = {
      city: "Paris",
      zipcode: "75001",
      country: "FR",
    };

    // Get city
    for (const field of addressFields.city) {
      if (!field.condition) continue;
      const element = document.getElementById(field.id);
      if (element?.value) {
        result.city = element.value;
        break;
      }
    }

    // Get zipcode
    for (const field of addressFields.zipcode) {
      if (!field.condition) continue;
      const element = document.getElementById(field.id);
      if (element?.value) {
        result.zipcode = element.value;
        break;
      }
    }

    // Get country
    for (const field of addressFields.country) {
      if (!field.condition) continue;
      const element = document.getElementById(field.id);
      if (element) {
        // Handle both select and input fields
        const input = element.querySelector("input");
        result.country = input?.value || element.value || result.country;
        if (result.country !== "FR") break;
      }
    }

    return result;
  }

  /**
   * Initialize or reinitialize the map
   */
  function initMap() {
    const mapContainer = document.getElementById(
      "wms_pickup_modal_map_openstreemap"
    );

    if (!mapContainer) {
      console.error("WMS: Map container not found");
      return false;
    }

    // If map already exists, remove it first
    if (state.map) {
      try {
        state.map.remove();
        state.map = null;
      } catch (e) {
        console.warn("WMS: Error removing existing map", e);
      }
    }

    // Clear the container
    mapContainer.innerHTML = "";

    // Create new map
    const defaultLat = 48.866667;
    const defaultLng = 2.333333;

    try {
      state.map = L.map(mapContainer).setView([defaultLat, defaultLng], 14);

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        minZoom: 1,
        maxZoom: 20,
      }).addTo(state.map);

      return true;
    } catch (e) {
      console.error("WMS: Error initializing map", e);
      return false;
    }
  }

  /**
   * Clear all markers from map
   */
  function clearMarkers() {
    if (!state.map) return;

    state.markers.forEach((marker) => {
      try {
        state.map.removeLayer(marker);
      } catch (e) {
        console.warn("WMS: Error removing marker", e);
      }
    });
    state.markers = [];
  }

  /**
   * Show error message in listing
   */
  function showError(message) {
    if (!state.listingContainer) return;

    state.listingContainer.innerHTML = `
      <div class="wms-error-message" style="color: #dc3232; padding: 15px; background: #fef7f7; border-left: 4px solid #dc3232; margin: 10px 0;">
        <strong>${__("Erreur", "wc-multishipping")}:</strong> ${message}
      </div>
    `;
  }

  /**
   * Show loader
   */
  function showLoader() {
    if (state.loader) {
      state.loader.style.display = "block";
    }
  }

  /**
   * Hide loader
   */
  function hideLoader() {
    if (state.loader) {
      state.loader.style.display = "none";
    }
  }

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

    return null;
  }

  /**
   * Fetch pickup points via AJAX
   */
  function fetchPickupPoints() {
    const address = getAddressFromModal();

    if (!address) {
      showError(__("Impossible de récupérer l'adresse", "wc-multishipping"));
      return Promise.reject("No address");
    }

    // Validate address
    const validation = validateAddress(address);
    if (!validation.isValid) {
      showError(validation.errors.join("<br>"));
      return Promise.reject(validation.errors);
    }

    showLoader();
    clearMarkers();

    const wmsNonce = $("#wms_nonce").val();
    const shippingProvider = $("#wms_shipping_provider").val();

    // Get selected shipping method ID for specific method handling
    const selectedShippingMethod = getSelectedShippingMethod();

    const data = {
      action: "wms_get_pickup_point",
      shipping_provider: shippingProvider,
      shipping_method_id: selectedShippingMethod,
      country: address.country,
      zipcode: address.zipcode,
      city: address.city,
      wms_nonce: wmsNonce,
    };

    return $.get(WMS.ajaxurl, data)
      .done(handlePickupPointsResponse)
      .fail(handleAjaxError)
      .always(hideLoader);
  }

  /**
   * Handle successful AJAX response
   */
  function handlePickupPointsResponse(response) {
    if (response.error) {
      showError(
        response.error_message ||
        __("Une erreur est survenue", "wc-multishipping")
      );
      return;
    }

    if (!response.data || response.data.length === 0) {
      showError(
        __("Aucun point relais trouvé pour cette adresse", "wc-multishipping")
      );
      return;
    }

    state.listingContainer = state.modal.querySelector(
      ".wms_pickup_modal_listing"
    );
    if (!state.listingContainer) {
      console.error("WMS: Listing container not found");
      return;
    }

    state.listingContainer.innerHTML = "";

    // Add markers and listings
    response.data.forEach((point, index) => {
      addPickupPoint(point, index === 0);
    });

    setupPointSelection();
    setupShipHereButtons();
  }

  /**
   * Handle AJAX error
   */
  function handleAjaxError(xhr, status, error) {
    console.error("WMS AJAX Error:", { xhr, status, error });

    let errorMessage = __("Erreur de connexion au serveur", "wc-multishipping");

    if (xhr.responseJSON?.error_message) {
      errorMessage = xhr.responseJSON.error_message;
    } else if (xhr.status === 0) {
      errorMessage = __("Pas de connexion internet", "wc-multishipping");
    } else if (xhr.status === 404) {
      errorMessage = __("Service non trouvé (404)", "wc-multishipping");
    } else if (xhr.status === 500) {
      errorMessage = __("Erreur serveur (500)", "wc-multishipping");
    }

    showError(errorMessage);
  }

  /**
   * Add a pickup point to map and listing
   */
  function addPickupPoint(point, isFirst) {
    if (!state.map || !state.listingContainer) return;

    const lat = parseFloat(point.latitude);
    const lng = parseFloat(point.longitude);

    if (isNaN(lat) || isNaN(lng)) {
      console.warn("WMS: Invalid coordinates for point", point);
      return;
    }

    // Center map on first point
    if (isFirst) {
      state.map.setView([lat, lng], 13);
    }

    // Add marker
    const marker = L.marker([lat, lng], { title: point.name }).addTo(state.map);

    const popupContent = generatePopupContent(point);
    marker
      .bindPopup(`<b>${point.name}</b><br>${popupContent}`)
      .on("click", () => handleMarkerClick(point.name));

    state.markers.push(marker);

    // Add to listing
    const listingHTML = generateListingHTML(point);
    state.listingContainer.innerHTML += listingHTML;
  }

  /**
   * Generate popup content for marker
   */
  function generatePopupContent(point) {
    const days = {
      0: __("Lundi", "wc-multishipping"),
      1: __("Mardi", "wc-multishipping"),
      2: __("Mercredi", "wc-multishipping"),
      3: __("Jeudi", "wc-multishipping"),
      4: __("Vendredi", "wc-multishipping"),
      5: __("Samedi", "wc-multishipping"),
      6: __("Dimanche", "wc-multishipping"),
    };

    if (!point.opening_time) {
      return `<div class="wms-popup-content">
        <div>${point.address}</div>
        <div>${point.zip_code} ${point.city}</div>
      </div>`;
    }

    let openingHours = '<table class="wms_pickup_open_time"><tbody>';
    point.opening_time.forEach((hours, dayIndex) => {
      openingHours += `<tr><td>${days[dayIndex]}</td><td>${hours}</td></tr>`;
    });
    openingHours += "</tbody></table>";

    return `<div class="wms-popup-content">
      <div>${point.address}</div>
      <div>${point.zip_code} ${point.city}</div>
      ${openingHours}
      <button class="button wms_pickup_modal_infowindow_one_button_ship" data-pickup-id="${point.id
      }">
        ${__("Envoyer à cette adresse", "wc-multishipping")}
      </button>
    </div>`;
  }

  /**
   * Generate listing HTML for a point
   */
  function generateListingHTML(point) {
    const days = {
      0: __("Lundi", "wc-multishipping"),
      1: __("Mardi", "wc-multishipping"),
      2: __("Mercredi", "wc-multishipping"),
      3: __("Jeudi", "wc-multishipping"),
      4: __("Vendredi", "wc-multishipping"),
      5: __("Samedi", "wc-multishipping"),
      6: __("Dimanche", "wc-multishipping"),
    };

    let openingHours = "";
    if (point.opening_time) {
      openingHours = '<table class="wms_pickup_open_time"><tbody>';
      point.opening_time.forEach((hours, dayIndex) => {
        openingHours += `<tr><td>${days[dayIndex]}</td><td>${hours}</td></tr>`;
      });
      openingHours += "</tbody></table>";
    }

    return `<div class="wms_pickup_modal_listing_one" data-pickup-name="${point.name
      }" data-pickup-id="${point.id}">
      <div class="wms_pickup_name">${point.name}</div>
      <div class="wms_pickup_address1" data-pickup-address1="${point.address
      }">${point.address}</div>
      <div class="wms_pickup_address2">
        <span class="wms_pickup_zipcode" data-pickup-zipcode="${point.zip_code
      }">${point.zip_code}</span>
        <span class="wms_pickup_city" data-pickup-city="${point.city}">${point.city
      }</span>
      </div>
      <div class="wms_pickup_country" data-pickup-country="${point.country}">${point.country
      }</div>
      ${openingHours}
      <button class="button wms_pickup_modal_listing_one_button_ship" data-pickup-id="${point.id
      }">
        ${__("Envoyer à cette adresse", "wc-multishipping")}
      </button>
    </div>`;
  }

  /**
   * Handle marker click
   */
  function handleMarkerClick(pointName) {
    unselectAllPoints();

    const point = state.modal.querySelector(
      `.wms_pickup_modal_listing [data-pickup-name="${pointName}"]`
    );
    if (point) {
      point.scrollIntoView({ behavior: "smooth", block: "center" });
      point.classList.add("wms_is_selected");
    }

    // Setup buttons in popup
    setTimeout(() => {
      const popupButtons = document.querySelectorAll(
        ".wms_pickup_modal_infowindow_one_button_ship"
      );
      popupButtons.forEach((button) => {
        button.addEventListener("click", handleShipHereClick);
      });
    }, 100);
  }

  /**
   * Setup point selection in listing
   */
  function setupPointSelection() {
    const points = state.modal.querySelectorAll(
      ".wms_pickup_modal_listing_one"
    );

    points.forEach((point) => {
      point.addEventListener("click", function () {
        unselectAllPoints();
        this.classList.add("wms_is_selected");
      });
    });
  }

  /**
   * Unselect all points
   */
  function unselectAllPoints() {
    const points = document.querySelectorAll(".wms_pickup_modal_listing_one");
    points.forEach((point) => point.classList.remove("wms_is_selected"));
  }

  /**
   * Setup "Ship here" buttons
   */
  function setupShipHereButtons() {
    const buttons = state.modal.querySelectorAll(
      ".wms_pickup_modal_listing_one_button_ship"
    );
    buttons.forEach((button) => {
      button.addEventListener("click", handleShipHereClick);
    });
  }

  /**
   * Handle "Ship here" button click
   */
  function handleShipHereClick(e) {
    e.stopPropagation();

    const button = e.currentTarget;
    const pickupId = button.getAttribute("data-pickup-id");
    let closestPickup = button.closest(".wms_pickup_modal_listing_one");

    // If the click comes from the Leaflet popup button, it's not inside the listing DOM.
    // In that case, find the matching listing item by pickup id.
    if (!closestPickup && pickupId && state.modal) {
      const escapedPickupId =
        window.CSS && typeof window.CSS.escape === "function"
          ? window.CSS.escape(pickupId)
          : pickupId;

      closestPickup = state.modal.querySelector(
        `.wms_pickup_modal_listing_one[data-pickup-id="${escapedPickupId}"]`
      );
    }

    if (!closestPickup) {
      console.error("WMS: Could not find pickup element", {
        pickupId,
        buttonClass: button && button.className,
      });
      return;
    }

    const pickupInfo = {
      id: pickupId,
      name: closestPickup.getAttribute("data-pickup-name"),
      address:
        closestPickup
          .querySelector(".wms_pickup_address1")
          ?.getAttribute("data-pickup-address1") || "",
      zipcode:
        closestPickup
          .querySelector(".wms_pickup_zipcode")
          ?.getAttribute("data-pickup-zipcode") || "",
      city:
        closestPickup
          .querySelector(".wms_pickup_city")
          ?.getAttribute("data-pickup-city") || "",
      country:
        closestPickup
          .querySelector(".wms_pickup_country")
          ?.getAttribute("data-pickup-country") || "",
    };

    const confirmMessage = sprintf(
      __("Merci de confirmer votre choix: %s", "wc-multishipping"),
      `\n\n${pickupInfo.name}\n${pickupInfo.address}\n${pickupInfo.city} ${pickupInfo.zipcode}\n${pickupInfo.country}`
    );

    if (!confirm(confirmMessage)) {
      return;
    }

    savePickupSelection(pickupInfo);
  }

  /**
   * Save pickup selection via AJAX
   */
  function savePickupSelection(pickupInfo) {
    const shippingProvider = $("#wms_shipping_provider").val();
    const wmsNonce = $("#wms_nonce").val();
    const errorDiv = $("#wms_ajax_error");
    const pickupDescDiv = $("#wms_pickup_selected");

    $.ajax({
      url: WMS.ajaxurl,
      type: "POST",
      dataType: "json",
      data: {
        action: "wms_select_pickup_point",
        pickup_id: pickupInfo.id,
        pickup_name: pickupInfo.name,
        pickup_address: pickupInfo.address,
        pickup_zipcode: pickupInfo.zipcode,
        pickup_city: pickupInfo.city,
        pickup_country: pickupInfo.country,
        pickup_provider: shippingProvider,
        wms_nonce: wmsNonce,
      },
      beforeSend: function () {
        errorDiv.hide();
      },
      success: function (response) {
        if (response.error === false) {
          // Update hidden input
          $("#wms_pickup_point").val(pickupInfo.id).trigger("change");

          // Update display
          const displayInfo = [
            pickupInfo.name,
            pickupInfo.address,
            `${pickupInfo.city} ${pickupInfo.zipcode}`,
            pickupInfo.country,
          ];

          pickupDescDiv.html(
            "<div>" + displayInfo.join("</div><div>") + "</div>"
          );
          $("#wms_pickup_info").val(JSON.stringify(displayInfo));

          // Close modal
          state.modal.querySelector(".modal-close")?.click();

          // Update WooCommerce Blocks display
          $(".wc-block-components-shipping-address").each(function () {
            if ($(this).html().indexOf("Livraison à") !== -1) {
              $(this).html("Livraison à : " + displayInfo.join("\n"));
            }
          });

          // Trigger checkout update
          $("body").trigger("update_checkout");
        } else {
          errorDiv.html(
            response.error_message ||
            __("Erreur lors de la sauvegarde", "wc-multishipping")
          );
          errorDiv.show();
        }
      },
      error: function (xhr, status, error) {
        console.error("WMS: Save error", { xhr, status, error });
        errorDiv.html(__("Erreur de connexion", "wc-multishipping"));
        errorDiv.show();
      },
    });
  }

  /**
   * Initialize modal
   */
  function initModal() {
    if (!state.modal) {
      console.error("WMS: Modal not found");
      return false;
    }

    state.loader = state.modal.querySelector(".wc-backbone-modal-loader");
    state.listingContainer = state.modal.querySelector(
      ".wms_pickup_modal_listing"
    );

    // Initialize map
    if (!initMap()) {
      return false;
    }

    // Pre-fill address from checkout
    const userAddress = getUserAddress();

    const cityInput = state.modal.querySelector(
      ".wms_pickup_modal_address_city_input"
    );
    const zipcodeInput = state.modal.querySelector(
      ".wms_pickup_modal_address_zipcode_input"
    );
    const countrySelect = state.modal.querySelector(
      ".wms_pickup_modal_address_country_select select"
    );

    // Only pre-fill city if zipcode is available
    if (
      zipcodeInput &&
      userAddress.zipcode &&
      userAddress.zipcode !== "75001"
    ) {
      zipcodeInput.value = userAddress.zipcode;
      if (cityInput && userAddress.city && userAddress.city !== "Paris") {
        cityInput.value = userAddress.city;
      }
    }

    if (countrySelect) countrySelect.value = userAddress.country;

    // Setup search button
    const searchButton = state.modal.querySelector(
      ".wms_pickup_modal_address_search"
    );
    if (searchButton) {
      searchButton.addEventListener("click", () => {
        clearMarkers();
        fetchPickupPoints();
      });
    }

    // Initial fetch
    fetchPickupPoints();

    state.isInitialized = true;
    return true;
  }

  /**
   * Setup modal button
   */
  function setupModalButton(button, modalClass) {
    if (button.getAttribute("wms-backbone-set")) {
      return; // Already setup
    }

    button.addEventListener("click", function (e) {
      e.preventDefault();

      $(this).WCBackboneModal({
        template: modalClass || this.getAttribute("wms-pickup-modal-id"),
      });

      state.modal = document.getElementById(
        modalClass || this.getAttribute("wms-pickup-modal-id")
      );

      if (state.modal) {
        initModal();
      }
    });

    button.setAttribute("wms-backbone-set", "true");
  }

  /**
   * Main initialization function
   */
  function init(
    buttonClass = "wms_pickup_open_modal_openstreetmap",
    modalClass = ""
  ) {
    const buttons = document.getElementsByClassName(buttonClass);

    if (buttons.length === 0) {
      return;
    }

    Array.from(buttons).forEach((button) => {
      setupModalButton(button, modalClass);
    });
  }

  // Setup WooCommerce events
  $(function () {
    $(document.body)
      .on("updated_shipping_method", () => init())
      .on("updated_wc_div", () => init())
      .on("updated_checkout", () => init());

    init();
  });

  // Expose function globally
  window.set_wms_openstreetmap_pickup_modal = init;
})(window, jQuery, L, wp);
