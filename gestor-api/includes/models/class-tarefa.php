<?php
/**
 * Model de Tarefa.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Models;

use Gestor_Api\DB\Schema;
use Gestor_Api\Util\Gestor_Api_Validation_Exception;
use Gestor_Api\Util\Ulid;
use Gestor_Api\Util\Validator;
use wpdb;

defined('ABSPATH') || exit;

/**
 * CRUD de Tarefas + helpers (concluir, reabrir).
 */
final class Tarefa
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Cria ou atualiza tarefa.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsert(string $usuario_id, array $data): array
    {
        $id = Validator::ulid($data['id'] ?? '', 'id', true);
        $titulo = Validator::string($data['titulo'] ?? '', 'titulo', 200);
        if ($titulo === '') {
            throw new Gestor_Api_Validation_Exception('titulo_obrigatorio', 'Titulo obrigatorio');
        }
        $descricao = isset($data['descricao']) ? Validator::rich_text((string) $data['descricao'], 'descricao') : '';
        $area_id = Validator::ulid_optional($data['area_id'] ?? null, 'area_id');
        $projeto_id = Validator::ulid_optional($data['projeto_id'] ?? null, 'projeto_id');
        $cliente_id = Validator::ulid_optional($data['cliente_id'] ?? null, 'cliente_id');
        $status = Validator::enum(
            $data['status'] ?? 'CAIXA_ENTRADA',
            'status',
            Validator::STATUS_TAREFA,
            'CAIXA_ENTRADA'
        );
        $prioridade = Validator::enum(
            $data['prioridade'] ?? 'NORMAL',
            'prioridade',
            Validator::PRIORIDADE,
            'NORMAL'
        );
        $nivel_cobranca = Validator::enum(
            $data['nivel_cobranca'] ?? 'PERSISTENTE',
            'nivel_cobranca',
            Validator::NIVEL_COBRANCA,
            'PERSISTENTE'
        );
        $inicio_em = $this->iso_to_mysql(Validator::iso8601($data['inicio_em'] ?? null, 'inicio_em'));
        $vencimento_em = $this->iso_to_mysql(Validator::iso8601($data['vencimento_em'] ?? null, 'vencimento_em'));
        $duracao_estimada = Validator::int_optional($data['duracao_estimada_min'] ?? null, 'duracao_estimada_min', 1);
        $duracao_realizada = Validator::int_optional($data['duracao_realizada_min'] ?? 0, 'duracao_realizada_min', 0) ?? 0;
        $recorrencia = Validator::json($data['recorrencia'] ?? null, 'recorrencia');
        $etiquetas = Validator::json($data['etiquetas'] ?? [], 'etiquetas', true) ?? [];
        $responsavel = isset($data['responsavel']) ? Validator::string((string) $data['responsavel'], 'responsavel', 255) : '';
        $origem = Validator::enum(
            $data['origem'] ?? 'MANUAL',
            'origem',
            Validator::ORIGEM,
            'MANUAL'
        );
        $versao_base = isset($data['versao_base']) ? (int) $data['versao_base'] : null;
        $now = current_time('mysql', true);

        if ($inicio_em !== null && $vencimento_em !== null && strtotime($vencimento_em) < strtotime($inicio_em)) {
            throw new Gestor_Api_Validation_Exception('data_invalida', 'vencimento_em deve ser >= inicio_em');
        }
        if ($recorrencia !== null && $projeto_id !== null) {
            throw new Gestor_Api_Validation_Exception('regra_negocio', 'Tarefa recorrente nao pode ter projeto');
        }

        $existing = $this->find_by_id($id, $usuario_id);

        if ($existing === null) {
            $this->wpdb->insert(
                Schema::table('tarefas'),
                [
                    'id' => $id,
                    'usuario_id' => $usuario_id,
                    'titulo' => $titulo,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'area_id' => $area_id,
                    'projeto_id' => $projeto_id,
                    'cliente_id' => $cliente_id,
                    'status' => $status,
                    'prioridade' => $prioridade,
                    'nivel_cobranca' => $nivel_cobranca,
                    'inicio_em' => $inicio_em,
                    'vencimento_em' => $vencimento_em,
                    'duracao_estimada_min' => $duracao_estimada,
                    'duracao_realizada_min' => $duracao_realizada,
                    'recorrencia_json' => $recorrencia !== null ? wp_json_encode($recorrencia) : null,
                    'recorrencia_tipo' => isset($data['recorrencia_tipo']) ? (string) $data['recorrencia_tipo'] : null,
                    'recorrencia_data_base' => $this->iso_to_mysql(Validator::iso8601($data['recorrencia_data_base'] ?? null, 'recorrencia_data_base')),
                    'etiquetas_json' => wp_json_encode($etiquetas),
                    'responsavel' => $responsavel !== '' ? $responsavel : null,
                    'origem' => $origem,
                    'criado_em' => $now,
                    'atualizado_em' => $now,
                    'versao' => 1,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
        } else {
            if ($versao_base !== null && (int) $existing['versao'] !== $versao_base) {
                throw new Gestor_Api_Validation_Exception(
                    'conflito_versao',
                    sprintf(
                        'Versao base %d nao bate com versao atual %d',
                        $versao_base,
                        (int) $existing['versao']
                    ),
                    409
                );
            }
            $nova_versao = (int) $existing['versao'] + 1;
            $this->wpdb->update(
                Schema::table('tarefas'),
                [
                    'titulo' => $titulo,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'area_id' => $area_id,
                    'projeto_id' => $projeto_id,
                    'cliente_id' => $cliente_id,
                    'status' => $status,
                    'prioridade' => $prioridade,
                    'nivel_cobranca' => $nivel_cobranca,
                    'inicio_em' => $inicio_em,
                    'vencimento_em' => $vencimento_em,
                    'duracao_estimada_min' => $duracao_estimada,
                    'duracao_realizada_min' => $duracao_realizada,
                    'recorrencia_json' => $recorrencia !== null ? wp_json_encode($recorrencia) : null,
                    'recorrencia_tipo' => isset($data['recorrencia_tipo']) ? (string) $data['recorrencia_tipo'] : null,
                    'recorrencia_data_base' => $this->iso_to_mysql(Validator::iso8601($data['recorrencia_data_base'] ?? null, 'recorrencia_data_base')),
                    'etiquetas_json' => wp_json_encode($etiquetas),
                    'responsavel' => $responsavel !== '' ? $responsavel : null,
                    'origem' => $origem,
                    'atualizado_em' => $now,
                    'versao' => $nova_versao,
                ],
                ['id' => $id, 'usuario_id' => $usuario_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
                ['%s', '%s']
            );
        }

        $row = $this->find_by_id($id, $usuario_id);
        if ($row === null) {
            throw new Gestor_Api_Validation_Exception('erro_persistencia', 'Falha ao persistir tarefa', 500);
        }
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_id(string $id, string $usuario_id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('tarefas')
                . " WHERE id = %s AND usuario_id = %s AND deletado_em IS NULL",
                $id,
                $usuario_id
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_for_user(
        string $usuario_id,
        bool $include_deleted = false,
        ?string $status = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $sql = "SELECT * FROM " . Schema::table('tarefas') . " WHERE usuario_id = %s";
        $params = [$usuario_id];
        if (!$include_deleted) {
            $sql .= " AND deletado_em IS NULL";
        }
        if ($status !== null) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }
        $sql .= " ORDER BY atualizado_em DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Tarefas com vencimento = hoje.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_hoje(string $usuario_id): array
    {
        $hoje = gmdate('Y-m-d');
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('tarefas')
                . " WHERE usuario_id = %s AND deletado_em IS NULL"
                . " AND DATE(vencimento_em) = %s"
                . " AND status NOT IN ('CONCLUIDA','CANCELADA','ARQUIVADA')"
                . " ORDER BY FIELD(prioridade,'CRITICA','URGENTE','ALTA','NORMAL','BAIXA'), vencimento_em ASC",
                $usuario_id,
                $hoje
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Tarefas atrasadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_atrasadas(string $usuario_id): array
    {
        $now = current_time('mysql', true);
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('tarefas')
                . " WHERE usuario_id = %s AND deletado_em IS NULL"
                . " AND vencimento_em < %s"
                . " AND status NOT IN ('CONCLUIDA','CANCELADA','ARQUIVADA')"
                . " ORDER BY vencimento_em ASC",
                $usuario_id,
                $now
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Tarefas de um projeto.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_por_projeto(string $usuario_id, string $projeto_id): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('tarefas')
                . " WHERE usuario_id = %s AND projeto_id = %s AND deletado_em IS NULL"
                . " ORDER BY atualizado_em DESC",
                $usuario_id,
                $projeto_id
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Tarefas de um cliente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_por_cliente(string $usuario_id, string $cliente_id): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('tarefas')
                . " WHERE usuario_id = %s AND cliente_id = %s AND deletado_em IS NULL"
                . " ORDER BY atualizado_em DESC",
                $usuario_id,
                $cliente_id
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Concluir tarefa.
     */
    public function concluir(string $id, string $usuario_id, bool $confirmada = true): array
    {
        $now = current_time('mysql', true);
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE " . Schema::table('tarefas')
                . " SET status = 'CONCLUIDA', concluida_em = %s,"
                . " confirmada_em = " . ($confirmada ? "%s" : "confirmada_em") . ","
                . " versao = versao + 1, atualizado_em = %s"
                . " WHERE id = %s AND usuario_id = %s AND deletado_em IS NULL",
                $now,
                $confirmada ? $now : null,
                $now,
                $id,
                $usuario_id
            )
        );
        $row = $this->find_by_id($id, $usuario_id);
        if ($row === null) {
            throw new Gestor_Api_Validation_Exception('tarefa_nao_encontrada', 'Tarefa nao encontrada', 404);
        }
        return $row;
    }

    /**
     * Reabrir tarefa (status volta pra EM_ANDAMENTO).
     */
    public function reabrir(string $id, string $usuario_id): array
    {
        $now = current_time('mysql', true);
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE " . Schema::table('tarefas')
                . " SET status = 'EM_ANDAMENTO', concluida_em = NULL, versao = versao + 1, atualizado_em = %s"
                . " WHERE id = %s AND usuario_id = %s AND deletado_em IS NULL",
                $now,
                $id,
                $usuario_id
            )
        );
        $row = $this->find_by_id($id, $usuario_id);
        if ($row === null) {
            throw new Gestor_Api_Validation_Exception('tarefa_nao_encontrada', 'Tarefa nao encontrada', 404);
        }
        return $row;
    }

    public function soft_delete(string $id, string $usuario_id): bool
    {
        $now = current_time('mysql', true);
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE " . Schema::table('tarefas')
                . " SET deletado_em = %s, versao = versao + 1, atualizado_em = %s"
                . " WHERE id = %s AND usuario_id = %s AND deletado_em IS NULL",
                $now,
                $now,
                $id,
                $usuario_id
            )
        );
        return $result !== false && $result > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function to_dto(array $row): array
    {
        return [
            'id' => $row['id'],
            'titulo' => $row['titulo'],
            'descricao' => $row['descricao'],
            'area_id' => $row['area_id'],
            'projeto_id' => $row['projeto_id'],
            'cliente_id' => $row['cliente_id'],
            'status' => $row['status'],
            'prioridade' => $row['prioridade'],
            'nivel_cobranca' => $row['nivel_cobranca'],
            'inicio_em' => $row['inicio_em'] ? self::iso8601((string) $row['inicio_em']) : null,
            'vencimento_em' => $row['vencimento_em'] ? self::iso8601((string) $row['vencimento_em']) : null,
            'duracao_estimada_min' => $row['duracao_estimada_min'] !== null ? (int) $row['duracao_estimada_min'] : null,
            'duracao_realizada_min' => (int) $row['duracao_realizada_min'],
            'recorrencia' => $row['recorrencia_json'] ? json_decode($row['recorrencia_json'], true) : null,
            'recorrencia_tipo' => $row['recorrencia_tipo'],
            'recorrencia_data_base' => $row['recorrencia_data_base'] ? self::iso8601((string) $row['recorrencia_data_base']) : null,
            'etiquetas' => json_decode($row['etiquetas_json'], true) ?: [],
            'responsavel' => $row['responsavel'],
            'origem' => $row['origem'],
            'concluida_em' => $row['concluida_em'] ? self::iso8601((string) $row['concluida_em']) : null,
            'entregue_em' => $row['entregue_em'] ? self::iso8601((string) $row['entregue_em']) : null,
            'confirmada_em' => $row['confirmada_em'] ? self::iso8601((string) $row['confirmada_em']) : null,
            'criado_em' => self::iso8601((string) $row['criado_em']),
            'atualizado_em' => self::iso8601((string) $row['atualizado_em']),
            'versao' => (int) $row['versao'],
        ];
    }

    private function iso_to_mysql(?string $iso): ?string
    {
        if ($iso === null) {
            return null;
        }
        $ts = strtotime($iso);
        return $ts === false ? null : gmdate('Y-m-d H:i:s.v', $ts);
    }

    private static function iso8601(string $mysql_datetime): string
    {
        $ts = strtotime($mysql_datetime . ' UTC');
        return $ts === false ? $mysql_datetime : gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }
}
