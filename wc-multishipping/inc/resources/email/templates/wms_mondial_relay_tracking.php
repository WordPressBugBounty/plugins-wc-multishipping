<?php
echo '= '.esc_html($email_heading)." =<br/><br/>";


echo sprintf(__('Hi %s,', 'wc-multishipping'), esc_html($order->get_billing_first_name()))."<br/><br/>";
echo sprintf(__('The label for order #%s has been generated.', 'wc-multishipping'), esc_html($order->get_order_number()))."<br/><br/>";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=<br/><br/>";

echo sprintf(__('You can track your parcel using this link: %s', 'wc-multishipping'), esc_html($tracking_url))."<br/><br/>";


echo esc_html(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
