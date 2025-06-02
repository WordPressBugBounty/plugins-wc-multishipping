<?php

namespace WCMultiShipping\inc\admin\partials\shipping;

class wms_partial_shipping_rates {

	var $shipping_method = object;

	var $shipping_classes = [];

	var $current_rates = [];

	var $all_shipping_class_code = \WCMultiShipping\inc\shipping_methods\chronopost\chronopost_abstract_shipping::WMS_ALL_SHIPPING_CLASS_CODE;


	function display_table() {
		$shipping = new \WC_Shipping();
		$shipping_classes = $shipping->get_shipping_classes();

		$select_options = '<option value="all" selected="selected">' . esc_html__( 'All products', 'wc-multishipping' ) . '</option>';

		foreach ( $shipping_classes as $oneClass ) {
			$select_options .= '<option value="' . wms_display_value( $oneClass->term_id ) . '">' . esc_html__( $oneClass->name ) . '</option>';
		}

		wp_register_script(
			'wms_shipping_rates_script',
			WMS_ADMIN_JS_URL . 'chronopost/chronopost_shipping_rates.js?t=' . time(),
			[ 'jquery', 'wp-i18n' ],
			1.0,
			false
		);
		$wms_global_var = [ 
			'plugin_url' => plugins_url( '', dirname( __FILE__ ) ),
			'confirm_deletion_txt' => __( 'Delete the selected rates?', 'wc-multishipping' ),
			'shipping_classes_options' => $select_options,
		];

		wp_localize_script( 'wms_shipping_rates_script', 'wms_global_var', $wms_global_var );
		wp_localize_script( 'wms_shipping_rates_script', 'woo_unit', array( wms_display_value( get_option( 'woocommerce_weight_unit' ) ) ) );
		wp_enqueue_script( 'wms_shipping_rates_script' );

		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Shipping rates', 'wc-multishipping' ); ?></th>
			<td class="forminp" id="<?php echo wms_display_value( $this->shipping_method->id ); ?>_shipping_rates">
				<table class="shippingrows widefat" cellspacing="0">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox"></td>
							<th>
								<?php esc_html_e( 'From ', 'wc-multishipping' ); ?>
								<span class="condition_unit"></span>
								<span class="woocommerce-help-tip"
									data-tip="<?php esc_html_e( 'Included', 'wc-multishipping' ); ?>"
									title="<?php esc_html_e( 'Included', 'wc-multishipping' ); ?>"></span>
							</th>
							<th>
								<?php esc_html_e( 'To', 'wc-multishipping' ); ?>
								<span class="condition_unit"></span>
								<span class="woocommerce-help-tip"
									data-tip="<?php esc_html_e( 'Excluded', 'wc-multishipping' ); ?>"
									title="<?php esc_html_e( 'Excluded', 'wc-multishipping' ); ?>"></span>
							</th>
							<th>
								<?php esc_html_e( 'Shipping class', 'wc-multishipping' ); ?>
							</th>
							<th>
								<?php esc_html_e( 'Price', 'wc-multishipping' ); ?>
							</th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<th colspan="5">
								<a class="add button" id="wms_shipping_rates_add"
									style="margin-left: 24px"><?php esc_html_e( 'Add rate', 'wc-multishipping' ); ?></a>
								<a class="remove button"
									id="wms_shipping_rates_remove"><?php esc_html_e( 'Delete selected', 'wc-multishipping' ); ?></a>
							</th>
						</tr>
					</tfoot>
					<tbody class="table_rates">
						<?php

						foreach ( $this->current_rates as $i => $rate ) {
							?>
							<tr>
								<td class="check-column"><input type="checkbox" /></td>
								<td style="text-align: center">
									<input type="number" class="input-number regular-input" step="0.01" min="0" required
										value="<?php echo isset( $rate['min'] ) ? wms_display_value( $rate['min'] ) : ''; ?>"
										name="shipping_rates[<?php echo wms_display_value( $i ); ?>][min]" />
								</td>
								<td style="text-align: center">
									<input type="number" class="input-number regular-input" step="0.01" min="0" required
										value="<?php echo isset( $rate['max'] ) ? wms_display_value( $rate['max'] ) : ''; ?>"
										name="shipping_rates[<?php echo wms_display_value( $i ); ?>][max]" />
								</td>
								<td style="text-align: center">
									<select style="width: auto; max-width: 10rem"
										name="shipping_rates[<?php echo wms_display_value( $i ); ?>][shipping_class][]"
										multiple="multiple" class="wms__shipping_rates__shipping_class__select">
										<option value="<?php echo wms_display_value( $this->all_shipping_class_code ); ?>" <?php echo empty( $rate['shipping_class'] ) || in_array(
										   	$this->all_shipping_class_code,
										   	$rate['shipping_class']
										   ) ? 'selected="selected"' : ''; ?>>
											<?php
											esc_html_e( 'All products', 'wc-multishipping' );
											?>
										</option>
										<?php
										foreach ( $this->shipping_classes as $oneClass ) {
											echo '<option value="' . wms_display_value( $oneClass->term_id ) . '" ' . ( isset( $rate['shipping_class'] ) && in_array(
												$oneClass->term_id,
												$rate['shipping_class']
											) ? 'selected="selected"' : '' ) . '>' . wms_display_value( $oneClass->name ) . '</option>';
										}
										?>
									</select>
								</td>
								<td style="text-align: center">
									<input type="number" class="input-number regular-input" step="0.01" min="0" required
										value="<?php echo wms_display_value( $rate['price'] ); ?>"
										name="shipping_rates[<?php echo wms_display_value( $i ); ?>][price]" />
								</td>
							</tr>
							<?php
						} ?>
					</tbody>
				</table>
			</td>
		</tr>
		<style>
			label {
				font-weight: bold;
			}

			fieldset {
				margin-bottom: 0.5rem;
				margin-top: 0.5rem;
			}

			.select2 {
				width: auto !important;
			}
		</style>
		<?php
	}
}

?>