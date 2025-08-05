<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Cron;

use Exception;
use MeuMouse\Flexify_Checkout\Inter_Bank\Traits\Logger;
use MeuMouse\Flexify_Checkout\Admin\Admin_Options;

defined('ABSPATH') || exit;

/**
 * Schedule queries for the Inter bank module.
 * 
 * This class manages scheduled tasks (crons) for the Inter bank module, including checking
 * payments and sending reminders for expiring credentials.
 * 
 * @since 1.0.0
 * @version 1.2.2
 * @package MeuMouse.com
 */
class Schedule {
  use Logger;

  /**
   * Construct function.
   * 
   * Initializes the class by setting up hooks for scheduled tasks and checking for necessary
   * conditions to run certain tasks, such as the presence of an expiration date for credentials.
   * 
   * @since 1.0.0
   * @version 1.2.0
   * @return void
   */
  public function __construct() {
    add_action( 'admin_init', array( $this, 'maybe_create_crons' ) );
    add_action( 'module_inter_bank_check_interpix', array( $this, 'check_interpix' ) );
    add_action( 'module_inter_bank_check_interpix_order', array( $this, 'check_interpix_order' ) );
    add_action( 'module_inter_bank_check_interboletov2', array( $this, 'check_interboleto' ) );
    add_action( 'module_inter_bank_check_interboletov2_order', array( $this, 'check_interboleto_order' ) );
    add_action( 'module_inter_bank_retry', array( $this, 'retry_webhook_query' ), 100, 3);

    // Check if Inter bank module is active and if expire date exists
    if ( ! empty( Admin_Options::get_setting('inter_bank_expire_date') ) ) {
      // Hook for scheduling reminders for Inter bank credentials expiration
      add_action( 'wp_loaded', array( $this, 'schedule_remind_inter_bank_credentials' ) );

      // Hook for sending email reminders
      add_action( 'remind_expire_inter_bank_credentials_event', array( $this, 'remind_expire_inter_bank_credentials' ) );
    }
  }

  
  /**
   * Maybe create cron jobs.
   * 
   * Creates scheduled tasks if they are not already scheduled, including checking for Inter boleto
   * and Inter pix payments. Also clears any outdated scheduled tasks.
   * 
   * @since 1.0.0
   * @return void
   */
  public function maybe_create_crons() {
    if ( ! wp_next_scheduled( 'module_inter_bank_check_interboletov2' ) ) {
      wp_schedule_event( strtotime( 'Tomorrow 6am' ), 'hourly', 'module_inter_bank_check_interboletov2' );

      do_action('module_inter_bank_check_interboletov2');

      // Remove old v1 schedule
      wp_clear_scheduled_hook( 'module_inter_bank_check_interboleto' );
    }

    if ( ! wp_next_scheduled( 'module_inter_bank_check_interpix' ) ) {
      wp_schedule_event( time(), 'hourly', 'module_inter_bank_check_interpix' );

      do_action('module_inter_bank_check_interpix');
    }
  }


  /**
   * Check Inter boleto payments.
   * 
   * Queries for pending orders using the Inter boleto payment method and queues each order for
   * individual checking.
   * 
   * @since 1.0.0
   * @return void
   */
  public function check_interboleto() {
    $order_ids = $this->get_pending_orders('interboleto');
    $this->set_logger_source( 'interboleto', false );
    $this->log( 'Consulting orders via Cron: ' . print_r( $order_ids, true ) );

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
   * Check individual Inter boleto order.
   * 
   * Processes an individual order, checks its payment status with the Inter bank, and updates
   * the order status accordingly.
   * 
   * @since 1.0.0
   * @param int $order_id | Order ID
   * @return void
   */
  public function check_interboleto_order( $order_id ) {
    try {
      $this->set_logger_source( 'interboleto', false );
      $this->log( 'Consulting order #' . $order_id );
      $order = wc_get_order( $order_id );

      if ( ! $order )  {
        throw new \Exception( 'Order #' . $order_id . ' not found in manual query.' );
      }

      $gateway = wc_get_payment_gateway_by_order( $order );
      $transaction = $gateway->api->get( $order->get_transaction_id(), '' );
      $this->log( print_r( $transaction, true ) );

      if ( 'PAGO' === $transaction->situacao ) {
        $order->payment_complete();
        $order->add_order_note( __( 'Payment confirmed (delayed) by Inter bank.', 'module-inter-bank-for-flexify-checkout' ) );
      } else if ( in_array( $transaction->situacao, [ 'EXPIRADO', 'VENCIDO', 'CANCELADO' ] ) ) {
        $order->update_status( 'cancelled', __( '[EXPERIMENTAL] Payment deadline expired.', 'module-inter-bank-for-flexify-checkout' ) );
      }

    } catch ( \Exception $e ) {
      $this->log( 'Error consulting order #' . $order_id . '. ' . $e->getMessage() );
    }
  }


  /**
   * Check Inter pix payments.
   * 
   * Queries for pending orders using the Inter pix payment method and queues each order for
   * individual checking.
   * 
   * @since 1.0.0
   * @return void
   */
  public function check_interpix() {
    $order_ids = $this->get_pending_orders('interpix');
    $this->set_logger_source( 'interpix', false );
    $this->log( 'Consulting orders via Cron: ' . print_r( $order_ids, true ) );

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
   * Check if a Pix payment order has been completed.
   * 
   * Processes an individual order, checks its payment status with the Inter bank, and updates
   * the order status accordingly.
   * 
   * @since 1.0.0
   * @param int $order_id | Order ID
   * @return void
   */
  public function check_interpix_order( $order_id ) {
    try {
      $this->set_logger_source( 'interpix', false );
      $this->log( 'Consulting order #' . $order_id );
      $order = wc_get_order( $order_id );

      if ( ! $order )  {
        throw new \Exception( 'Order #' . $order_id . ' not found in manual query.' );
      }

      $gateway = wc_get_payment_gateway_by_order( $order );
      $transaction = $gateway->api->get( $order->get_transaction_id() );

      if ( 'CONCLUIDA' === $transaction->status ) {
        $order->payment_complete();
        $order->add_order_note( __( 'Payment confirmed (delayed) by Inter bank.', 'module-inter-bank-for-flexify-checkout' ) );
      } else if ( ( strtotime( $transaction->calendario->criacao ) + $transaction->calendario->expiracao ) <= time() ) {
        $order->update_status( 'cancelled', __( '[EXPERIMENTAL] Payment deadline expired.', 'module-inter-bank-for-flexify-checkout' ) );
      }

    } catch ( \Exception $e ) {
      $this->log( 'Error consulting order #' . $order_id . '. ' . $e->getMessage() );
    }
  }


  /**
   * Get pending orders.
   * 
   * Retrieves a list of order IDs that are on hold and using a specified payment gateway.
   * 
   * @since 1.0.0
   * @param string $gateway | Gateway ID
   * @return array
   */
  public function get_pending_orders( $gateway ) {
    $orders = wc_get_orders( array(
      'payment_method' => $gateway,
      'status' => [ 'wc-on-hold' ],
      'return' => 'ids',
    ));

    return $orders;
  }


  /**
   * Retry a failed webhook query.
   * 
   * Re-sends a webhook request to the Inter bank API in case the initial request failed.
   * Logs the result of the retry attempt.
   * 
   * @since 1.0.0
   * @version 1.2.0
   * @param string $method | Pix or bank slip ID
   * @param array $payload | Request payload
   * @param int $order_id | Order ID
   * @return void
   */
  public function retry_webhook_query( $method, $payload, $order_id ) {
    $q = new \WC_Logger();
    $q->add( $method, 'Webhook try again for ' . $order_id );

    $raw_response = wp_remote_post( WC()->api_request_url( $method ), array(
      'body' => $payload,
      'headers' => array(
        'Content-Type: application/json',
      ),
      'timeout' => 10,
    ));

    if ( is_wp_error( $raw_response ) ) {
      $q = new \WC_Logger();
      $q->add( $method, 'Retry result: WP_Error ' . $raw_response->get_error_message() );
    } else {
      $q = new \WC_Logger();
      $q->add( $method, 'Retry result: ' . $raw_response['body'] );
    }
  }


  /**
   * Schedules a single event to send an email reminder 7 days before the expiration date of the Inter bank credentials.
   * 
   * @since 1.2.0
   * @version 1.2.2
   * @return void
   */
  public function schedule_remind_inter_bank_credentials() {
    $expire_date = Admin_Options::get_setting('inter_bank_expire_date');

    // Get the date format from WordPress settings
    $wp_date_format = get_option('date_format');

    // Convert date to Y-m-d format using WordPress date format
    $date_object = \DateTime::createFromFormat( $wp_date_format, $expire_date );

    // Check if date_object is valid
    if ( $date_object ) {
        $expire_date_formatted = $date_object->format('Y-m-d');

        // Subtract 7 days from the expiration date
        $send_date_email = date( 'Y-m-d', strtotime( '-7 days', strtotime( $expire_date_formatted ) ) );

        // Schedule email sending
        $timestamp_send_email = strtotime( $send_date_email . ' 08:00:00' );
        wp_schedule_single_event( $timestamp_send_email, 'remind_expire_inter_bank_credentials_event' );
    } else {
        // Handle the error case where the date format is incorrect
        error_log( 'Invalid date format for Inter bank credentials expiration date: ' . $expire_date );
    }
  }


  /**
   * Sends an email to the site admin reminding them to update the Inter bank credentials 7 days before their expiration.
   * 
   * @since 1.2.0
   * @version 1.2.2
   * @return void
   */
  public function remind_expire_inter_bank_credentials() {
    $to = get_option('admin_email');
    $subject = 'IMPORTANTE: Suas credenciais do banco Inter irão expirar em breve!';
    $message = 'Este é um lembrete de que suas credenciais do Flexify Checkout - Inter addon expirarão em 7 dias. Atualize suas credenciais para evitar interrupções no processamento de pagamentos.';
    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail( $to, $subject, $message, $headers );
  }
}