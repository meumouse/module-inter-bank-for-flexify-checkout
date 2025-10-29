<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Gateways;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank;
use MeuMouse\Flexify_Checkout\Inter_Bank\API\Bank_Slip_API;
use MeuMouse\Flexify_Checkout\Inter_Bank\API\Webhooks_API;
use MeuMouse\Flexify_Checkout\Inter_Bank\Bank_Slip\Print_Bank_Slip;
use MeuMouse\Flexify_Checkout\Admin\Admin_Options;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Bank Slip Gateway Class
 * 
 * @since 1.0.0
 * @version 1.3.4
 * @package MeuMouse.com
 */
class Bank_Slip extends Base_Gateway {

	/**
	 * Gateway ID
	 * 
	 * @since 1.0.0
	 * @version 1.3.2
	 */
	public $id = 'interboleto';

	public $ticket_messages;
	public $expires_in;

	/**
	 * Track which orders already had the bank slip template rendered on thank you page.
	 *
	 * @since 1.3.4
	 * @var array<int, bool>
	 */
	protected static $rendered_thankyou = array();

	/**
	 * Track which orders already had the bank slip template rendered on e-mails.
	 *
	 * @since 1.3.4
	 * @var array<int, bool>
	 */
	protected static $rendered_email = array();

	/**
	 * Constructor for the gateway
	 * 
	 * @since 1.0.0
	 * @version 1.3.4
	 * @return void
	 */
	public function __construct() {
		// Load the settings.
		parent::__construct();

		$this->icon = apply_filters( 'inter_bank_ticket_icon', FD_MODULE_INTER_ASSETS . 'img/bank-slip.svg' );
		$this->has_fields = false;
		$this->method_title = __( 'Boleto Banco Inter', 'module-inter-bank-for-flexify-checkout' );
		$this->method_description = __( 'Receba pagamentos no Banco Inter com aprovação automática.', 'module-inter-bank-for-flexify-checkout' );
		$this->endpoint = 'cobranca/v3/cobrancas';

		// Define user set variables
		$this->title = Admin_Options::get_setting('bank_slip_gateway_title');
		$this->description = Admin_Options::get_setting('bank_slip_gateway_description');
		$this->email_instructions = Admin_Options::get_setting('bank_slip_gateway_email_instructions');
		$this->ticket_messages = Admin_Options::get_setting('bank_slip_gateway_footer_message');
		$this->expires_in = Admin_Options::get_setting('bank_slip_gateway_expires');

		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_account_button' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'my_account_order_details' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ), 1000 );

		$this->api = new Bank_Slip_API( $this );
	}


	/**
	 * Gateway field on WooCommerce panel settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function gateway_fields() {
		return apply_filters( $this->id . '_setting_fields', array(
			'enabled' => array(
				'title' => __( 'Ativar/Desativar', 'module-inter-bank-for-flexify-checkout' ),
				'type' => 'checkbox',
				'label' => __( 'Ativar boleto Banco Inter', 'module-inter-bank-for-flexify-checkout' ),
				'default' => 'yes',
			),
		));
	}


	/**
	 * Process the payment and return the result
	*
	* @since 1.0.0
	* @version 1.1.0
	* @param int $order_id | Order ID
	* @return array
	*/
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$response_code = $this->api->create( $order );
		$collection_details = $this->api->get_collection_details( strval( $response_code->codigoSolicitacao ) );
		
		// Add the necessary meta data
		$order->add_meta_data( 'inter_boleto_url', Print_Bank_Slip::get_bank_slip_url( $order->get_order_key() ), true );
		$order->add_meta_data( 'inter_codigo_solicitacao', $response_code->codigoSolicitacao, true );
		$order->add_meta_data( 'inter_barcode', $collection_details->boleto->codigoBarras, true );
		$order->add_meta_data( 'inter_payment_line', $collection_details->boleto->linhaDigitavel, true );
		$order->add_meta_data( 'inter_nossonumero', $collection_details->boleto->nossoNumero, true );
		$order->add_meta_data( 'inter_txid', $collection_details->pix->txid, true );
		$order->add_meta_data( 'inter_pix_copia_cola', $collection_details->pix->pixCopiaECola, true );

		$order->set_status( 'on-hold', sprintf( __( 'Boleto Banco Inter gerado. Linha digitável: <code>%s</code>.', 'module-inter-bank-for-flexify-checkout' ), $collection_details->boleto->linhaDigitavel ) );

		$order->save();
		
		// Remove cart
		WC()->cart->empty_cart();

		do_action( 'module_inter_bank_payments_new_bank_slip_order', $order, $response_code );

		// Return thankyou redirect.
		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}


	/**
	 * Check if the gateway is available for use
	 *
	 * @since 1.0.0
	 * @version 1.3.4
	 * @return bool
	 */
	public function is_available() {
		if ( empty( $this->expires_in ) || WC()->cart && 2.50 > $this->get_order_total() ) {
			return false;
		}

		return parent::is_available();
	}


	/**
	 * Validate bank slip messages
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function validate_ticket_messages_field( $key, $data ) {
		$data = is_array( $data ) ? array_values( array_filter( array_map( 'trim', $data ) ) ) : [];

		return $data;
	}


	/**
	 * Display bank slip details on email instructions
	 * 
	 * @since 1.0.0
	 * @version 1.3.4
	 * @param WC_Order $order | Order instance
	 * @param bool $sent_to_admin | If should sent to admin
	 * @param bool $plain_text | If email is in plain text
	 * @return void
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( ! $order->has_status('on-hold') || $sent_to_admin || $this->id !== $order->get_payment_method() ) {
			return;
		}

		if ( isset( self::$rendered_email[ $order->get_id() ] ) ) {
			return;
		}

		self::$rendered_email[ $order->get_id() ] = true;

		wc_get_template( 'emails/bank-slip-details.php',
			array(
				'url' => $order->get_meta('inter_boleto_url'),
				'email_instructions' => $this->email_instructions,
				'label' => __( 'Imprimir boleto', 'module-inter-bank-for-flexify-checkout' ),
				'color' => get_option( 'woocommerce_email_base_color', '#3EA901' ),
				'inter_payment_line' => $order->get_meta('inter_payment_line'),
				'txid' => $order->get_meta('inter_txid'),
				'pix_copia_cola' => $order->get_meta('inter_pix_copia_cola'),
			),
			'',
			FD_MODULE_INTER_TPL_PATH
		);
	}


	/**
	 * Display view bank slip on my account orders
	 * 
	 * @since 1.0.0
	 * @version 1.3.4
	 * @param array $actions | Table actions
	 * @param WC_Order $order | Order instance
	 * @return array
	 */
	public function my_account_button( $actions, $order ) {
		if ( ! $order->has_status('on-hold') || $this->id !== $order->get_payment_method() ) {
			return $actions;
		}

		$actions[$this->id] = array(
			'url' => $order->get_meta('inter_boleto_url'),
			'name' => __( 'Ver boleto', 'module-inter-bank-for-flexify-checkout' ),
		);

		return $actions;
	}


	/**
	 * Display bank slip details on order details
	 * 
	 * @since 1.0.0
	 * @version 1.3.4
	 * @param object|array $order | Order objetct
	 * @return void
	 */
	public function my_account_order_details( $order ) {
		if ( is_checkout() || ! $order->has_status('on-hold') || $this->id !== $order->get_payment_method() ) {
			return;
		}

		wc_get_template( 'checkout/bank-slip-details.php',
			array(
				'url' => $order->get_meta('inter_boleto_url'),
				'payment_line' => $order->get_meta('inter_payment_line'),
				'id' => $this->id,
				'instructions' =>  $this->description,
				'txid' => $order->get_meta('inter_txid'),
				'pix_copia_cola' => $order->get_meta('inter_pix_copia_cola'),
				'pay_number' => '$pay_number',
				'first_billet' => '$boleto_link',
				'all_billets' => '$carne_link',
				'due_date' => '$due_date',
				'has_installments' => '$has_installments',
			),
			'',
			FD_MODULE_INTER_TPL_PATH
		);
	}


	/**
	 * Display bank slip on thankyou page
	 * 
	 * @since 1.0.0
	 * @version 1.3.4
	 * @param int $order_id | Order ID
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $this->id !== $order->get_payment_method() || $order->is_paid() ) {
			return;
		}

		if ( isset( self::$rendered_thankyou[ $order->get_id() ] ) ) {
			return;
		}

		self::$rendered_thankyou[ $order->get_id() ] = true;

		wc_get_template( 'checkout/bank-slip-details.php',
			array(
				'url' => $order->get_meta('inter_boleto_url'),
				'payment_line' => $order->get_meta('inter_payment_line'),
				'id' => $this->id,
				'instructions' => $this->description,
				'txid' => $order->get_meta('inter_txid'),
				'pix_copia_cola' => $order->get_meta('inter_pix_copia_cola'),
				'pay_number' => '$pay_number',
				'first_billet' => '$boleto_link',
				'all_billets' => '$carne_link',
				'due_date' => '$due_date',
				'has_installments' => '$has_installments',
			),
			'',
			FD_MODULE_INTER_TPL_PATH
		);
	}


	/**
	 * Handle Webhook for process bank slip
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @return void
	 */
	public function handle_webhooks() {
		$content = json_decode( file_get_contents( 'php://input' ), true );
		$this->api->log( 'Webhook: ' . print_r( $content, true ) );
		$response = [];

		// bw compatibility
		if ( isset( $content['nossoNumero'] ) ) {
			$content = [$content];
		}

		foreach ( $content as $single_order ) {
			$response[] = $this->process_single_webhook_order( $single_order );
		}

		wp_send_json_success( $response );
	}


	/**
	 * AJAX process check on Webhook
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @param array $content | Object array from response
	 * @return array Response from Webhook
	 */
	public function process_single_webhook_order( $content ) {
		try {
			$response = [
				'message' => 'Success!',
				'version' => FD_MODULE_INTER_VERSION,
			];

			if ( ! isset( $content['nossoNumero'], $content['situacao'] ) ) {
				throw new \Exception( 'Invalid payload!' );
			}

			if ( 'PAGO' !== $content['situacao'] ) {
				throw new \Exception( 'Invalid param: "situacao" is not "PAGO"!' );
			}

			$order = wc_get_order( $content['seuNumero'] );

			if ( ! $order ) {
				throw new \Exception( 'Order not found!' );
			}

			if ( $order->is_paid() ) {
				throw new \Exception( 'Already paid!' );
			}

			if ( $order->get_transaction_id() !== $content['nossoNumero'] ) {
				throw new \Exception( 'Data does not match!' );
			}

			if ( wc_format_decimal( $content['valorNominal'], 2 ) !== wc_format_decimal( $content['valorTotalRecebimento'], 2 ) ) {
				throw new \Exception( 'Amount paid is invalid!' );
			}

			try {
				$data = $this->api->get( $content['nossoNumero'], '' );
			} catch ( \Exception $e ) {
				$this->api->log( 'Erro ao consultar boleto via webhook: ' . $e->getMessage() );

				if ( empty( $content['is_retry'] ) ) {
					$order->add_order_note( sprintf( __( 'Erro ao consultar Boleto no Banco Inter: %s. Haverá nova tentativa.', 'module-inter-bank-for-flexify-checkout' ), $e->getMessage() ) );

					$content['is_retry'] = true;

					WC()->queue()->schedule_single( time() + 300, 'module_inter_bank_retry', array(
						'method' => $this->id,
						'payload' => json_encode( $content ),
						'order_id' => $order->get_id(),
					));

					throw new \Exception( 'First attempt!' );
				} else {
					$order->add_order_note( sprintf( __( 'Erro ao consultar Boleto no Banco Inter: %s. Não haverá nova tentativa.', 'module-inter-bank-for-flexify-checkout' ), $e->getMessage() ) );

					throw new \Exception( 'Boleto not found after retry!' );
				}
			}

			if ( ! isset( $data->situacao ) ) {
				throw new \Exception( 'Boleto not found!' );
			}

			if ( 'PAGO' !== $data->situacao ) {
				throw new \Exception( 'Boleto is not paid!' );
			}

			$order->payment_complete();
			$order->add_order_note( __( 'Pagamento confirmado pelo Banco Inter.', 'module-inter-bank-for-flexify-checkout' ) );
		} catch ( \Exception $e ) {
			$this->api->log( 'Webhook error: ' . $e->getMessage() );

			$response['message'] = $e->getMessage();
		}

		return $response;
	}


	/**
	 * Regenerate Bank Slip payment details for an order.
	 *
	 * Useful when the original bank slip expired and a new payment
	 * request is required. The function will create a new bank slip
	 * and update all related order metadata.
	 *
	 * @since 1.3.4
	 * @param int $order_id | Order ID
	 * @return object
	 * @throws \Exception When the order is invalid or the gateway doesn't match.
	 */
	public function regenerate_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new \Exception( __( 'Pedido não encontrado.', 'module-inter-bank-for-flexify-checkout' ) );
		}

		if ( $this->id !== $order->get_payment_method() ) {
			throw new \Exception( __( 'O pedido não foi pago com Boleto Banco Inter.', 'module-inter-bank-for-flexify-checkout' ) );
		}

		if ( $order->is_paid() ) {
			throw new \Exception( __( 'O pedido já está pago.', 'module-inter-bank-for-flexify-checkout' ) );
		}

		$response_code = $this->api->create( $order );
		$collection_details = $this->api->get_collection_details( strval( $response_code->codigoSolicitacao ) );

		if ( isset( $collection_details->boleto->nossoNumero ) ) {
			$order->set_transaction_id( $collection_details->boleto->nossoNumero );
		}

		$order->update_meta_data( 'inter_boleto_url', Print_Bank_Slip::get_bank_slip_url( $order->get_order_key() ) );
		$order->update_meta_data( 'inter_codigo_solicitacao', $response_code->codigoSolicitacao );
		$order->update_meta_data( 'inter_barcode', $collection_details->boleto->codigoBarras );
		$order->update_meta_data( 'inter_payment_line', $collection_details->boleto->linhaDigitavel );
		$order->update_meta_data( 'inter_nossonumero', $collection_details->boleto->nossoNumero );
		$order->update_meta_data( 'inter_txid', $collection_details->pix->txid );
		$order->update_meta_data( 'inter_pix_copia_cola', $collection_details->pix->pixCopiaECola );

		$order->set_status( 'on-hold', sprintf( __( 'Boleto Banco Inter gerado. Linha digitável: <code>%s</code>.', 'module-inter-bank-for-flexify-checkout' ), $collection_details->boleto->linhaDigitavel ) );
		$order->add_order_note( __( 'Novo boleto gerado.', 'module-inter-bank-for-flexify-checkout' ) );

		$order->save();

		do_action( 'module_inter_bank_payments_new_bank_slip_order', $order, $response_code );

		return $collection_details;
	}
}