<?php
defined('ABSPATH') || exit;

if (!isset($order) || !$order) return;

$order_id = $order->get_id();
$payer_name = $order->get_meta('_cartara_payer_name');
$payer_last4 = $order->get_meta('_cartara_payer_card_last4');
$tracking_code = $order->get_meta('_cartara_tracking_code');
$payment_date = $order->get_meta('_cartara_payment_date');
$receipt_url = $order->get_meta('_cartara_last_receipt_url');
$receipts = CartAra_Receipts::get_order_receipts($order_id);
$order_status = $order->get_status();
?>

<div class="cartara-thankyou-wrapper" dir="rtl">
    <div class="cartara-receipt-header">
        <div class="cartara-header-left">
            <h3>📑 جزئیات پرداخت کارت به کارت سفارش #<?php echo $order_id; ?></h3>
            <span class="cartara-status-pill status-<?php echo esc_attr($order_status); ?>">
                <?php echo wc_get_order_status_name($order_status); ?>
            </span>
        </div>
    </div>

    <div class="cartara-receipt-summary-grid">
        <div class="cartara-sum-item">
            <span class="cartara-sum-label">مبلغ پرداخت شده:</span>
            <strong class="cartara-sum-value"><?php echo wc_price($order->get_total()); ?></strong>
        </div>
        <div class="cartara-sum-item">
            <span class="cartara-sum-label">نام واریزکننده:</span>
            <strong class="cartara-sum-value"><?php echo esc_html($payer_name ?: '---'); ?></strong>
        </div>
        <div class="cartara-sum-item">
            <span class="cartara-sum-label">۴ رقم آخر کارت مبدا:</span>
            <strong class="cartara-sum-value"><?php echo esc_html($payer_last4 ? '**** ' . $payer_last4 : '---'); ?></strong>
        </div>
        <div class="cartara-sum-item">
            <span class="cartara-sum-label">شماره پیگیری / ارجاع:</span>
            <strong class="cartara-sum-value cartara-code"><?php echo esc_html($tracking_code ?: '---'); ?></strong>
        </div>
    </div>

    <!-- Receipts Gallery -->
    <?php if (!empty($receipts)): ?>
        <div class="cartara-receipts-gallery">
            <h4>🖼️ تصاویر و مدارک ثبت شده:</h4>
            <div class="cartara-gallery-grid">
                <?php foreach ($receipts as $rc): ?>
                    <div class="cartara-gallery-item">
                        <?php if (pathinfo($rc['file_url'], PATHINFO_EXTENSION) === 'pdf'): ?>
                            <a href="<?php echo esc_url($rc['file_url']); ?>" target="_blank" class="cartara-pdf-card">
                                <span class="cartara-pdf-badge">PDF</span>
                                <p>مشاهده فایل رسید</p>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo esc_url($rc['file_url']); ?>" target="_blank" class="cartara-img-card">
                                <img src="<?php echo esc_url($rc['file_url']); ?>" alt="رسید سفارش" />
                            </a>
                        <?php endif; ?>
                        <div class="cartara-gallery-meta">
                            <small>کد پیگیری: <?php echo esc_html($rc['tracking_code'] ?: $tracking_code); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Secondary upload form if no receipt was provided or order is on-hold -->
    <?php if (empty($receipt_url) || in_array($order_status, ['pending', 'on-hold', 'wc-pending-receipt'])): ?>
        <div class="cartara-reupload-box">
            <h4>📤 ارسال مجدد یا اصلاح تصویر فیش بانکی:</h4>
            <form id="cartara-ajax-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="cartara_upload_receipt" />
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>" />
                <input type="hidden" name="security" value="<?php echo wp_create_nonce('cartara_frontend_nonce'); ?>" />

                <div class="cartara-grid-2">
                    <input type="text" name="tracking_code" placeholder="شماره پیگیری جدید..." class="cartara-field" />
                    <input type="file" name="file" accept="image/*,application/pdf" class="cartara-field" required />
                </div>
                <button type="submit" class="button button-primary" style="margin-top: 10px;">ثبت و بارگذاری فیش جدید</button>
            </form>
        </div>
    <?php endif; ?>
</div>
