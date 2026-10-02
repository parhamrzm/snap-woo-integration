<?php
/**
 * Plugin Name: SnapWoo Integration
 * Description: اتصال فروشگاه ووکامرس به پنل فروشندگان اسنپ شاپ
 * Version: 0.2.0
 * Author: Przm.ir
 * Text Domain: snap-woo-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNAPWOO_VERSION', '0.2.0' );
define( 'SNAPWOO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SNAPWOO_URL', plugin_dir_url( __FILE__ ) );

// بارگذاری کلاس‌ها
require_once SNAPWOO_PATH . 'includes/class-api-client.php';
require_once SNAPWOO_PATH . 'includes/class-admin.php';
require_once SNAPWOO_PATH . 'includes/class-product-meta.php';
require_once SNAPWOO_PATH . 'includes/class-sync-manager.php';

// راه‌اندازی افزونه
add_action( 'plugins_loaded', 'snapwoo_init' );

function snapwoo_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    new SnapWoo_Admin();
    new SnapWoo_Product_Meta();
    new SnapWoo_Sync_Manager();
}