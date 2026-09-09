<?php
defined('ABSPATH') || exit;

class CartAra_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'add_order_metabox']);
        
        // HPOS and legacy order columns
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_receipt_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_receipt_column'], 10, 2);
        add_filter('manage_edit-shop_order_columns', [$this, 'add_receipt_column']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_receipt_column_legacy'], 10, 2);

        // AJAX handlers
        add_action('wp_ajax_cartara_admin_update_status', [$this, 'ajax_update_order_status']);
        add_action('wp_ajax_cartara_admin_save_card', [$this, 'ajax_save_card']);
        add_action('wp_ajax_cartara_admin_delete_card', [$this, 'ajax_delete_card']);
        add_action('wp_ajax_cartara_admin_test_sms', [$this, 'ajax_test_sms']);
        add_action('wp_ajax_cartara_admin_simulate_sms', [$this, 'ajax_simulate_sms']);
        add_action('wp_ajax_cartara_admin_export_csv', [$this, 'ajax_export_csv']);
    }

    public function register_admin_menus() {
        add_menu_page(
            __('کارت‌آرا پرو', 'cartara-pro'),
            __('کارت‌آرا پرو 💳', 'cartara-pro'),
            'manage_woocommerce',
            'cartara-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-money-alt',
            56
        );

        add_submenu_page(
            'cartara-dashboard',
            __('داشبورد و آمار تحلیلی', 'cartara-pro'),
            __('داشبورد تحلیلی', 'cartara-pro'),
            'manage_woocommerce',
            'cartara-dashboard',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'cartara-dashboard',
            __('مدیریت رسیدها و تراکنش‌ها', 'cartara-pro'),
            __('مدیریت رسیدها', 'cartara-pro'),
            'manage_woocommerce',
            'cartara-receipts',
            [$this, 'render_receipts_page']
        );

        add_submenu_page(
            'cartara-dashboard',
            __('حساب‌ها و کارت‌های بانکی', 'cartara-pro'),
            __('کارت‌های بانکی', 'cartara-pro'),
            'manage_woocommerce',
            'cartara-cards',
            [$this, 'render_cards_page']
        );

        add_submenu_page(
            'cartara-dashboard',
            __('تایید خودکار و وب‌هوک', 'cartara-pro'),
            __('تایید خودکار پیامکی', 'cartara-pro'),
            'manage_woocommerce',
            'cartara-autoverify',
            [$this, 'render_autoverify_page']
        );

        add_submenu_page(
            'cartara-dashboard',
            __('تنظیمات و پیامک', 'cartara-pro'),
            __('تنظیمات و پیامک', 'cartara-pro'),
            'manage_woocommerce',
            'cartara-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'cartara') !== false || strpos($hook, 'wc-orders') !== false || strpos($hook, 'shop_order') !== false) {
            wp_enqueue_style('cartara-admin', CARTARA_PRO_URL . 'assets/css/cartara-admin.css', [], CARTARA_PRO_VERSION);
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
            wp_enqueue_script('cartara-admin', CARTARA_PRO_URL . 'assets/js/cartara-admin.js', ['jquery'], CARTARA_PRO_VERSION, true);

            wp_localize_script('cartara-admin', 'cartara_admin_vars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cartara_admin_nonce'),
                'i18n' => [
                    'confirm_delete' => __('آیا از حذف این کارت اطمینان دارید؟', 'cartara-pro'),
                    'confirm_phishing' => __('آیا از علامت‌گذاری این پرداخت به عنوان فیشینگ اطمینان دارید؟', 'cartara-pro'),
                    'saved_success' => __('تنظیمات با موفقیت ذخیره شد.', 'cartara-pro'),
                    'action_done' => __('عملیات با موفقیت انجام شد.', 'cartara-pro')
                ]
            ]);
        }
    }

    public function add_receipt_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('order_status' === $key || 'order_total' === $key) {
                $new_columns['cartara_receipt'] = __('رسید کارت‌آرا', 'cartara-pro');
            }
        }
        if (!isset($new_columns['cartara_receipt'])) {
            $new_columns['cartara_receipt'] = __('رسید کارت‌آرا', 'cartara-pro');
        }
        return $new_columns;
    }

    public function render_receipt_column($column, $order) {
        if ('cartara_receipt' === $column) {
            $this->render_receipt_column_content($order);
        }
    }

    public function render_receipt_column_legacy($column, $post_id) {
        if ('cartara_receipt' === $column) {
            $order = wc_get_order($post_id);
            if ($order) {
                $this->render_receipt_column_content($order);
            }
        }
    }

    private function render_receipt_column_content($order) {
        if ($order->get_payment_method() !== 'cartara_card') {
            echo '<span class="cartara-dash-muted">—</span>';
            return;
        }

        $receipt_url = $order->get_meta('_cartara_last_receipt_url');
        $tracking_code = $order->get_meta('_cartara_tracking_code');
        $order_id = $order->get_id();

        if ($receipt_url) {
            $ext = pathinfo($receipt_url, PATHINFO_EXTENSION);
            if ($ext === 'pdf') {
                echo '<a href="' . esc_url($receipt_url) . '" target="_blank" class="cartara-btn-pill cartara-btn-pdf" title="' . esc_attr($tracking_code) . '">📄 مشاهده PDF</a>';
            } else {
                echo '<button type="button" class="cartara-btn-pill cartara-btn-img cartara-open-receipt-modal" data-url="' . esc_url($receipt_url) . '" data-order="' . esc_attr($order_id) . '" data-track="' . esc_attr($tracking_code) . '">🖼️ مشاهده فیش</button>';
            }
        } elseif (!empty($tracking_code)) {
            echo '<span class="cartara-badge cartara-badge-track">کد: ' . esc_html($tracking_code) . '</span>';
        } else {
            echo '<span class="cartara-badge cartara-badge-empty">بدون رسید</span>';
        }
    }

    public function add_order_metabox() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'cartara_order_details',
            __('اطلاعات پرداخت کارت به کارت (کارت‌آرا پرو)', 'cartara-pro'),
            [$this, 'render_order_metabox'],
            $screen,
            'side',
            'high'
        );
    }

    public function render_order_metabox($post_or_order_object) {
        $order = ($post_or_order_object instanceof WC_Order) ? $post_or_order_object : wc_get_order($post_or_order_object->ID);
        if (!$order) return;

        $payer_name = $order->get_meta('_cartara_payer_name');
        $payer_last4 = $order->get_meta('_cartara_payer_card_last4');
        $tracking_code = $order->get_meta('_cartara_tracking_code');
        $payment_date = $order->get_meta('_cartara_payment_date');
        $receipt_url = $order->get_meta('_cartara_last_receipt_url');
        $receipts = CartAra_Receipts::get_order_receipts($order->get_id());

        ?>
        <div class="cartara-metabox-wrapper">
            <div class="cartara-meta-row">
                <strong>👤 نام واریزکننده:</strong>
                <span><?php echo esc_html($payer_name ?: '---'); ?></span>
            </div>
            <div class="cartara-meta-row">
                <strong>💳 ۴ رقم آخر کارت:</strong>
                <span><?php echo esc_html($payer_last4 ?: '---'); ?></span>
            </div>
            <div class="cartara-meta-row">
                <strong>🔢 کد رهگیری/پیگیری:</strong>
                <span class="cartara-code-highlight"><?php echo esc_html($tracking_code ?: '---'); ?></span>
            </div>
            <div class="cartara-meta-row">
                <strong>📅 تاریخ و ساعت:</strong>
                <span><?php echo esc_html($payment_date ?: '---'); ?></span>
            </div>

            <?php if (!empty($receipts)): ?>
                <div class="cartara-metabox-receipts">
                    <strong>📑 فیش‌های بارگذاری شده (<?php echo count($receipts); ?>):</strong>
                    <div class="cartara-receipt-thumbs">
                        <?php foreach ($receipts as $rc): ?>
                            <a href="<?php echo esc_url($rc['file_url']); ?>" target="_blank" class="cartara-thumb-link">
                                <?php if (pathinfo($rc['file_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                    <div class="cartara-pdf-icon">PDF</div>
                                <?php else: ?>
                                    <img src="<?php echo esc_url($rc['file_url']); ?>" alt="رسید" />
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="cartara-metabox-actions">
                <button type="button" class="button button-primary cartara-btn-approve-order" data-order="<?php echo $order->get_id(); ?>">✅ تایید واریز</button>
                <button type="button" class="button cartara-btn-reject-order" data-order="<?php echo $order->get_id(); ?>">❌ رد پرداخت</button>
                <button type="button" class="button cartara-btn-phishing-order" data-order="<?php echo $order->get_id(); ?>">⚠️ فیشینگ</button>
            </div>
        </div>
        <?php
    }

    /**
     * Render Dashboard Page
     */
    public function render_dashboard_page() {
        global $wpdb;
        $receipts_table = $wpdb->prefix . 'cartara_receipts';

        // Summary Stats
        $today_date = current_time('Y/m/d');
        $total_verified_amount = $wpdb->get_var("SELECT SUM(amount) FROM $receipts_table WHERE status = 'verified'") ?: 0;
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $receipts_table WHERE status = 'pending'") ?: 0;
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $receipts_table") ?: 0;
        $auto_verified_count = $wpdb->get_var("SELECT COUNT(*) FROM $receipts_table WHERE verification_method = 'auto_sms'") ?: 0;

        ?>
        <div class="wrap cartara-admin-wrap" dir="rtl">
            <div class="cartara-header">
                <div class="cartara-header-title">
                    <h1>💳 داشبورد مدیریتی کارت‌آرا پرو</h1>
                    <p>سیستم یکپارچه و هوشمند مدیریت پرداخت‌های کارت به کارت و فیش‌های بانکی</p>
                </div>
                <div class="cartara-header-badge">
                    <span class="cartara-status-pill online">● سیستم آنلاین و فعال</span>
                    <span class="cartara-version">نسخه <?php echo CARTARA_PRO_VERSION; ?></span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="cartara-stats-grid">
                <div class="cartara-stat-card gradient-purple">
                    <div class="cartara-stat-icon">💰</div>
                    <div class="cartara-stat-info">
                        <h3>مجموع کل واریزی‌های تاییدشده</h3>
                        <div class="cartara-stat-value"><?php echo number_format($total_verified_amount); ?> <small>ریال</small></div>
                    </div>
                </div>

                <div class="cartara-stat-card gradient-amber">
                    <div class="cartara-stat-icon">⏳</div>
                    <div class="cartara-stat-info">
                        <h3>فیش‌های در انتظار بررسی</h3>
                        <div class="cartara-stat-value"><?php echo number_format($pending_count); ?> <small>رسید</small></div>
                    </div>
                </div>

                <div class="cartara-stat-card gradient-emerald">
                    <div class="cartara-stat-icon">⚡</div>
                    <div class="cartara-stat-info">
                        <h3>تایید خودکار پیامکی</h3>
                        <div class="cartara-stat-value"><?php echo number_format($auto_verified_count); ?> <small>سفارش</small></div>
                    </div>
                </div>

                <div class="cartara-stat-card gradient-cyan">
                    <div class="cartara-stat-icon">📊</div>
                    <div class="cartara-stat-info">
                        <h3>کل تراکنش‌های ثبت‌شده</h3>
                        <div class="cartara-stat-value"><?php echo number_format($total_count); ?> <small>تراکنش</small></div>
                    </div>
                </div>
            </div>

            <!-- Chart & Health Grid -->
            <div class="cartara-two-col-grid">
                <div class="cartara-card">
                    <div class="cartara-card-header">
                        <h2>📈 روند تراکنش‌های ۷ روز اخیر</h2>
                        <span class="cartara-tag">نمودار زنده</span>
                    </div>
                    <div class="cartara-card-body">
                        <canvas id="cartaraWeeklyChart" height="110"></canvas>
                    </div>
                </div>

                <div class="cartara-card">
                    <div class="cartara-card-header">
                        <h2>🛡️ وضعیت سلامت و امنیت سیستم</h2>
                    </div>
                    <div class="cartara-card-body">
                        <ul class="cartara-health-list">
                            <li>
                                <span>سازگاری با HPOS ووکامرس:</span>
                                <strong class="badge-success">سازگار و فعال ✅</strong>
                            </li>
                            <li>
                                <span>پوشه آپلود حفاظت‌شده:</span>
                                <strong class="badge-success">ایمن با .htaccess 🔒</strong>
                            </li>
                            <li>
                                <span>وب‌هوک تایید خودکار پیامکی:</span>
                                <strong class="badge-info">آماده دریافت وب‌هوک ⚡</strong>
                            </li>
                            <li>
                                <span>درگاه‌های پیامکی پشتیبانی‌شده:</span>
                                <strong>کاوه‌نگار، فراز، اس‌ام‌اس دات‌آی‌آر</strong>
                            </li>
                            <li>
                                <span>سیستم ضد فیشینگ:</span>
                                <strong class="badge-success">فعال 🛡️</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Receipts Manager Page
     */
    public function render_receipts_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_receipts';

        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        $search_query = sanitize_text_field($_GET['s'] ?? '');

        $sql = "SELECT * FROM $table WHERE 1=1";
        if (!empty($status_filter)) {
            $sql .= $wpdb->prepare(" AND status = %s", $status_filter);
        }
        if (!empty($search_query)) {
            $sql .= $wpdb->prepare(" AND (tracking_code LIKE %s OR payer_name LIKE %s OR order_id = %d)", "%$search_query%", "%$search_query%", intval($search_query));
        }
        $sql .= " ORDER BY id DESC LIMIT 50";

        $receipts = $wpdb->get_results($sql, ARRAY_A);

        ?>
        <div class="wrap cartara-admin-wrap" dir="rtl">
            <div class="cartara-header">
                <div>
                    <h1>📑 مدیریت رسیدها و تراکنش‌های بانکی</h1>
                    <p>بررسی سریع، تایید یا رد فیش‌های واریزی با امکان مشاهده باکیفیت تصویر فیش</p>
                </div>
                <div>
                    <button type="button" class="button button-secondary cartara-btn-export-csv">📥 دریافت خروجی اکسل / CSV</button>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="cartara-filter-bar">
                <div class="cartara-filter-group">
                    <a href="<?php echo admin_url('admin.php?page=cartara-receipts'); ?>" class="cartara-pill <?php echo empty($status_filter) ? 'active' : ''; ?>">همه</a>
                    <a href="<?php echo admin_url('admin.php?page=cartara-receipts&status=pending'); ?>" class="cartara-pill <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">در انتظار بررسی</a>
                    <a href="<?php echo admin_url('admin.php?page=cartara-receipts&status=verified'); ?>" class="cartara-pill <?php echo $status_filter === 'verified' ? 'active' : ''; ?>">تایید شده</a>
                    <a href="<?php echo admin_url('admin.php?page=cartara-receipts&status=rejected'); ?>" class="cartara-pill <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">رد شده</a>
                    <a href="<?php echo admin_url('admin.php?page=cartara-receipts&status=phishing'); ?>" class="cartara-pill <?php echo $status_filter === 'phishing' ? 'active' : ''; ?>">فیشینگ</a>
                </div>

                <form method="get" class="cartara-search-box">
                    <input type="hidden" name="page" value="cartara-receipts" />
                    <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="جستجو کد پیگیری، نام یا شماره سفارش..." />
                    <button type="submit" class="button">جستجو</button>
                </form>
            </div>

            <!-- Receipts Table -->
            <div class="cartara-table-container">
                <table class="wp-list-table widefat fixed striped cartara-table">
                    <thead>
                        <tr>
                            <th width="80">سفارش</th>
                            <th>واریزکننده</th>
                            <th>مبلغ</th>
                            <th>کد پیگیری</th>
                            <th>تاریخ واریز</th>
                            <th>تصویر فیش</th>
                            <th>نوع تایید</th>
                            <th>وضعیت</th>
                            <th width="160">عملیات سریع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receipts)): ?>
                            <tr>
                                <td colspan="9" class="cartara-empty-row">هیچ رسیدی با این شرایط یافت نشد.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($receipts as $r): ?>
                                <tr id="receipt-row-<?php echo $r['id']; ?>">
                                    <td>
                                        <a href="<?php echo admin_url('post.php?post=' . $r['order_id'] . '&action=edit'); ?>" target="_blank">
                                            <strong>#<?php echo esc_html($r['order_id']); ?></strong>
                                        </a>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($r['payer_name'] ?: '---'); ?></strong>
                                        <?php if (!empty($r['payer_card_last4'])): ?>
                                            <div class="cartara-subtext">کارت: **** <?php echo esc_html($r['payer_card_last4']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="cartara-amount"><?php echo number_format($r['amount']); ?></strong> ریال
                                    </td>
                                    <td>
                                        <code class="cartara-track-code"><?php echo esc_html($r['tracking_code'] ?: '---'); ?></code>
                                    </td>
                                    <td>
                                        <span><?php echo esc_html($r['payment_date']); ?></span>
                                        <div class="cartara-subtext"><?php echo esc_html($r['payment_time']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['file_url'])): ?>
                                            <?php if (pathinfo($r['file_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                                <a href="<?php echo esc_url($r['file_url']); ?>" target="_blank" class="button button-small">📄 فایل PDF</a>
                                            <?php else: ?>
                                                <img src="<?php echo esc_url($r['file_url']); ?>" class="cartara-table-thumb cartara-open-receipt-modal" data-url="<?php echo esc_url($r['file_url']); ?>" data-order="<?php echo $r['order_id']; ?>" data-track="<?php echo esc_attr($r['tracking_code']); ?>" alt="رسید" />
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="cartara-dash-muted">فاقد تصویر</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['verification_method'] === 'auto_sms'): ?>
                                            <span class="cartara-badge-auto">⚡ خودکار پیامک</span>
                                        <?php else: ?>
                                            <span class="cartara-badge-manual">👤 دستی</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="cartara-status-badge status-<?php echo esc_attr($r['status']); ?>">
                                            <?php
                                            switch ($r['status']) {
                                                case 'verified': echo '✅ تایید شده'; break;
                                                case 'rejected': echo '❌ رد شده'; break;
                                                case 'phishing': echo '⚠️ فیشینگ'; break;
                                                default: echo '⏳ در انتظار بررسی'; break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="cartara-action-buttons">
                                            <button type="button" class="button button-small button-primary cartara-btn-quick-verify" data-id="<?php echo $r['id']; ?>" data-order="<?php echo $r['order_id']; ?>" title="تایید پرداخت">✓</button>
                                            <button type="button" class="button button-small cartara-btn-quick-reject" data-id="<?php echo $r['id']; ?>" data-order="<?php echo $r['order_id']; ?>" title="رد پرداخت">✕</button>
                                            <button type="button" class="button button-small cartara-btn-quick-phishing" data-id="<?php echo $r['id']; ?>" data-order="<?php echo $r['order_id']; ?>" title="علامت‌گذاری فیشینگ">🛡️</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Lightbox Modal for Receipt Viewing -->
        <div id="cartara-receipt-modal" class="cartara-modal">
            <div class="cartara-modal-backdrop"></div>
            <div class="cartara-modal-content">
                <div class="cartara-modal-header">
                    <h3 id="cartara-modal-title">تصویر فیش واریزی</h3>
                    <button type="button" class="cartara-modal-close">✕</button>
                </div>
                <div class="cartara-modal-body">
                    <img id="cartara-modal-img" src="" alt="فیش پرداخت" />
                </div>
                <div class="cartara-modal-footer">
                    <button type="button" class="button cartara-modal-rotate">🔄 چرخش تصویر</button>
                    <a id="cartara-modal-download" href="" download class="button">⬇️ دانلود اصل تصویر</a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Bank Cards Management Page
     */
    public function render_cards_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_cards';
        $cards = $wpdb->get_results("SELECT * FROM $table ORDER BY priority ASC, id ASC", ARRAY_A);
        $all_banks = CartAra_Cards_Manager::get_all_supported_banks();

        ?>
        <div class="wrap cartara-admin-wrap" dir="rtl">
            <div class="cartara-header">
                <div>
                    <h1>💳 مدیریت حساب‌ها و کارت‌های بانکی</h1>
                    <p>تعریف کارت‌ها، شماره شبا، سقف واریزی روزانه و ترتیب نمایش در صفحه پرداخت</p>
                </div>
                <div>
                    <button type="button" class="button button-primary" id="cartara-btn-add-card">+ افزودن کارت بانکی جدید</button>
                </div>
            </div>

            <!-- Bank Cards Visual Grid -->
            <div class="cartara-cards-grid">
                <?php foreach ($cards as $card): ?>
                    <div class="cartara-virtual-card <?php echo esc_attr($card['card_color'] ?: 'gradient-blue'); ?>" id="card-card-<?php echo $card['id']; ?>">
                        <div class="card-chip-row">
                            <div class="card-emv-chip"></div>
                            <span class="card-bank-badge"><?php echo esc_html($card['bank_name']); ?></span>
                        </div>
                        <div class="card-number-display">
                            <?php echo esc_html(CartAra_Cards_Manager::format_card_number($card['card_number'])); ?>
                        </div>
                        <div class="card-meta-row">
                            <div>
                                <small>صاحب حساب</small>
                                <strong><?php echo esc_html($card['account_holder']); ?></strong>
                            </div>
                            <div>
                                <small>سقف امروز</small>
                                <strong><?php echo number_format($card['today_total']); ?> / <?php echo $card['daily_limit'] > 0 ? number_format($card['daily_limit']) : 'نامحدود'; ?></strong>
                            </div>
                        </div>
                        <div class="card-footer-sheba">
                            <small>شبا: <?php echo esc_html($card['sheba_number'] ?: '---'); ?></small>
                        </div>
                        <div class="card-admin-actions">
                            <button type="button" class="button button-small cartara-edit-card" data-card='<?php echo esc_attr(json_encode($card)); ?>'>✏️ ویرایش</button>
                            <button type="button" class="button button-small cartara-delete-card" data-id="<?php echo $card['id']; ?>">🗑️ حذف</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Card Add/Edit Modal -->
            <div id="cartara-card-modal" class="cartara-modal">
                <div class="cartara-modal-backdrop"></div>
                <div class="cartara-modal-content">
                    <div class="cartara-modal-header">
                        <h3 id="cartara-card-modal-title">افزودن کارت بانکی جدید</h3>
                        <button type="button" class="cartara-modal-close">✕</button>
                    </div>
                    <form id="cartara-card-form">
                        <input type="hidden" name="card_id" id="modal_card_id" value="0" />
                        <div class="cartara-modal-body">
                            <div class="cartara-form-row">
                                <label>نام بانک:</label>
                                <input type="text" name="bank_name" id="modal_bank_name" class="regular-text" required placeholder="مثال: بانک ملت" />
                            </div>
                            <div class="cartara-form-row">
                                <label>شماره کارت ۱۶ رقمی:</label>
                                <input type="text" name="card_number" id="modal_card_number" class="regular-text" maxlength="19" required placeholder="6037-9918-XXXX-XXXX" />
                            </div>
                            <div class="cartara-form-row">
                                <label>شماره شبا (IBAN):</label>
                                <input type="text" name="sheba_number" id="modal_sheba_number" class="regular-text" placeholder="IR000000000000000000000000" />
                            </div>
                            <div class="cartara-form-row">
                                <label>شماره حساب:</label>
                                <input type="text" name="account_number" id="modal_account_number" class="regular-text" placeholder="مثال: 0123456789" />
                            </div>
                            <div class="cartara-form-row">
                                <label>نام صاحب حساب:</label>
                                <input type="text" name="account_holder" id="modal_account_holder" class="regular-text" required placeholder="نام و نام خانوادگی" />
                            </div>
                            <div class="cartara-form-row">
                                <label>سقف واریزی روزانه (ریال):</label>
                                <input type="number" name="daily_limit" id="modal_daily_limit" value="500000000" class="regular-text" />
                                <small>۰ برای نامحدود بودن</small>
                            </div>
                            <div class="cartara-form-row">
                                <label>رنگ و استایل کارت:</label>
                                <select name="card_color" id="modal_card_color">
                                    <option value="gradient-blue">گرادیان آبی کهکشانی</option>
                                    <option value="gradient-red">گرادیان قرمز آتشین</option>
                                    <option value="gradient-gold">گرادیان طلایی لوکس</option>
                                    <option value="gradient-emerald">گرادیان زمردی سبز</option>
                                    <option value="gradient-purple">گرادیان بنفش مدرن</option>
                                    <option value="gradient-dark">گرادیان مشکی گرافیتی</option>
                                </select>
                            </div>
                        </div>
                        <div class="cartara-modal-footer">
                            <button type="submit" class="button button-primary" id="cartara-save-card-btn">💾 ذخیره اطلاعات کارت</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Auto Verify & Webhook Page
     */
    public function render_autoverify_page() {
        $settings = get_option('cartara_settings', []);
        $webhook_url = rest_url('cartara/v1/sms-webhook');
        $token = $settings['auto_verify_token'] ?? '';

        ?>
        <div class="wrap cartara-admin-wrap" dir="rtl">
            <div class="cartara-header">
                <div>
                    <h1>⚡ تایید خودکار پیامکی و اتصال اپلیکیشن همراه</h1>
                    <p>پیکربندی هوشمند وب‌هوک برای تایید لحظه‌ای سفارشات با دریافت پیامک‌های واریزی بانک</p>
                </div>
            </div>

            <div class="cartara-two-col-grid">
                <div class="cartara-card">
                    <div class="cartara-card-header">
                        <h2>🔗 مشخصات اتصال وب‌هوک (Webhook)</h2>
                    </div>
                    <div class="cartara-card-body">
                        <div class="cartara-code-box">
                            <label>آدرس Webhook URL جهت ارسال در اپلیکیشن فورواردر پیامک:</label>
                            <input type="text" class="large-text code" value="<?php echo esc_url($webhook_url); ?>" readonly />
                        </div>
                        <div class="cartara-code-box" style="margin-top: 15px;">
                            <label>توکن امنیتی احراز هویت (Security Token):</label>
                            <input type="text" class="large-text code" value="<?php echo esc_attr($token); ?>" readonly />
                        </div>
                        <p class="description" style="margin-top: 10px;">
                            این توکن را در اپلیکیشن اندروید فورواردر پیامک (CartAra Companion / SMS Forwarder) قرار دهید تا پیامک‌های دریافتی به صورت امن پردازش شوند.
                        </p>
                    </div>
                </div>

                <div class="cartara-card">
                    <div class="cartara-card-header">
                        <h2>🧪 شبیه‌ساز پیامک بانکی (تست آنی الگوریتم)</h2>
                    </div>
                    <div class="cartara-card-body">
                        <div class="cartara-form-row">
                            <label>متن پیامک بانک واریزی:</label>
                            <textarea id="cartara_test_sms_body" rows="4" class="large-text" placeholder="واریز به کارت 610433******1234&#10;مبلغ: 2,500,000 ریال&#10;پیگیری: 98765432&#10;موجودی: 15,000,000 ریال"></textarea>
                        </div>
                        <button type="button" class="button button-primary" id="cartara-btn-simulate-sms">🚀 اجرای تست پردازش پیامک</button>
                        <div id="cartara-sms-sim-result" style="margin-top: 15px; display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        if (isset($_POST['cartara_save_settings'])) {
            check_admin_referer('cartara_save_settings_nonce');

            $settings = [
                'enable_auto_verify' => sanitize_text_field($_POST['enable_auto_verify'] ?? 'no'),
                'auto_verify_token' => sanitize_text_field($_POST['auto_verify_token'] ?? ''),
                'enable_sms_notification' => sanitize_text_field($_POST['enable_sms_notification'] ?? 'no'),
                'sms_provider' => sanitize_text_field($_POST['sms_provider'] ?? 'kavenegar'),
                'sms_api_key' => sanitize_text_field($_POST['sms_api_key'] ?? ''),
                'sms_sender' => sanitize_text_field($_POST['sms_sender'] ?? ''),
                'enable_payment_timer' => sanitize_text_field($_POST['enable_payment_timer'] ?? 'no'),
                'timer_minutes' => intval($_POST['timer_minutes'] ?? 30),
                'enable_tax_exclusion' => sanitize_text_field($_POST['enable_tax_exclusion'] ?? 'no'),
                'enable_split_payment' => sanitize_text_field($_POST['enable_split_payment'] ?? 'no'),
                'require_receipt_upload' => sanitize_text_field($_POST['require_receipt_upload'] ?? 'no'),
                'require_tracking_code' => sanitize_text_field($_POST['require_tracking_code'] ?? 'no'),
                'require_payer_name' => sanitize_text_field($_POST['require_payer_name'] ?? 'no'),
                'require_payer_card_last4' => sanitize_text_field($_POST['require_payer_card_last4'] ?? 'no'),
                'max_file_size_mb' => intval($_POST['max_file_size_mb'] ?? 10),
                'theme_mode' => sanitize_text_field($_POST['theme_mode'] ?? 'modern_glass'),
                'enable_phishing_shield' => sanitize_text_field($_POST['enable_phishing_shield'] ?? 'no'),
                'enable_qr_code' => sanitize_text_field($_POST['enable_qr_code'] ?? 'no')
            ];

            update_option('cartara_settings', $settings);
            echo '<div class="notice notice-success is-dismissible"><p>تنظیمات با موفقیت ذخیره شد.</p></div>';
        }

        $settings = get_option('cartara_settings', []);

        ?>
        <div class="wrap cartara-admin-wrap" dir="rtl">
            <div class="cartara-header">
                <div>
                    <h1>⚙️ تنظیمات عمومی، پیامک و فرم پرداخت</h1>
                    <p>شخصی‌سازی جامع رفتار درگاه، اعتبارسنجی‌ها، سامانه پیامک و استایل‌های گرافیکی</p>
                </div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('cartara_save_settings_nonce'); ?>
                
                <div class="cartara-card">
                    <div class="cartara-card-header">
                        <h2>📱 تنظیمات سامانه پیامک (SMS)</h2>
                    </div>
                    <div class="cartara-card-body">
                        <table class="form-table">
                            <tr>
                                <th>فعال‌سازی اطلاع‌رسانی پیامکی:</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_sms_notification" value="yes" <?php checked($settings['enable_sms_notification'] ?? 'no', 'yes'); ?> />
                                        ارسال پیامک تایید سفارش و ثبت رسید به مشتری و مدیر
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>سامانه پیامکی:</th>
                                <td>
                                    <select name="sms_provider">
                                        <option value="kavenegar" <?php selected($settings['sms_provider'] ?? '', 'kavenegar'); ?>>کاوه‌نگار (Kavenegar)</option>
                                        <option value="ippanel" <?php selected($settings['sms_provider'] ?? '', 'ippanel'); ?>>فراز اس‌ام‌اس / IPPanel</option>
                                        <option value="smsir" <?php selected($settings['sms_provider'] ?? '', 'smsir'); ?>>اس‌ام‌اس دات آی‌آر (SMS.ir)</option>
                                        <option value="melipayamak" <?php selected($settings['sms_provider'] ?? '', 'melipayamak'); ?>>ملی‌پیامک (Melipayamak)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>کلید دسترسی API / رمز عبور:</th>
                                <td>
                                    <input type="password" name="sms_api_key" value="<?php echo esc_attr($settings['sms_api_key'] ?? ''); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th>شماره فرستنده / نام کاربری:</th>
                                <td>
                                    <input type="text" name="sms_sender" value="<?php echo esc_attr($settings['sms_sender'] ?? ''); ?>" class="regular-text" placeholder="مثال: 3000505" />
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="cartara-card" style="margin-top: 20px;">
                    <div class="cartara-card-header">
                        <h2>⏱️ مهلت پرداخت و قوانین فرم</h2>
                    </div>
                    <div class="cartara-card-body">
                        <table class="form-table">
                            <tr>
                                <th>تایمر شمارش معکوس پرداخت:</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_payment_timer" value="yes" <?php checked($settings['enable_payment_timer'] ?? 'no', 'yes'); ?> />
                                        فعال‌سازی تایمر مهلت پرداخت در صفحه تسویه حساب و فاکتور
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>مدت زمان مهلت پرداخت (دقیقه):</th>
                                <td>
                                    <input type="number" name="timer_minutes" value="<?php echo esc_attr($settings['timer_minutes'] ?? 30); ?>" class="small-text" /> دقیقه
                                </td>
                            </tr>
                            <tr>
                                <th>پرداخت چند کارته (Split Payment):</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_split_payment" value="yes" <?php checked($settings['enable_split_payment'] ?? 'no', 'yes'); ?> />
                                        امکان تقسیم مبلغ فاکتور و پرداخت از چند کارت مختلف
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>کیو‌آرکد پرداخت (QR Code):</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_qr_code" value="yes" <?php checked($settings['enable_qr_code'] ?? 'no', 'yes'); ?> />
                                        تولید کیو‌آرکد پویا جهت پرداخت آسان با همراه بانک
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="submit" style="margin-top: 25px;">
                    <button type="submit" name="cartara_save_settings" class="button button-primary button-hero">💾 ذخیره تغییرات تنظیمات</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX Handlers
     */
    public function ajax_update_order_status() {
        check_ajax_referer('cartara_admin_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $receipt_id = intval($_POST['receipt_id'] ?? 0);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cartara_receipts';

        if ($status === 'verified') {
            $order->payment_complete();
            $order->add_order_note(__('پرداخت توسط مدیر تایید شد.', 'cartara-pro'));
            $wpdb->update($table, ['status' => 'verified'], ['id' => $receipt_id]);
            CartAra_SMS_Notifier::send_payment_confirmed_sms($order);
        } elseif ($status === 'rejected') {
            $order->update_status('cancelled', __('پرداخت کارت به کارت توسط مدیر رد شد.', 'cartara-pro'));
            $wpdb->update($table, ['status' => 'rejected'], ['id' => $receipt_id]);
        } elseif ($status === 'phishing') {
            $order->update_status('phishing', __('⚠️ هشدار: این پرداخت به عنوان فیشینگ/مشکوک علامت‌گذاری شد.', 'cartara-pro'));
            $wpdb->update($table, ['status' => 'phishing'], ['id' => $receipt_id]);
        }

        wp_send_json_success(['message' => 'Status updated successfully']);
    }

    public function ajax_save_card() {
        check_ajax_referer('cartara_admin_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cartara_cards';

        $card_id = intval($_POST['card_id'] ?? 0);
        $data = [
            'bank_name' => sanitize_text_field($_POST['bank_name'] ?? ''),
            'bank_slug' => sanitize_title($_POST['bank_name'] ?? 'bank'),
            'card_number' => sanitize_text_field($_POST['card_number'] ?? ''),
            'sheba_number' => sanitize_text_field($_POST['sheba_number'] ?? ''),
            'account_number' => sanitize_text_field($_POST['account_number'] ?? ''),
            'account_holder' => sanitize_text_field($_POST['account_holder'] ?? ''),
            'daily_limit' => intval($_POST['daily_limit'] ?? 0),
            'card_color' => sanitize_text_field($_POST['card_color'] ?? 'gradient-blue'),
            'is_active' => 1
        ];

        if ($card_id > 0) {
            $wpdb->update($table, $data, ['id' => $card_id]);
        } else {
            $wpdb->insert($table, $data);
            $card_id = $wpdb->insert_id;
        }

        wp_send_json_success(['message' => 'Card saved', 'card_id' => $card_id]);
    }

    public function ajax_delete_card() {
        check_ajax_referer('cartara_admin_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cartara_cards';
        $card_id = intval($_POST['card_id'] ?? 0);

        $wpdb->delete($table, ['id' => $card_id]);
        wp_send_json_success(['message' => 'Card deleted']);
    }

    public function ajax_simulate_sms() {
        check_ajax_referer('cartara_admin_nonce', 'security');
        $sms_body = sanitize_textarea_field($_POST['sms_body'] ?? '');

        $parsed = CartAra_Auto_Verify::parse_bank_sms($sms_body);
        wp_send_json_success(['parsed' => $parsed]);
    }

    public function ajax_export_csv() {
        check_ajax_referer('cartara_admin_nonce', 'security');
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cartara_receipts';
        $results = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC", ARRAY_A);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=cartara-receipts-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($output, ['شناسه', 'سفارش', 'مبلغ', 'کد پیگیری', 'واریزکننده', 'کارت', 'تاریخ', 'وضعیت', 'روش تایید']);

        foreach ($results as $row) {
            fputcsv($output, [
                $row['id'],
                $row['order_id'],
                $row['amount'],
                $row['tracking_code'],
                $row['payer_name'],
                $row['payer_card_last4'],
                $row['payment_date'] . ' ' . $row['payment_time'],
                $row['status'],
                $row['verification_method']
            ]);
        }
        fclose($output);
        exit;
    }
}
new CartAra_Admin();
