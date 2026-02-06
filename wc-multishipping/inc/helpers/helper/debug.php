<?php

function wms_dbug( $var, $exit = false ) {

	new \phpdbug( $var );

	if ( $exit )
		exit;
}


function wms_dump( $dump, $exit = false ) {
	echo '<pre style="margin-left:200px; z-index: 999999999">';
	var_dump( $dump );
	echo '</pre>';

	if ( $exit )
		exit;
}


function wms_debug_backtrace( $file = false, $indent = true ) {
	$debug = debug_backtrace();
	$takenPath = [];
	foreach ( $debug as $step ) {
		if ( empty( $step['file'] ) || empty( $step['line'] ) )
			continue;
		$takenPath[] = $step['file'] . ' => ' . $step['line'];
	}
	wms_dump( implode( $file ? "\n" : '<br/>', $takenPath ), $file, $indent );
}


function wms_get_shipping_debug_messages() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return [];
	}
	
	$user_id = get_current_user_id();
	$transient_key = 'wms_debug_messages_' . $user_id;
	$messages = get_transient( $transient_key );
	
	return is_array( $messages ) ? $messages : [];
}

function wms_add_shipping_debug_message( $debug_data ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	
	$user_id = get_current_user_id();
	$transient_key = 'wms_debug_messages_' . $user_id;
	$messages = get_transient( $transient_key );
	
	if ( ! is_array( $messages ) ) {
		$messages = [];
	}
	
	$message_hash = md5( serialize( [
		'method_title' => $debug_data['method_title'],
		'reason' => $debug_data['reason'],
		'total_weight' => $debug_data['total_weight'],
		'total_price' => $debug_data['total_price']
	] ) );
	
	if ( ! isset( $messages[ $message_hash ] ) ) {
		$messages[ $message_hash ] = $debug_data;
		set_transient( $transient_key, $messages, 5 * MINUTE_IN_SECONDS );
	}
}

function wms_clear_shipping_debug_messages() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	
	$user_id = get_current_user_id();
	$transient_key = 'wms_debug_messages_' . $user_id;
	delete_transient( $transient_key );
}

function wms_display_shipping_debug_message( $debug_data ) {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$reason = $debug_data['reason'];
	$method_title = $debug_data['method_title'];
	$total_weight = $debug_data['total_weight'];
	$total_price = $debug_data['total_price'];
	$pricing_condition = $debug_data['pricing_condition'];
	$weight_unit = $debug_data['weight_unit'];
	$rates = $debug_data['rates'];
	$carrier_name = isset( $debug_data['carrier_name'] ) ? $debug_data['carrier_name'] : $method_title;
	
	echo '<div style="display: flex; align-items: center; margin-bottom: 6px;">';
	echo '<span style="font-size: 16px; margin-right: 6px;">⚠️</span>';
	echo '<strong style="font-size: 14px; color: #856404;">' . esc_html( $method_title ) . '</strong>';
	echo '</div>';
	
	if ( $reason === 'no_matching_rates' ) {
		echo '<p style="margin: 6px 0; color: #856404; font-size: 12px;">';
		echo '<strong>' . __( 'Reason:', 'wc-multishipping' ) . '</strong> ';
		
		if ( $total_weight == 0 ) {
			echo __( 'Products in cart have no weight configured.', 'wc-multishipping' );
			echo '</p>';
			
			if ( ! empty( WC()->cart ) ) {
				echo '<div style="background: #fff; padding: 8px; border-radius: 3px; margin: 8px 0; font-size: 11px;">';
				echo '<strong>' . __( 'Products without weight:', 'wc-multishipping' ) . '</strong><br>';
				echo '<ul style="margin: 4px 0 0 0; padding-left: 20px;">';
				
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$product = $cart_item['data'];
					$product_weight = $product->get_weight();
					
					if ( empty( $product_weight ) || $product_weight == 0 ) {
						$product_id = $product->get_id();
						$edit_link = admin_url( 'post.php?post=' . $product_id . '&action=edit' );
						echo '<li style="margin: 2px 0;">';
						echo esc_html( $product->get_name() );
						echo ' - <a href="' . esc_url( $edit_link ) . '" target="_blank" style="color: #2271b1; text-decoration: underline;">' . __( 'Edit product', 'wc-multishipping' ) . '</a>';
						echo '</li>';
					}
				}
				
				echo '</ul>';
				echo '</div>';
			}
		} else {
			if ( $pricing_condition === 'weight' ) {
				echo sprintf( __( 'Cart weight (%1$s %2$s) does not match any configured weight range.', 'wc-multishipping' ), number_format( $total_weight, 2 ), esc_html( $weight_unit ) );
			} else {
				echo sprintf( __( 'Cart total (%1$s) does not match any configured price range.', 'wc-multishipping' ), wc_price( $total_price ) );
			}
			echo '</p>';
		}
		
	} elseif ( $reason === 'no_matching_shipping_classes' ) {
		echo '<p style="margin: 6px 0; color: #856404; font-size: 12px;">';
		echo '<strong>' . __( 'Reason:', 'wc-multishipping' ) . '</strong> ';
		echo __( 'Products in cart do not belong to any shipping class configured in the rate table.', 'wc-multishipping' );
		echo '</p>';
	}
	
	echo '<div style="background: #fff; padding: 8px; border-radius: 3px; margin: 8px 0; font-size: 11px;">';
	echo '<strong>' . __( 'Cart Details:', 'wc-multishipping' ) . '</strong><br>';
	echo '• ' . sprintf( __( 'Total Weight: %1$s %2$s', 'wc-multishipping' ), '<strong>' . number_format( $total_weight, 2 ) . '</strong>', esc_html( $weight_unit ) ) . '<br>';
	echo '• ' . sprintf( __( 'Total Amount: %s', 'wc-multishipping' ), '<strong>' . wc_price( $total_price ) . '</strong>' ) . '<br>';
	echo '• ' . sprintf( __( 'Pricing Condition: %s', 'wc-multishipping' ), '<strong>' . ( $pricing_condition === 'weight' ? __( 'Weight', 'wc-multishipping' ) : __( 'Cart Amount', 'wc-multishipping' ) ) . '</strong>' );
	echo '</div>';
	
	if ( ! empty( $rates ) ) {
		echo '<div style="background: #fff; padding: 8px; border-radius: 3px; margin: 8px 0; font-size: 11px;">';
		echo '<strong>' . __( 'Configured Rates:', 'wc-multishipping' ) . '</strong><br>';
		echo '<table style="width: 100%; margin-top: 6px; border-collapse: collapse; font-size: 10px;">';
		echo '<thead><tr style="background: #f8f9fa;">';
		echo '<th style="padding: 4px; text-align: left; border: 1px solid #dee2e6;">' . __( 'Min', 'wc-multishipping' ) . '</th>';
		echo '<th style="padding: 4px; text-align: left; border: 1px solid #dee2e6;">' . __( 'Max', 'wc-multishipping' ) . '</th>';
		echo '<th style="padding: 4px; text-align: left; border: 1px solid #dee2e6;">' . __( 'Price', 'wc-multishipping' ) . '</th>';
		echo '<th style="padding: 4px; text-align: left; border: 1px solid #dee2e6;">' . __( 'Shipping Class', 'wc-multishipping' ) . '</th>';
		echo '</tr></thead><tbody>';
		
		foreach ( $rates as $rate ) {
			echo '<tr>';
			echo '<td style="padding: 4px; border: 1px solid #dee2e6;">' . esc_html( $rate['min'] ) . '</td>';
			echo '<td style="padding: 4px; border: 1px solid #dee2e6;">' . esc_html( $rate['max'] ) . '</td>';
			echo '<td style="padding: 4px; border: 1px solid #dee2e6;">' . wc_price( $rate['price'] ) . '</td>';
			echo '<td style="padding: 4px; border: 1px solid #dee2e6;">';
			if ( is_array( $rate['shipping_class'] ) ) {
				if ( in_array( 'all', $rate['shipping_class'] ) ) {
					echo __( 'All', 'wc-multishipping' );
				} else {
					$class_names = [];
					foreach ( $rate['shipping_class'] as $class_id ) {
						$term = get_term( $class_id, 'product_shipping_class' );
						if ( $term && ! is_wp_error( $term ) ) {
							$class_names[] = $term->name;
						}
					}
					echo esc_html( implode( ', ', $class_names ) );
				}
			}
			echo '</td>';
			echo '</tr>';
		}
		
		echo '</tbody></table>';
		echo '</div>';
	} else {
		echo '<div style="background-color: #fff3cd; border: 1px solid #ffeeba; padding: 10px; border-radius: 4px; margin: 10px 0; color: #856404; font-size: 12px;">';
		echo '<p style="margin: 0 0 5px 0;">' . __( 'No rates configured for this shipping method.', 'wc-multishipping' ) . '</p>';
		echo '<p style="margin: 0 0 5px 0;">' . __( 'Please use the link below to configure your shipping rates. Don\'t forget to save the changes.', 'wc-multishipping' ) . '</p>';
		echo '<p style="margin: 0;">' . sprintf(__( '<a href="%s" style="color: #856404; text-decoration: underline;">See the shipping method configuration page</a>', 'wc-multishipping'), admin_url( '/wp-admin/admin.php?page=wc-settings&tab=shipping&instance_id=' . $instance_id )) . '</p>';
		echo '</div>';
	}
}

function wms_display_queued_shipping_debug_messages() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$messages = wms_get_shipping_debug_messages();
	
	if ( empty( $messages ) ) {
		return;
	}
	
	$messages = array_values( $messages );
	
	$message_count = count( $messages );
	$accordion_id = 'wms-debug-accordion-' . uniqid();
	
	echo '<div style="margin: 20px 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, \'Helvetica Neue\', sans-serif;">';
	
	echo '<div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
	echo '<div onclick="document.getElementById(\'' . $accordion_id . '\').style.display = document.getElementById(\'' . $accordion_id . '\').style.display === \'none\' ? \'block\' : \'none\'; var arrow = this.querySelector(\'span\'); arrow.textContent = document.getElementById(\'' . $accordion_id . '\').style.display === \'none\' ? \'▶\' : \'▼\';" style="cursor: pointer; padding: 15px 20px; background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%);">';
	echo '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">';
	echo '<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">';
	echo '<span style="font-size: 20px; transition: transform 0.3s;">▶</span>';
	echo '<strong style="font-size: 16px; color: #000;">' . sprintf( __( 'WcMultiShipping Debug Mode: %d shipping method(s) not available', 'wc-multishipping' ), $message_count ) . '</strong>';
	echo '<span onclick="event.stopPropagation(); var accordion = document.getElementById(\'' . $accordion_id . '\'); accordion.style.display = \'block\'; var arrow = this.parentElement.querySelector(\'span\'); arrow.textContent = \'▼\';" style="cursor: pointer; color: #000; text-decoration: underline; font-size: 12px; font-weight: 500;">';
	echo __( 'Click to see more details', 'wc-multishipping' );
	echo '</span>';
	echo '</div>';
	echo '<span style="background: rgba(0,0,0,0.1); padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600; color: #000;">Only Admins see this message. It will not be displayed to customers</span>';
	echo '</div>';
	echo '<div style="font-size: 13px; color: #000; padding-left: 32px;">';
	echo '<strong>' . __( 'Any question?', 'wc-multishipping' ) . '</strong> ';
	echo '<a href="https://www.wcmultishipping.com/contactez-nous" target="_blank" style="color: #000; text-decoration: underline; font-weight: 600;">' . __( 'Contact us', 'wc-multishipping' ) . '</a>';
	echo '</div>';
	echo '</div>';
	
	echo '<div id="' . $accordion_id . '" style="display: none;">';
	
	echo '<div style="padding: 10px 15px; background: #fff3cd; border-bottom: 2px solid #ffc107;">';
	echo '<div style="display: flex; align-items: start; gap: 8px;">';
	echo '<span style="font-size: 18px; flex-shrink: 0;">💡</span>';
	echo '<div style="font-size: 12px; line-height: 1.5; color: #856404;">';
	echo '<strong>' . __( 'Administrator Notice:', 'wc-multishipping' ) . '</strong> ';
	echo __( 'These debug messages are only visible to administrators with WooCommerce management permissions. They help diagnose why certain shipping methods are not available for the current cart.', 'wc-multishipping' );
	echo '<br><br><strong>' . __( 'To disable these messages:', 'wc-multishipping' ) . '</strong><br>';
	echo '• <a href="' . admin_url( 'admin.php?page=wc-settings&tab=mondial_relay' ) . '" style="color: #2271b1; text-decoration: underline;" target="_blank">' . __( 'Disable Mondial Relay debug mode', 'wc-multishipping' ) . '</a><br>';
	echo '• <a href="' . admin_url( 'admin.php?page=wc-settings&tab=chronopost' ) . '" style="color: #2271b1; text-decoration: underline;" target="_blank">' . __( 'Disable Chronopost debug mode', 'wc-multishipping' ) . '</a>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	
	foreach ( $messages as $index => $debug_data ) {
		echo '<div style="border-top: 1px solid #ffc107; padding: 10px 15px; background: ' . ( $index % 2 === 0 ? '#fffbf0' : '#fff' ) . ';">';
		wms_display_shipping_debug_message( $debug_data );
		echo '</div>';
	}
	
	echo '</div>'; // Close accordion content
	echo '</div>'; // Close container
	echo '</div>'; // Close main wrapper
	
	add_action( 'shutdown', 'wms_clear_shipping_debug_messages', 999 );
}


add_action( 'woocommerce_blocks_enqueue_cart_block_scripts_after', 'wms_display_queued_shipping_debug_messages', 999 );
add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', 'wms_display_queued_shipping_debug_messages', 999 );

add_action( 'woocommerce_before_checkout_form', 'wms_display_queued_shipping_debug_messages', 999 );
