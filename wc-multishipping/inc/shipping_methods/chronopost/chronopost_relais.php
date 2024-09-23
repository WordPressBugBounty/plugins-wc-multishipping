<?php


namespace WCMultiShipping\inc\shipping_methods\chronopost;

require_once __DIR__ . DS . 'chronopost_abstract_shipping.php';

class chronopost_relais extends chronopost_abstract_shipping {

	const ID = 'chronopost_relais';

	public function __construct( $instance_id = 0 ) {
		$this->id = self::ID;

		$this->method_title = __( 'Chronopost - Livraison Express en point relais', 'wc-multishipping' );

		$this->method_description = 'Colis livrés le lendemain avant 13h dans le Pickup de votre choix. Vous serez averti par e-mail et par SMS.';

		$this->product_code = '86';

		$this->return_product_code = '01';

		parent::__construct( $instance_id );
	}

}
