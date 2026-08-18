<?php
/**
 * Model de Cliente.
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
 * CRUD de Clientes.
 */
final class Cliente
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
        $nome = Validator::string($data['nome'] ?? '', 'nome', 255);
        $organizacao = isset($data['organizacao']) ? Validator::string((string) $data['organizacao'], 'organizacao', 255) : '';
        $contatos = Validator::json($data['contatos'] ?? [], 'contatos', true) ?? [];
        $observacoes = isset($data['observacoes']) ? Validator::rich_text((string) $data['observacoes'], 'observacoes') : '';
        $status = Validator::enum(
            $data['status'] ?? 'ATIVO',
            'status',
            Validator::STATUS_CLIENTE,
            'ATIVO'
        );
        $versao_base = isset($data['versao_base']) ? (int) $data['versao_base'] : null;
        $now = current_time('mysql', true);

        if ($nome === '' && $organizacao === '') {
            throw new Gestor_Api_Validation_Exception(
                'nome_ou_organizacao_obrigatorio',
                'Informe nome ou organizacao'
            );
        }

        $existing = $this->find_by_id($id, $usuario_id);

        if ($existing === null) {
            $this->wpdb->insert(
                Schema::table('clientes'),
                [
                    'id' => $id,
                    'usuario_id' => $usuario_id,
                    'nome' => $nome,
                    'organizacao' => $organizacao !== '' ? $organizacao : null,
                    'contatos_json' => wp_json_encode($contatos),
                    'observacoes' => $observacoes !== '' ? $observacoes : null,
                    'status' => $status,
                    'criado_em' => $now,
                    'atualizado_em' => $now,
                    'versao' => 1,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
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
                Schema::table('clientes'),
                [
                    'nome' => $nome,
                    'organizacao' => $organizacao !== '' ? $organizacao : null,
                    'contatos_json' => wp_json_encode($contatos),
                    'observacoes' => $observacoes !== '' ? $observacoes : null,
                    'status' => $status,
                    'atualizado_em' => $now,
                    'versao' => $nova_versao,
                ],
                ['id' => $id, 'usuario_id' => $usuario_id],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%d'],
                ['%s', '%s']
            );
        }

        $row = $this->find_by_id($id, $usuario_id);
        if ($row === null) {
            throw new Gestor_Api_Validation_Exception('erro_persistencia', 'Falha ao persistir cliente', 500);
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
                "SELECT * FROM " . Schema::table('clientes')
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
        $sql = "SELECT * FROM " . Schema::table('clientes') . " WHERE usuario_id = %s";
        if (!$include_deleted) {
            $sql .= " AND deletado_em IS NULL";
        }
        $sql .= " ORDER BY nome ASC";

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
                "UPDATE " . Schema::table('clientes')
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
            'nome' => $row['nome'],
            'organizacao' => $row['organizacao'],
            'contatos' => json_decode($row['contatos_json'], true) ?: [],
            'observacoes' => $row['observacoes'],
            'status' => $row['status'],
            'criado_em' => self::iso8601((string) $row['criado_em']),
            'atualizado_em' => self::iso8601((string) $row['atualizado_em']),
            'versao' => (int) $row['versao'],
        ];
    }

    private static function iso8601(string $mysql_datetime): string
    {
        $ts = strtotime($mysql_datetime . ' UTC');
        return $ts === false ? $mysql_datetime : gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }
}
