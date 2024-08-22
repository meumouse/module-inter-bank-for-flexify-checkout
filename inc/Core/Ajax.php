<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Core;

use MeuMouse\Flexify_Checkout\Init;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Handle AJAX calls
 * 
 * @since 1.2.0
 * @package MeuMouse.com
 */
class Ajax {

   /**
    * Construct function
    * 
    * @since 1.2.0
    * @return void
    */
   public function __construct() {
      add_action( 'wc_ajax_inter_bank_order_is_paid', array( \MeuMouse\Flexify_Checkout\Inter_Bank\Gateways\Pix::class, 'order_is_paid' ), 10, 2 );

      // get AJAX call from upload files from Inter bank module
		add_action( 'wp_ajax_upload_file', array( $this, 'upload_files_callback' ) );

      // Inter bank module actions
      add_action( 'wp_ajax_remove_certificates', array( $this, 'remove_certificates_callback' ) );
   }


   /**
	 * Processing files uploaded for Inter Bank module
	 * 
	 * @since 1.2.0
	 * @return void
	 */
	public function upload_files_callback() {
		// Checks if the file upload action has been triggered
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'upload_file' ) {
			$uploads_dir = wp_upload_dir();
			$upload_path = $uploads_dir['basedir'] . '/flexify_checkout_integrations/';

			// Checks if the file was sent
			if ( ! empty( $_FILES["file"] ) ) {
				$file = $_FILES["file"];
				$type = $_POST["type"];

				// Checks if it is a .crt or .key file
				if ( ( $type === "dropzone-crt" && pathinfo( $file["name"], PATHINFO_EXTENSION ) === "crt") || ( $type === "dropzone-key" && pathinfo( $file["name"], PATHINFO_EXTENSION ) === "key" ) ) {
					$file_tmp_name = $file["tmp_name"];
					$new_file_name = generate_hash(20) . ( $type === "dropzone-crt" ? ".crt" : ".key" );

					move_uploaded_file( $file_tmp_name, $upload_path . $new_file_name );

					update_option('flexify_checkout_inter_bank_' . ($type === "dropzone-crt" ? "crt" : "key") . '_file', $new_file_name);

					$response = array(
						'status' => 'success',
						'message' => __( 'Arquivo carregado com sucesso.', 'module-inter-bank-for-flexify-checkout' ),
					);
			
					wp_send_json( $response ); // send response
				} else {
					$response = array(
						'status' => 'invalid_file',
						'message' => __( 'Arquivo inválido. O arquivo deve ser um .crt ou .key.', 'module-inter-bank-for-flexify-checkout' ),
					);
			
					wp_send_json( $response );
				}
			} else {
				$response = array(
					'status' => 'error',
					'message' => __( 'Erro ao carregar o arquivo. O arquivo não foi enviado.', 'module-inter-bank-for-flexify-checkout' ),
				);
		
				wp_send_json( $response );
			}
		} else {
			$response = array(
				'status' => 'error',
				'message' => __( 'Erro ao carregar o arquivo. A ação não foi acionada corretamente.', 'module-inter-bank-for-flexify-checkout' ),
			);
	
			wp_send_json( $response );
		}
	}


   /**
    * Process AJAX request to remove Inter bank files.
    * 
    * This function handles the AJAX request to remove the certificate and key files
    * for the Inter bank integration and delete the associated options from the database.
    * 
    * @since 1.2.0
    * @return void
    */
   public function remove_certificates_callback() {
      // Check for nonce security
      check_ajax_referer('inter_bank_nonce', 'security');

      $uploads_dir = wp_upload_dir();
      $upload_path = $uploads_dir['basedir'] . '/flexify_checkout_integrations/';
      $crt_file = get_option('flexify_checkout_inter_bank_crt_file');
      $key_file = get_option('flexify_checkout_inter_bank_key_file');

      // Remove certificate file
      if ( ! empty( $crt_file ) ) {
         $file_path = $upload_path . $crt_file;

         if ( file_exists( $file_path ) ) {
            wp_delete_file( $file_path );
         }
      }

      // Remove key file
      if ( ! empty( $key_file ) ) {
         $file_path = $upload_path . $key_file;

         if ( file_exists( $file_path ) ) {
            wp_delete_file( $file_path );
         }
      }

      // Delete options from the database
      $delete_crt = delete_option('flexify_checkout_inter_bank_crt_file');
      $delete_key = delete_option('flexify_checkout_inter_bank_key_file');

      if ( $delete_crt && $delete_key ) {
         $response = array(
            'status' => 'success',
            'toast_header_title' => __( 'Os arquivos foram removidos.', 'module-inter-bank-for-flexify-checkout' ),
            'toast_body_title' => __( 'Arquivos removidos com sucesso!', 'module-inter-bank-for-flexify-checkout' ),
            'reload' => true,
         );
      } else {
         $response = array(
            'status' => 'error',
            'toast_header_title' => __( 'Ops! Ocorreu um erro.', 'module-inter-bank-for-flexify-checkout' ),
            'toast_body_title' => __( 'Ocorreu um erro ao remover os arquivos.', 'module-inter-bank-for-flexify-checkout' ),
            'reload' => false,
         );
      }

      wp_send_json( $response );
   }
}