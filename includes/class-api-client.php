<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * پیدا کردن قاعده قیمت‌گذاری قابل اعمال برای یک محصول
 */
function snapwoo_get_applicable_pricing_rule( $product_id = 0 ) {
    $rule = array(
        'markup'   => (float) get_option( 'snapwoo_markup_percent', 0 ),
        'round_to' => (int) get_option( 'snapwoo_round_to', 0 ),
        'source'   => 'default',
        'category' => '',
    );

    if ( ! $product_id ) {
        return $rule;
    }

    $rules = get_option( 'snapwoo_category_rules', array() );
    if ( ! is_array( $rules ) || empty( $rules ) ) {
        return $rule;
    }

    $product_cats = wp_get_post_terms( $product_id, 'product_cat' );
    if ( is_wp_error( $product_cats ) || empty( $product_cats ) ) {
        return $rule;
    }

    $product_cat_ids = wp_list_pluck( $product_cats, 'term_id' );

    foreach ( $rules as $r ) {
        if ( empty( $r['term_id'] ) ) {
            continue;
        }
        if ( in_array( (int) $r['term_id'], $product_cat_ids, true ) ) {
            $rule['markup']   = (float) $r['markup'];
            $rule['round_to'] = (int) $r['round_to'];
            $rule['source']   = 'category';

            $term = get_term( (int) $r['term_id'], 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $rule['category'] = $term->name;
            }
            return $rule;
        }
    }

    return $rule;
}

/**
 * محاسبه قیمت نهایی اسنپ‌شاپ از روی قیمت ووکامرس
 */
function snapwoo_calculate_snap_price( $woo_price, $product_id = 0 ) {
    $rule  = snapwoo_get_applicable_pricing_rule( $product_id );
    $price = $woo_price * ( 1 + $rule['markup'] / 100 );

    if ( $rule['round_to'] > 0 ) {
        $price = round( $price / $rule['round_to'] ) * $rule['round_to'];
    }

    return $price;
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