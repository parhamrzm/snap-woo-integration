<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SnapWoo_Sync_Manager {

    public function __construct() {
        // Bulk action در لیست محصولات
        add_filter( 'bulk_actions-edit-product', array( $this, 'register_bulk_action' ) );
        add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_action' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'bulk_action_notice' ) );

        // Cron
        add_action( SNAPWOO_CRON_HOOK, array( $this, 'run_cron_sync' ) );
    }

    /* ==================== Bulk Action ==================== */

    /**
     * افزودن گزینه به لیست bulk actions
     */
    public function register_bulk_action( $actions ) {
        $actions['snapwoo_sync'] = 'همگام‌سازی با اسنپ‌شاپ';
        return $actions;
    }

    /**
     * پردازش bulk action
     */
    public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
        if ( $action !== 'snapwoo_sync' ) {
            return $redirect_to;
        }

        $success = 0;
        $failed  = 0;

        foreach ( $post_ids as $product_id ) {
            $result = $this->sync_product( $product_id );

            if ( is_wp_error( $result ) ) {
                $failed++;
            } else {
                $success++;
                update_post_meta( $product_id, '_snapwoo_registered', 1 );
            }
        }

        $redirect_to = add_query_arg( array(
            'snapwoo_bulk_done'    => 1,
            'snapwoo_bulk_success' => $success,
            'snapwoo_bulk_failed'  => $failed,
        ), $redirect_to );

        return $redirect_to;
    }

    /**
     * نمایش پیام نتیجه بعد از bulk action
     */
    public function bulk_action_notice() {
        if ( empty( $_GET['snapwoo_bulk_done'] ) ) {
            return;
        }

        $success = isset( $_GET['snapwoo_bulk_success'] ) ? absint( $_GET['snapwoo_bulk_success'] ) : 0;
        $failed  = isset( $_GET['snapwoo_bulk_failed'] ) ? absint( $_GET['snapwoo_bulk_failed'] ) : 0;

        $class = $failed > 0 ? 'notice-warning' : 'notice-success';

        printf(
            '<div class="notice %s is-dismissible"><p>%s</p></div>',
            esc_attr( $class ),
            esc_html( sprintf(
                'همگام‌سازی با اسنپ‌شاپ انجام شد: %d موفق، %d ناموفق.',
                $success,
                $failed
            ) )
        );
    }

    /* ==================== Cron ==================== */

    /**
     * اجرای همگام‌سازی خودکار برای محصولات ثبت‌شده
     */
    public function run_cron_sync() {
        $products = wc_get_products( array(
            'limit'      => -1,
            'status'     => 'publish',
            'meta_query' => array(
                array(
                    'key'   => '_snapwoo_registered',
                    'value' => '1',
                ),
            ),
        ) );

        foreach ( $products as $product ) {
            $this->sync_product( $product->get_id() );
        }
    }

    /* ==================== Core Sync ==================== */

    /**
     * همگام‌سازی یک محصول (مشترک بین bulk action، cron و متاباکس)
     */
    public function sync_product( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'no_product', 'محصول یافت نشد.' );
        }

        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            return new WP_Error( 'no_sku', 'محصول SKU ندارد.' );
        }

        $woo_price  = (float) $product->get_price();
        $snap_price = snapwoo_calculate_snap_price( $woo_price, $product_id );

        $client = new SnapWoo_API_Client();
        $result = $client->update_product(
            $sku,
            $snap_price,
            $product->get_stock_quantity()
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        update_post_meta( $product_id, '_snapwoo_price', $snap_price );
        update_post_meta( $product_id, '_snapwoo_stock', $product->get_stock_quantity() );
        update_post_meta( $product_id, '_snapwoo_last_sync', current_time( 'timestamp' ) );

        return true;
    }
}