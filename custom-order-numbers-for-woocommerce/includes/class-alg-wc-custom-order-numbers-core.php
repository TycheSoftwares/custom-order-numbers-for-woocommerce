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
				add_action( 'wp_insert_post', array( $this, 'add_new_order_number' ), PHP_INT_MAX ); // 'woocommerce_new_order'
				add_filter( 'woocommerce_order_number', array( $this, 'display_order_number' ), PHP_INT_MAX, 2 );
				if ( 'yes' === get_option( 'alg_wc_custom_order_numbers_order_tracking_enabled', 'yes' ) ) {
					add_action( 'init', array( $this, 'alg_remove_tracking_filter' ) );
					add_filter( 'woocommerce_shortcode_order_tracking_order_id', array( $this, 'add_order_number_to_tracking' ), PHP_INT_MAX );
				}
				if ( 'yes' === get_option( 'alg_wc_custom_order_numbers_search_by_custom_number_enabled', 'yes' ) ) {
					add_action( 'pre_get_posts', array( $this, 'search_by_custom_number' ) );
				}
				add_action( 'admin_menu', array( $this, 'add_renumerate_orders_tool' ), PHP_INT_MAX );
				if ( 'yes' === apply_filters( 'alg_wc_custom_order_numbers', 'no', 'manual_counter_value' ) ) {
					add_action( 'add_meta_boxes', array( $this, 'add_order_number_meta_box' ) );
					add_action( 'save_post_shop_order', array( $this, 'save_order_number_meta_box' ), PHP_INT_MAX, 2 );
				}

				// check if subscriptions is enabled.
				if ( in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
					add_action( 'woocommerce_checkout_subscription_created', array( $this, 'update_custom_order_meta' ), PHP_INT_MAX, 1 );
					add_filter( 'wcs_renewal_order_created', array( $this, 'remove_order_meta_renewal' ), PHP_INT_MAX, 2 );
				}
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
			if ( ! isset( $_POST['alg_wc_custom_order_numbers_meta_box'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return;
			}

			if ( isset( $_POST['alg_wc_custom_order_number'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				update_post_meta( $post_id, '_alg_wc_custom_order_number', sanitize_text_field( wp_unslash( $_POST['alg_wc_custom_order_number'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}

		/**
		 * Add_order_number_meta_box.
		 *
		 * @version 1.1.1
		 * @since   1.1.1
		 */
		public function add_order_number_meta_box() {
			add_meta_box(
				'alg-wc-custom-order-numbers-meta-box',
				__( 'Order Number', 'custom-order-numbers-for-woocommerce' ),
				array( $this, 'create_order_number_meta_box' ),
				'shop_order',
				'side',
				'low'
			);
		}

		/**
		 * Create_order_number_meta_box.
		 *
		 * @version 1.1.1
		 * @since   1.1.1
		 */
		public function create_order_number_meta_box() {
			?>
			<input type="number" name="alg_wc_custom_order_number" style="width:100%;" value="<?php echo esc_attr( get_post_meta( get_the_ID(), '_alg_wc_custom_order_number', true ) ); ?>">
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
			if ( isset( $_POST['alg_renumerate_orders'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
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
				$args = array(
					'post_type'      => 'shop_order',
					'post_status'    => 'any',
					'posts_per_page' => $block_size,
					'orderby'        => 'date',
					'order'          => 'ASC',
					'offset'         => $offset,
					'fields'         => 'ids',
				);
				$loop = new WP_Query( $args );
				if ( ! $loop->have_posts() ) {
					break;
				}
				foreach ( $loop->posts as $order_id ) {
					$last_renumerated = $this->add_order_number_meta( $order_id, true );
					$total_renumerated++;
				}
				$offset += $block_size;
			}
			return array( $total_renumerated, $last_renumerated );
		}

		/**
		 * Search_by_custom_number.
		 *
		 * @param object $query - WP_Query.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @see     https://github.com/pablo-pacheco/wc-booster-search-order-by-custom-number-fix
		 */
		public function search_by_custom_number( $query ) {
			if (
			! is_admin() ||
			! isset( $query->query ) ||
			! isset( $query->query['s'] ) ||
			false === is_numeric( $query->query['s'] ) ||
			'0' === $query->query['s'] ||
			'shop_order' !== $query->query['post_type'] ||
			! $query->query_vars['shop_order_search']
			) {
				return;
			}
			$custom_order_id               = $query->query['s'];
			$query->query_vars['post__in'] = array();
			$query->query['s']             = '';
			$query->set( 'meta_key', '_alg_wc_custom_order_number' );
			$query->set( 'meta_value', $custom_order_id );
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
			$order_number_meta     = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
			if ( '' === $order_number_meta || 'order_id' === get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' ) ) {
				$order_number_meta = $order_id;
			}
			$order_timestamp = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
			$order_number    = apply_filters(
				'alg_wc_custom_order_numbers',
				sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ),
				'value',
				array(
					'order_timestamp'   => $order_timestamp,
					'order_number_meta' => $order_number_meta,
				)
			);
			return $order_number;
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
			if ( ! in_array( get_post_type( $order_id ), array( 'shop_order', 'shop_subscription' ), true ) ) {
				return false;
			}
			if ( true === $do_overwrite || 0 == get_post_meta( $order_id, '_alg_wc_custom_order_number', true ) ) {
				$counter_type = get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' );
				if ( 'sequential' === $counter_type ) {
					// Using MySQL transaction, so in case of a lot of simultaneous orders in the shop - prevent duplicate sequential order numbers.
					global $wpdb;
					$wpdb->query( 'START TRANSACTION' ); //phpcs:ignore
					$wp_options_table = $wpdb->prefix . 'options';
					$result_select    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . $wpdb->prefix . 'options` WHERE option_name = %s', 'alg_wc_custom_order_numbers_counter' ) ); //phpcs:ignore
					if ( null !== $result_select ) {
						$current_order_number = $this->maybe_reset_sequential_counter( $result_select->option_value, $order_id );
						$result_update        = $wpdb->update( //phpcs:ignore
							$wp_options_table,
							array( 'option_value' => ( $current_order_number + 1 ) ),
							array( 'option_name' => 'alg_wc_custom_order_numbers_counter' )
						);
						$current_order_number_new = $current_order_number + 1;
						if ( null !== $result_update || $current_order_number_new === $result_select->option_value ) {
							// all ok.
							$wpdb->query( 'COMMIT' ); //phpcs:ignore
							update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
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
					$current_order_number = sprintf( '%u', crc32( $order_id ) );
					update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
				} else { // 'order_id'
					$current_order_number = '';
					update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
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

	}

endif;

return new Alg_WC_Custom_Order_Numbers_Core();
