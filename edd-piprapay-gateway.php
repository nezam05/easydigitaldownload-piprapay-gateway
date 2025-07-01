<?php
/**
 * Plugin Name: EDD PipraPay
 * Plugin URI: https://piprapay.com
 * Description: Adds PipraPay as a payment gateway for Easy Digital Downloads.
 * Version: 1.0.0
 * Author: PipraPay
 * Author URI: https://piprapay.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: edd-piprapay
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register the gateway
add_filter('edd_payment_gateways', 'edd_register_piprapay_gateway');
function edd_register_piprapay_gateway($gateways)
{
    $gateways['piprapay'] = [
        'admin_label'    => 'PipraPay',
        'checkout_label' => __('Pay with PipraPay', 'edd')
    ];
    return $gateways;
}

// Settings
add_filter('edd_settings_gateways', 'edd_piprapay_settings');
function edd_piprapay_settings($settings)
{
    $piprapay_settings = [
        [
            'id'   => 'piprapay_settings',
            'name' => '<strong>' . __('PipraPay Settings', 'edd') . '</strong>',
            'desc' => __('Configure your PipraPay gateway settings', 'edd'),
            'type' => 'header'
        ],
        [
            'id'   => 'piprapay_api_key',
            'name' => 'API Key',
            'type' => 'text'
        ],
        [
            'id'   => 'piprapay_api_url',
            'name' => 'API URL',
            'type' => 'text',
            'std'  => 'https://sandbox.piprapay.com/api'
        ],
        [
            'id'   => 'piprapay_currency',
            'name' => 'Currency Code',
            'type' => 'text',
            'std'  => 'BDT'
        ],
        [
            'id'   => 'piprapay_webhook_secret',
            'name' => 'Webhook Secret/API Key',
            'type' => 'text'
        ],
    ];

    return array_merge($settings, $piprapay_settings);
}

// Process the payment
add_action('edd_gateway_piprapay', 'edd_process_piprapay_payment');
function edd_process_piprapay_payment($purchase_data)
{
    global $edd_options;

    $api_url  = trailingslashit($edd_options['piprapay_api_url']);
    $api_key  = $edd_options['piprapay_api_key'];
    $currency = $edd_options['piprapay_currency'];

    $payment_data = [
        'price'        => $purchase_data['price'],
        'date'         => $purchase_data['date'],
        'user_email'   => $purchase_data['user_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'currency'     => $currency,
        'downloads'    => $purchase_data['downloads'],
        'cart_details' => $purchase_data['cart_details'],
        'user_info'    => $purchase_data['user_info'],
        'status'       => 'pending'
    ];

    $payment_id = edd_insert_payment($payment_data);

    if (!$payment_id) {
        edd_send_back_to_checkout('?payment-mode=piprapay');
        return;
    }

    $metadata = [
        'invoiceid' => $purchase_data['purchase_key'],
    ];

    $post_data = [
        'full_name'    => $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'],
        'email_mobile' => $purchase_data['user_email'],
        'amount'       => $purchase_data['price'],
        'redirect_url' => add_query_arg(['payment-confirmation' => 'piprapay', 'payment-id' => $payment_id], get_permalink(edd_get_option('success_page'))),
        'cancel_url'   => edd_get_failed_transaction_uri(),
        'webhook_url'  => site_url('/?edd-listener=piprapay'),
        'return_type'  => 'GET',
        'currency'     => $currency,
        'metadata'     => $metadata
    ];

    $response = wp_remote_post($api_url . 'create-charge', [
        'method'    => 'POST',
        'headers'   => [
            'Content-Type' => 'application/json',
            'accept'       => 'application/json',
            'mh-piprapay-api-key' => $api_key
        ],
        'body'      => json_encode($post_data),
        'data_format' => 'body'
    ]);

    if (is_wp_error($response)) {
        edd_record_gateway_error('PipraPay Error', $response->get_error_message());
        edd_send_back_to_checkout('?payment-mode=piprapay');
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['pp_url'])) {
        edd_empty_cart();
        wp_redirect($body['pp_url']);
        exit;
    } else {
        edd_send_back_to_checkout('?payment-mode=piprapay');
    }
}

// Webhook Listener
add_action('init', 'edd_piprapay_webhook_listener');
function edd_piprapay_webhook_listener()
{
    if (!isset($_GET['edd-listener']) || $_GET['edd-listener'] !== 'piprapay') {
        return;
    }

    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    $headers = getallheaders();
    $api_key_received = $headers['mh-piprapay-api-key'] ?? ($headers['Mh-Piprapay-Api-Key'] ?? $_SERVER['HTTP_MH_PIPRAPAY_API_KEY']);

    $expected_key = edd_get_option('piprapay_webhook_secret');

    if ($api_key_received !== $expected_key) {
        status_header(401);
        echo json_encode(["status" => false, "message" => "Unauthorized request."]);
        exit;
    }

    if (!isset($data['metadata']['invoiceid'])) {
        status_header(400);
        echo json_encode(["status" => false, "message" => "Invalid data."]);
        exit;
    }

    $invoice = $data['metadata']['invoiceid'];

    $payment = edd_get_payment_by('key', $invoice);
    if (!$payment) {
        status_header(404);
        echo json_encode(["status" => false, "message" => "Payment not found."]);
        exit;
    }

    if ($data['status'] === 'completed') {
        edd_update_payment_status($payment->ID, 'publish');
        edd_insert_payment_note($payment->ID, 'PipraPay transaction ID: ' . $data['transaction_id']);
    }

    echo json_encode(["status" => true]);
    exit;
}
