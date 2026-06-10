<?php
/**
 * Custom Order Numbers for WooCommerce.
 *
 * Main Class.
 *
 * @author      Tyche Softwares
 * @package     CON/Main
 * @category    Classes
 * @since       2.0
 */

namespace Tyche\CON;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Tyche\CON\Functions as CON_Functions;

/**
 * Main Class.
 */
final class Custom_Order_Numbers {

    /**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	protected static $plugin_version = '2.0.0';

	/**
	 * Minimum version of WordPress required.
	 *
	 * @var string
	 */
	private static $wordpress_version = '5.2';

	/**
	 * Minimum version of PHP required.
	 *
	 * @var string
	 */
	private static $php_version = '7.4';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected static $plugin_slug = 'custom-order-numbers-for-woocommerce';

	/**
	 * Plugin Name.
	 *
	 * @var string
	 */
	protected static $plugin_name = 'Custom Order Numbers for WooCommerce Pro';

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	protected static $plugin_url = 'https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-for-woocommerce/';

    /**
     * The single instance of the class.
     *
     * @var Custom_Order_Numbers
     * @since 2.0
     */
    protected static $instance = null;

    /**
     * Main Custom Order Numbers Instance.
     *
     * Ensures only one instance of Custom Order Numbers is loaded or can be loaded.
     *
     * @return Custom_Order_Numbers - Main instance.
     * @since 2.0
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
            self::$instance->setup();
        }
        return self::$instance;
    }

    /**
	 * A dummy constructor to prevent CON from being loaded more than once.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

    /**
	 * A dummy magic method to prevent CON from being cloned.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed.', 'product-input-fields-for-woocommerce' ), '1.0' );
	}

	/**
	 * A dummy magic method to prevent CON from being unserialized.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed.', 'product-input-fields-for-woocommerce' ), '1.0' );
	}

    private function setup() {

        self::handle_localization();
		/**
		 * Define Constants.
		 */
		self::define_constants();

		if ( ! self::check_requirements() ) {
			return;
		}

		self::init();

		/**
		 * Include Files.
		 */
		self::maybe_include_files();

		/**
		 * Hooks.
		 */
		self::init_hooks();
    }

	public function init() {
		add_filter( 'plugin_action_links_' . CON_PLUGIN_BASENAME, array( $this, 'action_links' ) );

		add_action( 'admin_init', array( $this, 'con_update_data' ) );

		add_action( 
			'admin_init',
			function () {
				$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
				$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';

				if ( 'wc-settings' !== $page || 'custom-order-numbers-for-woocommerce' !== $tab ) {
					return;
				}

				$hide_for_roles  = CON_Functions::get_setting( 'hide_tab_for_roles', array() );
				$hide_role_slugs = array_column( (array) $hide_for_roles, 'value' );
				$user_roles      = (array) wp_get_current_user()->roles;

				if ( ! empty( array_intersect( $hide_role_slugs, $user_roles ) ) ) {
					wp_safe_redirect( admin_url( 'admin.php?page=wc-settings' ) );
					exit;
				}
			}
		);

		add_filter(
			'woocommerce_settings_tabs_array',
			function ( $tabs ) {
				$hide_for_roles = CON_Functions::get_setting( 'hide_tab_for_roles', array() );

				if ( ! empty( $hide_for_roles ) ) {
					$user            = wp_get_current_user();
					$user_roles      = (array) $user->roles;
					$hide_role_slugs = array_column( (array) $hide_for_roles, 'value' );
					$intersect       = array_intersect( $hide_role_slugs, $user_roles );

					if ( ! empty( $intersect ) ) {
						return $tabs;
					}
				}
				$tabs['custom-order-numbers-for-woocommerce'] = __( 'Custom Order Numbers', 'custom-order-numbers-for-woocommerce' );
				return $tabs;
			},
			50
		);

		add_action(
			'woocommerce_settings_tabs_custom-order-numbers-for-woocommerce',
			function () {
				echo '<div id="custom-order-numbers-for-woocommerce"></div>';
			}
		);
	}

	/**
	 * Function for definining constants.
	 *
	 * @param string $variable Constant which is to be defined.
	 * @param string $value Valueof the Constant.
	 *
	 * @since 1.0
	 */
	public static function define( $variable, $value ) {
		if ( ! defined( $variable ) ) {
			define( $variable, $value );
		}
	}

	/**
	 * Include File.
	 *
	 * @param string $file File to be included.
	 * @param bool   $is_plugin_include_file If it's a plugin file, then we can add the path.
	 * @since 1.0
	 */
	public static function include_file( $file, $is_plugin_include_file = true ) {
		$file = $is_plugin_include_file ? CON_PLUGIN_DIR_PATH . '/includes/' . $file : $file;

		$real        = realpath( $file );
		$plugin_base = realpath( CON_PLUGIN_DIR_PATH );

		if ( false === $real || false === $plugin_base || 0 !== strpos( $real, $plugin_base ) ) {
			return;
		}

		include_once $real; // nosemgrep: audit.php.lang.security.file.inclusion-arg -- all callers pass hardcoded string literals; path is prefixed with CON_PLUGIN_DIR_PATH.
	}

	/**
	 * Localization
	 *
	 * @version 1.1.3
	 * @since   1.1.3
	 */
	private function handle_localization() {
		$domain = 'custom-order-numbers-for-woocommerce';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		$loaded = load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . 'plugins/' . $domain . '-pro/' . $domain . '-' . $locale . '.mo' );
		if ( $loaded ) {
			return $loaded;
		} else {
			load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	}

	/**
	 * Define constants to be used accross the plugin.
	 *
	 * @since 2.0
	 */
	public static function define_constants() {
		self::define( 'CON_URL', self::$plugin_url );
		self::define( 'CON_VERSION', self::$plugin_version );
		self::define( 'CON_PLUGIN_BASENAME', plugin_basename( CON_FILE ) );
		self::define( 'CON_PLUGIN_DIR_PATH', plugin_dir_path( CON_FILE ) );
		self::define( 'CON_PLUGIN_PATH', untrailingslashit( plugin_dir_path( CON_FILE ) ) );
		self::define( 'CON_PLUGIN_URL', plugins_url( '/', CON_FILE ) );
		self::define( 'CON_AJAX_URL', get_admin_url() . 'admin-ajax.php' );
		self::define( 'ALG_WC_CON_ID', 'alg_wc_con' );
	}

	/**
	 * Checks that all requirements are met.
	 *
	 * @return bool
	 */
	public static function check_requirements() {

		$messages = array();

		// Check WordPress version.
		if ( version_compare( get_bloginfo( 'version' ), self::$wordpress_version, '<' ) ) {
			/* translators: 1. Plugin Name, 2. WordPress Version */
			$messages[] = sprintf( esc_html__( 'You are using an outdated version of WordPress. %1$s requires WP version %2$s or higher.', 'custom-order-numbers-for-woocommerce' ), self::$plugin_name, self::$wordpress_version );
		}

		// Check PHP version.
		if ( version_compare( phpversion(), self::$php_version, '<' ) ) {
			/* translators: 1. Plugin Name, 2. PHP Version */
			$messages[] = sprintf( esc_html__( '%1$s requires PHP version %2$s or above. Please update PHP to run this plugin.', 'custom-order-numbers-for-woocommerce' ), self::$plugin_name, self::$php_version );
		}

		// Check WooCommerce.
		if ( ! self::is_woocommerce_active() ) {
			/* translators: Plugin Name */
			$messages[] = sprintf( esc_html__( 'WooCommerce not found. %s requires a minimum of WooCommerce v3.3.0.', 'custom-order-numbers-for-woocommerce' ), self::$plugin_name );
		}

		if ( empty( $messages ) ) {
			return true;
		}

		add_action( 'admin_init', array( __CLASS__, 'deactivate' ) );

		return false;
	}

	/**
	 * Auto-deactivate plugin if requirements are not met.
	 */
	public static function deactivate() {
		if ( is_plugin_active( plugin_basename( CON_FILE ) ) ) {
			deactivate_plugins( plugin_basename( CON_FILE ) );
		}

		if ( isset( $_GET['activate'] ) ) { // phpcs:ignore
			unset( $_GET['activate'] ); // phpcs:ignore
		}
	}

	/**
	 * Checks if WooCommerce is installed and active.
	 *
	 * @since 1.0
	 */
	public static function is_woocommerce_active() {

		// WooCommerce is required.
		$woocommerce_path = 'woocommerce/woocommerce.php';
		$active_plugins   = (array) get_option( 'active_plugins', array() );
		$active           = false;

		if ( is_multisite() ) {
			$plugins = get_site_option( 'active_sitewide_plugins' );
			$active  = isset( $plugins[ $woocommerce_path ] );
		}

		return in_array( $woocommerce_path, $active_plugins, true ) || array_key_exists( $woocommerce_path, $active_plugins ) || $active;
	}

	/**
	 * Checks whether to inlcude the plugin files.
	 *
	 * @since 1.0
	 */
	public static function maybe_include_files() {
		self::include_file( 'class-files.php' );
		Files::include_files();
	}

	/**
	 * Action Hooks.
	 *
	 * @since 1.0
	 */
	private static function init_hooks() {
		register_activation_hook( CON_FILE, array( __CLASS__, 'activate_plugin' ) );
		register_deactivation_hook( CON_FILE, array( __CLASS__, 'deactivate_plugin' ) );

		// CON Hooks.
		self::include_file( 'class-hooks.php' );
		Hooks::init();
	}

	/**
	 * Function to update the settings when the plugin is updated.
	 * 
	 * @since 2.0
	 */
	public function con_update_data() {
		if ( ! class_exists( 'CON_Update' ) ) {
			require_once CON_PLUGIN_DIR_PATH . 'includes/class-update.php';
		}

		Update::maybe_update_settings();
	}

	/**
	 * Activation Hook.
	 *
	 * @since 2.0
	 */
	public static function activate_plugin() {
		if ( get_option( 'alg_wc_custom_order_numbers_enabled', null ) !== null ) {
			return;
		}

		$defaults = array(
			'enabled'                         => false,
			'counter_type'                    => 'sequential',
			'counter'                         => 1,
			'counter_reset_enabled'           => 'no',
			'day_of_counter_reset_weekly'     => 'mon',
			'counter_reset_counter_value'     => 1,
			'min_width'                       => 1,
			'settings_to_apply'               => 'new_order',
			'settings_to_apply_from_order_id' => '',
			'settings_to_apply_from_date'     => '',
			'order_tracking_enabled'          => false,
			'manual_enabled'                  => false,
			'hide_menu_for_roles'             => array(),
			'hide_tab_for_roles'              => array(),
			'custom_order_numbers_template'   => '{prefix}{date_prefix}{number}{suffix}{date_suffix}',
		);

		if ( ! get_option( 'con_general_settings', false ) ) {
			update_option( 'con_general_settings', $defaults );
			update_option( 'alg_custom_order_numbers_version', CON_VERSION );
		}
	}

	/**
	 * Deactivation Hook.
	 *
	 * @since 1.0
	 */
	public static function deactivate_plugin() {
		as_unschedule_all_actions( 'ts_send_data_tracking_usage' );
		as_unschedule_all_actions( 'alg_custom_order_numbers_weekly_reset_event' );
	}

	/**
	 * Show action links on the plugin screen
	 *
	 * @param   mixed $links Action Links.
	 * @version 1.2.0
	 * @since   1.0.0
	 * @return  array
	 */
	public function action_links( $links ) {
		$custom_links   = array();
		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=custom-order-numbers-for-woocommerce' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';

		$custom_links[] = '<a href="https://www.tychesoftwares.com/products/woocommerce-custom-order-numbers-plugin/?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite">' . __( 'Unlock All', 'product-input-fields-for-woocommerce' ) . '</a>';

		return array_merge( $custom_links, $links );
	}

}

