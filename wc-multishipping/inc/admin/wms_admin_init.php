<?php

namespace WCMultiShipping\inc\admin;

use WCMultiShipping\inc\admin\classes\chronopost\chronopost_helper;

use WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_helper;
use WCMultiShipping\inc\admin\classes\ups\ups_helper;
use WCMultiShipping\inc\admin\partials\orders\wms_orders_list_table;

use WCMultiShipping\inc\front\pickup\chronopost\chronopost_pickup_widget;
use WCMultiShipping\inc\front\pickup\mondial_relay\mondial_relay_pickup_widget;
use WCMultiShipping\inc\front\pickup\ups\ups_pickup_widget;

defined( 'ABSPATH' ) || die( 'Restricted Access' );

class wms_admin_init {

	public function __construct() {
		add_action(
			'admin_notices',
			function () {
				wms_display_messages();
			}
		);

		add_filter( 'plugin_action_links', [ $this, 'wms_action_links' ], 10, 2 );

		chronopost_helper::register_hooks();
		mondial_relay_helper::register_hooks();
		ups_helper::register_hooks();

		chronopost_pickup_widget::register_hooks();
		mondial_relay_pickup_widget::register_hooks();
		ups_pickup_widget::register_hooks();


	}


	function wms_action_links( $links, $file ) {
		static $this_plugin;

		if ( ! $this_plugin )
			$this_plugin = 'wc-multishipping/wc-multishipping.php';


		if ( $file == $this_plugin ) {
			$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping' ) . '">' . __( 'Settings', 'wc-multishipping' ) . '</a>';

			array_unshift( $links, $settings_link );
		}

		return $links;
	}


}
