var WMS = (function () {
    var wpI18n = (typeof wp !== 'undefined' && wp.i18n) ? wp.i18n : null;
    var localizedData = (typeof wms_data !== 'undefined') ? wms_data : {};

    return {
        ajaxurl: localizedData.ajaxurl || '',
        i18n: {
            __: wpI18n ? wpI18n.__ : function (text) { return text; },
            _x: wpI18n ? wpI18n._x : function (text) { return text; },
            _n: wpI18n ? wpI18n._n : function (single) { return single; },
            _nx: wpI18n ? wpI18n._nx : function (single) { return single; }
        },
        maps: localizedData.maps || {
            markers: [],
            instance: null,
            google: null
        },
        ui: localizedData.ui || {
            modal: null,
            loader: null,
            listingContainer: null
        }
    };
})();
