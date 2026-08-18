<?php
/**
 * Smoke test da logica core que nao depende de WP.
 * Roda com: php tests/smoke-core.php
 */

declare(strict_types=1);

// Stub de constantes WP usadas pelas classes core.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
if (!defined('GESTOR_API_VERSION')) {
    define('GESTOR_API_VERSION', '0.1.0');
}
if (!defined('GESTOR_API_TABLE_PREFIX')) {
    define('GESTOR_API_TABLE_PREFIX', 'gestor_');
}

// Stubs minimos para o codigo carregar.
if (!function_exists('__')) {
    function __($s, $d = '') { return $s; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($s, $d = '') { return $s; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) { return trim((string) $s); }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email($s) { return filter_var((string) $s, FILTER_SANITIZE_EMAIL); }
}
if (!function_exists('is_email')) {
    function is_email($s) { return (bool) filter_var((string) $s, FILTER_VALIDATE_EMAIL); }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($s) { return (string) $s; }
}
if (!class_exists('WP_Error')) {
    class WP_Error {
        public string $code;
        public string $message;
        public array $data;
        public function __construct(string $code = '', string $message = '', array $data = []) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function get_error_data(string $key = ''): mixed {
            if ($key === '') return $this->data;
            return $this->data[$key] ?? null;
        }
    }
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
}
if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = false) {
        return gmdate('Y-m-d H:i:s');
    }
}
if (!function_exists('update_option')) {
    function update_option($k, $v) { return true; }
}
if (!function_exists('get_option')) {
    function get_option($k, $d = null) { return $d; }
}
if (!function_exists('delete_option')) {
    function delete_option($k) { return true; }
}
if (!function_exists('get_transient')) {
    function get_transient($k) { return false; }
}
if (!function_exists('set_transient')) {
    function set_transient($k, $v, $e = 0) { return true; }
}
if (!function_exists('add_action')) {
    function add_action(...$a) {}
}
if (!function_exists('add_filter')) {
    function add_filter(...$a) {}
}
if (!function_exists('register_activation_hook')) {
    function register_activation_hook(...$a) {}
}
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(...$a) {}
}
if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain(...$a) {}
}
if (!function_exists('plugin_basename')) {
    function plugin_basename($f) { return basename(dirname($f)) . '/' . basename($f); }
}
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($f) { return dirname($f) . '/'; }
}
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($f) { return ''; }
}
if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules() {}
}
if (!function_exists('wp_die')) {
    function wp_die($m = '') { throw new \RuntimeException($m); }
}
if (!function_exists('deactivate_plugins')) {
    function deactivate_plugins($p) {}
}
if (!function_exists('get_role')) {
    function get_role($r) { return null; }
}
if (!function_exists('spl_autoload_register')) {
    // ja existe em PHP
}

// Carrega ULID.
require __DIR__ . '/../gestor-api/includes/util/class-ulid.php';
$ns_prefix = 'Gestor_Api\\';
spl_autoload_register(static function (string $class) use ($ns_prefix): void {
    if (strncmp($class, $ns_prefix, strlen($ns_prefix)) !== 0) return;
    $relative = substr($class, strlen($ns_prefix));
    $relative = str_replace('_', '-', $relative);
    $parts = explode('\\', $relative);
    $class_file = 'class-' . strtolower(array_pop($parts)) . '.php';
    $subdir = empty($parts) ? '' : strtolower(implode('/', $parts)) . '/';
    $path = __DIR__ . '/../gestor-api/includes/' . $subdir . $class_file;
    if (is_file($path)) {
        require_once $path;
    }
});

use Gestor_Api\Util\Ulid;
use Gestor_Api\Util\Validator;
use Gestor_Api\Util\Gestor_Api_Validation_Exception;

$passou = 0;
$falhou = 0;
function check(string $nome, bool $cond): void {
    global $passou, $falhou;
    if ($cond) {
        echo "  [OK]  $nome\n";
        $GLOBALS['passou']++;
    } else {
        echo "  [FAIL] $nome\n";
        $GLOBALS['falhou']++;
    }
}

echo "\n=== ULID ===\n";
$u1 = Ulid::generate();
check('ULID gerado tem 26 chars', strlen($u1) === 26);
check('ULID gerado valido (regex)', Ulid::is_valid($u1));

// Gera 1000 ULIDs e checa que todos sao unicos.
$ulids = [];
for ($i = 0; $i < 1000; $i++) {
    $ulids[] = Ulid::generate();
}
check('1000 ULIDs unicos', count(array_unique($ulids)) === 1000);

// ULID monotonic: dois no mesmo ms.
$u_a = Ulid::generate(1700000000000);
$u_b = Ulid::generate(1700000000000);
check('ULID monotonic: 2 mesmo ms, b > a', strcmp($u_b, $u_a) > 0);

// Conversao timestamp.
$ts = Ulid::to_timestamp($u1);
check('ULID timestamp decodificado (prox 1.7T)', $ts > 1_500_000_000_000 && $ts < 1_900_000_000_000);

check('ULID invalido detectado', !Ulid::is_valid('not-a-ulid'));
check('ULID com chars lowercase invalido', !Ulid::is_valid('abcdefghijklmnopqrstuvwxyz'));

echo "\n=== Validator ===\n";
check('email valido', Validator::email('test@example.com') === 'test@example.com');
check('email normalizado pra lowercase', Validator::email('TEST@EXAMPLE.COM') === 'test@example.com');

try {
    Validator::email('not-an-email');
    check('email invalido rejeitado', false);
} catch (Gestor_Api_Validation_Exception $e) {
    check('email invalido rejeitado', true);
}

check('senha valida', Validator::senha('Abcdef12') === 'Abcdef12');
try {
    Validator::senha('curta');
    check('senha curta rejeitada', false);
} catch (Gestor_Api_Validation_Exception $e) {
    check('senha curta rejeitada', true);
}

try {
    Validator::senha('abcdefghi'); // sem maiuscula
    check('senha sem maiuscula rejeitada', false);
} catch (Gestor_Api_Validation_Exception $e) {
    check('senha sem maiuscula rejeitada', true);
}

check('enum OK', Validator::enum('ALTA', 'p', Validator::PRIORIDADE) === 'ALTA');
try {
    Validator::enum('INVALIDA', 'p', Validator::PRIORIDADE);
    check('enum invalido rejeitado', false);
} catch (Gestor_Api_Validation_Exception $e) {
    check('enum invalido rejeitado', true);
}

$u = Validator::ulid('', 'id', true);
check('ulid auto-gerado quando vazio', Ulid::is_valid($u));
check('ulid valido passa direto', Validator::ulid($u1, 'id', false) === $u1);
check('ulid opcional null', Validator::ulid_optional(null, 'id') === null);
check('ulid opcional com valor', Validator::ulid_optional($u1, 'id') === $u1);
try {
    Validator::ulid('bad', 'id', false);
    check('ulid invalido rejeitado', false);
} catch (Gestor_Api_Validation_Exception $e) {
    check('ulid invalido rejeitado', true);
}

check('iso8601 valido', Validator::iso8601('2026-08-17T00:00:00.000Z', 'd') === '2026-08-17T00:00:00.000Z');
check('iso8601 null', Validator::iso8601(null, 'd') === null);
try {
    Validator::iso8601('not a date', 'd');
    check('iso8601 invalido rejeitado', false);
} catch (Gestor_Api_Validation_Exception $e) {
    check('iso8601 invalido rejeitado', true);
}

check('json valido array', Validator::json('{"a":1}', 'j') === ['a' => 1]);
check('json array direto', Validator::json(['a' => 1], 'j') === ['a' => 1]);
check('decimal 0-1', Validator::decimal_0_1(0.5, 'd') === 0.5);
check('decimal default 0', Validator::decimal_0_1(null, 'd') === 0.0);

echo "\n=== ULID monotonic (mesmo timestamp, comparacao) ===\n";
$base = Ulid::generate(1_700_000_000_000);
$next = Ulid::generate(1_700_000_000_000);
check('ULID monotonic (b > a)', strcmp($next, $base) > 0);

echo "\n=== RESUMO ===\n";
echo "Passou: $passou\n";
echo "Falhou: $falhou\n";
exit($falhou > 0 ? 1 : 0);
