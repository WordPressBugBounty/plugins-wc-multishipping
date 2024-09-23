<?php

if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

define('WMS_COMPONENT', 'wc-multishipping');
define('WMS_FOLDER', WP_PLUGIN_DIR.DS.WMS_COMPONENT.DS);
define('WMS_WEBSITE', 'https://www.wcmultishipping.com/');

define('WMS_INCLUDES', WMS_FOLDER.'inc'.DS);

define('WMS_ADMIN', WMS_INCLUDES.'admin'.DS);
define('WMS_ASSETS', WMS_ADMIN.'assets'.DS);
define('WMS_PARTIALS', WMS_ADMIN.'partials'.DS);
define('WMS_VIEWS', WMS_ADMIN.'views'.DS);
define('WMS_CLASSES', WMS_ADMIN.'classes'.DS);

define('WMS_RESOURCES', WMS_INCLUDES.'resources'.DS);

define('WMS_LOG', WMS_FOLDER.'logs'.DS);

define('WMS_LIB', WMS_INCLUDES.'lib'.DS);

define('WMS_FRONT', WMS_INCLUDES.'front'.DS);
define('WMS_FRONT_PICKUP', WMS_FRONT.'pickup'.DS);
define('WMS_FRONT_PARTIALS', WMS_FRONT.'partials'.DS);

define('WMS_SHIPPING_METHODS', WMS_INCLUDES.'shipping_methods'.DS);

define('WMS_GLOBAL_HELPERS', WMS_INCLUDES.'helpers'.DS);

define('WMS_SCRIPTS', WMS_ASSETS.'js'.DS);

define('WMS_WOOCOMMERCE_BLOCK', WMS_INCLUDES.'woocommerce_block'.DS);


define('WMS_PLUGINS_URL', plugins_url());
define('WMS_ADMIN_ASSETS_URL', WMS_PLUGINS_URL.'/'.WMS_COMPONENT.'/inc/admin/assets/');
define('WMS_ADMIN_JS_URL', WMS_ADMIN_ASSETS_URL.'js/');
define('WMS_ADMIN_CSS_URL', WMS_ADMIN_ASSETS_URL.'css/');
define('WMS_PLUGIN_URL', WMS_PLUGINS_URL.'/wc-multishipping/');

define('WMS_FRONT_ASSETS_URL', WMS_PLUGINS_URL.'/'.WMS_COMPONENT.'/inc/front/assets/');
define('WMS_FRONT_JS_URL', WMS_FRONT_ASSETS_URL.'js/');
define('WMS_FRONT_CSS_URL', WMS_FRONT_ASSETS_URL.'css/');



require WMS_FOLDER.'vendor'.DS.'autoload.php';

include WMS_GLOBAL_HELPERS.'helper'.DS.'log.php';
include WMS_GLOBAL_HELPERS.'helper'.DS.'security.php';
include WMS_GLOBAL_HELPERS.'helper'.DS.'debug.php';
include WMS_GLOBAL_HELPERS.'helper'.DS.'database.php';
include WMS_GLOBAL_HELPERS.'helper'.DS.'notice.php';



