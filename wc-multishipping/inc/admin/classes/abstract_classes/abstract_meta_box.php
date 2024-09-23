<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;

use WCMultiShipping\inc\admin\classes\label_class;
use WCMultiShipping\inc\admin\classes\parcel_class;

abstract class abstract_meta_box {

	const SHIPPING_PROVIDER_ID = '';

	var $order;

	var $order_id = 0;

	var $shipping_method_id = '';

	var $helper;

	abstract protected function __construct();

	abstract protected static function save_meta_box_values( $post_id );

	abstract protected function display_parcel_information();


	protected function display_shipment_data() {
		$order = wc_get_order( $this->order_id );
		$shipment_data = $order->get_meta( '_wms_' . static::SHIPPING_PROVIDER_ID . '_shipment_data', true );

		$skybill_number_list = [];
		$download_link = [];
		$tracking_numbers = [];

		$parcel_class = $this->helper->get_parcel_class();
		$label_class = $this->helper->get_label_class();


		$label_types = [ '_wms_outward_parcels', '_wms_inward_parcels' ];
		foreach ( $label_types as $one_label_type ) {
			if ( empty( $shipment_data[ $one_label_type ]['_wms_parcels'] ) )
				continue;

			foreach ( $shipment_data[ $one_label_type ]['_wms_parcels'] as $one_shipment ) {

				$meta_name = '_wms_parcel_skybill_number';
				if ( empty( $one_shipment[ $meta_name ] ) )
					continue;

				$tracking_numbers[ $one_label_type ][] = $one_shipment[ $meta_name ];

				$tracking_URL = $parcel_class::get_tracking_url( $one_shipment[ $meta_name ] );
				$skybill_number_list[ $one_label_type ][] = '<a target="_blank" href="' . wms_display_value( $tracking_URL ) . '">' . wms_display_value(
					$one_shipment['_wms_parcel_skybill_number']
				) . '</a>';
			}
			if ( ! empty( $tracking_numbers[ $one_label_type ] ) ) {
				$download_link[ $one_label_type ] = ( count( $tracking_numbers[ $one_label_type ] ) <= 1 ) ? $label_class::get_url_for_download_label( $tracking_numbers[ $one_label_type ][0] ) : $label_class::get_url_for_download_labels_zip( $tracking_numbers[ $one_label_type ] );
			}
		}

		?>

		<tbody id="wms_meta_box_shipment_data">
			<?php if ( ! empty( $skybill_number_list['_wms_outward_parcels'] ) ) : ?>
				<tr>
					<td colspan="2">
						<h4 style="text-align:center"><?php esc_html_e( 'Outward Labels', 'wc-multishipping' ) ?></h4>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<div>
							<div class="wms_chrono_tracking_numbers">
								<?php echo implode( '<br/> ', $skybill_number_list['_wms_outward_parcels'] ); ?>
							</div>
							<a class="button button-small" target="_blank"
								href="<?php echo wms_display_value( $download_link['_wms_outward_parcels'] ); ?>">
								<?php echo esc_html_e( 'Download outward labels', 'wc-multishipping' ); ?>
							</a>
						</div>
					</td>
				</tr>
			<?php endif; ?>
			<?php if ( ! empty( $skybill_number_list['_wms_inward_parcels'] ) ) : ?>
				<tr>
					<td colspan="2">
						<h4 style="text-align:center"><?php esc_html_e( 'Inward Labels', 'wc-multishipping' ) ?></h4>
						<div>
							<div class="wms_chrono_tracking_numbers">
								<?php echo implode( '<br/> ', $skybill_number_list['_wms_inward_parcels'] ); ?>
							</div>
							<a class="button button-small" target="_blank"
								href="<?php echo wms_display_value( $download_link['_wms_inward_parcels'] ); ?>">
								<?php echo esc_html_e( 'Download inward label', 'wc-multishipping' ); ?>
							</a>
						</div>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
		<?php
	}

	protected function display_generate_outward_label_button() {
		?>
		<tbody id="wms_meta_box_generate_outward_label_button">
			<tr>
				<td style="text-align:center" colspan="2">
					<?php
					if ( ! wms_table_exists() ) {
						wms_enqueue_message(
							__(
								'You need WcMultishipping Pro Version to generate labels from your WordPress website. Please consider upgrading or use your shipping provider website to generate the label',
								'wc-multishipping'
							)
						);
						echo '<a href="https://www.wcmultishipping.com/fr/tarifs?utm_source=wms_plugin&utm_campaign=go_pro&utm_medium=wms_metabox" target="_blank" class="button">' . esc_html__( 'Upgrade to Pro version', 'wc-multishipping' ) . '</a>';
					}
					wms_display_messages( true, false );
					?>
				</td>
			</tr>
			<tr>
				<td style="text-align:center" colspan="2">
					<button type="button" id="wms_generate_outward_label_button"
						class="button button-primary wms_generate_labels"
						data-order-id="<?php echo wms_display_value( $this->order->get_id() ); ?>" <?php if ( ! wms_table_exists() )
								 echo 'disabled'; ?>>
						<?php
						esc_html_e( 'Generate label', 'wc-multishipping' );
						?>
					</button>
				</td>
			</tr>
		</tbody>
		<?php
	}

	protected function display_generate_inward_label_button() {
		$order = wc_get_order( $this->order_id );
		$shipment_data = $order->get_meta( '_wms_' . static::SHIPPING_PROVIDER_ID . '_shipment_data', true );
		if ( empty( $shipment_data['_wms_outward_parcels']['_wms_parcels'] ) )
			return;

		?>
		<tbody id="wms_meta_box_generate_outward_label_button">
			<tr>
				<td style="text-align:center" colspan="2">
					<?php
					if ( ! wms_table_exists() ) {
						wms_enqueue_message(
							__(
								'You need WcMultishipping Pro Version to generate labels from your WordPress website. Please consider upgrading or use your shipping provider website to generate the label',
								'wc-multishipping'
							)
						);
						echo '<a href="https://www.wcmultishipping.com/fr/tarifs?utm_source=wms_plugin&utm_campaign=go_pro&utm_medium=wms_metabox" target="_blank" class="button">' . esc_html__( 'Upgrade to Pro version', 'wc-multishipping' ) . '</a>';
					}
					wms_display_messages( true, false );
					?>
				</td>
			</tr>
			<tr>
				<td style="text-align:center" colspan="2">
					<button type="submit" id="wms_generate_inward_label_button"
						class="button button-primary wms_generate_labels"
						data-order-id="<?php echo wms_display_value( $this->order->get_id() ); ?>" <?php if ( ! wms_table_exists() )
								 echo 'disabled'; ?>>
						<?php esc_html_e( 'Generate inward labels', 'wc-multishipping' ); ?>
					</button>
				</td>
			</tr>
		</tbody>
		<?php
	}

	protected function display_hidden_inputs() {
		?>
		<input type="hidden" name="wms_nonce" value="<?php echo wp_create_nonce( 'wms_generate_label_nonce' ) ?>" />
		<input type="hidden" name="wms_shipping_method" value="<?php echo static::SHIPPING_PROVIDER_ID; ?>" />
		<input type="hidden" name="wms_order_id" value="<?php echo wms_display_value( $this->order->get_id() ); ?>" />

		<?php
	}
}