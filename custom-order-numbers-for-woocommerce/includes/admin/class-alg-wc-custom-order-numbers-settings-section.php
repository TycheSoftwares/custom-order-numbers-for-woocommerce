<?php
/**
 * Custom Order Numbers for WooCommerce - Section Settings
 *
 * @version 1.2.0
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-Order-Numbers-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers_Settings_Section' ) ) :

	/**
	 * Settings class.
	 */
	class Alg_WC_Custom_Order_Numbers_Settings_Section {

		/**
		 * Constructor.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function __construct() {
			add_filter( 'woocommerce_get_sections_alg_wc_custom_order_numbers', array( $this, 'settings_section' ) );
			add_filter( 'woocommerce_get_settings_alg_wc_custom_order_numbers_' . $this->id, array( $this, 'get_settings' ), PHP_INT_MAX );
			add_action( 'init', array( $this, 'add_settings_hook' ) );
		}

		/**
		 * Add_settings_hook.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_settings_hook() {
			add_filter( 'alg_custom_order_numbers_settings_' . $this->id, array( $this, 'add_settings' ) );
		}

		/**
		 * Get_settings.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function get_settings() {
			return array_merge(
				apply_filters( 'alg_custom_order_numbers_settings_' . $this->id, array() ),
				array(
					array(
						'title' => __( 'Reset Settings', 'custom-order-numbers-for-woocommerce' ),
						'type'  => 'title',
						'id'    => 'alg_wc_custom_order_numbers_' . $this->id . '_reset_options',
					),
					array(
						'title'   => __( 'Reset section settings', 'custom-order-numbers-for-woocommerce' ),
						'desc'    => '<strong>' . __( 'Reset', 'custom-order-numbers-for-woocommerce' ) . '</strong>',
						'id'      => 'alg_wc_custom_order_numbers_' . $this->id . '_reset',
						'default' => 'no',
						'type'    => 'checkbox',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'alg_wc_custom_order_numbers_' . $this->id . '_reset_options',
					),
				)
			);
		}

		/**
		 * Settings_section.
		 *
		 * @param array $sections - List of sections (ID & Desc).
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function settings_section( $sections ) {
			$sections[ $this->id ] = $this->desc;
			return $sections;
		}

	}

endif;
