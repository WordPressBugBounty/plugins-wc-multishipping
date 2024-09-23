<button type="button" class="wms_pickup_open_modal_openstreetmap" wms-pickup-modal-id="<?php echo $modal_id; ?>"><?php esc_html_e(
        'Choose a pickup point',
        'wc-multishipping'
    ); ?></button>
<input type="hidden" id="wms_shipping_provider" value="<?php echo wms_display_value(static::SHIPPING_PROVIDER_ID); ?>">
<input type="hidden" id="wms_pickup_point">
<input type="hidden" id="wms_pickup_info">
<input type="hidden" id="wms_nonce" value="<?php echo wp_create_nonce('wms_pickup_selection'); ?>"/>


<div id="wms_ajax_error"></div>
<div id="wms_selected_pickup_desc">
	<div id="wms_pickup_selected">
        <?php
        if (!empty($pickup_info)) { ?>
			<strong><?php esc_html_e('Your package will be ship to:', 'wc-multishipping'); ?> </strong>
            <?php echo wms_display_value($pickup_info['pickup_name']); ?> <br/>
            <?php echo wms_display_value($pickup_info['pickup_address']); ?> <br/>
            <?php echo wms_display_value($pickup_info['pickup_zipcode']).' '.wms_display_value($pickup_info['pickup_city']).' '.wms_display_value($pickup_info['pickup_country']); ?> <br/>
            <?php
        }

        ?>
	</div>
</div>
