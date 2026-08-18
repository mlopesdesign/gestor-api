<?php
/**
 * Schema do banco MySQL do plugin.
 *
 * Cria/migra as tabelas wp_gestor_* (espelho 1:1 do schema.sql do Gestor desktop).
 * Idempotente: CREATE TABLE IF NOT EXISTS.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\DB;

use wpdb;

defined('ABSPATH') || exit;

/**
 * Cria e gerencia schema do plugin.
 */
final class Schema
{
    /**
     * Cria todas as tabelas (idempotente).
     */
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . GESTOR_API_TABLE_PREFIX;

        $sqls = self::table_definitions($prefix);

        // dbDelta e idempotente: cria se nao existir, atualiza se diferir.
        foreach ($sqls as $sql) {
            dbDelta($sql);
        }

        // Triggers nao sao suportados por dbDelta, cria manualmente.
        self::install_triggers($prefix);

        // Grava versao do schema.
        update_option('gestor_api_db_version', GESTOR_API_VERSION);
    }

    /**
     * Remove todas as tabelas (usado no uninstall).
     */
    public static function uninstall(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix . GESTOR_API_TABLE_PREFIX;

        $tables = [
            'auditoria',
            'sync_conflitos',
            'sync_cursores',
            'subtarefas',
            'tarefas',
            'projetos',
            'clientes',
            'areas',
            'sessoes',
            'usuarios',
        ];

        foreach ($tables as $t) {
            $wpdb->query("DROP TABLE IF EXISTS `{$prefix}{$t}`");
        }

        // Triggers.
        self::drop_triggers($prefix);

        delete_option('gestor_api_db_version');
        delete_option('gestor_api_db_schema');
    }

    /**
     * Retorna nome completo de uma tabela do plugin.
     */
    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . GESTOR_API_TABLE_PREFIX . $name;
    }

    /**
     * Definicoes das tabelas (formato dbDelta).
     *
     * @return array<int, string>
     */
    private static function table_definitions(string $prefix): array
    {
        $c = 'CHAR(26)';
        $c_pk = 'CHAR(26) NOT NULL';
        $c_index = 'CHAR(26)';

        return [
            "CREATE TABLE {$prefix}usuarios (
                id {$c} NOT NULL,
                email VARCHAR(255) NOT NULL,
                senha_hash VARCHAR(255) NOT NULL,
                nome VARCHAR(255) NOT NULL,
                fuso VARCHAR(64) NOT NULL DEFAULT 'America/Sao_Paulo',
                horario_trab_inicio VARCHAR(5) NOT NULL DEFAULT '08:00',
                horario_trab_fim VARCHAR(5) NOT NULL DEFAULT '18:00',
                dias_trabalho_json TEXT NOT NULL,
                tom_cobranca VARCHAR(20) NOT NULL DEFAULT 'PROFISSIONAL',
                ia_habilitada TINYINT(1) NOT NULL DEFAULT 1,
                ia_consentimento_em DATETIME(3) NULL,
                conta_apagada_em DATETIME(3) NULL,
                criado_em DATETIME(3) NOT NULL,
                atualizado_em DATETIME(3) NOT NULL,
                versao INT NOT NULL DEFAULT 1,
                wp_user_id BIGINT(20) UNSIGNED NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY email (email),
                KEY apagada (conta_apagada_em)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}sessoes (
                id {$c} NOT NULL,
                usuario_id {$c_pk},
                token_hash CHAR(64) NOT NULL,
                criada_em DATETIME(3) NOT NULL,
                expira_em DATETIME(3) NOT NULL,
                revogada_em DATETIME(3) NULL,
                dispositivo_id VARCHAR(64) NULL,
                ip_criacao VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY token_hash (token_hash),
                KEY usuario (usuario_id),
                KEY expira (expira_em)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}areas (
                id {$c} NOT NULL,
                usuario_id {$c_pk},
                nome VARCHAR(120) NOT NULL,
                cor CHAR(7) NOT NULL DEFAULT '#888888',
                ordem INT NOT NULL DEFAULT 0,
                criado_em DATETIME(3) NOT NULL,
                atualizado_em DATETIME(3) NOT NULL,
                versao INT NOT NULL DEFAULT 1,
                deletado_em DATETIME(3) NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uq_usuario_nome (usuario_id, nome),
                KEY usuario (usuario_id)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}clientes (
                id {$c} NOT NULL,
                usuario_id {$c_pk},
                nome VARCHAR(255) NOT NULL,
                organizacao VARCHAR(255) NULL,
                contatos_json LONGTEXT NOT NULL,
                observacoes LONGTEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
                criado_em DATETIME(3) NOT NULL,
                atualizado_em DATETIME(3) NOT NULL,
                versao INT NOT NULL DEFAULT 1,
                deletado_em DATETIME(3) NULL,
                PRIMARY KEY  (id),
                KEY usuario (usuario_id),
                KEY status (usuario_id, status)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}projetos (
                id {$c} NOT NULL,
                usuario_id {$c_pk},
                titulo VARCHAR(255) NOT NULL,
                descricao LONGTEXT NULL,
                cliente_id {$c_index} NULL,
                area_id {$c_index} NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'PLANEJADO',
                prioridade VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
                inicio_em DATETIME(3) NULL,
                fim_em DATETIME(3) NULL,
                progresso_calc DECIMAL(3,2) NOT NULL DEFAULT 0.00,
                participantes_json LONGTEXT NOT NULL,
                criado_em DATETIME(3) NOT NULL,
                atualizado_em DATETIME(3) NOT NULL,
                versao INT NOT NULL DEFAULT 1,
                deletado_em DATETIME(3) NULL,
                PRIMARY KEY  (id),
                KEY usuario (usuario_id),
                KEY status (usuario_id, status),
                KEY cliente (cliente_id),
                KEY area (area_id)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}tarefas (
                id {$c} NOT NULL,
                usuario_id {$c_pk},
                titulo VARCHAR(200) NOT NULL,
                descricao LONGTEXT NULL,
                area_id {$c_index} NULL,
                projeto_id {$c_index} NULL,
                cliente_id {$c_index} NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'CAIXA_ENTRADA',
                prioridade VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
                nivel_cobranca VARCHAR(20) NOT NULL DEFAULT 'PERSISTENTE',
                inicio_em DATETIME(3) NULL,
                vencimento_em DATETIME(3) NULL,
                duracao_estimada_min INT NULL,
                duracao_realizada_min INT NOT NULL DEFAULT 0,
                recorrencia_json LONGTEXT NULL,
                recorrencia_tipo VARCHAR(32) NULL,
                recorrencia_data_base DATETIME(3) NULL,
                etiquetas_json LONGTEXT NOT NULL,
                responsavel VARCHAR(255) NULL,
                origem VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
                concluida_em DATETIME(3) NULL,
                entregue_em DATETIME(3) NULL,
                confirmada_em DATETIME(3) NULL,
                motivo_cancelamento LONGTEXT NULL,
                motivo_adiamento LONGTEXT NULL,
                cancelada_em DATETIME(3) NULL,
                cancelada_motivo LONGTEXT NULL,
                adiada_ate DATETIME(3) NULL,
                adiada_motivo LONGTEXT NULL,
                criado_em DATETIME(3) NOT NULL,
                atualizado_em DATETIME(3) NOT NULL,
                versao INT NOT NULL DEFAULT 1,
                deletado_em DATETIME(3) NULL,
                PRIMARY KEY  (id),
                KEY usuario (usuario_id),
                KEY status (usuario_id, status),
                KEY projeto (projeto_id),
                KEY area (area_id),
                KEY cliente (cliente_id),
                KEY vencimento (vencimento_em),
                KEY prioridade (usuario_id, prioridade),
                KEY atualizado (atualizado_em),
                KEY deletado (deletado_em)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}subtarefas (
                id {$c} NOT NULL,
                tarefa_id {$c_pk},
                usuario_id {$c_pk},
                titulo VARCHAR(255) NOT NULL,
                ordem INT NOT NULL,
                concluida_em DATETIME(3) NULL,
                criado_em DATETIME(3) NOT NULL,
                atualizado_em DATETIME(3) NOT NULL,
                versao INT NOT NULL DEFAULT 1,
                deletado_em DATETIME(3) NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY uq_tarefa_ordem (tarefa_id, ordem),
                KEY tarefa (tarefa_id)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}sync_cursores (
                usuario_id {$c_pk},
                dispositivo_id VARCHAR(64) NOT NULL,
                ultimo_id BIGINT NOT NULL DEFAULT 0,
                atualizado_em DATETIME(3) NOT NULL,
                PRIMARY KEY  (usuario_id, dispositivo_id)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}sync_conflitos (
                id BIGINT NOT NULL AUTO_INCREMENT,
                usuario_id {$c_pk},
                tabela VARCHAR(32) NOT NULL,
                registro_id {$c_pk},
                versao_servidor INT NOT NULL,
                versao_cliente_a INT NOT NULL,
                dispositivo_a_id VARCHAR(64) NOT NULL,
                payload_servidor LONGTEXT NOT NULL,
                payload_cliente_a LONGTEXT NOT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT 'PENDENTE',
                escolhido_por {$c_index} NULL,
                escolhido_em DATETIME(3) NULL,
                diff_json LONGTEXT NULL,
                criado_em DATETIME(3) NOT NULL,
                PRIMARY KEY  (id),
                KEY usuario_estado (usuario_id, estado, criado_em)
            ) " . self::engine(),

            "CREATE TABLE {$prefix}auditoria (
                id BIGINT NOT NULL AUTO_INCREMENT,
                usuario_id {$c_pk},
                entidade VARCHAR(32) NOT NULL,
                entidade_id {$c_pk},
                acao VARCHAR(20) NOT NULL,
                diff_json LONGTEXT NULL,
                dispositivo_id VARCHAR(64) NULL,
                em DATETIME(3) NOT NULL,
                PRIMARY KEY  (id),
                KEY usuario_em (usuario_id, em),
                KEY entidade (entidade, entidade_id)
            ) " . self::engine(),
        ];
    }

    private static function engine(): string
    {
        return 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    /**
     * Cria triggers de invariante (auditoria append-only).
     */
    private static function install_triggers(string $prefix): void
    {
        global $wpdb;

        // Trigger de BEFORE DELETE na auditoria: SIGNAL SQLSTATE 45000.
        $trigger_delete = "DROP TRIGGER IF EXISTS `{$prefix}trg_auditoria_no_delete`;
            CREATE TRIGGER `{$prefix}trg_auditoria_no_delete`
            BEFORE DELETE ON `{$prefix}auditoria`
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'auditoria: append-only';
            END";

        $trigger_update = "DROP TRIGGER IF EXISTS `{$prefix}trg_auditoria_no_update`;
            CREATE TRIGGER `{$prefix}trg_auditoria_no_update`
            BEFORE UPDATE ON `{$prefix}auditoria`
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'auditoria: append-only';
            END";

        // @phpstan-ignore-next-line — $wpdb->query aceita multi-statement.
        $wpdb->query($trigger_delete);
        // @phpstan-ignore-next-line
        $wpdb->query($trigger_update);
    }

    /**
     * Remove triggers (usado no uninstall).
     */
    private static function drop_triggers(string $prefix): void
    {
        global $wpdb;
        $wpdb->query("DROP TRIGGER IF EXISTS `{$prefix}trg_auditoria_no_delete`");
        $wpdb->query("DROP TRIGGER IF EXISTS `{$prefix}trg_auditoria_no_update`");
    }
}
