<?php
/**
 * PHPUnit bootstrap — carrega WP test framework + plugin.
 *
 * Setup:
 *   composer install --dev
 *   ./vendor/bin/wp scaffold plugin-tests gestor-api
 *   ./vendor/bin/phpunit
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

/**
 * Carrega o bootstrap padrao do WP test framework.
 *
 * WP_PHPUNIT__DIR vem de phpunit.xml.
 */
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    // Tenta resolver via vendor (wp-phpunit).
    $vendor = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
    if (is_dir($vendor)) {
        $_tests_dir = $vendor;
    }
}
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(STDERR, "WP test framework nao encontrado. Rode: composer install --dev\n");
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Carrega o plugin sendo testado.
 */
function _manually_load_plugin(): void
{
    require dirname(__DIR__) . '/gestor-api.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';
