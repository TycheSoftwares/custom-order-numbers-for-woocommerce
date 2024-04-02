<?php
/**
 * Custom Order Numbers for WooCommerce - Data Tracking Class
 *
 * @version 1.0.0
 * @since   1.3.0
 * @package Custom Order Numbers/Data Tracking
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Con_Lite_Data_Tracking' ) ) :

	/**
	 * Custom Order Number Data Tracking Core.
	 */
	class Con_Lite_Data_Tracking {

		/**
		 * Construct.
		 *
		 * @since 1.3.0
		 */
		public function __construct() {

			// Include JS script for the notice.
			add_filter( 'con_lite_ts_tracker_data', array( __CLASS__, 'con_lite_ts_add_plugin_tracking_data' ), 10, 1 );
			add_action( 'admin_footer', array( __CLASS__, 'ts_admin_notices_scripts' ) );
			// Send Tracker Data.
			add_action( 'con_lite_init_tracker_completed', array( __CLASS__, 'init_tracker_completed' ), 10, 2 );
			add_filter( 'con_lite_ts_tracker_display_notice', array( __CLASS__, 'con_lite_ts_tracker_display_notice' ), 10, 1 );

		}

		/**
		 * Send the plugin data when the user has opted in
		 *
		 * @hook ts_tracker_data
		 * @param array $data All data to send to server.
		 *
		 * @return array $plugin_data All data to send to server.
		 */
		public static function con_lite_ts_add_plugin_tracking_data( $data ) {
			$plugin_short_name = 'con_lite';
			if ( ! isset( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ) {
				return $data;
			}

			$tracker_option = isset( $_GET[ $plugin_short_name . '_tracker_optin' ] ) ? $plugin_short_name . '_tracker_optin' : ( isset( $_GET[ $plugin_short_name . '_tracker_optout' ] ) ? $plugin_short_name . '_tracker_optout' : '' ); // phpcs:ignore
			if ( '' === $tracker_option || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ), $tracker_option ) ) {
				return $data;
			}

			$data = self::con_lite_plugin_tracking_data( $data );
			return $data;
		}

		/**
		 * Admin enqueue scripts for data tracking.
		 *
		 * @since 1.3.0
		 */
		public static function ts_admin_notices_scripts() {
			$nonce            = wp_create_nonce( 'tracking_notice' );
			$plugin_url       = plugins_url() . '/custom-order-numbers-for-woocommerce';
			$numbers_instance = alg_wc_custom_order_numbers();
			wp_enqueue_script(
				'con_ts_dismiss_notice',
				$plugin_url . '/includes/js/tyche-dismiss-tracking-notice.js',
				'',
				$numbers_instance->version,
				false
			);

			wp_localize_script(
				'con_ts_dismiss_notice',
				'con_ts_dismiss_notice',
				array(
					'ts_prefix_of_plugin' => 'con_lite',
					'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
					'tracking_notice'     => $nonce,
				)
			);
		}

		/**
		 * Add tracker completed.
		 */
		public static function init_tracker_completed() {
			header( 'Location: ' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_numbers' ) );
			exit;
		}

		/**
		 * Display admin notice on specific page.
		 *
		 * @param array $is_flag Is Flag defailt value true.
		 */
		public static function con_lite_ts_tracker_display_notice( $is_flag ) {
			global $current_section;
			if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) { // phpcs:ignore
				$is_flag = false;
				if ( isset( $_GET['tab'] ) && 'alg_wc_custom_order_numbers' === $_GET['tab'] && empty( $current_section ) ) { // phpcs:ignore
					$is_flag = true;
				}
			}
			return $is_flag;
		}

		/**
		 * Returns plugin data for tracking.
		 *
		 * @param array $data - Generic data related to WP, WC, Theme, Server and so on.
		 * @return array $data - Plugin data included in the original data received.
		 * @since 1.3.0
		 */
		public static function con_lite_plugin_tracking_data( $data ) {

			$plugin_data = array(
				'ts_meta_data_table_name' => 'ts_tracking_con_lite_meta_data',
				'ts_plugin_name'          => 'Custom Order Numbers for WooCommerce',
				'global_settings'         => self::con_lite_get_global_settings(),
				'orders_count'            => self::con_lite_get_custom_order_numbers_count(),
			);

			$data['plugin_data'] = $plugin_data;

			return $data;
		}

		/**
		 * Send the global settings for tracking.
		 *
		 * @since 1.3.0
		 */
		public static function con_lite_get_global_settings() {

			$global_settings = array(
				'alg_wc_custom_order_numbers_enabled'      => get_option( 'alg_wc_custom_order_numbers_enabled' ),
				'alg_wc_custom_order_numbers_counter_type' => get_option( 'alg_wc_custom_order_numbers_counter_type' ),
				'alg_wc_custom_order_numbers_counter'      => get_option( 'alg_wc_custom_order_numbers_counter' ),
				'alg_wc_custom_order_numbers_counter_reset_enabled' => get_option( 'alg_wc_custom_order_numbers_counter_reset_enabled' ),
				'alg_wc_custom_order_numbers_counter_reset_counter_value' => get_option( 'alg_wc_custom_order_numbers_counter_reset_counter_value' ),
				'alg_wc_custom_order_numbers_prefix'       => get_option( 'alg_wc_custom_order_numbers_prefix' ),
				'alg_wc_custom_order_numbers_order_tracking_enabled' => get_option( 'alg_wc_custom_order_numbers_order_tracking_enabled' ),
				'alg_wc_custom_order_numbers_hide_menu_for_roles' => get_option( 'alg_wc_custom_order_numbers_hide_menu_for_roles' ),
				'alg_wc_custom_order_numbers_hide_tab_for_roles' => get_option( 'alg_wc_custom_order_numbers_hide_tab_for_roles' ),
				'alg_wc_custom_order_numbers_settings_to_apply' => get_option( 'alg_wc_custom_order_numbers_settings_to_apply' ),
			);

			return wp_json_encode( $global_settings );

		}

		/**
		 * Send the total count for orders which have been assigned custom numbers.
		 *
		 * @since 1.3.0
		 */
		public static function con_lite_get_custom_order_numbers_count() {

			global $wpdb;

			$orders_count = $wpdb->get_var( $wpdb->prepare( 'SELECT count(post_id) FROM ' . $wpdb->prefix . 'postmeta where meta_key = %s', '_alg_wc_custom_order_number' ) ); //phpcs:ignore
			return $orders_count;
		}

	}

endif;

$con_data_tracking = new Con_Lite_Data_Tracking();
