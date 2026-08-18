<?php
/**
 * Cria ZIP do plugin no padrao WordPress.
 * Usa PHP + ZipArchive (extensao padrao) pra garantir '/' como separador
 * (Compress-Archive do PowerShell usa '\' no Windows, fora do padrao ZIP RFC).
 */

declare(strict_types=1);

$source = $argv[1] ?? null;
$dest = $argv[2] ?? null;
if ($source === null || $dest === null) {
    fwrite(STDERR, "Uso: php build-zip.php <source_dir> <dest_zip>\n");
    exit(1);
}

if (!is_dir($source)) {
    fwrite(STDERR, "Diretorio nao encontrado: $source\n");
    exit(1);
}

if (file_exists($dest)) {
    unlink($dest);
}

$zip = new ZipArchive();
if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Falha ao criar $dest\n");
    exit(1);
}

$source = rtrim($source, '/\\') . DIRECTORY_SEPARATOR;
$base = basename(rtrim($source, '/\\'));
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count_files = 0;
$count_dirs = 0;
foreach ($it as $f) {
    $abs = $f->getPathname();
    $rel = substr($abs, strlen($source));
    // Normaliza separador pra '/'
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    $zip_path = $base . '/' . $rel;

    if ($f->isDir()) {
        $zip->addEmptyDir($zip_path);
        $count_dirs++;
    } else {
        $zip->addFile($abs, $zip_path);
        $count_files++;
    }
}

$zip->close();

$size = filesize($dest);
$hash = hash_file('sha256', $dest);
echo "ZIP criado: $dest\n";
echo "Tamanho: $size bytes\n";
echo "SHA-256: $hash\n";
echo "Arquivos: $count_files | Diretorios: $count_dirs\n";
