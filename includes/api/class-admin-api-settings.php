<?php
/**
 * CON Admin API Settings Class
 * Handles admin settings-related API functionalities for the CON plugin.
 * 
 * @package CON/Admin/API/Settings
 */

namespace Tyche\CON\Admin\API;

use Tyche\CON\Tyche_Plugin_Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * CON_Admin_API_Settings Class
 */
class Admin_API_Settings extends Admin_API {

    /**
	 * Construct
	 *
	 * @since 1.2
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_endpoints' ) );
	}

    /**
	 * Function for registering the API endpoints.
	 *
	 * @since 1.2
	 */
	public static function register_endpoints() {

		// Renumerate orders.
		register_rest_route(
			self::$base_endpoint,
			'renumerate',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'renumerate_orders' ),
					'permission_callback' => array( __CLASS__, 'get_permission' ),
				),
			)
		);

		// Reset tracking.
		register_rest_route(
			self::$base_endpoint,
			'reset-tracking',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'reset_tracking' ),
					'permission_callback' => array( __CLASS__, 'get_permission' ),
				),
			)
		);

		// Batch processing status.
		register_rest_route(
			self::$base_endpoint,
			'batch-status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_batch_status' ),
					'permission_callback' => array( __CLASS__, 'get_permission' ),
				),
			)
		);

		// Fetch Settings.
		register_rest_route(
			self::$base_endpoint,
			'settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'fetch_data' ),
					'permission_callback' => array( __CLASS__, 'get_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'save_data' ),
					'permission_callback' => array( __CLASS__, 'get_permission' ),
				),
			)
		);
	}

	/**
	 * Returns whether a rules batch processing job or renumeration job is still pending or running.
	 */
	public static function get_batch_status() {
		$in_progress           = false;
		$renumerate_in_progress = false;

		if ( function_exists( 'as_get_scheduled_actions' ) ) {
			foreach ( array( 'con_process_rules_batch', 'con_renumerate_batch' ) as $hook ) {
				$pending = as_get_scheduled_actions(
					array(
						'hook'     => $hook,
						'status'   => \ActionScheduler_Store::STATUS_PENDING,
						'per_page' => 1,
					),
					'ids'
				);
				$running = as_get_scheduled_actions(
					array(
						'hook'     => $hook,
						'status'   => \ActionScheduler_Store::STATUS_RUNNING,
						'per_page' => 1,
					),
					'ids'
				);
				$hook_active = ! empty( $pending ) || ! empty( $running );
				if ( 'con_renumerate_batch' === $hook ) {
					$renumerate_in_progress = $hook_active;
				} else {
					$in_progress = $hook_active;
				}
			}
		}

		$total_raw            = get_transient( 'con_renumerate_total_orders' );
		$processed_raw        = get_transient( 'con_renumerate_processed_count' );
		$renumerate_total     = false !== $total_raw ? (int) $total_raw : null;
		$renumerate_processed = false !== $processed_raw ? (int) $processed_raw : 0;

		return self::return_response(
			array(
				'in_progress'            => $in_progress,
				'renumerate_in_progress' => $renumerate_in_progress,
				'renumerate_total'       => $renumerate_total,
				'renumerate_processed'   => $renumerate_processed,
			)
		);
	}

	/**
	 * Permission callback for API endpoints
	 */
	public static function get_permission( $request ) {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return false;
	}

    /**
	 * Returns General Settings Data.
	 *
	 * @param array $return_raw Whether to return the Raw response.
	 *
	 * @since 1.2
	 */
	public static function fetch_data( $request ) {
		$settings = get_option( 'con_general_settings', array() );

		return self::return_response( $settings );
	}

	/**
	 * Triggers order renumeration via the con_renumerate_orders filter bridge.
	 */
	public static function renumerate_orders( $request ) {
		$result = apply_filters( 'con_renumerate_orders', null );

		if ( is_null( $result ) ) {
			return self::return_response( array( 'error' => 'Renumeration could not be triggered. Ensure the plugin is enabled.' ) );
		}

		if ( isset( $result['scheduled'] ) && $result['scheduled'] ) {
			$total = false !== get_transient( 'con_renumerate_total_orders' ) ? (int) get_transient( 'con_renumerate_total_orders' ) : null;
			return self::return_response( array( 'scheduled' => true, 'total' => $total ) );
		}

		return self::return_response(
			array(
				'total_renumerated' => $result[0],
				'last_renumerated'  => $result[1],
			)
		);
	}

	/**
	 * Resets usage tracking by deleting the tracking options.
	 */
	public static function reset_tracking( $request ) {
		Tyche_Plugin_Tracking::reset_tracker_setting( 'con_lite' );
		return self::return_response( array( 'message' => 'Tracking has been successfully reset.' ) );
	}

	/**
	 * Saves the settings data.
	 */
	public static function save_data( $request ) {
		$data = json_decode( $request->get_body(), true );

		if ( ! is_array( $data ) ) {
			return self::return_response( array( 'error' => 'Invalid data format' ) );
		}

		if ( ! empty( $data['prefix_suffix_rules'] ) && is_array( $data['prefix_suffix_rules'] ) ) {
			foreach ( $data['prefix_suffix_rules'] as &$rule ) {
				if ( ! isset( $rule['custom_counter'] ) ) {
					$rule['custom_counter'] = 1;
				}
			}
			unset( $rule );
		}

		$existing       = get_option( 'con_general_settings', array() );
		$existing_rules = isset( $existing['prefix_suffix_rules'] ) ? $existing['prefix_suffix_rules'] : array();
		$incoming_rules = array_key_exists( 'prefix_suffix_rules', $data ) ? $data['prefix_suffix_rules'] : null;
		$rules_changed  = null !== $incoming_rules && $incoming_rules !== $existing_rules;

		$existing_settings_to_apply = isset( $existing['settings_to_apply'] ) ? $existing['settings_to_apply'] : '';
		$incoming_settings_to_apply = array_key_exists( 'settings_to_apply', $data ) ? $data['settings_to_apply'] : $existing_settings_to_apply;
		$existing_from_order_id     = isset( $existing['settings_to_apply_from_order_id'] ) ? $existing['settings_to_apply_from_order_id'] : '';
		$incoming_from_order_id     = array_key_exists( 'settings_to_apply_from_order_id', $data ) ? $data['settings_to_apply_from_order_id'] : $existing_from_order_id;
		$existing_from_date         = isset( $existing['settings_to_apply_from_date'] ) ? $existing['settings_to_apply_from_date'] : '';
		$incoming_from_date         = array_key_exists( 'settings_to_apply_from_date', $data ) ? $data['settings_to_apply_from_date'] : $existing_from_date;

		$apply_settings_changed = (
			$incoming_settings_to_apply !== $existing_settings_to_apply ||
			$incoming_from_order_id !== $existing_from_order_id ||
			$incoming_from_date !== $existing_from_date
		);

		update_option( 'con_general_settings', array_merge( $existing, $data ) );

		$should_trigger_batch = $rules_changed || (
			$apply_settings_changed && in_array( $incoming_settings_to_apply, array( 'order_id', 'date', 'all_orders' ), true )
		);

		if ( $should_trigger_batch ) {
			do_action( 'con_prefix_suffix_rules_updated', null !== $incoming_rules ? $incoming_rules : $existing_rules, $existing_rules );
		}

		self::handle_weekly_reset_scheduling( $existing, $data );

		$response = array(
			'message' => 'The rule was successfully updated.',
		);

		return self::return_response( $response );
	}

	/**
	 * Schedules or unschedules the weekly reset Action Scheduler event based on settings.
	 *
	 * @param array $old_settings Previously saved settings.
	 * @param array $new_settings Incoming settings being saved.
	 */
	private static function handle_weekly_reset_scheduling( $old_settings, $new_settings ) {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		$old_reset = isset( $old_settings['counter_reset_enabled'] ) ? $old_settings['counter_reset_enabled'] : 'no';
		$new_reset = isset( $new_settings['counter_reset_enabled'] ) ? $new_settings['counter_reset_enabled'] : 'no';
		$old_day   = isset( $old_settings['day_of_counter_reset_weekly'] ) ? $old_settings['day_of_counter_reset_weekly'] : 'mon';
		$new_day   = isset( $new_settings['day_of_counter_reset_weekly'] ) ? $new_settings['day_of_counter_reset_weekly'] : 'mon';

		if ( 'weekly' === $new_reset ) {
			$needs_reschedule = ( $old_reset !== $new_reset ) || ( $old_day !== $new_day );
			$not_scheduled    = false === as_next_scheduled_action( 'alg_custom_order_numbers_weekly_reset_event' );

			if ( $needs_reschedule || $not_scheduled ) {
				as_unschedule_all_actions( 'alg_custom_order_numbers_weekly_reset_event' );
				$next_run = self::get_next_weekday_timestamp( $new_day );
				as_schedule_recurring_action( $next_run, DAY_IN_SECONDS * 7, 'alg_custom_order_numbers_weekly_reset_event' );
			}
		} elseif ( 'weekly' === $old_reset ) {
			as_unschedule_all_actions( 'alg_custom_order_numbers_weekly_reset_event' );
		}
	}

	/**
	 * Returns the Unix timestamp for the next occurrence of a given weekday.
	 *
	 * @param string $day_abbr Three-letter lowercase weekday abbreviation (e.g. 'mon', 'fri').
	 * @return int Unix timestamp.
	 */
	private static function get_next_weekday_timestamp( $day_abbr ) {
		$day_map = array(
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
			'sun' => 'Sunday',
		);

		$day_name = isset( $day_map[ $day_abbr ] ) ? $day_map[ $day_abbr ] : 'Monday';

		return strtotime( 'next ' . $day_name, current_time( 'timestamp' ) ); // phpcs:ignore
	}
}

return new Admin_API_Settings();
