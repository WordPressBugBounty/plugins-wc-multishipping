<?php

if ((!isset($_SERVER['DOCUMENT_ROOT'])) or (empty($_SERVER['DOCUMENT_ROOT']))) {
    if (isset($_SERVER['SCRIPT_FILENAME'])) {
        $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr($_SERVER['SCRIPT_FILENAME'], 0, 0 - strlen($_SERVER['PHP_SELF'])));
    } elseif (isset($_SERVER['PATH_TRANSLATED'])) {
        $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', substr(str_replace('\\\\', '\\', $_SERVER['PATH_TRANSLATED']), 0, 0 - strlen($_SERVER['PHP_SELF'])));
    } else {
        $_SERVER['DOCUMENT_ROOT'] = '/';
    }
}
$_SERVER['DOCUMENT_ROOT'] = str_replace('//', '/', $_SERVER['DOCUMENT_ROOT']);
if (substr($_SERVER['DOCUMENT_ROOT'], -1) != '/') {
    $_SERVER['DOCUMENT_ROOT'] .= '/';
}

if (!defined('K_TCPDF_EXTERNAL_CONFIG') or !K_TCPDF_EXTERNAL_CONFIG) {
    $tcpdf_config_files = [dirname(__FILE__).'/config/tcpdf_config.php', '/etc/php-tcpdf/tcpdf_config.php', '/etc/tcpdf/tcpdf_config.php', '/etc/tcpdf_config.php'];
    foreach ($tcpdf_config_files as $tcpdf_config) {
        if (@file_exists($tcpdf_config) and is_readable($tcpdf_config)) {
            require_once($tcpdf_config);
            break;
        }
    }
}

if (!defined('WMS_K_PATH_MAIN')) {
    define('WMS_K_PATH_MAIN', dirname(__FILE__).'/');
}

if (!defined('WMS_K_PATH_FONTS')) {
    define('WMS_K_PATH_FONTS', WMS_K_PATH_MAIN.'fonts/');
}

if (!defined('WMS_K_PATH_URL')) {
    $k_path_url = WMS_K_PATH_MAIN; // default value for console mode
    if (isset($_SERVER['HTTP_HOST']) and (!empty($_SERVER['HTTP_HOST']))) {
        if (isset($_SERVER['HTTPS']) and (!empty($_SERVER['HTTPS'])) and (strtolower($_SERVER['HTTPS']) != 'off')) {
            $k_path_url = 'https://';
        } else {
            $k_path_url = 'http://';
        }
        $k_path_url .= $_SERVER['HTTP_HOST'];
        $k_path_url .= str_replace('\\', '/', substr(WMS_K_PATH_MAIN, (strlen($_SERVER['DOCUMENT_ROOT']) - 1)));
    }
    define('WMS_K_PATH_URL', $k_path_url);
}

if (!defined('WMS_K_PATH_IMAGES')) {
    $tcpdf_images_dirs = [
        WMS_K_PATH_MAIN.'examples/images/',
        WMS_K_PATH_MAIN.'images/',
        '/usr/share/doc/php-tcpdf/examples/images/',
        '/usr/share/doc/tcpdf/examples/images/',
        '/usr/share/doc/php/tcpdf/examples/images/',
        '/var/www/tcpdf/images/',
        '/var/www/html/tcpdf/images/',
        '/usr/local/apache2/htdocs/tcpdf/images/',
        WMS_K_PATH_MAIN,
    ];
    foreach ($tcpdf_images_dirs as $tcpdf_images_path) {
        if (@file_exists($tcpdf_images_path)) {
            define('WMS_K_PATH_IMAGES', $tcpdf_images_path);
            break;
        }
    }
}

if (!defined('WMS_PDF_HEADER_LOGO')) {
    $tcpdf_header_logo = '';
    if (@file_exists(WMS_K_PATH_IMAGES.'tcpdf_logo.jpg')) {
        $tcpdf_header_logo = 'tcpdf_logo.jpg';
    }
    define('WMS_PDF_HEADER_LOGO', $tcpdf_header_logo);
}

if (!defined('WMS_PDF_HEADER_LOGO_WIDTH')) {
    if (!empty($tcpdf_header_logo)) {
        define('WMS_PDF_HEADER_LOGO_WIDTH', 30);
    } else {
        define('WMS_PDF_HEADER_LOGO_WIDTH', 0);
    }
}

if (!defined('WMS_K_PATH_CACHE')) {
    $WMS_K_PATH_CACHE = ini_get('upload_tmp_dir') ? ini_get('upload_tmp_dir') : sys_get_temp_dir();
    if (substr($WMS_K_PATH_CACHE, -1) != '/') {
        $WMS_K_PATH_CACHE .= '/';
    }
    define('WMS_K_PATH_CACHE', $WMS_K_PATH_CACHE);
}

if (!defined('WMS_K_BLANK_IMAGE')) {
    define('WMS_K_BLANK_IMAGE', '_blank.png');
}

if (!defined('WMS_PDF_PAGE_FORMAT')) {
    define('WMS_PDF_PAGE_FORMAT', 'A4');
}

if (!defined('WMS_PDF_PAGE_ORIENTATION')) {
    define('WMS_PDF_PAGE_ORIENTATION', 'P');
}

if (!defined('WMS_PDF_CREATOR')) {
    define('WMS_PDF_CREATOR', 'TCPDF');
}

if (!defined('WMS_PDF_AUTHOR')) {
    define('WMS_PDF_AUTHOR', 'TCPDF');
}

if (!defined('WMS_PDF_HEADER_TITLE')) {
    define('WMS_PDF_HEADER_TITLE', 'TCPDF Example');
}

if (!defined('WMS_PDF_HEADER_STRING')) {
    define('WMS_PDF_HEADER_STRING', "by Nicola Asuni - Tecnick.com\nwww.tcpdf.org");
}

if (!defined('WMS_PDF_UNIT')) {
    define('WMS_PDF_UNIT', 'mm');
}

if (!defined('WMS_PDF_MARGIN_HEADER')) {
    define('WMS_PDF_MARGIN_HEADER', 5);
}

if (!defined('WMS_PDF_MARGIN_FOOTER')) {
    define('WMS_PDF_MARGIN_FOOTER', 10);
}

if (!defined('WMS_PDF_MARGIN_TOP')) {
    define('WMS_PDF_MARGIN_TOP', 27);
}

if (!defined('WMS_PDF_MARGIN_BOTTOM')) {
    define('WMS_PDF_MARGIN_BOTTOM', 25);
}

if (!defined('WMS_PDF_MARGIN_LEFT')) {
    define('WMS_PDF_MARGIN_LEFT', 15);
}

if (!defined('WMS_PDF_MARGIN_RIGHT')) {
    define('WMS_PDF_MARGIN_RIGHT', 15);
}

if (!defined('WMS_PDF_FONT_NAME_MAIN')) {
    define('WMS_PDF_FONT_NAME_MAIN', 'helvetica');
}

if (!defined('WMS_PDF_FONT_SIZE_MAIN')) {
    define('WMS_PDF_FONT_SIZE_MAIN', 10);
}

if (!defined('WMS_PDF_FONT_NAME_DATA')) {
    define('WMS_PDF_FONT_NAME_DATA', 'helvetica');
}

if (!defined('WMS_PDF_FONT_SIZE_DATA')) {
    define('WMS_PDF_FONT_SIZE_DATA', 8);
}

if (!defined('WMS_PDF_FONT_MONOSPACED')) {
    define('WMS_PDF_FONT_MONOSPACED', 'courier');
}

if (!defined('WMS_PDF_IMAGE_SCALE_RATIO')) {
    define('WMS_PDF_IMAGE_SCALE_RATIO', 1.25);
}

if (!defined('WMS_HEAD_MAGNIFICATION')) {
    define('WMS_HEAD_MAGNIFICATION', 1.1);
}

if (!defined('WMS_K_CELL_HEIGHT_RATIO')) {
    define('WMS_K_CELL_HEIGHT_RATIO', 1.25);
}

if (!defined('WMS_K_TITLE_MAGNIFICATION')) {
    define('WMS_K_TITLE_MAGNIFICATION', 1.3);
}

if (!defined('WMS_K_SMALL_RATIO')) {
    define('WMS_K_SMALL_RATIO', 2 / 3);
}

if (!defined('WMS_K_THAI_TOPCHARS')) {
    define('WMS_K_THAI_TOPCHARS', true);
}

if (!defined('WMS_K_TCPDF_CALLS_IN_HTML')) {
    define('WMS_K_TCPDF_CALLS_IN_HTML', false);
}

if (!defined('WMS_K_TCPDF_THROW_EXCEPTION_ERROR')) {
    define('WMS_K_TCPDF_THROW_EXCEPTION_ERROR', false);
}

if (!defined('WMS_K_TIMEZONE')) {
    define('WMS_K_TIMEZONE', @date_default_timezone_get());
}

