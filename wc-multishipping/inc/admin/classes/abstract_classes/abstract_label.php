<?php

namespace WCMultiShipping\inc\admin\classes\abstract_classes;

abstract class abstract_label {
	const WMS_TABLE_NAME = 'wms_labels';

	const LABEL_FORMAT_PDF = 'PDF';

	const TRACKING_NUMBER_VAR_NAME = 'wms_tracking_numbers';

	const DOWNLOAD_NAME = '';

	const SHIPPING_PROVIDER_ID = '';

	var $payload = [];

	var $errors = [];


	public function save_label_in_db( $order_id, $label, $label_format, $tracking_number, $reservation_number, $is_return_label = false, $outward_tracking_number = '' ) {
		$function_params = [ $order_id, $label, $label_format, $tracking_number, $reservation_number, $is_return_label, $outward_tracking_number ];

		array_walk(
			$function_params,
			function ($one_param) {
				if ( ! isset( $one_param ) ) {
					wms_enqueue_message( __( 'Error while storing label in DB => A parameter is missing' ), 'error' );

					return false;
				}
			}
		);

		global $wpdb;

		$table_name = $wpdb->prefix . self::WMS_TABLE_NAME;

		$label_type = ( ! $is_return_label ? 'outward' : 'inward' );
		$request_action = ( ! $is_return_label ? 'INSERT INTO' : 'UPDATE' );

		$sql = <<<END_SQL
                     $request_action $table_name SET                      
                        order_id = %d,
                        shipping_provider = %s,
                        {$label_type}_label= %s,
                        {$label_type}_label_format= %s,
                        {$label_type}_tracking_number= %s,
                        {$label_type}_label_created_at= %d,
                        {$label_type}_reservation_number= %d
                        
                        
END_SQL;

		$additional_where_conditions = [];
		if ( ! empty( $outward_tracking_number ) )
			$additional_where_conditions['outward_tracking_number'] = $outward_tracking_number;

		if ( ! empty( $additional_where_conditions ) ) {
			$sql_additional_where_condition = implode( '= %s, ', array_keys( $additional_where_conditions ) ) . '= %s';
			$sql .= <<<END_SQL
                    WHERE $sql_additional_where_condition 
END_SQL;
		}

		$sql = $wpdb->prepare(
			$sql,
			array_merge(
				[ 
					$order_id,
					static::SHIPPING_PROVIDER_ID,
					$label,
					$label_format,
					$tracking_number,
					current_time( 'mysql' ),
					$reservation_number,
				],
				$additional_where_conditions
			)
		);

		return $wpdb->query( $sql );
	}

	public static function get_info_from_tracking_numbers( $tracking_numbers ) {
		if ( empty( $tracking_numbers ) ) {
			wms_enqueue_message( __( 'Unable to get info from tracking number => Tracking numbers are empty', 'wc-multishipping' ), 'error' );

			return false;
		}

		if ( ! is_array( $tracking_numbers ) )
			$tracking_numbers = [ $tracking_numbers ];

		array_walk(
			$tracking_numbers,
			function ($one_tracking_number) {
				esc_sql( wms_display_value( $one_tracking_number ) );
			}
		);


		$prepare_in_condition = implode( ',', array_fill( 0, count( $tracking_numbers ), '%s' ) );

		global $wpdb;
		$table_name = $wpdb->prefix . self::WMS_TABLE_NAME;

		$query = <<<END_SQL
SELECT *
FROM $table_name
WHERE (outward_tracking_number IN ($prepare_in_condition)
OR inward_tracking_number IN ($prepare_in_condition))
AND shipping_provider = %s;
END_SQL;

		$query = $wpdb->prepare(
			$query,
			array_merge(
				$tracking_numbers,
				$tracking_numbers,
				[ 
					static::SHIPPING_PROVIDER_ID,
				]
			)
		);


		$pdf_labels = wms_load_object_list( $query, 'order_id' );
		if ( empty( $pdf_labels ) )
			return [];

		return $pdf_labels;
	}

	public static function delete( $tracking_number ) {
		if ( empty( $tracking_number ) ) {
			wms_enqueue_message( __( 'Can\'t delete label => No tracking number selected', 'wc-multishipping' ) );

			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::WMS_TABLE_NAME;

		$query = <<<END_SQL
DELETE FROM $table_name 
WHERE outward_tracking_number IN (%s)
AND shipping_provider = %s
END_SQL;

		$query = $wpdb->prepare(
			$query,
			[ 
				$tracking_number,
				static::SHIPPING_PROVIDER_ID,
			]
		);
		$wpdb->query( $query );

		$query = <<<END_SQL
UPDATE $table_name 

SET inward_reservation_number = null,
inward_label = null, 
inward_label_format = null, 
inward_tracking_number = null,
inward_label_created_at = null 

WHERE inward_tracking_number IN (%s)
AND shipping_provider = %s 
END_SQL;

		$query = $wpdb->prepare(
			$query,
			[ 
				$tracking_number,
				static::SHIPPING_PROVIDER_ID,
			]
		);
		$wpdb->query( $query );

		return true;
	}

	public function generate_labels_from_api( $api_parameter ) {
		if ( empty( $api_parameter ) ) {
			wms_enqueue_message( __( 'Error while generating label from API => Parameter is empty.' ), 'error' );

			return false;
		}


		$api_helper = static::get_api_helper();
		$label = $api_helper->get_labels_from_api( $api_parameter );

		if ( empty( $label ) || ! empty( $label->errorCode ) ) {

			wms_logger( "Class: Label / Function: Generate_labels_from_tracking_number" );
			wms_logger( "----- Details -----" );
			wms_logger( sprintf( "API Parameter: %d", wms_display_value( $api_parameter ) ) );
			wms_logger( sprintf( "return: %d", json_encode( $label ) ) );

			wms_enqueue_message( __( 'Error while generating label from tracking number => API return is invalid.' ), 'error' );

			return false;
		}

		return $label;
	}

	public static function get_url_for_download_label( $tracking_numbers = [] ) {
		if ( empty( $tracking_numbers ) )
			return '';

		if ( ! is_array( $tracking_numbers ) )
			$tracking_numbers = [ $tracking_numbers ];

		$tracking_numbers_params = implode( ',', $tracking_numbers );

		array_walk(
			$tracking_numbers,
			function ($one_tracking_number) {
				wms_display_value( $one_tracking_number );
			}
		);

		$wms_nonce = wp_create_nonce( 'wms_download_label' );

		return admin_url( 'admin-post.php?action=wms_download_label&wms_shipping_provider=' . static::SHIPPING_PROVIDER_ID . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $tracking_numbers_params . '&wms_nonce=' . $wms_nonce );
	}

	public static function download_label_PDF( $tracking_number ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			header( 'HTTP/1.0 401 Unauthorized' );
		}
		try {
			$label = self::get_info_from_tracking_numbers( $tracking_number );
			if ( empty( $label ) ) {
				return;
			}


			if ( $tracking_number == reset( $label )->outward_tracking_number )
				$label = reset( $label )->outward_label;
			else
				$label = reset( $label )->inward_label;

			if ( ! class_exists( 'PDF_lib' ) )
				include( WMS_LIB . 'PDF_lib.class.php' );

			$file_to_download_name = get_temp_dir() . DS . static::DOWNLOAD_NAME . '.outward(' . $tracking_number . ').pdf';
			$label_file_name = 'outward_label.pdf';
			$files_to_merge = [];
			$label_content_file = fopen( sys_get_temp_dir() . DS . $label_file_name, 'w' );
			fwrite( $label_content_file, $label );
			fclose( $label_content_file );

			$files_to_merge[] = sys_get_temp_dir() . DS . $label_file_name;

			@\PDF_lib::merge( $files_to_merge, \PDF_lib::DESTINATION__DISK_DOWNLOAD, $file_to_download_name );
			foreach ( $files_to_merge as $one_file_to_merge ) {
				unlink( $one_file_to_merge );
			}
			unlink( $file_to_download_name );
		} catch (\Exception $e) {
			header( 'HTTP/1.0 404 Not Found' );
		}
	}

	public static function get_url_for_download_labels_zip( $tracking_numbers = [] ) {
		if ( empty( $tracking_numbers ) )
			return '';

		if ( ! is_array( $tracking_numbers ) )
			$tracking_numbers = [ $tracking_numbers ];

		$tracking_numbers_params = implode( ',', $tracking_numbers );

		array_walk(
			$tracking_numbers,
			function ($one_tracking_number) {
				wms_display_value( $one_tracking_number );
			}
		);

		$wms_nonce = wp_create_nonce( 'wms_download_labels_zip' );

		return admin_url( 'admin-post.php?action=wms_download_labels_zip&wms_shipping_provider=' . static::SHIPPING_PROVIDER_ID . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $tracking_numbers_params . '&wms_nonce=' . $wms_nonce );
	}

	public static function download_labels_zip( $tracking_numbers ) {

		if ( ! current_user_can( 'edit_posts' ) ) {
			header( 'HTTP/1.0 401 Unauthorized' );
		}
		if ( empty( $tracking_numbers ) || ! is_array( $tracking_numbers ) )
			return false;

		$zip_file = static::generate_zip( $tracking_numbers );
		if ( empty( $zip_file ) )
			return false;

		try {
			$filename = basename( static::DOWNLOAD_NAME . '_' . date( 'Y-m-d_H-i' ) . '.zip' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Transfer-Encoding: Binary' );
			header( "Content-disposition: attachment; filename=\"$filename\"" );
			wp_die( $zip_file, '', 200 );
		} catch (\Exception $e) {
			header( 'HTTP/1.0 404 Not Found' );
		}
	}


	public static function get_url_for_print_labels( $tracking_numbers = [] ) {
		if ( empty( $tracking_numbers ) )
			return '';

		if ( ! is_array( $tracking_numbers ) )
			$tracking_numbers = [ $tracking_numbers ];

		array_walk(
			$tracking_numbers,
			function ($one_tracking_number) {
				wms_display_value( $one_tracking_number );
			}
		);

		$tracking_numbers_params = implode( ',', $tracking_numbers );

		$wms_nonce = wp_create_nonce( 'wms_print_labels' );

		return admin_url( 'admin-post.php?action=wms_print_label&wms_shipping_provider=' . static::SHIPPING_PROVIDER_ID . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $tracking_numbers_params . '&wms_nonce=' . $wms_nonce );
	}

	public static function print_label_PDF( $tracking_numbers ) {
		if ( empty( $tracking_numbers ) ) {
			return false;
		}

		$tracking_numbers = explode( ',', $tracking_numbers );
		$label_types = [ 'outward', 'inward' ];
		$files_to_merge = [];

		try {
			foreach ( $tracking_numbers as $tracking_number ) {
				$label_info = self::get_info_from_tracking_numbers( $tracking_number );
				if ( empty( $label_info ) ) {
					continue;
				}
				$label = reset( $label_info );

				if ( empty( $label->outward_label ) && empty( $label->inward_label ) ) {
					continue;
				}

				foreach ( $label_types as $label_type ) {
					$label_property = "{$label_type}_label";
					$tracking_property = "{$label_type}_tracking_number";

					if ( ! empty( $label->$label_property ) && $tracking_number == $label->$tracking_property ) {
						$label_file_name = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "label({$tracking_number}).pdf";
						file_put_contents( $label_file_name, $label->$label_property );
						$files_to_merge[] = $label_file_name;
					}
				}
			}

			if ( ! empty( $files_to_merge ) ) {
				if ( ! class_exists( 'PDF_lib' ) ) {
					require_once( WMS_LIB . 'PDF_lib.class.php' );
				}

				\PDF_lib::merge( $files_to_merge, \PDF_lib::DESTINATION__INLINE );

				array_map( 'unlink', $files_to_merge );
			}
		} catch (\Exception $e) {
			error_log( 'Error printing label PDF: ' . $e->getMessage() );
			header( 'HTTP/1.0 404 Not Found' );
		}
	}

	public static function get_url_for_delete_label( $tracking_numbers = [] ) {
		if ( empty( $tracking_numbers ) )
			return '';

		if ( ! is_array( $tracking_numbers ) )
			$tracking_numbers = [ $tracking_numbers ];

		array_walk(
			$tracking_numbers,
			function ($one_tracking_number) {
				wms_display_value( $one_tracking_number );
			}
		);

		$string_tracking_numbers = implode( ',', $tracking_numbers );

		$wms_nonce = wp_create_nonce( 'wms_delete_labels' );

		return admin_url( 'admin-post.php?action=wms_delete_label&wms_shipping_provider=' . static::SHIPPING_PROVIDER_ID . '&' . self::TRACKING_NUMBER_VAR_NAME . '=' . $string_tracking_numbers . '&wms_nonce=' . $wms_nonce );
	}

	public static function delete_label_PDF_from_tracking_number( $tracking_number ) {

		$result = self::delete( $tracking_number );

		if ( ! $result ) {
			wms_enqueue_message( sprintf( __( 'Unable to delete label %s', 'wc-multishipping' ), $tracking_number ), 'error', true );
		} else {
			wms_enqueue_message( sprintf( __( 'Label %s successfully deleted', 'wc-multishipping' ), $tracking_number ), 'success', true );
		}
	}

	private static function generate_zip( $tracking_numbers = [] ) {
		if ( empty( $tracking_numbers ) )
			return;
		if ( ! is_array( $tracking_numbers ) )
			$tracking_numbers = [ $tracking_numbers ];

		$zip = new \ZipArchive();
		$filename = tempnam( sys_get_temp_dir(), static::DOWNLOAD_NAME . '_' );
		$tmp_files = [];
		$tracking_info_already_generated = [];
		try {
			$zip->open( $filename, \ZipArchive::CREATE );
			foreach ( $tracking_numbers as $one_tracking_number ) {
				if ( in_array( $one_tracking_number, $tracking_info_already_generated ) )
					continue;

				$info_from_tracking = static::get_info_from_tracking_numbers( $one_tracking_number );
				if ( empty( $info_from_tracking ) )
					continue;

				$order_labels = reset( $info_from_tracking );

				$zip_dir_name = 'order_' . $order_labels->order_id;
				$zip->addEmptyDir( $zip_dir_name );

				$order_labels_format = ! empty( $order_labels->outward_format ) ? $order_labels->outward_format : 'PDF';

				if ( ! empty( $order_labels->outward_label ) && $order_labels->outward_tracking_number == $one_tracking_number ) {
					$zip->addFromString(
						$zip_dir_name . '/outward_labels/outward_label(' . $one_tracking_number . ').' . strtolower( $order_labels_format ),
						$order_labels->outward_label
					);
					$tracking_info_already_generated[] = $one_tracking_number;
				}
				if ( ! empty( $order_labels->inward_label ) && $order_labels->inward_tracking_number == $one_tracking_number ) {
					$zip->addFromString(
						$zip_dir_name . '/inward_labels/inward_label(' . $one_tracking_number . ').' . strtolower( $order_labels_format ),
						$order_labels->inward_label
					);
					$tracking_info_already_generated[] = $one_tracking_number;
				}
			}

			if ( empty( $tracking_info_already_generated ) )
				return false;

			$zip->close();

			$content = readfile( $filename );

			return $content;
		} finally {
			array_map(
				function ($tmp_file) {
					unlink( $tmp_file );
				},
				$tmp_files
			);
			unlink( $filename );
		}
	}

	public static function save_label_PDF( $tracking_number ) {
		if ( ! current_user_can( 'edit_posts' ) )
			return false;

		try {
			$label = self::get_info_from_tracking_numbers( $tracking_number );
			if ( empty( $label ) ) {
				return;
			}
			$label = reset( $label )->outward_label;

			if ( ! class_exists( 'PDF_lib' ) )
				include( WMS_LIB . 'PDF_lib.class.php' );

			$file_to_save_name = get_temp_dir() . static::DOWNLOAD_NAME . '.outward(' . $tracking_number . ').pdf';
			$label_file_name = 'outward_label.pdf';
			$files_to_merge = [];
			$label_content_file = fopen( sys_get_temp_dir() . DS . $label_file_name, 'w' );
			fwrite( $label_content_file, $label );
			fclose( $label_content_file );

			$files_to_merge[] = sys_get_temp_dir() . DS . $label_file_name;

			@\PDF_lib::merge( $files_to_merge, \PDF_lib::DESTINATION__DISK, $file_to_save_name );
			foreach ( $files_to_merge as $one_file_to_merge ) {
				unlink( $one_file_to_merge );
			}

			return $file_to_save_name;
		} catch (\Exception $e) {
			return false;
		}
	}

}