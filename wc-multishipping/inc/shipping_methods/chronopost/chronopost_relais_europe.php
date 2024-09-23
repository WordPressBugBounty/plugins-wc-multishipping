<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__ . DS . 'chronopost_abstract_shipping.php';

class chronopost_relais_europe extends chronopost_abstract_shipping {

	const ID = 'chronopost_relais_europe';

	public function __construct( $instance_id = 0 ) {
		$this->id = self::ID;

		$this->method_title = __( 'Chronopost - Livraison point relais Europe', 'wc-multishipping' );

		$this->method_description = 'Les colis sont livrés en 2 à 6 jours en Europe dans le point d\'enlèvement de votre choix.';

		$this->product_code = '49';

		$this->return_product_code = '3T';

		parent::__construct( $instance_id );
	}
}
