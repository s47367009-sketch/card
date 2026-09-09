<?php
defined('ABSPATH') || exit;

$settings = get_option('cartara_settings', []);
$cards = isset($cards) ? $cards : CartAra_Cards_Manager::get_active_cards();
$default_card = !empty($cards) ? $cards[0] : null;
$enable_timer = ($settings['enable_payment_timer'] ?? 'yes') === 'yes';
$timer_mins = intval($settings['timer_minutes'] ?? 30);
$enable_split = ($settings['enable_split_payment'] ?? 'yes') === 'yes';
$enable_qr = ($settings['enable_qr_code'] ?? 'yes') === 'yes';
?>

<div class="cartara-checkout-container" dir="rtl">
    <!-- Header Notification & Timer -->
    <?php if ($enable_timer): ?>
        <div class="cartara-timer-banner" data-duration="<?php echo $timer_mins * 60; ?>">
            <div class="cartara-timer-icon">⏳</div>
            <div class="cartara-timer-text">
                <span>مهلت تکمیل و ثبت فیش پرداخت:</span>
                <strong id="cartara-countdown-clock">--:--</strong>
            </div>
            <div class="cartara-timer-progress">
                <div class="cartara-progress-bar" id="cartara-timer-bar"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bank Cards Carousel / Selector -->
    <div class="cartara-section-title">
        <h4>💳 کارت بانکی مقصد را انتخاب کنید:</h4>
        <small>برای کپی شماره کارت یا شبا، روی دکمه کپی کلیک کنید</small>
    </div>

    <div class="cartara-cards-slider-container">
        <div class="cartara-cards-track">
            <?php foreach ($cards as $idx => $card): 
                $is_selected = ($idx === 0);
            ?>
                <div class="cartara-card-box <?php echo esc_attr($card['card_color'] ?: 'gradient-blue'); ?> <?php echo $is_selected ? 'selected' : ''; ?>"
                     data-card-id="<?php echo $card['id']; ?>"
                     data-card-num="<?php echo esc_attr(preg_replace('/[^0-9]/', '', $card['card_number'])); ?>"
                     data-sheba="<?php echo esc_attr($card['sheba_number']); ?>"
                     data-account="<?php echo esc_attr($card['account_number']); ?>"
                     data-holder="<?php echo esc_attr($card['account_holder']); ?>"
                     data-bank="<?php echo esc_attr($card['bank_name']); ?>">
                    
                    <div class="card-glass-glow"></div>

                    <div class="card-top-row">
                        <div class="card-chip-svg"></div>
                        <div class="card-bank-header">
                            <span class="card-bank-badge"><?php echo esc_html($card['bank_name']); ?></span>
                        </div>
                    </div>

                    <div class="card-center-number">
                        <span class="card-digits"><?php echo esc_html(CartAra_Cards_Manager::format_card_number($card['card_number'])); ?></span>
                        <button type="button" class="cartara-btn-copy" data-text="<?php echo esc_attr(preg_replace('/[^0-9]/', '', $card['card_number'])); ?>" title="کپی شماره کارت">
                            <span class="copy-icon">📋</span>
                            <span class="copy-label">کپی کارت</span>
                        </button>
                    </div>

                    <div class="card-bottom-row">
                        <div class="card-holder-info">
                            <span class="card-label">نام صاحب حساب</span>
                            <strong class="card-val"><?php echo esc_html($card['account_holder']); ?></strong>
                        </div>
                        <div class="card-shaba-info">
                            <span class="card-label">شماره شبا (IBAN)</span>
                            <strong class="card-val-sheba"><?php echo esc_html($card['sheba_number'] ?: '---'); ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Hidden input for selected card -->
    <input type="hidden" name="cartara_selected_card_id" id="cartara_selected_card_id" value="<?php echo $default_card ? $default_card['id'] : 0; ?>" />

    <!-- Method Switcher Tabs (Card Number, Sheba, Account, QR Code) -->
    <div class="cartara-tabs-nav">
        <button type="button" class="cartara-tab-btn active" data-tab="tab-card">💳 پرداخت با کارت</button>
        <button type="button" class="cartara-tab-btn" data-tab="tab-sheba">🏛️ انتقال پایا / ساتنا (شبا)</button>
        <button type="button" class="cartara-tab-btn" data-tab="tab-account">🏦 شماره حساب</button>
        <?php if ($enable_qr): ?>
            <button type="button" class="cartara-tab-btn" data-tab="tab-qr">📱 اسکن بارکد QR</button>
        <?php endif; ?>
    </div>

    <!-- Tab Contents -->
    <div class="cartara-tabs-content">
        <!-- Tab 1: Card -->
        <div class="cartara-tab-panel active" id="tab-card">
            <div class="cartara-quick-copy-bar">
                <span>شماره کارت فعال:</span>
                <strong id="active-card-number-txt"><?php echo $default_card ? esc_html(CartAra_Cards_Manager::format_card_number($default_card['card_number'])) : ''; ?></strong>
                <button type="button" class="cartara-btn-action-copy" id="btn-copy-active-card">📋 کپی شماره کارت</button>
            </div>
        </div>

        <!-- Tab 2: Sheba -->
        <div class="cartara-tab-panel" id="tab-sheba">
            <div class="cartara-quick-copy-bar">
                <span>شماره شبا:</span>
                <strong id="active-sheba-txt"><?php echo $default_card ? esc_html($default_card['sheba_number']) : '---'; ?></strong>
                <button type="button" class="cartara-btn-action-copy" id="btn-copy-active-sheba">📋 کپی شماره شبا</button>
            </div>
        </div>

        <!-- Tab 3: Account -->
        <div class="cartara-tab-panel" id="tab-account">
            <div class="cartara-quick-copy-bar">
                <span>شماره حساب:</span>
                <strong id="active-account-txt"><?php echo $default_card ? esc_html($default_card['account_number']) : '---'; ?></strong>
                <button type="button" class="cartara-btn-action-copy" id="btn-copy-active-account">📋 کپی شماره حساب</button>
            </div>
        </div>

        <!-- Tab 4: QR Code -->
        <?php if ($enable_qr): ?>
            <div class="cartara-tab-panel" id="tab-qr">
                <div class="cartara-qr-center">
                    <div class="cartara-qr-box" id="cartara-qr-code-holder">
                        <!-- Dynamic SVG QR Code will be rendered via JS -->
                        <div class="cartara-qr-placeholder">
                            <canvas id="cartara-qr-canvas" width="180" height="180"></canvas>
                        </div>
                    </div>
                    <p>با دوربین گوشی یا اپلیکیشن‌های پرداخت (آپ، همراه بانک، بلوبانک) اسکن کنید</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Split Payment Toggle (Optional) -->
    <?php if ($enable_split): ?>
        <div class="cartara-split-accordion">
            <label class="cartara-split-label">
                <input type="checkbox" id="cartara-toggle-split" />
                <span>🔀 پرداخت از طریق دو یا چند کارت مختلف (تقسیم مبلغ بالای سقف)</span>
            </label>
            <div class="cartara-split-fields" style="display: none;">
                <p class="cartara-subtext">در صورتی که مبلغ فاکتور بیش از سقف کارت شماست، می‌توانید رسیدهای هر تراکنش را به همراه مبلغ مجزا آپلود نمایید.</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Receipt Upload & Payer Input Fields -->
    <div class="cartara-form-box">
        <h4 class="cartara-form-title">📝 فرم ثبت مشخصات و فیش واریزی:</h4>

        <div class="cartara-grid-2">
            <div class="cartara-input-group">
                <label for="cartara_payer_name">نام و نام خانوادگی صاحب کارت واریزکننده <span class="req">*</span></label>
                <input type="text" name="cartara_payer_name" id="cartara_payer_name" class="cartara-field" placeholder="مثال: علی رضایی" required />
            </div>

            <div class="cartara-input-group">
                <label for="cartara_payer_card_last4">۴ رقم آخر کارت مبدا <span class="req">*</span></label>
                <input type="text" name="cartara_payer_card_last4" id="cartara_payer_card_last4" class="cartara-field" maxlength="4" placeholder="مثال: 5678" required />
            </div>
        </div>

        <div class="cartara-grid-2">
            <div class="cartara-input-group">
                <label for="cartara_tracking_code">شماره پیگیری / شماره ارجاع فیش <span class="req">*</span></label>
                <input type="text" name="cartara_tracking_code" id="cartara_tracking_code" class="cartara-field" placeholder="مثال: 98765432" required />
            </div>

            <div class="cartara-input-group">
                <label for="cartara_payment_date">تاریخ واریز</label>
                <input type="text" name="cartara_payment_date" id="cartara_payment_date" class="cartara-field" value="<?php echo date('Y/m/d'); ?>" />
            </div>
        </div>

        <!-- Drag & Drop Receipt Upload Box -->
        <div class="cartara-upload-zone" id="cartara-dropzone">
            <input type="file" name="cartara_receipt_file" id="cartara_receipt_file" accept="image/jpeg,image/png,image/webp,application/pdf" />
            <div class="cartara-dropzone-inner" id="cartara-dropzone-prompt">
                <div class="cartara-upload-cloud-icon">☁️</div>
                <p class="cartara-upload-main-text"><strong>تصویر فیش یا فایل PDF</strong> را بکشید و اینجا رها کنید</p>
                <span class="cartara-upload-subtext">یا برای انتخاب فایل کلیک کنید (حداکثر ۱۰ مگابایت)</span>
            </div>
            <div class="cartara-preview-box" id="cartara-preview-box" style="display: none;">
                <img id="cartara-img-preview" src="" alt="پیش‌نمایش رسید" />
                <div class="cartara-preview-info">
                    <span id="cartara-filename-preview">نام فایل</span>
                    <button type="button" class="cartara-remove-file" id="cartara-remove-file">✕ حذف فایل</button>
                </div>
            </div>
        </div>
    </div>
</div>
