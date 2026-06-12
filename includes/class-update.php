<?php
/**
 * Class Con_Update
 * @since 1.0.0
 * @package CON
 */

namespace Tyche\CON;

defined( 'ABSPATH' ) || exit;

/**
 * Class Con_Update
 *
 * @since 1.0.0
 */
class Update {

	public static function maybe_update_settings() {
		$current_version = get_option( 'alg_custom_order_numbers_version', '1.19.0' );

		if ( $current_version !== CON_VERSION ) {
			self::migrate_settings();
			update_option( 'alg_custom_order_numbers_version', CON_VERSION );
		}
	}

	public static function migrate_settings() {
		$general_settings = array();

		$settings_keys = array(
			'enabled',
			'counter_type',
			'include_character_enabled',
			'counter',
			'counter_reset_enabled',
			'day_of_counter_reset_weekly',
			'counter_reset_counter_value',
			'min_width',
			'settings_to_apply',
			'settings_to_apply_from_date',
			'settings_to_apply_from_order_id',
			'order_tracking_enabled',
			'manual_enabled',
			'hide_menu_for_roles',
			'hide_tab_for_roles',
			'include_character_enabled',
			'template',
		);

		$role_keys = array( 'hide_menu_for_roles', 'hide_tab_for_roles' );

		foreach ( $settings_keys as $key ) {
			$option_name = 'alg_wc_custom_order_numbers_' . $key;
			$value       = get_option( $option_name, '' );

			if ( in_array( $key, $role_keys, true ) ) {
				$roles_array = array();
				if ( is_array( $value ) ) {
					global $wp_roles;
					$all_roles = $wp_roles ? $wp_roles->roles : array();
					foreach ( $value as $role_slug ) {
						$roles_array[] = array(
							'value' => $role_slug,
							'label' => isset( $all_roles[ $role_slug ] )
								? translate_user_role( $all_roles[ $role_slug ]['name'] )
								: $role_slug,
						);
					}
				}
				$general_settings[ $key ] = $roles_array;
			} elseif ( 'template' === $key ) {
				$general_settings['custom_order_numbers_template'] = $value;
			} elseif ( 'counter_reset_enabled' === $key ) {
				$general_settings['counter_reset_enabled'] = $value;
			} elseif ( 'settings_to_apply_from_date' === $key ) {
				$general_settings[ $key ] = self::normalize_date_format( $value );
			} else {
				$general_settings[ $key ] = self::normalize_yes_no( $value );
			}
		}

		$general_settings['enable_prefix_suffix'] = true;
		$general_settings['prefix_suffix_rules']  = array(
			array(
				'condition_type'  => 'custom',
				'condition_value' => array(),
				'prefix'          => get_option( 'alg_wc_custom_order_numbers_prefix', '' ),
				'suffix'          => '',
				'sequential'      => false,
				'custom_counter'  => 1,
			),
			array(
				'condition_type'  => 'date',
				'condition_value' => array(),
				'prefix'          => get_option( 'alg_wc_custom_order_numbers_date_prefix', '' ),
				'suffix'          => '',
				'sequential'      => false,
				'custom_counter'  => 1,
			),
		);

		update_option( 'con_general_settings', $general_settings );
	}

	/**
	 * Converts a date string from m/d/Y (old format) to Y-m-d (new format).
	 * Passes through values already in Y-m-d format unchanged.
	 */
	public static function normalize_date_format( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}
		$date = \DateTime::createFromFormat( 'm/d/Y', $value );
		if ( $date && $date->format( 'm/d/Y' ) === $value ) {
			return $date->format( 'Y-m-d' );
		}
		return $value;
	}

	public static function normalize_yes_no( $value ) {
		if ( $value === 'yes' ) return true;
		if ( $value === 'no' ) return false;
		return $value;
	}

}

return new Update();