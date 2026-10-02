<?php
/**
 * Plugin Name: SnapWoo Integration
 * Description: اتصال فروشگاه ووکامرس به پنل فروشندگان اسنپ شاپ
 * Version: 0.3.0
 * Author: Przm.ir
 * Text Domain: snap-woo-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNAPWOO_VERSION', '0.3.0' );
define( 'SNAPWOO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SNAPWOO_URL', plugin_dir_url( __FILE__ ) );
define( 'SNAPWOO_CRON_HOOK', 'snapwoo_cron_sync' );
define( 'SNAPWOO_CRON_SCHEDULE', 'snapwoo_custom' );

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

/* ============ Cron Schedule ============ */
add_filter( 'cron_schedules', 'snapwoo_add_cron_schedule' );

function snapwoo_add_cron_schedule( $schedules ) {
    $interval = (int) get_option( 'snapwoo_sync_interval', 60 );
    if ( $interval < 1 ) {
        $interval = 60;
    }

    $schedules[ SNAPWOO_CRON_SCHEDULE ] = array(
        'interval' => $interval * MINUTE_IN_SECONDS,
        'display'  => sprintf( 'هر %d دقیقه (اسنپ‌وو)', $interval ),
    );

    return $schedules;
}

/* ============ Schedule Helpers ============ */
function snapwoo_schedule_cron() {
    if ( ! wp_next_scheduled( SNAPWOO_CRON_HOOK ) ) {
        wp_schedule_event( time() + 60, SNAPWOO_CRON_SCHEDULE, SNAPWOO_CRON_HOOK );
    }
}

function snapwoo_reschedule_cron() {
    wp_clear_scheduled_hook( SNAPWOO_CRON_HOOK );
    wp_schedule_event( time() + 60, SNAPWOO_CRON_SCHEDULE, SNAPWOO_CRON_HOOK );
}

// اطمینان از زمان‌بندی در هر بار لود
add_action( 'init', 'snapwoo_schedule_cron' );

// زمان‌بندی مجدد وقتی بازه تغییر کرد
add_action( 'update_option_snapwoo_sync_interval', 'snapwoo_reschedule_cron' );
add_action( 'add_option_snapwoo_sync_interval', 'snapwoo_reschedule_cron' );

/* ============ Activation / Deactivation ============ */
register_activation_hook( __FILE__, 'snapwoo_activate' );
function snapwoo_activate() {
    snapwoo_schedule_cron();
}

register_deactivation_hook( __FILE__, 'snapwoo_deactivate' );
function snapwoo_deactivate() {
    wp_clear_scheduled_hook( SNAPWOO_CRON_HOOK );
}