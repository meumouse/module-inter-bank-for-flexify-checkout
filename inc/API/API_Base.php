<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\API;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank\Traits\Helpers;
use MeuMouse\Flexify_Checkout\Inter_Bank\Traits\Logger;
use MeuMouse\Flexify_Checkout\Admin\Admin_Options;

defined('ABSPATH') || exit;

abstract class API_Base {
	use Helpers, Logger;

	/**
	 * API token scopes
	 *
	 * @var string
	 */
	protected $token_scope = 'webhook.write webhook.read cob.write cob.read boleto-cobranca.read boleto-cobranca.write';

	/**
	 * Authentication endpoint
	 *
	 * @var string
	 */
	protected $auth_endpoint = 'oauth/v2/token';

	/**
	 * API URL
	 *
	 * @var string
	 */
	protected $api_url;

	/**
	 * Gateway class.
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Construct function
	 *
	 * @since 1.0.0
	 * @version 1.3.0
	 * @param WC_Payment_Gateway $gateway Gateway instance.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
		$this->api_url = Admin_Options::get_setting('inter_bank_env_mode') === 'production' ? 'https://cdpj.partners.bancointer.com.br/' : 'https://cdpj-sandbox.partners.uatinter.co/';

		if ( $this->gateway ) {
			$critical_only = ! $this->gateway->debug;
			$this->set_logger_source( $this->gateway->id, $critical_only );
		}

		add_action( 'http_api_curl', array( $this, 'inter_http_api_curl' ), 10, 3 );
	}

  
	/**
	 * Get API URL
	 *
     * @since 1.0.0
	 * @return string
	 */
	public function get_api_url() {
		return $this->api_url;
	}


	/**
	 * Get Inter bank token authorization
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @return array
	 */
	protected function get_token() {
		$transient_name = 'module_inter_bank_token_' . $this->gateway->id;
		$token_data = get_transient( $transient_name );

		/**
		 * 1. Token is valid on WordPress
		 * 2. Token is expired but WordPress failed to remove it
		 * 3. Plugin scope is different!
		 */
		if ( false === $token_data || $token_data['expires_at'] < time() || $token_data['scope'] !== $this->token_scope ) {
			$this->log( 'Solicitando novo token... ' . print_r( $token_data, true ) );

			$data = array(
				'client_id' => $this->gateway->client_id,
				'client_secret' => $this->gateway->client_secret,
				'grant_type' => 'client_credentials',
				'scope' => $this->token_scope,
			);

			$headers = array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			);

			$response = $this->do_request( $this->auth_endpoint . '?current_method=' . $this->gateway->id, 'POST', $data, $headers );

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$this->log( 'Erro ao solicitar token ' . print_r( $response, true ) );

				throw new \Exception( __( 'Ocorreu um erro ao entrar em contato com o Banco emissor. Por favor, tente novamente', 'module-inter-bank-for-flexify-checkout' ) );
			}

			$body = json_decode( $response['body'] );

			$token_data = array(
				'token' => $body->access_token,
				'scope' => $body->scope,
				'expires_at' => time() + $body->expires_in,
			);

			set_transient( $transient_name, $token_data, $body->expires_in );
		}

		return $token_data['token'];
	}


	/**
	 * Process API requests
	 *
	 * @param string $endpoint | Partial endpoint for API
	 * @param string $method | Request method
	 * @param array $data | Request data
	 * @param array $headers | Request headers
	 * @return object|Exception Request response
	 */
	protected function do_request( $endpoint, $method = 'POST', $data = [], $headers = [] ) {
		$url = $this->get_api_url() . $endpoint;

		$this->log('URL: ' . $url . ' (' . $method . ')');

		$params = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
			),
		);

		if ( false === strpos( $endpoint, $this->auth_endpoint ) ) {
			$params['headers']['Authorization'] = 'Bearer ' . $this->get_token();
		}

		if ( in_array( $method, ['POST', 'PUT'] ) && ! empty( $data ) ) {
			$params['body'] = wp_json_encode( $data );
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = wp_parse_args( $headers, $params['headers'] );
		}

		if ( $params['headers']['Content-Type'] === 'application/x-www-form-urlencoded' && ! empty( $data ) ) {
			$params['body'] = http_build_query( $data );
		} elseif ( $params['method'] === 'GET' ) {
			$url = add_query_arg( $data, $url );
		}

		$response = wp_safe_remote_post( $url, $params );

		if ( is_wp_error( $response ) ) {
			$this->log('WP Error on enpoint ' . $endpoint . ': ' . print_r($response, true), 'emergency');
			throw new \Exception( $response->get_error_message(), 500 );
		}

		if ( wp_remote_retrieve_response_code( $response ) >= 400 ) {
			$body = json_decode( $response['body'] );

			$message = __('Ocorreu um erro de processamento: ', 'module-inter-bank-for-flexify-checkout');

			if ( ! empty( $body->violacoes ) ) {
				$message .= implode( ';', array_map( function($violation) {
					return $violation->razao;
				}, $body->violacoes) );
			}

			if ( ! empty( $body->error_title ) && current_user_can('manage_woocommerce') ) {
				$message .= $body->error_title;
			}

			if ( ! empty( $body->title ) && current_user_can('manage_woocommerce') ) {
				$message .= $body->title;

				if ( ! empty( $body->detail ) ) {
					$message .= $body->detail;
				}
			}

			$message .= __('Tente novamente.', 'module-inter-bank-for-flexify-checkout');

			$this->log('Invalid response on enpoint ' . $endpoint . ': ' . wp_remote_retrieve_response_code( $response ) . ': ' . print_r( $body, true ), 'emergency');

			throw new \Exception( $message, 500 );
		}

		return $response;
	}


	/**
	 * Send certificates with cURL request
	 * 
	 * @since 1.0.0
	 * @param $handle | The cURL handle returned by curl_init() (passed by reference)
	 * @param $request | The HTTP request arguments
	 * @param $url | The request URL
	 * @return void
	 */
	public function inter_http_api_curl( $handle, $request, $url ) {
		if ( false !== strpos( $url, $this->get_api_url() ) && ( false !== strpos( $url, $this->gateway->endpoint ) || false !== strpos( $url, 'current_method=' . $this->gateway->id ) ) ) {
			curl_setopt( $handle, CURLOPT_SSLCERT, $this->gateway->cert_crt );
			curl_setopt( $handle, CURLOPT_SSLKEY, $this->gateway->cert_key );

			$this->log( 'Certificate .crt: ' . print_r( $this->gateway->cert_crt, true ), 'info' );
			$this->log( 'Certificate .key: ' . print_r( $this->gateway->cert_key, true ), 'info' );
		}
	}
}