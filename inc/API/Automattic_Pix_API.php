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
     * @param \WC_Order $order Order instance
     * @return object
     * @throws Exception
     */
    public function create_contract( $order ) {
        $document = $this->get_order_document( $order );
        $document_type = ( strlen( $document ) > 11 ) ? 'cnpj' : 'cpf';

        if ( ! $document ) {
            throw new Exception( __( 'Informe seu documento para prosseguir.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        $data = [
            'pagador' => [
                'nome' => $order->get_formatted_billing_full_name(),
                $document_type => $document,
            ],
            'valor'   => [
                'original' => $order->get_total(),
            ],
            'solicitacaoPagador' => sprintf( __( 'Autorização do pedido #%s.', 'module-inter-bank-for-flexify-checkout' ), $order->get_id() ),
        ];

        $this->log( 'Criando contrato Pix Automático do pedido ' . $order->get_id() . ': ' . print_r( $data, true ) );

        $response = $this->do_request( 'pix-automatico/v1/contratos', 'POST', $data );
        $body = json_decode( $response['body'] );

        if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ] ) ) {
            $this->log( 'Erro ao criar contrato Pix Automático: ' . print_r( $response, true ), 'emergency' );
            throw new Exception( __( 'Ocorreu um erro ao gerar a autorização do Pix. Tente novamente.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        return $body;
    }


    /**
     * Create a charge for a given contract
     *
     * @param string $contract_id Contract identifier returned on creation
     * @param array  $data Charge data
     * @return object
     * @throws Exception
     */
    public function create_charge( $contract_id, $data ) {
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
     * @param string $contract_id Contract identifier
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