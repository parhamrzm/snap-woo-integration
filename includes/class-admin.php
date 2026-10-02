<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SnapWoo_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
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
     * ثبت تنظیمات
     */
    public function register_settings() {
        register_setting( 'snapwoo_settings', 'snapwoo_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
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
                $message = '<div class="notice notice-error"><p>خطا: '
                    . esc_html( $result->get_error_message() )
                    . '</p></div>';
            } else {
                $message = '<div class="notice notice-success"><p>اتصال با موفقیت برقرار شد!</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <h1>تنظیمات اسنپ‌وو</h1>

            <?php echo $message; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'snapwoo_settings' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="snapwoo_api_key">کلید وب سرویس اسنپ شاپ</label>
                        </th>
                        <td>
                            <input type="password"
                                   id="snapwoo_api_key"
                                   name="snapwoo_api_key"
                                   value="<?php echo esc_attr( $api_key ); ?>"
                                   class="regular-text" />
                            <p class="description">
                                کلید API را از پنل فروشندگان اسنپ شاپ دریافت کنید.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'ذخیره تنظیمات' ); ?>
            </form>

            <hr>

            <h2>تست اتصال</h2>
            <form method="post">
                <?php wp_nonce_field( 'snapwoo_test' ); ?>
                <p>پس از ذخیره کلید API، برای اطمینان از صحت اتصال، روی دکمه زیر کلیک کنید.</p>
                <button type="submit" name="snapwoo_test_connection" class="button button-secondary">
                    تست اتصال
                </button>
            </form>
        </div>
        <?php
    }
}