<?php
/**
 * Sync Conflict Resolver — aplica escolha do usuario.
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
 * Resolve conflito de sync com escolha MINE / THEIRS / MERGE.
 */
final class Sync_Conflict_Resolver
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Aplica escolha.
     *
     * @param array<string, mixed>|null $payload_merged
     * @return array<string, mixed>
     */
    public function resolver(
        int $conflito_id,
        string $usuario_id,
        string $escolha,
        ?array $payload_merged = null
    ): array {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('sync_conflitos') . " WHERE id = %d",
                $conflito_id
            ),
            ARRAY_A
        );
        if ($row === null) {
            throw new Gestor_Api_Validation_Exception(
                'conflito_nao_encontrado',
                'Conflito nao encontrado',
                404
            );
        }
        if ((string) $row['usuario_id'] !== $usuario_id) {
            throw new Gestor_Api_Validation_Exception(
                'sem_permissao',
                'Conflito nao pertence ao usuario',
                403
            );
        }
        if ($row['estado'] !== 'PENDENTE') {
            throw new Gestor_Api_Validation_Exception(
                'conflito_ja_resolvido',
                'Conflito ja foi resolvido',
                409
            );
        }

        $escolha = strtoupper($escolha);
        if (!in_array($escolha, ['MINE', 'THEIRS', 'MERGE'], true)) {
            throw new Gestor_Api_Validation_Exception(
                'escolha_invalida',
                'escolha deve ser MINE, THEIRS ou MERGE'
            );
        }

        $tabela = (string) $row['tabela'];
        $registro_id = (string) $row['registro_id'];
        $payload_servidor = json_decode((string) $row['payload_servidor'], true);
        $payload_cliente = json_decode((string) $row['payload_cliente_a'], true);

        $payload_final = match ($escolha) {
            'MINE' => is_array($payload_cliente) ? $payload_cliente : [],
            'THEIRS' => is_array($payload_servidor) ? $payload_servidor : [],
            'MERGE' => $payload_merged ?? $this::merge_default(
                is_array($payload_servidor) ? $payload_servidor : [],
                is_array($payload_cliente) ? $payload_cliente : []
            ),
        };

        if (!is_array($payload_final) || $payload_final === []) {
            throw new Gestor_Api_Validation_Exception(
                'payload_invalido',
                'Payload mergeado invalido',
                400
            );
        }

        // Aplica payload final (sempre com versao_base = versao_servidor para forcar update).
        $payload_final['id'] = $registro_id;
        $payload_final['versao_base'] = (int) $row['versao_servidor'];

        $this->apply_payload($tabela, $usuario_id, $payload_final);

        $estado = match ($escolha) {
            'MINE' => 'RESOLVIDO_MINE',
            'THEIRS' => 'RESOLVIDO_THEIRS',
            'MERGE' => 'RESOLVIDO_MERGE',
        };

        $now = current_time('mysql', true);
        $this->wpdb->update(
            Schema::table('sync_conflitos'),
            [
                'estado' => $estado,
                'escolhido_por' => $usuario_id,
                'escolhido_em' => $now,
            ],
            ['id' => $conflito_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        Logger::audit(
            $usuario_id,
            $tabela,
            $registro_id,
            Logger::ACAO_SYNC_PUSH,
            ['conflito_id' => $conflito_id, 'escolha' => $escolha],
            (string) ($row['dispositivo_a_id'] ?? '')
        );

        return [
            'conflito_id' => $conflito_id,
            'estado' => $estado,
            'aplicado' => $payload_final,
        ];
    }

    /**
     * @param array<string, mixed> $servidor
     * @param array<string, mixed> $cliente
     * @return array<string, mixed>
     */
    public static function merge_default(array $servidor, array $cliente): array
    {
        // Merge simples: cliente sobrescreve servidor para campos presentes.
        return array_merge($servidor, $cliente);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply_payload(string $tabela, string $usuario_id, array $payload): void
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
}
