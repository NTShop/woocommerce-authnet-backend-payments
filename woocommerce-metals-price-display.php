<?php
/**
 * Plugin Name: WooCommerce Metals Price Display
 * Plugin URI: https://github.com/NTShop
 * Description: Inserts metal prices into specific HTML elements on a page based on CSS class name and updates the prices every 5 minutes. Requires the use of IgniteWoo's Precious Metals plugin.
 * Version: 1.0
 * Author: Mark Edwards
 * Author URI: https://github.com/NTShop
 * Requires at least: 5.3
 * Text Domain: woocommerce-metals-price-display
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
 * @package Metals Price Display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads main class file if WooCommerce is active on the site.
 *
 * @return void
 */
function wc_backend_authoret_load_if_wc_active() {
	if ( ! class_exists( 'woocommerce' ) ) {
		return;
	}
	require_once dirname( __FILE__ ) . '/class-wc-metals-price-display.php';
}
add_action( 'init', 'wc_backend_authoret_load_if_wc_active', 0 );
