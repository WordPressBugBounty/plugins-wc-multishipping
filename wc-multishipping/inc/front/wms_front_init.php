<?php

namespace WCMultiShipping\inc\front;

use WCMultiShipping\inc\front\pickup\chronopost\chronopost_pickup_widget;
use WCMultiShipping\inc\front\pickup\mondial_relay\mondial_relay_pickup_widget;
use WCMultiShipping\inc\front\pickup\ups\ups_pickup_widget;

use WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_helper;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_helper;

use WCMultiShipping\inc\admin\classes\config\config_class;

defined( 'ABSPATH' ) || die( 'Restricted Access' );

class wms_front_init {
	public function __construct() {
		chronopost_pickup_widget::register_hooks();
		mondial_relay_pickup_widget::register_hooks();
		ups_pickup_widget::register_hooks();

		add_action( 'update_wms_statuses', function () {
			mondial_relay_helper::update_wms_statuses();
			chronopost_helper::update_wms_statuses();
		} );

		if ( ! wp_next_scheduled( 'update_wms_statuses' ) )
			wp_schedule_event( time(), 'hourly', 'update_wms_statuses' );


		if ( ! wp_next_scheduled( 'check_wms_license' ) )
			wp_schedule_event( time(), 'hourly', 'check_wms_license' );


	}

	public function generate_labels( $order_id, $status_from, $status_to, $order ) {

		$order_statuses = get_option( 'wms_chronopost_section_label_generation_status', '' );

		if ( empty( $order_statuses ) || $status_from === $status_to ) {
			return;
		}

		if ( in_array( $status_to, $order_statuses ) || in_array( 'wc-' . $status_to, $order_statuses ) ) {
			$parcel_class = new parcel_class();
			$parcel_class->generate_labels( $order_id, $status_from, $status_to, $order );
		}
	}
}
