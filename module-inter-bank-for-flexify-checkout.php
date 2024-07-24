<?php

/**
 * Plugin Name: 			Módulo adicional banco Inter para Flexify Checkout para WooCommerce
 * Description: 			Extensão que adiciona a forma de pagamento via Pix e Boleto do banco Inter para lojas WooCommerce exclusivo para Flexify Checkout.
 * Plugin URI: 				https://meumouse.com/plugins/flexify-checkout-para-woocommerce/
 * Author: 				    MeuMouse.com
 * Author URI: 			    https://meumouse.com/
 * Version: 			    1.1.0
 * WC requires at least:    6.0.0
 * WC tested up to: 		9.1.2
 * Requires PHP: 			7.4
 * Tested up to:      		6.4.3
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
 * @version 1.1.0
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
	public static $version = '1.1.0';

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
	 * @version 1.1.0
	 * @return void
	 */
	public function __construct() {
		$this->define_constants();
		require FCW_MODULE_INTER_PATH . '/vendor/autoload.php';

		load_plugin_textdomain( 'module-inter-bank-for-flexify-checkout', false, dirname( FCW_MODULE_INTER_BASENAME ) . '/languages/' );
		add_action( 'plugins_loaded', array( $this, 'check_before_init' ), 5 );

		include_once FCW_MODULE_INTER_PATH . '/core/class-updater.php';
	}


  	/**
	 * Check dependencies before activate plugin
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @return void
	 */
	public function check_before_init() {
		// Display notice if PHP version is bottom 7.4
		if ( version_compare( phpversion(), '7.4', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
	
		// check if Flexify Checkout is active
		if ( is_plugin_active('flexify-checkout-for-woocommerce/flexify-checkout-for-woocommerce.php') ) {
			add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

			new \MeuMouse\Flexify_Checkout\Inter_Bank\Bank_Slip\Print_Bank_Slip();
			new \MeuMouse\Flexify_Checkout\Inter_Bank\Cron\Schedule();

			add_action( 'wc_ajax_inter_bank_order_is_paid', array( \MeuMouse\Flexify_Checkout\Inter_Bank\Gateways\Pix::class, 'order_is_paid' ), 10, 2 );
			add_action( 'module_inter_bank_retry', array( $this, 'retry_webhook_query' ), 100, 3);
			$this->initiated = true;

			add_action( 'plugins_loaded', array( $this, 'activate' ), 100 );
		} else {
			add_action( 'admin_notices', array( $this, 'flexify_checkout_require_notice' ) );
		}
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
		$this->define( 'FCW_MODULE_INTER_FILE', __FILE__ );
		$this->define( 'FCW_MODULE_INTER_PATH', plugin_dir_path( __FILE__ ) );
		$this->define( 'FCW_MODULE_INTER_URL', plugin_dir_url( __FILE__ ) );
		$this->define( 'FCW_MODULE_INTER_ASSETS', FCW_MODULE_INTER_URL . 'assets/' );
		$this->define( 'FCW_MODULE_INTER_INC_PATH', FCW_MODULE_INTER_PATH . 'inc/' );
		$this->define( 'FCW_MODULE_INTER_TPL_PATH', FCW_MODULE_INTER_PATH . 'templates/' );
		$this->define( 'FCW_MODULE_INTER_BASENAME', plugin_basename( __FILE__ ) );
		$this->define( 'FCW_MODULE_INTER_VERSION', self::$version );
		$this->define( 'FCW_MODULE_INTER_SLUG', self::$slug );
	}


	/**
	 * Setp compatibility with HPOS/Custom order table feature of WooCommerce.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function setup_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FCW_MODULE_INTER_FILE, true );
		}
	}


	/**
	 * Retry cron fail
	 * 
	 * @since 1.0.0
	 * @param string $method | Pix or bank slip ID
	 * @param array $payload | Get body request
	 * @param int $order_id | Order ID
	 * @return void
	 */
	public function retry_webhook_query( $method, $payload, $order_id ) {
		$q = new \WC_Logger(); $q->add( $method, 'Webhook try again for ' . $order_id );

		$raw_response = wp_remote_post( WC()->api_request_url( $method ), array(
			'body' => $payload,
			'headers' => array(
				'Content-Type: application/json',
			),
			'timeout' => 10,
		));

		if ( is_wp_error( $raw_response ) ) {
			$q = new \WC_Logger(); $q->add( $method, 'Retry result: WP_Error ' . $raw_response->get_error_message() );
		} else {
			$q = new \WC_Logger(); $q->add( $method, 'Retry result: ' . $raw_response['body'] );
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
	 * Flexify Checkout require notice
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @return void
	 */
	public function flexify_checkout_require_notice() {
		$class = 'notice notice-error is-dismissible';
		$message = esc_html__( '<strong>Módulo adicional banco Inter para Flexify Checkout para WooCommerce</strong> requer o plugin Flexify Checkout para WooCommerce.', 'module-inter-bank-for-flexify-checkout' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}


  	/**
	 * PHP version notice
	 * 
	 * @since 1.0.0
	 * @version 1.1.0
	 * @return void
	 */
	public function php_version_notice() {
		$class = 'notice notice-error is-dismissible';
		$message = esc_html__( '<strong>Módulo adicional banco Inter para Flexify Checkout para WooCommerce</strong> requer a versão do PHP 7.4 ou maior. Contate o suporte da sua hospedagem para realizar a atualização.', 'module-inter-bank-for-flexify-checkout' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
	}


  	/**
	 * Create paste and archive .htaccess for certificates
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		$upload_dir = wp_upload_dir();
		$folder_path = $upload_dir['basedir'] . '/flexify_checkout_integrations';

		if ( ! file_exists( $folder_path ) ) {
			mkdir( $folder_path );
		}

		$file_path = $folder_path . '/.htaccess';

		if ( ! file_exists( $file_path ) ) {
			file_put_contents( $file_path, 'deny from all' );
		}
	}
}

Module_Inter_Bank::run();