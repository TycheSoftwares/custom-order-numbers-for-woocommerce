<?php
/**
 * Custom Order Numbers for WooCommerce - Lite
 *
 * Uninstalling Custom Order Numbers for WooCommerce Plugin delete settings.
 *
 * @author      Tyche Softwares
 * @category    Core
 * @version     1.2.6
 * @package     Custom-Order-Numbers-Lite
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// check if the Pro version file is present. If yes, do not delete any settings irrespective of whether the plugin is active or no.
if ( file_exists( WP_PLUGIN_DIR . '/custom-order-numbers-for-woocommerce-pro/custom-order-numbers-for-woocommerce-pro.php' ) ) {
	return;
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Delete the data for the WordPress Multisite.
 */
if ( is_multisite() ) {

	$con_blog_list = get_sites();

	foreach ( $con_blog_list as $con_blog_list_key => $con_blog_list_value ) {


		$con_blog_id = $con_blog_list_value->blog_id;

		/**
		 * It indicates the sub site id.
		 */
		$con_multisite_prefix = $con_blog_id > 1 ? $wpdb->prefix . "$con_blog_id_" : $wpdb->prefix;

		// General Settings.
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_enabled' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_counter_type' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_counter' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_counter_reset_enabled' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_counter_reset_counter_value' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_prefix' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_date_prefix' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_min_width' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_suffix' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_date_suffix' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_template' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_order_tracking_enabled' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_search_by_custom_number_enabled' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_manual_enabled' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_hide_menu_for_roles' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_hide_tab_for_roles' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers__reset' );
		delete_blog_option( $con_blog_id, 'alg_wc_custom_order_numbers_counter_previous_order_date' );
		// License.
		delete_blog_option( $con_blog_id, 'edd_license_key_con' );

		// Version Number.
		delete_blog_option( $con_blog_id, 'alg_custom_order_numbers_version' );

	}
} else {

	// General Settings.
	delete_option( 'alg_wc_custom_order_numbers_enabled' );
	delete_option( 'alg_wc_custom_order_numbers_counter_type' );
	delete_option( 'alg_wc_custom_order_numbers_counter' );
	delete_option( 'alg_wc_custom_order_numbers_counter_reset_enabled' );
	delete_option( 'alg_wc_custom_order_numbers_counter_reset_counter_value' );
	delete_option( 'alg_wc_custom_order_numbers_prefix' );
	delete_option( 'alg_wc_custom_order_numbers_date_prefix' );
	delete_option( 'alg_wc_custom_order_numbers_min_width' );
	delete_option( 'alg_wc_custom_order_numbers_suffix' );
	delete_option( 'alg_wc_custom_order_numbers_date_suffix' );
	delete_option( 'alg_wc_custom_order_numbers_template' );
	delete_option( 'alg_wc_custom_order_numbers_order_tracking_enabled' );
	delete_option( 'alg_wc_custom_order_numbers_search_by_custom_number_enabled' );
	delete_option( 'alg_wc_custom_order_numbers_manual_enabled' );
	delete_option( 'alg_wc_custom_order_numbers_hide_menu_for_roles' );
	delete_option( 'alg_wc_custom_order_numbers_hide_tab_for_roles' );
	delete_option( 'alg_wc_custom_order_numbers__reset' );
	delete_option( 'alg_wc_custom_order_numbers_counter_previous_order_date' );
	// License.
	delete_option( 'edd_license_key_con' );

	// Version Number.
	delete_option( 'alg_custom_order_numbers_version' );

}
// Clear any cached data that has been removed.
wp_cache_flush();
