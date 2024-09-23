<?php

function wms_session()
{
    $session_id = session_id();
    if (empty($session_id)) {
        @session_start();
    }
}

function wms_enqueue_message($message, $type = 'success')
{
    $type = str_replace(['notice', 'message'], ['info', 'success'], $type);
    $message = is_array($message) ? implode('<br/>', $message) : $message;

    $handledTypes = ['info', 'warning', 'error', 'success'];

    if (in_array($type, $handledTypes)) {
        wms_session();
        if (empty($_SESSION['wms_message'.$type]) || !in_array($message, $_SESSION['wms_message'.$type])) {
            $_SESSION['wms_message'.$type][] = $message;
        }
    }

    return true;
}


function wms_display($messages, $type = 'success', $inline = false, $is_dismissible = true, $display_area = 'screen')
{
    if (empty($messages)) return;
    if (!is_array($messages)) $messages = [$messages];

    if ('logs' === $display_area) $log_message = '';

    $is_dismissible = (empty($is_dismissible) ? '' : 'is-dismissible');
    $inline = (empty($inline) ? '' : 'inline');

    foreach ($messages as $one_message) {

        if ('logs' === $display_area) {
            if (is_array($one_message)) $one_message = implode("\r\n", $one_message);
            $log_message .= $one_message;
            continue;
        }
        echo '<div class="notice notice-'.$type.' '.$inline.' '.$is_dismissible.'">';

        if (is_array($one_message)) $one_message = implode('</p><p>', $one_message);

        echo '<div><p>'.($one_message).'</p></div>';

        echo '</div>';
    }
    if ('logs' === $display_area && !empty($display_area)) error_log($log_message);
}

function wms_display_messages($inline = false, $is_dismissible = true)
{
    $types = ['success', 'info', 'warning', 'error'];
    wms_session();

    foreach ($types as $id => $type) {

        if (empty($_SESSION['wms_message'.$type])) continue;

        wms_display($_SESSION['wms_message'.$type], $type, $inline, $is_dismissible);
        unset($_SESSION['wms_message'.$type]);
    }
}
