<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Bank_Slip;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank;

defined('ABSPATH') || exit;

/**
 * 
 */
class Print_Bank_Slip {

  /**
   * $page_slug
   *
   * @var string
   */
  public static $page_slug = 'boleto';

  /**
   * Construct function
   * 
   * @since 1.0.0
   * @return void
   */
  public function __construct() {
    add_action( 'init', array( $this, 'add_endpoint' ), 0 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
    add_action( 'parse_request', array( $this, 'handle_api_requests' ), 0 );
    add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ) );
  }


  /**
	 * WC API for payment gateway IPNs, etc.
	 *
	 * @since 1.0.0
   * @return void
	 */
	public function add_endpoint() {
	  add_rewrite_endpoint( self::$page_slug, EP_ALL );
	}


	/**
	 * Add new query vars
	 *
	 * @since 1.0.0
	 * @param array $vars | Query vars
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::$page_slug;

		return $vars;
	}


  /**
   * Handle request
   *
   * @since 1.0.0
   * @version 1.1.0
   * @return void
   */
  public function handle_api_requests() {
    global $wp;

    // track endpoint requests.
    if ( array_key_exists( self::$page_slug, $wp->query_vars ) ) {

      // Buffer, we won't want any output here.
      ob_start();

      // No cache headers.
      wc_nocache_headers();

      // Clean the API request.
      $order_key = wc_clean( $wp->query_vars[ self::$page_slug ] );
      $order_key = str_replace( '.pdf', '', $order_key );

      try {
        $order_id = wc_get_order_id_by_order_key( $order_key );

        if ( ! $order_id ) {
          throw new \Exception( __( 'Pedido não encontrado.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
          throw new \Exception( __( 'Pedido inválido.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        if ( 'interboleto' !== $order->get_payment_method() ) {
          throw new \Exception( __( 'Pedido incompatível com boletos.', 'module-inter-bank-for-flexify-checkout' ) );
        }

        $bank_slip_folder = ABSPATH . 'boletos-pdf/';
        $bank_slip_path = $bank_slip_folder . $order_key . '.pdf';

        // making sure the folder already exists
        wp_mkdir_p( $bank_slip_folder );

        if ( file_exists( $bank_slip_path ) ) {
          $pdf = file_get_contents( $bank_slip_path );
        } else {
          // se não conseguir pegar o PDF, exibe barcode e linha digitável
          $fallback_notice = sprintf( __( 'Não foi possível buscar o PDF do boleto. Por favor, tente mais tarde ou realize o pagamento com a linha digitável: <br /><br /> <code>%s</code>', 'module-inter-bank-for-flexify-checkout' ), $order->get_meta( 'inter_payment_line' ) );

          $gateway = wc_get_payment_gateway_by_order( $order );

          if ( ! $gateway ) {
            throw new \Exception( $fallback_notice );
          }

          try {
            $pdf = $gateway->api->get_bank_slip_pdf( $order->get_meta('inter_codigo_solicitacao') );

            file_put_contents( $bank_slip_path, $pdf );

          } catch ( \Exception $e ) {
            throw new \Exception( $fallback_notice );
          }
        }

        header( 'Content-type: application/pdf' );
        header( 'Content-disposition: inline;filename=boleto.pdf' );

        echo $pdf;

      } catch ( \Exception $e ) {
        wp_die( __( '<h1>Ocorreu um erro!</h1>', 'module-inter-bank-for-flexify-checkout' ) . '<p>' . $e->getMessage() . '</p>', __( 'Erro no boleto', 'module-inter-bank-for-flexify-checkout' ) );
      }

      echo ob_get_clean();
      exit;
    }
  }


  /**
   * Get bank slip file URL
   * 
   * @since 1.0.0
   * @param $key | Meta dada - get_order_key
   * @return string
   */
  public static function get_bank_slip_url( $key ) {
    $suffix = apply_filters( 'module_inter_bank_bank_slip_preview_suffix', '.pdf' );

    return home_url( self::$page_slug . '/' . $key . $suffix );
  }


  /**
   * Maybe flush rewrite rules
   * 
   * @since 1.0.0
   * @return void
   */
  public function maybe_flush_rewrite_rules() {
    if ( FCW_MODULE_INTER_VERSION !== get_option( 'module_inter_bank_flush_version' ) ) {
      flush_rewrite_rules();

      update_option( 'module_inter_bank_flush_version', FCW_MODULE_INTER_VERSION );
    }
  }
}
