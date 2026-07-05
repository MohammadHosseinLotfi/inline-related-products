<?php
/**
 * Plugin Name:       Inline Related Products
 * Description:       نمایش محصولات مرتبط ووکامرس داخل متن مقاله (کارت تکی / گرید / اسلایدر) با شورتکد یا درج خودکار بعد از هدینگ.
 * Version:           2.0.0
 * Author:            Stockifa
 * Text Domain:       irp
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.5
 * License:           GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IRP_VERSION', '2.0.0' );
define( 'IRP_FILE', __FILE__ );
define( 'IRP_DIR', plugin_dir_path( __FILE__ ) );
define( 'IRP_URL', plugin_dir_url( __FILE__ ) );
define( 'IRP_META_KEY', '_irp_blocks' );

// اعلام سازگاری با HPOS (این افزونه با سفارش‌ها کاری ندارد، اما برای جلوگیری از هشدار ادمین اعلام می‌کنیم)
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', IRP_FILE, true );
	}
} );

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'irp', false, dirname( plugin_basename( IRP_FILE ) ) . '/languages' );

	// نیازمند ووکامرس
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'افزونه «Inline Related Products» برای کار به ووکامرس فعال نیاز دارد.', 'irp' )
				. '</p></div>';
		} );
		return;
	}

	require_once IRP_DIR . 'includes/class-irp-metabox.php';
	require_once IRP_DIR . 'includes/class-irp-rest.php';
	require_once IRP_DIR . 'includes/class-irp-frontend.php';

	new IRP_Metabox();
	new IRP_Rest();
	new IRP_Frontend();
} );
