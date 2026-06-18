<?php
/**
 * Plugin Name: Custom Order Numbers for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/
 * Description: Create custom WooCommerce order numbers with prefix and numbering formats.
 * Version: 2.0.0
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: custom-order-numbers-for-woocommerce
 * Domain Path: /languages
 * Copyright: � 2021 Tyche Softwares
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 10.8.1
 * Requires PHP: 7.4
 * Tested up to: 7.0.0
 * WC requires at least: 5.0
 * Requires Plugins: woocommerce
 *
 * @package Custom-order-numbers-for-WooCommerce
 */

namespace Tyche\CON;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'CON_FILE' ) ) {
	define( 'CON_FILE', __FILE__ );
}

// Include the Product Input Fields class.
if ( ! class_exists( 'Custom_Order_Numbers', false ) ) {
	include_once dirname( CON_FILE ) . '/includes/class-custom-order-numbers.php';
}

/**
 * Returns the instance of PIF.
 *
 * @since  1.0
 */
function CON_Lite() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Custom_Order_Numbers::instance();
}

CON_Lite();
