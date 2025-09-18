<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\API;

use Exception;

defined('ABSPATH') || exit;

/**
 * Pix Automático API integration
 *
 * Implements basic endpoints used to create and manage automatic Pix
 * contracts and charges.
 *
 * @since 1.4.0
 * @see https://developers.inter.co/references/pix-automatico
 * @package MeuMouse.com
 */
class Automattic_Pix_API extends API_Base {

    /**
     * Create a new Pix Automático contract
     *
     * @since 1.4.0
     * @param \WC_Order $order | Order instance
     * @param array $charge_args | Optional charge configuration
     * @return object
     * @throws Exception
     */
    public function create_contract( $order, $charge_args = array() ) {
        $document = $this->get_order_document( $order );
        $document_type = ( strlen( $document ) > 11 ) ? 'cnpj' : 'cpf';

        if ( ! $document ) {
            throw new Exception( __( 'Informe seu documento para prosseguir.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        $charge_args = is_array( $charge_args ) ? $charge_args : array();
        $amount = isset( $charge_args['amount'] ) ? wc_format_decimal( $charge_args['amount'] ) : wc_format_decimal( $order->get_total() );

        $data = array(
            'pagador' => array(
                'nome' => $order->get_formatted_billing_full_name(),
                $document_type => $document,
            ),
            'valor' => array(
                'original' => $amount,
            ),
            'solicitacaoPagador' => sprintf( __( 'Autorização do pedido #%s.', 'module-inter-bank-for-flexify-checkout' ), $order->get_id() ),
        );

        if ( isset( $charge_args['due_days'] ) && is_numeric( $charge_args['due_days'] ) ) {
            $due_days = absint( $charge_args['due_days'] );

            if ( $due_days > 0 ) {
                $data['calendario'] = array(
                    'expiracao' => $due_days * DAY_IN_SECONDS,
                );
            }
        }

        $this->log( 'Criando contrato Pix Automático do pedido ' . $order->get_id() . ': ' . print_r( $data, true ) );

        $response = $this->do_request( 'pix-automatico/v1/contratos', 'POST', $data );
        $body = json_decode( $response['body'] );

        if ( ! in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201 ) ) ) {
            $this->log( 'Erro ao criar contrato Pix Automático: ' . print_r( $response, true ), 'emergency' );
            throw new Exception( __( 'Ocorreu um erro ao gerar a autorização do Pix. Tente novamente.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        return $body;
    }


    /**
     * Create a charge for a given contract
     *
     * @since 1.4.0
     * @param string $contract_id | Contract identifier returned on creation
     * @param array $data | Charge data
     * @param array $charge_args | Optional charge configuration
     * @return object
     * @throws Exception
     */
    public function create_charge( $contract_id, $data, $charge_args = array() ) {
        $charge_args = is_array( $charge_args ) ? $charge_args : [];

        if ( ! isset( $data['valor']['original'] ) && isset( $charge_args['amount'] ) ) {
            $data['valor']['original'] = wc_format_decimal( $charge_args['amount'] );
        }

        if ( ! isset( $data['calendario'] ) && isset( $charge_args['due_days'] ) && is_numeric( $charge_args['due_days'] ) ) {
            $due_days = absint( $charge_args['due_days'] );

            if ( $due_days > 0 ) {
                $data['calendario'] = [
                    'expiracao' => $due_days * DAY_IN_SECONDS,
                ];
            }
        }

        $endpoint = sprintf( 'pix-automatico/v1/contratos/%s/cobrancas', $contract_id );
        $response = $this->do_request( $endpoint, 'POST', $data );
        $body = json_decode( $response['body'] );

        if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ] ) ) {
            $this->log( 'Erro ao criar cobrança Pix Automático: ' . print_r( $response, true ), 'emergency' );
            throw new Exception( __( 'Ocorreu um erro ao gerar a cobrança. Tente novamente.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        return $body;
    }
    

    /**
     * Get contract or charge information
     *
     * @since 1.4.0
     * @param string $contract_id | Contract identifier
     * @param string|null $txid | Charge identifier (optional)
     * @return object
     * @throws Exception
     */
    public function get( $contract_id, $txid = null ) {
        $endpoint = sprintf( 'pix-automatico/v1/contratos/%s', $contract_id );

        if ( $txid ) {
            $endpoint .= sprintf( '/cobrancas/%s', $txid );
        }

        $response = $this->do_request( $endpoint, 'GET' );

        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
            throw new Exception( __( 'Ocorreu um erro ao consultar o Pix Automático', 'module-inter-bank-for-flexify-checkout' ) );
        }

        return json_decode( $response['body'] );
    }


    /**
     * Cancel an existing contract
     *
     * @since 1.4.0
     * @param string $contract_id | Contract identifier
     * @return bool
     * @throws Exception
     */
    public function cancel_contract( $contract_id ) {
        $endpoint = sprintf( 'pix-automatico/v1/contratos/%s', $contract_id );
        $response = $this->do_request( $endpoint, 'DELETE' );

        if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
            throw new Exception( __( 'Ocorreu um erro ao cancelar o contrato Pix Automático', 'module-inter-bank-for-flexify-checkout' ) );
        }

        return true;
    }
}