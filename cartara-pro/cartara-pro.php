<?php
/**
 * Plugin Name: کارت‌آرا پرو | CartAra Pro - افزونه فوق پیشرفته پرداخت کارت به کارت ووکامرس
 * Plugin URI: https://github.com/s47367009-sketch/card
 * Description: جامع‌ترین و لوکس‌ترین درگاه پرداخت کارت به کارت، شبا و فیش بانکی برای ووکامرس با پشتیبانی از تایید خودکار پیامکی، کارت‌های چندگانه هوشمند، بارگذاری امن چند رسید، پرداخت دو مرحله‌ای (Split Payment)، تولید کیو‌آرکد پویا، پنل داشبورد تحلیلی مدرن، سیستم ضد فیشینگ و سازگاری کامل با HPOS.
 * Version: 5.0.0
 * Author: CartAra Pro Team
 * Author URI: https://cartara.local
 * Text Domain: cartara-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.3
 * License: GPLv2 or later
 */

defined('ABSPATH') || exit;

// Version definition
define('CARTARA_PRO_VERSION', '5.0.0');
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
            true
        );
    }
});

/**
 * Check requirements and initialize
 */
function cartara_pro_init() {
    // Load text domain
    load_plugin_textdomain('cartara-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');

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
add_action('plugins_loaded', 'cartara_pro_init', 11);

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function () {
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-core.php';
    CartAra_Core::activate();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    require_once CARTARA_PRO_DIR . 'includes/class-cartara-core.php';
    CartAra_Core::deactivate();
});
