<?php
/**
 * Custom Order Numbers for WooCommerce - General Section Settings
 *
 * @version 1.2.3
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-Order-Numbers-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers_Settings_General' ) ) :

	/**
	 * General Settings.
	 */
	class Alg_WC_Custom_Order_Numbers_Settings_General extends Alg_WC_Custom_Order_Numbers_Settings_Section {

		/**
		 * Constructor.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function __construct() {
			$this->id   = '';
			$this->desc = __( 'General', 'custom-order-numbers-for-woocommerce' );
			parent::__construct();
			add_action( 'admin_head', array( $this, 'add_tool_button_class_style' ) );
		}

		/**
		 * Add_tool_button_class_style.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function add_tool_button_class_style() {
			echo '<style type="text/css">';
			echo '#alg-tool-button { ';
			echo 'background: #ba0000; border-color: #aa0000; text-shadow: 0 -1px 1px #990000,1px 0 1px #990000,0 1px 1px #990000,-1px 0 1px #990000; box-shadow: 0 1px 0 #990000;';
			echo ' }';
			echo '</style>';
		}

		/**
		 * Add_settings.
		 *
		 * @param array $settings - Settings to be displayed.
		 *
		 * @version 1.2.3
		 * @since   1.0.0
		 * @todo    [dev] add `alg_wc_custom_order_numbers_counter_previous_order_date` as `hidden` field (for proper settings reset)
		 */
		public function add_settings( $settings ) {

			global $wp_roles;
			$user_roles          = array();
			$user_roles_no_admin = array();
			foreach ( apply_filters( 'editable_roles', ( isset( $wp_roles->roles ) ? $wp_roles->roles : array() ) ) as $role_key => $role ) {
				$user_roles[ $role_key ] = $role['name'];
				if ( ! in_array( $role_key, array( 'administrator', 'super_admin' ), true ) ) {
					$user_roles_no_admin[ $role_key ] = $role['name'];
				}
			}

			$settings = array_merge(
				array(
					array(
						'title' => __( 'Custom Order Numbers Options', 'custom-order-numbers-for-woocommerce' ),
						'type'  => 'title',
						'desc'  => __( 'Enable sequential order numbering, set custom number prefix, suffix and number width.', 'custom-order-numbers-for-woocommerce' ),
						'id'    => 'alg_wc_custom_order_numbers_options',
					),
					array(
						'title'    => __( 'WooCommerce Custom Order Numbers', 'custom-order-numbers-for-woocommerce' ),
						'desc'     => '<strong>' . __( 'Enable plugin', 'custom-order-numbers-for-woocommerce' ) . '</strong>',
						'desc_tip' => __( 'Custom Order Numbers for WooCommerce.', 'custom-order-numbers-for-woocommerce' ),
						'id'       => 'alg_wc_custom_order_numbers_enabled',
						'default'  => 'yes',
						'type'     => 'checkbox',
					),
					array(
						'title'   => __( 'Order numbers counter', 'custom-order-numbers-for-woocommerce' ),
						'id'      => 'alg_wc_custom_order_numbers_counter_type',
						'default' => 'sequential',
						'type'    => 'select',
						'options' => array(
							'sequential' => __( 'Sequential', 'custom-order-numbers-for-woocommerce' ),
							'order_id'   => __( 'Order ID', 'custom-order-numbers-for-woocommerce' ),
							'hash_crc32' => __( 'Pseudorandom - crc32 Hash (max 10 digits)', 'custom-order-numbers-for-woocommerce' ),
						),
					),
					array(
						'title'    => __( 'Sequential: Next order number', 'custom-order-numbers-for-woocommerce' ),
						'desc_tip' => __( 'Next new order will be given this number.', 'custom-order-numbers-for-woocommerce' ) . ' ' .
							__( 'Use "Renumerate Orders tool" for existing orders.', 'custom-order-numbers-for-woocommerce' ) . ' ' .
							__( 'This will be ignored if sequential order numbering is disabled.', 'custom-order-numbers-for-woocommerce' ),
						'id'       => 'alg_wc_custom_order_numbers_counter',
						'default'  => 1,
						'type'     => 'number',
					),
					array(
						'title'    => __( 'Sequential: Reset counter', 'custom-order-numbers-for-woocommerce' ),
						'desc_tip' => __( 'This will be ignored if sequential order numbering is disabled.', 'custom-order-numbers-for-woocommerce' ),
						'id'       => 'alg_wc_custom_order_numbers_counter_reset_enabled',
						'default'  => 'no',
						'type'     => 'select',
						'options'  => array(
							'no'      => __( 'Disabled', 'custom-order-numbers-for-woocommerce' ),
							'daily'   => __( 'Daily', 'custom-order-numbers-for-woocommerce' ),
							'monthly' => __( 'Monthly', 'custom-order-numbers-for-woocommerce' ),
							'yearly'  => __( 'Yearly', 'custom-order-numbers-for-woocommerce' ),
						),
					),
					array(
						'desc'     => '<br>' . __( 'Reset counter value.', 'custom-order-numbers-for-woocommerce' ),
						'desc_tip' => __( 'Counter value to reset to.', 'custom-order-numbers-for-woocommerce' ) . ' ' .
							__( 'This will be ignored if "Sequential: Reset counter" option is set to "Disabled".', 'custom-order-numbers-for-woocommerce' ),
						'id'       => 'alg_wc_custom_order_numbers_counter_reset_counter_value',
						'default'  => 1,
						'type'     => 'number',
					),
					array(
						'title'    => __( 'Order number custom prefix', 'custom-order-numbers-for-woocommerce' ),
						'desc_tip' => __( 'Prefix before order number (optional). This will change the prefixes for all existing orders.', 'custom-order-numbers-for-woocommerce' ),
						'id'       => 'alg_wc_custom_order_numbers_prefix',
						'default'  => '',
						'type'     => 'text',
					),
					array(
						'title'             => __( 'Order number date prefix', 'custom-order-numbers-for-woocommerce' ),
						'desc'              => apply_filters(
							'alg_wc_custom_order_numbers',
							'<br>' . sprintf(
								'You will need <a href="%s" target="_blank">%s</a> plugin to set this option.',
								'https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',
								'Custom Order Numbers for WooCommerce Pro'
							),
							'settings'
						),
						'desc_tip'          => __( 'Date prefix before order number (optional). This will change the prefixes for all existing orders. Value is passed directly to PHP `date` function, so most of PHP date formats can be used. The only exception is using `\` symbol in date format, as this symbol will be excluded from date. Try: Y-m-d- or mdy.', 'custom-order-numbers-for-woocommerce' ),
						'id'                => 'alg_wc_custom_order_numbers_date_prefix',
						'default'           => '',
						'type'              => 'text',
						'custom_attributes' => apply_filters( 'alg_wc_custom_order_numbers', array( 'readonly' => 'readonly' ), 'settings' ),
					),
					array(
						'title'             => __( 'Order number width', 'custom-order-numbers-for-woocommerce' ),
						'desc'              => apply_filters(
							'alg_wc_custom_order_numbers',
							'<br>' . sprintf(
								'You will need <a href="%s" target="_blank">%s</a> plugin to set this option.',
								'https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',
								'Custom Order Numbers for WooCommerce Pro'
							),
							'settings'
						),
						'desc_tip'          => __( 'Minimum width of number without prefix (zeros will be added to the left side). This will change the minimum width of order number for all existing orders. E.g. set to 5 to have order number displayed as 00001 instead of 1. Leave zero to disable.', 'custom-order-numbers-for-woocommerce' ),
						'id'                => 'alg_wc_custom_order_numbers_min_width',
						'default'           => 0,
						'type'              => 'number',
						'custom_attributes' => apply_filters( 'alg_wc_custom_order_numbers', array( 'readonly' => 'readonly' ), 'settings' ),
					),
					array(
						'title'             => __( 'Order number custom suffix', 'custom-order-numbers-for-woocommerce' ),
						'desc'              => apply_filters(
							'alg_wc_custom_order_numbers',
							'<br>' . sprintf(
								'You will need <a href="%s" target="_blank">%s</a> plugin to set this option.',
								'https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',
								'Custom Order Numbers for WooCommerce Pro'
							),
							'settings'
						),
						'desc_tip'          => __( 'Suffix after order number (optional). This will change the suffixes for all existing orders.', 'custom-order-numbers-for-woocommerce' ),
						'id'                => 'alg_wc_custom_order_numbers_suffix',
						'default'           => '',
						'type'              => 'text',
						'custom_attributes' => apply_filters( 'alg_wc_custom_order_numbers', array( 'readonly' => 'readonly' ), 'settings' ),
					),
					array(
						'title'             => __( 'Order number date suffix', 'custom-order-numbers-for-woocommerce' ),
						'desc'              => apply_filters(
							'alg_wc_custom_order_numbers',
							'<br>' . sprintf(
								'You will need <a href="%s" target="_blank">%s</a> plugin to set this option.',
								'https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',
								'Custom Order Numbers for WooCommerce Pro'
							),
							'settings'
						),
						'desc_tip'          => __( 'Date suffix after order number (optional). This will change the suffixes for all existing orders. Value is passed directly to PHP `date` function, so most of PHP date formats can be used. The only exception is using `\` symbol in date format, as this symbol will be excluded from date. Try: Y-m-d- or mdy.', 'custom-order-numbers-for-woocommerce' ),
						'id'                => 'alg_wc_custom_order_numbers_date_suffix',
						'default'           => '',
						'type'              => 'text',
						'custom_attributes' => apply_filters( 'alg_wc_custom_order_numbers', array( 'readonly' => 'readonly' ), 'settings' ),
					),
					array(
						'title'             => __( 'Order number template', 'custom-order-numbers-for-woocommerce' ),
						'desc'              => '<br>' . sprintf(
							// translators: Merge tags which can be used in the setting and will be replaced with actual values.
							__( 'Replaced values: %s.', 'custom-order-numbers-for-woocommerce' ),
							'<code>' . implode( '</code>, <code>', array( '{prefix}', '{date_prefix}', '{number}', '{suffix}', '{date_suffix}' ) ) . '</code>'
						) .
							apply_filters(
								'alg_wc_custom_order_numbers',
								'<br>' . sprintf(
									'You will need <a href="%s" target="_blank">%s</a> plugin to set this option.',
									'https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',
									'Custom Order Numbers for WooCommerce Pro'
								),
								'settings'
							),
						'id'                => 'alg_wc_custom_order_numbers_template',
						'default'           => '{prefix}{date_prefix}{number}{suffix}{date_suffix}',
						'type'              => 'text',
						'custom_attributes' => apply_filters( 'alg_wc_custom_order_numbers', array( 'readonly' => 'readonly' ), 'settings' ),
					),
					array(
						'title'   => __( 'Enable order tracking by custom number', 'custom-order-numbers-for-woocommerce' ),
						'desc'    => __( 'Enable', 'custom-order-numbers-for-woocommerce' ),
						'id'      => 'alg_wc_custom_order_numbers_order_tracking_enabled',
						'default' => 'yes',
						'type'    => 'checkbox',
					),
					array(
						'title'   => __( 'Enable order admin search by custom number', 'custom-order-numbers-for-woocommerce' ),
						'desc'    => __( 'Enable', 'custom-order-numbers-for-woocommerce' ),
						'id'      => 'alg_wc_custom_order_numbers_search_by_custom_number_enabled',
						'default' => 'yes',
						'type'    => 'checkbox',
					),
					array(
						'title'             => __( 'Manual order number counter', 'custom-order-numbers-for-woocommerce' ),
						'desc'              => __( 'Enable', 'custom-order-numbers-for-woocommerce' ),
						'desc_tip'          => __( 'This will add "Order Number" meta box to each order\'s edit page. "Order Numbers Counter" must be set to "Sequential".', 'custom-order-numbers-for-woocommerce' ) .
							apply_filters(
								'alg_wc_custom_order_numbers',
								'<br>' . sprintf(
									'You will need <a href="%s" target="_blank">%s</a> plugin to set this option.',
									'https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=conupgradetopro&utm_medium=link&utm_campaign=CustomOrderNumbersLite',
									'Custom Order Numbers for WooCommerce Pro'
								),
								'settings'
							),
						'id'                => 'alg_wc_custom_order_numbers_manual_enabled',
						'default'           => 'no',
						'type'              => 'checkbox',
						'custom_attributes' => apply_filters( 'alg_wc_custom_order_numbers', array( 'disabled' => 'disabled' ), 'settings' ),
					),
					array(
						'title'    => __( 'Hide "Renumerate Orders" admin menu for roles', 'custom-order-numbers-for-woocommerce' ),
						'desc_tip' => __( 'Hide "Renumerate Orders" admin menu for selected user roles.', 'custom-order-numbers-for-woocommerce' ) . ' ' .
							__( 'All user roles are listed here - even those which do not see the menu by default.', 'custom-order-numbers-for-woocommerce' ),
						'id'       => 'alg_wc_custom_order_numbers_hide_menu_for_roles',
						'default'  => array(),
						'type'     => 'multiselect',
						'class'    => 'chosen_select',
						'options'  => $user_roles,
					),
					array(
						'title'    => __( 'Hide "Custom Order Numbers" admin settings tab for roles', 'custom-order-numbers-for-woocommerce' ),
						'desc_tip' => __( 'Hide "Custom Order Numbers" admin settings tab for selected user roles.', 'custom-order-numbers-for-woocommerce' ) . ' ' .
							__( 'Tab can not be hidden for administrators.', 'custom-order-numbers-for-woocommerce' ) . ' ' .
							__( 'All user roles are listed here - even those which do not see the tab by default.', 'custom-order-numbers-for-woocommerce' ),
						'id'       => 'alg_wc_custom_order_numbers_hide_tab_for_roles',
						'default'  => array(),
						'type'     => 'multiselect',
						'class'    => 'chosen_select',
						'options'  => $user_roles_no_admin,
					),
					array(
						'type' => 'sectionend',
						'id'   => 'alg_wc_custom_order_numbers_options',
					),
					array(
						'title' => __( 'Tools', 'custom-order-numbers-for-woocommerce' ),
						'desc'  => '<a class="button-primary" id="alg-tool-button" title="' . __( 'Tool for existing orders.', 'custom-order-numbers-for-woocommerce' ) . '" ' .
								'href="' . admin_url( 'admin.php?page=alg-wc-renumerate-orders-tools' ) . '">' . __( 'Renumerate Orders tool', 'custom-order-numbers-for-woocommerce' ) .
							'</a>',
						'id'    => 'alg_wc_custom_order_numbers_tools',
						'type'  => 'title',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'alg_wc_custom_order_numbers_tools',
					),
				),
				$settings
			);
			return $settings;
		}

	}

endif;

return new Alg_WC_Custom_Order_Numbers_Settings_General();
