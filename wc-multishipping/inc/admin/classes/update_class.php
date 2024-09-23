<?php

namespace WCMultiShipping\inc\admin\classes;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_label;

class update_class
{
    public static function create_wms_db()
    {
        require_once ABSPATH.'wp-admin/includes/upgrade.php';

        global $wpdb;

        $table_name = $wpdb->prefix.abstract_label::WMS_TABLE_NAME;

        $charset_collate = $wpdb->get_charset_collate();

        $query = <<<END_SQL
CREATE TABLE $table_name (
    id                              INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    order_id                        BIGINT(20) UNSIGNED NOT NULL,
    shipping_provider               VARCHAR(255)        NOT NULL,
    outward_reservation_number      VARCHAR(255)        NULL,
    outward_label                   MEDIUMBLOB          NULL,
    outward_label_format            VARCHAR(255)        NULL,
    outward_tracking_number         VARCHAR(255)        NULL,
    outward_label_created_at        DATETIME            NULL,
    inward_reservation_number       VARCHAR(255)        NULL,
    inward_label                    MEDIUMBLOB          NULL,
    inward_label_format             VARCHAR(255)        NULL,
    inward_tracking_number          VARCHAR(255)        NULL,
    inward_label_created_at         DATETIME            NULL,
    PRIMARY KEY (id),
    INDEX order_id (order_id),
    INDEX outward_tracking_number (outward_tracking_number),
    INDEX inward_tracking_number (inward_tracking_number)
) $charset_collate;
END_SQL;

        dbDelta($query);
    }
}