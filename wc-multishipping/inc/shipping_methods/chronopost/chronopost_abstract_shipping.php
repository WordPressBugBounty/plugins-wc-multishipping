<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

use WCMultiShipping\inc\admin\classes\chronopost\chronopost_shipping_methods;
use WCMultiShipping\inc\shipping_methods\abstract_shipping;
use WCMultiShipping\inc\admin\classes\chronopost\chronopost_api_helper;


class chronopost_abstract_shipping extends abstract_shipping {
	const WMS_ALL_SHIPPING_CLASS_CODE = 'all';

	protected $country_capabilities;

	protected $product_code;

	protected $return_product_code;

	public function init_form_fields() {

		$account_number = $this->get_option( 'method_account_number', false ) && $this->get_option( 'method_account_number', false ) != ''
			? $this->get_option( 'method_account_number', false )
			: get_option( 'wms_chronopost_account_number', '' );

		$account_name = $this->get_option( 'method_account_name', false )
			? $this->get_option( 'method_account_name', false )
			: get_option( 'wms_chronopost_account_name', '' );

		$account_password = $this->get_option( 'method_account_password', false )
			? $this->get_option( 'method_account_password', false )
			: get_option( 'wms_chronopost_account_password', '' );

		$this->instance_form_fields = [ 
			'title' => [ 
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'This controls the title seen by the user during checkout.', 'woocommerce' ),
				'default' => $this->method_title,
				'desc_tip' => true,
			],
			'title_if_free' => [ 
				'title' => __( 'Title if free', 'wc-multishipping' ),
				'type' => 'text',
				'description' => __( 'This controls the title seen by the user during checkout if shipping is free.', 'woocommerce' ),
				'default' => __( 'Free Shipping', 'wc-multishipping' ),
				'desc_tip' => true,
			],
			'method_account_number' => [ 
				'title' => __( 'Account number', 'wc-multishipping' ),
				'type' => 'text',
				'default' => $account_number,
				'desc_tip' => true,
			],
			'method_account_name' => [ 
				'title' => __( "Account name", "wc-multishipping" ),
				'type' => 'text',
				'default' => $account_name,
				'desc_tip' => true,
			],
			'method_account_password' => [ 
				'title' => __( 'Password', 'wc-multishipping' ),
				'type' => 'text',
				'default' => $account_password,
				'desc_tip' => true,
			],
			'pricing_condition' => [ 
				'title' => __( 'Pricing Condition', 'wc-multishipping' ),
				'type' => 'pricing_condition_radio',
				'description' => __( 'Decide whether pricing is calculated based on weight or cart amount', 'wc-multishipping' ),
				'default' => '',
				'desc_tip' => true,
			],
			'free_shipping' => [ 
				'title' => __( 'Always free', 'wc-multishipping' ),
				'type' => 'checkbox',
				'description' => __( 'Check if you want this shipping methods to be always free', 'wc-multishipping' ),
				'default' => '',
				'label' => ' ',
				'desc_tip' => true,
			],
			'free_shipping_condition' => [ 
				'title' => __( 'Free if the amount is superior than', 'wc-multishipping' ),
				'type' => 'free_shipping_condition',
				'description' => __( 'Set the minimal amount of the order for shipping price to be free', 'wc-multishipping' ),
				'default' => '',
				'desc_tip' => true,
			],
			'quickcost' => [ 
				'title' => __( 'Activate Quickcost', 'wc-multishipping' ),
				'type' => 'checkbox',
				'description' => __( 'Quickcost will calculate the amount of the shipping according to Chronopost rates', 'wc-multishipping' ),
				'default' => '',
				'desc_tip' => true,
			],
			'value_add_quickcost' => [ 
				'title' => __( 'Value to add to Quickcost', 'wc-multishipping' ),
				'type' => 'number',
				'description' => __( 'This value will be added to the estimated shipping amount return by Chronopost service', 'wc-multishipping' ),
				'default' => 0,
				'desc_tip' => true,
				'custom_attributes' => [ 
					'step' => 0.01,
					'min' => 0,
				],
			],
			'value_quickcost_type' => [ 
				'title' => __( 'Value type', 'wc-multishipping' ),
				'type' => 'select',
				'description' => __( 'Set if you want the value to be in percentage or a fix amount', 'wc-multishipping' ),
				'default' => '',
				'desc_tip' => true,
				'options' => [ 
					'percentage' => __( 'Percentage', 'wc-multishipping' ),
					'amount' => __( 'Fix amount', 'wc-multishipping' ),
				],
			],
			'management_fees' => [ 
				'title' => __( 'Add management fees', 'wc-multishipping' ),
				'type' => 'number',
				'description' => __( 'Add fix management fees to the shipping price', 'wc-multishipping' ),
				'default' => 0,
				'desc_tip' => true,
				'custom_attributes' => [ 
					'step' => 0.01,
					'min' => 0,
				],
			],
			'packaging_weight' => [ 
				'title' => __( 'Packaging weight (kg)', 'wc-multishipping' ),
				'type' => 'number',
				'description' => __( 'Add a fix weight (kg) to the order to include it in the final weight', 'wc-multishipping' ),
				'default' => 0,
				'desc_tip' => true,
				'custom_attributes' => [ 
					'min' => 0,
					'step' => 0.1,
				],
			],
			'shipping_rates' => [ 
				'title' => __( 'Rates', 'wc-multishipping' ),
				'type' => 'shipping_rates',
				'description' => __( 'Rates by weight', 'wc-multishipping' ),
				'default' => '',
				'desc_tip' => true,
			],
		];

		if ( ! in_array( $this->id, [ 'chronopost_express', 'chronopost_classic', 'chronopost_relais', 'chronopost_precise', 'chronopost_relais_europe', 'chronopost_relais_dom', 'chronopost_2shop', 'chronopost_2shop_europe' ] ) ) {

			$this->instance_form_fields['deliver_on_saturday'] = [ 
				'title' => __( 'Deliver on Saturday?', 'wc-multishipping' ),
				'type' => 'checkbox',
				'description' => __( 'Choose whether you want to allow shipment to be delivered on Saturday', 'wc-multishipping' ),
				'default' => '',
				'desc_tip' => true,
			];
		}
	}

	private function get_quickcost( $package, $total_weight ) {
		$account_number = get_option( 'wms_chronopost_account_number', '' );
		$account_password = get_option( 'wms_chronopost_account_password', '' );
		$departure_zip_code = get_option( 'wms_chronopost_departure_zip_code', '69002' );

		if ( empty( $account_password ) || empty( $account_number ) || empty( $package ) )
			return false;

		$chronopost_api_helper = new chronopost_api_helper();

		$data = $chronopost_api_helper->get_quick_cost(
			[ 
				'accountNumber' => $account_number,
				'password' => $account_password,
				'depCode' => $departure_zip_code,
				'arrCode' => $package['destination']['postcode'],
				'weight' => $total_weight,
				'productCode' => $this->get_product_code(),
				'type' => 'M',
			]
		);

		if ( empty( $data ) )
			return false;

		if ( empty( $data->amountTTC ) && $data->amountTTC != 0 )
			return false;

		$value_add_quickcost = $this->get_option( 'value_add_quickcost', 0 );
		$value_quickcost_type = $this->get_option( 'value_quickcost_type', 'percentage' );

		$final_amount = $data->amountTTC;

		if ( ! empty( $value_add_quickcost ) ) {
			$final_amount += 'percentage' == $value_quickcost_type ? ( $final_amount * $value_add_quickcost / 100 ) : $value_add_quickcost;
		}

		return $final_amount;
	}

	public function calculate_shipping( $package = [] ) {
		$cost = null;
		$debug_mode = get_option( 'wms_chronopost_debug_mode' );

		if ( chronopost_shipping_methods::get_one_country_capabilities_info( $package['destination']['country'], $this->id ) ) {
			$total_weight = floatval( $this->get_option( 'packaging_weight', 0 ) );
			$woocommerce_weight_unit = get_option( 'woocommerce_weight_unit' );
			if ( $woocommerce_weight_unit == 'g' && $total_weight > 0 )
				$total_weight = $total_weight * 1000;

			$total_price = get_option( 'wms_chronopost_price_before_discount', 'yes' ) === 'no' ? $package['contents_cost'] : $package['cart_subtotal'];

			$cart_shipping_classes = [];
			$rates = $this->get_rates();
			$pricing_condition = $this->get_pricing_condition();

			foreach ( $rates as $id => $one_rate ) {
				if ( isset( $one_rate['shipping_class'] ) && ! is_array( $one_rate['shipping_class'] ) ) {
					$rates[ $id ]['shipping_class'] = [ $one_rate['shipping_class'] ];
				}
			}

			foreach ( $package['contents'] as $item ) {
				$product = $item['data'];
				$total_weight += (float) $product->get_weight() * $item['quantity'];
				$cart_shipping_classes[] = $product->get_shipping_class_id();
			}

			$cart_shipping_classes = array_unique( $cart_shipping_classes );

			$free_shipping_amount = $this->get_option( 'free_shipping_condition', -1 );

			$quickcost = $this->get_option( 'quickcost', 'no' ) == 'yes';

			if ( $quickcost ) {
				$cost = $this->get_quickcost( $package, $total_weight );
				if ( $cost === false )
					return;
			} else {
				$matching_rates = [];

				foreach ( $rates as $one_rate ) {
					if ( ! empty( array_intersect( $one_rate['shipping_class'], $cart_shipping_classes ) ) || in_array( self::WMS_ALL_SHIPPING_CLASS_CODE, $one_rate['shipping_class'] ) ) {
						if ( 'weight' == $pricing_condition ) {
							if ( $total_weight >= $one_rate['min'] && $total_weight < $one_rate['max'] ) {
								$matching_rates[] = $one_rate;
							}
						} elseif ( 'cart_amount' == $pricing_condition ) {
							if ( $total_price >= $one_rate['min'] && $total_price < $one_rate['max'] ) {
								$matching_rates[] = $one_rate;
							}
						}
					}
				}

				if ( empty( $matching_rates ) ) {
					if ( $debug_mode ) {
						add_action( 'woocommerce_before_checkout_form', function () {

							$total_weight = floatval( $this->get_option( 'packaging_weight', 0 ) );
							$woocommerce_weight_unit = get_option( 'woocommerce_weight_unit' );
							if ( $woocommerce_weight_unit == 'g' && $total_weight > 0 )
								$total_weight = $total_weight * 1000;

							wc_print_notice(
								sprintf(
									__( 'No %s shipping method displayed: The current cart doesn\'t match any rates you\'ve set in the shipping method pricing configuration.', 'wc-multishipping' ),
									'Chronopost'

								),
								'notice'
							);
							wc_print_notice(
								sprintf(
									__( 'Cart total amount: %1$s%2$s / Cart total weight: %3$s%4$s', 'wc-multishipping' ),
									$total_weight + WC()->cart->cart_contents_total,
									get_woocommerce_currency_symbol(),
									WC()->cart->cart_contents_weight,
									get_option( 'woocommerce_weight_unit' )
								),
								'notice'
							);
						}, 10 );
					}
					return;
				}

				$matching_rates_shipping_classes = [];

				foreach ( $cart_shipping_classes as $one_shipping_class_id ) {

					if ( ! empty( $one_shipping_class_id ) ) {
						$matching_rates_shipping_classes[ $one_shipping_class_id ] = array_filter(
							$matching_rates, function ($rate) use ($one_shipping_class_id) {
								return in_array( $one_shipping_class_id, $rate['shipping_class'] );
							}
						);
					}

					if ( empty( $matching_rates_shipping_classes[ $one_shipping_class_id ] ) || '0' == $one_shipping_class_id ) {
						$matching_rates_shipping_classes[ $one_shipping_class_id ] = array_filter(
							$matching_rates, function ($rate) use ($one_shipping_class_id) {
								return in_array( self::WMS_ALL_SHIPPING_CLASS_CODE, $rate['shipping_class'] );
								;
							}
						);
					}
				}

				if ( empty( $matching_rates_shipping_classes ) ) {
					if ( $debug_mode ) {
						add_action( 'woocommerce_before_checkout_form', function () {
							wc_print_notice(
								sprintf(
									__( 'No %s shipping method displayed: The products in the cart does not belong to the shippings classes set in the shipping method pricing configuration.', 'wc-multishipping' ),
									'Chronopost'

								),
								'notice'
							);
						}, 10 );
					}
					return;
				}

				$shipping_method_prices = [];

				foreach ( $matching_rates_shipping_classes as $shipping_class_id => $one_shipping_method_rate ) {
					foreach ( $one_shipping_method_rate as $one_rate ) {
						if ( ! isset( $shipping_method_prices[ $shipping_class_id ] ) || $shipping_method_prices[ $shipping_class_id ] > $one_rate['price'] ) {
							$shipping_method_prices[ $shipping_class_id ] = $one_rate['price'];
						}
					}
				}
				foreach ( $shipping_method_prices as $onePrice ) {
					if ( null === $cost || $onePrice > $cost ) {
						$cost = $onePrice;
					}
				}
			}

			$free_shipping_amount = $this->get_option( 'free_shipping_condition', -1 );
			if ( $this->get_option( 'free_shipping', 'no' ) == 'yes' || ( $free_shipping_amount > 0 && $total_price >= $free_shipping_amount ) ) {
				$cost = 0;
			}

			$management_fees = $this->get_option( 'management_fees', 0 );
			if ( ! empty( $management_fees ) && null !== $cost ) {
				$cost += $management_fees;
			}

			if ( null !== $cost ) {
				$rate = [ 
					'id' => $this->get_rate_id(),
					'label' => ( $cost == 0 && $this->get_option( 'title_if_free', 0 ) ? $this->get_option( 'title_if_free' ) : $this->title ),
					'cost' => $cost,
				];
				$this->add_rate( $rate );
			}
		}
	}
}
