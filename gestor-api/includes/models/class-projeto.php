<?php
/**
 * Model de Projeto.
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
 * CRUD de Projetos.
 */
final class Projeto
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsert(string $usuario_id, array $data): array
    {
        $id = Validator::ulid($data['id'] ?? '', 'id', true);
        $titulo = Validator::string($data['titulo'] ?? '', 'titulo', 255);
        if ($titulo === '') {
            throw new Gestor_Api_Validation_Exception('titulo_obrigatorio', 'Titulo obrigatorio');
        }
        $descricao = isset($data['descricao']) ? Validator::rich_text((string) $data['descricao'], 'descricao') : '';
        $cliente_id = Validator::ulid_optional($data['cliente_id'] ?? null, 'cliente_id');
        $area_id = Validator::ulid_optional($data['area_id'] ?? null, 'area_id');
        $status = Validator::enum(
            $data['status'] ?? 'PLANEJADO',
            'status',
            Validator::STATUS_PROJETO,
            'PLANEJADO'
        );
        $prioridade = Validator::enum(
            $data['prioridade'] ?? 'NORMAL',
            'prioridade',
            Validator::PRIORIDADE,
            'NORMAL'
        );
        $inicio_em = $this->iso_to_mysql(Validator::iso8601($data['inicio_em'] ?? null, 'inicio_em'));
        $fim_em = $this->iso_to_mysql(Validator::iso8601($data['fim_em'] ?? null, 'fim_em'));
        $progresso = Validator::decimal_0_1($data['progresso_calc'] ?? 0, 'progresso_calc');
        $participantes = Validator::json($data['participantes'] ?? [], 'participantes', true) ?? [];
        $versao_base = isset($data['versao_base']) ? (int) $data['versao_base'] : null;
        $now = current_time('mysql', true);

        if ($inicio_em !== null && $fim_em !== null && strtotime($fim_em) < strtotime($inicio_em)) {
            throw new Gestor_Api_Validation_Exception('data_invalida', 'fim_em deve ser >= inicio_em');
        }

        $existing = $this->find_by_id($id, $usuario_id);

        if ($existing === null) {
            $this->wpdb->insert(
                Schema::table('projetos'),
                [
                    'id' => $id,
                    'usuario_id' => $usuario_id,
                    'titulo' => $titulo,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'cliente_id' => $cliente_id,
                    'area_id' => $area_id,
                    'status' => $status,
                    'prioridade' => $prioridade,
                    'inicio_em' => $inicio_em,
                    'fim_em' => $fim_em,
                    'progresso_calc' => $progresso,
                    'participantes_json' => wp_json_encode($participantes),
                    'criado_em' => $now,
                    'atualizado_em' => $now,
                    'versao' => 1,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%d']
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
                Schema::table('projetos'),
                [
                    'titulo' => $titulo,
                    'descricao' => $descricao !== '' ? $descricao : null,
                    'cliente_id' => $cliente_id,
                    'area_id' => $area_id,
                    'status' => $status,
                    'prioridade' => $prioridade,
                    'inicio_em' => $inicio_em,
                    'fim_em' => $fim_em,
                    'progresso_calc' => $progresso,
                    'participantes_json' => wp_json_encode($participantes),
                    'atualizado_em' => $now,
                    'versao' => $nova_versao,
                ],
                ['id' => $id, 'usuario_id' => $usuario_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d'],
                ['%s', '%s']
            );
        }

        $row = $this->find_by_id($id, $usuario_id);
        if ($row === null) {
            throw new Gestor_Api_Validation_Exception('erro_persistencia', 'Falha ao persistir projeto', 500);
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
                "SELECT * FROM " . Schema::table('projetos')
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
    public function list_for_user(string $usuario_id, bool $include_deleted = false): array
    {
        $sql = "SELECT * FROM " . Schema::table('projetos') . " WHERE usuario_id = %s";
        if (!$include_deleted) {
            $sql .= " AND deletado_em IS NULL";
        }
        $sql .= " ORDER BY atualizado_em DESC";

        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $usuario_id),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function soft_delete(string $id, string $usuario_id): bool
    {
        $now = current_time('mysql', true);
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE " . Schema::table('projetos')
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
            'cliente_id' => $row['cliente_id'],
            'area_id' => $row['area_id'],
            'status' => $row['status'],
            'prioridade' => $row['prioridade'],
            'inicio_em' => $row['inicio_em'] ? self::iso8601((string) $row['inicio_em']) : null,
            'fim_em' => $row['fim_em'] ? self::iso8601((string) $row['fim_em']) : null,
            'progresso_calc' => (float) $row['progresso_calc'],
            'participantes' => json_decode($row['participantes_json'], true) ?: [],
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
