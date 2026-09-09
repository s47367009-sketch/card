<?php
defined('ABSPATH') || exit;

class WC_Gateway_CartAra extends WC_Payment_Gateway {

    public $instructions = '';

    public function __construct() {
        $this->id = 'cartara_card';
        $this->icon = apply_filters('cartara_gateway_icon', '');
        $this->has_fields = true;
        $this->method_title = __('پرداخت کارت به کارت و فیش بانکی (کارت‌آرا)', 'cartara-pro');
        $this->method_description = __('درگاه پرداخت کارت به کارت هوشمند با کارت‌های شیشه‌ای، کیو‌آرکد، اعتبارسنجی سریع و آپلود مدرن رسید.', 'cartara-pro');

        $this->supports = [
            'products',
            'refunds'
        ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', __('کارت به کارت / فیش بانکی', 'cartara-pro'));
        $this->description = $this->get_option('description', __('مبلغ سفارش را به یکی از کارت‌های زیر واریز کرده و اطلاعات رسید را ثبت نمایید.', 'cartara-pro'));
        $this->instructions = $this->get_option('instructions', __('لطفاً پس از انتقال وجه، تصویر یا کد رهگیری فیش پرداختی را ثبت کنید.', 'cartara-pro'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        add_action('woocommerce_order_details_after_order_table', [$this, 'order_details_receipt_box']);

        // AJAX for checkout receipt upload
        add_action('wp_ajax_cartara_upload_receipt', [$this, 'ajax_upload_receipt']);
        add_action('wp_ajax_nopriv_cartara_upload_receipt', [$this, 'ajax_upload_receipt']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => __('فعال‌سازی درگاه', 'cartara-pro'),
                'type' => 'checkbox',
                'label' => __('فعال‌سازی پرداخت کارت به کارت کارت‌آرا', 'cartara-pro'),
                'default' => 'yes'
            ],
            'title' => [
                'title' => __('عنوان درگاه', 'cartara-pro'),
                'type' => 'text',
                'description' => __('عنوانی که مشتری در صفحه تسویه حساب مشاهده می‌کند.', 'cartara-pro'),
                'default' => __('پرداخت کارت به کارت / فیش بانکی 💳', 'cartara-pro'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('توضیحات درگاه', 'cartara-pro'),
                'type' => 'textarea',
                'description' => __('توضیحاتی که زیر عنوان درگاه در صفحه پرداخت نمایش داده می‌شود.', 'cartara-pro'),
                'default' => __('انتقال وجه از طریق کلیه همراه بانک‌ها، عابربانک‌ها و اپلیکیشن‌های پرداخت (آپ، ۷۲۴، بلو، دیجی‌پی و...) با تایید سریع.', 'cartara-pro'),
            ],
            'instructions' => [
                'title' => __('راهنمای بعد از خرید', 'cartara-pro'),
                'type' => 'textarea',
                'description' => __('پیامی که در صفحه تشکر پس از ثبت سفارش نمایش داده می‌شود.', 'cartara-pro'),
                'default' => __('سفارش شما با موفقیت ثبت شد. کارشناسان ما به محض بررسی رسید واریزی، سفارش شما را آماده و ارسال خواهند کرد.', 'cartara-pro'),
            ],
            'initial_order_status' => [
                'title' => __('وضعیت اولیه سفارش', 'cartara-pro'),
                'type' => 'select',
                'options' => [
                    'wc-card-to-card' => __('در انتظار بررسی کارت به کارت (پیشنهادی)', 'cartara-pro'),
                    'wc-pending-receipt' => __('در انتظار ارسال رسید', 'cartara-pro'),
                    'wc-on-hold' => __('در انتظار بررسی (ووکامرس)', 'cartara-pro'),
                    'wc-pending' => __('در انتظار پرداخت', 'cartara-pro')
                ],
                'default' => 'wc-card-to-card'
            ],
            'enable_discount' => [
                'title' => __('تخفیف تشویقی پرداخت کارت به کارت', 'cartara-pro'),
                'type' => 'checkbox',
                'label' => __('اعمال تخفیف برای مشتریانی که روش کارت به کارت را انتخاب می‌کنند', 'cartara-pro'),
                'default' => 'no'
            ],
            'discount_percent' => [
                'title' => __('درصد تخفیف (%)', 'cartara-pro'),
                'type' => 'number',
                'default' => '2',
                'description' => __('درصد تخفیف از مجموع سبد خرید', 'cartara-pro')
            ]
        ];
    }

    public function payment_fields() {
        if ($this->description) {
            echo '<div class="cartara-gateway-desc">' . wpautop(wp_kses_post($this->description)) . '</div>';
        }

        $cart_total = WC()->cart ? WC()->cart->get_total('edit') : 0;
        $cards = CartAra_Cards_Manager::get_active_cards($cart_total);

        if (empty($cards)) {
            echo '<div class="cartara-notice cartara-notice-warning">' . esc_html__('در حال حاضر کارت بانکی فعالی ثبت نشده است.', 'cartara-pro') . '</div>';
            return;
        }

        // Include checkout template
        include CARTARA_PRO_DIR . 'templates/checkout-form.php';
    }

    public function validate_fields() {
        $settings = get_option('cartara_settings', []);
        
        if (($settings['require_payer_name'] ?? 'yes') === 'yes' && empty($_POST['cartara_payer_name'])) {
            wc_add_notice(__('لطفاً نام و نام خانوادگی صاحب کارت واریزکننده را وارد کنید.', 'cartara-pro'), 'error');
            return false;
        }

        if (($settings['require_payer_card_last4'] ?? 'yes') === 'yes') {
            $last4 = sanitize_text_field($_POST['cartara_payer_card_last4'] ?? '');
            if (empty($last4) || strlen($last4) < 4) {
                wc_add_notice(__('لطفاً حداقل ۴ رقم آخر شماره کارت مبدا را وارد کنید.', 'cartara-pro'), 'error');
                return false;
            }
        }

        if (($settings['require_tracking_code'] ?? 'yes') === 'yes' && empty($_POST['cartara_tracking_code'])) {
            wc_add_notice(__('لطفاً شماره پیگیری / شماره ارجاع فیش بانکی را وارد کنید.', 'cartara-pro'), 'error');
            return false;
        }

        return true;
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $settings = get_option('cartara_settings', []);
        $initial_status = $this->get_option('initial_order_status', 'wc-card-to-card');

        // Extract and sanitize input data
        $payer_name = sanitize_text_field($_POST['cartara_payer_name'] ?? '');
        $payer_card_last4 = sanitize_text_field($_POST['cartara_payer_card_last4'] ?? '');
        $tracking_code = sanitize_text_field($_POST['cartara_tracking_code'] ?? '');
        $selected_card_id = intval($_POST['cartara_selected_card_id'] ?? 0);
        $payment_date = sanitize_text_field($_POST['cartara_payment_date'] ?? current_time('Y/m/d'));
        $payment_time = sanitize_text_field($_POST['cartara_payment_time'] ?? current_time('H:i'));

        // Handle uploaded file if present in form submit
        if (!empty($_FILES['cartara_receipt_file']) && !empty($_FILES['cartara_receipt_file']['name'])) {
            $upload_res = CartAra_Receipts::handle_upload($_FILES['cartara_receipt_file'], $order_id, [
                'card_id' => $selected_card_id,
                'amount' => $order->get_total(),
                'tracking_code' => $tracking_code,
                'payer_name' => $payer_name,
                'payer_card_last4' => $payer_card_last4,
                'payment_date' => $payment_date,
                'payment_time' => $payment_time
            ]);

            if (is_wp_error($upload_res)) {
                wc_add_notice($upload_res->get_error_message(), 'error');
                return;
            }
        }

        // Save order meta
        $order->update_meta_data('_cartara_payer_name', $payer_name);
        $order->update_meta_data('_cartara_payer_card_last4', $payer_card_last4);
        $order->update_meta_data('_cartara_tracking_code', $tracking_code);
        $order->update_meta_data('_cartara_selected_card_id', $selected_card_id);
        $order->update_meta_data('_cartara_payment_date', $payment_date);
        $order->update_meta_data('_cartara_payment_time', $payment_time);

        // Update card daily usage
        if ($selected_card_id > 0) {
            CartAra_Cards_Manager::record_card_usage($selected_card_id, $order->get_total());
        }

        // Set status
        $status_slug = str_replace('wc-', '', $initial_status);
        $order->update_status($status_slug, sprintf(
            __('پرداخت کارت به کارت ثبت شد. نام واریزکننده: %s | ۴ رقم آخر: %s | پیگیری: %s', 'cartara-pro'),
            $payer_name ?: '---',
            $payer_card_last4 ?: '---',
            $tracking_code ?: '---'
        ));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Clear cart
        WC()->cart->empty_cart();

        // Send SMS notifications
        CartAra_SMS_Notifier::send_receipt_submitted_sms($order, $tracking_code);

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    }

    public function thankyou_page($order_id) {
        if ($this->instructions) {
            echo '<div class="cartara-thankyou-instructions">' . wpautop(wp_kses_post($this->instructions)) . '</div>';
        }
        $order = wc_get_order($order_id);
        if ($order) {
            include CARTARA_PRO_DIR . 'templates/thankyou-order-view.php';
        }
    }

    public function order_details_receipt_box($order) {
        if ($order->get_payment_method() === $this->id) {
            include CARTARA_PRO_DIR . 'templates/thankyou-order-view.php';
        }
    }

    public function ajax_upload_receipt() {
        check_ajax_referer('cartara_frontend_nonce', 'security');

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(['message' => __('شناسه سفارش نامعتبر است.', 'cartara-pro')]);
        }

        $res = CartAra_Receipts::handle_upload($_FILES['file'] ?? [], $order_id, [
            'tracking_code' => sanitize_text_field($_POST['tracking_code'] ?? ''),
            'payer_name' => sanitize_text_field($_POST['payer_name'] ?? ''),
            'payer_card_last4' => sanitize_text_field($_POST['payer_card_last4'] ?? ''),
            'amount' => sanitize_text_field($_POST['amount'] ?? 0),
        ]);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => __('رسید با موفقیت بارگذاری شد.', 'cartara-pro'),
                'data' => $res
            ]);
        }
    }
}
