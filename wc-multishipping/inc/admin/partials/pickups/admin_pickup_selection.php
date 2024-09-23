<?php
if (!wp_style_is('wms_pickup_CSS', 'enqueued')) { ?>

	<div id="wms_pickup_selected">
        <?php
        if (!empty($pickup_info)) { ?>
			<strong><?php esc_html_e('Your package will be shipped to:', 'wc-multishipping'); ?> </strong>
            <?php echo wms_display_value($pickup_info['pickup_name']); ?> <br/>
            <?php echo wms_display_value($pickup_info['pickup_address']); ?> <br/>
            <?php echo wms_display_value($pickup_info['pickup_zipcode']).' '.wms_display_value($pickup_info['pickup_city']).' '.wms_display_value($pickup_info['pickup_country']); ?> <br/>
            <?php
        }
        ?>
	</div>
<?php } ?>

<div class="wms_order_assign_shipping_methods_area">
	<button type="button" class="button button-primary wms_order_select_shipping_method_button">
        <?php echo sprintf(__('Click here to ship this order with %s (Pro version only)', 'wc-multishipping'), static::SHIPPING_PROVIDER_DISPLAYED_NAME); ?>
	</button>


</div>
