<?php defined('ABSPATH') || exit; ?>


<h1 class="wp-heading-inline"><?php esc_html_e('Configuration', 'wc-multishipping'); ?></h1>
<hr class="wp-header-end">

<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

	<div> <?php echo __('Thanks for using our plugin! Here are some useful information to get you started 🙂', 'wc-multishipping') ?></div>


	<h2> <?php echo __('Useful links', 'wc-multishipping') ?></h2>

	<table class="form-table">
		<tbody>
		<tr scope="row">
			<th scope="row">
				<label for="wms_api_key"><?php echo __('Shipping Methods Configuration', 'wc-multishipping'); ?>
			</th>
			<td>
					<span>
						<?php echo '<a href="'.admin_url('admin.php?page=wc-settings&tab=shipping').'">'.__('Access Shipping Methods configuration page', 'wc-multishipping').'</a>'
                        ?>
					</span>
				</p>
			</td>
		</tr>
		<tr scope="row">
			<th scope="row">
				<label for="wms_api_key"><?php echo __('Chronopost Configuration', 'wc-multishipping'); ?>
			</th>
			<td>
					<span>
						<?php echo '<a href="'.admin_url('/admin.php?page=wc-settings&tab=chronopost').'">'.__('Access Chronopost configuration page', 'wc-multishipping').'</a>'
                        ?>
					</span>
				</p>
			</td>
		</tr>
		<tr scope="row">
			<th scope="row">
				<label for="wms_api_key"><?php echo __('Mondial Relay Configuration', 'wc-multishipping'); ?>
			</th>
			<td>
					<span>
						<?php echo '<a href="'.admin_url('/admin.php?page=wc-settings&tab=mondial_relay').'">'.__('Access Mondial Relay configuration page', 'wc-multishipping').'</a>'
                        ?>
					</span>
				</p>
			</td>
		</tr>
		<tr scope="row">
			<th scope="row">
				<label for="wms_api_key"><?php echo __('UPS Configuration', 'wc-multishipping'); ?>
			</th>
			<td>
					<span>
						<?php echo '<a href="'.admin_url('/admin.php?page=wc-settings&tab=ups').'">'.__('Access UPS configuration page', 'wc-multishipping').'</a>'
                        ?>
					</span>
				</p>
			</td>
		</tr>
		</tbody>
	</table>
	<p class="submit">
		<input name="submit" id="submit" class="button button-primary" value="<?php esc_html_e('Save', 'wc-multishipping'); ?>" type="submit">
	</p>
</form>

