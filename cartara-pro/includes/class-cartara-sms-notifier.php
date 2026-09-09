<?php
defined('ABSPATH') || exit;

class CartAra_SMS_Notifier {

    public static function send_sms($receptor, $message, $pattern_code = '', $tokens = []) {
        $settings = get_option('cartara_settings', []);
        if (($settings['enable_sms_notification'] ?? 'no') !== 'yes') {
            return false;
        }

        $receptor = preg_replace('/[^0-9]/', '', $receptor);
        if (strlen($receptor) === 10 && substr($receptor, 0, 1) === '9') {
            $receptor = '0' . $receptor;
        }

        if (strlen($receptor) !== 11 || substr($receptor, 0, 2) !== '09') {
            return false;
        }

        $provider = $settings['sms_provider'] ?? 'kavenegar';
        $api_key = $settings['sms_api_key'] ?? '';
        $sender = $settings['sms_sender'] ?? '';

        if (empty($api_key)) {
            return false;
        }

        switch ($provider) {
            case 'kavenegar':
                return self::send_kavenegar($api_key, $receptor, $message, $pattern_code, $tokens);
            case 'ippanel':
            case 'farazsms':
                return self::send_ippanel($api_key, $sender, $receptor, $pattern_code, $tokens, $message);
            case 'smsir':
                return self::send_smsir($api_key, $sender, $receptor, $pattern_code, $tokens);
            case 'melipayamak':
                return self::send_melipayamak($api_key, $sender, $receptor, $message);
            default:
                return false;
        }
    }

    private static function send_kavenegar($api_key, $receptor, $message, $pattern_code = '', $tokens = []) {
        if (!empty($pattern_code)) {
            $url = "https://api.kavenegar.com/v1/$api_key/verify/lookup.json";
            $body = [
                'receptor' => $receptor,
                'template' => $pattern_code,
                'token' => $tokens['token'] ?? '',
                'token2' => $tokens['token2'] ?? '',
                'token3' => $tokens['token3'] ?? ''
            ];
        } else {
            $url = "https://api.kavenegar.com/v1/$api_key/sms/send.json";
            $body = [
                'receptor' => $receptor,
                'message' => $message
            ];
        }

        $response = wp_remote_post($url, [
            'body' => $body,
            'timeout' => 10,
            'sslverify' => false
        ]);

        return !is_wp_error($response);
    }

    private static function send_ippanel($api_key, $sender, $receptor, $pattern_code, $tokens, $fallback_message) {
        $url = 'https://api2.ippanel.com/api/v1/sms/pattern/normal/send';
        $body = [
            'code' => $pattern_code,
            'sender' => $sender ?: '+983000505',
            'recipient' => $receptor,
            'variable' => $tokens
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'apikey' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 10,
            'sslverify' => false
        ]);

        return !is_wp_error($response);
    }

    private static function send_smsir($api_key, $sender, $receptor, $pattern_code, $tokens) {
        $url = 'https://api.sms.ir/v1/send/verify';
        $parameters = [];
        foreach ($tokens as $k => $v) {
            $parameters[] = ['name' => $k, 'value' => (string)$v];
        }

        $body = [
            'mobile' => $receptor,
            'templateId' => intval($pattern_code),
            'parameters' => $parameters
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body),
            'timeout' => 10,
            'sslverify' => false
        ]);

        return !is_wp_error($response);
    }

    private static function send_melipayamak($api_key, $sender, $receptor, $message) {
        $url = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
        $body = [
            'username' => $sender,
            'password' => $api_key,
            'to' => $receptor,
            'from' => $sender,
            'text' => $message,
            'isflash' => false
        ];

        $response = wp_remote_post($url, [
            'body' => $body,
            'timeout' => 10,
            'sslverify' => false
        ]);

        return !is_wp_error($response);
    }

    public static function send_payment_confirmed_sms($order) {
        $phone = $order->get_billing_phone();
        if (empty($phone)) return;

        $msg = sprintf(
            __("پرداخت سفارش #%s با موفقیت تایید شد.\nمبلغ: %s %s\nبا تشکر از خرید شما.", 'cartara-pro'),
            $order->get_id(),
            number_format($order->get_total()),
            get_woocommerce_currency_symbol()
        );

        self::send_sms($phone, $msg, get_option('cartara_sms_pattern_success', ''), [
            'token' => $order->get_id(),
            'token2' => number_format($order->get_total()),
            'token3' => 'موفق'
        ]);
    }

    public static function send_receipt_submitted_sms($order, $tracking_code) {
        $phone = $order->get_billing_phone();
        if (empty($phone)) return;

        $msg = sprintf(
            __("رسید پرداخت سفارش #%s با کد پیگیری %s دریافت شد و در دست بررسی است.", 'cartara-pro'),
            $order->get_id(),
            $tracking_code
        );

        self::send_sms($phone, $msg);
    }
}
