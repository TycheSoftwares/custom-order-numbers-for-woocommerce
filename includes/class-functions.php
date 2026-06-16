<?php
/**
 * CON Functions Class
 *
 * Helper functions used across the CON plugin.
 *
 * @author  Tyche Softwares
 * @package CON/Functions
 */

namespace Tyche\CON;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * CON Functions.
 */
class Functions {

	/**
	 * Fetch available WooCommerce payment gateways.
	 *
	 * @return array
	 */
	public static function get_payment_gateways() {
		$gateways = array();

		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return $gateways;
		}

		$available_gateways = WC()->payment_gateways()->payment_gateways();

		foreach ( $available_gateways as $gateway ) {
			$gateways[] = (object) array(
				'value' => $gateway->id,
				'label' => $gateway->get_title(),
			);
		}

		return $gateways;
	}

	/**
	 * Fetch available WordPress user roles.
	 *
	 * @return array
	 */
	public static function get_user_roles() {
		$roles = array();
		$wp_roles = wp_roles();

		if ( ! $wp_roles || empty( $wp_roles->roles ) ) {
			return $roles;
		}

		foreach ( $wp_roles->roles as $role_key => $role_data ) {
			$roles[] = (object) array(
				'value' => $role_key,
				'label' => translate_user_role( $role_data['name'] ),
			);
		}

		return $roles;
	}

	/**
	 * Fetch a CON general setting value by key.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional default value when key does not exist.
	 *
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return $default;
		}

		$settings = get_option( 'con_general_settings', array() );

		if ( ! is_array( $settings ) ) {
			return $default;
		}

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Fetch all condition values for a given condition type from the prefix/suffix rules.
	 *
	 * Each condition value is stored as an object with `label` and `value` keys.
	 * This method extracts and returns a unique, re-indexed array of just the `value`
	 * entries across all rules whose `condition_type` matches the requested type.
	 *
	 * @param string $condition_type Condition type to filter by (e.g. 'product', 'category',
	 *                               'payment_method', 'user_role', 'country').
	 * @param mixed  $default        Value to return when no matching rules or values are found.
	 *
	 * @return array|mixed
	 */
	public static function get_rule_values_by_type( $condition_type, $default = array() ) {
		if ( ! is_string( $condition_type ) || '' === $condition_type ) {
			return $default;
		}

		$rules = self::get_setting( 'prefix_suffix_rules', array() );
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return $default;
		}

		$values = array();

		foreach ( $rules as $rule ) {
			if ( ! isset( $rule['condition_type'] ) || $rule['condition_type'] !== $condition_type ) {
				continue;
			}

			if ( ! empty( $rule['condition_value'] ) && is_array( $rule['condition_value'] ) ) {
				foreach ( $rule['condition_value'] as $item ) {
					if ( is_array( $item ) && isset( $item['value'] ) ) {
						$values[] = $item['value'];
					}
				}
			}
		}

		return ! empty( $values ) ? array_values( array_unique( $values ) ) : $default;
	}

	/**
	 * Fetch the prefix value from the first rule matching a given condition type.
	 *
	 * @param string $condition_type Condition type to filter by (e.g. 'product', 'category',
	 *                               'payment_method', 'user_role', 'country').
	 * @param mixed  $default        Value to return when no matching rule is found.
	 *
	 * @return string|mixed
	 */
	public static function get_rule_prefix_by_type( $condition_type, $default = '' ) {
		if ( ! is_string( $condition_type ) || '' === $condition_type ) {
			return $default;
		}

		$rules = self::get_setting( 'prefix_suffix_rules', array() );

		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return $default;
		}

		foreach ( $rules as $rule ) {
			if ( isset( $rule['condition_type'] ) && $rule['condition_type'] === $condition_type ) {
				return isset( $rule['prefix'] ) ? $rule['prefix'] : $default;
			}
		}

		return $default;
	}

	/**
	 * Fetch the suffix value from the first rule matching a given condition type.
	 *
	 * @param string $condition_type Condition type to filter by (e.g. 'product', 'category',
	 *                               'payment_method', 'user_role', 'country').
	 * @param mixed  $default        Value to return when no matching rule is found.
	 *
	 * @return string|mixed
	 */
	public static function get_rule_suffix_by_type( $condition_type, $default = '' ) {
		if ( ! is_string( $condition_type ) || '' === $condition_type ) {
			return $default;
		}

		$rules = self::get_setting( 'prefix_suffix_rules', array() );

		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return $default;
		}

		foreach ( $rules as $rule ) {
			if ( isset( $rule['condition_type'] ) && $rule['condition_type'] === $condition_type ) {
				return isset( $rule['suffix'] ) ? $rule['suffix'] : $default;
			}
		}

		return $default;
	}

	/**
	 * Update a key in the prefix/suffix rule matched by condition type and prefix.
	 *
	 * @param string $condition_type Condition type identifying the rule (e.g. 'product', 'category').
	 * @param string $prefix         Prefix value identifying the rule.
	 * @param string $key            The rule key to update (e.g. 'custom_counter', 'suffix').
	 * @param mixed  $value          The value to set.
	 *
	 * @return bool True if a matching rule was found and the option saved, false otherwise.
	 */
	public static function update_rule_key( $condition_type, $prefix, $key, $value ) {
		if ( ! is_string( $condition_type ) || '' === $condition_type || ! is_string( $key ) || '' === $key ) {
			return false;
		}

		$rules = self::get_setting( 'prefix_suffix_rules', array() );

		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return false;
		}

		$matched = false;

		foreach ( $rules as &$rule ) {
			if (
				isset( $rule['condition_type'] ) && $rule['condition_type'] === $condition_type &&
				isset( $rule['prefix'] ) && $rule['prefix'] === $prefix
			) {
				$rule[ $key ] = $value;
				$matched       = true;
				break;
			}
		}
		unset( $rule );

		if ( ! $matched ) {
			return false;
		}

		return self::update_setting( 'prefix_suffix_rules', $rules );
	}

	/**
	 * Update a CON general setting value by key.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value to store.
	 *
	 * @return bool True if the option was updated, false otherwise.
	 */
	public static function update_setting( $key, $value ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return false;
		}

		$settings = get_option( 'con_general_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings[ $key ] = $value;

		return update_option( 'con_general_settings', $settings );
	}

	/**
	 * Check if HPOS is enabled or not.
	 *
	 * @since 1.8.0
	 * return boolean true if enabled else false
	 */
	public static function con_wc_hpos_enabled() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				return true;
			}
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
	public static function add_sku_number( $order_id, $order ) {
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
}
