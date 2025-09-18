<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for initializing all the plugin classes
 * 
 * @since 1.3.1
 * @version 1.4.0
 * @package MeuMouse.com
 */
class Init {

    /**
     * Construct function
     * 
     * @since 1.4.0
     * @return void
     */
    public function __construct() {
        // load text domain
        load_plugin_textdomain( 'module-inter-bank-for-flexify-checkout', false, dirname( FD_MODULE_INTER_BASENAME ) . '/languages/' );
		
        // register gateways
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ), 999, 1 );

		// prevent conflicts with other gateway plugins
		$this->rebuild_wc_gateways();

		add_filter( 'plugin_action_links_' . FD_MODULE_INTER_BASENAME, array( $this, 'setup_action_links' ), 10, 4 );

        new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Admin();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Views\Settings();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Admin\Product_Automattic_Pix();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Assets();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Ajax();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Bank_Slip\Print_Bank_Slip();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Cron\Schedule();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Updater();
    }


	/**
	 * Re-build WooCommerce payment gateways to ensure the latest changes are applied
	 *
	 * @since 1.3.2
	 * @return void
	 */
	private function rebuild_wc_gateways() {
		if ( function_exists('WC') && WC()->payment_gateways() ) {
			$pg = WC()->payment_gateways();

			// Evita duplicatas e garante re-build
			if ( isset( $pg->payment_gateways ) ) {
				$pg->payment_gateways = array();
			}

			$pg->init();
		}
	}


    /**
	 * Add the gateway to WooCommerce
	 *
	 * @since 1.0.0
	 * @version 1.4.0
	 * @param array $methods | WooCommerce payment methods
	 * @return array Payment methods with Banco Inter Methods.
	 */
	public function add_gateway( $methods ) {
		if ( ! is_array( $methods ) ) {
			$methods = array();
		}

		$add = array(
			\MeuMouse\Flexify_Checkout\Inter_Bank\Gateways\Bank_Slip::class,
			\MeuMouse\Flexify_Checkout\Inter_Bank\Gateways\Pix::class,
			\MeuMouse\Flexify_Checkout\Inter_Bank\Gateways\Automattic_Pix::class,
		);

		foreach ( $add as $class ) {
			if ( ! in_array( $class, $methods, true ) ) {
				$methods[] = $class;
			}
		}

		return $methods;
	}


	/**
	 * Plugin action links
	 * 
	 * @since 1.2.0
	 * @param array $action_links
	 * @return string
	 */
	public function setup_action_links( $action_links ) {
		$plugins_links = array(
			'<a href="' . admin_url('admin.php?page=flexify-checkout-for-woocommerce#integrations') . '">'. __( 'Configurar', 'module-inter-bank-for-flexify-checkout' ) .'</a>',
		);

		return array_merge( $plugins_links, $action_links );
	}
}