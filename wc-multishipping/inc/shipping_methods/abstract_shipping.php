<?php


namespace WCMultiShipping\inc\shipping_methods;

use WCMultiShipping\inc\admin\partials\shipping\wms_partial_shipping_rates;

abstract class abstract_shipping extends \WC_Shipping_Method {
	const WMS_ALL_SHIPPING_CLASS_CODE = 'all';

	protected $country_capabilities;

	protected $product_code;

	protected $return_product_code;

	public function __construct( $instance_id = 0 ) {
		$this->instance_id = absint( $instance_id );
		$this->supports = [ 
			'shipping-zones',
			'instance-settings',
		];
		$this->init();
	}

	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title' );
	}

	public function init_form_fields() {
		$this->instance_form_fields = [];
	}

	public function generate_pricing_condition_radio_html( $key, $data ) {
		$pricing_condition = $this->get_option( 'pricing_condition', 'weight' );

		$weight_pricing_condition = ( 'weight' == $pricing_condition || empty( $pricing_condition ) ) ? 'checked' : '';
		$cart_amount_pricing_condition = ( 'cart_amount' == $pricing_condition ) ? 'checked' : '';

		$field_key = $this->get_field_key( $key );

		ob_start();
		?>


		<tr valign="top">

			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok.
					  		?></label>
			</th>
			<td>
				<fieldset>
					<input type="radio" id="order_weight" value="weight" name="pricing_condition" <?php echo $weight_pricing_condition ?>>
					<label for="order_weight"><?php esc_html_e( 'Order Weight', 'wc-multishipping' ); ?></label>

					<input type="radio" id="cart_amount" value="cart_amount" name="pricing_condition" <?php echo $cart_amount_pricing_condition ?>>
					<label for="cart_amount"><?php esc_html_e( 'Order Amount', 'wc-multishipping' ) ?></label>
				</fieldset>
			</td>
		</tr>


		<?php
		return ob_get_clean();
	}

	public function generate_free_shipping_condition_html( $key, $data ) {
		$free_shipping_condition = $this->get_option( 'free_shipping_condition', '' );

		$field_key = $this->get_field_key( $key );

		ob_start();
		?>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok.
					  		?></label>
			</th>
			<td>
				<fieldset>
					<input type="number" min="0" step="0.01" id="<?php echo esc_attr( $field_key ); ?>"
						name="free_shipping_condition" value="<?php echo $free_shipping_condition; ?>">
				</fieldset>
			</td>
		</tr>

		<?php
		return ob_get_clean();
	}

	public function validate_pricing_condition_field( $key ) {
		$pricing_condition = $this->get_post_data()[ $key ];
		if ( ! in_array( $pricing_condition, [ 'weight', 'cart_amount' ] ) )
			return 'weight';

		return $pricing_condition;
	}

	public function validate_free_shipping_condition_field( $key ) {
		$free_shipping_condition = $this->get_post_data()[ $key ];

		if ( empty( $free_shipping_condition ) )
			return '';

		return floatval( $free_shipping_condition );
	}


	public function generate_shipping_rates_html() {
		$shipping = new \WC_Shipping();

		$shipping_rates_table = new wms_partial_shipping_rates();
		$shipping_rates_table->shipping_method = $this;
		$shipping_rates_table->shipping_classes = $shipping->get_shipping_classes();

		$shipping_rates_table->current_rates = $this->get_option(
			'shipping_rates', [ 
				[ 
					'min' => 0,
					'max' => 10,
					'shipping_class' => [ self::WMS_ALL_SHIPPING_CLASS_CODE ],
					'price' => 10,
				],
			]
		);

		ob_start();

		$shipping_rates_table->display_table();

		return ob_get_clean();
	}


	public function validate_shipping_rates_field( $key ) {
		$result = [];

		if ( empty( $this->get_post_data()[ $key ] ) )
			return;

		foreach ( $this->get_post_data()[ $key ] as $rate ) {

			$min = (float) str_replace( ',', '.', $rate['min'] );
			$max = (float) str_replace( ',', '.', $rate['max'] );

			$min = max( $min, 0 );
			$max = max( $min, $max, 0 );

			$item = [ 
				'min' => $min,
				'max' => $max,
				'shipping_class' => empty( $rate['shipping_class'] ) ? [ self::WMS_ALL_SHIPPING_CLASS_CODE ] : $rate['shipping_class'],
				'price' => (float) str_replace( ',', '.', $rate['price'] ),
			];

			$result[] = $item;
		}

		usort(
			$result, function ($a, $b) {
				$result = 0;

				if ( $a['min'] > $b['min'] ) {
					$result = 1;
				} else {
					if ( $a['min'] < $b['min'] ) {
						$result = -1;
					}
				}

				return $result;
			}
		);

		return $result;
	}

	public function get_rates() {
		return $this->get_option( 'shipping_rates', [] );
	}

	public function get_pricing_condition() {
		return $this->get_option( 'pricing_condition', '' );
	}

	public function get_product_code() {
		return $this->product_code;
	}

	public function get_return_product_code() {
		return $this->return_product_code;
	}


	public function calculate_shipping( $package = [] ) {
		$rate = [ 
			'id' => $this->get_rate_id(),
			'label' => $this->title,
			'cost' => '',
		];
		$this->add_rate( $rate );
	}
}
