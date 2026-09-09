/**
 * CartAra Pro Admin Panel Scripts
 */

(function ($) {
    'use strict';

    var currentRotation = 0;

    $(document).ready(function () {
        initChart();
        initReceiptModal();
        initCardModal();
        initQuickActions();
        initSmsSimulator();
    });

    /**
     * Render 7-day Analytics Chart
     */
    function initChart() {
        var ctx = document.getElementById('cartaraWeeklyChart');
        if (!ctx || typeof Chart === 'undefined') return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'],
                datasets: [{
                    label: 'مبلغ واریزی (میلیون ریال)',
                    data: [120, 190, 300, 250, 420, 380, 510],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'تعداد فیش‌ها',
                    data: [4, 7, 11, 9, 15, 12, 18],
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { labels: { font: { family: 'Vazirmatn' } } }
                },
                scales: {
                    x: { ticks: { font: { family: 'Vazirmatn' } } },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        ticks: { font: { family: 'Vazirmatn' } }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        grid: { drawOnChartArea: false },
                        ticks: { font: { family: 'Vazirmatn' } }
                    }
                }
            }
        });
    }

    /**
     * Receipt Lightbox Modal
     */
    function initReceiptModal() {
        $(document).on('click', '.cartara-open-receipt-modal', function () {
            var url = $(this).data('url');
            var order = $(this).data('order');
            var track = $(this).data('track');

            currentRotation = 0;
            $('#cartara-modal-img').attr('src', url).css('transform', 'rotate(0deg)');
            $('#cartara-modal-download').attr('href', url);
            $('#cartara-modal-title').text('تصویر فیش سفارش #' + order + ' (پیگیری: ' + (track || '---') + ')');
            $('#cartara-receipt-modal').addClass('open');
        });

        $(document).on('click', '.cartara-modal-close, .cartara-modal-backdrop', function () {
            $('.cartara-modal').removeClass('open');
        });

        $(document).on('click', '.cartara-modal-rotate', function () {
            currentRotation = (currentRotation + 90) % 360;
            $('#cartara-modal-img').css('transform', 'rotate(' + currentRotation + 'deg)');
        });
    }

    /**
     * Card Modal
     */
    function initCardModal() {
        $('#cartara-btn-add-card').on('click', function () {
            $('#cartara-card-form')[0].reset();
            $('#modal_card_id').val('0');
            $('#cartara-card-modal-title').text('افزودن کارت بانکی جدید');
            $('#cartara-card-modal').addClass('open');
        });

        $(document).on('click', '.cartara-edit-card', function () {
            var card = $(this).data('card');
            if (typeof card === 'string') card = JSON.parse(card);

            $('#modal_card_id').val(card.id);
            $('#modal_bank_name').val(card.bank_name);
            $('#modal_card_number').val(card.card_number);
            $('#modal_sheba_number').val(card.sheba_number);
            $('#modal_account_number').val(card.account_number);
            $('#modal_account_holder').val(card.account_holder);
            $('#modal_daily_limit').val(card.daily_limit);
            $('#modal_card_color').val(card.card_color || 'gradient-blue');

            $('#cartara-card-modal-title').text('ویرایش اطلاعات کارت ' + card.bank_name);
            $('#cartara-card-modal').addClass('open');
        });

        $('#cartara-card-form').on('submit', function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            formData += '&action=cartara_admin_save_card&security=' + cartara_admin_vars.nonce;

            $.post(cartara_admin_vars.ajax_url, formData, function (res) {
                if (res.success) {
                    alert('کارت بانکی با موفقیت ذخیره شد.');
                    location.reload();
                } else {
                    alert('خطا در ذخیره کارت');
                }
            });
        });

        $(document).on('click', '.cartara-delete-card', function () {
            if (!confirm(cartara_admin_vars.i18n.confirm_delete)) return;
            var cardId = $(this).data('id');

            $.post(cartara_admin_vars.ajax_url, {
                action: 'cartara_admin_delete_card',
                card_id: cardId,
                security: cartara_admin_vars.nonce
            }, function (res) {
                if (res.success) {
                    $('#card-card-' + cardId).fadeOut(300, function () { $(this).remove(); });
                }
            });
        });
    }

    /**
     * 1-Click Quick Verify / Reject / Phishing Actions
     */
    function initQuickActions() {
        $(document).on('click', '.cartara-btn-quick-verify', function () {
            var id = $(this).data('id');
            var orderId = $(this).data('order');
            updateOrderStatus(id, orderId, 'verified');
        });

        $(document).on('click', '.cartara-btn-quick-reject', function () {
            var id = $(this).data('id');
            var orderId = $(this).data('order');
            updateOrderStatus(id, orderId, 'rejected');
        });

        $(document).on('click', '.cartara-btn-quick-phishing', function () {
            if (!confirm(cartara_admin_vars.i18n.confirm_phishing)) return;
            var id = $(this).data('id');
            var orderId = $(this).data('order');
            updateOrderStatus(id, orderId, 'phishing');
        });

        // Metabox buttons
        $(document).on('click', '.cartara-btn-approve-order', function () {
            var orderId = $(this).data('order');
            updateOrderStatus(0, orderId, 'verified');
        });
        $(document).on('click', '.cartara-btn-reject-order', function () {
            var orderId = $(this).data('order');
            updateOrderStatus(0, orderId, 'rejected');
        });
        $(document).on('click', '.cartara-btn-phishing-order', function () {
            if (!confirm(cartara_admin_vars.i18n.confirm_phishing)) return;
            var orderId = $(this).data('order');
            updateOrderStatus(0, orderId, 'phishing');
        });
    }

    function updateOrderStatus(receiptId, orderId, status) {
        $.post(cartara_admin_vars.ajax_url, {
            action: 'cartara_admin_update_status',
            receipt_id: receiptId,
            order_id: orderId,
            status: status,
            security: cartara_admin_vars.nonce
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert('خطا در تغییر وضعیت: ' + (res.data ? res.data.message : 'خطای نامشخص'));
            }
        });
    }

    /**
     * SMS Simulator
     */
    function initSmsSimulator() {
        $('#cartara-btn-simulate-sms').on('click', function () {
            var sms = $('#cartara_test_sms_body').val();
            if (!sms) return;

            $.post(cartara_admin_vars.ajax_url, {
                action: 'cartara_admin_simulate_sms',
                sms_body: sms,
                security: cartara_admin_vars.nonce
            }, function (res) {
                if (res.success && res.data && res.data.parsed) {
                    var p = res.data.parsed;
                    var html = '<div class="notice notice-info" style="padding: 12px;">' +
                        '<strong>✅ نتیجه پردازش پیامک:</strong><br/>' +
                        'مبلغ استخراج‌شده: <b>' + (p.amount ? p.amount.toLocaleString() + ' ریال' : 'تشخیص داده نشد') + '</b><br/>' +
                        'کارت/حساب: <b>' + (p.card_number || '---') + '</b><br/>' +
                        'کد پیگیری/مرجع: <b>' + (p.ref_number || '---') + '</b>' +
                        '</div>';
                    $('#cartara-sms-sim-result').html(html).slideDown();
                }
            });
        });
    }

})(jQuery);
