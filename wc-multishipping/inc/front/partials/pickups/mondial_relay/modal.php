<script type="text/template" id="tmpl-wms_pickup_open_modal_mondial_relay">
	<div class="wms_pickup_modal" id="wms_pickup_open_modal_mondial_relay">
		<div class="wc-backbone-modal">
			<div class="wc-backbone-modal-content">
				<section class="wc-backbone-modal-main" role="main">
					<div class="wc-backbone-modal-loader"></div>
					<header class="wc-backbone-modal-header">
						<button class="modal-close modal-close-link dashicons dashicons-no-alt">
							<span class="screen-reader-text"><?php echo esc_html_e( 'Close modal panel', 'woocommerce' ); ?></span>
						</button>
					</header>
					<body>
						<div class="wms_pickup_modal_map">
						</div>
						<button id="wms_select_point" class="button wms_select_pickup_point_button">
							<?php echo __( 'Ship Here', 'wc-multishipping' ); ?>
						</button>
				</section>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</div>
	</div>
</script>