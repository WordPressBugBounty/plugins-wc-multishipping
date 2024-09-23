<?php


namespace WCMultiShipping\inc\admin\partials\settings;


class wms_partial_settings_button
{

    public static $already_defined = false;

    public function __construct()
    {
        if (!self::$already_defined) {
            add_action('woocommerce_admin_field_button', [$this, 'add_admin_field_button']);
        }
        self::$already_defined = true;
    }

    public function add_admin_field_button($value)
    {
        ?>

		<tr valign="top">
			<th class="titledesc">
				<button type="button" class="<?php echo esc_attr($value['class']); ?>" id="<?php echo esc_attr($value['id']); ?>">
                    <?php echo esc_html($value['title']); ?>
				</button>
			</th>
			<td class="forminp">
				<span class="wms_text_result"></span>
			</td>
		</tr>

        <?php
    }
}
