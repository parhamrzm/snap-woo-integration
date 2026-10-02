<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SnapWoo_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * افزودن منو به پیشخوان وردپرس
     */
    public function add_menu() {
        add_menu_page(
            'تنظیمات اسنپ‌وو',
            'اسنپ‌وو',
            'manage_options',
            'snapwoo',
            array( $this, 'render_settings_page' ),
            'dashicons-update',
            56
        );
    }

    /**
     * بارگذاری اسکریپت و استایل در صفحه تنظیمات
     */
    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_snapwoo' ) {
            return;
        }

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
    }

    /**
     * ثبت تنظیمات
     */
    public function register_settings() {
        register_setting( 'snapwoo_settings', 'snapwoo_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        register_setting( 'snapwoo_settings', 'snapwoo_markup_percent', array(
            'type'              => 'number',
            'sanitize_callback' => 'floatval',
            'default'           => 0,
        ) );

        register_setting( 'snapwoo_settings', 'snapwoo_round_to', array(
            'type'              => 'number',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ) );

        register_setting( 'snapwoo_settings', 'snapwoo_category_rules', array(
            'type'              => 'array',
            'sanitize_callback' => array( $this, 'sanitize_category_rules' ),
            'default'           => array(),
        ) );
    }

    /**
     * پاکسازی قوانین دسته‌بندی قبل از ذخیره
     */
    public function sanitize_category_rules( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $clean = array();
        foreach ( $value as $rule ) {
            if ( empty( $rule['term_id'] ) ) {
                continue;
            }
            $clean[] = array(
                'term_id'  => absint( $rule['term_id'] ),
                'markup'   => isset( $rule['markup'] ) ? floatval( $rule['markup'] ) : 0,
                'round_to' => isset( $rule['round_to'] ) ? absint( $rule['round_to'] ) : 0,
            );
        }
        return $clean;
    }

    /**
     * نمایش صفحه تنظیمات
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $api_key = get_option( 'snapwoo_api_key', '' );
        $message = '';

        if ( isset( $_POST['snapwoo_test_connection'] ) && check_admin_referer( 'snapwoo_test' ) ) {
            $client = new SnapWoo_API_Client();
            $result = $client->test_connection();

            if ( is_wp_error( $result ) ) {
                $message = '<div class="notice notice-error is-dismissible"><p>خطا: '
                    . esc_html( $result->get_error_message() )
                    . '</p></div>';
            } else {
                $message = '<div class="notice notice-success is-dismissible"><p>اتصال با موفقیت برقرار شد!</p></div>';
            }
        }

        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $categories ) ) {
            $categories = array();
        }

        $rules = get_option( 'snapwoo_category_rules', array() );
        if ( ! is_array( $rules ) ) {
            $rules = array();
        }

        ?>
        <div class="wrap snapwoo-wrap">
            <h1>
                <span class="snapwoo-logo">⚡</span>
                تنظیمات اسنپ‌وو
            </h1>
            <p class="snapwoo-subtitle">
                اتصال فروشگاه ووکامرس شما به پنل فروشندگان اسنپ شاپ
            </p>

            <?php echo $message; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'snapwoo_settings' ); ?>

                <!-- کارت ۱: قوانین قیمت‌گذاری -->
                <div class="snapwoo-card">
                    <div class="snapwoo-card-header">
                        <h2>💵 قوانین قیمت‌گذاری در اسنپ شاپ</h2>
                        <p class="snapwoo-card-desc">
                            تعیین کنید قیمت محصولات چگونه از ووکامرس به اسنپ شاپ ارسال شود؛ هم به‌صورت پیش‌فرض برای همه، هم به‌صورت اختصاصی برای هر دسته‌بندی.
                        </p>
                    </div>
                    <div class="snapwoo-card-body">

                        <h3 class="snapwoo-section-title">قاعده پیش‌فرض</h3>
                        <p class="snapwoo-section-desc">
                            این قاعده برای محصولاتی اعمال می‌شود که در هیچ‌یک از دسته‌بندی‌های زیر نباشند.
                        </p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="snapwoo_markup_percent">درصد افزایش قیمت</label>
                                </th>
                                <td>
                                    <div class="snapwoo-input-group">
                                        <input type="number"
                                               id="snapwoo_markup_percent"
                                               name="snapwoo_markup_percent"
                                               value="<?php echo esc_attr( get_option( 'snapwoo_markup_percent', 0 ) ); ?>"
                                               step="0.1"
                                               min="0" />
                                        <span class="snapwoo-input-suffix">%</span>
                                    </div>
                                    <p class="description">
                                        قیمت ووکامرس به این درصد افزایش داده می‌شود. مثال: ۱۰ یعنی ۱۰٪ گران‌تر.
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="snapwoo_round_to">رند کردن قیمت به</label>
                                </th>
                                <td>
                                    <div class="snapwoo-input-group">
                                        <input type="number"
                                               id="snapwoo_round_to"
                                               name="snapwoo_round_to"
                                               value="<?php echo esc_attr( get_option( 'snapwoo_round_to', 0 ) ); ?>"
                                               step="1"
                                               min="0" />
                                        <span class="snapwoo-input-suffix">تومان</span>
                                    </div>
                                    <p class="description">
                                        قیمت نهایی به نزدیک‌ترین مضرب این عدد رند می‌شود. مثال با ۱۰۰۰: مقدار ۱۱۰,۵۰۰ تبدیل به ۱۱۱,۰۰۰ می‌شود. مقدار ۰ یعنی بدون رند.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <hr class="snapwoo-divider">

                        <h3 class="snapwoo-section-title">قواعد اختصاصی دسته‌بندی‌ها</h3>
                        <p class="snapwoo-section-desc">
                            برای هر دسته‌بندی می‌توانید قاعده جداگانه تعریف کنید. اگر محصولی در چند دسته باشد، اولین دسته منطبق اعمال می‌شود.
                        </p>

                        <table class="snapwoo-rules-table">
                            <thead>
                                <tr>
                                    <th>دسته‌بندی</th>
                                    <th>درصد افزایش</th>
                                    <th>رند به (تومان)</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $rules ) ) : ?>
                                    <tr class="snapwoo-empty-row">
                                        <td colspan="4">
                                            هنوز قانونی تعریف نشده. با دکمه زیر اضافه کنید.
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ( $rules as $i => $rule ) : ?>
                                        <tr>
                                            <td>
                                                <select name="snapwoo_category_rules[<?php echo esc_attr( $i ); ?>][term_id]" class="snapwoo-cat-select">
                                                    <option value="">— انتخاب دسته —</option>
                                                    <?php foreach ( $categories as $cat ) : ?>
                                                        <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                                            <?php selected( $rule['term_id'], $cat->term_id ); ?>>
                                                            <?php echo esc_html( $cat->name ); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="snapwoo-input-group">
                                                    <input type="number"
                                                           name="snapwoo_category_rules[<?php echo esc_attr( $i ); ?>][markup]"
                                                           value="<?php echo esc_attr( $rule['markup'] ); ?>"
                                                           step="0.1"
                                                           min="0" />
                                                    <span class="snapwoo-input-suffix">%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="snapwoo-input-group">
                                                    <input type="number"
                                                           name="snapwoo_category_rules[<?php echo esc_attr( $i ); ?>][round_to]"
                                                           value="<?php echo esc_attr( $rule['round_to'] ); ?>"
                                                           step="1"
                                                           min="0" />
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="button snapwoo-remove-rule">حذف</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <p class="snapwoo-add-wrap">
                            <button type="button" class="button button-secondary snapwoo-add-rule">
                                + افزودن دسته‌بندی
                            </button>
                        </p>

                    </div>
                </div>

                <!-- کارت ۲: اتصال به اسنپ شاپ -->
                <div class="snapwoo-card">
                    <div class="snapwoo-card-header">
                        <h2>🔌 اتصال به اسنپ شاپ</h2>
                        <p class="snapwoo-card-desc">
                            کلید وب‌سرویس خود را از پنل فروشندگان اسنپ شاپ دریافت و اینجا وارد کنید، سپس اتصال را تست نمایید.
                        </p>
                    </div>
                    <div class="snapwoo-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="snapwoo_api_key">کلید وب‌سرویس</label>
                                </th>
                                <td>
                                    <input type="password"
                                           id="snapwoo_api_key"
                                           name="snapwoo_api_key"
                                           value="<?php echo esc_attr( $api_key ); ?>"
                                           class="regular-text"
                                           autocomplete="off" />
                                    <p class="description">
                                        کلید به صورت امن ذخیره می‌شود و در هیچ‌کجا نمایش داده نمی‌شود.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button( 'ذخیره تنظیمات', 'primary large', 'submit', false ); ?>
                    </div>
                </div>
            </form>

            <!-- کارت ۳: تست اتصال -->
            <div class="snapwoo-card">
                <div class="snapwoo-card-header">
                    <h2>🧪 بررسی صحت اتصال</h2>
                    <p class="snapwoo-card-desc">
                        پس از ذخیره کلید API، برای اطمینان از صحت اتصال روی دکمه زیر کلیک کنید.
                    </p>
                </div>
                <div class="snapwoo-card-body">
                    <form method="post">
                        <?php wp_nonce_field( 'snapwoo_test' ); ?>
                        <button type="submit"
                                name="snapwoo_test_connection"
                                class="button button-secondary">
                            ارسال درخواست تست
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- قالب ردیف جدید -->
        <script type="text/template" id="snapwoo-rule-template">
            <tr>
                <td>
                    <select name="snapwoo_category_rules[__INDEX__][term_id]" class="snapwoo-cat-select">
                        <option value="">— انتخاب دسته —</option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                <?php echo esc_html( $cat->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <div class="snapwoo-input-group">
                        <input type="number" name="snapwoo_category_rules[__INDEX__][markup]" value="0" step="0.1" min="0" />
                        <span class="snapwoo-input-suffix">%</span>
                    </div>
                </td>
                <td>
                    <div class="snapwoo-input-group">
                        <input type="number" name="snapwoo_category_rules[__INDEX__][round_to]" value="0" step="1" min="0" />
                    </div>
                </td>
                <td>
                    <button type="button" class="button snapwoo-remove-rule">حذف</button>
                </td>
            </tr>
        </script>
        <?php
    }
}