<script type="text/template" id="tmpl-wms_pickup_open_modal_mondial_relay">
	<div class="wms_pickup_modal wms_pickup_modal_mondial_relay" id="wms_pickup_open_modal_mondial_relay">
		<div class="wc-backbone-modal">
			<div class="wc-backbone-modal-content">
				<section class="wc-backbone-modal-main" role="main">
					<div class="wc-backbone-modal-loader"></div>
					<header class="wc-backbone-modal-header">
						<button class="modal-close modal-close-link dashicons dashicons-no-alt">
							<span class="screen-reader-text"><?php echo esc_html_e( 'Close modal panel', 'woocommerce' ); ?></span>
						</button>
					</header>
					<article>
						<div class="wms_mondial_relay_container">
							<div class="wms_pickup_modal_map">
								<!-- Mondial Relay widget iframe will be injected here -->
							</div>
							<div class="wms_mondial_relay_sidebar">
								<div class="wms_mondial_relay_selection">
									<h3><?php echo __( 'Selected pickup point', 'wc-multishipping' ); ?></h3>
									<div id="wms_mondial_relay_selected_info" class="wms_selected_info_empty">
										<p><?php echo __( 'Please select a pickup point on the map', 'wc-multishipping' ); ?></p>
									</div>
								</div>
								<button id="wms_select_point" class="button wms_select_pickup_point_button">
									<?php echo __( 'Ship Here', 'wc-multishipping' ); ?>
								</button>
							</div>
						</div>
					</article>
				</section>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</div>
	</div>
</script>