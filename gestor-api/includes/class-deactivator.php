<?php
/**
 * Hook de desativacao do plugin.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api;

use Gestor_Api\Util\Logger;

defined('ABSPATH') || exit;

/**
 * Deactivator — nao remove tabelas (dados persistem entre desativa/reativa).
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        try {
            flush_rewrite_rules();
            Logger::info('plugin.deactivated', ['version' => GESTOR_API_VERSION]);
        } catch (\Throwable $e) {
            // Nao bloqueia desativacao se o log falhar.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[gestor-api] deactivate error: ' . $e->getMessage());
        }
    }
}
