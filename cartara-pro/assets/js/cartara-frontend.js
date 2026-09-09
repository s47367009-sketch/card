/**
 * CartAra Pro Frontend Scripts
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        initCardSelector();
        initTabs();
        initCopyButtons();
        initDropzone();
        initTimer();
        initSplitPayment();
        renderQRCode();
    });

    /**
     * Card Selection Interaction
     */
    function initCardSelector() {
        $(document).on('click', '.cartara-card-box', function () {
            $('.cartara-card-box').removeClass('selected');
            $(this).addClass('selected');

            var cardId = $(this).data('card-id');
            var cardNum = $(this).data('card-num');
            var sheba = $(this).data('sheba');
            var account = $(this).data('account');

            $('#cartara_selected_card_id').val(cardId);

            // Format card number
            var formattedCard = formatCardStr(cardNum.toString());
            $('#active-card-number-txt').text(formattedCard);
            $('#active-sheba-txt').text(sheba || '---');
            $('#active-account-txt').text(account || '---');

            renderQRCode(cardNum, sheba);
        });
    }

    /**
     * Tabs Handler
     */
    function initTabs() {
        $(document).on('click', '.cartara-tab-btn', function () {
            var tabId = $(this).data('tab');
            $('.cartara-tab-btn').removeClass('active');
            $(this).addClass('active');

            $('.cartara-tab-panel').removeClass('active');
            $('#' + tabId).addClass('active');
        });
    }

    /**
     * Copy to Clipboard with Visual Toast
     */
    function initCopyButtons() {
        // Individual Card Copy
        $(document).on('click', '.cartara-btn-copy', function (e) {
            e.stopPropagation();
            var text = $(this).data('text');
            copyToClipboard(text);
        });

        // Tab Quick Action Copy
        $(document).on('click', '#btn-copy-active-card', function () {
            var text = $('.cartara-card-box.selected').data('card-num') || '';
            copyToClipboard(text);
        });

        $(document).on('click', '#btn-copy-active-sheba', function () {
            var text = $('.cartara-card-box.selected').data('sheba') || '';
            copyToClipboard(text);
        });

        $(document).on('click', '#btn-copy-active-account', function () {
            var text = $('.cartara-card-box.selected').data('account') || '';
            copyToClipboard(text);
        });
    }

    function copyToClipboard(text) {
        if (!text) return;
        var clean = text.toString().replace(/[^0-9a-zA-Z]/g, '');

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(clean).then(function () {
                showToast('✅ شماره با موفقیت کپی شد: ' + clean);
            });
        } else {
            var tempInput = $('<input>');
            $('body').append(tempInput);
            tempInput.val(clean).select();
            document.execCommand('copy');
            tempInput.remove();
            showToast('✅ شماره با موفقیت کپی شد: ' + clean);
        }
    }

    function showToast(msg) {
        $('.cartara-toast').remove();
        var toast = $('<div class="cartara-toast">' + msg + '</div>');
        $('body').append(toast);
        setTimeout(function () {
            toast.fadeOut(300, function () { $(this).remove(); });
        }, 3000);
    }

    /**
     * Dropzone & File Preview
     */
    function initDropzone() {
        var dropzone = $('#cartara-dropzone');
        var fileInput = $('#cartara_receipt_file');

        dropzone.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.addClass('dragover');
        });

        dropzone.on('dragleave drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.removeClass('dragover');
        });

        fileInput.on('change', function (e) {
            var file = e.target.files[0];
            if (file) {
                handleFilePreview(file);
            }
        });

        $(document).on('click', '#cartara-remove-file', function (e) {
            e.preventDefault();
            fileInput.val('');
            $('#cartara-preview-box').hide();
            $('#cartara-dropzone-prompt').show();
        });
    }

    function handleFilePreview(file) {
        if (!file) return;

        $('#cartara-filename-preview').text(file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)');

        if (file.type.match('image.*')) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $('#cartara-img-preview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            $('#cartara-img-preview').hide();
        }

        $('#cartara-dropzone-prompt').hide();
        $('#cartara-preview-box').show();
    }

    /**
     * Payment Countdown Timer
     */
    function initTimer() {
        var banner = $('.cartara-timer-banner');
        if (!banner.length) return;

        var totalSeconds = parseInt(banner.data('duration')) || 1800;
        var clockEl = $('#cartara-countdown-clock');
        var barEl = $('#cartara-timer-bar');

        var storageKey = 'cartara_timer_end';
        var endTime = sessionStorage.getItem(storageKey);

        if (!endTime) {
            endTime = Date.now() + (totalSeconds * 1000);
            sessionStorage.setItem(storageKey, endTime);
        }

        var interval = setInterval(function () {
            var now = Date.now();
            var remaining = Math.max(0, Math.floor((endTime - now) / 1000));

            var mins = Math.floor(remaining / 60);
            var secs = remaining % 60;

            clockEl.text((mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs);

            var pct = (remaining / totalSeconds) * 100;
            barEl.css('width', pct + '%');

            if (remaining <= 0) {
                clearInterval(interval);
                clockEl.text('منقضی شد');
                showToast('⚠️ مهلت پرداخت شما به پایان رسید.');
            }
        }, 1000);
    }

    /**
     * Split Payment Toggle
     */
    function initSplitPayment() {
        $('#cartara-toggle-split').on('change', function () {
            if ($(this).is(':checked')) {
                $('.cartara-split-fields').slideDown(200);
            } else {
                $('.cartara-split-fields').slideUp(200);
            }
        });
    }

    /**
     * Dynamic QR Canvas Generator (UPI / Bank link representation)
     */
    function renderQRCode(cardNum, sheba) {
        var canvas = document.getElementById('cartara-qr-canvas');
        if (!canvas) return;

        var ctx = canvas.getContext('2d');
        var size = 180;
        ctx.clearRect(0, 0, size, size);

        // Draw modern stylised QR placeholder pattern
        ctx.fillStyle = '#1e293b';
        var boxSize = 6;
        var padding = 15;

        // Draw corner position squares
        drawPositionSquare(ctx, padding, padding, 36);
        drawPositionSquare(ctx, size - padding - 36, padding, 36);
        drawPositionSquare(ctx, padding, size - padding - 36, 36);

        // Draw pseudo QR data matrix
        for (var x = padding; x < size - padding; x += boxSize) {
            for (var y = padding; y < size - padding; y += boxSize) {
                // Skip corner areas
                if ((x < padding + 40 && y < padding + 40) ||
                    (x > size - padding - 40 && y < padding + 40) ||
                    (x < padding + 40 && y > size - padding - 40)) {
                    continue;
                }
                if (Math.random() > 0.55) {
                    ctx.fillRect(x, y, boxSize - 1, boxSize - 1);
                }
            }
        }

        // Center card logo symbol
        ctx.fillStyle = '#6366f1';
        ctx.fillRect(size / 2 - 14, size / 2 - 14, 28, 28);
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 12px Vazirmatn, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('💳', size / 2, size / 2);
    }

    function drawPositionSquare(ctx, x, y, s) {
        ctx.fillRect(x, y, s, s);
        ctx.clearRect(x + 5, y + 5, s - 10, s - 10);
        ctx.fillRect(x + 10, y + 10, s - 20, s - 20);
    }

    function formatCardStr(num) {
        var clean = num.replace(/[^0-9]/g, '');
        return clean.replace(/(\d{4})(?=\d)/g, '$1-');
    }

})(jQuery);
