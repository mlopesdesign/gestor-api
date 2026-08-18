<?php
/**
 * Plugin Name:       Gestor API
 * Plugin URI:        https://tools.mlopesdesign.com.br
 * Description:       API REST central do Gestor Inteligente de Demandas. Consumida pelo app Android.
 * Version:           0.1.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            ML Lopes Design
 * Author URI:        https://mlopesdesign.com.br
 * License:           Proprietary
 * Text Domain:       gestor-api
 * Domain Path:       /languages
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

// Constantes de identidade. NAO MEXER apos o primeiro release.
define('GESTOR_API_VERSION', '0.1.1');
define('GESTOR_API_PLUGIN_FILE', __FILE__);
define('GESTOR_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GESTOR_API_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GESTOR_API_NAMESPACE', 'gestor/v1');
define('GESTOR_API_PREFIX', 'gestor_api_');
define('GESTOR_API_TABLE_PREFIX', 'gestor_');
define('GESTOR_API_TOKEN_TTL_DAYS', 30);
define('GESTOR_API_LOGIN_RATE_LIMIT', 5);
define('GESTOR_API_LOGIN_RATE_WINDOW_MIN', 15);

// Encerrar execucao se acessado diretamente.
if (!defined('ABSPATH')) {
    exit;
}

// PSR-4 autoload manual (sem composer em producao).
spl_autoload_register(static function (string $class): void {
    $prefix = 'Gestor_Api\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $relative = str_replace('_', '-', $relative);
    $parts = explode('\\', $relative);
    $class_file = 'class-' . strtolower(array_pop($parts)) . '.php';
    $subdir = empty($parts) ? '' : strtolower(implode('/', $parts)) . '/';
    $path = GESTOR_API_PLUGIN_DIR . 'includes/' . $subdir . $class_file;
    if (is_file($path)) {
        require_once $path;
    }
});

// Hooks de ciclo de vida.
register_activation_hook(__FILE__, ['Gestor_Api\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Gestor_Api\\Deactivator', 'deactivate']);

// Bootstrap principal.
add_action('plugins_loaded', static function (): void {
    // Carrega textdomain pra i18n.
    load_plugin_textdomain(
        'gestor-api',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Inicializa o plugin.
    \Gestor_Api\Gestor_Api::instance()->init();
});

// Inicializa rotas REST quando WP carregar a API.
add_action('rest_api_init', static function (): void {
    \Gestor_Api\Gestor_Api::instance()->register_rest_routes();
});
