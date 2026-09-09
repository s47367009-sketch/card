<?php
defined('ABSPATH') || exit;

class CartAra_Cards_Manager {

    private static $banks_data = [
        '603799' => ['name' => 'بانک ملی ایران', 'slug' => 'melli', 'color' => '#0c5da5', 'gradient' => 'linear-gradient(135deg, #0c5da5 0%, #1e3c72 100%)'],
        '610433' => ['name' => 'بانک ملت', 'slug' => 'mellat', 'color' => '#d32f2f', 'gradient' => 'linear-gradient(135deg, #d32f2f 0%, #7b1fa2 100%)'],
        '621986' => ['name' => 'بانک سامان', 'slug' => 'saman', 'color' => '#00838f', 'gradient' => 'linear-gradient(135deg, #00838f 0%, #00acc1 100%)'],
        '502229' => ['name' => 'بانک پاسارگاد', 'slug' => 'pasargad', 'color' => '#d4af37', 'gradient' => 'linear-gradient(135deg, #e6c875 0%, #a47c1b 100%)'],
        '589210' => ['name' => 'بانک سپه', 'slug' => 'sepah', 'color' => '#004d40', 'gradient' => 'linear-gradient(135deg, #004d40 0%, #00796b 100%)'],
        '627353' => ['name' => 'بانک تجارت', 'slug' => 'tejarat', 'color' => '#1565c0', 'gradient' => 'linear-gradient(135deg, #1565c0 0%, #0d47a1 100%)'],
        '603769' => ['name' => 'بانک صادرات ایران', 'slug' => 'saderat', 'color' => '#4527a0', 'gradient' => 'linear-gradient(135deg, #4527a0 0%, #283593 100%)'],
        '622106' => ['name' => 'بانک پارسیان', 'slug' => 'parsian', 'color' => '#b71c1c', 'gradient' => 'linear-gradient(135deg, #b71c1c 0%, #880e4f 100%)'],
        '639194' => ['name' => 'بانک پارسیان', 'slug' => 'parsian', 'color' => '#b71c1c', 'gradient' => 'linear-gradient(135deg, #b71c1c 0%, #880e4f 100%)'],
        '627412' => ['name' => 'بانک اقتصاد نوین', 'slug' => 'en', 'color' => '#455a64', 'gradient' => 'linear-gradient(135deg, #455a64 0%, #263238 100%)'],
        '504172' => ['name' => 'بانک رسالت', 'slug' => 'resalat', 'color' => '#00695c', 'gradient' => 'linear-gradient(135deg, #00695c 0%, #004d40 100%)'],
        '606373' => ['name' => 'بانک قرض‌الحسنه مهر ایران', 'slug' => 'mehr', 'color' => '#2e7d32', 'gradient' => 'linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%)'],
        '502806' => ['name' => 'بانک شهر', 'slug' => 'shahr', 'color' => '#c62828', 'gradient' => 'linear-gradient(135deg, #c62828 0%, #ad1457 100%)'],
        '636214' => ['name' => 'بانک آینده', 'slug' => 'ayandeh', 'color' => '#551717', 'gradient' => 'linear-gradient(135deg, #880e4f 0%, #4a148c 100%)'],
        '603770' => ['name' => 'بانک کشاورزی', 'slug' => 'keshavarzi', 'color' => '#2e7d32', 'gradient' => 'linear-gradient(135deg, #2e7d32 0%, #33691e 100%)'],
        '589463' => ['name' => 'بانک رفاه کارگران', 'slug' => 'refah', 'color' => '#0277bd', 'gradient' => 'linear-gradient(135deg, #0277bd 0%, #01579b 100%)'],
        '628023' => ['name' => 'بانک مسکن', 'slug' => 'maskan', 'color' => '#f57c00', 'gradient' => 'linear-gradient(135deg, #f57c00 0%, #e65100 100%)'],
        '639346' => ['name' => 'بانک سینا', 'slug' => 'sina', 'color' => '#4e342e', 'gradient' => 'linear-gradient(135deg, #4e342e 0%, #3e2723 100%)'],
        '505785' => ['name' => 'بانک ایران زمین', 'slug' => 'iranzamin', 'color' => '#6a1b9a', 'gradient' => 'linear-gradient(135deg, #6a1b9a 0%, #4a148c 100%)'],
        '823438' => ['name' => 'بلو بانک (سامان)', 'slug' => 'blubank', 'color' => '#00d2d3', 'gradient' => 'linear-gradient(135deg, #00d2d3 0%, #54a0ff 100%)'],
        '606256' => ['name' => 'بلو بانک (سامان)', 'slug' => 'blubank', 'color' => '#00d2d3', 'gradient' => 'linear-gradient(135deg, #00d2d3 0%, #54a0ff 100%)']
    ];

    /**
     * Get all active bank cards
     */
    public static function get_active_cards($order_amount = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_cards';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        self::check_daily_limit_reset();

        $query = "SELECT * FROM $table WHERE is_active = 1";
        
        // Exclude cards that reached daily limits if set > 0
        if ($order_amount > 0) {
            $query .= " AND (daily_limit = 0 OR (today_total + " . intval($order_amount) . ") <= daily_limit)";
        }
        
        $query .= " ORDER BY priority ASC, id ASC";

        $cards = $wpdb->get_results($query, ARRAY_A);

        // Fallback: if all active cards reached limit, return all active cards
        if (empty($cards) && $order_amount > 0) {
            $cards = $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY priority ASC, id ASC", ARRAY_A);
        }

        return $cards;
    }

    /**
     * Get card by ID
     */
    public static function get_card($card_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_cards';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $card_id), ARRAY_A);
    }

    /**
     * Increment card daily total on successful or pending payment
     */
    public static function record_card_usage($card_id, $amount) {
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_cards';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET today_total = today_total + %d WHERE id = %d",
            $amount,
            $card_id
        ));
    }

    /**
     * Reset today_total if date changed
     */
    public static function check_daily_limit_reset() {
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_cards';
        $today = current_time('Y-m-d');
        
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET today_total = 0, last_reset_date = %s WHERE last_reset_date IS NULL OR last_reset_date < %s",
            $today,
            $today
        ));
    }

    /**
     * Detect bank from 16-digit card number
     */
    public static function detect_bank_by_card($card_number) {
        $clean = preg_replace('/[^0-9]/', '', $card_number);
        $bin = substr($clean, 0, 6);

        if (isset(self::$banks_data[$bin])) {
            return self::$banks_data[$bin];
        }

        return [
            'name' => 'شبکه شتاب',
            'slug' => 'shetab',
            'color' => '#3b82f6',
            'gradient' => 'linear-gradient(135deg, #1e293b 0%, #334155 100%)'
        ];
    }

    /**
     * Validate card number using Luhn Algorithm
     */
    public static function validate_card_number($card_number) {
        $number = preg_replace('/[^0-9]/', '', $card_number);
        if (strlen($number) !== 16) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 16; $i++) {
            $val = intval($number[$i]);
            if ($i % 2 === 0) {
                $val *= 2;
                if ($val > 9) {
                    $val -= 9;
                }
            }
            $sum += $val;
        }

        return ($sum % 10 === 0);
    }

    /**
     * Format Card Number with dashes: 6037-9918-1234-5678
     */
    public static function format_card_number($card_number) {
        $clean = preg_replace('/[^0-9]/', '', $card_number);
        if (strlen($clean) === 16) {
            return chunk_split($clean, 4, '-');
        }
        return $card_number;
    }

    /**
     * Format Sheba Number with spaces: IR 82 0120 0000 0000 1234 5678 90
     */
    public static function format_sheba($sheba) {
        $clean = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $sheba));
        if (strpos($clean, 'IR') !== 0) {
            $clean = 'IR' . $clean;
        }
        return chunk_split($clean, 4, ' ');
    }

    /**
     * Return all known banks for admin selector
     */
    public static function get_all_supported_banks() {
        return self::$banks_data;
    }
}
