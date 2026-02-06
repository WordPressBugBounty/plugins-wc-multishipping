<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css"
	integrity="sha256-kLaT2GOSpHechhsozzB+flnD+zUyjE2LlfWPgU04xyI=" crossorigin="" />

<!-- Styles are now in /inc/front/assets/dev/scss/pickups/wooshippping_pickup_widget.scss -->
<style>
.wms_pickup_modal .wc-backbone-modal-content {
	max-width: 95vw !important;
	max-height: 95vh !important;
	width: 1200px !important;
}

.wms_pickup_modal .wc-backbone-modal-main {
	padding: 0 !important;
	overflow: hidden !important;
}

.wms_pickup_modal .wc-backbone-modal-main article {
	padding: 0 !important;
	margin: 0 !important;
}

.wms_modal_content {
	display: flex;
	flex-direction: column;
	height: 90vh !important;
	max-height: 800px;
}

.wms_pickup_modal .wc-backbone-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	padding: 20px;
	border-bottom: 1px solid #ddd;
	background: #f9f9f9;
	flex-shrink: 0;
}

.wms_pickup_modal_address {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr auto;
	gap: 10px;
	flex: 1;
	margin-right: 20px;
}

.wms_pickup_modal_address input,
.wms_pickup_modal_address select {
	width: 100%;
	padding: 10px 12px;
	border: 1px solid #ddd;
	border-radius: 4px;
	font-size: 14px;
	box-sizing: border-box;
}

.wms_pickup_modal_address input:focus,
.wms_pickup_modal_address select:focus {
	outline: none;
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
}

.wms_pickup_modal_address_search {
	padding: 10px 20px;
	background: #2271b1;
	color: #fff;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	font-weight: 500;
	white-space: nowrap;
	transition: background 0.2s;
}

.wms_pickup_modal_address_search:hover {
	background: #135e96;
}

.wms_pickup_modal_address_search:active {
	transform: translateY(1px);
}

.wms_pickup_modal_map_container {
	display: grid;
	grid-template-columns: 1fr 400px;
	gap: 0;
	flex: 1;
	overflow: hidden;
	min-height: 0;
}

.wms_pickup_modal_map {
	position: relative;
	height: 100%;
	background: #e5e3df;
}

#wms_pickup_modal_map_openstreemap {
	width: 100%;
	height: 100%;
}

.wms_pickup_modal_listing {
	height: 100%;
	overflow-y: auto;
	background: #fff;
	border-left: 1px solid #ddd;
	padding: 15px;
}

.wms_pickup_modal_listing::-webkit-scrollbar {
	width: 8px;
}

.wms_pickup_modal_listing::-webkit-scrollbar-track {
	background: #f1f1f1;
}

.wms_pickup_modal_listing::-webkit-scrollbar-thumb {
	background: #888;
	border-radius: 4px;
}

.wms_pickup_modal_listing::-webkit-scrollbar-thumb:hover {
	background: #555;
}

.wms_pickup_modal_listing_one {
	padding: 15px;
	margin-bottom: 15px;
	border: 1px solid #ddd;
	border-radius: 6px;
	cursor: pointer;
	transition: all 0.2s;
	background: #fff;
}

.wms_pickup_modal_listing_one:hover {
	border-color: #2271b1;
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
	transform: translateY(-2px);
}

.wms_pickup_modal_listing_one.wms_is_selected {
	border-color: #2271b1;
	background: #f0f6fc;
	box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.2);
}

.wms_pickup_name {
	font-weight: 600;
	font-size: 15px;
	color: #1e1e1e;
	margin-bottom: 8px;
}

.wms_pickup_address1,
.wms_pickup_address2,
.wms_pickup_country {
	font-size: 13px;
	color: #666;
	margin-bottom: 4px;
}

.wms_pickup_open_time {
	width: 100%;
	margin: 10px 0;
	font-size: 12px;
	border-collapse: collapse;
}

.wms_pickup_open_time td {
	padding: 4px 8px;
	border-bottom: 1px solid #f0f0f0;
}

.wms_pickup_open_time td:first-child {
	font-weight: 500;
	color: #666;
	width: 80px;
}

.wms_pickup_modal_listing_one_button_ship {
	width: 100%;
	margin-top: 12px;
	padding: 10px;
	background: #2271b1;
	color: #fff;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	font-size: 14px;
	font-weight: 500;
	transition: background 0.2s;
}

.wms_pickup_modal_listing_one_button_ship:hover {
	background: #135e96;
}

.wms-error-message {
	padding: 15px;
	margin: 15px;
	background: #fef7f7;
	border-left: 4px solid #dc3232;
	border-radius: 4px;
	color: #dc3232;
}

.wms-error-message strong {
	display: block;
	margin-bottom: 5px;
}

.wc-backbone-modal-loader {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	z-index: 1000;
}

@media (max-width: 1024px) {
	.wms_pickup_modal .wc-backbone-modal-content {
		width: 95vw !important;
	}
	
	.wms_pickup_modal_map_container {
		grid-template-columns: 1fr 350px;
	}
	
	.wms_pickup_modal_address {
		grid-template-columns: 1fr 1fr;
	}
	
	.wms_pickup_modal_address_country,
	.wms_pickup_modal_address_find_pickup {
		grid-column: span 2;
	}
}

@media (max-width: 768px) {
	.wms_modal_content {
		height: 95vh !important;
	}
	
	.wms_pickup_modal .wc-backbone-modal-header {
		position: relative;
		flex-direction: column;
		padding-top: 50px; 	}
	
	.wms_pickup_modal .wc-backbone-modal-header > div:last-child {
		position: absolute;
		top: 10px;
		right: 10px;
		z-index: 10;
	}
	
	.wms_pickup_modal .modal-close {
		width: 36px;
		height: 36px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 50%;
		box-shadow: 0 2px 8px rgba(0,0,0,0.15);
	}
	
	.wms_pickup_modal_address {
		grid-template-columns: 1fr;
		margin-right: 0;
		margin-bottom: 0;
		width: 100%;
	}
	
	.wms_pickup_modal_address_country,
	.wms_pickup_modal_address_find_pickup {
		grid-column: span 1;
	}
	
	.wms_pickup_modal_map_container {
		grid-template-columns: 1fr;
		grid-template-rows: 250px 1fr;
	}
	
	.wms_pickup_modal_listing {
		border-left: none;
		border-top: 1px solid #ddd;
		max-height: calc(95vh - 350px);
	}
	
	.wms_pickup_modal_listing_one {
		padding: 12px;
		margin-bottom: 10px;
	}
	
	.wms_pickup_name {
		font-size: 14px;
	}
	
	.wms_pickup_address1,
	.wms_pickup_address2,
	.wms_pickup_country {
		font-size: 12px;
	}
	
	.wms_pickup_open_time {
		font-size: 11px;
	}
}

@media (max-width: 480px) {
	.wms_pickup_modal .wc-backbone-modal-content {
		max-width: 100vw !important;
		max-height: 100vh !important;
		width: 100vw !important;
		margin: 0 !important;
		border-radius: 0 !important;
	}
	
	.wms_modal_content {
		height: 100vh !important;
		max-height: none;
		margin: 15px;
	}
	
	.wms_pickup_modal .wc-backbone-modal-header {
		padding: 10px;
		padding-top: 45px;
	}
	
	.wms_pickup_modal .modal-close {
		width: 32px;
		height: 32px;
		font-size: 18px;
	}
	
	.wms_pickup_modal_map_container {
		grid-template-rows: 200px 1fr;
	}
	
	.wms_pickup_modal_listing {
		padding: 10px;
		max-height: calc(100vh - 320px);
	}
	
	.wms_pickup_modal_address input,
	.wms_pickup_modal_address select,
	.wms_pickup_modal_address_search {
		font-size: 16px; 		padding: 12px;
	}
	
	.wms_pickup_modal_address_search {
		padding: 12px 16px;
	}
	
	.wms_pickup_modal_listing_one {
		padding: 10px;
		margin-bottom: 8px;
	}
	
	.wms_pickup_name {
		font-size: 13px;
		margin-bottom: 6px;
	}
	
	.wms_pickup_address1,
	.wms_pickup_address2,
	.wms_pickup_country {
		font-size: 11px;
		margin-bottom: 3px;
	}
	
	.wms_pickup_open_time {
		font-size: 10px;
		margin: 8px 0;
	}
	
	.wms_pickup_open_time td {
		padding: 2px 4px;
	}
	
	.wms_pickup_modal_listing_one_button_ship {
		padding: 10px;
		font-size: 13px;
		margin-top: 8px;
	}
}

@media (prefers-color-scheme: dark) {
	.wms_pickup_modal .wc-backbone-modal-header {
		background: #1e1e1e;
		border-bottom-color: #444;
	}
	
	.wms_pickup_modal_listing {
		background: #2d2d2d;
		border-left-color: #444;
	}
	
	.wms_pickup_modal_listing_one {
		background: #1e1e1e;
		border-color: #444;
	}
	
	.wms_pickup_modal_listing_one.wms_is_selected {
		background: #2a3f5f;
	}
	
	.wms_pickup_name {
		color: #fff;
	}
	
	.wms_pickup_address1,
	.wms_pickup_address2,
	.wms_pickup_country {
		color: #aaa;
	}
}
</style>

<script type="text/template" id="tmpl-wms_pickup_open_modal_openstreetmap">
	<div class="wms_pickup_modal" id="wms_pickup_open_modal_openstreetmap">
		<div class="wc-backbone-modal">
			<div class="wc-backbone-modal-content">
				<section class="wc-backbone-modal-main" role="main">
					<div class="wc-backbone-modal-loader"></div>
					<article>
						<div class="wms_modal_content">
							<div class="wc-backbone-modal-header">
								<div class="wms_pickup_modal_address">
									<div class="wms_pickup_modal_address_city">
										<input type="text" 
											placeholder="<?php esc_attr_e( 'City', 'wc-multishipping' ); ?>" 
											class="wms_pickup_modal_address_city_input"
											aria-label="<?php esc_attr_e( 'City', 'wc-multishipping' ); ?>">
									</div>
									<div class="wms_pickup_modal_address_zip-code">
										<input type="text" 
											placeholder="<?php esc_attr_e( 'Zip Code', 'wc-multishipping' ); ?>" 
											class="wms_pickup_modal_address_zipcode_input"
											aria-label="<?php esc_attr_e( 'Zip Code', 'wc-multishipping' ); ?>"
											required>
									</div>
									<div class="wms_pickup_modal_address_country">
										<?php echo woocommerce_form_field( 'wms_pickup_modal_address_country_select', [ 
											'type' => 'select',
											'class' => [ 'wms_pickup_modal_address_country_select' ],
											'options' => $countries,
											'label' => '',
										] ); ?>
									</div>
									<div class="wms_pickup_modal_address_find_pickup">
										<button type="button" class="wms_pickup_modal_address_search">
											<span class="dashicons dashicons-search" style="margin-right: 5px; vertical-align: middle;"></span>
											<?php esc_html_e( 'Find a pickup point', 'wc-multishipping' ); ?>
										</button>
									</div>
								</div>
								<div>
									<button class="modal-close modal-close-link dashicons dashicons-no-alt" aria-label="<?php esc_attr_e( 'Close', 'wc-multishipping' ); ?>">
										<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woocommerce' ); ?></span>
									</button>
								</div>
							</div>
							<div class="wms_pickup_modal_map_container">
								<div class="wms_pickup_modal_map">
									<div id="wms_pickup_modal_map_openstreemap"></div>
								</div>
								<div class="wms_pickup_modal_listing"></div>
							</div>
						</div>
					</article>
				</section>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</div>
	</div>
</script>