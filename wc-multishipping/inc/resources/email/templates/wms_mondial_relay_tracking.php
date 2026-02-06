<?php

do_action('woocommerce_email_header', $email_heading, $email); ?>
	<p><?php printf(__('Hi %s,', 'wc-multishipping'), $order->get_billing_first_name()); ?></p>
	<p><?php echo sprintf(__('The label for order #%s has been generated.', 'wc-multishipping'), esc_html($order->get_order_number()))."<br/><br/>";
 ?></p>
<?php

echo sprintf(__('You can track your parcel using this link: %s', 'wc-multishipping'), esc_html($tracking_url));  

do_action('woocommerce_email_footer', $email);
