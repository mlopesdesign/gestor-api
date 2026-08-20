<?php
declare(strict_types=1);
$zip_path = $argv[1] ?? null;
if (!$zip_path) { fwrite(STDERR, "Uso: php _validate-zip.php <zip>\n"); exit(1); }
$z = new ZipArchive();
$ok = $z->open($zip_path);
if ($ok !== true) { fwrite(STDERR, "Falha ao abrir ZIP\n"); exit(1); }
$entries = [];
for ($i = 0; $i < $z->numFiles; $i++) {
    $entries[] = $z->getNameIndex($i);
}
$z->close();
echo "Total entries: " . count($entries) . PHP_EOL;
$root_dirs = [];
foreach ($entries as $e) {
    $first = explode('/', $e)[0];
    $root_dirs[$first] = true;
}
echo "Root entries: " . implode(', ', array_keys($root_dirs)) . PHP_EOL;
if (count($root_dirs) === 1 && isset($root_dirs['gestor-api'])) {
    echo "OK: pasta raiz unica 'gestor-api'" . PHP_EOL;
} else {
    echo "ERRO: mais de uma pasta raiz ou raiz errada" . PHP_EOL;
    exit(1);
}
echo "Primeiros 10:" . PHP_EOL;
foreach (array_slice($entries, 0, 10) as $e) echo "  " . $e . PHP_EOL;
