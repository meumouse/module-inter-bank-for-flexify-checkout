<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Gateways;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank;
use MeuMouse\Flexify_Checkout\Inter_Bank\API\Bank_Slip_API;
use MeuMouse\Flexify_Checkout\Inter_Bank\API\Webhooks_API;
use MeuMouse\Flexify_Checkout\Inter_Bank\Bank_Slip\Print_Bank_Slip;
use MeuMouse\Flexify_Checkout\Admin\Admin_Options;
use WC_Payment_Gateway;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for extends payment gateways from WooCommerce, adding Base_Gateway for extends with Pix and Slip
 * 
 * @since 1.0.0
 * @version 1.2.0
 * @package MeuMouse.com
 */
abstract class Base_Gateway extends WC_Payment_Gateway {

    public $client_id;
    public $client_secret;
    public $cert_key;
    public $cert_crt;
    public $debug;
    public $title;
    public $description;
    public $email_instructions;
    public $endpoint;
    public $api;

    /**
     * Construct function
     * 
     * @since 1.0.0
     * @version 1.1.0
     * @return void
     */
    public function __construct() {
		$this->init_form_fields();
		$this->init_settings();
		$this->client_id = Admin_Options::get_setting('inter_bank_client_id');
		$this->client_secret = Admin_Options::get_setting('inter_bank_client_secret');
		$upload_path = wp_upload_dir()['basedir'] . '/flexify_checkout_integrations/';
		$this->cert_key = $upload_path . get_option('flexify_checkout_inter_bank_key_file');
		$this->cert_crt = $upload_path . get_option('flexify_checkout_inter_bank_crt_file');
		$this->debug = Admin_Options::get_setting('inter_bank_debug_mode') === 'yes';

		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_webhooks' ), 1000 );
		add_action( 'admin_init', array( $this, 'handle_debug' ), 1000 );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }


    /**
     * Gateway specific fields
     *
     * @return array
     */
    public abstract function gateway_fields();


    /**
     * Gateway settings form fields
     * 
     * @since 1.0.0
     * @return void
     */
    public function init_form_fields() {
      	$this->form_fields = array_merge( $this->gateway_fields(), $this->shared_fields() );
    }


    /**
     * Shared fields for display on payment settings page from Pix and ticket
     * 
     * @since 1.0.0
     * @return array
     */
    public function shared_fields() {
		return array(
			'advanced_section' => array(
				'title' => __( 'Avançado', 'wc-banco-inter' ),
				'type' => 'title',
				'desc_tip' => false,
			),
			'webhooks' => array(
				'title' => __( 'Webhooks', 'wc-banco-inter' ),
				'type' => 'webhooks',
				'description' => __( 'O webhook é necessário para dar baixa automática nos pedidos pagos.', 'wc-banco-inter' ),
				'desc_tip' => false,
			),
		);
    }


    /**
     * Check if gateway is available for use
     * 
     * @since 1.0.0
     * @return bool
     */
    public function is_available() {
		if ( 'BRL' !== get_woocommerce_currency() ) {
			return false;
		}

		if ( empty( $this->client_id ) || empty( $this->client_secret ) || empty( $this->cert_key ) || empty( $this->cert_crt ) ) {
			return false;
		}

		return parent::is_available();
    }


    /**
     * Generate Webhooks HTML
     * 
     * @since 1.0.0
     * @param $key | 
     * @param $data | 
     * @return string
     */
    public function generate_webhooks_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title' => '',
			'disabled' => false,
			'class' => '',
			'css' => '',
			'placeholder' => '',
			'type' => 'text',
			'desc_tip' => false,
			'description' => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start(); ?>

		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			
			<td class="forminp">
				<?php
					if ( ! isset( $_GET['section'] ) || $this->id !== $_GET['section'] ) {
					// not the current section! Avoid external calls
					} elseif ( empty( $this->expires_in ) || empty( $this->client_id ) || empty( $this->client_secret ) || empty( $this->cert_key ) || empty( $this->cert_crt ) ) {
						echo '<p>' . __( 'Configure os dados da integração para visualizar as configurações de webhooks.', 'module-inter-bank-for-flexify-checkout' ) . '</p>';
					} else {
						try {
							// always request a new token
							delete_transient( 'module_inter_bank_token_' . $this->id );

							if ( ! file_exists( $this->cert_key ) || ! file_exists( $this->cert_crt ) ) {
								throw new \Exception( __( 'Certificados não encontrados. O caminho informado é inválido.', 'module-inter-bank-for-flexify-checkout' ) );
								update_option('flexify_checkout_inter_bank_webhook', 'disabled');
							}

							if ( defined('BANCO_INTER_DISABLE_WEBHOOKS') && BANCO_INTER_DISABLE_WEBHOOKS ) {
								throw new \Exception( __( 'A Configuração dos Webhooks foram desativadas no wp-config.php', 'module-inter-bank-for-flexify-checkout' ) );
								update_option('flexify_checkout_inter_bank_webhook', 'disabled');
							}

							$api = new Webhooks_API( $this );
							$webhook = $api->get();

							if ( isset( $_GET['debug'] ) ) {
								print_r($webhook);
							}

							$webhook_url = WC()->api_request_url( $this->id );

							if ( defined('BANCOINTER_CUSTOM_WEBHOOK_URL') ) {
								$urls = BANCOINTER_CUSTOM_WEBHOOK_URL;
								$webhook_url = $urls[$this->id];
							}

							$message = __( 'Webhook ativo para consultas.', 'module-inter-bank-for-flexify-checkout' );
							$should_update = isset( $_GET['force_webhook_update'] ) ? true : false;

							if ( ! $webhook ) {
								$should_update = true;
								$message = __( 'Webhook criado com sucesso!', 'module-inter-bank-for-flexify-checkout' );
							} elseif ( $webhook['webhookUrl'] !== $webhook_url ) {
								$should_update = true;

								$message = sprintf( __( 'A URL do webhook foi atualizada. URL anterior: %s', 'module-inter-bank-for-flexify-checkout' ), $webhook['webhookUrl'] );
							}

							if ( $should_update ) {
								$api->create( $webhook_url );
								update_option('flexify_checkout_inter_bank_webhook', 'enabled');
							}

							echo '<p style="font-weight: bold; color: #29921a">' . $message . '</p>';
							echo '<a href="' . admin_url( '?check_inter_bank_payments' ) . '">'. __( 'Verificar pedidos pendentes', 'module-inter-bank-for-flexify-checkout' ) .'</a>';

						} catch ( \Exception $e ) {
							echo '<p style="font-weight: bold; color: #f00">' . sprintf( __( 'Ocorreu um erro ao listar os webhooks: %s', 'module-inter-bank-for-flexify-checkout' ), $e->getMessage() ) . '</p>';
						}
					}
				
				echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
			</td>
		</tr>
		
		<?php return ob_get_clean();
    }


    /**
     * Check manual status for payments
     * 
     * @since 1.0.0
     * @return void
     */
    public function handle_debug() {
		if ( isset( $_GET['check_inter_bank_payments'] ) && current_user_can( 'manage_woocommerce' ) ) {
			do_action( 'module_inter_bank_check_interboletov2' );
			do_action( 'module_inter_bank_check_interpix' );

			wp_die( __( 'Você já pode fechar essa página. A consulta será feita em segundo plano.', 'module-inter-bank-for-flexify-checkout' ) );
		}
    }

    public abstract function handle_webhooks();
}