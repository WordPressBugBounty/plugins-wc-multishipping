<?php

namespace WCMultiShipping\inc\admin\partials\orders\abstract_classes;


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class wms_orders_list_table extends \WP_List_Table {
	const SHIPPING_PROVIDER_NAME = '';

	const BULK_ACTION_GENERATE_OUTWARD = 'bulk-label_generate_outward';
	const BULK_ACTION_GENERATE_INWARD = 'bulk-label_generate_inward';
	const BULK_ACTION_DOWNLOAD = 'bulk-label_download';
	const BULK_ACTION_PRINT = 'bulk-label_print';
	const BULK_ACTION_DELETE = 'bulk-label_delete';

	const CHECKBOX_IDS = 'bulk-wms_cb_id';

	public $helper_class;

	protected function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%s[]" value="%s" />',
			self::CHECKBOX_IDS,
			$item['wms_data_id']
		);
	}

	public function prepare_items() {
		$this->process_bulk_action();



		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();
		$current_page = $this->get_pagenum();
		$user = get_current_user_id();
		$screen = get_current_screen();

		$option = $screen->get_option( 'per_page', 'option' );
		$per_page = get_user_meta( $user, $option, true );

		if ( empty( $per_page ) || $per_page < 1 ) {
			$per_page = $screen->get_option( 'per_page', 'default' );
		}

		$filters = $this->get_listing_filters();

		$args = [];
		$args['orderby'] = wms_get_var( 'string', 'orderby', '' );
		$args['order'] = wms_get_var( 'string', 'order', '' );

		$helper_class = $this->helper_class;
		$order_class = $helper_class->get_order_class();

		$this->_column_headers = [ $columns, $hidden, $sortable ];
		$this->items = $this->get_data( $current_page, $per_page, $args, $filters );
		$total_order = $order_class::count_all_orders();

		$this->set_pagination_args( [ 
			'total_items' => $total_order,
			'per_page' => $per_page,
		] );
	}


	protected function get_order_edit_link( $order_id ) {
		$orderUrl = ! empty( $order_id ) ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '';

		return '<a href="' . $orderUrl . '">' . $order_id . '</a>';
	}

	function display_table() {
		wp_enqueue_script( 'wms_chronopost_settings', WMS_ADMIN_JS_URL . 'chronopost/chronopost_print_label.js?t=' . time(), [ 'jquery', 'wp-i18n' ] );
		?>

		<div class="wrap">
			<?php
			$this->display_headers();
			echo '<h1>' . sprintf( __( 'Your %s orders', 'wc-multishipping' ), static::SHIPPING_PROVIDER_NAME ) . '</h1>';
			echo '<div style="font-weight: 600;margin: 1.33em 0;">' . sprintf( __( 'On this page you can see all the confirmed order using %s as shipping method. You can also create, print, download your shipping labels.', 'wc-multishipping' ), static::SHIPPING_PROVIDER_NAME ) . '</div>';

			wms_display_messages( true );

			echo '<div style="margin-left: 15px; display:inline">';
			echo '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=email' ) . '" target="_blank" class="button">' . esc_html__( 'Edit tracking notification email', 'wc-multishipping' ) . '</a>';
			echo '</div>';
			echo '<div style="margin-left: 15px; display:inline"">';
			echo '<a href="https://www.wcmultishipping.com/contact?utm_source=wms_plugin&utm_campaign=contact&utm_medium=wms_listing_button" target="_blank" class="button">' . esc_html__( 'Get help from support', 'wc-multishipping' ) . '</a>';
			echo '</div>';
			echo '<div style="margin-left: 15px; display:inline">';
			echo '<a href="https://www.wcmultishipping.com/fr/docs?utm_source=wms_plugin&utm_campaign=check_doc&utm_medium=wms_listing_button" target="_blank" class="button">' . esc_html__( 'Check documentation', 'wc-multishipping' ) . '</a>';
			echo '</div>';
			echo '<div style="margin-left: 15px; display:inline">';
			echo '<a href="https://www.wcmultishipping.com/fr/tarifs?utm_source=wms_plugin&utm_campaign=go_pro&utm_medium=wms_listing_button" target="_blank" class="button">' . esc_html__( 'Upgrade to Pro version', 'wc-multishipping' ) . '</a>';
			echo '</div>';
			?>
			<form method="post">
				<?php
				if ( isset( $_REQUEST['page'] ) ) {
					?>
					<input type="hidden" name="page" value="<?php echo wms_display_value( wp_unslash( $_REQUEST['page'] ) ); ?>" />
					<?php
				}
				$this->search_box( 'Search', 'search_id' );
				$this->display();
				?>
			</form>

		</div>

		<?php
	}

	function extra_tablenav( $which ) {
		if ( 'top' === $which ) {

			ob_start();

			$this->add_shipping_method_filter();
			$this->add_country_filter();
			$this->add_woo_status_filter();

			$output = ob_get_clean();

			if ( ! empty( $output ) ) {
				echo $output;
				submit_button( __( 'Filter', 'wc-multishipping' ), '', 'filter_action', false, [ 'id' => 'post-query-submit' ] );
			}
		}
		if ( $which == "bottom" ) {

		}
	}

	private function add_shipping_method_filter() {
		$available_shipping_methods = [ '' => __( 'Shipping Methods', 'wc-multishipping' ) ];

		$helper_class = $this->helper_class;
		$order_class = $helper_class->get_order_class();


		$available_shipping_methods = array_merge( $available_shipping_methods, $order_class::AVAILABLE_SHIPPING_METHODS );

		$selected_shipping_method = wms_get_var( 'string', 'shipping_methods', '' );

		?>
		<select name="shipping_methods" id="wms_shipping_method_filter" style="height:100%;">
			<?php
			foreach ( $available_shipping_methods as $value => $label ) { ?>
				<option value="<?php echo wms_display_value( $value ); ?>" <?php echo ( $value === $selected_shipping_method ) ? 'selected' : ''; ?>><?php echo wms_display_value( $label ); ?>
				</option>
			<?php } ?>
		</select>

		<?php
	}


	private function add_country_filter() {
		$helper_class = $this->helper_class;
		$order_class = $helper_class->get_order_class();

		$countries = array_column( $order_class::get_all_countries(), "country" );
		if ( empty( $countries ) )
			return;

		$countries_option = [ '' => __( 'Country', 'wc-multishipping' ) ];
		array_walk( $countries, function ($one_country) use (&$countries_option) {
			if ( ! empty( $one_country ) )
				$countries_option[ $one_country ] = WC()->countries->countries[ $one_country ];
		} );

		$selected_country = wms_get_var( 'cmd', 'shipping_country', '' );

		?>
		<select name="shipping_country" id="wms_country_filter" style="height:100%;">
			<?php
			foreach ( $countries_option as $value => $label ) { ?>
				<option value="<?php echo wms_display_value( $value ); ?>" <?php echo ( $value === $selected_country ) ? 'selected' : ''; ?>><?php echo wms_display_value( $label ); ?>
				</option>
			<?php } ?>
		</select>

		<?php
	}

	private function add_woo_status_filter() {
		$helper_class = $this->helper_class;
		$order_class = $helper_class->get_order_class();

		$status = $order_class::get_all_woo_status();
		if ( empty( $status ) )
			return;

		$status_options = [ '' => __( 'Order Status', 'wc-multishipping' ) ];
		array_walk( $status, function ($one_status) use (&$status_options) {
			$status_options[ $one_status ] = wc_get_order_status_name( $one_status );
		} );

		$selected_woo_status = wms_get_var( 'string', 'woo_status', '' );

		?>
		<select name="woo_status" id="wms_woo_status_filter" style="height:100%;">
			<?php
			foreach ( $status_options as $value => $label ) { ?>
				<option value="<?php echo wms_display_value( $value ); ?>" <?php echo ( $value === $selected_woo_status ) ? 'selected' : ''; ?>><?php echo wms_display_value( $label ); ?>
				</option>
			<?php } ?>
		</select>

		<?php
	}

	protected function get_formated_tracking_numbers( $orders_id = [] ) {
		$rendered_tracking_numbers_by_orders = [];

		$helper_class = $this->helper_class;
		$parcel_class = $helper_class->get_parcel_class();


		$tracking_numbers_by_orders = $parcel_class::get_formated_tracking_number_from_orders( $orders_id );
		if ( empty( $tracking_numbers_by_orders ) )
			return false;

		$helper_class = $this->helper_class;
		$label_class = $helper_class->get_label_class();

		foreach ( $tracking_numbers_by_orders as $one_order_id => $one_order ) {
			if ( ! is_array( $one_order ) )
				continue;

			$rendered_tracking_numbers_by_orders[ $one_order_id ] = '<div class="wms__orders_listing__tracking-numbers">';
			foreach ( $one_order as $one_parcel_number => $one_parcel ) {
				if ( ! is_array( $one_order ) )
					continue;

				foreach ( $one_parcel as $one_label_type => $one_label ) {
					if ( empty( $one_label->tracking_number ) )
						continue;
					if ( '_wms_outward_parcels' == $one_label_type ) {

						$rendered_tracking_numbers_by_orders[ $one_order_id ] .= '
                        <span class="wms__orders_listing__tracking-number" data-tracking-number="' . $one_label->tracking_number . '" data-label-type="outward">' . '
                        <span class="wms__orders_listing__tracking_number--outward">' . $one_label->tracking_number . '</span>' . '
                        <span class="dashicons dashicons-download wms_download_label" ' . $this->get_label_download_attr( $one_label->tracking_number ) . '></span>' . '
                        <span class="dashicons dashicons-printer wms_print_label" ' . $this->get_label_print_attr(
									$one_label->tracking_number,
										$label_class::LABEL_FORMAT_PDF
								) . ' ></span>' . '
                        <span class="dashicons dashicons-trash wms_delete_label" ' . $this->get_label_deletion_attr( $one_label->tracking_number ) . '></span>' . '
                        </span>
                        <br>';
					} else {
						$rendered_tracking_numbers_by_orders[ $one_order_id ] .= '
                    	<span class="wms__orders_listing__tracking-number" data-tracking-number="' . $one_label->tracking_number . '" data-label-type="inward">' . '
							<span class="dashicons dashicons-undo wms__orders_listing__inward_logo"></span>' . '
							<span class="wms__orders_listing__tracking_number--inward">' . $one_label->tracking_number . '</span>' . '
							<span class="dashicons dashicons-download wms_download_label" ' . $this->get_label_download_attr( $one_label->tracking_number ) . '></span>' . '
							<span class="dashicons dashicons-printer wms_print_label" ' . $this->get_label_print_attr(
									$one_label->tracking_number,
										$label_class::LABEL_FORMAT_PDF
								) . '></span>' . '
							<span class="dashicons dashicons-trash wms_delete_label" ' . $this->get_label_deletion_attr( $one_label->tracking_number ) . '></span>' . '
						</span>
						<br>';
					}
					$rendered_tracking_numbers_by_orders[ $one_order_id ] .= '<br>';
				}
			}
			$rendered_tracking_numbers_by_orders[ $one_order_id ] .= '</div>';
		}

		return $rendered_tracking_numbers_by_orders;
	}


	protected function get_label_download_attr( $tracking_number ) {
		if ( empty( $tracking_number ) )
			return '';

		$helper_class = $this->helper_class;
		$label_class = $helper_class->get_label_class();

		$outward_label_download_link = $label_class::get_url_for_download_label( $tracking_number );
		if ( empty( $outward_label_download_link ) )
			return '';

		return 'data-link="' . wms_display_value( $outward_label_download_link ) . '" title="' . esc_html__( 'Download label', 'wc-multishipping' ) . '"';
	}

	protected function get_label_print_attr( $tracking_number, $format ) {
		if ( empty( $tracking_number ) || empty( $format ) )
			return '';

		$helper_class = $this->helper_class;
		$label_class = $helper_class->get_label_class();

		$outward_label_print_link = $label_class::get_url_for_print_labels( $tracking_number );
		if ( empty( $outward_label_print_link ) )
			return '';

		return 'data-link="' . wms_display_value( $outward_label_print_link ) . '" data-label-type="outward" ' . 'data-format="' . wms_display_value( $format ) . '" ' . 'title="' . esc_html__( 'Print label', 'wc-multishipping' ) . '"';
	}

	protected function get_label_deletion_attr( $tracking_number ) {
		if ( empty( $tracking_number ) )
			return '';

		$helper_class = $this->helper_class;
		$label_class = $helper_class->get_label_class();

		$outward_label_delete_link = $label_class::get_url_for_delete_label( $tracking_number );
		if ( empty( $outward_label_delete_link ) )
			return '';

		return 'data-link="' . wms_display_value( $outward_label_delete_link ) . '" ' . 'title="' . esc_html__( 'Delete outward label', 'wc-multishipping' ) . '"';
	}

	protected function bulk_generate_outward_labels( $order_ids ) {
		if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
			wms_enqueue_message( __( 'Unable to generate label => No order selected.', 'wc-multishipping' ), 'error' );

			return false;
		}

		foreach ( $order_ids as $one_order_id ) {
			$order = wc_get_order( $one_order_id );

			$helper_class = $this->helper_class;
			$order_class = $helper_class->get_order_class();

			if ( $order_class::register_parcels_labels( $order, $return = false ) ) {
				wms_enqueue_message( sprintf( __( 'Outward labels for order n°%s successfully generated.', 'wc-multishipping' ), $one_order_id ), 'success' );
			} else
				wms_enqueue_message( sprintf( __( 'Error while generating outward labels for order n°%s.', 'wc-multishipping' ), $one_order_id ), 'error' );
		}
	}

	protected function bulk_generate_inward_labels( $order_ids ) {
		if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
			wms_enqueue_message( __( 'Unable to generate label => No order selected.', 'wc-multishipping' ), 'error' );

			return false;
		}
		if ( empty( $order_ids ) || ! is_array( $order_ids ) )
			return;

		foreach ( $order_ids as $one_order_id ) {
			$order = wc_get_order( $one_order_id );

			$helper_class = $this->helper_class;
			$order_class = $helper_class->get_order_class();

			if ( $order_class::register_parcels_labels( $order, $return = true ) ) {
				wms_enqueue_message( sprintf( __( 'Inward labels for order n°%s successfully generated.', 'wc-multishipping' ), $one_order_id ), 'success' );
			} else
				wms_enqueue_message( sprintf( __( 'Error while generating inward labels for order n°%s.', 'wc-multishipping' ), $one_order_id ), 'error' );
		}
	}

	protected function bulk_download_labels( $order_ids ) {
		if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
			wms_enqueue_message( __( 'Unable to download label => No order selected.', 'wc-multishipping' ), 'error' );

			return false;
		}

		$helper_class = $this->helper_class;
		$parcel_class = $helper_class->get_parcel_class();

		$tracking_numbers = $parcel_class::get_tracking_numbers_from_order_ids( $order_ids );
		if ( empty( $tracking_numbers ) || ! is_array( $tracking_numbers ) ) {
			wms_enqueue_message( __( 'Unable to download label => No tracking number found', 'wc-multishipping' ), 'error' );

			return false;
		}

		$label_class = $helper_class->get_label_class();

		wp_redirect( $label_class::get_url_for_download_labels_zip( $tracking_numbers ) );
	}

	protected function bulk_print_labels( $order_ids ) {
		if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
			wms_enqueue_message( __( 'Unable to print label => No order selected.', 'wc-multishipping' ), 'error' );

			return false;
		}

		$helper_class = $this->helper_class;
		$parcel_class = $helper_class->get_parcel_class();

		$tracking_numbers = $parcel_class::get_tracking_numbers_from_order_ids( $order_ids );
		if ( empty( $tracking_numbers ) || ! is_array( $tracking_numbers ) ) {
			wms_enqueue_message( __( 'Unable to print label => No tracking number found for selected orders', 'wc-multishipping' ), 'error' );

			return false;
		}

		$label_class = $helper_class->get_label_class();

		$label_print_url = $label_class::get_url_for_print_labels( $tracking_numbers );

		echo <<<END_PRINT_SCRIPT
<script type="text/javascript">
        jQuery(function ($) {
            $(document).ready(function(){
                wms_print_PDF('$label_print_url');
            });
        });
</script>
END_PRINT_SCRIPT;
	}

	protected function bulk_delete_label( $order_ids ) {
		if ( empty( $order_ids ) || ! is_array( $order_ids ) ) {
			wms_enqueue_message( __( 'Unable to delete label => No order selected.', 'wc-multishipping' ), 'error' );

			return false;
		}

		$helper_class = $this->helper_class;
		$parcel_class = $helper_class->get_parcel_class();

		$tracking_numbers = $parcel_class::get_tracking_numbers_from_order_ids( $order_ids );
		if ( empty( $tracking_numbers ) || ! is_array( $tracking_numbers ) ) {
			wms_enqueue_message( __( 'Unable to delete label => No tracking number found for selected orders', 'wc-multishipping' ), 'error' );

			return false;
		}
		$label_class = $helper_class->get_label_class();

		wp_redirect( $label_class::get_url_for_delete_label( $tracking_numbers ) );
	}
}


