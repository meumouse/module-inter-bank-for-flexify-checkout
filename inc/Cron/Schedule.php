<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Cron;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank\Traits\Logger;

defined('ABSPATH') || exit;

/**
 * Schedule querys for bank Inter module
 * 
 * @since 1.0.0
 * @version 1.1.0
 * @package MeuMouse.com
 */
class Schedule {
  use Logger;

  /**
   * Construct function
   * 
   * @since 1.0.0
   * @return void
   */
  public function __construct() {
    add_action( 'admin_init', array( $this, 'maybe_create_crons' ) );
    add_action( 'module_inter_bank_check_interpix', array( $this, 'check_interpix' ) );
    add_action( 'module_inter_bank_check_interpix_order', array( $this, 'check_interpix_order' ) );
    add_action( 'module_inter_bank_check_interboletov2', array( $this, 'check_interboleto' ) );
    add_action( 'module_inter_bank_check_interboletov2_order', array( $this, 'check_interboleto_order' ) );
  }


  /**
   * 
   */
  public function maybe_create_crons() {
    if ( ! wp_next_scheduled( 'module_inter_bank_check_interboletov2' ) ) {
      wp_schedule_event( strtotime( 'Tomorrow 6am' ), 'hourly', 'module_inter_bank_check_interboletov2' );

      do_action('module_inter_bank_check_interboletov2');

      // remove v1
      wp_clear_scheduled_hook( 'module_inter_bank_check_interboleto' );
    }

    if ( ! wp_next_scheduled( 'module_inter_bank_check_interpix' ) ) {
      wp_schedule_event( time(), 'hourly', 'module_inter_bank_check_interpix' );

      do_action('module_inter_bank_check_interpix');
    }
  }


  /**
   * 
   */
  public function check_interboleto() {
    $order_ids = $this->get_pending_orders('interboleto');
    $this->set_logger_source( 'interboleto', false );
    $this->log( 'Consultando pedidos via Cron: ' . print_r( $order_ids, true ) );

    foreach ( $order_ids as $order_id ) {
			WC()->queue()->add( 'module_inter_bank_check_interboletov2_order',
				array(
					'order_id' => $order_id,
				),
				'module-inter-bank-for-flexify-checkout'
			);
    }
  }


  /**
   * 
   */
  public function check_interboleto_order( $order_id ) {
    try {
      $this->set_logger_source( 'interboleto', false );
      $this->log( 'Consultando pedido #' . $order_id );
      $order = wc_get_order( $order_id );

      if ( ! $order )  {
        throw new \Exception( 'Pedido #' . $order_id . ' não encontrado na consulta manual.' );
      }

      $gateway = wc_get_payment_gateway_by_order( $order );
      $transaction = $gateway->api->get( $order->get_transaction_id(), '' );
      $this->log( print_r( $transaction, true ) );

      if ( 'PAGO' === $transaction->situacao ) {
        $order->payment_complete();
        $order->add_order_note( __( 'Pagamento confirmado (com atraso) pelo Banco Inter.', 'module-inter-bank-for-flexify-checkout' ) );
      } else if ( in_array( $transaction->situacao, [ 'EXPIRADO', 'VENCIDO', 'CANCELADO', ] ) ) {
        $order->update_status( 'cancelled', __( '[EXPERIMENTAL] Prazo para pagamento expirou.', 'module-inter-bank-for-flexify-checkout' ) );
      }

    } catch ( \Exception $e ) {
      $this->log( 'Erro ao consultar pedido #' . $order_id . '. ' . $e->getMessage() );
    }
  }


  /**
   * 
   */
  public function check_interpix() {
    $order_ids = $this->get_pending_orders('interpix');
    $this->set_logger_source( 'interpix', false );
    $this->log( 'Consultando pedidos via Cron: ' . print_r( $order_ids, true ) );

    foreach ( $order_ids as $order_id ) {
			WC()->queue()->add(
				'module_inter_bank_check_interpix_order',
				array(
					'order_id' => $order_id,
				),
				'module-inter-bank-for-flexify-checkout'
			);
    }
  }


  /**
   * 
   */
  public function check_interpix_order( $order_id ) {
    try {
      $this->set_logger_source( 'interpix', false );
      $this->log( 'Consultando pedido #' . $order_id );
      $order = wc_get_order( $order_id );

      if ( ! $order )  {
        throw new \Exception( 'Pedido #' . $order_id . ' não encontrado na consulta manual.' );
      }

      $gateway = wc_get_payment_gateway_by_order( $order );
      $transaction = $gateway->api->get( $order->get_transaction_id() );

      if ( 'CONCLUIDA' === $transaction->status ) {
        $order->payment_complete();
        $order->add_order_note( __( 'Pagamento confirmado (com atraso) pelo Banco Inter.', 'module-inter-bank-for-flexify-checkout' ) );
      } else if ( ( strtotime( $transaction->calendario->criacao ) + $transaction->calendario->expiracao ) <= time() ) {
        $order->update_status( 'cancelled', __( '[EXPERIMENTAL] Prazo para pagamento expirou.', 'module-inter-bank-for-flexify-checkout' ) );
      }

    } catch ( \Exception $e ) {
      $this->log( 'Erro ao consultar pedido #' . $order_id . '. ' . $e->getMessage() );
    }
  }


  /**
   * 
   */
  public function get_pending_orders( $gateway ) {
    $orders = wc_get_orders([
      'payment_method' => $gateway,
      'status' => [ 'wc-on-hold' ],
      'return' => 'ids',
    ]);

    return $orders;
  }
}