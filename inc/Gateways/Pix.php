<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Gateways;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank;
use MeuMouse\Flexify_Checkout\Inter_Bank\API\Pix_API;
use MeuMouse\Flexify_Checkout\Admin\Admin_Options;
use chillerlan\QRCode\QRCode;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for extends main class Base_Gateway for add payment gateway Pix on WooCommerce
 * 
 * @since 1.0.0
 * @version 1.3.4
 * @package MeuMouse.com
 */
class Pix extends Base_Gateway {

	/**
	 * $api
	 *
	 * @var Pix_API
	 */
	public $api;
	public $id = 'interpix';
	public $expires_in;
	public $pix_key;

	/**
	 * Track which orders already had the Pix template rendered on thank you page.
	 *
	 * @since 1.3.4
	 * @var array<int, bool>
	 */
	protected static $rendered_thankyou = array();

	/**
	 * Track which orders already had the Pix template rendered on e-mails.
	 *
	 * @since 1.3.4
	 * @var array<int, bool>
	 */
	protected static $rendered_email = array();

	/**
	 * Constructor for the gateway
	 * 
	 * @since 1.0.0
	 * @version 1.3.3
	 * @return void
	 */
	public function __construct() {
		// Load the settings.
		parent::__construct();

		// compatibility with migration from another plugin
		$this->icon = apply_filters( 'inter_bank_pix_icon', FD_MODULE_INTER_ASSETS . 'img/pix.svg' );
		$this->has_fields = false;
		$this->method_title = __( 'Pix Banco Inter', 'module-inter-bank-for-flexify-checkout' );
		$this->method_description = __( 'Receba pagamentos instantâneos via Pix no Banco Inter com aprovação automática.', 'module-inter-bank-for-flexify-checkout' );
		$this->endpoint = 'pix/v2';

		// Define user set variables
		$this->title = Admin_Options::get_setting('pix_gateway_title');
		$this->description = Admin_Options::get_setting('pix_gateway_description');
		$this->email_instructions = Admin_Options::get_setting('pix_gateway_email_instructions');
		$this->expires_in = Admin_Options::get_setting('pix_gateway_expires');
		$this->pix_key = Admin_Options::get_setting('pix_gateway_receipt_key');

		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		add_action( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_account_button' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'my_account_order_details' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'thankyou_page' ), 1000 );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ), 1000 );

		add_filter( 'woocommerce_order_needs_payment', array( $this, 'allow_order_needs_payment' ), 100, 2 );

		$this->api = new Pix_API( $this );
	}


	/**
	 * Fields for the gateway settings
	 * 
	 * @since 1.0.0
	 * @return array
	 */
	public function gateway_fields() {
		return apply_filters( $this->id . '_setting_fields', array(
			'enabled' => array(
				'title' 	=> __( 'Ativar/Desativar', 'module-inter-bank-for-flexify-checkout' ),
				'type' 		=> 'checkbox',
				'label' 	=> __( 'Ativar recebimento via Pix do banco Inter', 'module-inter-bank-for-flexify-checkout' ),
				'default' 	=> 'yes',
			),
		));
	}


	/**
	 * Check if the gateway is available for use.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_available() {
		if ( empty( $this->expires_in ) ) {
			return false;
		}

		if ( empty( $this->pix_key ) ) {
			return false;
		}

		return parent::is_available();
	}


	/**
	 * Process the payment and return the result
	*
	* @since 1.1.0
	* @param int $order_id | Order ID
	* @return array
	*/
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$result = $this->api->create( $order );
		$order->set_transaction_id( $result->txid );
		$order->add_meta_data( 'inter_pix_result', $result, true );
		$order->add_meta_data( 'inter_pix_payload', $result->pixCopiaECola, true );
		$order->add_meta_data( 'inter_pix_txid', $result->txid, true );
		$order->add_meta_data( 'inter_pix_loc', $result->loc, true );
		$order->add_meta_data( 'inter_pix_created_at', $result->calendario->criacao, true );
		$order->add_meta_data( 'inter_pix_expires_in', $result->calendario->expiracao, true );
		$order->set_status( 'on-hold', sprintf( __( 'Pix Banco Inter gerado. Copia e Cola: <code>%s</code>.', 'module-inter-bank-for-flexify-checkout' ), $result->pixCopiaECola ) );

		try {
			$order->add_meta_data( 'inter_pix_qrcode', (new QRCode)->render( $result->pixCopiaECola ), true );
		} catch ( \Throwable $th ) {
			$order->add_order_note( 'Erro ao gerar QR Code: ' . $th->getMessage() );
		}

		$order->save();

		// Remove cart.
		WC()->cart->empty_cart();

		do_action( 'module_inter_bank_payments_new_pix_order', $order, $result );

		// Return thankyou redirect.
		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}


	/**
	 * Display Pix on email instructions
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @param WC_Order $order | Order instance
	 * @param bool $sent_to_admin | If should sent to admin
	 * @param bool $plain_text | Plain text email
	 * @return void
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( ! $order->has_status( 'on-hold' ) || $sent_to_admin ) {
			return;
		}

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		if ( isset( self::$rendered_email[ $order->get_id() ] ) ) {
			return;
		}

		self::$rendered_email[ $order->get_id() ] = true;

		wc_get_template( 'checkout/pix-details.php',
			array(
				'id' => $this->id,
				'order' => $order,
				'is_email' => true,
				'email_instructions' => $this->email_instructions,
				'instructions' => $this->email_instructions,
				'pix_details_page' => $order->get_checkout_payment_url( true ),
				'payload' => $order->get_meta('inter_pix_payload'),
				'pix_image' => $order->get_meta('inter_pix_qrcode'),
			),
			'',
			FD_MODULE_INTER_TPL_PATH
		);
	}


	/**
	 * Display view Pix on my accouny orders
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @param array $actions | Table actions
	 * @param WC_Order $order | Order instance
	 * @return array
	 */
	public function my_account_button( $actions, $order ) {
		if ( ! $order->has_status( 'on-hold' ) ) {
			return $actions;
		}

		if ( $this->id !== $order->get_payment_method() ) {
			return $actions;
		}

		$actions[ $this->id ] = array(
			'url' => $order->get_checkout_payment_url( true ),
			'name' => __( 'Ver pix', 'module-inter-bank-for-flexify-checkout' ),
		);

		return $actions;
	}


	/**
	 * Display Pix details on order details
	 * 
	 * @since 1.0.0
	 * @param WC_Order $order | Order instance
	 * @return void
	 */
	public function my_account_order_details( $order ) {
		if ( ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		if ( is_checkout() ) {
			return;
		}

		wc_get_template( 'checkout/pix-details.php',
			array(
				'id' => $this->id,
				'order' => $order,
				'is_email' => false,
				'instructions' => '',
				'pix_details_page' => $order->get_checkout_payment_url( true ),
				'payload' => $order->get_meta('inter_pix_payload'),
				'pix_image' => $order->get_meta('inter_pix_qrcode'),
			),
			'',
			FD_MODULE_INTER_TPL_PATH
		);
	}


	/**
	 * Display Pix details on thankyou page
	 * 
	 * @since 1.0.0
	 * @param int $order_id | Order ID
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $this->id !== $order->get_payment_method() ) {
			return;
		}

		if ( $order->is_paid() ) {
			echo '<p class="cart-empty woocommerce-message">Seu pagamento foi confirmado! Agora iremos prosseguir com as próximas etapas do seu pedido.</p>';
			return;
		}

		if ( ! $order->has_status('on-hold') ) {
			return;
		}

		if ( isset( self::$rendered_thankyou[ $order->get_id() ] ) ) {
			return;
		}

		self::$rendered_thankyou[ $order->get_id() ] = true; ?>

		<script>
			jQuery( function($) {
				var BancoInterPixCheckParams = <?php echo json_encode( array(
					'interval' => 5,
					'wc_ajax_url' => \WC_AJAX::get_endpoint('%%endpoint%%'),
					'orderId' => intval( $order->get_id() ),
					'orderKey' => esc_attr( $order->get_order_key() ),
				)); ?>;

				/**
				* Main file.
				*
				* @type {Object}
				*/
				var BancoInterPixCheck = {
					/**
					* Initialize actions.
					*/
					init: function() {
						if ( 'undefined' === typeof BancoInterPixCheckParams ) {
							return;
						}

						this.checkOrderStatus()
					},

					checkOrderStatus: function() {
						var interval = setInterval(() => {
							$.ajax({
								url: BancoInterPixCheckParams.wc_ajax_url.toString().replace( '%%endpoint%%', 'inter_bank_order_is_paid' ),
								type: 'POST',
								data: {
									order_id: BancoInterPixCheckParams.orderId,
									order_key: BancoInterPixCheckParams.orderKey,
								},
								success: function( response ) {
									console.log('order status check', response)

									if ( 'yes' === response?.data?.result ) {
										clearInterval(interval)

										$(document.body).block({
											message: null,
											overlayCSS: {
												background: '#fff',
												opacity: 0.6,
											}
										});

										if ( response?.data?.redirect ) {
											window.location.href = response.data.redirect;
										} else {
											document.location.reload();
										}
									}
								},
								fail: function(error) {
									console.log( 'status check error', error, error.code )
								},
							} ).always( function(response) {
								// self.unblock();
							});
						}, parseInt( BancoInterPixCheckParams.interval ) * 1000 );
					}
				}

				BancoInterPixCheck.init();
			});
		</script>

		<?php

		wc_get_template( 'checkout/pix-details.php',
			array(
				'id' => $this->id,
				'order' => $order,
				'is_email' => false,
				'instructions' => '',
				'pix_details_page' => $order->get_checkout_payment_url( true ),
				'payload' => $order->get_meta('inter_pix_payload'),
				'pix_image' => $order->get_meta('inter_pix_qrcode'),
			),
			'',
			FD_MODULE_INTER_TPL_PATH
		);
	}


	/**
	 * Handle Pix Webhook
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_webhooks() {
		$content = json_decode( file_get_contents( 'php://input' ), true );

		$this->api->log( 'webhook recebido: ' . print_r($content,true) );

		$errors = $processed = [];

		$response = [
			'message' => 'Success!',
			'version' => FD_MODULE_INTER_VERSION,
		];

		try {
			if ( empty( $content['pix'] ) ) {
				throw new \Exception( 'Payload is invalid!' );
			}

			foreach ( $content['pix'] as $pix ) {
				if ( ! isset( $pix['txid'] ) ) {
					$errors[] = [
						'error' => 'Invalid body',
						'pix' => $pix,
					];

					continue;
				}

				$orders = wc_get_orders( [
					'transaction_id' => $pix['txid'],
				] );

				if ( ! isset( $orders[0] ) ) {
					$errors[] = [
						'error' => 'Order not found',
						'pix' => $pix,
					];

					continue;
				}

				$order = $orders[0];

				if ( $order->get_transaction_id() !== $pix['txid'] ) {
					$errors[] = [
						'error' => 'txid doesnt match',
						'pix' => $pix,
					];

					continue;
				}

				if ( $order->is_paid() ) {
					$errors[] = [
						'error' => 'order ' . $order->get_id() . ' is already paid',
						'pix' => $pix,
					];

					continue;
				}

				$transaction = false;

				try {
					$transaction = $this->api->get( $pix['txid'] );
				} catch ( \Exception $e ) {
					$this->api->log( 'Erro ao consultar pix via webhook: ' . $e->getMessage() );

					if ( empty( $content['is_retry'] ) ) {
						$order->add_order_note( sprintf( __( 'Erro ao consultar Pix no Banco Inter: %s. Haverá nova tentativa.', 'module-inter-bank-for-flexify-checkout' ), $e->getMessage() ) );

						$content['is_retry'] = true;

						WC()->queue()->schedule_single( time() + 300, 'module_inter_bank_retry', array(
							'method' => $this->id,
							'payload' => json_encode( $content ),
							'order_id' => $order->get_id(),
						));
					} else {
						$order->add_order_note( sprintf( __( 'Erro ao consultar Pix no Banco Inter: %s. Não haverá nova tentativa.', 'module-inter-bank-for-flexify-checkout' ), $e->getMessage() ) );
					}

					$errors[] = [
						'error' => 'error on get result: ' . $e->getMessage(),
						'pix' => $pix,
					];

					continue;
				}

				if ( ! $transaction ) {
					$errors[] = [
						'error' => 'transaction not found',
						'pix' => $pix,
					];

					continue;
				}

				if ( wc_format_decimal( $pix['valor'], 2 ) !== wc_format_decimal( $transaction->valor->original, 2 ) ) {
					$errors[] = [
						'error' => 'amount is invalid ' . wc_format_decimal( $pix['valor'], 2 ) . ' !== ' . wc_format_decimal( $transaction->valor->original, 2 ),
						'pix' => $pix,
						'transaction' => $transaction,
					];

					continue;
				}

				if ( ! isset( $transaction->status ) || 'CONCLUIDA' !== $transaction->status ) {
					$errors[] = [
						'error' => 'pix not paid yet!',
						'pix' => $pix,
						'transaction' => $transaction,
					];

					continue;
				}

				$processed[] = $pix;

				$order->payment_complete();
				$order->add_order_note( __( 'Pagamento confirmado pelo Banco Inter.', 'module-inter-bank-for-flexify-checkout' ) );
			}
		} catch ( \Exception $e ) {
			$this->api->log( 'Webhook error: ' . $e->getMessage() );

			$response['message'] = $e->getMessage();
		}

		$response['erros'] = $errors;
		$response['processed'] = $processed;

		wp_send_json_success( $response );
	}


	/**
	 * Check if order needs payment
	 * 
	 * @since 1.0.0
	 * @param bool $needs_payment | If order needs payment
	 * @param WC_Order $order | Order instance
	 * @return bool
	 */
	public function allow_order_needs_payment( $needs_payment, $order ) {
		if ( $this->id !== $order->get_payment_method() ) {
			return $needs_payment;
		}

		if ( $order->has_status('on-hold') && $order->get_total() > 0 ) {
			return true;
		}

		return $needs_payment;
	}


	/**
	 * Handle AJAX call when order is paid
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public static function order_is_paid() {
		try {
			if ( empty( $_POST['order_id'] ) || empty( $_POST['order_key'] ) ) {
				throw new \Exception( __( 'Requisição inválida', 'module-inter-bank-for-flexify-checkout' ) );
			}

			$order = wc_get_order( intval( $_POST['order_id'] ) );

			if ( ! $order ) {
				throw new \Exception( __( 'Pedido não encontrado', 'module-inter-bank-for-flexify-checkout' ) );
			}

			if ( $order->get_order_key() !== esc_attr( $_POST['order_key'] ) ) {
				throw new \Exception( __( 'Chave inválida!', 'module-inter-bank-for-flexify-checkout' ) );
			}

			wp_send_json_success( array(
				'message' => __( 'Pedido consultado!', 'module-inter-bank-for-flexify-checkout' ),
				'result' => wc_bool_to_string( $order->is_paid() ),
				'cancelled' => wc_bool_to_string( $order->has_status( ['cancelled', 'failed'] ) ),
				'redirect' => $order->is_paid() ? $order->get_checkout_order_received_url() : null,
			));
		} catch ( \Exception $th ) {
			wp_send_json_error( array(
				'message' => $th->getMessage(),
				'result' => 'no',
				'cancelled' => 'no',
			));
		}

		wp_send_json_success('yes');
	}
}