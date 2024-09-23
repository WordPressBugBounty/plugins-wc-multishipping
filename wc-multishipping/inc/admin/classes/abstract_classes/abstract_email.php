<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;

abstract class abstract_email extends \WC_Email
{
    const SHIPPING_PROVIDER_DISPLAYED_NAME = '';
    const SHIPPING_PROVIDER_ID = '';

    abstract public static function get_order_class();

    abstract public static function get_parcel_class();

    public function __construct()
    {
        $this->id = static::SHIPPING_PROVIDER_ID;

        $this->title = sprintf(__('%s parcel tracking (Pro version only)', 'wc-multishipping'), static::SHIPPING_PROVIDER_DISPLAYED_NAME);


        $this->description = __('The email customer will receive once the outward label is generated (Pro version only)', 'wc-multishipping');


        $this->customer_email = true;
        $this->heading = sprintf(__('Your %s parcel tracking', 'wc-multishipping'), static::SHIPPING_PROVIDER_DISPLAYED_NAME);
        $this->subject = vsprintf(__('[%s] - Your %s parcel tracking', 'wc-multishipping'), ['{blogname}', static::SHIPPING_PROVIDER_DISPLAYED_NAME]);
        $this->template_html = 'wms_'.static::SHIPPING_PROVIDER_ID.'_tracking.php';
        $this->template_plain = 'plain'.DS.'wms_'.static::SHIPPING_PROVIDER_ID.'_tracking.php';
        $this->template_base = WMS_RESOURCES.'email'.DS.'templates'.DS;

        add_action('wms_'.static::SHIPPING_PROVIDER_ID.'_send_tracking_email', [$this, 'trigger'], 10, 2);

        parent::__construct();
    }

    public function get_content_html()
    {
        $order_class = static::get_order_class();
        $tracking_number = $order_class::get_tracking_numbers_from_order($this->object->get_id());

        $parcel_class = static::get_parcel_class();
        $tracking_url = $parcel_class::get_tracking_url(reset($tracking_number));

        return wc_get_template_html(
            $this->template_html,
            [
                'order'         => $this->object,
                'email_heading' => $this->heading,
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
                'tracking_url'  => $tracking_url,
            ],
            '',
            $this->template_base
        );
    }

    public function get_content_plain()
    {
        $order_class = static::get_order_class();
        $tracking_number = $order_class::get_tracking_numbers_from_order($this->object->get_id());

        $parcel_class = static::get_parcel_class();
        $tracking_url = $parcel_class::get_tracking_url(reset($tracking_number));

        return wc_get_template_html(
            $this->template_plain,
            [
                'order'         => $this->object,
                'email_heading' => $this->heading,
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
                'tracking_url'  => $tracking_url,
            ],
            '',
            $this->template_base
        );
    }

    public function trigger($order, $label_path)
    {
        if (!$this->is_enabled()) {
            return false;
        }

        $this->object = $order;
        $sending = false;
        try {
            $this->recipient = $order->get_billing_email();

            $sending = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), null);
        } catch (\Exception $e) {
            return false;
        } finally {
            $this->delete_attachment($label_path);

            return $sending;
        }
    }

    protected function delete_attachment($file_name)
    {
        unlink($file_name);
    }
}
