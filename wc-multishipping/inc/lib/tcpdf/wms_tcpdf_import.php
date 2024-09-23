<?php


require_once(dirname(__FILE__).'/wmsTcpdf.php');
require_once(dirname(__FILE__).'/tcpdf_parser.php');

class WMS_TCPDF_IMPORT extends wms_TCPDF {

	public function importPDF($filename) {
		$rawdata = file_get_contents($filename);
		if ($rawdata === false) {
			$this->Error('Unable to get the content of the file: '.$filename);
		}
		$cfg = array(
			'die_for_errors' => false,
			'ignore_filter_decoding_errors' => true,
			'ignore_missing_filter_decoders' => true,
		);
		try {
			$pdf = new WMS_TCPDF_PARSER($rawdata, $cfg);
		} catch (\Exception $e) {
			die($e->getMessage());
		}
		$data = $pdf->getParsedData();
		unset($rawdata);



		print_r($data); // DEBUG


		unset($pdf);
	}

} // END OF CLASS

