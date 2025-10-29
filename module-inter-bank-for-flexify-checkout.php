<?php

/**
 * Plugin Name: 			Flexify Checkout - Inter addon
 * Description: 			Comece a receber via Pix e Boleto com aprovação imediata e sem cobrança de taxas com o Inter Empresas, exclusivo para Flexify Checkout.
 * Plugin URI: 				https://meumouse.com/plugins/flexify-checkout-para-woocommerce/
 * Requires Plugins: 		flexify-checkout-for-woocommerce
 * Author: 				    MeuMouse.com
 * Author URI: 			    https://meumouse.com/
 * Version: 			    1.4.0
 * WC requires at least:    6.0.0
 * WC tested up to: 		10.3.3
 * Requires PHP: 			7.4
 * Tested up to:      		6.8.3
 * Text Domain: 			module-inter-bank-for-flexify-checkout
 * Domain Path: 			/languages
 * License: 				GPL2
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add Inter bank payment gateways on WooCommerce
 * 
 * @since 1.0.0
 * @version 1.3.1
 * @package MeuMouse.com
 */
class Module_Inter_Bank {

  	/**
	 * Plugin slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public static $slug = 'module-inter-bank-for-flexify-checkout';

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public static $version = '1.4.0';

  	/**
	 * Plugin initiated
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public $initiated = false;

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Construct function
	 * 
	 * @since 1.0.0
	 * @version 1.2.5
	 * @return void
	 */
	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );
		add_action( 'wp_loaded', array( $this, 'init' ), 99 );
	}


  	/**
	 * Check dependencies before activate plugin
	 * 
	 * @since 1.0.0
	 * @version 1.3.2
	 * @return void
	 */
	public function init() {
		// load WordPress plugin class if function is_plugin_active() is not defined
        if ( ! function_exists('is_plugin_active') ) {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

		// check if Flexify Checkout is active
		if ( ! is_plugin_active('flexify-checkout-for-woocommerce/flexify-checkout-for-woocommerce.php') ) {
			add_action( 'admin_notices', array( $this, 'flexify_checkout_require_notice' ) );
			return;
		}

		$this->define_constants();

		// check if Flexify Checkout version is compatible
		if ( defined('FLEXIFY_CHECKOUT_VERSION') && version_compare( FLEXIFY_CHECKOUT_VERSION, '5.0.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'flexify_checkout_version_notice' ) );
			deactivate_plugins( FD_MODULE_INTER_BASENAME );
			return;
		}

		// load Composer
		require_once FD_MODULE_INTER_PATH . 'vendor/autoload.php';

		// initialize the plugin
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Init();
	}


	/**
	 * Flexify Checkout require notice
	 * 
	 * @since 1.0.0
	 * @version 1.2.0
	 * @return void
	 */
	public function flexify_checkout_require_notice() {
		$class = 'notice notice-error is-dismissible';
		$message = __( '<strong>Flexify Checkout - Inter addon</strong> requer o plugin Flexify Checkout para WooCommerce.', 'module-inter-bank-for-flexify-checkout' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}


	/**
	 * Show notice if Flexify Checkout version is outdated
	 * 
	 * @since 1.3.1
	 * @return void
	 */
	public function flexify_checkout_version_notice() {
		$class = 'notice notice-error';
		$message = sprintf( __( '<strong>Flexify Checkout - Inter addon</strong> requer o <strong>Flexify Checkout</strong> na versão <strong>5.0.0</strong> ou superior. Atualize o plugin principal para continuar usando este módulo.', 'module-inter-bank-for-flexify-checkout' ) );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}


	/**
	 * Return an instance of this class
	 *
	 * @since 1.0.0
	 * @return object A single instance of this class
	 */
	public static function run() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}


	/**
	 * Define constants
	 * 
	 * @since 1.0.0
	 * @version 1.3.1
	 * @return void
	 */
	private function define_constants() {
		$base_file = __FILE__;
		$base_path = plugin_dir_path( $base_file );
		$base_url = plugin_dir_url( $base_file );

		$constants = array(
			'FD_MODULE_INTER_FILE'     => $base_file,
			'FD_MODULE_INTER_PATH'     => $base_path,
			'FD_MODULE_INTER_URL'      => $base_url,
			'FD_MODULE_INTER_ASSETS'   => $base_url . 'assets/',
			'FD_MODULE_INTER_INC_PATH' => $base_path . 'inc/',
			'FD_MODULE_INTER_TPL_PATH' => $base_path . 'templates/',
			'FD_MODULE_INTER_BASENAME' => plugin_basename( $base_file ),
			'FD_MODULE_INTER_VERSION'  => self::$version,
			'FD_MODULE_INTER_SLUG'     => self::$slug,
		);

		foreach ( $constants as $name => $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}
	}


	/**
	 * Setup compatibility with HPOS table feature of WooCommerce
	 *
	 * @since 1.0.0
	 * @version 1.3.2
	 * @return void
	 */
	public static function setup_hpos_compatibility() {
		if ( defined('WC_VERSION') && version_compare( WC_VERSION, '7.1', '>' ) ) {
			if ( class_exists( FeaturesUtil::class ) ) {
				FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
			}
		}
	}
}

/**
 * Initialize the plugin
 * 
 * @since 1.0.0
 */
Module_Inter_Bank::run();