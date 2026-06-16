<?php
/**
 * CON Hooks Class
 *
 * Handles the hooks for the CON plugin.
 *
 * @author  Tyche Softwares
 * @package CON/Hooks
 */

namespace Tyche\CON;

defined( 'ABSPATH' ) || exit;

/**
 * CON Hooks.
 */
class Hooks {

	public static function init() {
		add_action(
			'before_woocommerce_init',
			function () {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CON_FILE, true );
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', CON_FILE, true );
				}
			},
			999
		);

		add_filter( 'alg_wc_custom_order_numbers', array( 'Tyche\CON\Core', 'alg_wc_custom_order_numbers' ), 9999, 3 );
	}
}