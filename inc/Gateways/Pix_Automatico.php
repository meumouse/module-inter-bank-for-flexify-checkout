<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Gateways;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank\API\Pix_Automatico_API;
use MeuMouse\Flexify_Checkout\Admin\Admin_Options;
use chillerlan\QRCode\QRCode;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Pix Automático payment gateway
 *
 * @since 1.4.0
 * @package MeuMouse.com
 */
class Pix_Automatico extends Base_Gateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    public $id = 'interpixautomatico';

    /**
     * API instance
     *
     * @var Pix_Automatico_API
     */
    public $api;

    public $title;
    public $description;
    public $email_instructions;

    /**
     * Constructor
     * 
     * @since 1.4.0
     * @return void
     */
    public function __construct() {
        parent::__construct();

        $this->icon               = apply_filters( 'inter_bank_pix_automatico_icon', FD_MODULE_INTER_ASSETS . 'img/pix.svg' );
        $this->has_fields         = false;
        $this->method_title       = __( 'Pix Automático Banco Inter', 'module-inter-bank-for-flexify-checkout' );
        $this->method_description = __( 'Receba autorizações de Pix Automático diretamente pelo Banco Inter.', 'module-inter-bank-for-flexify-checkout' );
        $this->endpoint           = 'pix-automatico/v1';

        $this->title             = Admin_Options::get_setting( 'pix_automatico_gateway_title', __( 'Pix Automático', 'module-inter-bank-for-flexify-checkout' ) );
        $this->description       = Admin_Options::get_setting( 'pix_automatico_gateway_description' );
        $this->email_instructions = Admin_Options::get_setting( 'pix_automatico_gateway_email_instructions' );

        add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
        add_action( 'woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ], 1000 );
        add_action( 'woocommerce_my_account_my_orders_actions', [ $this, 'my_account_button' ], 10, 2 );
        add_action( 'woocommerce_order_details_after_customer_details', [ $this, 'my_account_order_details' ] );

        $this->api = new Pix_Automatico_API( $this );
    }


    /**
     * Fields for gateway settings
     *
     * @since 1.4.0
     * @return array
     */
    public function gateway_fields() {
        return apply_filters( $this->id . '_setting_fields', [
            'enabled' => [
                'title'   => __( 'Ativar/Desativar', 'module-inter-bank-for-flexify-checkout' ),
                'type'    => 'checkbox',
                'label'   => __( 'Ativar Pix Automático do Banco Inter', 'module-inter-bank-for-flexify-checkout' ),
                'default' => 'no',
            ],
        ] );
    }


    /**
     * Process the payment
     *
     * @since 1.4.0
     * @param int $order_id | Order ID
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $result = $this->api->create_contract( $order );
        $txid = $result->id ?? $result->txid ?? '';

        if ( $txid ) {
            $order->set_transaction_id( $txid );
            $order->add_meta_data( 'inter_pix_automatico_txid', $txid, true );
        }

        $payload = $result->pixCopiaECola ?? $result->brcode ?? '';

        if ( $payload ) {
            $order->add_meta_data( 'inter_pix_automatico_payload', $payload, true );
            try {
                $order->add_meta_data( 'inter_pix_automatico_qrcode', ( new QRCode() )->render( $payload ), true );
            } catch ( \Throwable $th ) {
                $order->add_order_note( 'Erro ao gerar QR Code do Pix Automático: ' . $th->getMessage() );
            }
        }

        $order->add_meta_data( 'inter_pix_automatico_contract', $result, true );
        $order->set_status( 'on-hold', __( 'Aguardando autorização do Pix Automático.', 'module-inter-bank-for-flexify-checkout' ) );
        $order->save();

        WC()->cart->empty_cart();

        do_action( 'module_inter_bank_pix_automatico_new_contract', $order, $result );

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        ];
    }


    /**
     * Email instructions
     * 
     * @since 1.4.0
     * @return void
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
        if ( ! $order->has_status( 'on-hold' ) || $sent_to_admin ) {
            return;
        }

        if ( $this->id !== $order->get_payment_method() ) {
            return;
        }

        wc_get_template( 'checkout/pix-automatico-details.php', [
            'id'               => $this->id,
            'order'            => $order,
            'is_email'         => true,
            'instructions'     => $this->email_instructions,
            'pix_details_page' => $order->get_checkout_payment_url( true ),
            'payload'          => $order->get_meta( 'inter_pix_automatico_payload' ),
            'pix_image'        => $order->get_meta( 'inter_pix_automatico_qrcode' ),
        ], '', FD_MODULE_INTER_TPL_PATH );
    }


    /**
     * Button on My Account orders list
     * 
     * @since 1.4.0
     * @return array
     */
    public function my_account_button( $actions, $order ) {
        if ( ! $order->has_status( 'on-hold' ) ) {
            return $actions;
        }

        if ( $this->id !== $order->get_payment_method() ) {
            return $actions;
        }

        $actions[ $this->id ] = [
            'url'  => $order->get_checkout_payment_url( true ),
            'name' => __( 'Ver autorização Pix', 'module-inter-bank-for-flexify-checkout' ),
        ];

        return $actions;
    }


    /**
     * Show details in My Account order page
     * 
     * @since 1.4.0
     * @param object $order | Order object
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

        wc_get_template( 'checkout/pix-automatico-details.php', [
            'id'           => $this->id,
            'order'        => $order,
            'is_email'     => false,
            'instructions' => '',
            'payload'      => $order->get_meta( 'inter_pix_automatico_payload' ),
            'pix_image'    => $order->get_meta( 'inter_pix_automatico_qrcode' ),
        ], '', FD_MODULE_INTER_TPL_PATH );
    }


    /**
     * Thankyou page
     * 
     * @since 1.4.0
     * @param int $order_id | Order ID
     * @return void
     */
    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( $this->id !== $order->get_payment_method() ) {
            return;
        }

        if ( $order->is_paid() ) {
            echo '<p class="cart-empty woocommerce-message">' . esc_html__( 'Seu pagamento foi confirmado! Agora iremos prosseguir com as próximas etapas do seu pedido.', 'module-inter-bank-for-flexify-checkout' ) . '</p>';
            return;
        }

        if ( ! $order->has_status( 'on-hold' ) ) {
            return;
        }

        ?>
        <script>
        jQuery(function($){
            var BancoInterPixAutoCheckParams = <?php echo json_encode([
                'interval' => 5,
                'wc_ajax_url' => \WC_AJAX::get_endpoint('%%endpoint%%'),
                'orderId' => intval( $order->get_id() ),
                'orderKey' => esc_attr( $order->get_order_key() ),
            ]); ?>;

            var BancoInterPixAutoCheck = {
                init: function(){
                    if ( typeof BancoInterPixAutoCheckParams === 'undefined' ) {
                        return;
                    }
                    this.checkOrderStatus();
                },
                checkOrderStatus: function(){
                    var interval = setInterval(function(){
                        $.ajax({
                            url: BancoInterPixAutoCheckParams.wc_ajax_url.toString().replace('%%endpoint%%','inter_bank_order_is_paid'),
                            type: 'POST',
                            data: {
                                order_id: BancoInterPixAutoCheckParams.orderId,
                                order_key: BancoInterPixAutoCheckParams.orderKey,
                            },
                            success: function(response){
                                if ( response?.data?.result === 'yes' ) {
                                    clearInterval(interval);
                                    if ( response?.data?.redirect ) {
                                        window.location.href = response.data.redirect;
                                    } else {
                                        document.location.reload();
                                    }
                                }
                            }
                        });
                    }, parseInt(BancoInterPixAutoCheckParams.interval) * 1000);
                }
            };
            BancoInterPixAutoCheck.init();
        });
        </script>
        <?php

        wc_get_template( 'checkout/pix-automatico-details.php', [
            'id'           => $this->id,
            'order'        => $order,
            'is_email'     => false,
            'instructions' => '',
            'payload'      => $order->get_meta( 'inter_pix_automatico_payload' ),
            'pix_image'    => $order->get_meta( 'inter_pix_automatico_qrcode' ),
        ], '', FD_MODULE_INTER_TPL_PATH );
    }


    /**
     * Handle webhooks from Banco Inter
     * 
     * @since 1.4.0
     * @return void
     */
    public function handle_webhooks() {
        $content = json_decode( file_get_contents( 'php://input' ), true );

        $this->api->log( 'pix automatico webhook recebido: ' . print_r( $content, true ) );

        if ( empty( $content['txid'] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid body' ], 400 );
        }

        $orders = wc_get_orders( [ 'transaction_id' => $content['txid'] ] );

        if ( empty( $orders[0] ) ) {
            wp_send_json_error( [ 'message' => 'Order not found' ], 404 );
        }

        $order = $orders[0];

        if ( $order->is_paid() ) {
            wp_send_json_success( [ 'message' => 'Already processed' ] );
        }

        $order->payment_complete();
        $order->add_order_note( __( 'Pagamento confirmado via Pix Automático.', 'module-inter-bank-for-flexify-checkout' ) );

        /**
         * Triggered when a Pix Automático authorization/payment is confirmed.
         * Allows other parts of the system to create subsequent charges or update data.
         */
        do_action( 'module_inter_bank_pix_automatico_confirmed', $order, $content );

        /**
         * Hook fired for each charge processed via Pix Automático.
         */
        do_action( 'module_inter_bank_pix_automatico_new_charge', $order, $content );

        wp_send_json_success( [ 'message' => 'Success!' ] );
    }
}