<?php
/**
 * Plugin Name: Custom Order Numbers for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/
 * Description: Custom order numbers for WooCommerce.
 * Version: 1.2.10
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: custom-order-numbers-for-woocommerce
 * Domain Path: /langs
 * Copyright: � 2018 Tyche Softwares
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 4.0.0
 *
 * @package Custom-Order-Numbers-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers' ) ) {
	include_once 'class-alg-wc-custom-order-numbers.php';
}

if ( is_admin() ) {
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'action_links' );

	/**
	 * Show action links on the plugin screen
	 *
	 * @param   mixed $links - Links to be displayed for the plugin on WP Dashboard->Plugins.
	 * @return  array
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function action_links( $links ) {
		$custom_links   = array();
		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_numbers' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
		if ( 'custom-order-numbers-for-woocommerce.php' === basename( __FILE__ ) ) {
			$custom_links[] = '<a href="https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=conupgradetopro&utm_medium=unlockall&utm_campaign=CustomOrderNumbersLite">' . __( 'Unlock All', 'custom-order-numbers-for-woocommerce' ) . '</a>';
		}
		return array_merge( $custom_links, $links );
	}
}
