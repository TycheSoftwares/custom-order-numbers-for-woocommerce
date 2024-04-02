<?php
/**
 * Custom Order Numbers for WooCommerce - Core Class
 *
 * @version 1.2.2
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-Order-Numbers-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers_Core' ) ) :

	/**
	 * Class for the Core functions.
	 */
	class Alg_WC_Custom_Order_Numbers_Core {

		/**
		 * Constructor.
		 *
		 * @version 1.1.1
		 * @since   1.0.0
		 * @todo    [feature] (maybe) prefix / suffix per order (i.e. different prefix / suffix for different orders)
		 */
		public function __construct() {
			if ( 'yes' === get_option( 'alg_wc_custom_order_numbers_enabled', 'yes' ) ) {
				add_filter( 'woocommerce_update_order', array( $this, 'add_new_order_number' ), PHP_INT_MAX, 2 );
				add_action( 'woocommerce_new_order', array( $this, 'add_new_order_number' ), 11 );
				add_filter( 'woocommerce_order_number', array( $this, 'display_order_number' ), PHP_INT_MAX, 2 );
				add_action( 'admin_notices', array( $this, 'alg_custom_order_numbers_update_admin_notice' ) );
				add_action( 'admin_notices', array( $this, 'alg_custom_order_numbers_update_success_notice' ) );
				// Add a recurring As action.
				add_action( 'admin_init', array( $this, 'alg_custom_order_numbers_add_recurring_action' ) );
				add_action( 'admin_init', array( $this, 'alg_custom_order_numbers_stop_recurring_action' ) );
				add_action( 'alg_custom_order_numbers_update_old_custom_order_numbers', array( $this, 'alg_custom_order_numbers_update_old_custom_order_numbers_callback' ) );
				// Include JS script for the notice.
				add_action( 'admin_enqueue_scripts', array( $this, 'alg_custom_order_numbers_setting_script' ) );
				add_action( 'wp_ajax_alg_custom_order_numbers_admin_notice_dismiss', array( $this, 'alg_custom_order_numbers_admin_notice_dismiss' ) );
				add_action( 'woocommerce_settings_save_alg_wc_custom_order_numbers', array( $this, 'woocommerce_settings_save_alg_wc_custom_order_numbers_callback' ), PHP_INT_MAX );
				if ( 'yes' === get_option( 'alg_wc_custom_order_numbers_order_tracking_enabled', 'yes' ) ) {
					add_action( 'init', array( $this, 'alg_remove_tracking_filter' ) );
					add_filter( 'woocommerce_shortcode_order_tracking_order_id', array( $this, 'add_order_number_to_tracking' ), PHP_INT_MAX );
				}
				add_filter( 'pre_update_option_alg_wc_custom_order_numbers_settings_to_apply', array( &$this, 'pre_alg_wc_custom_order_numbers_settings_to_apply' ), 10, 2 );
				add_action( 'woocommerce_shop_order_search_fields', array( $this, 'search_by_custom_number' ) );
				add_filter( 'woocommerce_order_table_search_query_meta_keys', array( $this, 'search_by_custom_number' ) );
				add_action( 'admin_menu', array( $this, 'add_renumerate_orders_tool' ), PHP_INT_MAX );
				if ( 'yes' === apply_filters( 'alg_wc_custom_order_numbers', 'no', 'manual_counter_value' ) ) {
					add_action( 'add_meta_boxes', array( $this, 'add_order_number_meta_box' ) );
					add_action( 'save_post_shop_order', array( $this, 'save_order_number_meta_box' ), PHP_INT_MAX, 2 );
				}

				// check if subscriptions is enabled.
				if ( in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
					add_action( 'woocommerce_checkout_subscription_created', array( $this, 'update_custom_order_meta' ), PHP_INT_MAX, 1 );
					add_filter( 'wcs_renewal_order_created', array( $this, 'remove_order_meta_renewal' ), PHP_INT_MAX, 2 );
					// To unset the CON meta key at the time of renewal of subscription, so that renewal orders don't have duplicate order numbers.
					add_filter( 'wcs_renewal_order_meta', array( $this, 'remove_con_metakey_in_wcs_order_meta' ), 10, 3 );
				}
				add_filter( 'pre_update_option_alg_wc_custom_order_numbers_prefix', array( $this, 'pre_alg_wc_custom_order_numbers_prefix' ), 10, 2 );
				add_action( 'admin_init', array( $this, 'alg_custom_order_number_old_orders_without_meta_key' ) );
				add_action( 'admin_init', array( $this, 'alg_custom_order_numbers_add_recurring_action_to_add_meta_key' ) );
				add_action( 'alg_custom_order_numbers_update_meta_key_in_old_con', array( $this, 'alg_custom_order_numbers_update_meta_key_in_old_con_callback' ) );
				add_action( 'wp_ajax_alg_custom_order_numbers_admin_meta_key_notice_dismiss', array( $this, 'alg_custom_order_numbers_admin_meta_key_notice_dismiss' ) );
				add_action( 'alg_wc_update_orders_prefix_con', array( $this, 'alg_wc_webhook_after_cutoff_con' ) );
			}
		}

		/**
		 * Enqueue JS script for showing fields as per the changes made in the settings.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public static function alg_custom_order_numbers_setting_script() {
			$plugin_url       = plugins_url() . '/custom-order-numbers-for-woocommerce';
			$numbers_instance = alg_wc_custom_order_numbers();
			wp_register_script(
				'jquery-ui-datepicker',
				WC()->plugin_url() . '/assets/js/admin/ui-datepicker.js',
				'',
				$numbers_instance->version,
				false
			);
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_script(
				'con_dismiss_notice',
				$plugin_url . '/includes/js/con-dismiss-notice.js',
				'',
				$numbers_instance->version,
				false
			);
			wp_localize_script(
				'con_dismiss_notice',
				'con_dismiss_param',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'dismissed' ),
				)
			);
			wp_register_script(
				'tyche',
				$plugin_url . '/includes/js/tyche.js',
				array( 'jquery' ),
				$numbers_instance->version,
				true
			);
			wp_enqueue_script( 'tyche' );
		}
		/**
		 * Check if HPOS is enabled or not.
		 *
		 * @since 1.8.0
		 * return boolean true if enabled else false
		 */
		public function con_wc_hpos_enabled() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Function to save the time when the Apply to new order setting is saved.
		 *
		 * @param string $new_value New setting value which is selected.
		 * @param string $old_value Old setting value which is saved in the database.
		 * @version 1.5.0
		 * @since   1.5.0
		 */
		public function pre_alg_wc_custom_order_numbers_settings_to_apply( $new_value, $old_value ) {
			if ( $new_value !== $old_value ) {
				if ( 'new_order' === $new_value ) {
					$current_time = current_time( 'timestamp' );
					update_option( 'alg_custom_order_numbers_new_order_time', $current_time );
				}
			}
			return $new_value;
		}

		/**
		 * Function to show the admin notice to update the old CON meta key in the database when the plugin is updated.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public static function alg_custom_order_numbers_update_admin_notice() {
			global $current_screen;
			$ts_current_screen = get_current_screen();
			// Return when we're on any edit screen, as notices are distracting in there.
			if ( ( method_exists( $ts_current_screen, 'is_block_editor' ) && $ts_current_screen->is_block_editor() ) || ( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) ) {
				return;
			}
			wp_nonce_field( 'dismissed_nonce', 'dismissed' );
			if ( 'yes' === get_option( 'alg_custom_order_numbers_show_admin_notice', '' ) ) {
				if ( '' === get_option( 'alg_custom_order_numbers_update_database', '' ) ) {
					?>
					<div class=''>
						<div class="con-lite-message notice notice-info" style="position: relative;">
							<p style="margin: 10px 0 10px 10px; font-size: medium;">
								<?php
									echo esc_html_e( 'From version 1.3.0, you can now search the orders by custom order numbers on the Orders page. In order to make the previous orders with custom order numbers searchable on Orders page, we need to update the database. Please click the "Update Now" button to do this. The database update process will run in the background.', 'custom-order-numbers-for-woocommerce' );
								?>
							</p>
							<p class="submit" style="margin: -10px 0 10px 10px;">
								<a class="button-primary button button-large" id="con-lite-update" href="edit.php?post_type=shop_order&action=alg_custom_order_numbers_update_old_con_in_database"><?php esc_html_e( 'Update Now', 'custom-order-numbers-for-woocommerce' ); ?></a>
							</p>
						</div>
					</div>
					<?php
				}
			}
			if ( 'yes' !== get_option( 'alg_custom_order_numbers_no_meta_admin_notice', '' ) ) {
				if ( 'yes' === get_option( 'alg_custom_order_number_old_orders_to_update_meta_key', '' ) ) {
					if ( '' === get_option( 'alg_custom_order_numbers_update_meta_key_in_database', '' ) ) {
						?>
						<div class=''>
							<div class="con-lite-message notice notice-info" style="position: relative;">
								<p style="margin: 10px 0 10px 10px; font-size: medium;">
									<?php
										echo esc_html_e( 'In order to make the previous orders searchable on Orders page where meta key of the custom order number is not present, we need to update the database. Please click the "Update Now" button to do this. The database update process will run in the background.', 'custom-order-numbers-for-woocommerce' );
									?>
								</p>
								<p class="submit" style="margin: -10px 0 10px 10px;">
									<a class="button-primary button button-large" id="con-lite-update" href="edit.php?post_type=shop_order&action=alg_custom_order_numbers_update_old_con_with_meta_key"><?php esc_html_e( 'Update Now', 'custom-order-numbers-for-woocommerce' ); ?></a>
								</p>
							</div>
						</div>
						<?php
					}
				}
			}
		}

		/**
		 * Function to add a scheduled action when Update now button is clicked in admin notice.AS will run every 5 mins and will run the script to update the CON meta value in old orders.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public function alg_custom_order_numbers_add_recurring_action() {
			if ( isset( $_REQUEST['action'] ) && 'alg_custom_order_numbers_update_old_con_in_database' === $_REQUEST['action'] ) { // phpcs:ignore
				update_option( 'alg_custom_order_numbers_update_database', 'yes' );
				$current_time = current_time( 'timestamp' ); // phpcs:ignore
				update_option( 'alg_custom_order_numbers_time_of_update_now', $current_time );
				if ( function_exists( 'as_next_scheduled_action' ) ) { // Indicates that the AS library is present.
					as_schedule_recurring_action( time(), 300, 'alg_custom_order_numbers_update_old_custom_order_numbers' );
				}
				wp_safe_redirect( admin_url( 'edit.php?post_type=shop_order' ) );
				exit;
			}
		}

		/**
		 * Function to add a scheduled action when Update now button is clicked in admin notice.AS will run every 5 mins and will run the script to add the meta key of CON in old orders where it is missing.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public function alg_custom_order_numbers_add_recurring_action_to_add_meta_key() {
			if ( isset( $_REQUEST['action'] ) && 'alg_custom_order_numbers_update_old_con_with_meta_key' === $_REQUEST['action'] ) { // phpcs:ignore
				update_option( 'alg_custom_order_numbers_update_meta_key_in_database', 'yes' );
				$current_time = current_time( 'timestamp' ); // phpcs:ignore
				update_option( 'alg_custom_order_numbers_meta_key_time_of_update_now', $current_time );
				if ( function_exists( 'as_next_scheduled_action' ) ) { // Indicates that the AS library is present.
					as_schedule_recurring_action( time(), 300, 'alg_custom_order_numbers_update_meta_key_in_old_con' );
				}
				wp_safe_redirect( admin_url( 'edit.php?post_type=shop_order' ) );
				exit;
			}
		}

		/**
		 * Callback function for the AS to run the script to update the CON meta value for the old orders.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public function alg_custom_order_numbers_update_old_custom_order_numbers_callback() {
			$args        = array(
				'post_type'      => 'shop_order',
				'return'         => 'ids',
				'posts_per_page' => -1, // phpcs:ignore
				'post_status'    => 'any',
				'meta_query'     => array( // phpcs:ignore
					'relation' => 'AND',
					array(
						'key'     => '_alg_wc_custom_order_number',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_alg_wc_custom_order_number_updated',
						'compare' => 'NOT EXISTS',
					),
				),
			);
			$loop_orders = wc_get_orders( $args );
			if ( count( $loop_orders ) <= 0 ) {
				update_option( 'alg_custom_order_numbers_no_old_orders_to_update', 'yes' );
				return;
			}
			foreach ( $loop_orders->posts as $order_ids ) {
				$order_id = $order_ids->ID;
				if ( $this->con_wc_hpos_enabled() ) {
					$order_number_meta = get_meta( '_alg_wc_custom_order_number' );
				} else {
					$order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
				}
				if ( '' === $order_number_meta ) {
					$order_number_meta = $order_id;
				}
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order                 = wc_get_order( $order_id );
				$order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
				$time                  = get_option( 'alg_custom_order_numbers_time_of_update_now', '' );
				if ( $order_timestamp > $time ) {
					return;
				}
				$con_order_number = apply_filters(
					'alg_wc_custom_order_numbers',
					sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
					'value',
					array(
						'order_timestamp'   => $order_timestamp,
						'order_number_meta' => $order_number_meta,
					)
				);
				if ( $this->con_wc_hpos_enabled() ) {
					$order->update_meta_data( '_alg_wc_full_custom_order_number', $con_order_number );
					$order->update_meta_data( '_alg_wc_custom_order_number_updated', 1 );
					$order->save();
				} else {
					update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $con_order_number );
					update_post_meta( $order_id, '_alg_wc_custom_order_number_updated', 1 );
				}
			}
			$loop_old_orders = $this->alg_custom_order_number_old_orders_without_meta_key_data();
			if ( '' === $loop_old_orders ) {
				update_option( 'alg_custom_order_numbers_no_old_orders_to_update', 'yes' );
				return;
			}
			foreach ( $loop_old_orders->posts as $order_ids ) {
				$order_id              = $order_ids->ID;
				$order_number_meta     = $order_id;
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order                 = wc_get_order( $order_id );
				$order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
				$time                  = get_option( 'alg_custom_order_numbers_meta_key_time_of_update_now', '' );
				if ( $order_timestamp > $time ) {
					return;
				}
				$con_order_number = apply_filters(
					'alg_wc_custom_order_numbers',
					sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
					'value',
					array(
						'order_timestamp'   => $order_timestamp,
						'order_number_meta' => $order_number_meta,
					)
				);
				if ( $this->con_wc_hpos_enabled() ) {
					$order->update_meta_data( '_alg_wc_full_custom_order_number', $con_order_number );
					$order->update_meta_data( '_alg_wc_custom_order_number_meta_key_updated', 1 );
					$order->save();
				} else {
					update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $con_order_number );
					update_post_meta( $order_id, '_alg_wc_custom_order_number_meta_key_updated', 1 );
				}
			}
			if ( 10000 > count( $loop_orders->posts ) && 500 > count( $loop_old_orders->posts ) ) {
				update_option( 'alg_custom_order_numbers_no_old_orders_to_update', 'yes' );
			}
		}

		/**
		 * Callback function for the AS to run the script to add the CON meta key for the old orders where it is missing.
		 */
		public function alg_custom_order_numbers_update_meta_key_in_old_con_callback() {
			$loop_orders = $this->alg_custom_order_number_old_orders_without_meta_key_data();
			if ( '' === $loop_orders ) {
				update_option( 'alg_custom_order_number_no_old_con_without_meta_key', 'yes' );
				return;
			}
			foreach ( $loop_orders->posts as $order_ids ) {
				$order_id              = $order_ids->ID;
				$order_number_meta     = $order_id;
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order                 = wc_get_order( $order_id );
				$order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
				$time                  = get_option( 'alg_custom_order_numbers_meta_key_time_of_update_now', '' );
				if ( $order_timestamp > $time ) {
					return;
				}
				$con_order_number = apply_filters(
					'alg_wc_custom_order_numbers',
					sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
					'value',
					array(
						'order_timestamp'   => $order_timestamp,
						'order_number_meta' => $order_number_meta,
					)
				);
				if ( $this->con_wc_hpos_enabled() ) {
					$order->update_meta_data( '_alg_wc_full_custom_order_number', $con_order_number );
					$order->update_meta_data( '_alg_wc_custom_order_number_meta_key_updated', 1 );
					$order->save();
				} else {
					update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $con_order_number );
					update_post_meta( $order_id, '_alg_wc_custom_order_number_meta_key_updated', 1 );
				}
			}
			if ( 500 > count( $loop_orders->posts ) ) {
				update_option( 'alg_custom_order_number_no_old_con_without_meta_key', 'yes' );
			}
		}

		/**
		 * Function to get the old orders where CON meta key is missing.
		 */
		public function alg_custom_order_number_old_orders_without_meta_key() {
			if ( 'yes' !== get_option( 'alg_custom_order_number_no_old_con_without_meta_key', '' ) && 'yes' !== get_option( 'alg_custom_order_number_no_old_orders_to_update_meta_key', '' ) ) {
				$args        = array(
					'post_type'      => 'shop_order',
					'posts_per_page' => 1, // phpcs:ignore
					'post_status'    => 'any',
					'meta_query'     => array( // phpcs:ignore
						'relation' => 'AND',
						array(
							'key'     => '_alg_wc_custom_order_number',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_alg_wc_custom_order_number_meta_key_updated',
							'compare' => 'NOT EXISTS',
						),
					),
				);
				$loop_orders = new WP_Query( $args );
				update_option( 'alg_custom_order_number_no_old_orders_to_update_meta_key', 'yes' );
				if ( ! $loop_orders->have_posts() ) {
					return '';
				} else {
					update_option( 'alg_custom_order_number_old_orders_to_update_meta_key', 'yes' );
					return $loop_orders;
				}
			}
		}

		/**
		 * Function to get the old orders data where CON meta key is missing.
		 */
		public function alg_custom_order_number_old_orders_without_meta_key_data() {
			$args        = array(
				'post_type'      => 'shop_order',
				'posts_per_page' => 500, // phpcs:ignore
				'post_status'    => 'any',
				'meta_query'     => array( // phpcs:ignore
					'relation' => 'AND',
					array(
						'key'     => '_alg_wc_custom_order_number',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_alg_wc_custom_order_number_meta_key_updated',
						'compare' => 'NOT EXISTS',
					),
				),
			);
			$loop_orders = new WP_Query( $args );
			if ( ! $loop_orders->have_posts() ) {
				return '';
			} else {
				return $loop_orders;
			}
		}

		/**
		 * Stop AS when there are no old orders left to update the CON meta key.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public static function alg_custom_order_numbers_stop_recurring_action() {
			if ( 'yes' === get_option( 'alg_custom_order_numbers_no_old_orders_to_update', '' ) ) {
				as_unschedule_all_actions( 'alg_custom_order_numbers_update_old_custom_order_numbers' );
			}
			if ( 'yes' === get_option( 'alg_custom_order_number_no_old_con_without_meta_key', '' ) ) {
				as_unschedule_all_actions( 'alg_custom_order_numbers_update_meta_key_in_old_con' );
			}
		}

		/**
		 * Function to show the Success Notice when all the old orders CON meta value are updated.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public function alg_custom_order_numbers_update_success_notice() {
			if ( 'yes' === get_option( 'alg_custom_order_numbers_no_old_orders_to_update', '' ) ) {
				if ( 'dismissed' !== get_option( 'alg_custom_order_numbers_success_notice', '' ) ) {
					?>
					<div>
						<div class="con-lite-message con-lite-success-message notice notice-success is-dismissible" style="position: relative;">
							<p>
								<?php
									echo esc_html_e( 'Database updated successfully. In addition to new orders henceforth, you can now also search the old orders on Orders page with the custom order numbers.', 'custom-order-numbers-for-woocommerce' );
								?>
							</p>
						</div>
					</div>
					<?php
				}
			}
			if ( 'yes' !== get_option( 'alg_custom_order_numbers_no_meta_admin_notice', '' ) ) {
				if ( 'yes' === get_option( 'alg_custom_order_number_no_old_con_without_meta_key', '' ) ) {
					if ( 'dismissed' !== get_option( 'alg_custom_order_numbers_success_notice_for_meta_key', '' ) ) {
						?>
						<div>
							<div class="con-lite-message con-lite-meta-key-success-message notice notice-success is-dismissible" style="position: relative;">
								<p>
									<?php
										echo esc_html_e( 'Database updated successfully. In addition to new orders henceforth, you can now also search the old orders on Orders page with the custom order numbers.', 'custom-order-numbers-for-woocommerce' );
									?>
								</p>
							</div>
						</div>
						<?php
					}
				}
			}
		}

		/**
		 * Function to dismiss the admin notice.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public function alg_custom_order_numbers_admin_notice_dismiss() {
			check_ajax_referer( 'dismissed', 'security' );
			$admin_choice = isset( $_POST['admin_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_choice'] ) ) : ''; // phpcs:ignore
			update_option( 'alg_custom_order_numbers_success_notice', $admin_choice );
		}

		/**
		 * Function to dismiss the admin notice.
		 */
		public function alg_custom_order_numbers_admin_meta_key_notice_dismiss() {
			check_ajax_referer( 'dismissed', 'security' );
			$admin_choice = isset( $_POST['alg_admin_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['alg_admin_choice'] ) ) : ''; // phpcs:ignore
			update_option( 'alg_custom_order_numbers_success_notice_for_meta_key', $admin_choice );
		}

		/**
		 * Function to update the prefix in the databse when settings are saved.
		 *
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public function woocommerce_settings_save_alg_wc_custom_order_numbers_callback() {
			if ( '1' === get_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed' ) ) {
				as_schedule_single_action( time() + 10, 'alg_wc_update_orders_prefix_con' );
			}
		}

		/**
		 * Function to update the prefix in the databse when settings are saved.
		 *
		 * @version 1.5.1
		 * @since   1.5.1
		 */
		public function alg_wc_webhook_after_cutoff_con() {
				$args        = array(
					'post_type'      => 'shop_order',
					'post_status'    => 'any',
					'posts_per_page' => -1,
				);
				$loop_orders = wc_get_orders( $args );
				if ( count( $loop_orders ) <= 0 ) {
					update_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed', '' );
					return;
				}
				foreach ( $loop_orders as $order_ids ) {
					$order_id = $order_ids->get_id();
					$order    = wc_get_order( $order_id );
					if ( $this->con_wc_hpos_enabled() ) {
						$order_number_meta = $order->get_meta( '_alg_wc_custom_order_number' );
					} else {
						$order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
					}
					if ( '' === $order_number_meta ) {
						$order_number_meta = $order_id;
					}
					$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
					$order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
					$full_order_number     = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $order_number_meta,
						)
					);
					$apply_settings        = get_option( 'alg_wc_custom_order_numbers_settings_to_apply', 'all_orders' );
					$order_con             = false;
					if ( 'new_order' === $apply_settings ) {
						update_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed', '' );
						return;
					} elseif ( 'date' === $apply_settings ) {
						$date = strtotime( get_option( 'alg_wc_custom_order_numbers_settings_to_apply_from_date' ) );
						if ( $order_timestamp >= $date ) {
							$order_con = true;
						}
					} elseif ( 'order_id' === $apply_settings ) {
						$saved_order_id = get_option( 'alg_wc_custom_order_numbers_settings_to_apply_from_order_id', '' );
						if ( $order_id >= $saved_order_id ) {
							$order_con = true;
						}
					} else {
						$order_con = true;
					}
					if ( $order_con ) {
						$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_order_number );
						$order->save();
					}
					update_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed', '' );
				}
		}

		/**
		 * Maybe_reset_sequential_counter.
		 *
		 * @param string $current_order_number - Current custom Order Number.
		 * @param int    $order_id - WC Order ID.
		 *
		 * @version 1.2.2
		 * @since   1.1.2
		 * @todo    [dev] use MySQL transaction
		 */
		public function maybe_reset_sequential_counter( $current_order_number, $order_id ) {

			$reset_period = get_option( 'alg_wc_custom_order_numbers_counter_reset_enabled', 'no' );
			if ( 'no' !== $reset_period ) {
				$previous_order_date   = get_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', 0 );
				$order                 = wc_get_order( $order_id );
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order_date            = ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() );
				$current_order_date    = strtotime( $order_date );

				if ( ! $current_order_date || '' === $current_order_date ) {
					$current_order_date = current_time( 'timestamp' );
				}

				update_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', $current_order_date );
				if ( 0 != $previous_order_date ) {
					$do_reset = false;
					switch ( $reset_period ) {
						case 'daily':
							$do_reset = (
							date( 'Y', $current_order_date ) != date( 'Y', $previous_order_date ) || //phpcs:ignore
							date( 'm', $current_order_date ) != date( 'm', $previous_order_date ) || //phpcs:ignore
							date( 'd', $current_order_date ) != date( 'd', $previous_order_date ) //phpcs:ignore
							);
							break;
						case 'monthly':
							$do_reset = (
							date( 'Y', $current_order_date ) != date( 'Y', $previous_order_date ) || //phpcs:ignore
							date( 'm', $current_order_date ) != date( 'm', $previous_order_date ) //phpcs:ignore
							);
							break;
						case 'yearly':
							$do_reset = (
							date( 'Y', $current_order_date ) != date( 'Y', $previous_order_date ) //phpcs:ignore
							);
							break;
					}
					if ( $do_reset ) {
						return get_option( 'alg_wc_custom_order_numbers_counter_reset_counter_value', 1 );
					}
				}
			}
			return $current_order_number;
		}

		/**
		 * Save_order_number_meta_box.
		 *
		 * @param int      $post_id - Order ID.
		 * @param WC_Order $post - Post Object.
		 * @version 1.1.1
		 * @since   1.1.1
		 */
		public function save_order_number_meta_box( $post_id, $post ) {
			if ( ! isset( $_POST['alg_wc_custom_order_numbers_meta_box'] ) && ! isset( $_POST['meta_box'] ) && !wp_verify_nonce( $_POST['meta_box'], 'create_order_number_meta_box' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return;
			}

			if ( isset( $_POST['alg_wc_custom_order_number'] ) && isset( $_POST['meta_box'] ) && wp_verify_nonce( $_POST['meta_box'], 'create_order_number_meta_box' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order                 = wc_get_order( $post_id );
				$order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
				$current_order_number  = '';
				if ( isset( $_POST['alg_wc_custom_order_number'] ) ) { // phpcs:ignore
					$current_order_number = sanitize_text_field( wp_unslash( $_POST['alg_wc_custom_order_number'] ) ); // phpcs:ignore
				}
				$full_custom_order_number = apply_filters(
					'alg_wc_custom_order_numbers',
					sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $current_order_number ),
					'value',
					array(
						'order_timestamp'   => $order_timestamp,
						'order_number_meta' => $current_order_number,
					)
				);
				if ( $this->con_wc_hpos_enabled() ) {
					$order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
					$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
					$order->save();
				} else {
					update_post_meta( $post_id, '_alg_wc_custom_order_number', $current_order_number );
					update_post_meta( $post_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
				}
			}
		}

		/**
		 * Add_order_number_meta_box.
		 *
		 * @version 1.1.1
		 * @since   1.1.1
		 */
		public function add_order_number_meta_box() {
			wp_nonce_field( 'create_order_number_meta_box', 'meta_box' );
			if ( $this->con_wc_hpos_enabled() ) {
				add_meta_box(
					'alg-wc-custom-order-numbers-meta-box',
					__( 'Order Number', 'custom-order-numbers-for-woocommerce' ),
					array( $this, 'create_order_number_meta_box' ),
					wc_get_page_screen_id( 'shop-order' ),
					'side',
					'low'
				);

			} else {
				add_meta_box(
					'alg-wc-custom-order-numbers-meta-box',
					__( 'Order Number', 'custom-order-numbers-for-woocommerce' ),
					array( $this, 'create_order_number_meta_box' ),
					'shop_order',
					'side',
					'low'
				);
			}
		}

		/**
		 * Create_order_number_meta_box.
		 *
		 * @version 1.1.1
		 * @since   1.1.1
		 */
		public function create_order_number_meta_box() {
			if ( $this->con_wc_hpos_enabled() ) {
				$order = wc_get_order( get_the_ID() );
				$meta  = $order->get_meta( '_alg_wc_custom_order_number' );
			} else {
				$meta = get_post_meta( get_the_ID(), '_alg_wc_custom_order_number', true );
			}
			?>
			<input type="number" name="alg_wc_custom_order_number" style="width:100%;" value="<?php echo esc_attr( $meta ); ?>">
			<input type="hidden" name="alg_wc_custom_order_numbers_meta_box">
			<?php
		}

		/**
		 * Add menu item.
		 *
		 * @version 1.2.2
		 * @since   1.0.0
		 */
		public function add_renumerate_orders_tool() {
			$hide_for_roles = get_option( 'alg_wc_custom_order_numbers_hide_menu_for_roles', array() );
			if ( ! empty( $hide_for_roles ) ) {
				$user       = wp_get_current_user();
				$user_roles = (array) $user->roles;
				$intersect  = array_intersect( $hide_for_roles, $user_roles );
				if ( ! empty( $intersect ) ) {
					return;
				}
			}
			add_submenu_page(
				'woocommerce',
				__( 'Renumerate Orders', 'custom-order-numbers-for-woocommerce' ),
				__( 'Renumerate Orders', 'custom-order-numbers-for-woocommerce' ),
				'manage_woocommerce',
				'alg-wc-renumerate-orders-tools',
				array( $this, 'create_renumerate_orders_tool' )
			);
		}

		/**
		 * Add Renumerate Orders tool to WooCommerce menu (the content).
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 * @todo    [dev] more results
		 */
		public function create_renumerate_orders_tool() {
			$last_renumerated_order = 0;
			?>
			<div class="wrap">
			<h1><?php esc_html_e( 'Renumerate Orders', 'custom-order-numbers-for-woocommerce' ); ?></h1>
			<?php
			if ( isset( $_POST['alg_renumerate_orders'] ) && isset( $_POST['renumerate_orders'] ) && wp_verify_nonce( $_POST['renumerate_orders'], 'alg_renumerate_orders' ) ) {  // phpcs:ignore WordPress.Security.NonceVerification
				$total_renumerated_orders = $this->renumerate_orders();
				$last_renumerated_order   = $total_renumerated_orders[1];
				$total_renumerated_orders = $total_renumerated_orders[0];
				?>
				<p>
					<div class="updated">
						<p><strong>
						<?php
						// translators: <number of orders> orders successfully renumerated.
						echo sprintf( esc_html__( '%d orders successfully renumerated!', 'custom-order-numbers-for-woocommerce' ), esc_attr( $total_renumerated_orders ) );
						?>
						</strong></p>
					</div>
				</p>
				<?php
			}
			?>
			<p>
				<?php
				echo sprintf(
					// translators: Settings Link.
					__( 'Plugin settings: <a href="%s">WooCommerce > Settings > Custom Order Numbers</a>.', 'custom-order-numbers-for-woocommerce' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					esc_url( admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_numbers' ) )
				);
				?>
			</p>
			<?php
			$next_order_number = ( 0 !== $last_renumerated_order ) ? ( $last_renumerated_order + 1 ) : get_option( 'alg_wc_custom_order_numbers_counter', 1 );
			?>
			<p><?php esc_html_e( 'Press the button below to renumerate all existing orders.', 'custom-order-numbers-for-woocommerce' ); ?></p>
			<?php
			if ( 'sequential' === get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' ) ) {
				?>
				<p>
				<?php
				// translators: First Order Number.
				echo sprintf( __( 'First order number will be <strong>%d</strong>.', 'custom-order-numbers-for-woocommerce' ), esc_attr( $next_order_number ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				</p>
				<?php
			}
			?>
			<form method="post" action="">
				<input class="button-primary" type="submit" name="alg_renumerate_orders" value="<?php esc_html_e( 'Renumerate orders', 'custom-order-numbers-for-woocommerce' ); ?>" onclick="return confirm('<?php echo esc_html__( 'Are you sure?', 'custom-order-numbers-for-woocommerce' ); ?>')">
				<?php wp_nonce_field( 'alg_renumerate_orders', 'renumerate_orders' ); ?>
			</form>
			</div>
			<?php
		}

		/**
		 * Renumerate orders function.
		 *
		 * @version 1.1.2
		 * @since   1.0.0
		 */
		public function renumerate_orders() {
			if ( 'sequential' === get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' ) && 'no' !== get_option( 'alg_wc_custom_order_numbers_counter_reset_enabled', 'no' ) ) {
				update_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', 0 );
			}
			$total_renumerated = 0;
			$last_renumerated  = 0;
			$offset            = 0;
			$block_size        = 512;
			while ( true ) {
				$args        = array(
					'type'    => array( 'shop_order', 'shop_subscription' ),
					'status'  => 'any',
					'limit'   => $block_size,
					'orderby' => 'date',
					'order'   => 'ASC',
					'offset'  => $offset,
					'return'  => 'ids',
				);
				$loop_orders = wc_get_orders( $args );
				if ( count( $loop_orders ) <= 0 ) {
					break;
				}
				foreach ( $loop_orders as $order_id ) {
					$last_renumerated = $this->add_order_number_meta( $order_id, true );
					$total_renumerated++;
				}
				$offset += $block_size;
			}
			return array( $total_renumerated, $last_renumerated );
		}

		/**
		 * Function search_by_custom_number.
		 *
		 * @param array $metakeys Array of the metakeys to search order numbers on shop order page.
		 * @version 1.3.0
		 * @since   1.3.0
		 */
		public function search_by_custom_number( $metakeys ) {
			$metakeys[] = '_alg_wc_full_custom_order_number';
			$metakeys[] = '_alg_wc_custom_order_number';
			return $metakeys;
		}

		/**
		 * Add_order_number_to_tracking.
		 *
		 * @param string $order_number - Custom Order Number.
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_order_number_to_tracking( $order_number ) {
			$offset     = 0;
			$block_size = 512;
			while ( true ) {
				$args = array(
					'post_type'      => 'shop_order',
					'post_status'    => 'any',
					'posts_per_page' => $block_size,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'offset'         => $offset,
					'fields'         => 'ids',
				);
				$loop = new WP_Query( $args );
				if ( ! $loop->have_posts() ) {
					break;
				}
				foreach ( $loop->posts as $order_id ) {
					$_order        = wc_get_order( $order_id );
					$_order_number = $this->display_order_number( $order_id, $_order );
					if ( $_order_number === $order_number ) {
						return $order_id;
					}
				}
				$offset += $block_size;
			}
			return $order_number;
		}

		/**
		 * Display order number.
		 *
		 * @param string $order_number - Custom Order Number.
		 * @param object $order - WC_Order object.
		 *
		 * @version 1.2.1
		 * @since   1.0.0
		 */
		public function display_order_number( $order_number, $order ) {
			$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
			$order_id              = ( $is_wc_version_below_3 ? $order->id : $order->get_id() );
			$order_timestamp       = ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() );
			$apply_settings_to     = get_option( 'alg_wc_custom_order_numbers_settings_to_apply', 'all_orders' );
			$con_wc_hpos_enabled   = $this->con_wc_hpos_enabled();
			$custom_number_set     = true;
			$apply_custom_numbers  = false;
			$full_custom_number    = true;
			if ( ! is_null( $order_timestamp ) ) {
				$order_timestamp = strtotime( $order_timestamp );
			}
			if ( 'yes' !== get_option( 'alg_custom_order_numbers_show_admin_notice', '' ) || 'yes' === get_option( 'alg_custom_order_numbers_no_old_orders_to_update', '' ) ) {
				// This code of block is added to update the meta key '_alg_wc_full_custom_order_number' in the subscription orders as the order numbers were getting changed after the database update.
				if ( $con_wc_hpos_enabled ) {
					$subscription_orders_updated = $order->get_meta( 'subscription_orders_updated' );
				} else {
					$subscription_orders_updated = get_post_meta( $order_id, 'subscription_orders_updated', true );
				}
				if ( 'yes' !== $subscription_orders_updated ) {
					if ( $con_wc_hpos_enabled ) {
						$post_type = OrderUtil::get_order_type( $order_id );
					} else {
						$post_type = get_post_type( $order_id );
					}
					if ( 'shop_subscription' === $post_type ) {
						if ( $con_wc_hpos_enabled ) {
							$order_number_meta = $order->get_meta( '_alg_wc_custom_order_number' );
						} else {
							$order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
						}
						if ( '' === $order_number_meta ) {
							$order_number_meta = $order_id;
						}
						$order_number = apply_filters(
							'alg_wc_custom_order_numbers',
							sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
							'value',
							array(
								'order_timestamp'   => $order_timestamp,
								'order_number_meta' => $order_number_meta,
							)
						);
						if ( $con_wc_hpos_enabled ) {
							$order->update_meta_data( '_alg_wc_full_custom_order_number', $order_number );
							$order->update_meta_data( 'subscription_orders_updated', 'yes' );
							$order->save();
						} else {
							update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $order_number );
							update_post_meta( $order_id, 'subscription_orders_updated', 'yes' );
						}
						return $order_number;
					}
				}
				if ( $con_wc_hpos_enabled ) {
					$order_number_meta = $order->get_meta( '_alg_wc_full_custom_order_number' );
				} else {
					$order_number_meta = get_post_meta( $order_id, '_alg_wc_full_custom_order_number', true );
				}
				// This code of block is added to update the meta key '_alg_wc_full_custom_order_number' in new orders which were placed after the update of v1.3.0 where counter type is set to order id.
				if ( $con_wc_hpos_enabled ) {
					$new_orders_updated = $order->get_meta( 'new_orders_updated' );
				} else {
					$new_orders_updated = get_post_meta( $order_id, 'new_orders_updated', true );
				}
				if ( 'yes' !== $new_orders_updated ) {
					$counter_type = get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' );
					if ( 'order_id' === $counter_type ) {
						$order_number_meta = $order_id;
						$order_number      = apply_filters(
							'alg_wc_custom_order_numbers',
							sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
							'value',
							array(
								'order_timestamp'   => $order_timestamp,
								'order_number_meta' => $order_number_meta,
							)
						);
						if ( $con_wc_hpos_enabled ) {
							$order->update_meta_data( '_alg_wc_full_custom_order_number', $order_number );
							$order->update_meta_data( 'new_orders_updated', 'yes' );
							$order->save();
						} else {
							update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $order_number );
							update_post_meta( $order_id, 'new_orders_updated', 'yes' );
						}
						return $order_number;
					}
				}
				if ( '' === $order_number_meta ) {
					$order_number_meta = $order_id;
					$order_number_meta = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $order_number_meta,
						)
					);
				}
				return $order_number_meta;
			} else {
				if ( $con_wc_hpos_enabled ) {
					$order_number_meta = $order->get_meta( '_alg_wc_full_custom_order_number' );
				} else {
					$order_number_meta = get_post_meta( $order_id, '_alg_wc_full_custom_order_number', true );
				}
				if ( '' === $order_number_meta ) {
					$order_number_meta  = $order_id;
					$full_custom_number = false;
					$custom_number_set  = false;
				}
				switch ( $apply_settings_to ) {
					case 'new_order':
					default:
					case '':
						if ( $custom_number_set ) {
							$apply_custom_numbers = true;
						}
						break;
					case 'order_id':
						$apply_from_order_id = get_option( 'alg_wc_custom_order_numbers_settings_to_apply_from_order_id', 0 );
						if ( 0 === $apply_from_order_id || '' == $apply_from_order_id || $order_id >= $apply_from_order_id ) { // phpcs:ignore
							$apply_custom_numbers = true;
						}
						break;
					case 'date':
						$apply_from_date = get_option( 'alg_wc_custom_order_numbers_settings_to_apply_from_date', '' );
						$date_created    = $order->get_date_created()->date( 'm/d/Y' );
						if ( '' == $apply_from_date || $date_created >= $apply_from_date ) { // phpcs:ignore
							$apply_custom_numbers = true;
						}
						break;
					case 'all_orders':
						$apply_custom_numbers = true;
						break;
				}
				if ( $apply_custom_numbers && ! $full_custom_number ) {
					$order_number_meta = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $order_number_meta,
						)
					);
				}
			}
			return $order_number_meta;
		}

		/**
		 * Add_new_order_number.
		 *
		 * @param int $order_id - Order ID.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_new_order_number( $order_id ) {
			$this->add_order_number_meta( $order_id, false );
		}

		/**
		 * Add/update order_number meta to order.
		 *
		 * @param int  $order_id - Order ID.
		 * @param bool $do_overwrite - Change the order number to a custom number.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function add_order_number_meta( $order_id, $do_overwrite ) {
			$con_wc_hpos_enabled = $this->con_wc_hpos_enabled();
			if ( $con_wc_hpos_enabled ) {
				if ( ! in_array( OrderUtil::get_order_type( $order_id ), array( 'shop_order', 'shop_subscription' ), true ) ) {
					return false;
				}
			}
			if ( ! $con_wc_hpos_enabled ) {
				if ( ! in_array( get_post_type( $order_id ), array( 'shop_order', 'shop_subscription' ), true ) ) {
					return false;
				}
			}
			$order = wc_get_order( $order_id );
			if ( true === $do_overwrite || '' ==  ( $con_wc_hpos_enabled ? $order->get_meta( '_alg_wc_custom_order_number' ) : get_post_meta( $order_id, '_alg_wc_custom_order_number', true ) ) ) { // phpcs:ignore
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				if ( $order ) {
					$order_timestamp = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
				} else {
					$order_timestamp = get_option( 'alg_custom_order_numbers_meta_key_time_of_update_now', '' );
				}
				$counter_type = get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' );
				if ( 'sequential' === $counter_type ) {
					// Using MySQL transaction, so in case of a lot of simultaneous orders in the shop - prevent duplicate sequential order numbers.
					global $wpdb;
					$wpdb->query( 'START TRANSACTION' ); //phpcs:ignore
					$wp_options_table = $wpdb->prefix . 'options';
					$result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . 'options` WHERE option_name = %s FOR UPDATE', 'alg_wc_custom_order_numbers_counter' ) ); //phpcs:ignore
					if ( null !== $result_select ) {
						$current_order_number     = $this->maybe_reset_sequential_counter( $result_select->option_value, $order_id );
						$result_update            = $wpdb->update( // phpcs:ignore
							$wp_options_table,
							array( 'option_value' => ( $current_order_number + 1 ) ),
							array( 'option_name' => 'alg_wc_custom_order_numbers_counter' )
						);
						$current_order_number_new = $current_order_number + 1;
						if ( null !== $result_update || $current_order_number_new === $result_select->option_value ) {
							$full_custom_order_number = apply_filters(
								'alg_wc_custom_order_numbers',
								sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $current_order_number ),
								'value',
								array(
									'order_timestamp'   => $order_timestamp,
									'order_number_meta' => $current_order_number,
								)
							);
							// all ok.
							$wpdb->query( 'COMMIT' ); //phpcs:ignore
							if ( $con_wc_hpos_enabled ) {
								$order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
								$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
								$order->save();
							} else {
								update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
								update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
							}
						} else {
							// something went wrong, Rollback.
							$wpdb->query( 'ROLLBACK' ); //phpcs:ignore
							return false;
						}
					} else {
						// something went wrong, Rollback.
						$wpdb->query( 'ROLLBACK' ); //phpcs:ignore
						return false;
					}
				} elseif ( 'hash_crc32' === $counter_type ) {
					$current_order_number     = sprintf( '%u', crc32( $order_id ) );
					$full_custom_order_number = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $current_order_number ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $current_order_number,
						)
					);
					if ( $con_wc_hpos_enabled ) {
						$order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
						$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
						$order->save();
					} else {
						update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
						update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
					}
				} else { // 'order_id'
					$current_order_number     = $order_id;
					$full_custom_order_number = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $current_order_number ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $current_order_number,
						)
					);
					if ( $con_wc_hpos_enabled ) {
						$order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
						$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
						$order->save();
					} else {
						update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
						update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
					}
				}
				return $current_order_number;
			}
			return false;
		}

		/**
		 * Updates the custom order number for a renewal order created
		 * using WC Subscriptions
		 *
		 * @param WC_Order $renewal_order - Order Object of the renewed order.
		 * @param object   $subscription - Subscription for which the order has been created.
		 * @return WC_Order $renewal_order
		 * @since 1.2.6
		 */
		public function remove_order_meta_renewal( $renewal_order, $subscription ) {
			$new_order_id = $renewal_order->get_id();
			// update the custom order number.
			$this->add_order_number_meta( $new_order_id, true );
			return $renewal_order;
		}

		/**
		 * Updates the custom order number for the WC Subscription
		 *
		 * @param object $subscription - Subscription for which the order has been created.
		 * @since 1.2.6
		 */
		public function update_custom_order_meta( $subscription ) {

			$subscription_id = $subscription->get_id();
			// update the custom order number.
			$this->add_order_number_meta( $subscription_id, true );

		}

		/**
		 * Remove the WooCommerc filter which convers the order numbers to integers by removing the * * characters.
		 */
		public function alg_remove_tracking_filter() {
			remove_filter( 'woocommerce_shortcode_order_tracking_order_id', 'wc_sanitize_order_id' );
		}

		/**
		 * Function to unset the CON meta key at the time of renewal of subscription.
		 *
		 * @param Array  $meta Array of a meta key present in the subscription.
		 * @param Object $to_order  Order object.
		 * @param Objec  $from_order Subscription object.
		 */
		public function remove_con_metakey_in_wcs_order_meta( $meta, $to_order, $from_order ) {
			$to_order_id = $to_order->get_id();
			if ( $this->con_wc_hpos_enabled() ) {
				$from_order_type = OrderUtil::get_order_type( $from_order->get_id() );
			} else {
				$from_order_type = get_post_type( $from_order->get_id() );
			}
			if ( 0 === $to_order_id && 'shop_subscription' === $from_order_type ) {
				foreach ( $meta as $key => $value ) {
					if ( '_alg_wc_custom_order_number' === $value['meta_key'] ) {
						unset( $meta[ $key ] );
					}
					if ( '_alg_wc_full_custom_order_number' === $value['meta_key'] ) {
						unset( $meta[ $key ] );
					}
				}
			}
			return $meta;
		}

		/**
		 * Function to see if prefix value is changed or not.
		 *
		 * @param string $new_value New setting value which is selected.
		 * @param string $old_value Old setting value which is saved in the database.
		 */
		public function pre_alg_wc_custom_order_numbers_prefix( $new_value, $old_value ) {
			if ( $new_value !== $old_value ) {
				update_option( 'alg_wc_custom_order_numbers_prefix_suffix_changed', '1' );
			}
			return $new_value;
		}
	}

endif;

return new Alg_WC_Custom_Order_Numbers_Core();
