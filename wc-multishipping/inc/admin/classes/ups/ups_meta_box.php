<?php

namespace WCMultiShipping\inc\admin\classes\ups;

use WCMultiShipping\inc\admin\classes\abstract_classes\abstract_meta_box;

class ups_meta_box extends abstract_meta_box {
	const SHIPPING_PROVIDER_ID = 'ups';

	var $order;

	var $order_id = 0;

	var $helper;

	public function __construct() {
		$this->helper = new ups_helper();
	}

	public function wms_order_meta_box_display( $order ) {
		$this->order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->ID;
		$this->order = wc_get_order( $this->order_id );

		$this->shipping_method_id = ups_order::get_shipping_method_name( $this->order );
		if ( ! array_key_exists( $this->shipping_method_id, ups_order::AVAILABLE_SHIPPING_METHODS ) )
			return;

		wp_register_script( 'wms_meta_box', WMS_ADMIN_JS_URL . 'wms_meta_box.js?t=' . time(), [ 'jquery' ] );
		wp_localize_script( 'wms_meta_box', 'wmsajaxurl', admin_url( 'admin-ajax.php' ) );
		wp_enqueue_script( 'wms_meta_box' );

		?>

		<table class="widefat wms_meta_box_options">


			<?php $this->display_shipment_data(); ?>

			<?php $this->display_parcel_information(); ?>

			<?php $this->display_generate_outward_label_button(); ?>

			<?php $this->display_hidden_inputs(); ?>


		</table>

		<?php
	}

	protected function display_parcel_information() {
		$parcel_number_meta = $this->order->get_meta( '_wms_' . static::SHIPPING_PROVIDER_ID . '_parcels_number', true ) ?: 1;

		$parcel_class = $this->helper->get_parcel_class();
		$parcels_dimensions = $parcel_class::get_parcels_dimensions( $this->order );

		?>

		<tr id="wms_parcels_number">
			<td><?php esc_html_e( 'Parcels number', 'wc-multishipping' ) ?></td>
			<td class=wms_parcels_number">
				<input id="wms_parcels_number_input" name="_wms_ups_parcels_number" type="number"
					value="<?php echo wms_display_value( $parcel_number_meta ); ?>" min="1" style="width: 100%;"
					data-order-id="<?php echo wms_display_value( $this->order->get_id() ); ?>" />
			</td>
		</tr>
		<?php foreach ( $parcels_dimensions as $one_parcel_number => $one_parcel_dimensions ) : ?>

			<tbody <?php if ( $one_parcel_number == key( $parcels_dimensions ) )
				echo 'id="wms_meta_box_parcel_information"'; ?>
				class="wms_metabox_parcel_info">

				<tr>
					<td colspan="2">
						<div class="title">
							<?php esc_html_e( sprintf( "Parcel n°%d dimensions", $one_parcel_number + 1 ), 'wc-multishipping' ) ?>
						</div>
					</td>
				</tr>
				<?php
				$translations = [ 
					'weight' => __( 'Weight', 'wc-multishipping' ),
					'length' => __( 'Length', 'wc-multishipping' ),
					'height' => __( 'Height', 'wc-multishipping' ),
					'width' => __( 'Width', 'wc-multishipping' ),
				];

				foreach ( $one_parcel_dimensions as $one_dimension_label => $one_dimension_value ) : ?>
					<tr>
						<td><?php echo wms_display_value( $translations[ $one_dimension_label ] ); ?></td>
						<td>
							<input
								name="_wms_ups_parcels_dimensions[<?php echo wms_display_value( $one_parcel_number ); ?>][<?php echo wms_display_value( $one_dimension_label ); ?>]"
								type="number" class="default"
								placeholder="<?php echo ucfirst( __( $one_dimension_label, 'wc-multishipping' ) ) ?>"
								value="<?php echo wms_display_value( $one_dimension_value ); ?>" min="1" step="0.01"
								style="width: 100%;" data-order-id="<?php echo wms_display_value( $this->order->get_id() ); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		<?php endforeach; ?>

		<?php
	}

	public static function save_meta_box_values( $post_id ) {
		$wms_nonce = wms_get_var( 'cmd', 'wms_nonce', '' );
		if ( empty( $wms_nonce ) )
			return;
		if ( ! wp_verify_nonce( $wms_nonce, 'wms_generate_label_nonce' ) )
			return;

		$wms_parcels_number = wms_get_var( 'int', '_wms_ups_parcels_number', '0' );
		$wms_parcels_dimensions = wms_get_var( 'array', '_wms_ups_parcels_dimensions', [] );

		array_walk_recursive(
			$wms_parcels_dimensions, function ($one_param) {
				if ( ! is_numeric( $one_param ) )
					return;
			}
		);

		$order = wc_get_order( $post_id );
		$meta_to_update = [ 
			'_wms_ups_parcels_number' => $wms_parcels_number,
			'_wms_ups_parcels_dimensions' => $wms_parcels_dimensions,
		];

		$save_order = false;
		foreach ( $meta_to_update as $one_meta_to_update => $one_meta_value ) {

			if ( "array" != gettype( $one_meta_value ) )
				$one_meta_value = (string) $one_meta_value;

			if ( $order->get_meta( $one_meta_to_update, true ) !== $one_meta_value ) {
				$order->update_meta_data( $one_meta_to_update, $one_meta_value );
				$save_order = true;
			}
		}
		if ( ! empty( $save_order ) )
			$order->save();
	}
}