<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Core;

use MeuMouse\Flexify_Checkout\Admin\Admin_Options;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Enqueue assets
 * 
 * @since 1.2.0
 * @package MeuMouse.com
 */
class Assets {

    /**
     * Construct function
     * 
     * @since 1.2.0
     * @return void
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
    }


    /**
	 * Enqueue admin scripts in page settings only
	 * 
	 * @since 1.2.0
	 * @return void
	 */
	public function admin_assets() {
		// check if is admin settings
		if ( is_flexify_checkout_admin_settings() ) {
			wp_enqueue_script( 'inter-bank-admin-scripts', FD_MODULE_INTER_ASSETS . 'js/inter-bank-admin.js', array('jquery'), FD_MODULE_INTER_VERSION );

			wp_localize_script( 'inter-bank-admin-scripts', 'inter_bank_params', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('inter_bank_nonce'),
                'confirm_remove_certificates' => esc_html__( 'Você realmente deseja remover os certificados?', 'module-inter-bank-for-flexify-checkout' ),
			));
		}
	}
}