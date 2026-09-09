<?php
/**
 * Plugin Name: کارت‌آرا پرو | CartAra Pro - افزونه فوق پیشرفته پرداخت کارت به کارت ووکامرس
 * Plugin URI: https://github.com/s47367009-sketch/card
 * Description: جامع‌ترین و لوکس‌ترین درگاه پرداخت کارت به کارت، شبا و فیش بانکی برای ووکامرس با پشتیبانی از تایید خودکار پیامکی، کارت‌های چندگانه هوشمند، بارگذاری امن چند رسید، پرداخت دو مرحله‌ای (Split Payment)، تولید کیو‌آرکد پویا، پنل داشبورد تحلیلی مدرن، سیستم ضد فیشینگ و سازگاری کامل با HPOS.
 * Version: 5.0.1
 * Author: CartAra Pro Team
 * Author URI: https://cartara.local
 * Text Domain: cartara-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.3
 * License: GPLv2 or later
 */

defined('ABSPATH') || exit;

// Version definition
define('CARTARA_PRO_VERSION', '5.0.1');
define('CARTARA_PRO_FILE', __FILE__);
define('CARTARA_PRO_DIR', plugin_dir_path(__FILE__));
define('CARTARA_PRO_URL', plugin_dir_url(__FILE__));
define('CARTARA_PRO_UPLOAD_DIR_NAME', 'cartara_receipts');

// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            CARTARA_PRO_FILE,
            true
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            CARTARA_PRO_FILE,
            false
        );
    }
});

/**
 * Check requirements and initialize
 */
function cartara_pro_init() {
    // Never load a gateway whose parent class is unavailable.
    if (!class_exists('WC_Payment_Gateway') || !defined('WC_VERSION') || version_compare(WC_VERSION, '5.0', '<')) {
        add_action('admin_notices', 'cartara_pro_dependency_notice');
        return;
    }

    // Autoload includes
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-core.php';
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-cards-manager.php';
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-receipts.php';
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-auto-verify.php';
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-sms-notifier.php';
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-gateway.php';
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-admin.php';
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-shortcodes.php';

    // Boot the core
    CartAra_Core::instance();
}
// Register hooks before WooCommerce registers its order statuses on init.
add_action('plugins_loaded', 'cartara_pro_init', 20);

function cartara_pro_dependency_notice() {
    if (current_user_can('activate_plugins')) {
        echo '<div class="notice notice-error"><p>' . esc_html__('کارت‌آرا پرو به ووکامرس فعال نسخه ۵ یا بالاتر نیاز دارد. ابتدا ووکامرس را نصب و فعال کنید.', 'cartara-pro') . '</p></div>';
    }
}
add_action('init', function () {
    load_plugin_textdomain('cartara-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 5);

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function () {
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-core.php';
    $result = CartAra_Core::activate();
    if (is_wp_error($result)) {
        wp_die(esc_html($result->get_error_message()), 'CartAra Pro', ['back_link' => true]);
    }
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-core.php';
    CartAra_Core::deactivate();
});
