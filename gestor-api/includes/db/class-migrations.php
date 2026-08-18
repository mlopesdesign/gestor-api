<?php
/**
 * Controle de versao do schema.
 *
 * Mantem compatibilidade com o padrao do PADRÃO-ML-LOPES-DESIGN.md §3.5.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\DB;

defined('ABSPATH') || exit;

/**
 * Versao + delta de migrations.
 */
final class Migrations
{
    public const CURRENT_VERSION = '0.1.3';

    /**
     * Retorna versao atual do schema no banco.
     */
    public static function current_db_version(): string
    {
        return (string) get_option('gestor_api_db_version', '0.0.0');
    }

    /**
     * Verifica se precisa migrar.
     */
    public static function needs_migration(): bool
    {
        return version_compare(self::current_db_version(), self::CURRENT_VERSION, '<');
    }

    /**
     * Aplica migrations necessarias.
     */
    public static function run(): void
    {
        if (!self::needs_migration()) {
            return;
        }
        // MVP: install() e idempotente. Em versoes futuras, deltas aqui.
        Schema::install();
    }
}
