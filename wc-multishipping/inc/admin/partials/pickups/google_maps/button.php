<script type="text/javascript">
    if(window.wms_admin_select_pickup !== undefined){
        window.wms_admin_select_pickup();
    }
    if(window.set_wms_google_maps_pickup_modal !== undefined){
        window.set_wms_google_maps_pickup_modal()
    }
</script>
<button type="button" wms-shipping-provider="<?php echo static::SHIPPING_PROVIDER_ID; ?>" class="wms_pickup_open_modal_google_maps" wms-pickup-modal-id="<?php echo $modal_id; ?>">
    <?php esc_html_e(
        'Choose a pickup point',
        'wc-multishipping'
    ); ?>
</button>
