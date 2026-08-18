<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

// STUBS DEVEM VIR ANTES DO REQUIRE DO PLUGIN
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($f) { return rtrim(dirname($f), '/') . '/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($f) { return ''; } }
if (!function_exists('plugin_basename')) { function plugin_basename($f) { return ''; } }
if (!function_exists('load_plugin_textdomain')) { function load_plugin_textdomain(...$a) { return true; } }
if (!function_exists('register_activation_hook')) { function register_activation_hook(...$a) {} }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook(...$a) {} }
if (!function_exists('dbDelta')) { function dbDelta($sql) { return 1; } }
if (!function_exists('update_option')) { function update_option($k, $v) { return true; } }
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $d; } }
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('register_rest_route')) { function register_rest_route(...$a) { return true; } }
if (!function_exists('flush_rewrite_rules')) { function flush_rewrite_rules() {} }
if (!function_exists('is_admin')) { function is_admin() { return false; } }

// wpdb mock
class TestWpdb {
    public string $prefix = 'wptools_';
    public string $last_error = '';
    public array $queries = [];
    public function get_charset_collate(): string { return 'DEFAULT CHARSET=utf8mb4'; }
    public function query($sql) { $this->queries[] = (string) $sql; return 1; }
    public function get_results($s) { return []; }
    public function get_row($s) { return null; }
    public function get_var($s) { return null; }
    public function insert($t, $d) { return 1; }
    public function update($t, $d, $w) { return 1; }
    public function delete($t, $w) { return 1; }
    public function prepare($s, ...$a) { return $s; }
}
global $wpdb;
$wpdb = new TestWpdb();

define('ABSPATH', __DIR__ . '/');
define('WP_PLUGIN_DIR', __DIR__ . '/gestor-api');

// Carrega o plugin
$plugin_dir = __DIR__ . '/gestor-api';
require_once $plugin_dir . '/gestor-api.php';

// Roda install
Gestor_Api\DB\Schema::install();

echo "Queries executadas: " . count($wpdb->queries) . "\n";
echo "--- triggers ---\n";
$drops = 0; $creates = 0;
foreach ($wpdb->queries as $i => $q) {
    if (stripos($q, 'TRIGGER') !== false) {
        echo "  [$i] " . trim(substr($q, 0, 80)) . "...\n";
    }
    if (stripos($q, 'DROP TRIGGER') !== false) $drops++;
    if (stripos($q, 'CREATE TRIGGER') !== false) $creates++;
}
echo "Total: DROP=$drops CREATE=$creates\n";

if ($drops === 2 && $creates === 2) {
    echo "PASS: 2 DROP + 2 CREATE separados\n";
    exit(0);
}
echo "FAIL: esperado 2+2 triggers\n";
exit(1);
