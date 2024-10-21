<?php
/**
 * Plugin Name: Custom Order Numbers for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/
 * Description: Custom order numbers for WooCommerce.
 * Version: 1.8.0
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: custom-order-numbers-for-woocommerce
 * Domain Path: /langs
 * Copyright: � 2021 Tyche Softwares
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP: 7.4
 * WC requires at least: 5.0.0
 * WC tested up to: 9.3.3
 * Tested up to: 6.6.2
 * Requires Plugins: woocommerce
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
	$plugin_file = 'custom-order-numbers-for-woocommerce-pro/custom-order-numbers-for-woocommerce-pro.php';
	if (
		in_array( $plugin_file, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ||
		( is_multisite() && array_key_exists( $plugin_file, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		if ( version_compare( get_option( 'alg_custom_order_numbers_version', '' ), '1.4.1' ) < 0 ) {
			add_action( 'admin_notices', 'alg_wc_con_admin_notice_for_contact_us' );
		}
	}
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

	/**
	 * Show admin notice to contact us for manual update for CON Pro when pro & lite both plugins are active.
	 *
	 * @version 1.2.12
	 * @since   1.2.12
	 */
	function alg_wc_con_admin_notice_for_contact_us() {
		?>
		<div class="notice notice-info" style="position: relative;">
			<p  style="margin: 10px 0 10px 10px; font-size: medium;">
				<?php esc_html_e( 'There is an update to Custom Order Numbers Pro but cannot be updated automatically. Please contact us to manually update the plugin. Apologies for the inconvenience.', 'custom-order-numbers-for-woocommerce' ); ?></p>
			<p class="submit" style="margin: -10px 0 10px 10px;">
				<a class="button-primary button button-large" href="https://support.tychesoftwares.com/help/2285384554"><?php esc_html_e( 'Contact us', 'custom-order-numbers-for-woocommerce' ); ?></a>
			</p>
		</div>
		<?php
	}
}
