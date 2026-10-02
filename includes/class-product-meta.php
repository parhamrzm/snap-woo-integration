<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SnapWoo_Product_Meta {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'wp_ajax_snapwoo_sync_single', array( $this, 'ajax_sync_single' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_meta_box() {
        add_meta_box(
            'snapwoo_product_box',
            '⚡ وضعیت اسنپ‌شاپ',
            array( $this, 'render_meta_box' ),
            'product',
            'side',
            'high'
        );
    }

    public function enqueue_scripts( $hook ) {
        global $post_type;

        if ( ( $hook === 'post.php' || $hook === 'post-new.php' ) && $post_type === 'product' ) {
            wp_enqueue_style(
                'snapwoo-admin',
                SNAPWOO_URL . 'assets/css/admin.css',
                array(),
                SNAPWOO_VERSION
            );

            wp_enqueue_script(
                'snapwoo-admin',
                SNAPWOO_URL . 'assets/js/admin.js',
                array( 'jquery' ),
                SNAPWOO_VERSION,
                true
            );

            wp_localize_script( 'snapwoo-admin', 'snapwoo_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'snapwoo_sync' ),
                'strings'  => array(
                    'syncing' => 'در حال همگام‌سازی...',
                    'success' => 'همگام‌سازی با موفقیت انجام شد.',
                    'error'   => 'خطا در همگام‌سازی: ',
                ),
            ) );
        }
    }

    public function render_meta_box( $post ) {
        $product = wc_get_product( $post->ID );

        if ( ! $product ) {
            echo '<p>محصول یافت نشد.</p>';
            return;
        }

        $sku              = $product->get_sku();
        $woo_price        = (float) $product->get_price();
        $snap_price_saved = get_post_meta( $post->ID, '_snapwoo_price', true );
        $snap_stock       = get_post_meta( $post->ID, '_snapwoo_stock', true );
        $last_sync        = get_post_meta( $post->ID, '_snapwoo_last_sync', true );
        $is_registered    = (bool) get_post_meta( $post->ID, '_snapwoo_registered', true );

        $rule          = snapwoo_get_applicable_pricing_rule( $post->ID );
        $preview_price = snapwoo_calculate_snap_price( $woo_price, $post->ID );

        $has_api = ! empty( get_option( 'snapwoo_api_key', '' ) );

        ?>
        <div class="snapwoo-meta-box">
            <?php if ( empty( $sku ) ) : ?>
                <div class="snapwoo-alert snapwoo-alert-warning">
                    ⚠️ برای این محصول SKU تعریف نشده است. همگام‌سازی نیاز به SKU دارد.
                </div>
            <?php else : ?>

                <div class="snapwoo-mb-row">
                    <span class="snapwoo-mb-label">SKU</span>
                    <span class="snapwoo-mb-value"><code><?php echo esc_html( $sku ); ?></code></span>
                </div>

                <div class="snapwoo-mb-row">
                    <span class="snapwoo-mb-label">وضعیت ثبت</span>
                    <span class="snapwoo-mb-value">
                        <?php if ( $is_registered ) : ?>
                            <span class="snapwoo-mb-badge snapwoo-badge-ok">✓ ثبت‌شده</span>
                        <?php else : ?>
                            <span class="snapwoo-mb-badge snapwoo-badge-default">ثبت نشده</span>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ( $is_registered ) : ?>
                    <p class="snapwoo-mb-meta">
                        این محصول در همگام‌سازی خودکار شرکت می‌کند.
                    </p>
                <?php endif; ?>

                <div class="snapwoo-mb-section">
                    <h5 class="snapwoo-mb-section-title">📦 در ووکامرس</h5>
                    <div class="snapwoo-mb-row">
                        <span class="snapwoo-mb-label">قیمت</span>
                        <span class="snapwoo-mb-value"><?php echo wp_kses_post( wc_price( $woo_price ) ); ?></span>
                    </div>
                    <div class="snapwoo-mb-row">
                        <span class="snapwoo-mb-label">موجودی</span>
                        <span class="snapwoo-mb-value">
                            <?php echo $product->get_stock_quantity() !== null
                                ? esc_html( $product->get_stock_quantity() )
                                : 'نامشخص'; ?>
                        </span>
                    </div>
                </div>

                <div class="snapwoo-mb-section snapwoo-mb-highlight">
                    <h5 class="snapwoo-mb-section-title">🎯 پیش‌نمایش ارسال</h5>
                    <div class="snapwoo-mb-row">
                        <span class="snapwoo-mb-label">قیمت ارسالی</span>
                        <span class="snapwoo-mb-value snapwoo-price">
                            <?php echo wp_kses_post( wc_price( $preview_price ) ); ?>
                        </span>
                    </div>

                    <?php if ( $rule['source'] === 'category' ) : ?>
                        <div class="snapwoo-mb-badge snapwoo-badge-cat">
                            🏷️ دسته: <?php echo esc_html( $rule['category'] ); ?>
                        </div>
                    <?php else : ?>
                        <div class="snapwoo-mb-badge snapwoo-badge-default">
                            ⚙️ قاعده پیش‌فرض
                        </div>
                    <?php endif; ?>

                    <div class="snapwoo-mb-meta">
                        <?php if ( $rule['markup'] > 0 ) : ?>
                            شامل <?php echo esc_html( $rule['markup'] ); ?>٪ کارمزد
                        <?php else : ?>
                            بدون کارمزد
                        <?php endif; ?>
                        <?php if ( $rule['round_to'] > 0 ) : ?>
                            &nbsp;•&nbsp;
                            رند به <?php echo esc_html( number_format_i18n( $rule['round_to'] ) ); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="snapwoo-mb-section">
                    <h5 class="snapwoo-mb-section-title">📡 آخرین ارسال</h5>
                    <?php if ( $snap_price_saved || $snap_stock !== '' ) : ?>
                        <div class="snapwoo-mb-row">
                            <span class="snapwoo-mb-label">قیمت</span>
                            <span class="snapwoo-mb-value">
                                <?php echo $snap_price_saved ? wp_kses_post( wc_price( $snap_price_saved ) ) : '—'; ?>
                            </span>
                        </div>
                        <div class="snapwoo-mb-row">
                            <span class="snapwoo-mb-label">موجودی</span>
                            <span class="snapwoo-mb-value">
                                <?php echo $snap_stock !== '' ? esc_html( $snap_stock ) : '—'; ?>
                            </span>
                        </div>
                        <?php if ( $last_sync ) : ?>
                            <div class="snapwoo-mb-meta">
                                <?php echo esc_html( date_i18n( 'Y/m/d H:i', $last_sync ) ); ?>
                            </div>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="snapwoo-mb-meta">هنوز چیزی ارسال نشده.</p>
                    <?php endif; ?>
                </div>

                <?php if ( ! $has_api ) : ?>
                    <div class="snapwoo-alert snapwoo-alert-warning">
                        ⚠️ کلید API تنظیم نشده. ابتدا از صفحه تنظیمات، کلید را وارد کنید.
                    </div>
                <?php endif; ?>

                <button type="button"
                        class="button button-primary snapwoo-sync-btn"
                        data-product-id="<?php echo esc_attr( $post->ID ); ?>"
                        <?php echo $has_api ? '' : 'disabled'; ?>>
                    همگام‌سازی این محصول
                </button>

                <div class="snapwoo-result"></div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function ajax_sync_single() {
        check_ajax_referer( 'snapwoo_sync', 'nonce' );

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $product    = $product_id ? wc_get_product( $product_id ) : null;

        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'محصول یافت نشد.' ) );
        }

        $sku = $product->get_sku();
        if ( empty( $sku ) ) {
            wp_send_json_error( array( 'message' => 'این محصول SKU ندارد.' ) );
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
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        update_post_meta( $product_id, '_snapwoo_price', $snap_price );
        update_post_meta( $product_id, '_snapwoo_stock', $product->get_stock_quantity() );
        update_post_meta( $product_id, '_snapwoo_last_sync', current_time( 'timestamp' ) );
        update_post_meta( $product_id, '_snapwoo_registered', 1 );

        wp_send_json_success( array( 'message' => 'همگام‌سازی با موفقیت انجام شد.' ) );
    }
}