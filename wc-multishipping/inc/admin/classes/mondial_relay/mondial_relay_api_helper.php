<?php


namespace WCMultiShipping\inc\admin\classes\mondial_relay;


use SoapClient;

class mondial_relay_api_helper
{
    const TRACKING_API_KEY = "";

    const API_URL = 'https://api.mondialrelay.com/Web_Services.asmx?wsdl';

    var $error_code = 1;

    var $error_message = '';

    public function get_pickup_point($params)
    {
        if (empty($params)) return false;

        ini_set('default_socket_timeout', 5000);
        $client = new SoapClient(self::API_URL, [
            'trace'              => true,
            'cache_wsdl'         => WSDL_CACHE_NONE,
            'connection_timeout' => 5000,
        ]);
        $result = $client->WSI4_PointRelais_Recherche($params);

        return $result->WSI4_PointRelais_RechercheResult;

        return false;
    }

    public function register_parcels($params)
    {
        $shipping_service_url = 'https://api.mondialrelay.com/Web_Services.asmx?wsdl';
        $client = new SoapClient($shipping_service_url, ['trace' => true]);
        $result = $client->WSI2_CreationEtiquette($params);

        if (!isset($result->WSI2_CreationEtiquetteResult->STAT) || $result->WSI2_CreationEtiquetteResult->STAT !== '0') {
            if (is_admin()) {
                wms_logger(
                    sprintf(
                        __('Order %s : Parcel not generated. %s', 'wc-multishipping'),
                        $params['NDossier'], print_r($result->WSI2_CreationEtiquetteResult, true)
                    )
                );
            }
        }

        return $result->WSI2_CreationEtiquetteResult;
        wms_logger(__('Webservice error: Soap Connection Issue (shippingMultiParcelWithReservationV3)', 'wc-multishipping'));

        return false;
    }

    public function get_labels_from_api($label_URL)
    {

        $label_URL = 'https://www.mondialrelay.com'.$label_URL;

        $response = wp_remote_get($label_URL);
        $http_code = wp_remote_retrieve_response_code($response);
        $pdf_body = wp_remote_retrieve_body($response);

        if ('200' != $http_code) {
            wms_logger(sprintf(__("Mondial Relay ERROR - Get Label : %s => %s", 'wc-multishipping'), $http_code, $pdf_body));

            return false;
        }

        return ($pdf_body);
    }

    public function get_error_message($error_code)
    {

        $error_messages = [
            '0'  => "Opération effectuée avec succès",
            '1'  => 'Enseigne invalide',
            '2'  => 'Numéro d\'enseigne vide ou inexistant',
            '3'  => 'Numéro de compte enseigne invalide',
            '8'  => 'Mot de passe ou hachage invalide',
            '5'  => 'Numéro de dossier enseigne invalide',
            '7'  => 'Numéro de client enseigne invalide(champ NCLIENT)',
            '9'  => 'Ville non reconnu ou non unique',
            '10' => 'Type de collecte invalide',
            '11' => 'Numéro de Relais de Collecte invalide',
            '12' => 'Pays de Relais de collecte invalide',
            '13' => 'Type de livraison invalide',
            '14' => 'Numéro de Relais de livraison invalide',
            '15' => 'Pays de Relais de livraison invalide',
            '20' => 'Poids du colis invalide',
            '21' => 'Taille(Longueur + Hauteur) du colis invalide',
            '22' => 'Taille du Colis invalide',
            '24' => 'Numéro d\'expédition ou de suivi invalide',
            '26' => 'Temps de montage invalide',
            '27' => 'Mode de collecte ou de livraison invalide',
            '28' => 'Mode de collecte invalide',
            '29' => 'Mode de livraison invalide. Rappel : 1 Colis max pour l\'offre "Start"',
            '30' => 'Adresse(L1) invalide',
            '31' => 'Adresse(L2) invalide',
            '33' => 'Adresse(L3) invalide',
            '34' => 'Adresse(L4) invalide',
            '35' => 'Ville invalide',
            '36' => 'Code postal invalide',
            '37' => 'Pays invalide',
            '38' => 'Numéro de téléphone invalide',
            '39' => 'Adresse e-mail invalide',
            '40' => 'Paramètres manquants',
            '42' => 'Montant CRT invalide',
            '43' => 'Devise CRT invalide',
            '44' => 'Valeur du colis invalide',
            '45' => 'Devise de la valeur du colis invalide',
            '46' => 'Plage de numéro d\'expédition épuisée',
            '47' => 'Nombre de colis invalide',
            '48' => 'Multi - Colis Relais Interdit',
            '49' => 'Action invalide',
            '60' => 'Champ texte libre invalide(Ce code erreur n\'est pas invalidant)',
            '61' => 'Top avisage invalide',
            '62' => 'Instruction de livraison invalide',
            '63' => 'Assurance invalide',
            '64' => 'Temps de montage invalide',
            '65' => 'Top rendez - vous invalide',
            '66' => 'Top reprise invalide',
            '67' => 'Latitude invalide',
            '68' => 'Longitude invalide',
            '69' => 'Code Enseigne invalide',
            '70' => 'Numéro de Point Relais invalide',
            '71' => 'Nature de point de vente non valide',
            '74' => 'Langue invalide',
            '78' => 'Pays de Collecte invalide',
            '79' => 'Pays de Livraison invalide',
            '80' => 'Code tracing : Colis enregistré',
            '81' => 'Code tracing : Colis en traitement chez Mondial Relay',
            '82' => 'Code tracing : Colis livré',
            '83' => 'Code tracing : Anomalie',
            '84' => '(Réservé Code Tracing)',
            '85' => '(Réservé Code Tracing)',
            '86' => '(Réservé Code Tracing)',
            '87' => '(Réservé Code Tracing)',
            '88' => '(Réservé Code Tracing)',
            '89' => '(Réservé Code Tracing)',
            '92' => 'Le code pays du destinataire et le code pays du Point Relais doivent être identiques ou Solde insuffisant(comptes prépayés)',
            '93' => 'Aucun élément retourné par le plan de tri Si vous effectuez une collecte ou une livraison en Point Relais, vérifiez que les Point Relais sont bien disponibles.Si vous effectuez une livraison à domicile, il est probable que le code postal que vous avez indiqué n\'existe pas.',
            '94' => 'Colis Inexistant',
            '95' => 'Compte Enseigne non activé',
            '96' => 'Type d\'enseigne incorrect en Base',
            '97' => 'Clé de sécurité invalide Cf. : § «Génération de la clé de sécurité»',
            '98' => 'Erreur générique(Paramètres invalides) Cette erreur masque une autre erreur de la liste et ne peut se produire que dans le cas où le compte utilisé est en mode «Production».Cf. : § «Fonctionnement normal et débogage»',
            '99' => 'Erreur générique du service. Cette erreur peut être due à un problème technique du service. Veuillez notifier cette erreur à Mondial Relay en précisant la date et l\'heure de la requête ainsi que les paramètres envoyés afin d\'effectuer une vérification.',
        ];

        if (isset($error_messages[$error_code])) return $error_messages[$error_code];

        return '';
    }


    public function get_status($params)
    {
        if (empty($params)) return false;
        $client = new SoapClient(self::API_URL);
        $result = $client->WSI2_TracingColisDetaille($params);

        return $result->WSI2_TracingColisDetailleResult;
    }

}
