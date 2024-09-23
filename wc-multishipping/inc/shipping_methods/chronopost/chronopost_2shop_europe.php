<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__ . DS . 'chronopost_abstract_shipping.php';

class chronopost_2shop_europe extends chronopost_abstract_shipping {

	const ID = 'chronopost_2shop_europe';

	public function __construct( $instance_id = 0 ) {
		$this->id = self::ID;

		$this->method_title = __( 'Chronopost - Livraison en relais Pickup (2shop) en Europe', 'wc-multishipping' );

		$this->method_description = 'Les colis sont livrés en 2 à 3 jours dans le point d\'enlèvement de votre choix en Europe. Vous serez informé par e-mail.';

		$this->product_code = '6B';

		$this->return_product_code = '6B';

		parent::__construct( $instance_id );
	}

}
