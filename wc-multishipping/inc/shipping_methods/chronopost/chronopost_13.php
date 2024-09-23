<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__ . DS . 'chronopost_abstract_shipping.php';

class chronopost_13 extends chronopost_abstract_shipping {

	const ID = 'chronopost_13';

	public function __construct( $instance_id = 0 ) {
		$this->id = self::ID;

		$this->method_title = __( 'Chronopost - Livraison express à domicile avant 13 heures', 'wc-multishipping' );

		$this->method_description = 'Livraison des colis le lendemain avant 13h à votre domicile. La veille de la livraison, vous serez prévenu par e-mail et par SMS.';

		$this->product_code = '01';

		$this->return_product_code = '4T';

		parent::__construct( $instance_id );
	}
}
