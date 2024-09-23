<?php

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_label;

function wms_table_exists()
{
    global $wpdb;
    $table_name = $wpdb->prefix.abstract_label::WMS_TABLE_NAME;

    return ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
}

function wms_load_object_list($query, $key = '', $offset = null, $limit = null)
{
    global $wpdb;
    $query = wms_prepare_query($query);

    if (isset($offset)) {
        $query .= ' LIMIT '.intval($offset).','.intval($limit);
    }

    $results = $wpdb->get_results($query);
    if (empty($key)) {
        return $results;
    }

    $sorted = [];
    foreach ($results as $oneRes) {
        $sorted[$oneRes->$key] = $oneRes;
    }

    return $sorted;
}

function wms_prepare_query($query)
{
    global $wpdb;
    $query = str_replace('#__', $wpdb->prefix, $query);
    if (is_multisite()) {
        $query = str_replace($wpdb->prefix.'users', $wpdb->base_prefix.'users', $query);
    }

    return $query;
}