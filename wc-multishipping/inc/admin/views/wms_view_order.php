<?php

namespace WCMultiShipping\inc\admin\views;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}

class wms_view_order extends \WP_List_Table
{

    const CHECKBOX_ID = 'bulk-wms_cb_id';

}
