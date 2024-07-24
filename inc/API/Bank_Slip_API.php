<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\API;

use Exception;

defined('ABSPATH') || exit;

class Bank_Slip_API extends API_Base {

  /**
   * Create new Bank Slip
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param object|array $order | Order object
   * @return void
   */
  public function create( $order ) {
    try {
        $document = $this->get_order_document( $order );
        $person_type = ( strlen( $document ) > 11 ) ? 'JURIDICA' : 'FISICA';

        if ( ! $document ) {
            throw new \Exception( __('Informe seu documento para prosseguir.', 'module-inter-bank-for-flexify-checkout') );
        }

        $full_name = $order->get_formatted_billing_full_name();
        $name = 'FISICA' === $person_type ? $full_name : $order->get_billing_company();
        $billing_address = $order->get_address('billing');
        $expire_date = $this->get_expire_date( $this->gateway->expires_in );

        $data = array(
            'seuNumero' => $order->get_id(),
            'valorNominal' => $order->get_total(),
            'dataVencimento' => $expire_date,
            'numDiasAgenda' => 0,
            'pagador' => array(
                'email' => $order->get_billing_email(),
                'ddd' => $this->extract_ddd( isset( $billing_address['phone'] ) ? $billing_address['phone'] : '' ),
                'telefone' => $this->extract_phone_number( isset( $billing_address['phone'] ) ? $billing_address['phone'] : '' ),
                'numero' => get_post_meta( $order->get_id(), 'billing_number', true ) !== '' ? substr( get_post_meta( $order->get_id(), 'billing_number', true ), 0, 10 ) : '',
                'complemento' => isset( $billing_address['address_2'] ) ? substr( $billing_address['address_2'], 0, 30 ) : '',
                'cpfCnpj' => $document,
                'tipoPessoa' => $person_type,
                'nome' => $name ? $name : $full_name,
                'endereco' => isset( $billing_address['address_1'] ) ? $billing_address['address_1'] : '',
                'bairro' => get_post_meta( $order->get_id(), 'billing_neighborhood', true ) !== '' ? substr( get_post_meta( $order->get_id(), 'billing_neighborhood', true ), 0, 60 ) : '',
                'cidade' => isset( $billing_address['city'] ) ? $billing_address['city'] : '',
                'uf' => isset( $billing_address['state'] ) ? $billing_address['state'] : '',
                'cep' => isset( $billing_address['postcode'] ) ? $this->only_numbers( $billing_address['postcode'] ) : '',
            ),
            'mensagem' => array(
                'linha1' => substr( str_replace( '{order_id}', $order->get_order_number(), $this->gateway->ticket_messages ), 0, 78 ),
            ),
        );

        $data = apply_filters( $this->gateway->id . '_request_args', $data, $order, $this );

        $this->log( 'Dados do pedido para a API: ' . print_r( $data, true ) );

        $response = $this->do_request_with_retries( 'cobranca/v3/cobrancas', 'POST', $data );

        $this->log( 'Resposta bruta da API: ' . print_r( $response, true ) );

        $result = json_decode( $response['body'] );

        if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
            $this->log( 'Código de resposta HTTP diferente de 200: ' . print_r( $response, true ), 'emergency' );
            throw new \Exception( __('Ocorreu um erro ao gerar o boleto. Tente novamente.', 'module-inter-bank-for-flexify-checkout') );
        }

        if ( empty( $result->codigoSolicitacao ) ) {
            $this->log('Chave "codigoSolicitacao" ausente na resposta da API! ' . print_r( $result, true ), 'emergency');
            throw new \Exception(__('Ocorreu um erro ao processar os dados do boleto. Tente novamente', 'module-inter-bank-for-flexify-checkout'));
        }

        $this->log( 'Código da solicitação do boleto: ' . $result->codigoSolicitacao );

        // Obter os detalhes da cobrança
        $cobranca_details = $this->get_collection_details( $result->codigoSolicitacao );
        $this->log( 'Detalhes da cobrança: ' . print_r( $cobranca_details, true ) );

        if ( empty( $cobranca_details->boleto ) || empty( $cobranca_details->pix ) ) {
            $this->log('Detalhes do boleto ou pix ausentes na resposta da API: ' . print_r( $cobranca_details, true ), 'emergency');
            throw new \Exception( __('Ocorreu um erro ao processar os dados do boleto. Tente novamente', 'module-inter-bank-for-flexify-checkout') );
        }

        return $result;
    } catch ( \Exception $e ) {
        wc_add_notice( __('O serviço de pagamento está temporariamente indisponível. Por favor, tente novamente mais tarde.', 'module-inter-bank-for-flexify-checkout'), 'error' );
        $this->log('Erro ao criar o boleto: ' . $e->getMessage(), 'emergency');
        throw $e;
    }
  }


  /**
   * Makes the request with retries in case of failure
   *
   * @since 1.1.0
   * @param string $endpoint | Partial endpoint
   * @param string $method | Request method
   * @param array $data | Request data
   * @param int $retries | Retries number
   * @return array
   */
  public function do_request_with_retries( $endpoint, $method, $data, $retries = 3 ) {
    $response = null;
    $attempts = 0;

    while ( $attempts < $retries ) {
        $response = $this->do_request( $endpoint, $method, $data );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code === 200 ) {
            return $response;
        }

        if ( $status_code === 503 ) {
            $this->log("Serviço indisponível, tentativa $attempts de $retries", 'warning');
            sleep(5); // Wait 5 seconds before trying again
        } else {
            break;
        }

        $attempts++;
    }

    throw new \Exception("Erro ao realizar a requisição: " . print_r($response, true));
  }


  /**
   * Get boleto PDF by codigoSolicitacao
   *
   * @since 1.1.0
   * @param string $response_code | Ticket request code
   * @return string | PDF base64 encoded
   */
  public function get_bank_slip_pdf( $response_code ) {
    $this->log( 'Consultando boleto PDF para a solicitação: ' . $response_code );

    $response = $this->do_request( 'cobranca/v3/cobrancas/' . $response_code . '/pdf', 'GET' );

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
        throw new \Exception( __('Ocorreu um erro ao consultar o boleto PDF', 'module-inter-bank-for-flexify-checkout') );
    }

    $result = json_decode( $response['body'] );

    if ( empty( $result->pdf ) ) {
        throw new \Exception( __('PDF do boleto não encontrado na resposta da API', 'module-inter-bank-for-flexify-checkout') );
    }

    return base64_decode( $result->pdf );
  }


  /**
   * Get collection details by codigoSolicitacao
   *
   * @since 1.1.0
   * @param string $response_code | Solicitation code
   * @return object | collection details
   */
  public function get_collection_details( $response_code ) {
    $this->log('Consultando dados da cobrança para a solicitação: ' . $response_code);
    $response = $this->do_request( 'cobranca/v3/cobrancas/' . $response_code, 'GET' );

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
      throw new \Exception( __('Ocorreu um erro ao consultar os dados da cobrança', 'module-inter-bank-for-flexify-checkout') );
    }

    $result = json_decode( $response['body'] );

    if ( empty( $result->cobranca ) ) {
      $this->log( 'Dados da cobrança não encontrados na resposta da API: ' . $result );
      throw new \Exception( __('Dados da cobrança não encontrados na resposta da API', 'module-inter-bank-for-flexify-checkout') );
    }

    return $result;
  }


  /**
   * Get a bank slip
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param int $id | Our number
   * @return bool
   */
  public function get( $id, $type = 'pdf' ) {
    $this->log( 'Consultando boleto ' . $id );

    $response = $this->do_request( 'cobranca/v3/cobrancas/' . $id . '/' . $type, 'GET' );

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
      throw new \Exception( __( 'Ocorreu um erro ao consultar o boleto', 'module-inter-bank-for-flexify-checkout' ) );
    }

    return json_decode( $response['body'] );
  }

  /**
   * Get expire date
   *
   * @since 1.0.0
   * @version 1.1.0
   * @param int $additional_days | Additional days to expire date
   * @return string
   */
  public function get_expire_date( $additional_days ) {
    return date( 'Y-m-d', ( strtotime ( $additional_days . ' weekdays' ) ) );
  }

  /**
   * Get all bank slips
   *
   * @since 1.0.0
   * @version 1.1.0
   * @return array
   */
  public function get_all() {
    $response = $this->do_request( 'cobranca/v3/cobrancas?dataInicial=2023-01-01&dataFinal=' . date( 'Y-m-d' ) . '&itensPorPagina=1000&filtrarDataPor=EMISSAO&ordenarPor=SEUNUMERO&tipoOrdenacao=DESC&situacao=PAGO', 'GET' );

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
      throw new \Exception( __( 'Ocorreu um erro ao consultar boletos: ', 'module-inter-bank-for-flexify-checkout' ) . print_r( $response, true ) );
    }

    return json_decode( $response['body'] );
  }
}