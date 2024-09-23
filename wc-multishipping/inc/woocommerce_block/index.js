/******/
(function () { // webpackBootstrap
    /******/
    "use strict";
    /******/
    var __webpack_modules__ = ({

        /***/
        "./src/js/index.js":
        /*!*************************!*\
          !*** ./src/js/index.js ***!
          \*************************/
        /***/ (function (__unused_webpack_module, __webpack_exports__, __webpack_require__) {

                __webpack_require__.r(__webpack_exports__);
                /* harmony import */
                var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
                /* harmony import */
                var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
                /* harmony import */
                var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/plugins */ "@wordpress/plugins");
                /* harmony import */
                var _wordpress_plugins__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_plugins__WEBPACK_IMPORTED_MODULE_1__);
                /* harmony import */
                var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
                /* harmony import */
                var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(
                    _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__);
                /* harmony import */
                var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @woocommerce/blocks-checkout */
                    "@woocommerce/blocks-checkout");
                /* harmony import */
                var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(
                    _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_3__);
                /* harmony import */
                var _style_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.scss */ "./src/js/style.scss");

                /**
                 * External dependencies
                 */


                /**
                 * Internal dependencies
                 */

                const dataFromBlockSettings = (0, _woocommerce_settings__WEBPACK_IMPORTED_MODULE_2__.getSetting)('wcmultishipping_data');
                const MyCustomComponent = ({
                    cart,
                    extensions
                }) => {
                    let selected_shipping_method,
                        display_wms_block_value = undefined;
                    if (undefined == cart?.shippingRates[0]) return;

                    //Check all shipping methods and pick the selected one
                    for (let one_shipping_method of cart?.shippingRates[0]?.shipping_rates) {
                        if (one_shipping_method.selected === true) {
                            selected_shipping_method = one_shipping_method.method_id;
                        }
                    }
                    if (undefined == selected_shipping_method) return;

                    //Check if we have a previous provider set in the HTML (if we've selected a pickup from MR then switch to Chronopost for example)
                    const previous_pickup_provider = jQuery('#wms_shipping_provider').val();

                    //If the provider isn't the same then we need to clean everything
                    if (undefined != previous_pickup_provider && '' != previous_pickup_provider && selected_shipping_method.indexOf(previous_pickup_provider) == -1) {

                        let pickup_desc_div = jQuery('#wms_pickup_selected');
                        if (pickup_desc_div.childElementsCount != 0) pickup_desc_div.html('');

                        for (const oneElement of jQuery('.wc-block-components-shipping-address')) {
                            if (jQuery(oneElement).html().indexOf("Livraison à") != -1) jQuery(oneElement)
                                .html("Livraison à : ");
                        }
                    }


                    if (undefined == selected_shipping_method || (-1 == selected_shipping_method.indexOf("relais") && -1 == selected_shipping_method.indexOf("2shop") && -1 == selected_shipping_method.indexOf("access_point"))) {
                        display_wms_block_value = 'none';
                    } else {
                        display_wms_block_value = 'block';
                        set_wms_popup_class(selected_shipping_method);
                    }

                    return (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div",
                        {
                            id: "wms_pickup_woo_block",
                            style: {
                                display: display_wms_block_value
                            }
                        },
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            "button",
                            {
                                style: {
                                    padding: 4,
                                    border: 2,
                                    borderColor: 'black',
                                    borderStyle: 'solid',
                                    marginTop: 20,
                                    marginBottom: 20
                                },
                                className: "wms_pickup_selection_button",
                                chronopost_modal_id: dataFromBlockSettings['chronopost_modal_id'],
                                mondial_relay_modal_id: dataFromBlockSettings['mondial_relay_modal_id'],
                                ups_modal_id: dataFromBlockSettings['ups_modal_id']
                            },
                            dataFromBlockSettings['choose-pickup-text']),
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            "div",
                            {
                                id: "wms_ajax_error"
                            }),
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            "div",
                            {
                                id: "wms_selected_pickup_desc"
                            },
                            (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                                "strong",
                                null,
                                dataFromBlockSettings['package-shipping-text']),
                            (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                                "div",
                                {
                                    id: "wms_pickup_selected"
                                },
                                dataFromBlockSettings['please-select-pickup-text'])),
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            "input",
                            {
                                type: "hidden",
                                id: "wms_nonce",
                                value: dataFromBlockSettings['nonce']
                            }),
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            "input",
                            {
                                type: "hidden",
                                id: "wms_shipping_provider"
                            }),
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            "input",
                            {
                                type: "hidden",
                                id: "wms_pickup_point"
                            }),
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            "input",
                            {
                                type: "hidden",
                                id: "wms_pickup_info"
                            }));
                };
                const render = () => {
                    return (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.Fragment,
                        null,
                        (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                            _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_3__.ExperimentalOrderShippingPackages,
                            null,
                            (0, _wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(
                                MyCustomComponent,
                                null)));
                };
                (0, _wordpress_plugins__WEBPACK_IMPORTED_MODULE_1__.registerPlugin)('wcmultishipping', {
                    render,
                    scope: 'woocommerce-checkout'
                });

                function set_wms_popup_class(selected_shipping_method) {
                    let wms_buttons = document.getElementsByClassName('wms_pickup_selection_button');
                    if (wms_buttons.length === 0) return;
                    for (let wms_button of wms_buttons) {
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
                        }
                        jQuery(wms_button).removeAttr("wms-backbone-set");
                        jQuery(wms_button).removeClass();
                        jQuery(wms_button).addClass(shipping_provider_modal_class).addClass('wms_pickup_selection_button');
                        wms_button.replaceWith(wms_button.cloneNode(true));
                        if (-1 != shipping_provider_modal_class.indexOf("google")) set_wms_google_maps_pickup_modal('wms_pickup_selection_button',
                            shipping_provider_modal_class); else if (-1 != shipping_provider_modal_class.indexOf(
                                "openstreetmap")) set_wms_openstreetmap_pickup_modal('wms_pickup_selection_button',
                                    shipping_provider_modal_class); else if (-1 != shipping_provider_modal_class.indexOf(
                                        "mondial_relay")) set_wms_mondial_relay_pickup_modal('wms_pickup_selection_button', shipping_provider_modal_class);
                    }
                }

                /***/
            }),

        /***/
        "./src/js/style.scss":
        /*!***************************!*\
          !*** ./src/js/style.scss ***!
          \***************************/
        /***/ (function (__unused_webpack_module, __webpack_exports__, __webpack_require__) {

                __webpack_require__.r(__webpack_exports__);
                // extracted by mini-css-extract-plugin


                /***/
            }),

        /***/
        "@woocommerce/blocks-checkout":
        /*!****************************************!*\
          !*** external ["wc","blocksCheckout"] ***!
          \****************************************/
        /***/ (function (module) {

                module.exports = window["wc"]["blocksCheckout"];

                /***/
            }),

        /***/
        "@woocommerce/settings":
        /*!************************************!*\
          !*** external ["wc","wcSettings"] ***!
          \************************************/
        /***/ (function (module) {

                module.exports = window["wc"]["wcSettings"];

                /***/
            }),

        /***/
        "@wordpress/element":
        /*!*********************************!*\
          !*** external ["wp","element"] ***!
          \*********************************/
        /***/ (function (module) {

                module.exports = window["wp"]["element"];

                /***/
            }),

        /***/
        "@wordpress/plugins":
        /*!*********************************!*\
          !*** external ["wp","plugins"] ***!
          \*********************************/
        /***/ (function (module) {

                module.exports = window["wp"]["plugins"];

                /***/
            })

        /******/
    });
    /************************************************************************/
    /******/ 	// The module cache
    /******/
    var __webpack_module_cache__ = {};
    /******/
    /******/ 	// The require function
    /******/
    function __webpack_require__(moduleId) {
        /******/ 		// Check if module is in cache
        /******/
        var cachedModule = __webpack_module_cache__[moduleId];
        /******/
        if (cachedModule !== undefined) {
            /******/
            return cachedModule.exports;
            /******/
        }
        /******/ 		// Create a new module (and put it into the cache)
        /******/
        var module = __webpack_module_cache__[moduleId] = {
            /******/ 			// no module.id needed
            /******/ 			// no module.loaded needed
            /******/
            exports: {}
            /******/
        };
        /******/
        /******/ 		// Execute the module function
        /******/
        __webpack_modules__[moduleId](module, module.exports, __webpack_require__);
        /******/
        /******/ 		// Return the exports of the module
        /******/
        return module.exports;
        /******/
    }

    /******/
    /******/ 	// expose the modules object (__webpack_modules__)
    /******/
    __webpack_require__.m = __webpack_modules__;
    /******/
    /************************************************************************/
    /******/ 	/* webpack/runtime/chunk loaded */
    /******/
    !function () {
        /******/
        var deferred = [];
        /******/
        __webpack_require__.O = function (result, chunkIds, fn, priority) {
            /******/
            if (chunkIds) {
                /******/
                priority = priority || 0;
                /******/
                for (var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
                /******/
                deferred[i] = [chunkIds,
                    fn,
                    priority];
                /******/
                return;
                /******/
            }
            /******/
            var notFulfilled = Infinity;
            /******/
            for (var i = 0; i < deferred.length; i++) {
                /******/
                var chunkIds = deferred[i][0];
                /******/
                var fn = deferred[i][1];
                /******/
                var priority = deferred[i][2];
                /******/
                var fulfilled = true;
                /******/
                for (var j = 0; j < chunkIds.length; j++) {
                    /******/
                    if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O)
                        .every(function (key) { return __webpack_require__.O[key](chunkIds[j]); })) {
                        /******/
                        chunkIds.splice(j--, 1);
                        /******/
                    } else {
                        /******/
                        fulfilled = false;
                        /******/
                        if (priority < notFulfilled) notFulfilled = priority;
                        /******/
                    }
                    /******/
                }
                /******/
                if (fulfilled) {
                    /******/
                    deferred.splice(i--, 1);
                    /******/
                    var r = fn();
                    /******/
                    if (r !== undefined) result = r;
                    /******/
                }
                /******/
            }
            /******/
            return result;
            /******/
        };
        /******/
    }();
    /******/
    /******/ 	/* webpack/runtime/compat get default export */
    /******/
    !function () {
        /******/ 		// getDefaultExport function for compatibility with non-harmony modules
        /******/
        __webpack_require__.n = function (module) {
            /******/
            var getter = module && module.__esModule ?
                /******/                function () { return module['default']; } :
                /******/                function () { return module; };
            /******/
            __webpack_require__.d(getter, { a: getter });
            /******/
            return getter;
            /******/
        };
        /******/
    }();
    /******/
    /******/ 	/* webpack/runtime/define property getters */
    /******/
    !function () {
        /******/ 		// define getter functions for harmony exports
        /******/
        __webpack_require__.d = function (exports, definition) {
            /******/
            for (var key in definition) {
                /******/
                if (__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
                    /******/
                    Object.defineProperty(exports,
                        key,
                        {
                            enumerable: true,
                            get: definition[key]
                        });
                    /******/
                }
                /******/
            }
            /******/
        };
        /******/
    }();
    /******/
    /******/ 	/* webpack/runtime/hasOwnProperty shorthand */
    /******/
    !function () {
        /******/
        __webpack_require__.o = function (obj, prop) { return Object.prototype.hasOwnProperty.call(obj, prop); };
        /******/
    }();
    /******/
    /******/ 	/* webpack/runtime/make namespace object */
    /******/
    !function () {
        /******/ 		// define __esModule on exports
        /******/
        __webpack_require__.r = function (exports) {
            /******/
            if (typeof Symbol !== 'undefined' && Symbol.toStringTag) {
                /******/
                Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
                /******/
            }
            /******/
            Object.defineProperty(exports, '__esModule', { value: true });
            /******/
        };
        /******/
    }();
    /******/
    /******/ 	/* webpack/runtime/jsonp chunk loading */
    /******/
    !function () {
        /******/ 		// no baseURI
        /******/
        /******/ 		// object to store loaded and loading chunks
        /******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
        /******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
        /******/
        var installedChunks = {
            /******/
            "index": 0,
            /******/
            "./style-index": 0
            /******/
        };
        /******/
        /******/ 		// no chunk on demand loading
        /******/
        /******/ 		// no prefetching
        /******/
        /******/ 		// no preloaded
        /******/
        /******/ 		// no HMR
        /******/
        /******/ 		// no HMR manifest
        /******/
        /******/
        __webpack_require__.O.j = function (chunkId) { return installedChunks[chunkId] === 0; };
        /******/
        /******/ 		// install a JSONP callback for chunk loading
        /******/
        var webpackJsonpCallback = function (parentChunkLoadingFunction, data) {
            /******/
            var chunkIds = data[0];
            /******/
            var moreModules = data[1];
            /******/
            var runtime = data[2];
            /******/ 			// add "moreModules" to the modules object,
            /******/ 			// then flag all "chunkIds" as loaded and fire callback
            /******/
            var moduleId, chunkId, i = 0;
            /******/
            if (chunkIds.some(function (id) { return installedChunks[id] !== 0; })) {
                /******/
                for (moduleId in moreModules) {
                    /******/
                    if (__webpack_require__.o(moreModules, moduleId)) {
                        /******/
                        __webpack_require__.m[moduleId] = moreModules[moduleId];
                        /******/
                    }
                    /******/
                }
                /******/
                if (runtime) var result = runtime(__webpack_require__);
                /******/
            }
            /******/
            if (parentChunkLoadingFunction) parentChunkLoadingFunction(data);
            /******/
            for (; i < chunkIds.length; i++) {
                /******/
                chunkId = chunkIds[i];
                /******/
                if (__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
                    /******/
                    installedChunks[chunkId][0]();
                    /******/
                }
                /******/
                installedChunks[chunkId] = 0;
                /******/
            }
            /******/
            return __webpack_require__.O(result);
            /******/
        };
        /******/
        /******/
        var chunkLoadingGlobal = self["webpackChunkwcmultishipping"] = self["webpackChunkwcmultishipping"] || [];
        /******/
        chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
        /******/
        chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
        /******/
    }();
    /******/
    /************************************************************************/
    /******/
    /******/ 	// startup
    /******/ 	// Load entry module and return exports
    /******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
    /******/
    var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-index"], function () { return __webpack_require__("./src/js/index.js"); });
    /******/
    __webpack_exports__ = __webpack_require__.O(__webpack_exports__);
    /******/
    /******/
})()
    ;
//# sourceMappingURL=index.js.map