<?php
defined('ABSPATH') || exit;

class CartAra_Receipts {

    public static function handle_upload($file_array, $order_id, $extra_data = []) {
        if (empty($file_array) || empty($file_array['name'])) {
            return new WP_Error('no_file', __('هیچ فایلی برای بارگذاری ارسال نشده است.', 'cartara-pro'));
        }

        $settings = get_option('cartara_settings', []);
        $max_mb = isset($settings['max_file_size_mb']) ? intval($settings['max_file_size_mb']) : 10;
        $max_size = $max_mb * 1024 * 1024;

        if ($file_array['size'] > $max_size) {
            return new WP_Error('file_too_large', sprintf(__('حجم فایل بیش از حد مجاز است. حداکثر حجم: %d مگابایت', 'cartara-pro'), $max_mb));
        }

        // Allowed extensions and MIME types
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $file_ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_exts)) {
            return new WP_Error('invalid_extension', __('پسوند فایل مجاز نیست. فقط تصاویر JPG, PNG, WEBP و فایل PDF مجاز است.', 'cartara-pro'));
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_array['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = [
            'image/jpeg', 'image/pjpeg',
            'image/png', 'image/x-png',
            'image/webp',
            'application/pdf', 'application/x-pdf'
        ];

        if (!in_array($mime_type, $allowed_mimes)) {
            return new WP_Error('invalid_mime', __('نوع محتوای فایل نامعتبر و ناامن است.', 'cartara-pro'));
        }

        // Prepare upload directory
        $wp_upload_dir = wp_upload_dir();
        $cartara_dir = $wp_upload_dir['basedir'] . '/' . CARTARA_PRO_UPLOAD_DIR_NAME;
        $cartara_url = $wp_upload_dir['baseurl'] . '/' . CARTARA_PRO_UPLOAD_DIR_NAME;

        if (!file_exists($cartara_dir)) {
            wp_mkdir_p($cartara_dir);
        }

        // Generate safe randomized filename
        $safe_order_id = intval($order_id);
        $random_token = wp_generate_password(16, false, false);
        $new_filename = sprintf('receipt_%d_%s_%s.%s', $safe_order_id, date('Ymd_His'), $random_token, $file_ext);
        $target_file_path = $cartara_dir . '/' . $new_filename;
        $target_file_url = $cartara_url . '/' . $new_filename;

        // Move uploaded file
        if (!move_uploaded_file($file_array['tmp_name'], $target_file_path)) {
            return new WP_Error('upload_failed', __('خطا در ذخیره‌سازی فایل روی هاست.', 'cartara-pro'));
        }

        // Sanitize Image & Strip EXIF if image
        if ($file_ext !== 'pdf' && function_exists('imagecreatefromstring')) {
            self::sanitize_image($target_file_path, $file_ext);
        }

        // Save into DB
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_receipts';

        $insert_data = [
            'order_id' => $safe_order_id,
            'user_id' => get_current_user_id(),
            'card_id' => isset($extra_data['card_id']) ? intval($extra_data['card_id']) : 0,
            'amount' => isset($extra_data['amount']) ? intval($extra_data['amount']) : 0,
            'tracking_code' => isset($extra_data['tracking_code']) ? sanitize_text_field($extra_data['tracking_code']) : '',
            'payer_name' => isset($extra_data['payer_name']) ? sanitize_text_field($extra_data['payer_name']) : '',
            'payer_card_last4' => isset($extra_data['payer_card_last4']) ? sanitize_text_field($extra_data['payer_card_last4']) : '',
            'payer_bank' => isset($extra_data['payer_bank']) ? sanitize_text_field($extra_data['payer_bank']) : '',
            'payment_date' => isset($extra_data['payment_date']) ? sanitize_text_field($extra_data['payment_date']) : current_time('Y/m/d'),
            'payment_time' => isset($extra_data['payment_time']) ? sanitize_text_field($extra_data['payment_time']) : current_time('H:i'),
            'file_url' => esc_url_raw($target_file_url),
            'file_path' => sanitize_text_field($target_file_path),
            'file_name' => sanitize_text_field($file_array['name']),
            'file_size' => intval($file_array['size']),
            'status' => 'pending',
            'verification_method' => 'manual',
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'created_at' => current_time('mysql')
        ];

        $wpdb->insert($table, $insert_data);
        $receipt_id = $wpdb->insert_id;

        // Save order meta for easy access
        $order = wc_get_order($safe_order_id);
        if ($order) {
            $existing_receipts = $order->get_meta('_cartara_receipts') ?: [];
            $existing_receipts[] = [
                'id' => $receipt_id,
                'url' => $target_file_url,
                'tracking_code' => $insert_data['tracking_code'],
                'amount' => $insert_data['amount'],
                'payer_name' => $insert_data['payer_name'],
                'date' => $insert_data['payment_date'],
                'status' => 'pending'
            ];
            $order->update_meta_data('_cartara_receipts', $existing_receipts);
            $order->update_meta_data('_cartara_tracking_code', $insert_data['tracking_code']);
            $order->update_meta_data('_cartara_last_receipt_url', $target_file_url);
            $order->save();
        }

        return [
            'id' => $receipt_id,
            'file_url' => $target_file_url,
            'file_name' => $new_filename,
            'tracking_code' => $insert_data['tracking_code']
        ];
    }

    /**
     * Recreate image to strip EXIF data and malware scripts
     */
    private static function sanitize_image($path, $ext) {
        if (!file_exists($path)) return;

        $image_data = @file_get_contents($path);
        if (!$image_data) return;

        $img = @imagecreatefromstring($image_data);
        if ($img !== false) {
            if ($ext === 'png') {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                imagepng($img, $path, 8);
            } elseif ($ext === 'webp' && function_exists('imagewebp')) {
                imagewebp($img, $path, 85);
            } else {
                imagejpeg($img, $path, 85);
            }
            imagedestroy($img);
        }
    }

    /**
     * Get all receipts for an order
     */
    public static function get_order_receipts($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'cartara_receipts';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE order_id = %d ORDER BY id DESC", $order_id), ARRAY_A);
    }
}
