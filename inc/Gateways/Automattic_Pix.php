<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Gateways;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank\API\Automattic_Pix_API;
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
class Automattic_Pix extends Base_Gateway {

    /**
     * Gateway ID
     *
     * @var string
     */
    public $id = 'interpixautomatico';

    /**
     * API instance
     *
     * @var Automattic_Pix_API
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

        $this->icon = apply_filters( 'inter_bank_pix_automatico_icon', FD_MODULE_INTER_ASSETS . 'img/pix.svg' );
        $this->has_fields = false;
        $this->method_title = __( 'Pix Automático Banco Inter', 'module-inter-bank-for-flexify-checkout' );
        $this->method_description = __( 'Receba autorizações de Pix Automático diretamente pelo Banco Inter.', 'module-inter-bank-for-flexify-checkout' );
        $this->endpoint = 'pix-automatico/v1';

        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_cancellation',
            'subscription_amount_changes',
            'subscription_payment_method_change_admin',
            'subscription_payment_method_change_customer',
            'multiple_subscriptions',
        );

        $this->title = Admin_Options::get_setting('pix_automatico_gateway_title');
        $this->description = Admin_Options::get_setting('pix_automatico_gateway_description');
        $this->email_instructions = Admin_Options::get_setting('pix_automatico_gateway_email_instructions');

        add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ), 1000 );
        add_action( 'woocommerce_my_account_my_orders_actions', array( $this, 'my_account_button' ), 10, 2 );
        add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'my_account_order_details' ) );

        if ( function_exists('wcs_get_subscriptions_for_order') ) {
            add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'process_scheduled_subscription_payment' ], 10, 2 );
            add_filter( 'wcs_is_payment_retry_enabled', [ $this, 'filter_subscription_retry_support' ], 10, 2 );
            add_filter( 'woocommerce_subscriptions_is_payment_retry_enabled', [ $this, 'filter_subscription_retry_support' ], 10, 2 );
        }

        add_action( 'module_inter_bank_pix_automatico_new_contract', [ $this, 'store_subscription_contract_meta' ], 10, 3 );
        add_action( 'module_inter_bank_pix_automatico_confirmed', [ $this, 'handle_subscription_payment_confirmation' ], 10, 3 );
        add_action( 'module_inter_bank_pix_automatico_new_charge', [ $this, 'handle_subscription_new_charge' ], 10, 3 );

        $this->api = new Automattic_Pix_API( $this );
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
                'default' => 'yes',
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
        $charge_args = $this->prepare_charge_configuration( $order );
        $result = $this->api->create_contract( $order, $charge_args );
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

        $order->update_meta_data( 'inter_pix_automatico_amount', $charge_args['amount'] );

        if ( isset( $charge_args['due_days'] ) ) {
            $order->update_meta_data( 'inter_pix_automatico_due_days', $charge_args['due_days'] );
        } else {
            $order->delete_meta_data( 'inter_pix_automatico_due_days' );
        }

        $order->add_meta_data( 'inter_pix_automatico_contract', $result, true );

        $contract_id = $this->get_contract_identifier( $result );

        if ( $contract_id ) {
            $order->update_meta_data( 'inter_pix_automatico_contract_id', $contract_id );
        }

        $order->set_status( 'on-hold', __( 'Aguardando autorização do Pix Automático.', 'module-inter-bank-for-flexify-checkout' ) );
        $order->save();

        WC()->cart->empty_cart();

        do_action( 'module_inter_bank_pix_automatico_new_contract', $order, $result, $charge_args );

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }


    /**
     * Process automatic renewal payments for subscriptions
     *
     * @since 1.4.0
     * @param float $amount_to_charge | Amount due for the renewal
     * @param \WC_Order $renewal_order | Renewal order instance
     * @return void
     * @throws Exception When the charge cannot be generated
     */
    public function process_scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
        if ( ! $renewal_order instanceof \WC_Order ) {
            $renewal_order = wc_get_order( $renewal_order );
        }

        if ( ! $renewal_order ) {
            return;
        }

        try {
            $contract_reference = $this->get_order_contract_reference( $renewal_order );

            if ( ! $contract_reference ) {
                throw new Exception( __( 'Contrato Pix Automático não encontrado para esta assinatura.', 'module-inter-bank-for-flexify-checkout' ) );
            }

            $contract_id = $this->get_contract_identifier( $contract_reference );

            if ( ! $contract_id ) {
                throw new Exception( __( 'Identificador do contrato Pix Automático inválido.', 'module-inter-bank-for-flexify-checkout' ) );
            }

            $charge_args = $this->prepare_charge_configuration( $renewal_order );
            $charge_args = $this->sanitize_charge_configuration( $charge_args );
            $stored_configuration = $this->get_stored_charge_configuration( $renewal_order );
            $charge_args = wp_parse_args( $charge_args, $stored_configuration );

            if ( $amount_to_charge > 0 ) {
                $charge_args['amount'] = wc_format_decimal( $amount_to_charge, wc_get_price_decimals() );
            }

            $charge_data = [
                'solicitacaoPagador' => sprintf( __( 'Renovação automática do pedido #%s.', 'module-inter-bank-for-flexify-checkout' ), $renewal_order->get_id() ),
            ];

            $renewal_order->add_order_note( __( 'Gerando nova cobrança Pix Automático para a renovação da assinatura.', 'module-inter-bank-for-flexify-checkout' ) );

            $result = $this->api->create_charge( $contract_id, $charge_data, $charge_args );

            $txid = $result->txid ?? $result->id ?? '';

            if ( $txid ) {
                $renewal_order->set_transaction_id( $txid );
                $renewal_order->update_meta_data( 'inter_pix_automatico_txid', $txid );
            }

            $payload = $result->pixCopiaECola ?? $result->brcode ?? '';

            if ( $payload ) {
                $renewal_order->update_meta_data( 'inter_pix_automatico_payload', $payload );

                try {
                    $renewal_order->update_meta_data( 'inter_pix_automatico_qrcode', ( new QRCode() )->render( $payload ) );
                } catch ( \Throwable $th ) {
                    $renewal_order->add_order_note( 'Erro ao gerar QR Code do Pix Automático: ' . $th->getMessage() );
                }
            }

            $this->persist_charge_configuration_meta( $renewal_order, $charge_args, true );

            $renewal_order->update_meta_data( 'inter_pix_automatico_contract', $contract_reference );
            $renewal_order->update_meta_data( 'inter_pix_automatico_contract_id', $contract_id );

            $renewal_order->set_status( 'on-hold', __( 'Cobrança Pix Automático gerada. Aguardando confirmação automática do Banco Inter.', 'module-inter-bank-for-flexify-checkout' ) );
            $renewal_order->save();
        } catch ( Exception $exception ) {
            $renewal_order->add_order_note( sprintf( __( 'Erro ao gerar cobrança Pix Automático para a renovação: %s', 'module-inter-bank-for-flexify-checkout' ), $exception->getMessage() ) );

            if ( is_callable( [ 'WC_Subscriptions_Manager', 'process_subscription_payment_failure_on_order' ] ) ) {
                \WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $renewal_order );
            } else {
                $renewal_order->update_status( 'failed', __( 'Falha ao gerar cobrança Pix Automático da renovação.', 'module-inter-bank-for-flexify-checkout' ) );
            }

            throw $exception;
        }
    }


    /**
     * Build the charge configuration based on order items.
     *
     * @since 1.4.0
     * @param \WC_Order $order | Order instance.
     * @return array
     */
    protected function prepare_charge_configuration( $order ) {
        $config = array(
            'amount' => null,
            'due_days' => null,
            'interval_count' => null,
            'interval_unit' => null,
        );

        $amount_conflict = false;
        $due_days_conflict = false;
        $interval_conflict = false;
        $decimals = wc_get_price_decimals();
        $first_amount = null;
        $total_amount = 0.0;

        foreach ( $order->get_items() as $item ) {
            if ( ! $item instanceof \WC_Order_Item_Product ) {
                continue;
            }

            $product = $item->get_product();

            if ( ! $product ) {
                continue;
            }

            // calculate the unit price ever, based on line total (include promotions and variations)
            $quantity = (float) $item->get_quantity();

            if ( $quantity <= 0 ) {
                continue;
            }

            $line_total = (float) $item->get_total();
            $total_amount += $line_total;
            $formatted_line_total = wc_format_decimal( $line_total, $decimals );

            if ( null === $first_amount ) {
                $first_amount = $formatted_line_total;
            } elseif ( $formatted_line_total !== $first_amount ) {
                $amount_conflict = true;
            }

            // get expiration date
            $product_due = $this->get_product_pix_meta( $product, '_inter_pix_auto_due_days' );

            if ( '' !== $product_due && null !== $product_due ) {
                $due = absint( $product_due );

                if ( null === $config['due_days'] ) {
                    $config['due_days'] = $due;
                } elseif ( $due !== $config['due_days'] ) {
                    $due_days_conflict = true;
                }
            }

            // get value and interval unit from product or variation
            $interval_count = $this->get_product_pix_meta( $product, '_inter_pix_auto_interval_count' );
            $interval_unit = $this->get_product_pix_meta( $product, '_inter_pix_auto_interval_unit' );

            if ( $interval_count ) {
                $int_count = absint( $interval_count );

                if ( null === $config['interval_count'] ) {
                    $config['interval_count'] = $int_count;
                } elseif ( $int_count !== $config['interval_count'] ) {
                    $interval_conflict = true;
                }
            }

            if ( $interval_unit ) {
                $unit = sanitize_text_field( $interval_unit );

                if ( null === $config['interval_unit'] ) {
                    $config['interval_unit'] = $unit;
                } elseif ( $unit !== $config['interval_unit'] ) {
                    $interval_conflict = true;
                }
            }
        }

        // if there was no expiration date on meta, remove it
        if ( null === $config['due_days'] ) {
            unset( $config['due_days'] );
        }

        if ( null !== $first_amount ) {
            $config['amount'] = wc_format_decimal( $total_amount, $decimals );
        }

        if ( $amount_conflict ) {
            $order->add_order_note( __( 'Foram encontrados valores distintos de Pix Automático entre os itens. Foi utilizado o valor total acumulado.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        if ( $due_days_conflict ) {
            $order->add_order_note( __( 'Foram encontrados prazos diferentes de expiração entre os itens. Foi utilizado o primeiro prazo encontrado.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        if ( $interval_conflict ) {
            $order->add_order_note( __( 'Foram encontrados intervalos de cobrança diferentes entre os itens. Foi utilizado o primeiro intervalo encontrado.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        return $config;
    }


    /**
     * Retrieve Pix Automático metadata for a product, including variation fallbacks.
     *
     * @since 1.4.0
     * @param \WC_Product $product | Product or variation.
     * @param string $meta_key | Meta key to retrieve.
     * @return mixed
     */
    protected function get_product_pix_meta( $product, $meta_key ) {
        $value = $product->get_meta( $meta_key, true );

        if ( ( '' === $value || null === $value ) && $product->is_type( 'variation' ) ) {
            $parent_id = $product->get_parent_id();

            if ( $parent_id ) {
                $parent_product = wc_get_product( $parent_id );

                if ( $parent_product ) {
                    $value = $parent_product->get_meta( $meta_key, true );
                }
            }
        }

        return $value;
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

        wc_get_template( 'checkout/automattic-pix-details.php', [
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
     * Store contract information on related subscriptions when a contract is created.
     *
     * @since 1.4.0
     * @param \WC_Order $order       Order associated with the contract.
     * @param mixed     $contract    Contract data returned by the API.
     * @param array     $charge_args Charge configuration used on creation.
     * @return void
     */
    public function store_subscription_contract_meta( $order, $contract, $charge_args ) {
        $order = $this->get_order_from_argument( $order );

        if ( ! $order || ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return;
        }

        $subscriptions = wcs_get_subscriptions_for_order( $order->get_id(), [ 'order_type' => 'parent' ] );

        if ( empty( $subscriptions ) ) {
            return;
        }

        $contract_id = $this->get_contract_identifier( $contract );
        $charge_args = $this->sanitize_charge_configuration( (array) $charge_args );

        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->get_payment_method() && $subscription->get_payment_method() !== $this->id ) {
                continue;
            }

            $subscription->update_meta_data( 'inter_pix_automatico_contract', $contract );

            if ( $contract_id ) {
                $subscription->update_meta_data( 'inter_pix_automatico_contract_id', $contract_id );
            }

            $this->persist_charge_configuration_meta( $subscription, $charge_args, false );
            $subscription->save();
        }
    }


    /**
     * Handle subscription payments confirmed via webhook.
     *
     * @since 1.4.0
     * @param \WC_Order $order        Order that triggered the webhook.
     * @param array     $payload      Webhook payload.
     * @param array     $charge_config Charge configuration used on the charge.
     * @return void
     */
    public function handle_subscription_payment_confirmation( $order, $payload, $charge_config ) {
        $this->process_subscription_webhook_event( $order, $payload, $charge_config, 'confirmed' );
    }


    /**
     * Handle new charges confirmed via webhook for subscriptions.
     *
     * @since 1.4.0
     * @param \WC_Order $order        Order that triggered the webhook.
     * @param array     $payload      Webhook payload.
     * @param array     $charge_config Charge configuration used on the charge.
     * @return void
     */
    public function handle_subscription_new_charge( $order, $payload, $charge_config ) {
        $this->process_subscription_webhook_event( $order, $payload, $charge_config, 'new_charge' );
    }


    /**
     * Filter WooCommerce Subscriptions retry policy according to plugin settings.
     *
     * @since 1.4.0
     * @param bool      $is_enabled Whether retries are enabled.
     * @param \WC_Order $order      Order or subscription instance.
     * @return bool
     */
    public function filter_subscription_retry_support( $is_enabled, $order ) {
        if ( 'yes' === Admin_Options::get_setting( 'enable_inter_retry_billing_policy' ) ) {
            return $is_enabled;
        }

        $order = $this->get_order_from_argument( $order );

        if ( ! $order ) {
            return $is_enabled;
        }

        if ( $order->get_payment_method() !== $this->id ) {
            return $is_enabled;
        }

        return false;
    }


    /**
     * Normalize charge configuration removing null values.
     *
     * @since 1.4.0
     * @param array $charge_args Charge configuration.
     * @return array
     */
    protected function sanitize_charge_configuration( $charge_args ) {
        if ( ! is_array( $charge_args ) ) {
            return [];
        }

        return array_filter(
            $charge_args,
            static function ( $value ) {
                return null !== $value;
            }
        );
    }


    /**
     * Persist charge configuration metadata on orders or subscriptions.
     *
     * @since 1.4.0
     * @param \WC_Data $object       Order or subscription instance.
     * @param array    $charge_args  Charge configuration.
     * @param bool     $allow_remove Whether missing fields should remove metadata.
     * @return void
     */
    protected function persist_charge_configuration_meta( $object, $charge_args, $allow_remove = false ) {
        if ( ! is_object( $object ) || ! method_exists( $object, 'update_meta_data' ) ) {
            return;
        }

        $decimals = wc_get_price_decimals();

        if ( isset( $charge_args['amount'] ) && '' !== $charge_args['amount'] && null !== $charge_args['amount'] ) {
            $object->update_meta_data( 'inter_pix_automatico_amount', wc_format_decimal( $charge_args['amount'], $decimals ) );
        } elseif ( $allow_remove && method_exists( $object, 'delete_meta_data' ) ) {
            $object->delete_meta_data( 'inter_pix_automatico_amount' );
        }

        if ( isset( $charge_args['due_days'] ) && '' !== $charge_args['due_days'] && null !== $charge_args['due_days'] ) {
            $object->update_meta_data( 'inter_pix_automatico_due_days', absint( $charge_args['due_days'] ) );
        } elseif ( $allow_remove && method_exists( $object, 'delete_meta_data' ) ) {
            $object->delete_meta_data( 'inter_pix_automatico_due_days' );
        }
    }


    /**
     * Retrieve charge configuration stored in order, parent or related subscriptions.
     *
     * @since 1.4.0
     * @param \WC_Order $order Order instance.
     * @return array
     */
    protected function get_stored_charge_configuration( $order ) {
        $config = [];
        $order = $this->get_order_from_argument( $order );

        if ( ! $order ) {
            return $config;
        }

        $decimals = wc_get_price_decimals();

        $amount_meta = $order->get_meta( 'inter_pix_automatico_amount' );

        if ( '' !== $amount_meta && null !== $amount_meta ) {
            $config['amount'] = wc_format_decimal( $amount_meta, $decimals );
        }

        $due_days_meta = $order->get_meta( 'inter_pix_automatico_due_days' );

        if ( '' !== $due_days_meta && null !== $due_days_meta ) {
            $config['due_days'] = absint( $due_days_meta );
        }

        if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );

            foreach ( $subscriptions as $subscription ) {
                if ( ! isset( $config['amount'] ) ) {
                    $sub_amount = $subscription->get_meta( 'inter_pix_automatico_amount' );

                    if ( '' !== $sub_amount && null !== $sub_amount ) {
                        $config['amount'] = wc_format_decimal( $sub_amount, $decimals );
                    }
                }

                if ( ! isset( $config['due_days'] ) ) {
                    $sub_due = $subscription->get_meta( 'inter_pix_automatico_due_days' );

                    if ( '' !== $sub_due && null !== $sub_due ) {
                        $config['due_days'] = absint( $sub_due );
                    }
                }
            }
        }

        $parent_id = $order->get_parent_id();

        if ( $parent_id ) {
            $parent_order = wc_get_order( $parent_id );

            if ( $parent_order ) {
                $config = wp_parse_args( $config, $this->get_stored_charge_configuration( $parent_order ) );
            }
        }

        return $config;
    }


    /**
     * Retrieve the contract stored for a given order hierarchy.
     *
     * @since 1.4.0
     * @param \WC_Order $order Order instance.
     * @return mixed|null
     */
    protected function get_order_contract_reference( $order ) {
        $order = $this->get_order_from_argument( $order );

        if ( ! $order ) {
            return null;
        }

        $contract = $order->get_meta( 'inter_pix_automatico_contract', true );

        if ( $contract ) {
            return $contract;
        }

        $contract_id = $order->get_meta( 'inter_pix_automatico_contract_id', true );

        if ( $contract_id ) {
            return $contract_id;
        }

        if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );

            foreach ( $subscriptions as $subscription ) {
                $sub_contract = $subscription->get_meta( 'inter_pix_automatico_contract', true );

                if ( $sub_contract ) {
                    return $sub_contract;
                }

                $sub_contract_id = $subscription->get_meta( 'inter_pix_automatico_contract_id', true );

                if ( $sub_contract_id ) {
                    return $sub_contract_id;
                }
            }
        }

        $parent_id = $order->get_parent_id();

        if ( $parent_id ) {
            $parent_order = wc_get_order( $parent_id );

            if ( $parent_order ) {
                return $this->get_order_contract_reference( $parent_order );
            }
        }

        return null;
    }


    /**
     * Process subscription webhook events, updating related subscriptions.
     *
     * @since 1.4.0
     * @param \WC_Order $order        Order that triggered the webhook.
     * @param mixed     $payload      Webhook payload.
     * @param array     $charge_config Charge configuration.
     * @param string    $context      Webhook context.
     * @return void
     */
    protected function process_subscription_webhook_event( $order, $payload, $charge_config, $context = 'confirmed' ) {
        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return;
        }

        $order = $this->get_order_from_argument( $order );

        if ( ! $order ) {
            return;
        }

        $subscriptions = wcs_get_subscriptions_for_order( $order->get_id(), [ 'order_type' => 'renewal' ] );

        if ( empty( $subscriptions ) ) {
            return;
        }

        $txid = '';

        if ( is_array( $payload ) ) {
            $txid = isset( $payload['txid'] ) ? (string) $payload['txid'] : '';
        } elseif ( is_object( $payload ) ) {
            $txid = isset( $payload->txid ) ? (string) $payload->txid : '';
        }

        $charge_config = $this->sanitize_charge_configuration( (array) $charge_config );

        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->get_payment_method() && $subscription->get_payment_method() !== $this->id ) {
                continue;
            }

            if ( $txid ) {
                $last_txid = $subscription->get_meta( 'inter_pix_automatico_last_txid' );

                if ( $last_txid === $txid ) {
                    continue;
                }

                $subscription->update_meta_data( 'inter_pix_automatico_last_txid', $txid );
            }

            if ( ! empty( $charge_config ) ) {
                $this->persist_charge_configuration_meta( $subscription, $charge_config, false );
            }

            if ( method_exists( $subscription, 'payment_complete' ) ) {
                $subscription->payment_complete();
            } else {
                $subscription->update_status( 'active' );
            }

            $note = __( 'Pagamento da renovação confirmado via Pix Automático.', 'module-inter-bank-for-flexify-checkout' );

            if ( 'new_charge' === $context ) {
                $note = __( 'Cobrança Pix Automático confirmada para a assinatura.', 'module-inter-bank-for-flexify-checkout' );
            }

            $subscription->add_order_note( $note );
            $subscription->save();
        }
    }


    /**
     * Retrieve contract identifier from contract response data.
     *
     * @since 1.4.0
     * @param mixed $contract Contract data.
     * @return string
     */
    protected function get_contract_identifier( $contract ) {
        if ( is_object( $contract ) ) {
            if ( isset( $contract->id ) ) {
                return (string) $contract->id;
            }

            if ( isset( $contract->contractId ) ) {
                return (string) $contract->contractId;
            }

            if ( isset( $contract->numeroContrato ) ) {
                return (string) $contract->numeroContrato;
            }

            if ( isset( $contract->txid ) ) {
                return (string) $contract->txid;
            }

            $contract = (array) $contract;
        }

        if ( is_array( $contract ) ) {
            foreach ( [ 'id', 'contractId', 'numeroContrato', 'txid' ] as $key ) {
                if ( ! empty( $contract[ $key ] ) ) {
                    return (string) $contract[ $key ];
                }
            }
        }

        if ( is_scalar( $contract ) ) {
            return (string) $contract;
        }

        return '';
    }


    /**
     * Resolve an order instance from a mixed argument.
     *
     * @since 1.4.0
     * @param mixed $order | Potential order reference.
     * @return \WC_Order|null
     */
    protected function get_order_from_argument( $order ) {
        if ( $order instanceof \WC_Order ) {
            return $order;
        }

        if ( is_numeric( $order ) ) {
            return wc_get_order( $order );
        }

        return null;
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

        $charge_config = array();
        $decimals = wc_get_price_decimals();
        $amount_meta = $order->get_meta('inter_pix_automatico_amount');

        if ( '' !== $amount_meta && null !== $amount_meta ) {
            $charge_config['amount'] = wc_format_decimal( $amount_meta, $decimals );
        }

        $due_days_meta = $order->get_meta('inter_pix_automatico_due_days');

        if ( '' !== $due_days_meta && null !== $due_days_meta ) {
            $charge_config['due_days'] = absint( $due_days_meta );
        }

        /**
         * Triggered when a Pix Automático authorization/payment is confirmed.
         * Allows other parts of the system to create subsequent charges or update data.
         */
        do_action( 'module_inter_bank_pix_automatico_confirmed', $order, $content, $charge_config );

        /**
         * Hook fired for each charge processed via Pix Automático.
         */
        do_action( 'module_inter_bank_pix_automatico_new_charge', $order, $content, $charge_config );

        wp_send_json_success( [ 'message' => 'Success!' ] );
    }
}