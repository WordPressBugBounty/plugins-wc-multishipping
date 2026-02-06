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

		$chronopost_helper = new chronopost_helper();
		$mondial_relay_helper = new mondial_relay_helper();
		
		add_action( 'woocommerce_order_status_changed', function( $order_id, $status_from, $status_to, $order ) use ( $chronopost_helper, $mondial_relay_helper ) {
			$chronopost_helper->do_order_status_changed_actions( $order_id, $status_from, $status_to, $order );
			$mondial_relay_helper->do_order_status_changed_actions( $order_id, $status_from, $status_to, $order );
		}, 10, 4 );

		add_action( 'update_wms_statuses', function () {
			mondial_relay_helper::update_wms_statuses();
			chronopost_helper::update_wms_statuses();
		} );

		if ( ! wp_next_scheduled( 'update_wms_statuses' ) )
			wp_schedule_event( time(), 'hourly', 'update_wms_statuses' );


		if ( ! wp_next_scheduled( 'check_wms_license' ) )
			wp_schedule_event( time(), 'hourly', 'check_wms_license' );

		$this->register_woocommerce_emails();
	}

	private function register_woocommerce_emails() {
		add_action( 'woocommerce_email_classes', [ $this, 'generate_woocommerce_emails' ] );
	}

	public function generate_woocommerce_emails( $emails ) {
		$chronopost_helper = new chronopost_helper();
		$emails = $chronopost_helper->generate_woocommerce_email( $emails );

		$mondial_relay_helper_instance = new mondial_relay_helper();
		$emails = $mondial_relay_helper_instance->generate_woocommerce_email( $emails );

		return $emails;
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
