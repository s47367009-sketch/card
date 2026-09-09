<?php
defined('ABSPATH') || exit;

class CartAra_Auto_Verify {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function register_rest_routes() {
        register_rest_route('cartara/v1', '/sms-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_sms_webhook'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cartara/v1', '/verify-manual', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_manual_api_verify'],
            'permission_callback' => function () {
                return current_user_can('manage_woocommerce');
            }
        ]);
    }

    /**
     * Handle incoming SMS webhook from Android companion app or SMS forwarder
     */
    public function handle_sms_webhook($request) {
        $settings = get_option('cartara_settings', []);
        
        if (($settings['enable_auto_verify'] ?? 'no') !== 'yes') {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Auto verification is disabled'], 400);
        }

        $token = $request->get_header('X-CartAra-Token') ?: $request->get_param('token');
        $expected_token = $settings['auto_verify_token'] ?? '';

        if (empty($token) || empty($expected_token) || !hash_equals($expected_token, $token)) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Invalid or missing security token'], 403);
        }

        $sms_body = $request->get_param('sms_body') ?: $request->get_param('message');
        $sender = $request->get_param('sender') ?: $request->get_param('from');

        if (empty($sms_body)) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'Empty SMS body'], 400);
        }

        $parsed = self::parse_bank_sms($sms_body, $sender);
        if (!$parsed || empty($parsed['amount'])) {
            return new WP_REST_Response(['status' => 'ignored', 'message' => 'Not a recognized bank deposit SMS', 'parsed' => $parsed], 200);
        }

        $deposit_amount = intval($parsed['amount']);
        $card_number = $parsed['card_number'] ?? '';
        $ref_number = $parsed['ref_number'] ?? '';

        // Match pending orders
        $matched_order_id = self::find_matching_order($deposit_amount, $card_number);

        if ($matched_order_id) {
            $order = wc_get_order($matched_order_id);
            if ($order) {
                // Auto complete or process
                $order->payment_complete($ref_number);
                $order->add_order_note(sprintf(
                    __('⚡ تایید خودکار توسط پیامک بانکی: مبلغ %s ریال با شماره پیگیری %s واریز شد.', 'cartara-pro'),
                    number_format($deposit_amount),
                    $ref_number ?: '---'
                ));

                // Save receipt record
                global $wpdb;
                $table = $wpdb->prefix . 'cartara_receipts';
                $wpdb->insert($table, [
                    'order_id' => $matched_order_id,
                    'user_id' => $order->get_customer_id(),
                    'amount' => $deposit_amount,
                    'tracking_code' => $ref_number,
                    'status' => 'verified',
                    'verification_method' => 'auto_sms',
                    'raw_sms_data' => $sms_body,
                    'payment_date' => current_time('Y/m/d'),
                    'payment_time' => current_time('H:i'),
                    'created_at' => current_time('mysql')
                ]);

                // Send SMS notification
                CartAra_SMS_Notifier::send_payment_confirmed_sms($order);

                return new WP_REST_Response([
                    'status' => 'matched',
                    'order_id' => $matched_order_id,
                    'amount' => $deposit_amount,
                    'message' => 'Order successfully matched and verified'
                ], 200);
            }
        }

        return new WP_REST_Response([
            'status' => 'unmatched',
            'parsed_amount' => $deposit_amount,
            'message' => 'Deposit parsed but no matching pending order found'
        ], 200);
    }

    /**
     * Parse SMS text from Iranian Banks
     */
    public static function parse_bank_sms($text, $sender = '') {
        $clean_text = str_replace([',', '،', '٬', 'ـ', '	'], '', $text);
        
        $result = [
            'bank' => '',
            'amount' => 0,
            'card_number' => '',
            'ref_number' => '',
            'date_time' => ''
        ];

        // 1. Detect Amount (واریز، واریز وجه، +مبلغ، انتقال)
        if (preg_match('/(?:واریز|واریزبه|انتقال|افزایش|مبلغ)\s*[:=]?\s*([0-9]+)\s*(?:ریال|تومان|Rial|IRR)?/iu', $clean_text, $matches)) {
            $amt = intval($matches[1]);
            // Convert to Rial if text says toman
            if (mb_stripos($clean_text, 'تومان') !== false && $amt < 1000000000) {
                $amt *= 10;
            }
            $result['amount'] = $amt;
        } elseif (preg_match('/\+([0-9]{4,12})/u', $clean_text, $matches)) {
            $result['amount'] = intval($matches[1]);
        }

        // 2. Detect Card / Account Mask
        if (preg_match('/(?:کارت|حساب|به)\s*[:=]?\s*([0-9\*\-]{4,19})/iu', $clean_text, $matches)) {
            $result['card_number'] = $matches[1];
        }

        // 3. Detect Reference / Tracking code
        if (preg_match('/(?:پیگیری|رهگیری|مرجع|ارجاع|کد)\s*[:=]?\s*([0-9a-zA-Z]{5,15})/iu', $clean_text, $matches)) {
            $result['ref_number'] = $matches[1];
        }

        return $result;
    }

    /**
     * Search orders with matching amount within grace period
     */
    public static function find_matching_order($deposit_amount, $card_number = '') {
        $statuses = ['wc-card-to-card', 'wc-pending-receipt', 'wc-on-hold', 'wc-pending'];

        $orders = wc_get_orders([
            'status' => $statuses,
            'payment_method' => 'cartara_card',
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        foreach ($orders as $order) {
            $order_total = intval($order->get_total());
            // Convert order total to rials if currency is IRT
            $currency = get_woocommerce_currency();
            $order_total_rial = in_array($currency, ['IRT', 'TOMAN', 'TMN']) ? ($order_total * 10) : $order_total;

            if ($deposit_amount === $order_total || $deposit_amount === $order_total_rial) {
                return $order->get_id();
            }
        }

        return null;
    }
}
new CartAra_Auto_Verify();
