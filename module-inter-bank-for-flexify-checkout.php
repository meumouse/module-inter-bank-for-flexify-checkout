<?php

/**
 * Plugin Name: 			Flexify Checkout - Inter addon
 * Description: 			Comece a receber via Pix e Boleto com aprovação imediata e sem cobrança de taxas com o Inter Empresas, exclusivo para Flexify Checkout.
 * Plugin URI: 				https://meumouse.com/plugins/flexify-checkout-para-woocommerce/
 * Requires Plugins: 		flexify-checkout-for-woocommerce
 * Author: 				    MeuMouse.com
 * Author URI: 			    https://meumouse.com/
 * Version: 			    1.2.6
 * WC requires at least:    6.0.0
 * WC tested up to: 		9.1.2
 * Requires PHP: 			7.4
 * Tested up to:      		6.6.1
 * Text Domain: 			module-inter-bank-for-flexify-checkout
 * Domain Path: 			/languages
 * License: 				GPL2
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add Inter bank payment gateways on WooCommerce
 * 
 * @since 1.0.0
 * @version 1.2.5
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
	public static $version = '1.2.6';

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
		add_action( 'plugins_loaded', array( $this, 'check_before_init' ), 99 );
	}


  	/**
	 * Check dependencies before activate plugin
	 * 
	 * @since 1.0.0
	 * @version 1.2.5
	 * @return void
	 */
	public function check_before_init() {
		if ( ! is_plugin_active('flexify-checkout-for-woocommerce/flexify-checkout-for-woocommerce.php') ) {
			add_action( 'admin_notices', array( $this, 'flexify_checkout_require_notice' ) );
			return;
		}
	
		$this->define_constants();

		load_plugin_textdomain( 'module-inter-bank-for-flexify-checkout', false, dirname( FD_MODULE_INTER_BASENAME ) . '/languages/' );
		add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_filter( 'plugin_action_links_' . FD_MODULE_INTER_BASENAME, array( $this, 'setup_action_links' ), 10, 4 );

		// load Composer
		require_once FD_MODULE_INTER_PATH . 'vendor/autoload.php';

		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Admin();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Assets();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Ajax();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Bank_Slip\Print_Bank_Slip();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Cron\Schedule();
		new \MeuMouse\Flexify_Checkout\Inter_Bank\Core\Updater();
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
	 * Define constant if not already set
	 *
	 * @since 1.0.0
	 * @param string $name | Constant name
	 * @param string|bool $value | Constant value
	 * @return void
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}


	/**
	 * Define constants
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @return void
	 */
	private function define_constants() {
		$this->define( 'FD_MODULE_INTER_FILE', __FILE__ );
		$this->define( 'FD_MODULE_INTER_PATH', plugin_dir_path( __FILE__ ) );
		$this->define( 'FD_MODULE_INTER_URL', plugin_dir_url( __FILE__ ) );
		$this->define( 'FD_MODULE_INTER_ASSETS', FD_MODULE_INTER_URL . 'assets/' );
		$this->define( 'FD_MODULE_INTER_INC_PATH', FD_MODULE_INTER_PATH . 'inc/' );
		$this->define( 'FD_MODULE_INTER_TPL_PATH', FD_MODULE_INTER_PATH . 'templates/' );
		$this->define( 'FD_MODULE_INTER_BASENAME', plugin_basename( __FILE__ ) );
		$this->define( 'FD_MODULE_INTER_VERSION', self::$version );
		$this->define( 'FD_MODULE_INTER_SLUG', self::$slug );
	}


	/**
	 * Setp compatibility with HPOS/Custom order table feature of WooCommerce.
	 *
	 * @since 1.0.0
	 * @version 1.2.0
	 * @return void
	 */
	public static function setup_hpos_compatibility() {
		if ( defined('WC_VERSION') && version_compare( WC_VERSION, '7.1', '>' ) ) {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FD_MODULE_INTER_FILE, true );
			}
		}
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
	 * PHP version notice
	 * 
	 * @since 1.0.0
	 * @version 1.2.0
	 * @return void
	 */
	public function php_version_notice() {
		$class = 'notice notice-error is-dismissible';
		$message = __( '<strong>Flexify Checkout - Inter addon</strong> requer a versão do PHP 7.4 ou maior. Contate o suporte da sua hospedagem para realizar a atualização.', 'module-inter-bank-for-flexify-checkout' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}
}

Module_Inter_Bank::run();