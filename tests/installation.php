<?php
// Isolated lifecycle regression tests; no WordPress or database required.
// Run: php tests/installation.php [missing|old|supported]
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
});
$mode = $argv[1] ?? 'missing';
$root = sys_get_temp_dir() . '/cartara-test-' . uniqid();
mkdir($root . '/wp-admin/includes', 0777, true);
file_put_contents($root . '/wp-admin/includes/upgrade.php', '<?php');
define('ABSPATH', $root . '/');
$hooks = $options = [];
$upload_error = false;
function add_action($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter($hook, $callback, $priority = 10, $args = 1) { add_action($hook, $callback); }
function add_shortcode($name, $callback) {}
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'https://example.test/plugins/cartara-pro/'; }
function register_activation_hook($file, $callback) { $GLOBALS['activation'] = $callback; }
function register_deactivation_hook($file, $callback) {}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function wp_generate_password($length, $special = true) { return str_repeat('x', $length); }
function wp_upload_dir() { return ['basedir' => ABSPATH . 'uploads', 'error' => $GLOBALS['upload_error']]; }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
class WP_Error { public $code; function __construct($code, $message) { $this->code = $code; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
function check($condition, $message) { if (!$condition) { throw new Exception($message); } }
class FakeDB {
    public $prefix = 'wp_';
    public $tables = [];
    public $fail = false;
    function get_charset_collate() { return ''; }
    function esc_like($value) { return $value; }
    function prepare($query, $value) { return $value; }
    function get_var($table) { return $this->tables[$table] ?? null; }
}
$wpdb = new FakeDB();
function dbDelta($sql) {
    check(strpos($sql, 'IF NOT EXISTS') === false, 'dbDelta must receive plain CREATE TABLE');
    preg_match('/CREATE TABLE (\w+)/', $sql, $matches);
    if (!$GLOBALS['wpdb']->fail) { $GLOBALS['wpdb']->tables[$matches[1]] = $matches[1]; }
}
if ($mode !== 'missing') {
    class WC_Payment_Gateway {}
    define('WC_VERSION', $mode === 'old' ? '4.9.0' : '9.3.0');
}
require dirname(__DIR__) . '/cartara-pro/cartara-pro.php';
cartara_pro_init();
if ($mode === 'supported') {
    check(class_exists('WC_Gateway_CartAra'), 'Gateway must load with WooCommerce');
    check(isset($hooks['woocommerce_register_shop_order_post_statuses']), 'Status hooks must be registered');
} else {
    check(!class_exists('WC_Gateway_CartAra'), 'Gateway must not load without a supported parent');
    check(isset($hooks['admin_notices']), 'Missing dependency must produce an admin notice');
}
require_once dirname(__DIR__) . '/cartara-pro/includes/class-cartara-core.php';
check(CartAra_Core::activate() === true, 'First activation failed');
check(count($wpdb->tables) === 2, 'Both tables must be created');
check(is_file(ABSPATH . 'uploads/cartara_receipts/.htaccess'), 'Upload protection missing');
$options['cartara_settings']['custom_setting'] = 'keep';
check(CartAra_Core::activate() === true, 'Repeat activation failed');
check($options['cartara_settings']['custom_setting'] === 'keep', 'Existing settings overwritten');
unlink(ABSPATH . 'uploads/cartara_receipts/.htaccess');
check(CartAra_Core::prepare_upload_directory() === true, 'Existing upload directory repair failed');
check(is_file(ABSPATH . 'uploads/cartara_receipts/.htaccess'), 'Protection was not restored');
$upload_error = 'Permission denied';
check(is_wp_error(CartAra_Core::activate()), 'Upload failure must return an error');
$upload_error = false;
$wpdb->tables = [];
$wpdb->fail = true;
check(is_wp_error(CartAra_Core::activate()), 'Database failure must return an error');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
rmdir($root);
echo "PASS: $mode dependency, activation, repeat activation, settings preservation, upload repair and failure paths\n";
