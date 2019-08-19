<?php
/**
 * Plugin Name: Custom Order Numbers for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/
 * Description: Custom order numbers for WooCommerce.
 * Version: 1.2.7
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: custom-order-numbers-for-woocommerce
 * Domain Path: /langs
 * Copyright: � 2018 Tyche Softwares
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 3.6.5
 *
 * @package Custom-Order-Numbers-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Numbers' ) ) {
	include_once 'class-alg-wc-custom-order-numbers.php';
}
