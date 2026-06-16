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

if ( ! class_exists( 'Tyche\CON\Tyche_CON_Deactivation' ) ) {

	/** Declaration of Class */
	class Tyche_CON_Deactivation {

		/** Constructor */
		public function __construct() {
			require_once __DIR__ . '/tyche/components/plugin-deactivation/class-tyche-plugin-deactivation.php';
			new Tyche_Plugin_Deactivation(
				array(
					'plugin_name'       => 'Custom Order Numbers for WooCommerce',
					'plugin_base'       => 'custom-order-numbers-for-woocommerce/custom-order-numbers-for-woocommerce.php',
					'script_file'       => CON_PLUGIN_URL . '/includes/tyche/assets/js/plugin-deactivation.js',
					'plugin_short_name' => 'con_lite',
					'version'           => CON_VERSION,
					'plugin_locale'     => 'custom-order-numbers-for-woocommerce',
				)
			);
		}
	}

	// Initialize the license class.
	new Tyche_CON_Deactivation();
}
