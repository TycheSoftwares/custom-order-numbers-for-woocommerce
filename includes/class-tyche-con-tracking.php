<?php
/**
 * Custom Order Number for WooCommerce Pro - Deactivation Class
 *
 * @version 1.1.7
 * @since   1.1.3
 * @author  Tyche Softwares
 * @package Custom Order Numbers Pro
 */

namespace Tyche\CON;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Tyche\CON\Tyche_CON_Tracking' ) ) {

	/** Declaration of Class */
	class Tyche_CON_Tracking {

		/** Constructor */
		public function __construct() {
			require_once __DIR__ . '/tyche/components/plugin-tracking/class-tyche-plugin-tracking.php';
			new Tyche_Plugin_Tracking(
				array(
					'plugin_name'       => 'Custom Order Numbers for WooCommerce',
					'plugin_locale'     => 'custom-order-numbers-for-woocommerce',
					'plugin_short_name' => 'con_lite',
					'version'           => CON_VERSION,
					'blog_link'         => 'https://www.tychesoftwares.com/docs/woocommerce-custom-order-numbers/usage-tracking-order-numbers/',
				)
			);
			if ( is_admin() ) {
				require_once __DIR__ . '/tyche/components/plugin-tracking/class-con-data-tracking.php';
			}
		}
	}

	// Initialize the license class.
	new Tyche_CON_Tracking();
}
