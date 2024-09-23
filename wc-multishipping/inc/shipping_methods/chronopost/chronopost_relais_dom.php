<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__ . DS . 'chronopost_abstract_shipping.php';

class chronopost_relais_dom extends chronopost_abstract_shipping {

	const ID = 'chronopost_relais_dom';

	public function __construct( $instance_id = 0 ) {
		$this->id = self::ID;

		$this->method_title = __( 'Chronopost - Livraison Point relais DOM TOM', 'wc-multishipping' );

		$this->method_description = 'Colis livrés en 3 à 4 jours au DOM dans le point d\'enlèvement de votre choix';

		$this->product_code = '4P';

		$this->return_product_code = '01';

		parent::__construct( $instance_id );
	}
}
