<?php
/**
 * Plugin Name: WooCommerce Authorize.net Backend Payments
 * Plugin URI: https://github.com/NTShop/
 * Description: Adds a payment form to WooCommerce orders in the admin area to take payments via Authorize.net. Requires the plugin "Authorize.Net CIM for WooCommerce" by CardPay Solutions.
 * Version: 1.0
 * Author: Mark Edwards
 * Author URI: https://github.com/NTShop/
 * Text Domain: woocommerce-authnet-backend-payments
 * Domain Path: languages/
 * License: GPLv3
 * WC requires at least: 4.0
 * WC tested up to: 6.5
 *
 * Copyright (c) 2022 - Mark Edwards - All Rights Reserved
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package Authnet Backend Payments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Cardpay_Authnet' ) ) {
	return;
}

const PLUGIN_FILE = __FILE__;

/**
 * Loads main class file if WooCommerce is active on the site.
 *
 * @return void
 */
function wc_authnet_backend_load_if_wc_active() {
	if ( ! class_exists( 'woocommerce' ) ) {
		return;
	}
	require_once dirname( __FILE__ ) . '/includes/class-wc-authnet-backend-payments.php';
}
add_action( 'init', 'wc_authnet_backend_load_if_wc_active', 0 );
