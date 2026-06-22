<?php
/**
 * Custom Order Numbers for WooCommerce - Core Class
 *
 * @version 1.2.2
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-order-numbers-for-WooCommerce
 */

namespace Tyche\CON;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

use Tyche\CON\Functions as CON_Functions;

if ( ! class_exists( 'Tyche\CON\Core' ) ) :
	/**
	 * Class Alg_WC_Custom_Order_Numbers_Core.
	 */
	class Core {

		/**
		 * Constructor.
		 *
		 * @version 1.1.1
		 * @since   1.0.0
		 * @todo    [feature] (maybe) prefix / suffix per order (i.e. different prefix / suffix for different orders)
		 */
		public function __construct() {
			if ( CON_Functions::get_setting( 'enabled', false ) ) {
				add_action( 'woocommerce_checkout_order_processed', array( $this, 'add_new_order_number' ), 10 );
				add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'add_new_order_number_block_checkout' ), 10 );
				add_filter( 'woocommerce_order_number', array( $this, 'display_order_number' ), PHP_INT_MAX, 2 );

				add_action( 'con_prefix_suffix_rules_updated', array( $this, 'on_rules_updated' ) );
				if ( CON_Functions::get_setting( 'order_tracking_enabled', true ) ) {
					add_action( 'init', array( $this, 'alg_remove_tracking_filter' ) );
					add_filter( 'woocommerce_shortcode_order_tracking_order_id', array( $this, 'add_order_number_to_tracking' ), PHP_INT_MAX );
				}

				add_action( 'alg_custom_order_numbers_weekly_reset_event', array( $this, 'alg_custom_order_numbers_weekly_reset_event_callback' ) );

				add_action( 'woocommerce_shop_order_search_fields', array( $this, 'search_by_custom_number' ) );
				add_filter( 'woocommerce_order_table_search_query_meta_keys', array( $this, 'search_by_custom_number' ) );

				// check if subscriptions is enabled.
				if ( in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
					add_action( 'woocommerce_checkout_subscription_created', array( $this, 'update_custom_order_meta' ), PHP_INT_MAX, 1 );
					add_filter( 'wcs_renewal_order_created', array( $this, 'remove_order_meta_renewal' ), PHP_INT_MAX, 1 );
					// To unset the CON meta key at the time of renewal of subscription, so that renewal orders don't have duplicate order numbers.
					add_filter( 'wcs_renewal_order_meta', array( $this, 'remove_con_metakey_in_wcs_order_meta' ), 10, 3 );
				}
				// Compatibility with Germanized for WooCommerce Plugin. Passing CON meta key in their search query when return form is submitted using CON.
				add_filter( 'woocommerce_gzd_return_request_customer_order_number_meta_key', array( $this, 'alg_wc_custom_order_numbers_gzd_return_request_meta_key' ), 10 );

				if ( is_admin() ) {
					add_action( 'woocommerce_new_order', array( $this, 'add_new_order_number_admin' ), 10 );
					add_action( 'woocommerce_process_shop_order_meta', array( $this, 'maybe_reapply_order_number_formatting_on_update' ), 99, 2 );
				}
				add_action( 'woocommerce_order_status_changed', array( $this, 'reapply_prefix_on_status_change' ), 99, 4 );
				add_action( 'woocommerce_rest_insert_shop_order_object', array( $this, 'add_new_order_number_inserted_rest_api' ), 10, 3 );

				add_filter( 'con_renumerate_orders',
					function() {
						return $this->renumerate_orders();
					}
				);
			}

			add_action( 'con_process_rules_batch', array( $this, 'process_rules_batch' ) );
			add_action( 'con_renumerate_batch', array( $this, 'process_renumerate_batch' ) );
			add_action( 'admin_notices', array( $this, 'maybe_show_batch_notice' ) );
			add_action( 'wp_ajax_con_dismiss_renumerate_complete', array( $this, 'dismiss_renumerate_complete' ) );
		}

		/**
		 * AJAX handler — deletes the renumeration complete transient when the notice is dismissed.
		 */
		public function dismiss_renumerate_complete() {
			check_ajax_referer( 'con_dismiss_renumerate_complete', 'nonce' );
			if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
				delete_transient( 'con_renumerate_complete' );
			}
			wp_die();
		}

		/**
		 * Display an admin notice while the rules batch is processing.
		 */
		public function maybe_show_batch_notice() {
			if ( get_transient( 'con_renumerate_complete' ) ) {
				echo '<div class="notice notice-success is-dismissible" id="con-renumerate-complete-notice"><p>'
					. esc_html__( 'Renumeration complete. All order numbers have been updated.', 'custom-order-numbers-for-woocommerce' )
					. '</p></div>';
				printf(
					'<script>
						jQuery( document ).on( "click", "#con-renumerate-complete-notice .notice-dismiss", function() {
							jQuery.post( ajaxurl, { action: "con_dismiss_renumerate_complete", nonce: "%s" } );
						} );
					</script>',
					esc_js( wp_create_nonce( 'con_dismiss_renumerate_complete' ) )
				);
			}
			if ( get_transient( 'con_renumerate_batch_in_progress' ) ) {
				echo '<div id="con-batch-notice" class="notice notice-info"><p>'
					. esc_html__( 'Custom order numbers are being renumbered in the background.', 'custom-order-numbers-for-woocommerce' )
					. '</p></div>';
			} elseif ( get_transient( 'con_rules_batch_in_progress' ) ) {
				echo '<div id="con-batch-notice" class="notice notice-info"><p>'
					. esc_html__( 'Custom order numbers are being updated in the background.', 'custom-order-numbers-for-woocommerce' )
					. '</p></div>';
			}
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
		 * Callback function to run the weekly AS.
		 */
		public function alg_custom_order_numbers_weekly_reset_event_callback() {
			$current_time = current_time( 'timestamp' ); // phpcs:ignore
			update_option( 'alg_custom_order_numbers_weekly_as_time', $current_time );
		}

		/**
		 * Update order numbers when the prefix/suffix is changed.
		 * Schedules batch processing via Action Scheduler instead of processing all orders inline.
		 */
		public function on_rules_updated() {
			$apply_settings = CON_Functions::get_setting( 'settings_to_apply', 'all_orders' );
			if ( 'new_order' === $apply_settings ) {
				return;
			}

			as_unschedule_all_actions( 'con_process_rules_batch' );
			set_transient( 'con_rules_batch_in_progress', true, HOUR_IN_SECONDS );
			as_schedule_single_action( time(), 'con_process_rules_batch', array( 1 ), 'con' );
		}

		/**
		 * Process a single batch of orders when applying updated prefix/suffix rules.
		 * Scheduled via Action Scheduler; reschedules itself until all orders are processed.
		 *
		 * @param int $page 1-based page number.
		 */
		public function process_rules_batch( $page ) {
			$batch_size     = 100;
			$apply_settings = CON_Functions::get_setting( 'settings_to_apply', 'all_orders' );
			$order_ids      = array();

			if ( CON_Functions::con_wc_hpos_enabled() ) {
				$order_ids = wc_get_orders(
					array(
						'limit'   => $batch_size,
						'page'    => $page,
						'type'    => 'shop_order',
						'status'  => 'any',
						'orderby' => 'ID',
						'order'   => 'ASC',
						'return'  => 'ids',
					)
				);
			} else {
				$query = new \WP_Query(
					array(
						'post_type'      => 'shop_order',
						'post_status'    => 'any',
						'posts_per_page' => $batch_size,
						'paged'          => $page,
						'order'          => 'ASC',
						'fields'         => 'ids',
					)
				);
				$order_ids = $query->posts;
			}

			if ( empty( $order_ids ) ) {
				delete_transient( 'con_rules_batch_in_progress' );
				return;
			}

			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					continue;
				}

				$order_con = false;

				if ( 'date' === $apply_settings ) {
					$from_date  = CON_Functions::get_setting( 'settings_to_apply_from_date' );
					$order_date = $order->get_date_created()->date( 'Y-m-d' );
					if ( $order_date >= $from_date ) {
						$order_con = true;
					}
				} elseif ( 'order_id' === $apply_settings ) {
					$saved_order_id = CON_Functions::get_setting( 'settings_to_apply_from_order_id', '' );
					if ( $order_id >= $saved_order_id ) {
						$order_con = true;
					}
				} else {
					$order_con = true;
				}

				if ( $order_con ) {
					$this->add_order_number_meta( $order_id, true );
				}

				unset( $order );
			}

			if ( count( $order_ids ) === $batch_size ) {
				as_schedule_single_action( time(), 'con_process_rules_batch', array( $page + 1 ), 'con' );
			} else {
				delete_transient( 'con_rules_batch_in_progress' );
			}
		}

		/**
		 * Sequential counter reset maybe_reset_sequential_counter.
		 *
		 * This method determines if the sequential counter should be reset based on the configured reset period and order dates.
		 *
		 * @param int $current_order_number The current order number being processed.
		 * @param int $order_id The ID of the order for which the sequential counter might be reset.
		 * @return int The potentially reset order number.
		 * @version 1.2.2
		 * @since   1.1.2
		 * @todo    [dev] use MySQL transaction
		 */
		public function maybe_reset_sequential_counter( $current_order_number, $order_id ) {
			if ( 'no' != ( $reset_period = CON_Functions::get_setting( 'counter_reset_enabled', 'no' ) ) ) { // phpcs:ignore
				$previous_order_date   = get_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', 0 );
				$order                 = wc_get_order( $order_id );
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order_date            = ( $order ? $order->get_date_created() : '' );
				$current_order_date    = strtotime( $order_date ? $order_date : '' );

				if ( ! $current_order_date || '' === $current_order_date ) {
					$current_order_date = current_time( 'timestamp' ); // phpcs:ignore
				}

				update_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', $current_order_date );
				if ( 0 != $previous_order_date ) { // phpcs:ignore
					$do_reset = false;
					switch ( $reset_period ) {
						case 'daily':
							$do_reset = (
								gmdate( 'Y', $current_order_date ) != gmdate( 'Y', $previous_order_date ) || gmdate( 'm', $current_order_date ) != gmdate( 'm', $previous_order_date ) || gmdate( 'd', $current_order_date ) != gmdate( 'd', $previous_order_date ) // phpcs:ignore
							);
							break;
						case 'monthly':
							$do_reset = (
								gmdate( 'Y', $current_order_date ) != gmdate( 'Y', $previous_order_date ) || gmdate( 'm', $current_order_date ) != gmdate( 'm', $previous_order_date ) // phpcs:ignore
							);
							break;
						case 'yearly':
							$do_reset = (
								gmdate( 'Y', $current_order_date ) != gmdate( 'Y', $previous_order_date ) // phpcs:ignore
							);
							break;
						case 'weekly':
							$current_order_time = current_time( 'timestamp' ); // phpcs:ignore
							// Time of Weekly AS runed.
							$time_of_as_called = get_option( 'alg_custom_order_numbers_weekly_as_time' );
							if ( '' !== $time_of_as_called && $current_order_time > $time_of_as_called ) {
								$do_reset = true;
								update_option( 'alg_custom_order_numbers_weekly_as_time', '' );
							}
							break;
					}
					$do_reset = apply_filters( 'alg_custom_order_numbers_do_reset_counter_value', $do_reset );
					if ( $do_reset ) {
						return CON_Functions::get_setting( 'counter_reset_counter_value', 1 );
					}
				}
			}
			return $current_order_number;
		}

		/**
		 * Renumerate orders function.
		 * Schedules async batch processing via Action Scheduler to avoid memory exhaustion on large stores.
		 *
		 * @version 1.2.3
		 * @since   1.0.0
		 */
		public function renumerate_orders() {
			if ( 'sequential' === CON_Functions::get_setting( 'counter_type', 'sequential' ) && 'no' != CON_Functions::get_setting( 'counter_reset_enabled', 'no' ) ) { // phpcs:ignore
				update_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', 0 );
			}

			as_unschedule_all_actions( 'con_renumerate_batch' );
			set_transient( 'con_renumerate_batch_in_progress', true, HOUR_IN_SECONDS * 2 );
			as_schedule_single_action( time(), 'con_renumerate_batch', array( 1 ), 'con' );

			return array( 'scheduled' => true );
		}

		/**
		 * Process a single batch of orders during renumeration.
		 * Scheduled via Action Scheduler; reschedules itself until all orders are processed.
		 *
		 * @param int $page 1-based page number.
		 */
		public function process_renumerate_batch( $page ) {
			$batch_size           = 100;
			$subscriptions_active = in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
			$order_types          = $subscriptions_active ? array( 'shop_order', 'shop_subscription' ) : array( 'shop_order' );
			$order_ids            = array();

			if ( CON_Functions::con_wc_hpos_enabled() ) {
				$order_ids = wc_get_orders(
					array(
						'type'    => $order_types,
						'status'  => 'any',
						'limit'   => $batch_size,
						'page'    => $page,
						'orderby' => 'date',
						'order'   => 'ASC',
						'return'  => 'ids',
					)
				);
			} else {
				$query = new \WP_Query(
					array(
						'post_type'      => $order_types,
						'post_status'    => 'any',
						'posts_per_page' => $batch_size,
						'paged'          => $page,
						'orderby'        => 'date',
						'order'          => 'ASC',
						'fields'         => 'ids',
					)
				);
				$order_ids = $query->posts;
			}

			if ( empty( $order_ids ) ) {
				delete_transient( 'con_renumerate_batch_in_progress' );
				set_transient( 'con_renumerate_complete', true, DAY_IN_SECONDS );
				return;
			}

			foreach ( $order_ids as $order_id ) {
				$this->add_order_number_meta( $order_id, true );
			}

			if ( count( $order_ids ) === $batch_size ) {
				as_schedule_single_action( time(), 'con_renumerate_batch', array( $page + 1 ), 'con' );
			} else {
				delete_transient( 'con_renumerate_batch_in_progress' );
				set_transient( 'con_renumerate_complete', true, DAY_IN_SECONDS );
			}
		}

		/**
		 * Function search_by_custom_number.
		 *
		 * @param array $metakeys Array of the metakeys to search order numbers on shop order page.
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function search_by_custom_number( $metakeys ) {
			$metakeys[] = '_alg_wc_full_custom_order_number';
			return $metakeys;
		}

		/**
		 * Add the order number to tracking and retrieve the corresponding order ID.
		 *
		 * This method searches through orders to find one with a matching order number. It uses pagination to handle large datasets efficiently.
		 *
		 * @param string $order_number The custom order number to be tracked and matched.
		 * @return int|string The ID of the order that matches the given order number, or the original order number if no match is found.
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_order_number_to_tracking( $order_number ) {
			$offset     = 0;
			$block_size = 512;
			while ( true ) {
				$args        = array(
					'type'           => 'shop_order',
					'status'         => 'any',
					'posts_per_page' => $block_size,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'offset'         => $offset,
				);
				$loop_orders = wc_get_orders( $args );
				if ( count( $loop_orders ) <= 0 ) {
					break;
				}
				foreach ( $loop_orders as $order_ids ) {
					$order_id        = $order_ids->get_id();
					$_order          = wc_get_order( $order_id );
					$_order_number   = $this->display_order_number( $order_id, $_order );
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
		 * @param String $order_number Order number of an order.
		 * @param Object $order order object.
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function display_order_number( $order_number, $order ) {
			if ( ! $order_number ) {
				return $order_number;
			}
			$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
			$order_id              = $order->get_id();
			$sku_new               = CON_Functions::add_sku_number( $order_id, $order );
			$user_role_data        = get_option( 'con_user_roles_data_array', '' );
			$apply_settings_to     = CON_Functions::get_setting( 'settings_to_apply', 'new_order' );
			$custom_number_set     = true;
			$apply_custom_numbers  = false;
			$full_custom_number    = true;
			$order_total           = (int) $order->get_total();
			$free_order            = 'no';
			if ( $order->get_date_created() ) {
				$order_timestamp = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
			} else {
				$order_timestamp = 'now';
			}
			if ( $this->con_wc_hpos_enabled() ) {
				$order_number_meta = $order->get_meta( '_alg_wc_full_custom_order_number' );
			} else {
				$order_number_meta = get_post_meta( $order_id, '_alg_wc_full_custom_order_number', true );
			}

			if ( '' === $order_number_meta ) {
				if ( $this->con_wc_hpos_enabled() ) {
					$order_number_meta = $order->get_meta( '_alg_wc_custom_order_number' );
				} else {
					$order_number_meta = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
				}
				$full_custom_number = false;
				if ( '' === $order_number_meta && 'all_orders' !== $apply_settings_to ) {
					$custom_number_set = false;
					if ( 'new_order' === $apply_settings_to && 'sequential' === CON_Functions::get_setting( 'counter_type', 'sequential' ) ) {
						if ( ( isset( $_GET['action'] ) && 'new' == $_GET['action'] ) || 'post-new.php' === $GLOBALS['pagenow'] ) { //phpcs:ignore
							$order_number = $this->add_order_number_meta_new( $order_id, false, $order );
						}
					}
					return $order_number; // Prevent assigning a new number to older orders.
				}
				if ( '' === $order_number_meta ) {
					$custom_number_set = false;
					global $pagenow;
					if ( ( isset( $_GET['action'] ) && 'new' == $_GET['action'] ) || 'post-new.php' == $pagenow ) { //phpcs:ignore
						$order_number_meta = $this->add_order_number_meta_new( $order_id, false, $order );
					} else {
						$order_number_meta = $order_id;
					}
				}
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
					$apply_from_order_id = CON_Functions::get_setting( 'settings_to_apply_from_order_id', 0 );
					if ( 0 === $apply_from_order_id || '' == $apply_from_order_id || $order_id >= $apply_from_order_id ) { // phpcs:ignore
						$apply_custom_numbers = true;
					}
					break;
				case 'date':
					$apply_from_date = CON_Functions::get_setting( 'settings_to_apply_from_date', '' );
					$date_created    = $order->get_date_created()->date( 'Y-m-d' );
					if ( '' == $apply_from_date || $date_created >= $apply_from_date ) { // phpcs:ignore
						$apply_custom_numbers = true;
					}
					break;
				case 'all_orders':
					$apply_custom_numbers = true;
					break;
			}
			if ( $apply_custom_numbers && ! $full_custom_number ) {
				$custom_prefix = CON_Functions::get_rule_prefix_by_type( 'custom' );

				$order_number_meta = apply_filters(
					'alg_wc_custom_order_numbers',
					sprintf( '%s%s', do_shortcode( $custom_prefix ), $order_number_meta ),
					'value',
					array(
						'order_timestamp'   => $order_timestamp,
						'order_number_meta' => $order_number_meta,
						'order_number_sku'  => $sku_new,
						'free_order'        => $free_order,
						'order_object'      => $order,
					)
				);
			}
			return ! empty( $order_number_meta ) ? $order_number_meta : $order_number;
		}

		/**
		 * Function add_new_order_number.
		 *
		 * @param int $order_id The ID of the order to which the custom order number will be added.
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_new_order_number( $order_id ) {
			$this->add_order_number_meta( $order_id, false );
		}

		/**
		 * Function add_new_order_number_block_checkout.
		 *
		 * Handles the addition of a new order number for block-based checkout orders.
		 *
		 * @param WC_Order $order The WooCommerce order object for which the order number is being added.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_new_order_number_block_checkout( $order ) {
			$order_id = $order->get_id();
			$this->add_order_number_meta( $order_id, false );
		}

		/**
		 * Function add_new_order_number_admin.
		 *
		 * @param  Object $order_id - id of orders.
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_new_order_number_admin( $order_id ) {
			$plugin_name = 'phone-orders-for-woocommerce-pro/phone-orders-for-woocommerce-pro.php';
			if ( in_array( $plugin_name, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ) {
				if ( isset( $_POST['action'], $_POST['method'] ) && 'phone-orders-for-woocommerce' === $_POST['action'] && 'create_order' === $_POST['method'] ) {//phpcs:ignore WordPress.Security.NonceVerification
					return;
				}
			}
			if ( is_admin() ) {
				$this->add_order_number_meta( $order_id, false );
			}
		}
		/**
		 * Function add_new_order_number_inserted_rest_api.
		 *
		 * @param  Object $object - object of orders.
		 * @param  Array  $request - request paramater.
		 * @param  Object $is_creating - object of creating orders.
		 * @version 1.6.1
		 * @since   1.0.0
		 */
		public function add_new_order_number_inserted_rest_api( $object, $request, $is_creating ) { // phpcs:ignore
			if ( ! $is_creating ) {
				return;
			}
			$order_id = $object->get_id();
			$this->add_order_number_meta( $order_id, false );
		}
		/**
		 * Function add_new_order_number_rest_api_for_emails.
		 *
		 * @param Array $order_id order id.
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_new_order_number_rest_api_for_emails( $order_id ) {
			$this->add_order_number_meta( $order_id, false );
		}

		/**
		 * Add/update order_number meta to order.
		 *
		 * @param WC_Order $order_id - id of the order.
		 * @param String   $do_overwrite - boolean.
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function add_order_number_meta( $order_id, $do_overwrite ) {

			if ( ! in_array( get_post_type( $order_id ), array( 'shop_order', 'shop_subscription', 'shop_order_placehold' ), true ) ) {
				return false;
			}

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}

			$order_number = $order->get_meta( '_alg_wc_custom_order_number' );
			if ( true === $do_overwrite || '' === $order_number ) {
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order                 = wc_get_order( $order_id );
				if ( ! $order ) {
					return false;
				}
				$sku_new         = CON_Functions::add_sku_number( $order_id, $order );
				$order_timestamp = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
				$counter_type    = CON_Functions::get_setting( 'counter_type', 'sequential' );
				$order_total     = (int) $order->get_total();
				$free_order      = 'no';
				if ( $order_total <= 0 ) {
					$free_order = 'yes';
				}

				if ( 'sequential' === $counter_type ) {
					$rules                   = CON_Functions::get_setting( 'prefix_suffix_rules', array() );
					$prefix_rules_enabled    = true;
					$priority_order          = array( 'date', 'custom' );
					$matched_sequential_rule = null;
					$current_order_number    = 0;

					// Fallback: Global sequential.
					// Acquire a named MySQL lock to prevent race conditions when two checkout
					// processes read the same counter value and assign duplicate order numbers.
					global $wpdb;
					$lock_name    = 'con_sequential_counter_lock';
					$lock_timeout = 10; // seconds to wait for the lock.
					$lock_result  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $lock_timeout ) );//phpcs:ignore

					if ( '1' !== (string) $lock_result ) {
						// Could not acquire the lock within the timeout — bail out safely.
						return false;
					}

					$current_counter = CON_Functions::get_setting( 'counter', null );

					if ( NULL != $current_counter ) {//phpcs:ignore
						$current_order_number = $this->maybe_reset_sequential_counter( $current_counter, $order_id );
						$result_update        = CON_Functions::update_setting( 'counter', $current_order_number + 1 );

						if ( NULL != $result_update || $current_counter == ( $current_order_number  ) ) {//phpcs:ignore
							$custom_prefix = CON_Functions::get_rule_prefix_by_type( 'custom' );

							$full_custom_order_number = apply_filters(
								'alg_wc_custom_order_numbers',
								sprintf( '%s%s', do_shortcode( $custom_prefix ), $current_order_number ),
								'value',
								array(
									'order_timestamp'   => $order_timestamp,
									'order_number_meta' => $current_order_number,
									'order_number_sku'  => $sku_new,
									'free_order'        => $free_order,
									'order_object'      => $order,
								)
							);

							if ( $this->con_wc_hpos_enabled() ) {
								$order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
								$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
								$order->save();
							} else {
								update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
								update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
							}
						} else {
							$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );//phpcs:ignore
							return false;
						}
					} else {
						$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );//phpcs:ignore
						return false;
					}

					$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );//phpcs:ignore
				} elseif ( 'hash_crc32' === $counter_type ) {
					if ( CON_Functions::get_setting( 'include_character_enabled', false ) ) {
						$current_order_number = sprintf( '%x', crc32( $order_id ) );
					} else {
						$current_order_number = sprintf( '%u', crc32( $order_id ) );
					}

					$custom_prefix = CON_Functions::get_rule_prefix_by_type( 'custom' );
					$full_custom_order_number = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( $custom_prefix ), $current_order_number ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $current_order_number,
							'order_number_sku'  => $sku_new,
							'free_order'        => $free_order,
							'order_object'      => $order,
						)
					);
					if ( $this->con_wc_hpos_enabled() ) {
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
							'order_number_sku'  => $sku_new,
							'free_order'        => $free_order,
							'order_object'      => $order,
						)
					);
					if ( $this->con_wc_hpos_enabled() ) {
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
			// Order already has a number - reapply prefix/suffix formatting.
			$current_order_number = $order_number;

			if ( $current_order_number ) {
				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					return false;
				}
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$order_timestamp       = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
				$order_total           = (int) $order->get_total();
				$free_order            = ( $order_total <= 0 ) ? 'yes' : 'no';
				$sku_new               = CON_Functions::add_sku_number( $order_id, $order );

				$full_custom_order_number = apply_filters(
					'alg_wc_custom_order_numbers',
					'',
					'value',
					array(
						'order_timestamp'   => $order_timestamp,
						'order_number_meta' => $current_order_number,
						'order_number_sku'  => $sku_new,
						'free_order'        => $free_order,
						'order_object'      => $order,
					)
				);
				if ( $this->con_wc_hpos_enabled() ) {
					$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
					$order->save();
				} else {
					update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
				}
				return $current_order_number;
			}
			return false;
		}

		/**
		 * Function alg_wc_custom_order_numbers.
		 *
		 * @param array  $value Value.
		 * @param string $type Type selected in general settings.
		 * @param array  $args Arguments of the value of Custom Order Numbers.
		 * @version 1.2.3
		 * @since   1.0.0
		 */
		public static function alg_wc_custom_order_numbers( $value, $type, $args = '' ) {
			switch ( $type ) {
				case 'settings':
					return ''; // phpcs:ignore
				case 'value':
					$order                               = isset( $args['order_object'] ) ? $args['order_object'] : false;
					$custom_order_timestamp              = isset( $args['order_timestamp'] ) ? $args['order_timestamp'] : time();
					$custom_order_number_no_restrictions = $args['order_number_meta'];
					$con_sku                             = $args['order_number_sku'];
					$template                            = CON_Functions::get_setting( 'custom_order_numbers_template', '{prefix}{date_prefix}{number}{suffix}{date_suffix}' );
					$counter_type                        = CON_Functions::get_setting( 'counter_type', 'sequential' );
					$order_number_width                  = (int) CON_Functions::get_setting( 'min_width', 0 );

					if ( $order_number_width > 0 && 'hash_crc32' === $counter_type ) {
						$custom_order_number_by_width = substr( $custom_order_number_no_restrictions, 0, $order_number_width );
					} else {
						$custom_order_number_by_width = $custom_order_number_no_restrictions;
					}
					$prefix_data = array(
						'date'   => '',
						'custom' => '',
					);

					$rules                = CON_Functions::get_setting( 'prefix_suffix_rules', array() );
					$prefix_rules_enabled = true; // always true in lite.

					if ( $prefix_rules_enabled && $order && ! empty( $rules ) ) {
						foreach ( $rules as $rule ) {
							$matched = false;
							switch ( $rule['condition_type'] ) {
								case 'date':
									$prefix_data['date'] = $rule['prefix'];
									$suffix_data['date'] = $rule['suffix'];
									$matched             = true;
									break;
								case 'custom':
									$prefix_data['custom'] = $rule['prefix'];
									$suffix_data['custom'] = $rule['suffix'];
									$matched               = true;
									break;
							}
						}
					}

					$data  = array(
						'{prefix}'      => $prefix_data['custom'],
						'{date_prefix}' => ( $prefix_data['date'] ? date_i18n( $prefix_data['date'], $custom_order_timestamp ) : '' ),
						'{number}'      => sprintf( '%0' . $order_number_width . 's', $custom_order_number_by_width ),
						'{suffix}'      => '',
						'{date_suffix}' => '',
					);

					$final = str_replace( array_keys( $data ), $data, $template );
					return $final; // phpcs:ignore
				case 'manual_counter_value':
					return CON_Functions::get_setting( 'manual_enabled', false ); // phpcs:ignore
			}

			return $value; // phpcs:ignore
		}

		/**
		 * Add order number to order.
		 *
		 * @param WC_Order $order_id - id of the order.
		 * @param String   $do_overwrite - boolean.
		 * @param Object   $order - object of order.
		 * @version 1.8.0
		 * @since   1.8.0
		 */
		public function add_order_number_meta_new( $order_id, $do_overwrite, $order ) {
			if ( ! in_array( get_post_type( $order_id ), array( 'shop_order', 'shop_subscription', 'shop_order_placehold' ), true ) ) {//phpcs:ignore
				return false;
			}

			if ( ! $order ) {
				return false;
			}

			$order_number = $order->get_meta( '_alg_wc_custom_order_number' );

			if ( true === $do_overwrite || '' === $order_number ) {
				$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
				$sku_new               = CON_Functions::add_sku_number( $order_id, $order );
				$counter_type          = CON_Functions::get_setting( 'counter_type', 'sequential' );
				$order_total           = (int) $order->get_total();
				$free_order            = 'no';

				if ( $order->get_date_created() ) {
					$order_timestamp = strtotime( $order->get_date_created() );
				} else {
					$order_timestamp = 'now';
				}

				if ( 'sequential' === $counter_type ) {
					// Acquire a named MySQL lock to prevent race conditions when two checkout
					// processes read the same counter value and assign duplicate order numbers.
					global $wpdb;
					$lock_name    = 'con_sequential_counter_lock';
					$lock_timeout = 10; // seconds to wait for the lock.
					$lock_result  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $lock_timeout ) );//phpcs:ignore

					if ( '1' !== (string) $lock_result ) {
						// Could not acquire the lock within the timeout — bail out safely.
						return false;
					}

					$current_counter = CON_Functions::get_setting( 'counter', null );

					if ( NULL != $current_counter ) {//phpcs:ignore
						$current_order_number = $this->maybe_reset_sequential_counter( $current_counter, $order_id );
						$result_update        = CON_Functions::update_setting( 'counter', $current_order_number + 1 );

						if ( NULL != $result_update || $current_counter == ( $current_order_number  ) ) {//phpcs:ignore
							$custom_prefix = CON_Functions::get_rule_prefix_by_type( 'custom' );

							$full_custom_order_number = apply_filters(
								'alg_wc_custom_order_numbers',
								sprintf( '%s%s', do_shortcode( $custom_prefix ), $current_order_number ),
								'value',
								array(
									'order_timestamp'   => $order_timestamp,
									'order_number_meta' => $current_order_number,
									'order_number_sku'  => $sku_new,
									'free_order'        => $free_order,
									'order_object'      => $order,
								)
							);

							if ( $this->con_wc_hpos_enabled() ) {
								$order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
								$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
								$order->save();
							} else {
								update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
								update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
							}
						} else {
							$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );//phpcs:ignore
							return false;
						}
					} else {
						$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );//phpcs:ignore
						return false;
					}

					$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );//phpcs:ignore
				} elseif ( 'hash_crc32' === $counter_type ) {
					if ( CON_Functions::get_setting( 'include_character_enabled', false ) ) {
						$current_order_number = sprintf( '%x', crc32( $order_id ) );
					} else {
						$current_order_number = sprintf( '%u', crc32( $order_id ) );
					}
					$custom_prefix            = CON_Functions::get_rule_prefix_by_type( 'custom' );
					$full_custom_order_number = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( $custom_prefix ), $current_order_number ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $current_order_number,
							'order_number_sku'  => $sku_new,
							'free_order'        => $free_order,
							'order_object'      => $order,
						)
					);
					if ( $this->con_wc_hpos_enabled() ) {
						$order->update_meta_data( '_alg_wc_custom_order_number', $current_order_number );
						$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
						$order->save();
					} else {
						update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
						update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
					}
				} else { // 'order_id'
					$current_order_number     = $order_id;
					$custom_prefix            = CON_Functions::get_rule_prefix_by_type( 'custom' );

					$full_custom_order_number = apply_filters(
						'alg_wc_custom_order_numbers',
						sprintf( '%s%s', do_shortcode( $custom_prefix ), $current_order_number ),
						'value',
						array(
							'order_timestamp'   => $order_timestamp,
							'order_number_meta' => $current_order_number,
							'order_number_sku'  => $sku_new,
							'free_order'        => $free_order,
							'order_object'      => $order,
						)
					);
					if ( $this->con_wc_hpos_enabled() ) {
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
		 * Add product sku to order.
		 *
		 * @param   WC_Order $order_id - id of the order.
		 * @param   Object   $order - object of the order.
		 * @return WC_Order $product_sku_new.
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function add_sku_number( $order_id, $order ) {
			if ( ! $order ) {
				return;
			}
			$items = $order->get_items();
			$sku   = array();
			foreach ( $items as $item ) {
				$product = $item->get_product();

				if ( $product ) {
					$sku[] = $product->get_sku();
				}
			}
			if ( ! empty( $sku ) ) {
				$sku_new = $sku[0];
			} else {
				$sku_new = '';
			}
			return $sku_new;
		}

		/**
		 * Updates the custom order number for a renewal order created
		 * using WC Subscriptions
		 *
		 * @param WC_Order $renewal_order - Order Object of the renewed order.
		 * @return WC_Order $renewal_order
		 * @since 1.2.5
		 */
		public function remove_order_meta_renewal( $renewal_order ) {
			$new_order_id = $renewal_order->get_id();
			// update the custom order number.
			$this->add_order_number_meta( $new_order_id, true );
			return $renewal_order;
		}

		/**
		 * Updates the custom order number for the WC Subscription
		 *
		 * @param Object $subscription - Subscription for which the order has been created.
		 * @since 1.2.5
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
			$to_order_id     = $to_order->get_id();
			$from_order_type = get_post_type( $from_order->get_id() );
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
		 * Function to pass the CON meta key in Germanized for WooCommerce Plugin, so that when the return form is submitted using the CON than it works.
		 */
		public function alg_wc_custom_order_numbers_gzd_return_request_meta_key() {
			return '_alg_wc_full_custom_order_number';
		}


		/**
		 * Function to add prefix suffix on status change.
		 *
		 * @param int $order_id Order ID.
		 */
		public function reapply_prefix_on_status_change( $order_id, $from, $to, $order ) {
			if ( in_array( $to, array( 'processing', 'completed','on-hold' ), true ) ) {
				$this->maybe_reapply_order_number_formatting_on_update( $order_id );
			}
		}

		/**
		 * Function to add prefix suffix on update.
		 *
		 * @param int $order_id Order ID.
		 */
		public function maybe_reapply_order_number_formatting_on_update( $order_id, $post = null ) {
			static $already_ran = array();

			if ( isset( $already_ran[ $order_id ] ) ) {
				return;
			}
			$already_ran[ $order_id ] = true;

			$order = wc_get_order( $order_id );
			if ( ! $order || is_wp_error( $order ) ) {
				return;
			}
			$status = $order->get_status();
			if ( in_array( $status, array( 'processing', 'completed' ), true ) ) {
				return;
			}
			$current_number = $order->get_meta( '_alg_wc_custom_order_number' );
			if ( '' === $current_number ) {
				return;
			}
			$order_timestamp = strtotime( $order->get_date_created() );
			$order_total     = (int) $order->get_total();
			$free_order      = ( $order_total <= 0 ) ? 'yes' : 'no';
			$sku_new         = CON_Functions::add_sku_number( $order_id, $order );

			$full_custom_order_number = apply_filters(
				'alg_wc_custom_order_numbers',
				'',
				'value',
				array(
					'order_timestamp'   => $order_timestamp,
					'order_number_meta' => $current_number,
					'order_number_sku'  => $sku_new,
					'free_order'        => $free_order,
					'order_object'      => $order,
				)
			);
			if ( $this->con_wc_hpos_enabled() ) {
				$order->update_meta_data( '_alg_wc_full_custom_order_number', $full_custom_order_number );
				$order->save();
			} else {
				update_post_meta( $order_id, '_alg_wc_full_custom_order_number', $full_custom_order_number );
			}
		}
	}

endif;

return new Core();
