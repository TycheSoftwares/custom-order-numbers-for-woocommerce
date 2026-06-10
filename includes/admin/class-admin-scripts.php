<?php
/**
 * CON Admin Scripts Class
 * 
 * Handles the enqueuing of admin scripts and styles for the CON plugin, as well as displaying relevant notices.
 * 
 * @package CON/Admin/Scripts
 * @since 1.0
 */

namespace Tyche\CON\Admin;

use Tyche\CON\Functions as CON_Functions;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Scripts.
 *
 * @since 1.0
 */
class Admin_Scripts extends Admin {

	/**
	 * Construct
	 *
	 * @since 1.0
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_css' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_js' ) );
	}

	/**
	 * CSS.
	 *
	 * @since 1.0
	 */
	public static function enqueue_css() {

		if ( self::is_on_con_page() ) {
			wp_enqueue_style(
				'product-input-fields-for-woocommerce-admin',
				plugins_url( 'build/admin.css', CON_FILE ),
				array(),
				CON_VERSION
			);
		}
	}

	/**
	 * JS.
	 *
	 * @since 1.0
	 */
	public static function enqueue_js() {
		$asset_file = array(
			'dependencies' => array( 'wp-api-fetch', 'wp-i18n', 'wp-date', 'wp-element', 'wp-components' ),
			'version'      => CON_VERSION,
		);
	
		if ( self::is_on_con_page() ) {

			// Load app.js.
			wp_register_script(
				'custom-order-numbers-for-woocommerce',
				plugins_url( 'build/admin.js', CON_FILE ),
				$asset_file['dependencies'],
				$asset_file['version'],
				true
			);

			$user       = wp_get_current_user();
			$user_roles = CON_Functions::get_user_roles();
			$user_roles[] = (object) array(
				'value' => 'guest',
				'label' => __( 'Guest', 'custom-order-numbers-for-woocommerce' ),
			);

			wp_localize_script(
				'custom-order-numbers-for-woocommerce',
				'conAdminData',
				array(
					'paymentGateways'  => CON_Functions::get_payment_gateways(),
					'userRoles'        => $user_roles,
					'currentUserRoles' => (array) $user->roles,
					'upgradeUrl'       => 'https://www.tychesoftwares.com/products/woocommerce-custom-order-numbers-plugin?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',
				)
			);

			wp_enqueue_script( 'custom-order-numbers-for-woocommerce' );

			wp_set_script_translations(
				'custom-order-numbers-for-woocommerce',
				'custom-order-numbers-for-woocommerce',
				CON_PLUGIN_DIR_PATH . 'languages'
			);

		}

		wp_register_script(
            'tyche',
            CON_PLUGIN_URL . '/assets/js/tyche.js',
            array( 'jquery' ),
            CON_VERSION,
            true
        );

	}
}