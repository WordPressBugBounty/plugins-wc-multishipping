<?php

function wms_get_var($type, $name, $default = null, $hash = 'REQUEST', $mask = 0)
{
    if (empty($name)) return;

    $hash = strtoupper($hash);

    switch ($hash) {
        case 'GET':
            $input = &$_GET[$name];
            break;
        case 'POST':
            $input = &$_POST[$name];
            break;
        case 'FILES':
            $input = &$_FILES[$name];
            break;
        case 'COOKIE':
            $input = &$_COOKIE[$name];
            break;
        case 'ENV':
            $input = &$_ENV[$name];
            break;
        case 'SERVER':
            $input = &$_SERVER[$name];
            break;
        case 'REQUEST':
            $input = &$_REQUEST[$name];
            break;
        default:
            $input = null;
            break;
    }

    if (!isset($input)) return $default;

    if ($type == 'array') {
        $input = (array)$input;
    }

    if (in_array($hash, ['POST', 'REQUEST', 'GET', 'COOKIE'])) {
        $input = wms_stripslashes($input);
    }

    return wms_clean_var($input, $type, $mask);
}

function wms_stripslashes($element)
{
    if (is_array($element)) {
        foreach ($element as &$oneCell) {
            $oneCell = wms_stripslashes($oneCell);
        }
    } elseif (is_string($element)) {
        $element = stripslashes($element);
    }

    return $element;
}

function wms_clean_var($var, $type, $mask)
{
    if (is_array($var)) {
        foreach ($var as $i => $val) {
            $var[$i] = wms_clean_var($val, $type, $mask);
        }

        return $var;
    }

    switch ($type) {
        case 'string':
            $var = sanitize_text_field($var);
            break;
        case 'email':
            $var = sanitize_email($var);
            break;
        case 'file':
            $var = sanitize_file_name($var);
            break;
        case 'html_class':
            $var = sanitize_html_class($var);
            break;
        case 'key':
            $var = sanitize_key($var);
            break;
        case 'meta':
            $var = sanitize_meta($var);
            break;
        case 'order_by':
            $var = sanitize_sql_orderby((string)$var);
            break;
        case 'user':
            $var = sanitize_user((string)$var);
            break;
        case 'int':
            $var = (int)$var;
            break;
        case 'float':
            $var = (float)$var;
            break;
        case 'boolean':
            $var = (boolean)$var;
            break;
        case 'word':
            $var = preg_replace('#[^a-zA-Z_]#', '', $var);
            break;
        case 'cmd':
            $var = preg_replace('#[^a-zA-Z0-9_\.-]#', '', $var);
            $var = ltrim($var, '.');
            break;
        default:
            break;
    }

    if (!is_string($var)) {
        return $var;
    }

    $var = trim($var);

    if ($mask) {
        return $var;
    }

    if (!preg_match('//u', $var)) {
        $var = htmlspecialchars_decode(htmlspecialchars($var, ENT_IGNORE, 'UTF-8'));
    }

    if (!$mask) {
        $var = preg_replace('#<[a-zA-Z/]+[^>]*>#Uis', '', $var);
    }

    return $var;
}

function wms_display_value($text)
{
    return esc_attr(sanitize_text_field($text));
}
