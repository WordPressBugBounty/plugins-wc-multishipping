<?php defined('ABSPATH') || exit; 

$installation_registered = get_option('wms_customer_installation_registered', false);
$email_required = isset($_GET['email_required']) && $_GET['email_required'] == '1';
?>

<div class="wms-config-wrapper">
	

	<h1 class="wp-heading-inline"><?php esc_html_e('Configuration', 'wc-multishipping'); ?></h1>
	<hr class="wp-header-end">

	<?php if ($email_required): ?>
	<div class="notice notice-error">
		<p><strong><?php esc_html_e('Email required', 'wc-multishipping'); ?></strong></p>
		<p><?php esc_html_e('You must enter your email address below to access the carrier configuration pages.', 'wc-multishipping'); ?></p>
	</div>
	<?php endif; ?>

	<?php if (!$installation_registered): ?>
	<!-- Email Required First -->
	<div class="wms-main-card">
		<div class="wms-subsection">
			<div class="wms-section-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<div>
					<h3><?php esc_html_e('Welcome to WCMultiShipping!', 'wc-multishipping'); ?></h3>
					<p><?php esc_html_e('Thank you for installing the new version of our plugin for Chronopost & Mondial Relay.', 'wc-multishipping'); ?></p>
				</div>
			</div>
		</div>
		
		<div class="wms-subsection">
			<h3><?php esc_html_e('Confirm your installation', 'wc-multishipping'); ?></h3>
			<p class="wms-description">
				<?php esc_html_e('To complete your installation, please provide us with your email address.', 'wc-multishipping'); ?>
			</p>
			<p class="wms-privacy-note">
				<?php esc_html_e('This single piece of information (your email address) allows us to create an account for you and give you access to the support service.', 'wc-multishipping'); ?>
			</p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wms-inline-form">
				<input type="hidden" name="action" value="wms_save_customer_email">
				<?php wp_nonce_field('wms_customer_email_action', 'wms_customer_email_nonce'); ?>
				<input 
					type="email" 
					name="wms_email" 
					class="wms-input" 
					placeholder="<?php esc_attr_e('your@email.com', 'wc-multishipping'); ?>"
					value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
					required
				>
				<button type="submit" class="button button-primary">
					<?php esc_html_e('Confirm', 'wc-multishipping'); ?>
				</button>
			</form>
			<span>Having trouble activating? <a href="https://www.wcmultishipping.com/contact-us">Contact support</a></span>
		</div>
	</div>
	
	<?php else: ?>

	<!-- Full Configuration (shown after email is provided) -->
	<div class="wms-main-card">
		<div class="wms-subsection">
			<div class="wms-section-header">
				<span class="dashicons dashicons-yes-alt"></span>
				<div>
					<h3><?php esc_html_e('WCMultiShipping Configuration', 'wc-multishipping'); ?></h3>
					<p><?php esc_html_e('Manage your settings and access the documentation.', 'wc-multishipping'); ?></p>
				</div>
			</div>
		</div>
	</div>

	

	<!-- Quick Start Section -->
	<div class="wms-docs-section">
		<h2><?php esc_html_e('Quick Start', 'wc-multishipping'); ?></h2>
		<div class="wms-quick-start">
			<div class="wms-quick-step">
				<span class="wms-step-number">1</span>
				<div class="wms-step-content">
					<h3><?php esc_html_e('Create your shipping methods and set your price grids', 'wc-multishipping'); ?></h3>
					<a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>" class="wms-doc-link">
						<?php esc_html_e('Access shipping methods', 'wc-multishipping'); ?>
						<span class="dashicons dashicons-arrow-right-alt"></span>
					</a>
				</div>
			</div>
			<div class="wms-quick-step">
				<span class="wms-step-number">2</span>
				<div class="wms-step-content">
					<h3><?php esc_html_e('Configure your carriers', 'wc-multishipping'); ?></h3>
					<div class="wms-step-links">
						<a href="<?php echo admin_url('admin.php?page=wc-settings&tab=mondial_relay'); ?>" class="wms-doc-link">
							<?php esc_html_e('Configure Mondial Relay', 'wc-multishipping'); ?>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</a>
						<span class="wms-separator">|</span>
						<a href="<?php echo admin_url('admin.php?page=wc-settings&tab=chronopost'); ?>" class="wms-doc-link">
							<?php esc_html_e('Configure Chronopost', 'wc-multishipping'); ?>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Documentation Section -->
	<div class="wms-docs-section">
		<h2><?php esc_html_e('Documentation', 'wc-multishipping'); ?></h2>
		<p class="wms-docs-intro"><?php esc_html_e('Check out our detailed guides for each carrier:', 'wc-multishipping'); ?></p>
		
		<div class="wms-docs-grid">
			<div class="wms-doc-card">
				<div class="wms-doc-icon">
					<span class="dashicons dashicons-location-alt"></span>
				</div>
				<h3><?php esc_html_e('Mondial Relay', 'wc-multishipping'); ?></h3>
				<ul class="wms-doc-links">
					<li>
						<a href="https://www.wcmultishipping.com/mondial-relay-woocommerce#configurer" target="_blank">
							<span class="dashicons dashicons-admin-settings"></span>
							<?php esc_html_e('Configure Mondial Relay', 'wc-multishipping'); ?>
						</a>
					</li>
					<li>
						<a href="https://www.wcmultishipping.com/mondial-relay-woocommerce#g%C3%A9n%C3%A9rer-une-%C3%A9tiquette" target="_blank">
							<span class="dashicons dashicons-media-text"></span>
							<?php esc_html_e('Generate a label', 'wc-multishipping'); ?>
						</a>
					</li>
				</ul>
			</div>

			<div class="wms-doc-card">
				<div class="wms-doc-icon">
					<span class="dashicons dashicons-clock"></span>
				</div>
				<h3><?php esc_html_e('Chronopost', 'wc-multishipping'); ?></h3>
				<ul class="wms-doc-links">
					<li>
						<a href="https://www.wcmultishipping.com/plugin-chronopost-woocommerce#configurer" target="_blank">
							<span class="dashicons dashicons-admin-settings"></span>
							<?php esc_html_e('Configure Chronopost', 'wc-multishipping'); ?>
						</a>
					</li>
					<li>
						<a href="https://www.wcmultishipping.com/plugin-chronopost-woocommerce#g%C3%A9n%C3%A9rer-une-%C3%A9tiquette" target="_blank">
							<span class="dashicons dashicons-media-text"></span>
							<?php esc_html_e('Generate a label', 'wc-multishipping'); ?>
						</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
