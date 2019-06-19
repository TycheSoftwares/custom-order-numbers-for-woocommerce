<?php
/**
 * Custom Order Numbers for WooCommerce - Core Class
 *
 * @version 1.2.2
 * @since   1.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers_Core' ) ) :

class Alg_WC_Custom_Order_Numbers_Core {

	/**
	 * Constructor.
	 *
	 * @version 1.1.1
	 * @since   1.0.0
	 * @todo    [feature] (maybe) prefix / suffix per order (i.e. different prefix / suffix for different orders)
	 */
	function __construct() {
		if ( 'yes' === get_option( 'alg_wc_custom_order_numbers_enabled', 'yes' ) ) {
			add_action( 'wp_insert_post',           array( $this, 'add_new_order_number' ), PHP_INT_MAX ); // 'woocommerce_new_order'
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
				add_action( 'add_meta_boxes',       array( $this, 'add_order_number_meta_box' ) );
				add_action( 'save_post_shop_order', array( $this, 'save_order_number_meta_box' ), PHP_INT_MAX, 2 );
			}

			// check if subscriptions is enabled
			if( in_array('woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				add_action( 'woocommerce_checkout_subscription_created', array( $this, 'update_custom_order_meta' ), PHP_INT_MAX, 1 );
				add_filter( 'wcs_renewal_order_created', array( $this, 'remove_order_meta_renewal' ), PHP_INT_MAX, 2 );
			}
		}
	}

	/**
	 * maybe_reset_sequential_counter.
	 *
	 * @version 1.2.2
	 * @since   1.1.2
	 * @todo    [dev] use MySQL transaction
	 */
	function maybe_reset_sequential_counter( $current_order_number, $order_id ) {
		if ( 'no' != ( $reset_period = get_option( 'alg_wc_custom_order_numbers_counter_reset_enabled', 'no' ) ) ) {
			$previous_order_date   = get_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', 0 );
			$order                 = wc_get_order( $order_id );
			$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
			$order_date            = ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() );
			$current_order_date    = strtotime( $order_date );
			update_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', $current_order_date );
			if ( 0 != $previous_order_date ) {
				$do_reset = false;
				switch ( $reset_period ) {
					case 'daily':
						$do_reset = (
							date( 'Y', $current_order_date ) != date( 'Y', $previous_order_date ) ||
							date( 'm', $current_order_date ) != date( 'm', $previous_order_date ) ||
							date( 'd', $current_order_date ) != date( 'd', $previous_order_date )
						);
						break;
					case 'monthly':
						$do_reset = (
							date( 'Y', $current_order_date ) != date( 'Y', $previous_order_date ) ||
							date( 'm', $current_order_date ) != date( 'm', $previous_order_date )
						);
						break;
					case 'yearly':
						$do_reset = (
							date( 'Y', $current_order_date ) != date( 'Y', $previous_order_date )
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
	 * save_order_number_meta_box.
	 *
	 * @version 1.1.1
	 * @since   1.1.1
	 */
	function save_order_number_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['alg_wc_custom_order_numbers_meta_box'] ) ) {
			return;
		}
		update_post_meta( $post_id, '_alg_wc_custom_order_number', $_POST['alg_wc_custom_order_number'] );
	}

	/**
	 * add_order_number_meta_box.
	 *
	 * @version 1.1.1
	 * @since   1.1.1
	 */
	function add_order_number_meta_box() {
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
	 * create_order_number_meta_box.
	 *
	 * @version 1.1.1
	 * @since   1.1.1
	 */
	function create_order_number_meta_box() {
		$html = '';
		$html .= '<input type="number" name="alg_wc_custom_order_number" style="width:100%;" value="' .
			get_post_meta( get_the_ID(), '_alg_wc_custom_order_number', true ) . '">';
		$html .= '<input type="hidden" name="alg_wc_custom_order_numbers_meta_box">';
		echo $html;
	}

	/**
	 * Add menu item.
	 *
	 * @version 1.2.2
	 * @since   1.0.0
	 */
	function add_renumerate_orders_tool() {
		$hide_for_roles = get_option( 'alg_wc_custom_order_numbers_hide_menu_for_roles', array() );
		if ( ! empty( $hide_for_roles ) ) {
			$user       = wp_get_current_user();
			$user_roles = ( array ) $user->roles;
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
	function create_renumerate_orders_tool() {
		$html                   = '';
		$result_message         = '';
		$last_renumerated_order = 0;
		if ( isset( $_POST['alg_renumerate_orders'] ) ) {
			$total_renumerated_orders = $this->renumerate_orders();
			$last_renumerated_order   = $total_renumerated_orders[1];
			$total_renumerated_orders = $total_renumerated_orders[0];
			$result_message           = '<p><div class="updated"><p><strong>' .
				sprintf( __( '%d orders successfully renumerated!', 'custom-order-numbers-for-woocommerce' ), $total_renumerated_orders ) . '</strong></p></div></p>';
		}
		$html .= '<h1>' . __( 'Renumerate Orders', 'custom-order-numbers-for-woocommerce' ) . '</h1>';
		$html .= $result_message;
		$html .= '<p>' . sprintf( __( 'Plugin settings: <a href="%s">WooCommerce > Settings > Custom Order Numbers</a>.', 'custom-order-numbers-for-woocommerce' ),
			admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_numbers' ) ) . '</p>';
		$next_order_number = ( 0 != $last_renumerated_order ) ? ( $last_renumerated_order + 1 ) : get_option( 'alg_wc_custom_order_numbers_counter', 1 );
		$html .= '<p>' . __( 'Press the button below to renumerate all existing orders.', 'custom-order-numbers-for-woocommerce' ) . '</p>';
		if ( 'sequential' === get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' ) ) {
			$html .= '<p>' . sprintf( __( 'First order number will be <strong>%d</strong>.', 'custom-order-numbers-for-woocommerce' ), $next_order_number ) . '</p>';
		}
		$html .= '<form method="post" action="">';
		$html .= '<input class="button-primary" type="submit" name="alg_renumerate_orders" value="' . __( 'Renumerate orders', 'custom-order-numbers-for-woocommerce' ) . '"' .
			' onclick="return confirm(\'' . __( 'Are you sure?', 'custom-order-numbers-for-woocommerce' ) . '\')">';
		$html .= '</form>';
		echo '<div class="wrap">' . $html . '</div>';
	}

	/**
	 * Renumerate orders function.
	 *
	 * @version 1.1.2
	 * @since   1.0.0
	 */
	function renumerate_orders() {
		if ( 'sequential' === get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' ) && 'no' != get_option( 'alg_wc_custom_order_numbers_counter_reset_enabled', 'no' ) ) {
			update_option( 'alg_wc_custom_order_numbers_counter_previous_order_date', 0 );
		}
		$total_renumerated = 0;
		$last_renumerated  = 0;
		$offset            = 0;
		$block_size        = 512;
		while( true ) {
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
	 * search_by_custom_number.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @see     https://github.com/pablo-pacheco/wc-booster-search-order-by-custom-number-fix
	 */
	function search_by_custom_number( $query ) {
		if (
			! is_admin() ||
			! isset( $query->query ) ||
			! isset( $query->query['s'] ) ||
			false === is_numeric( $query->query['s'] ) ||
			0 == $query->query['s'] ||
			'shop_order' !== $query->query['post_type'] ||
			! $query->query_vars['shop_order_search']
		) {
			return;
		}
		$custom_order_id = $query->query['s'];
		$query->query_vars['post__in'] = array();
		$query->query['s'] = '';
		$query->set( 'meta_key', '_alg_wc_custom_order_number' );
		$query->set( 'meta_value', $custom_order_id );
	}

	/**
	 * add_order_number_to_tracking.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_order_number_to_tracking( $order_number ) {
		$offset     = 0;
		$block_size = 512;
		while( true ) {
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
				$_order = wc_get_order( $order_id );
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
	 * @version 1.2.1
	 * @since   1.0.0
	 */
	function display_order_number( $order_number, $order ) {
		$is_wc_version_below_3 = version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' );
		$order_id              = ( $is_wc_version_below_3 ? $order->id : $order->get_id() );
		$order_number_meta     = get_post_meta( $order_id, '_alg_wc_custom_order_number', true );
		if ( '' == $order_number_meta || 'order_id' === get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' ) ) {
			$order_number_meta = $order_id;
		}
		$order_timestamp = strtotime( ( $is_wc_version_below_3 ? $order->order_date : $order->get_date_created() ) );
		$order_number = apply_filters( 'alg_wc_custom_order_numbers', sprintf( '%s%s', do_shortcode( get_option( 'alg_wc_custom_order_numbers_prefix', '' ) ), $order_number_meta ), 'value', array( 'order_timestamp' => $order_timestamp, 'order_number_meta' => $order_number_meta ) );
		return $order_number;
	}

	/**
	 * add_new_order_number.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function add_new_order_number( $order_id ) {
		$this->add_order_number_meta( $order_id, false );
	}

	/**
	 * Add/update order_number meta to order.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function add_order_number_meta( $order_id, $do_overwrite ) {
		if ( ! in_array( get_post_type( $order_id ), array( 'shop_order', 'shop_subscription' ) ) ) {
			return false;
		}
		if ( true === $do_overwrite || 0 == get_post_meta( $order_id, '_alg_wc_custom_order_number', true ) ) {
			$counter_type = get_option( 'alg_wc_custom_order_numbers_counter_type', 'sequential' );
			if ( 'sequential' === $counter_type ) {
				// Using MySQL transaction, so in case of a lot of simultaneous orders in the shop - prevent duplicate sequential order numbers
				global $wpdb;
				$wpdb->query( 'START TRANSACTION' );
				$wp_options_table = $wpdb->prefix . 'options';
				$result_select = $wpdb->get_row( "SELECT * FROM $wp_options_table WHERE option_name = 'alg_wc_custom_order_numbers_counter'" );
				if ( NULL != $result_select ) {
					$current_order_number = $this->maybe_reset_sequential_counter( $result_select->option_value, $order_id );
					$result_update = $wpdb->update(
						$wp_options_table,
						array( 'option_value' => ( $current_order_number + 1 ) ),
						array( 'option_name'  => 'alg_wc_custom_order_numbers_counter' )
					);
					if ( NULL != $result_update || $result_select->option_value == ( $current_order_number + 1 ) ) {
						$wpdb->query( 'COMMIT' );   // all ok
						update_post_meta( $order_id, '_alg_wc_custom_order_number', $current_order_number );
					} else {
						$wpdb->query( 'ROLLBACK' ); // something went wrong, Rollback
						return false;
					}
				} else {
					$wpdb->query( 'ROLLBACK' );     // something went wrong, Rollback
					return false;
				}
			} elseif ( 'hash_crc32' === $counter_type ) {
				$current_order_number = sprintf( "%u", crc32( $order_id ) );
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
	 * @param WC_Order $renewal_order - Order Object of the renewed order
	 * @param $subscription - Subscription for which the order has been created
	 * @return WC_Order $renewal_order
	 * @since 1.2.6
	 */
	function remove_order_meta_renewal( $renewal_order, $subscription ) {
		$new_order_id = $renewal_order->get_id();
		// update the custom order number
		$this->add_order_number_meta( $new_order_id, true );
		return $renewal_order;
	}

	/**
	 * Updates the custom order number for the WC Subscription
	 * @param $subscription - Subscription for which the order has been created
	 * @since 1.2.6
	 */
	function update_custom_order_meta( $subscription ) {
		
		$subscription_id = $subscription->get_id();
		// update the custom order number
		$this->add_order_number_meta( $subscription_id, true );
	
	}

	/**
	 * Remove the WooCommerc filter which convers the order numbers to integers by removing the * * characters.
	 */
	function alg_remove_tracking_filter() {
		remove_filter( 'woocommerce_shortcode_order_tracking_order_id', 'wc_sanitize_order_id' );
	}

}

endif;

return new Alg_WC_Custom_Order_Numbers_Core();
