<?php
define('ABSPATH', __DIR__);
define('GESTOR_API_VERSION', '0.1.0');
require 'E:\Projetos\LOPES FOCUS\wp-api\gestor-api\includes\util\class-ulid.php';
use Gestor_Api\Util\Ulid;

$u = Ulid::generate();
echo 'Len: ' . strlen($u) . PHP_EOL;
echo 'ULID: ' . $u . PHP_EOL;
echo 'Valid: ' . (Ulid::is_valid($u) ? 'sim' : 'nao') . PHP_EOL;
$ts = substr($u, 0, 10);
echo 'Time chars: ' . $ts . PHP_EOL;

// Tentando manualmente
$base32 = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
echo 'Base32 length: ' . strlen($base32) . PHP_EOL;

// Decodifica time chars
$value = 0;
$len = strlen($ts);
for ($i = 0; $i < $len; $i++) {
    $char = $ts[$i];
    $idx = strpos($base32, $char);
    if ($idx === false) {
        echo "Char invalido: $char\n";
        break;
    }
    $value = $value * 32 + $idx;
}
echo "Decoded timestamp ms: $value\n";

// Encoda 1737000000000 (~2025)
function encodeTime($timestamp_ms, $base32) {
    $out = '';
    $value = $timestamp_ms;
    for ($i = 9; $i >= 0; $i--) {
        $mod = $value & 31;
        $out = $base32[$mod] . $out;
        $value = (int) (($value - $mod) / 32);
    }
    return $out;
}
$ts1 = encodeTime(time() * 1000, $base32);
echo "Encode manual: $ts1 (len " . strlen($ts1) . ")\n";

// Teste isolado
echo "Test generate 10 ULIDs:\n";
for ($i = 0; $i < 10; $i++) {
    $x = Ulid::generate();
    echo "  len=" . strlen($x) . " valid=" . (Ulid::is_valid($x) ? 'Y' : 'N') . " $x\n";
}
