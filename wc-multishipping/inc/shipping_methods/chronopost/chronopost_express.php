<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__ . DS . 'chronopost_abstract_shipping.php';

class chronopost_express extends chronopost_abstract_shipping {

	const ID = 'chronopost_express';

	public function __construct( $instance_id = 0 ) {
		$this->id = self::ID;

		$this->method_title = __( 'Chronopost - Livraison express à domicile en Europe', 'wc-multishipping' );

		$this->method_description = 'Les colis sont livrés en Europe en 1 à 3 jours, en 48 heures dans les DOM et en 2 à 5 jours dans le reste du monde.';

		$this->product_code = '17';

		$this->return_product_code = '01';

		parent::__construct( $instance_id );
	}
}
