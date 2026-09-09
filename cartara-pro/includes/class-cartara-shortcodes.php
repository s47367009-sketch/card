<?php
defined('ABSPATH') || exit;

class CartAra_Shortcodes {

    public function __construct() {
        add_shortcode('cartara_pay', [$this, 'render_direct_payment_shortcode']);
        add_shortcode('cartara_cards', [$this, 'render_cards_shortcode']);
        add_shortcode('cartara_upload', [$this, 'render_upload_shortcode']);
    }

    /**
     * Standalone Direct Payment Gateway Shortcode
     * Usage: [cartara_pay amount="250000" title="شارژ حساب"]
     */
    public function render_direct_payment_shortcode($atts) {
        $atts = shortcode_atts([
            'amount' => 0,
            'title' => __('پرداخت مستقیم کارت به کارت', 'cartara-pro'),
            'description' => __('لطفاً مبلغ مورد نظر را به یکی از کارت‌های زیر واریز کرده و فرم زیر را تکمیل نمایید.', 'cartara-pro')
        ], $atts, 'cartara_pay');

        $cards = CartAra_Cards_Manager::get_active_cards($atts['amount']);
        ob_start();
        ?>
        <div class="cartara-standalone-wrapper" dir="rtl">
            <div class="cartara-standalone-header">
                <h3>💳 <?php echo esc_html($atts['title']); ?></h3>
                <p><?php echo esc_html($atts['description']); ?></p>
                <?php if (!empty($atts['amount'])): ?>
                    <div class="cartara-amount-badge">
                        <span>مبلغ قابل پرداخت:</span>
                        <strong><?php echo number_format($atts['amount']); ?></strong> ریال
                    </div>
                <?php endif; ?>
            </div>

            <div class="cartara-cards-container">
                <?php include CARTARA_PRO_DIR . 'templates/checkout-form.php'; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Display Cards Visual List
     */
    public function render_cards_shortcode($atts) {
        $cards = CartAra_Cards_Manager::get_active_cards();
        ob_start();
        ?>
        <div class="cartara-cards-list-wrapper" dir="rtl">
            <div class="cartara-cards-slider">
                <?php foreach ($cards as $card): ?>
                    <div class="cartara-card-item <?php echo esc_attr($card['card_color'] ?: 'gradient-blue'); ?>">
                        <div class="card-chip-row">
                            <span class="card-chip"></span>
                            <span class="card-bank-name"><?php echo esc_html($card['bank_name']); ?></span>
                        </div>
                        <div class="card-num-text">
                            <?php echo esc_html(CartAra_Cards_Manager::format_card_number($card['card_number'])); ?>
                            <button type="button" class="cartara-copy-btn" data-copy="<?php echo esc_attr(preg_replace('/[^0-9]/', '', $card['card_number'])); ?>" title="کپی شماره کارت">📋</button>
                        </div>
                        <div class="card-footer-info">
                            <span>صاحب حساب: <?php echo esc_html($card['account_holder']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Standalone Upload Receipt Shortcode
     */
    public function render_upload_shortcode($atts) {
        ob_start();
        ?>
        <div class="cartara-upload-box-wrapper" dir="rtl">
            <div class="cartara-upload-dropzone">
                <input type="file" id="cartara_standalone_file" accept="image/*,application/pdf" />
                <div class="cartara-dropzone-content">
                    <span class="cartara-upload-icon">📤</span>
                    <p>فایل تصویر فیش یا PDF واریزی را اینجا رها کنید یا کلیک کنید</p>
                    <small>حداکثر ۱۰ مگابایت (JPG, PNG, PDF)</small>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
new CartAra_Shortcodes();
