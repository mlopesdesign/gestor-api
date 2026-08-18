<?php
/**
 * Sync Push — aplica batch de mutacoes do cliente.
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
use Gestor_Api\Util\Gestor_Api_Validation_Exception;
use Gestor_Api\Util\Logger;
use wpdb;

defined('ABSPATH') || exit;

/**
 * Processa batch de mutacoes do cliente.
 */
final class Sync_Push
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Aplica batch.
     *
     * @param array<int, array<string, mixed>> $mutacoes
     * @return array<string, mixed>
     */
    public function executar(string $usuario_id, string $dispositivo_id, array $mutacoes): array
    {
        $aplicadas = 0;
        $conflitos = [];
        $now = current_time('mysql', true);

        foreach ($mutacoes as $mut) {
            if (!is_array($mut)) {
                continue;
            }
            $tabela = (string) ($mut['tabela'] ?? '');
            $operacao = (string) ($mut['operacao'] ?? '');
            $registro_id = (string) ($mut['registro_id'] ?? '');
            $payload = $mut['payload'] ?? [];
            $versao_base = isset($mut['versao_base']) ? (int) $mut['versao_base'] : null;

            if (!in_array($tabela, ['tarefas', 'projetos', 'clientes', 'areas'], true)) {
                continue;
            }
            if (!in_array($operacao, ['UPSERT', 'DELETE'], true)) {
                continue;
            }
            if ($registro_id === '') {
                continue;
            }
            if ($operacao === 'UPSERT' && !is_array($payload)) {
                continue;
            }

            try {
                if ($operacao === 'UPSERT') {
                    $payload['id'] = $registro_id;
                    if ($versao_base !== null) {
                        $payload['versao_base'] = $versao_base;
                    }

                    $existente = $this->current_row($tabela, $registro_id, $usuario_id);

                    if ($existente !== null && $versao_base !== null && (int) $existente['versao'] !== $versao_base) {
                        // Conflito: versao base nao bate.
                        $this->registrar_conflito(
                            $usuario_id,
                            $tabela,
                            $registro_id,
                            (int) $existente['versao'],
                            $versao_base,
                            $dispositivo_id,
                            $existente,
                            $payload
                        );
                        $dto = $this->to_dto($tabela, $existente);
                        $conflitos[] = [
                            'tabela' => $tabela,
                            'registro_id' => $registro_id,
                            'versao_servidor' => (int) $existente['versao'],
                            'versao_cliente' => $versao_base,
                            'payload_servidor' => $dto,
                            'estado' => 'PENDENTE',
                        ];
                        continue;
                    }

                    $this->upsert($tabela, $usuario_id, $payload);
                    Logger::audit(
                        $usuario_id,
                        $tabela,
                        $registro_id,
                        Logger::ACAO_SYNC_PUSH,
                        ['origem' => 'sync', 'dispositivo_id' => $dispositivo_id, 'operacao' => 'UPSERT'],
                        $dispositivo_id
                    );
                    $aplicadas++;
                } else {
                    // DELETE (soft-delete).
                    $ok = $this->soft_delete($tabela, $registro_id, $usuario_id);
                    if ($ok) {
                        Logger::audit(
                            $usuario_id,
                            $tabela,
                            $registro_id,
                            Logger::ACAO_SYNC_PUSH,
                            ['origem' => 'sync', 'dispositivo_id' => $dispositivo_id, 'operacao' => 'DELETE'],
                            $dispositivo_id
                        );
                        $aplicadas++;
                    }
                }
            } catch (Gestor_Api_Validation_Exception $e) {
                $conflitos[] = [
                    'tabela' => $tabela,
                    'registro_id' => $registro_id,
                    'estado' => 'ERRO',
                    'mensagem' => $e->getMessage(),
                ];
            }
        }

        return [
            'aplicadas' => $aplicadas,
            'conflitos' => $conflitos,
            'server_time' => gmdate('Y-m-d\TH:i:s.v\Z', time()),
        ];
    }

    /**
     * Lista conflitos pendentes do usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listar_conflitos_pendentes(string $usuario_id): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('sync_conflitos')
                . " WHERE usuario_id = %s AND estado = 'PENDENTE'"
                . " ORDER BY criado_em DESC LIMIT 100",
                $usuario_id
            ),
            ARRAY_A
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'tabela' => $r['tabela'],
                'registro_id' => $r['registro_id'],
                'versao_servidor' => (int) $r['versao_servidor'],
                'versao_cliente_a' => (int) $r['versao_cliente_a'],
                'dispositivo_a_id' => $r['dispositivo_a_id'],
                'payload_servidor' => json_decode($r['payload_servidor'], true),
                'payload_cliente_a' => json_decode($r['payload_cliente_a'], true),
                'estado' => $r['estado'],
                'criado_em' => $this->to_iso((string) $r['criado_em']),
            ];
        }
        return $out;
    }

    /**
     * Aplica upsert delegando ao model.
     *
     * @param array<string, mixed> $payload
     */
    private function upsert(string $tabela, string $usuario_id, array $payload): void
    {
        switch ($tabela) {
            case 'tarefas':
                (new Tarefa())->upsert($usuario_id, $payload);
                break;
            case 'projetos':
                (new Projeto())->upsert($usuario_id, $payload);
                break;
            case 'clientes':
                (new Cliente())->upsert($usuario_id, $payload);
                break;
            case 'areas':
                (new Area())->upsert($usuario_id, $payload);
                break;
        }
    }

    private function soft_delete(string $tabela, string $registro_id, string $usuario_id): bool
    {
        $now = current_time('mysql', true);
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE " . Schema::table($tabela)
                . " SET deletado_em = %s, versao = versao + 1, atualizado_em = %s"
                . " WHERE id = %s AND usuario_id = %s AND deletado_em IS NULL",
                $now,
                $now,
                $registro_id,
                $usuario_id
            )
        );
        return $result !== false && $result > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function current_row(string $tabela, string $id, string $usuario_id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table($tabela) . " WHERE id = %s AND usuario_id = %s",
                $id,
                $usuario_id
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function to_dto(string $tabela, array $row): array
    {
        switch ($tabela) {
            case 'tarefas':
                return Tarefa::to_dto($row);
            case 'projetos':
                return Projeto::to_dto($row);
            case 'clientes':
                return Cliente::to_dto($row);
            case 'areas':
                return Area::to_dto($row);
        }
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload
     */
    private function registrar_conflito(
        string $usuario_id,
        string $tabela,
        string $registro_id,
        int $versao_servidor,
        int $versao_cliente,
        string $dispositivo_id,
        array $row,
        array $payload
    ): void {
        $now = current_time('mysql', true);
        $this->wpdb->insert(
            Schema::table('sync_conflitos'),
            [
                'usuario_id' => $usuario_id,
                'tabela' => $tabela,
                'registro_id' => $registro_id,
                'versao_servidor' => $versao_servidor,
                'versao_cliente_a' => $versao_cliente,
                'dispositivo_a_id' => substr($dispositivo_id, 0, 64),
                'payload_servidor' => wp_json_encode($this->to_dto($tabela, $row)),
                'payload_cliente_a' => wp_json_encode($payload),
                'estado' => 'PENDENTE',
                'criado_em' => $now,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    private function to_iso(string $mysql_dt): string
    {
        $ts = strtotime($mysql_dt . ' UTC');
        return $ts === false ? $mysql_dt : gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }
}
