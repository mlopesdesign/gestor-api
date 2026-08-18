<?php
/**
 * Logger estruturado do plugin.
 *
 * Escreve em error_log do PHP e em wp_gestor_auditoria (para acoes sensiveis).
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Util;

use Gestor_Api\DB\Schema;
use WP_Error;
use wpdb;

defined('ABSPATH') || exit;

/**
 * Logger com contexto estruturado.
 */
final class Logger
{
    public const ACAO_CREATE = 'CREATE';
    public const ACAO_UPDATE = 'UPDATE';
    public const ACAO_DELETE = 'DELETE';
    public const ACAO_LOGIN = 'LOGIN';
    public const ACAO_LOGOUT = 'LOGOUT';
    public const ACAO_SYNC_PULL = 'SYNC_PULL';
    public const ACAO_SYNC_PUSH = 'SYNC_PUSH';

    /**
     * Log de erro generico (error_log).
     *
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Log de warning.
     *
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /**
     * Log de info.
     *
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Registra acao de auditoria na tabela wp_gestor_auditoria.
     *
     * @param array<string, mixed>|null $diff
     */
    public static function audit(
        string $usuario_id,
        string $entidade,
        string $entidade_id,
        string $acao,
        ?array $diff = null,
        ?string $dispositivo_id = null
    ): void {
        global $wpdb;

        $table = Schema::table('auditoria');
        $now = current_time('mysql', true);

        $wpdb->insert(
            $table,
            [
                'usuario_id' => $usuario_id,
                'entidade' => substr($entidade, 0, 32),
                'entidade_id' => $entidade_id,
                'acao' => $acao,
                'diff_json' => $diff === null ? null : wp_json_encode($diff, JSON_UNESCAPED_UNICODE),
                'dispositivo_id' => $dispositivo_id,
                'em' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Escreve log estruturado.
     *
     * @param array<string, mixed> $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        $ctx = $context;
        if (isset($ctx['senha'])) {
            $ctx['senha'] = '***REDACTED***';
        }
        $line = sprintf(
            '[gestor-api] [%s] %s %s',
            $level,
            $message,
            $ctx === [] ? '' : wp_json_encode($ctx, JSON_UNESCAPED_UNICODE)
        );
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($line);
    }
}
