<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\API;

use Exception;

defined('ABSPATH') || exit;

/**
 * Extends API_Base for add Webhook object
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Webhooks_API extends API_Base {

	/**
	 * Parts for fill API endpoints
	 * 
	 * @since 1.0.0
	 * @version 1.2.0
	 */
	public $endpoints = array(
		'interpix' => 'pix/v2/webhook/',
		'interboleto' => 'cobranca/v3/cobrancas/webhook',
	);


	/**
	 * Get current webhook
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get() {
		$endpoints = $this->endpoints;

		if ( ! isset( $endpoints[$this->gateway->id] ) ) {
			throw new \Exception( __( 'Método de pagamento inválido', 'module-inter-bank-for-flexify-checkout' ) );
		}

		// throw earlier if there is token issue
		$this->get_token();

		if ( 'interpix' === $this->gateway->id ) {
			if ( empty( $this->gateway->pix_key ) ) {
				throw new \Exception( __( 'Informe a chave Pix antes de consultar o webhook', 'module-inter-bank-for-flexify-checkout' ) );
			}

			$endpoints['interpix'] .= $this->gateway->pix_key;
		}

		try {
			$response = $this->do_request( $endpoints[$this->gateway->id], 'GET' );
			$result = json_decode( $response['body'], true );
		} catch ( \Exception $e ) {
			return false;
		}

		return $result;
	}


	/**
	 * Create a webhook
	 *
	 * @since 1.0.0
	 * @version 1.1.0
	 * @param string $url | Webhook URL
	 * @return bool
	 */
	public function create( $url ) {
		$endpoints = $this->endpoints;

		if ( ! isset( $endpoints[$this->gateway->id] ) ) {
			throw new \Exception( __( 'Método de pagamento inválido para criar endpoint', 'module-inter-bank-for-flexify-checkout' ) );
		}

		if ( 'interpix' === $this->gateway->id ) {
			if ( empty( $this->gateway->pix_key ) ) {
				throw new \Exception( __( 'Informe a chave Pix antes de consultar o webhook', 'module-inter-bank-for-flexify-checkout' ) );
			}

			$endpoints['interpix'] .= $this->gateway->pix_key;
		}

		$data = array(
			'webhookUrl' => $url,
		);

		$response = $this->do_request( $endpoints[$this->gateway->id], 'PUT', $data );

		if ( 204 !== wp_remote_retrieve_response_code( $response ) ) {
			throw new \Exception( __( 'Ocorreu um erro ao gerar o webhook.', 'module-inter-bank-for-flexify-checkout' ) );
		}

		return true;
	}
}