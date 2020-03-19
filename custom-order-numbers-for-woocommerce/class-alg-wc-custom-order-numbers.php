<?php
/**
 * Main Plugin Class file.
 *
 * @package Custom-Order-Numbers-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if WooCommerce is active.
$plugin_name = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin_name, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) &&
	! ( is_multisite() && array_key_exists( $plugin_name, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'custom-order-numbers-for-woocommerce.php' === basename( __FILE__ ) ) {
	// Check if Pro is active, if so then return.
	$plugin_file = 'custom-order-numbers-for-woocommerce-pro/custom-order-numbers-for-woocommerce-pro.php';
	if (
		in_array( $plugin_file, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ||
		( is_multisite() && array_key_exists( $plugin_file, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers' ) ) :

	/**
	 * Main Alg_WC_Custom_Order_Numbers Class
	 *
	 * @class   Alg_WC_Custom_Order_Numbers
	 * @version 1.2.3
	 * @since   1.0.0
	 */
	final class Alg_WC_Custom_Order_Numbers {

		/**
		 * Plugin version.
		 *
		 * @var   string
		 * @since 1.0.0
		 */
		public $version = '1.2.10';

		/**
		 * The single instance of the class
		 *
		 * @var   Alg_WC_Custom_Order_Numbers The single instance of the class
		 * @since 1.0.0
		 */
		protected static $instance = null;

		/**
		 * Main Alg_WC_Custom_Order_Numbers Instance
		 *
		 * Ensures only one instance of Alg_WC_Custom_Order_Numbers is loaded or can be loaded.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @static
		 * @return  Alg_WC_Custom_Order_Numbers - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Alg_WC_Custom_Order_Numbers Constructor.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @access  public
		 */
		public function __construct() {

			// Set up localisation.
			load_plugin_textdomain( 'custom-order-numbers-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

			// Include required files.
			$this->includes();

			// Settings & Scripts.
			if ( is_admin() ) {
				add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function includes() {
			// Settings.
			require_once 'includes/admin/class-alg-wc-custom-order-numbers-settings-section.php';
			$this->settings            = array();
			$this->settings['general'] = require_once 'includes/admin/class-alg-wc-custom-order-numbers-settings-general.php';
			if ( is_admin() && get_option( 'alg_custom_order_numbers_version', '' ) !== $this->version ) {
				foreach ( $this->settings as $section ) {
					foreach ( $section->get_settings() as $value ) {
						if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
							$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
							add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
						}
					}
				}
				update_option( 'alg_custom_order_numbers_version', $this->version );
			}
			// Core file needed.
			require_once 'includes/class-alg-wc-custom-order-numbers-core.php';
		}

		/**
		 * Add Custom Order Numbers settings tab to WooCommerce settings.
		 *
		 * @param array $settings - List containing all the plugin files which will be displayed in the Settings.
		 * @return array $settings
		 *
		 * @version 1.2.2
		 * @since   1.0.0
		 */
		public function add_woocommerce_settings_tab( $settings ) {
			$hide_for_roles = get_option( 'alg_wc_custom_order_numbers_hide_tab_for_roles', array() );
			if ( ! empty( $hide_for_roles ) ) {
				$user       = wp_get_current_user();
				$user_roles = (array) $user->roles;
				$intersect  = array_intersect( $hide_for_roles, $user_roles );
				if ( ! empty( $intersect ) ) {
					return $settings;
				}
			}
			$settings[] = include 'includes/admin/class-alg-wc-settings-custom-order-numbers.php';
			return $settings;
		}

		/**
		 * Get the plugin url.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_url() {
			return untrailingslashit( plugin_dir_url( __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

	}

endif;

if ( ! function_exists( 'alg_wc_custom_order_numbers' ) ) {
	/**
	 * Returns the main instance of Alg_WC_Custom_Order_Numbers to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  Alg_WC_Custom_Order_Numbers
	 */
	function alg_wc_custom_order_numbers() {
		return Alg_WC_Custom_Order_Numbers::instance();
	}
}

alg_wc_custom_order_numbers();
