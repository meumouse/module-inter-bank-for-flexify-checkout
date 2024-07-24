<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\API;

use Exception;

defined('ABSPATH') || exit;

/**
 * 
 */
class Pix_API extends API_Base {

  /**
   * Create new Pix collection
   *
   * @since 1.0.0
   * @return void
   */
  public function create( $order ) {
    $document = $this->get_order_document( $order );
    $document_type = ( strlen( $document ) > 11 ) ? 'cnpj' : 'cpf';

    if ( ! $document ) {
      throw new \Exception( __( 'Informe seu documento para prosseguir.', 'module-inter-bank-for-flexify-checkout' ) );
    }

    $data = [
      'calendario' => [
        'expiracao' => $this->gateway->expires_in * 60,
      ],
      'devedor' => [
        'nome' => $order->get_formatted_billing_full_name(),
        $document_type => $document,
      ],
      'valor' => [
        'original' => $order->get_total(),
      ],
      'chave' => $this->gateway->pix_key,
      'solicitacaoPagador' => sprintf( __( 'Pagamento do pedido #%s.', 'module-inter-bank-for-flexify-checkout' ), $order->get_id() ),
      'infoAdicionais' => [
        [
          'nome' => 'Pedido',
          'valor' => $order->get_id(),
        ],
        [
          'nome' => 'Site',
          'valor' => str_replace( [ 'https://', 'http://' ], '', home_url() ),
        ],
      ],
    ];

    $this->log( 'Gerando pix do pedido ' . $order->get_id() . ': ' . print_r( $data, true ), 'emergency' );

    $data = apply_filters( $this->gateway->id . '_request_args', $data, $order, $this );

    $response = $this->do_request( 'pix/v2/cob', 'POST', $data );
    $result   = json_decode( $response['body'] );

    if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ] ) ) {
      $this->log( 'A resposta da API do Inter não foi 201: ' . print_r( $response, true ), 'emergency' );

      throw new \Exception( __( 'Ocorreu um erro ao gerar o código Pix. Tente novamente.', 'module-inter-bank-for-flexify-checkout' ) );
    }

    if ( empty( $result->pixCopiaECola ) || empty( $result->txid ) ) {
      $this->log( 'A resposta da API do Inter está inválida! ' . print_r( $response, true ), 'emergency' );

      throw new \Exception( __( 'Ocorreu um erro ao processar os dados do Pix. Tente novamente', 'module-inter-bank-for-flexify-checkout' ) );
    }

    $this->log( 'Pix gerado no pedido ' . $order->get_id() . ': ' . print_r( $result, true ), 'emergency' );

    return $result;
  }


  /**
   * Query payment pix
   *
   * @since 1.0.0
   * @param int $id | txid - transaction identifier
   * @return bool
   */
  public function get( $id ) {
    $this->log( 'Consultando Pix ' . $id );

    $response = $this->do_request( 'pix/v2/cob/' . $id, 'GET' );

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
      throw new \Exception( __( 'Ocorreu um erro ao consultar o pix', 'module-inter-bank-for-flexify-checkout' ) );
    }

    $body = json_decode( $response['body'] );
    $this->log( 'Resposta Pix ' . print_r( $body, true ) );

    return $body;
  }
}
