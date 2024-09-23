<?php

function wms_logger($error)
{
    if (empty($error)) return false;

    $error = '['.date('Y-m-d H:i:s', time()).']: '.$error;
    $error .= "\r\n";

    $date_file_name = date('Y_W', time());

    if (!file_exists(WMS_LOG)) {
        mkdir(WMS_LOG);
    }

    return error_log($error, 3, WMS_LOG.'log_'.$date_file_name.'.txt');
}

function wms_delete_log()
{
    $date = date('Y_W', time() - 1209600);

    if (file_exists(WMS_LOG.'log_'.$date.'.txt')) @unlink(WMS_LOG.'log_'.$date.'.txt');
}

function wms_get_current_log()
{
    $date = date('Y_W', time());

    return WMS_LOG.'log_'.$date.'.txt';
}