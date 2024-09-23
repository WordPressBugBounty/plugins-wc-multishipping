<?php

function wms_dbug($var, $exit = false)
{

    new \phpdbug($var);

    if ($exit) exit;
}


function wms_dump($dump, $exit = false)
{
    echo '<pre style="margin-left:200px; z-index: 999999999">';
    var_dump($dump);
    echo '</pre>';

    if ($exit) exit;
}


function wms_debug_backtrace($file = false, $indent = true)
{
    $debug = debug_backtrace();
    $takenPath = [];
    foreach ($debug as $step) {
        if (empty($step['file']) || empty($step['line'])) continue;
        $takenPath[] = $step['file'].' => '.$step['line'];
    }
    wms_dump(implode($file ? "\n" : '<br/>', $takenPath), $file, $indent);
}

$translations = [
    __('Generate outward labels', 'wc-multishipping'),
    __('Download labels', 'wc-multishipping'),
    __('Print labels', 'wc-multishipping'),
    __('Delete labels', 'wc-multishipping'),
    __("Display pickup points map via", "wc-multishipping"),
    __("Automatically generate label on these order status", "wc-multishipping"),
    __("Status to set after label generation", "wc-multishipping"),
    __("Send tracking link via email once the label is generated?", "wc-multishipping"),
];