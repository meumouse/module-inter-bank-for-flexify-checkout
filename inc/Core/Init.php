<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class for initializing all the plugin classes
 * 
 * @since 1.3.1
 * @package MeuMouse.com
 */
class Init {

    /**
     * Construct function
     * 
     * @since 1.3.1
     * @return void
     */
    public function __construct() {
        // load text domain
        load_plugin_textdomain( 'module-inter-bank-for-flexify-checkout', false, dirname( FD_MODULE_INTER_BASENAME ) . '/languages/' );
		
        // register gateways
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_filter( 'plugin_action_links_' . FD_MODULE_INTER_BASENAME, array( $this, 'setup_action_links' ), 10, 4 );

        new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Admin();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Assets();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Ajax();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Bank_Slip\Print_Bank_Slip();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Cron\Schedule();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Updater();
    }


    /**
	 * Add the gateway to WooCommerce.
	 *
	 * @since 1.0.0
	 * @param array $methods WooCommerce payment methods.
	 * @return array Payment methods with Banco Inter Methods.
	 */
	public function add_gateway( $methods ) {
		$methods[] = \MeuMouse\Flexify_Checkout\Inter_Bank\Gateways\Bank_Slip::class;
		$methods[] = \MeuMouse\Flexify_Checkout\Inter_Bank\Gateways\Pix::class;

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