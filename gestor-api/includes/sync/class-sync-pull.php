<?php
/**
 * Sync Pull — retorna deltas desde cursor.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Sync;

use Gestor_Api\DB\Schema;
use Gestor_Api\Models\Area;
use Gestor_Api\Models\Cliente;
use Gestor_Api\Models\Projeto;
use Gestor_Api\Models\Tarefa;
use wpdb;

defined('ABSPATH') || exit;

/**
 * Implementacao do GET /sync/pull.
 */
final class Sync_Pull
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Executa pull.
     *
     * @return array<string, mixed>
     */
    public function executar(
        string $usuario_id,
        string $dispositivo_id,
        string $since_iso,
        int $limit,
        int $offset
    ): array {
        $now = current_time('mysql', true);

        // Cursor: timestamp ISO 8601. Se vazio, usar "epoca" (1970).
        $since_mysql = $since_iso === ''
            ? '1970-01-01 00:00:00.000'
            : $this->iso_to_mysql($since_iso);

        $mudancas = [];

        // 1. Tarefas.
        $mudancas = array_merge(
            $mudancas,
            $this->pull_tabela(
                'tarefas',
                $usuario_id,
                $since_mysql,
                $limit,
                $offset,
                [Tarefa::class, 'to_dto']
            )
        );

        // 2. Projetos.
        $mudancas = array_merge(
            $mudancas,
            $this->pull_tabela(
                'projetos',
                $usuario_id,
                $since_mysql,
                $limit,
                $offset,
                [Projeto::class, 'to_dto']
            )
        );

        // 3. Clientes.
        $mudancas = array_merge(
            $mudancas,
            $this->pull_tabela(
                'clientes',
                $usuario_id,
                $since_mysql,
                $limit,
                $offset,
                [Cliente::class, 'to_dto']
            )
        );

        // 4. Areas.
        $mudancas = array_merge(
            $mudancas,
            $this->pull_tabela(
                'areas',
                $usuario_id,
                $since_mysql,
                $limit,
                $offset,
                [Area::class, 'to_dto']
            )
        );

        // Ordena por timestamp e limita.
        usort($mudancas, static function ($a, $b) {
            return strcmp($a['atualizado_em'] ?? '', $b['atualizado_em'] ?? '');
        });
        if (count($mudancas) > $limit) {
            $mudancas = array_slice($mudancas, 0, $limit);
            $has_more = true;
        } else {
            $has_more = false;
        }

        $next_cursor = $mudancas === [] ? $since_iso : ($mudancas[count($mudancas) - 1]['atualizado_em'] ?? $since_iso);

        // Atualiza cursor do dispositivo.
        $this->atualizar_cursor($usuario_id, $dispositivo_id, $next_cursor);

        return [
            'mudancas' => $mudancas,
            'next_cursor' => $next_cursor,
            'has_more' => $has_more,
            'server_time' => $this->mysql_to_iso($now),
        ];
    }

    /**
     * @param callable $to_dto
     * @return array<int, array<string, mixed>>
     */
    private function pull_tabela(
        string $tabela,
        string $usuario_id,
        string $since_mysql,
        int $limit,
        int $offset,
        callable $to_dto
    ): array {
        $table = Schema::table($tabela);
        $prefix = $this->wpdb->prefix . GESTOR_API_TABLE_PREFIX;

        // UPSERTs: rows alteradas desde since, nao soft-deleted.
        // DELETEs: soft-deleted desde since (para propagar delecao).
        $sql = "
            SELECT id, versao, atualizado_em, deletado_em
            FROM {$table}
            WHERE usuario_id = %s
              AND (
                (deletado_em IS NULL AND atualizado_em > %s)
                OR (deletado_em IS NOT NULL AND deletado_em > %s)
              )
            ORDER BY GREATEST(COALESCE(atualizado_em, '1970-01-01'), COALESCE(deletado_em, '1970-01-01')) ASC
            LIMIT %d OFFSET %d
        ";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                $sql,
                $usuario_id,
                $since_mysql,
                $since_mysql,
                $limit * 2,
                $offset
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $full = $this->wpdb->get_row(
                $this->wpdb->prepare("SELECT * FROM {$table} WHERE id = %s", $r['id']),
                ARRAY_A
            );
            if ($full === null) {
                continue;
            }
            if ($full['deletado_em'] !== null) {
                $out[] = [
                    'tabela' => $tabela,
                    'operacao' => 'DELETE',
                    'registro_id' => $full['id'],
                    'payload' => ['id' => $full['id']],
                    'versao' => (int) $full['versao'],
                    'atualizado_em' => $this->mysql_to_iso((string) $full['deletado_em']),
                ];
            } else {
                $dto = $to_dto($full);
                $out[] = [
                    'tabela' => $tabela,
                    'operacao' => 'UPSERT',
                    'registro_id' => $full['id'],
                    'payload' => $dto,
                    'versao' => (int) $full['versao'],
                    'atualizado_em' => $this->mysql_to_iso((string) $full['atualizado_em']),
                ];
            }
        }
        return $out;
    }

    private function atualizar_cursor(string $usuario_id, string $dispositivo_id, string $iso): void
    {
        $now = current_time('mysql', true);
        $this->wpdb->replace(
            Schema::table('sync_cursores'),
            [
                'usuario_id' => $usuario_id,
                'dispositivo_id' => substr($dispositivo_id, 0, 64),
                'ultimo_id' => time(),
                'atualizado_em' => $now,
            ],
            ['%s', '%s', '%d', '%s']
        );
    }

    private function iso_to_mysql(string $iso): string
    {
        $ts = strtotime($iso);
        if ($ts === false) {
            return '1970-01-01 00:00:00.000';
        }
        return gmdate('Y-m-d H:i:s.v', $ts);
    }

    private function mysql_to_iso(string $mysql_dt): string
    {
        $ts = strtotime($mysql_dt . ' UTC');
        return $ts === false ? $mysql_dt : gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }
}
