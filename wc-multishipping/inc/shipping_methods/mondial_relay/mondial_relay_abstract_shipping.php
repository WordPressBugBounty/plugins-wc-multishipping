<?php

namespace WCMultiShipping\inc\shipping_methods\mondial_relay;

use WCMultiShipping\inc\admin\classes\mondial_relay\mondial_relay_shipping_methods;
use WCMultiShipping\inc\shipping_methods\abstract_shipping;

class mondial_relay_abstract_shipping extends abstract_shipping {
	const WMS_ALL_SHIPPING_CLASS_CODE = 'all';

	protected $country_capabilities;

	protected $product_code;

	protected $return_product_code;


	public function init_form_fields() {
		$this->instance_form_fields = [ 
			'title' => [ 
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'This controls the title seen by the user during checkout.', 'woocommerce' ),
				'default' => $this->method_title,
				'desc_tip' => true,
			],
			'title_if_free' => [ 
				'title' => __( 'Title if free', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'This controls the title seen by the user during checkout if shipping is free.', 'woocommerce' ),
				'default' => __( 'Free Shipping', 'woocommerce' ),
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
	}


	public function calculate_shipping( $package = [] ) {
		$cost = null;
		$debug_mode = get_option( 'wms_mondial_relay_debug_mode' );

		if ( mondial_relay_shipping_methods::get_one_country_capabilities_info( $package['destination']['country'], $this->id ) ) {
			$total_weight = floatval( $this->get_option( 'packaging_weight', 0 ) );
			$woocommerce_weight_unit = get_option( 'woocommerce_weight_unit' );
			if ( $woocommerce_weight_unit == 'g' && $total_weight > 0 )
				$total_weight = $total_weight * 1000;

			$total_price = get_option( 'wms_mondial_relay_price_before_discount', 'no' ) === 'yes' ? $package['contents_cost'] : $package['cart_subtotal'];

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
					add_action( 'woocommerce_review_order_after_shipping', function () {

						$total_weight = floatval( $this->get_option( 'packaging_weight', 0 ) );
						$woocommerce_weight_unit = get_option( 'woocommerce_weight_unit' );
						if ( $woocommerce_weight_unit == 'g' && $total_weight > 0 )
							$total_weight = $total_weight * 1000;

						wc_print_notice(
							sprintf(
								__( 'No %s shipping method displayed: The current cart doesn\'t match any rates you\'ve set in the shipping method pricing configuration.', 'wc-multishipping' ),
								'Mondial Relay'

							), 'notice'
						);
						wc_print_notice(
							sprintf(
								__( 'Cart total amount: %1$s%2$s / Cart total weight: %3$s%4$s', 'wc-multishipping' ),
								$total_weight + WC()->cart->cart_contents_total,
								get_woocommerce_currency_symbol(),
								WC()->cart->cart_contents_weight,
								get_option( 'woocommerce_weight_unit' )
							), 'notice'
						);
					}, 10 );
				}

				return;
			}

			$matching_rates_shipping_classes = [];

			foreach ( $cart_shipping_classes as $one_shipping_class_id ) {

				if ( ! empty( $one_shipping_class_id ) ) {
					$matching_rates_shipping_classes[ $one_shipping_class_id ] = array_filter(
						$matching_rates,
						function ($rate) use ($one_shipping_class_id) {
							return in_array( $one_shipping_class_id, $rate['shipping_class'] );
						}
					);
				}

				if ( empty( $matching_rates_shipping_classes[ $one_shipping_class_id ] ) || '0' == $one_shipping_class_id ) {
					$matching_rates_shipping_classes[ $one_shipping_class_id ] = array_filter(
						$matching_rates,
						function ($rate) use ($one_shipping_class_id) {
							return in_array( self::WMS_ALL_SHIPPING_CLASS_CODE, $rate['shipping_class'] );
						}
					);
				}
			}

			if ( empty( $matching_rates_shipping_classes ) ) {
				if ( $debug_mode ) {
					add_action( 'woocommerce_review_order_after_shipping', function () {
						wc_print_notice(
							sprintf(
								__( 'No %s shipping method displayed: The products in the cart does not belong to the shippings classes set in the shipping method pricing configuration.', 'wc-multishipping' ),
								'Mondial Relay'

							), 'notice'
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
