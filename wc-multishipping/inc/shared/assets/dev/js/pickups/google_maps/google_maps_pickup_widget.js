/**
 * WMS Google Maps Pickup Widget - Improved Version
 * Manages pickup point selection with Google Maps integration
 */
(function (window, $, google, wp) {
  "use strict";

  // Check dependencies
  if (
    typeof $ === "undefined" ||
    typeof google === "undefined" ||
    typeof wp === "undefined"
  ) {
    console.error("WMS Google Maps: Missing dependencies");
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
    infoWindowOpened: null,
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
        const value = input ? input.value : element.value;
        if (value) {
          result.country = value;
          break;
        }
      }
    }

    return result;
  }

  /**
   * Display error message in listing container
   */
  function showError(message) {
    if (!state.listingContainer) return;

    const errorHtml = `
      <div class="wms-error-message">
        <strong>${__("Erreur", "wc-multishipping")}</strong>
        <p>${message}</p>
      </div>
    `;

    state.listingContainer.innerHTML = errorHtml;
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
   * Clear all markers from map
   */
  function clearMarkersOnMap() {
    state.markers.forEach((marker) => {
      marker.setMap(null);
    });
    state.markers = [];
  }

  /**
   * Generate popup content for a pickup point
   */
  function generateMapPopup(point) {
    const days = {
      0: __("Lundi", "wc-multishipping"),
      1: __("Mardi", "wc-multishipping"),
      2: __("Mercredi", "wc-multishipping"),
      3: __("Jeudi", "wc-multishipping"),
      4: __("Vendredi", "wc-multishipping"),
      5: __("Samedi", "wc-multishipping"),
      6: __("Dimanche", "wc-multishipping"),
    };

    let openTimeHtml = '<table class="wms_pickup_open_time"><tbody>';
    point.opening_time.forEach((openingTime, dayNumber) => {
      openTimeHtml += `<tr><td>${days[dayNumber]}</td><td>${openingTime}</td></tr>`;
    });
    openTimeHtml += "</tbody></table>";

    const html = `
      <div class="wms_pickup_modal_listing_one" data-pickup-name="${point.name}">
        <div class="wms_pickup_name">${point.name}</div>
        <div class="wms_pickup_address1" data-pickup-address1="${point.address}">${point.address}</div>
        <div class="wms_pickup_address2">
          <span class="wms_pickup_zipcode" data-pickup-zipcode="${point.zip_code}">${point.zip_code}</span>
          <span class="wms_pickup_city" data-pickup-city="${point.city}">${point.city}</span>
        </div>
        <div class="wms_pickup_country" data-pickup-country="${point.country}">${point.country}</div>
        ${openTimeHtml}
        <button class="button wms_pickup_modal_listing_one_button_ship" data-pickup-id="${point.id}">
          ${__("Envoyer à cette adresse", "wc-multishipping")}
        </button>
      </div>
    `;

    state.listingContainer.innerHTML += html;

    return new google.maps.InfoWindow({
      content: html.replace(
        "wms_pickup_modal_listing_one_button_ship",
        "wms_pickup_modal_infowindow_one_button_ship"
      ),
    });
  }

  /**
   * Unselect all pickup points
   */
  function unselectAllPoints() {
    const points = document.querySelectorAll(".wms_pickup_modal_listing_one");
    points.forEach((point) => point.classList.remove("wms_is_selected"));
  }

  /**
   * Set marker click actions
   */
  function setMarkerClickActions(marker, infoWindow) {
    marker.addListener("click", function () {
      // Close previously opened info window
      if (state.infoWindowOpened) {
        state.infoWindowOpened.close();
      }

      // Open new info window
      infoWindow.open(state.map, marker);
      state.infoWindowOpened = infoWindow;

      unselectAllPoints();

      // Add click event to button inside info window
      google.maps.event.addListener(infoWindow, "domready", function () {
        const infoWindowButtons = document.querySelectorAll(
          ".wms_pickup_modal_infowindow_one_button_ship"
        );
        if (infoWindowButtons.length > 0) {
          setShipHereButtonActions(infoWindowButtons);
        }
      });

      // Scroll to and select corresponding point in list
      const point = document.querySelector(
        `[data-pickup-name="${marker.getTitle()}"]`
      );
      if (point) {
        point.scrollIntoView({ behavior: "smooth", block: "nearest" });
        point.classList.add("wms_is_selected");
      }
    });
  }

  /**
   * Set click event on pickup points in the listing
   */
  function setSelectPointActions() {
    const pickupPoints = document.querySelectorAll(
      ".wms_pickup_modal_listing_one"
    );

    pickupPoints.forEach((pickupPoint) => {
      pickupPoint.addEventListener("click", function () {
        unselectAllPoints();
        this.classList.add("wms_is_selected");
      });
    });
  }

  /**
   * Handle ship here button click
   */
  function setShipHereButtonActions(buttons = null) {
    if (!buttons) {
      buttons = document.querySelectorAll(
        ".wms_pickup_modal_listing_one_button_ship"
      );
    }

    buttons.forEach((button) => {
      button.addEventListener("click", function (e) {
        e.stopPropagation(); // Prevent triggering parent click event

        const closestPickup = this.closest(".wms_pickup_modal_listing_one");
        if (!closestPickup) return;

        const pickupData = {
          id: this.getAttribute("data-pickup-id"),
          name: closestPickup.getAttribute("data-pickup-name"),
          address: closestPickup
            .querySelector(".wms_pickup_address1")
            ?.getAttribute("data-pickup-address1"),
          zipcode: closestPickup
            .querySelector(".wms_pickup_zipcode")
            ?.getAttribute("data-pickup-zipcode"),
          city: closestPickup
            .querySelector(".wms_pickup_city")
            ?.getAttribute("data-pickup-city"),
          country: closestPickup
            .querySelector(".wms_pickup_country")
            ?.getAttribute("data-pickup-country"),
        };

        const pickupInfo = [
          pickupData.name,
          pickupData.address,
          `${pickupData.city} ${pickupData.zipcode}`,
          pickupData.country,
        ];

        // Confirm selection
        if (
          !confirm(
            sprintf(
              __("Merci de confirmer votre choix: %s", "wc-multishipping"),
              "\n\n" + pickupInfo.join("\n")
            )
          )
        ) {
          return;
        }

        // Set hidden input value
        const selectedPointInput = document.getElementById("wms_pickup_point");
        if (selectedPointInput) {
          selectedPointInput.value = pickupData.id;
          selectedPointInput.dispatchEvent(new Event("change"));
        }

        const shippingProvider = $("#wms_shipping_provider").val();
        const wmsNonce = $("#wms_nonce").val();

        // Save selection via AJAX
        $.ajax({
          url: WMS.ajaxurl,
          type: "POST",
          dataType: "json",
          data: {
            action: "wms_select_pickup_point",
            pickup_id: pickupData.id,
            pickup_name: pickupData.name,
            pickup_address: pickupData.address,
            pickup_zipcode: pickupData.zipcode,
            pickup_city: pickupData.city,
            pickup_country: pickupData.country,
            pickup_provider: shippingProvider,
            wms_nonce: wmsNonce,
          },
          beforeSend: function () {
            $("#wms_ajax_error").hide();
          },
          success: function (response) {
            if (response.error === false) {
              // Update pickup description
              $("#wms_pickup_selected").html(
                "<div>" + pickupInfo.join("</div><div>") + "</div>"
              );

              // Update WooCommerce Blocks if present
              $(".wc-block-components-shipping-address").each(function () {
                if ($(this).html().indexOf("Livraison à") !== -1) {
                  $(this).html("Livraison à : " + pickupInfo.join("\n"));
                }
              });

              // Close modal
              if (state.modal) {
                state.modal.querySelector(".modal-close")?.click();
              }

              // Trigger checkout update
              $("body").trigger("update_checkout");
            } else {
              const errorDiv = $("#wms_ajax_error");
              errorDiv.html(response.error_message);
              errorDiv.show();
            }
          },
          error: function () {
            showError(
              __(
                "Une erreur est survenue lors de la sélection du point relais",
                "wc-multishipping"
              )
            );
          },
        });
      });
    });
  }

  /**
   * Handle AJAX error
   */
  function handleAjaxError(xhr, status, error) {
    console.error("WMS Google Maps AJAX Error:", error);
    showError(
      __(
        "Une erreur est survenue lors de la récupération des points relais",
        "wc-multishipping"
      )
    );
  }

  /**
   * Handle pickup points response
   */
  function handlePickupPointsResponse(response) {
    if (response.error) {
      showError(response.error_message);
      return;
    }

    if (!response.data || response.data.length === 0) {
      showError(
        __(
          "Aucun point relais trouvé pour cette adresse",
          "wc-multishipping"
        )
      );
      return;
    }

    const bounds = new google.maps.LatLngBounds();
    state.listingContainer.innerHTML = "";

    // Add markers and listing items
    response.data.forEach((point) => {
      const position = {
        lat: parseFloat(point.latitude),
        lng: parseFloat(point.longitude),
      };

      // Create marker
      const marker = new google.maps.Marker({
        position: position,
        map: state.map,
        title: point.name,
        visible: true,
        icon: "https://maps.google.com/mapfiles/ms/icons/red-dot.png",
      });

      // Generate info window and set click actions
      const infoWindow = generateMapPopup(point);
      setMarkerClickActions(marker, infoWindow);

      state.markers.push(marker);
      bounds.extend(position);
    });

    // Fit map to show all markers
    state.map.fitBounds(bounds);

    // Set up interactions
    setSelectPointActions();
    setShipHereButtonActions();
  }

  /**
   * Fetch pickup points via AJAX
   */
  function getPickupPointsAjax() {
    const address = getAddressFromModal();
    if (!address) {
      showError(__("Impossible de récupérer l'adresse", "wc-multishipping"));
      return;
    }

    // Validate address
    const validation = validateAddress(address);
    if (!validation.isValid) {
      showError(validation.errors.join("<br>"));
      return;
    }

    showLoader();

    const shippingProvider = $("#wms_shipping_provider").val();
    const wmsNonce = $("#wms_nonce").val();

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
   * Set reload button action
   */
  function setReloadButtonAction() {
    const searchButton = state.modal?.querySelector(
      ".wms_pickup_modal_address_search"
    );
    if (searchButton) {
      searchButton.addEventListener("click", function () {
        clearMarkersOnMap();
        getPickupPointsAjax();
      });
    }
  }

  /**
   * Initialize Google Maps
   */
  function initGoogleMaps() {
    const mapOptions = {
      zoom: 15,
      mapTypeId: google.maps.MapTypeId.ROADMAP,
      center: {
        lat: 48.866667,
        lng: 2.333333,
      },
      disableDefaultUI: true,
    };

    const mapElement = state.modal?.querySelector(
      "#wms_pickup_modal_map_googlemaps"
    );
    if (!mapElement) {
      console.error("WMS Google Maps: Map element not found");
      return;
    }

    state.map = new google.maps.Map(mapElement, mapOptions);
    state.listingContainer = state.modal?.querySelector(
      ".wms_pickup_modal_listing"
    );

    // Pre-fill form with user address
    const userAddress = getUserAddress();

    // Only pre-fill if we have valid data (avoid default values)
    const cityInput = state.modal?.querySelector(
      ".wms_pickup_modal_address_city_input"
    );
    const zipcodeInput = state.modal?.querySelector(
      ".wms_pickup_modal_address_zipcode_input"
    );
    const countrySelect = state.modal?.querySelector(
      ".wms_pickup_modal_address_country_select select"
    );

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

    if (countrySelect && userAddress.country) {
      countrySelect.value = userAddress.country;
    }

    // Set up button actions and fetch initial data
    setReloadButtonAction();
    getPickupPointsAjax();
  }

  /**
   * Initialize modal and setup event listeners
   */
  function setupModal(modalElement) {
    state.modal = modalElement;
    state.loader = modalElement?.querySelector(".wc-backbone-modal-loader");
    initGoogleMaps();
  }

  /**
   * Set up pickup modal buttons
   */
  function setPickupModalButtons(
    buttonClass = "wms_pickup_open_modal_google_maps",
    modalClass = ""
  ) {
    const buttons = document.querySelectorAll(`.${buttonClass}`);
    if (buttons.length === 0) return;

    buttons.forEach((button) => {
      // Skip if already initialized
      if (button.getAttribute("wms-backbone-set")) return;

      button.addEventListener("click", function (e) {
        e.preventDefault();

        const modalId =
          modalClass.length > 0
            ? modalClass
            : this.getAttribute("wms-pickup-modal-id");

        $(this).WCBackboneModal({
          template: modalId,
        });

        const modal = document.getElementById(modalId);
        if (modal) {
          setupModal(modal);
        }
      });

      button.setAttribute("wms-backbone-set", "true");
    });
  }

  /**
   * Main initialization function
   */
  function init(
    buttonClass = "wms_pickup_open_modal_google_maps",
    modalClass = ""
  ) {
    setPickupModalButtons(buttonClass, modalClass);
  }

  // jQuery event listeners for WooCommerce events
  $(function () {
    $(document.body)
      .on("updated_shipping_method", () => init())
      .on("updated_wc_div", () => init())
      .on("updated_checkout", () => init());

    // Initial setup
    init();
  });

  // Expose function globally (matches OpenStreetMap convention)
  window.set_wms_google_maps_pickup_modal = init;
})(window, jQuery, google, wp);
