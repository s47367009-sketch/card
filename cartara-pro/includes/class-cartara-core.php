<?php
defined('ABSPATH') || exit;

class CartAra_Core {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Register custom order statuses
        add_filter('woocommerce_register_shop_order_post_statuses', [$this, 'register_order_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_order_statuses_to_wc']);
        add_filter('woocommerce_reports_order_statuses', [$this, 'add_custom_statuses_to_reports']);
        
        // Add gateway to WooCommerce
        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway']);

        // Frontend Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Order action buttons in admin list
        add_filter('woocommerce_admin_order_actions', [$this, 'add_quick_receipt_action'], 10, 2);
        add_action('admin_head', [$this, 'custom_order_status_css']);
    }

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. Bank Cards Table
        $cards_table = $wpdb->prefix . 'cartara_cards';
        $sql_cards = "CREATE TABLE IF NOT EXISTS $cards_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            bank_name varchar(100) NOT NULL,
            bank_slug varchar(50) NOT NULL,
            card_number varchar(30) NOT NULL,
            sheba_number varchar(40) DEFAULT '',
            account_number varchar(40) DEFAULT '',
            account_holder varchar(150) NOT NULL,
            daily_limit bigint(20) DEFAULT 0,
            today_total bigint(20) DEFAULT 0,
            last_reset_date date DEFAULT NULL,
            priority int(11) DEFAULT 10,
            is_active tinyint(1) DEFAULT 1,
            card_color varchar(50) DEFAULT 'gradient-blue',
            qr_payload text DEFAULT NULL,
            extra_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. Receipts & Transaction Logs Table
        $receipts_table = $wpdb->prefix . 'cartara_receipts';
        $sql_receipts = "CREATE TABLE IF NOT EXISTS $receipts_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            card_id bigint(20) DEFAULT 0,
            amount bigint(20) NOT NULL,
            tracking_code varchar(100) DEFAULT '',
            payer_name varchar(150) DEFAULT '',
            payer_card_last4 varchar(10) DEFAULT '',
            payer_bank varchar(100) DEFAULT '',
            payment_date varchar(50) DEFAULT '',
            payment_time varchar(20) DEFAULT '',
            file_url text DEFAULT NULL,
            file_path text DEFAULT NULL,
            file_name varchar(255) DEFAULT '',
            file_size int(11) DEFAULT 0,
            status varchar(50) DEFAULT 'pending',
            verification_method varchar(50) DEFAULT 'manual',
            raw_sms_data text DEFAULT NULL,
            ip_address varchar(60) DEFAULT '',
            user_agent text DEFAULT NULL,
            admin_note text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_cards);
        dbDelta($sql_receipts);

        // Populate sample cards if empty
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $cards_table");
        if ($count == 0) {
            $wpdb->insert($cards_table, [
                'bank_name' => 'بانک ملت',
                'bank_slug' => 'mellat',
                'card_number' => '6104-3378-1234-5678',
                'sheba_number' => 'IR820120000000001234567890',
                'account_number' => '1234567890',
                'account_holder' => 'فروشگاه آنلاین برتر',
                'daily_limit' => 500000000,
                'priority' => 1,
                'is_active' => 1,
                'card_color' => 'gradient-red',
                'created_at' => current_time('mysql')
            ]);
            $wpdb->insert($cards_table, [
                'bank_name' => 'بانک سامان (بلو)',
                'bank_slug' => 'saman',
                'card_number' => '6219-8610-9876-5432',
                'sheba_number' => 'IR440560000000009876543210',
                'account_number' => '9876543210',
                'account_holder' => 'فروشگاه آنلاین برتر',
                'daily_limit' => 500000000,
                'priority' => 2,
                'is_active' => 1,
                'card_color' => 'gradient-blue',
                'created_at' => current_time('mysql')
            ]);
            $wpdb->insert($cards_table, [
                'bank_name' => 'بانک پاسارگاد',
                'bank_slug' => 'pasargad',
                'card_number' => '5022-2910-4567-8901',
                'sheba_number' => 'IR120570000000004567890123',
                'account_number' => '4567890123',
                'account_holder' => 'فروشگاه آنلاین برتر',
                'daily_limit' => 500000000,
                'priority' => 3,
                'is_active' => 1,
                'card_color' => 'gradient-gold',
                'created_at' => current_time('mysql')
            ]);
        }

        // Set default options
        if (!get_option('cartara_settings')) {
            $defaults = [
                'enable_auto_verify' => 'yes',
                'auto_verify_token' => wp_generate_password(32, false),
                'enable_sms_notification' => 'no',
                'sms_provider' => 'kavenegar',
                'sms_api_key' => '',
                'sms_sender' => '',
                'enable_payment_timer' => 'yes',
                'timer_minutes' => 30,
                'enable_tax_exclusion' => 'no',
                'discount_type' => 'none', // none, percent, fixed
                'discount_value' => 0,
                'enable_split_payment' => 'yes',
                'split_threshold' => 100000000, // 10 million tomans in rial
                'card_display_mode' => 'slider', // slider, grid, list
                'require_receipt_upload' => 'yes',
                'require_tracking_code' => 'yes',
                'require_payer_name' => 'yes',
                'require_payer_card_last4' => 'yes',
                'require_payment_date' => 'yes',
                'max_file_size_mb' => 10,
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
                'theme_mode' => 'modern_glass',
                'primary_color' => '#6366f1',
                'enable_phishing_shield' => 'yes',
                'enable_qr_code' => 'yes',
                'enable_audio_feedback' => 'yes'
            ];
            update_option('cartara_settings', $defaults);
        }

        // Create secure uploads folder with .htaccess protection
        $upload_dir = wp_upload_dir();
        $cartara_dir = $upload_dir['basedir'] . '/' . CARTARA_PRO_UPLOAD_DIR_NAME;
        if (!file_exists($cartara_dir)) {
            wp_mkdir_p($cartara_dir);
            file_put_contents($cartara_dir . '/index.html', '<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>403 Forbidden</h1></body></html>');
            file_put_contents($cartara_dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\.(php|phtml|php3|php4|php5|php7|phps|pl|py|jsp|asp|sh|cgi)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>");
        }
    }

    public static function deactivate() {
        // Clear scheduled crons if any
        wp_clear_scheduled_hook('cartara_daily_limit_reset');
    }

    /**
     * Register Custom WooCommerce Order Statuses
     */
    public function register_order_statuses($order_statuses) {
        $order_statuses['wc-card-to-card'] = [
            'label' => _x('در انتظار بررسی کارت به کارت', 'Order status', 'cartara-pro'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('کارت به کارت <span class="count">(%s)</span>', 'کارت به کارت <span class="count">(%s)</span>', 'cartara-pro')
        ];

        $order_statuses['wc-pending-receipt'] = [
            'label' => _x('در انتظار ارسال رسید', 'Order status', 'cartara-pro'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('در انتظار رسید <span class="count">(%s)</span>', 'در انتظار رسید <span class="count">(%s)</span>', 'cartara-pro')
        ];

        $order_statuses['wc-phishing'] = [
            'label' => _x('مشکوک به فیشینگ / رد شده', 'Order status', 'cartara-pro'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('مشکوک به فیشینگ <span class="count">(%s)</span>', 'مشکوک به فیشینگ <span class="count">(%s)</span>', 'cartara-pro')
        ];

        $order_statuses['wc-partial-paid'] = [
            'label' => _x('پرداخت ناقص / چندمرحله‌ای', 'Order status', 'cartara-pro'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('پرداخت ناقص <span class="count">(%s)</span>', 'پرداخت ناقص <span class="count">(%s)</span>', 'cartara-pro')
        ];

        return $order_statuses;
    }

    public function add_order_statuses_to_wc($order_statuses) {
        $new_order_statuses = [];
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            if ('wc-on-hold' === $key) {
                $new_order_statuses['wc-card-to-card'] = _x('در انتظار بررسی کارت به کارت', 'Order status', 'cartara-pro');
                $new_order_statuses['wc-pending-receipt'] = _x('در انتظار ارسال رسید', 'Order status', 'cartara-pro');
                $new_order_statuses['wc-partial-paid'] = _x('پرداخت ناقص / چندمرحله‌ای', 'Order status', 'cartara-pro');
                $new_order_statuses['wc-phishing'] = _x('مشکوک به فیشینگ / رد شده', 'Order status', 'cartara-pro');
            }
        }
        return $new_order_statuses;
    }

    public function add_custom_statuses_to_reports($statuses) {
        $statuses[] = 'card-to-card';
        $statuses[] = 'partial-paid';
        return $statuses;
    }

    public function add_gateway($gateways) {
        $gateways[] = 'WC_Gateway_CartAra';
        return $gateways;
    }

    public function enqueue_frontend_assets() {
        if (is_checkout() || is_order_received_page() || is_account_page()) {
            wp_enqueue_style('cartara-frontend', CARTARA_PRO_URL . 'assets/css/cartara-frontend.css', [], CARTARA_PRO_VERSION);
            wp_enqueue_script('cartara-frontend', CARTARA_PRO_URL . 'assets/js/cartara-frontend.js', ['jquery'], CARTARA_PRO_VERSION, true);

            wp_localize_script('cartara-frontend', 'cartara_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cartara_frontend_nonce'),
                'i18n' => [
                    'copied' => __('با موفقیت در کلیپ‌بورد کپی شد!', 'cartara-pro'),
                    'uploading' => __('در حال ارسال رسید...', 'cartara-pro'),
                    'upload_success' => __('رسید پرداخت با موفقیت ثبت شد.', 'cartara-pro'),
                    'upload_error' => __('خطا در ارسال فایل. لطفاً مجدداً تلاش کنید.', 'cartara-pro'),
                    'invalid_card' => __('شماره کارت ۱۶ رقمی معتبر نیست.', 'cartara-pro'),
                    'timer_expired' => __('مهلت پرداخت به پایان رسید.', 'cartara-pro'),
                    'confirm_cancel' => __('آیا از انصراف پرداخت اطمینان دارید؟', 'cartara-pro')
                ]
            ]);
        }
    }

    public function custom_order_status_css() {
        echo '<style>
            .order-status.status-wc-card-to-card { background: #dbeafe; color: #1e40af; border-radius: 9999px; }
            .order-status.status-wc-pending-receipt { background: #fef3c7; color: #92400e; border-radius: 9999px; }
            .order-status.status-wc-partial-paid { background: #f3e8ff; color: #6b21a8; border-radius: 9999px; }
            .order-status.status-wc-phishing { background: #fee2e2; color: #991b1b; border-radius: 9999px; }
        </style>';
    }

    public function add_quick_receipt_action($actions, $order) {
        if ($order->get_payment_method() === 'cartara_card') {
            $actions['view_receipt'] = [
                'url' => admin_url('admin.php?page=cartara-receipts&order_id=' . $order->get_id()),
                'name' => __('مشاهده رسید و فیش بانکی', 'cartara-pro'),
                'action' => 'view-receipt'
            ];
        }
        return $actions;
    }
}
