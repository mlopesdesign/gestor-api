<?php
/**
 * Model de Usuario.
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
 * Operacoes CRUD de usuarios + LGPD (soft-delete de conta).
 */
final class Usuario
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Cria usuario novo.
     *
     * @param array<string, mixed> $data
     * @return string ULID do usuario criado.
     */
    public function criar(array $data): string
    {
        $email = Validator::email($data['email'] ?? '', 'email');
        $senha = Validator::senha($data['senha'] ?? '', 'senha');
        $nome = Validator::string($data['nome'] ?? '', 'nome', 255);

        if ($this->find_by_email($email) !== null) {
            throw new Gestor_Api_Validation_Exception(
                'email_ja_cadastrado',
                'Email ja cadastrado',
                409
            );
        }

        $id = Ulid::generate();
        $now = current_time('mysql', true);
        $dias = $data['dias_trabalho'] ?? [1, 2, 3, 4, 5];

        $row = [
            'id' => $id,
            'email' => $email,
            'senha_hash' => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
            'nome' => $nome,
            'fuso' => isset($data['fuso']) ? Validator::string((string) $data['fuso'], 'fuso', 64) : 'America/Sao_Paulo',
            'horario_trab_inicio' => isset($data['horario_trab_inicio']) ? (string) $data['horario_trab_inicio'] : '08:00',
            'horario_trab_fim' => isset($data['horario_trab_fim']) ? (string) $data['horario_trab_fim'] : '18:00',
            'dias_trabalho_json' => wp_json_encode($dias),
            'tom_cobranca' => Validator::enum(
                $data['tom_cobranca'] ?? 'PROFISSIONAL',
                'tom_cobranca',
                Validator::TOM_COBRANCA,
                'PROFISSIONAL'
            ),
            'ia_habilitada' => !empty($data['ia_habilitada']) ? 1 : 0,
            'ia_consentimento_em' => !empty($data['ia_habilitada']) ? $now : null,
            'criado_em' => $now,
            'atualizado_em' => $now,
            'versao' => 1,
        ];

        $this->wpdb->insert(
            Schema::table('usuarios'),
            $row,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d']
        );

        return $id;
    }

    /**
     * Busca usuario por email.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_email(string $email): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('usuarios') . " WHERE email = %s",
                strtolower($email)
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Busca usuario por ID.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_id(string $id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('usuarios') . " WHERE id = %s",
                $id
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Lista todos usuarios (admin).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_all(int $limit = 100, int $offset = 0): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, email, nome, criado_em, conta_apagada_em FROM "
                . Schema::table('usuarios')
                . " ORDER BY criado_em DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * LGPD: apaga conta do usuario (soft-delete + cascade via FK).
     */
    public function apagar_conta(string $usuario_id): bool
    {
        $now = current_time('mysql', true);
        $result = $this->wpdb->update(
            Schema::table('usuarios'),
            [
                'conta_apagada_em' => $now,
                'atualizado_em' => $now,
                'email' => 'deleted-' . $usuario_id . '@deleted.local',
            ],
            ['id' => $usuario_id],
            ['%s', '%s', '%s'],
            ['%s']
        );
        return $result !== false;
    }
}
