<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SnapWoo_API_Client {

    private $api_key;
    private $base_url = 'https://apix.snappshop.ir/automation/v1';

    public function __construct() {
        $this->api_key = get_option( 'snapwoo_api_key', '' );
    }

    /**
     * ارسال درخواست به API اسنپ‌شاپ
     */
    public function request( $endpoint, $method = 'GET', $body = array() ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'کلید API تنظیم نشده است.' );
        }

        $url = $this->base_url . $endpoint;

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code >= 400 ) {
            $message = isset( $data['message'] ) ? $data['message'] : 'خطای نامشخص';
            return new WP_Error(
                'api_error',
                sprintf( 'خطای API (کد %d): %s', $status_code, $message )
            );
        }

        return $data;
    }

    /**
     * تست اتصال
     */
    public function test_connection() {
        return $this->request( '/ping' );
    }

    /**
     * به‌روزرسانی محصول در اسنپ‌شاپ
     */
    public function update_product( $sku, $price, $stock ) {
        return $this->request( '/products/update', 'POST', array(
            'sku'   => $sku,
            'price' => $price,
            'stock' => $stock,
        ) );
    }

    /**
     * دریافت اطلاعات محصول از اسنپ‌شاپ
     */
    public function get_product( $sku ) {
        return $this->request( '/products/' . rawurlencode( $sku ) );
    }
}