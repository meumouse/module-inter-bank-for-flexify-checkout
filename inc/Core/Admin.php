<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Core;

use MeuMouse\Flexify_Checkout\Admin\Admin_Options;
use MeuMouse\Flexify_Checkout\API\License;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add plugin settings to Flexify Checkout admin
 * 
 * @since 1.2.0
 * @version 1.4.0
 * @package MeuMouse.com
 */
class Admin {

	/**
	 * Construct function
	 * 
	 * @since 1.3.0
	 * @version 1.4.0
	 * @return void
	 */
	public function __construct() {
		// add default options
		add_filter( 'Flexify_Checkout/Admin/Set_Default_Options', array( $this, 'add_inter_bank_admin_options' ) );

		// create folder to paste certificates
		add_action( 'plugins_loaded', array( $this, 'certificates_folder' ), 100 );

		// disable inter bank gateways if deactivated
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'disable_inter_bank_gateways' ) );

		add_action( 'plugins_loaded', function() {
			$this->migrate_flexify_option_value(
				'5.0.0',
				'inter_bank_env_mode',
				array(
					'yes' => 'production',
					'no' => 'sandbox',
				)
			);
		}, 20 );
	}


	/**
	 * Add options to default on Flexify Checkout
	 *
	 * @since 1.3.0
	 * @version 1.4.0
	 * @param array $options | Options array
	 * @return array
	 */
	public function add_inter_bank_admin_options( $options ) {
		$new_options = array(
			'enable_inter_bank_pix_api' => 'no',
			'enable_inter_bank_ticket_api' => 'no',
			'pix_gateway_title' => 'Pix',
			'pix_gateway_description' => 'Pague via transferência imediata Pix a qualquer hora, a aprovação é imediata!',
			'pix_gateway_email_instructions' => 'Clique no botão abaixo para ver os dados de pagamento do seu Pix.',
			'pix_gateway_receipt_key' => '',
			'pix_gateway_expires' => '30',
			'bank_slip_gateway_title' => 'Boleto bancário',
			'bank_slip_gateway_description' => 'Pague com boleto. Aprovação de 1 a 3 dias úteis após o pagamento.',
			'bank_slip_gateway_email_instructions' => 'Clique no botão abaixo para acessar seu boleto ou utilize a linha digitável para pagar via Internet Banking.',
			'bank_slip_gateway_expires' => '3',
			'bank_slip_gateway_footer_message' => 'Pagamento do pedido #{order_id}. Não receber após o vencimento.',
			'inter_bank_client_id' => '',
			'inter_bank_client_secret' => '',
			'inter_bank_debug_mode' => 'no',
			'inter_bank_env_mode' => 'production',
			'enable_inter_bank_pix_api' => 'yes',
			'enable_inter_bank_ticket_api' => 'yes',
			'inter_bank_expire_date' => '',
			'enable_inter_bank_pix_automatico_api' => 'no',
			'pix_automatico_gateway_title' => 'Pix Automático',
			'pix_automatico_gateway_description' => 'Pague via Pix Automático com cobrança recorrente.',
			'pix_automatico_gateway_receipt_key' => '',
			'enable_inter_retry_billing_policy' => 'yes',
		);

		$options = array_merge( $options, $new_options );

		return $options;
	}


   	/**
	 * Create paste and archive .htaccess for certificates
	 * 
	 * @since 1.0.0
	 * @version 1.2.0
	 * @return void
	 */
	public static function certificates_folder() {
		$upload_dir = wp_upload_dir();
		$folder_path = $upload_dir['basedir'] . '/flexify_checkout_integrations';

		if ( ! file_exists( $folder_path ) ) {
			mkdir( $folder_path );
		}

		$file_path = $folder_path . '/.htaccess';

		if ( ! file_exists( $file_path ) ) {
			file_put_contents( $file_path, 'deny from all' );
		}
	}


   /**
	 * Disable Inter bank gateways
	 * 
	 * @since 1.2.0
	 * @version 1.4.0
	 * @param array $gateways | Available gateways
	 * @return array
	 */
	public function disable_inter_bank_gateways( $gateways ) {
		if ( Admin_Options::get_setting('enable_inter_bank_pix_api') !== 'yes' && isset( $gateways['interpix'] ) ) {
			unset( $gateways['interpix'] );
		}

		if ( Admin_Options::get_setting('enable_inter_bank_ticket_api') !== 'yes' && isset( $gateways['interboleto'] ) ) {
			unset( $gateways['interboleto'] );
		}

		if ( Admin_Options::get_setting('enable_inter_bank_pix_automatico_api') !== 'yes' && isset( $gateways['interpixautomatico'] ) ) {
			unset( $gateways['interpixautomatico'] );
		}

		return $gateways;
	}


	/**
	 * Migrate specific setting inside Flexify Checkout settings
	 *
	 * @since 1.3.0
	 * @param string $version | Minimum version to check for migration
	 * @param string $key | Option key to check and migrate.
	 * @param array  $map | Mapping array to convert old values to new values
	 * @return void
	 */
	public function migrate_flexify_option_value( $version, $key, $map ) {
		if ( ! defined('FLEXIFY_CHECKOUT_VERSION') || version_compare( FLEXIFY_CHECKOUT_VERSION, $version, '<' ) ) {
			return;
		}

		$options = get_option( 'flexify_checkout_settings', array() );

		if ( ! isset( $options[ $key ] ) ) {
			return;
		}

		$current_value = $options[ $key ];

		if ( isset( $map[ $current_value ] ) ) {
			$options[ $key ] = $map[ $current_value ];
			update_option( 'flexify_checkout_settings', $options );
		}
	}

}