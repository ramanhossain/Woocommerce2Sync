<?php
/*
Plugin Name: WooCommerce to Bol.com Sync
Description: Sync WooCommerce products to Bol.com with custom price adjustments.
Version: 1.1
Author: Saru Bureau
*/

// Exit if accessed directly
defined('ABSPATH') || exit;

// === Constants ===
define('WC_BOL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_BOL_PLUGIN_URL', plugin_dir_url(__FILE__));

// === Settings ===
add_action('admin_menu', 'wc_bol_add_admin_menu');
add_action('admin_init', 'wc_bol_settings_init');

function wc_bol_add_admin_menu() {
    add_options_page('Woo to Bol.com', 'Woo to Bol.com', 'manage_options', 'woo_to_bol', 'wc_bol_options_page');
}

function wc_bol_settings_init() {
    register_setting('wc_bol_settings', 'wc_bol_client_id');
    register_setting('wc_bol_settings', 'wc_bol_client_secret');
    register_setting('wc_bol_settings', 'wc_bol_price_percentage');
    register_setting('wc_bol_settings', 'wc_bol_price_fixed');

    add_settings_section('wc_bol_section', __('Bol.com API Settings', 'wc-bol'), null, 'wc_bol_settings');

    add_settings_field('wc_bol_client_id', __('Client ID', 'wc-bol'), 'wc_bol_client_id_render', 'wc_bol_settings', 'wc_bol_section');
    add_settings_field('wc_bol_client_secret', __('Client Secret', 'wc-bol'), 'wc_bol_client_secret_render', 'wc_bol_settings', 'wc_bol_section');
    add_settings_field('wc_bol_price_percentage', __('Price Markup (%)', 'wc-bol'), 'wc_bol_price_percentage_render', 'wc_bol_settings', 'wc_bol_section');
    add_settings_field('wc_bol_price_fixed', __('Fixed Cost Addition (&#1026;)', 'wc-bol'), 'wc_bol_price_fixed_render', 'wc_bol_settings', 'wc_bol_section');
}

function wc_bol_client_id_render() {
    echo "<input type='text' name='wc_bol_client_id' value='" . esc_attr(get_option('wc_bol_client_id')) . "' size='50'>";
}

function wc_bol_client_secret_render() {
    echo "<input type='password' name='wc_bol_client_secret' value='" . esc_attr(get_option('wc_bol_client_secret')) . "' size='50'>";
}

function wc_bol_price_percentage_render() {
    echo "<input type='number' step='0.01' name='wc_bol_price_percentage' value='" . esc_attr(get_option('wc_bol_price_percentage')) . "'>";
}

function wc_bol_price_fixed_render() {
    echo "<input type='number' step='0.01' name='wc_bol_price_fixed' value='" . esc_attr(get_option('wc_bol_price_fixed')) . "'>";
}

function wc_bol_options_page() {
    echo '<form action="options.php" method="post">';
    settings_fields('wc_bol_settings');
    do_settings_sections('wc_bol_settings');
    submit_button('Save Settings');
    echo '</form>';

    echo '<hr><form method="post"><input type="submit" name="sync_to_bol" value="Sync Products to Bol.com" class="button button-primary"></form>';

    if (isset($_POST['sync_to_bol'])) {
        wc_bol_sync_products();
    }
}

// === Sync Functionality ===
function wc_bol_sync_products() {
    $args = [
        'status' => 'publish',
        'limit' => -1,
        'return' => 'ids',
    ];

    $products = wc_get_products($args);
    $percentage = floatval(get_option('wc_bol_price_percentage'));
    $fixed = floatval(get_option('wc_bol_price_fixed'));
    $token = wc_bol_get_access_token();

    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        $price = floatval($product->get_price());

        $adjusted_price = $price + ($price * $percentage / 100) + $fixed;
        $data = [
            'id' => $product_id,
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => round($adjusted_price, 2),
            'description' => $product->get_description(),
        ];

        wc_bol_send_to_api($data, $token);
    }
}

function wc_bol_get_access_token() {
    $client_id = get_option('wc_bol_client_id');
    $client_secret = get_option('wc_bol_client_secret');
    $auth = base64_encode("{$client_id}:{$client_secret}");

    $response = wp_remote_post('https://login.bol.com/token?grant_type=client_credentials', [
        'headers' => [
            'Authorization' => 'Basic ' . $auth,
            'Accept' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Bol API Token error: ' . $response->get_error_message());
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['access_token'] ?? null;
}

function wc_bol_send_to_api($data, $token) {
    if (!$token) {
        error_log('No token, cannot sync: ' . $data['name']);
        return;
    }

    $response = wp_remote_post('https://api.bol.com/retailer/products', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => json_encode($data),
    ]);

    if (is_wp_error($response)) {
        error_log('Bol API error: ' . $response->get_error_message());
    } else {
        error_log('Product synced: ' . $data['name']);
    }
}
