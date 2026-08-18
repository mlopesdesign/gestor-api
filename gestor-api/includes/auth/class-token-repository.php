<?php
/**
 * Repositorio de tokens (wp_gestor_sessoes).
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Auth;

use Gestor_Api\DB\Schema;
use Gestor_Api\Util\Ulid;
use wpdb;

defined('ABSPATH') || exit;

/**
 * CRUD de sessoes/tokens.
 */
final class Token_Repository
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Cria nova sessao e retorna (id, token plain, expira_em).
     *
     * @param array<string, mixed> $meta
     * @return array{id: string, token: string, expira_em: string}
     */
    public function create(string $usuario_id, int $ttl_days, array $meta = []): array
    {
        $id = Ulid::generate();
        $token = $this->generate_token();
        $token_hash = $this->hash_token($token);
        $now = current_time('mysql', true);
        $expira = gmdate('Y-m-d H:i:s', time() + ($ttl_days * 86400));

        $this->wpdb->insert(
            Schema::table('sessoes'),
            [
                'id' => $id,
                'usuario_id' => $usuario_id,
                'token_hash' => $token_hash,
                'criada_em' => $now,
                'expira_em' => $expira,
                'dispositivo_id' => $meta['dispositivo_id'] ?? null,
                'ip_criacao' => $meta['ip'] ?? null,
                'user_agent' => isset($meta['user_agent']) ? substr((string) $meta['user_agent'], 0, 255) : null,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return [
            'id' => $id,
            'token' => $token,
            'expira_em' => $this->to_iso8601($expira),
        ];
    }

    /**
     * Busca sessao ativa por token plain.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_token(string $token): ?array
    {
        $token_hash = $this->hash_token($token);
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('sessoes') . " WHERE token_hash = %s",
                $token_hash
            ),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }
        if ($row['revogada_em'] !== null) {
            return null;
        }
        // Verifica expiracao (string compare funciona pra DATETIME ISO).
        if (strtotime($row['expira_em'] . ' UTC') < time()) {
            return null;
        }
        return $row;
    }

    /**
     * Revoga sessao por ID.
     */
    public function revoke(string $sessao_id): bool
    {
        $now = current_time('mysql', true);
        $result = $this->wpdb->update(
            Schema::table('sessoes'),
            ['revogada_em' => $now],
            ['id' => $sessao_id],
            ['%s'],
            ['%s']
        );
        return $result !== false;
    }

    /**
     * Revoga todas as sessoes de um usuario.
     */
    public function revoke_all_for_user(string $usuario_id): int
    {
        $now = current_time('mysql', true);
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE " . Schema::table('sessoes') . "
                 SET revogada_em = %s
                 WHERE usuario_id = %s AND revogada_em IS NULL",
                $now,
                $usuario_id
            )
        );
        return (int) $result;
    }

    /**
     * Lista sessoes ativas de um usuario.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_active_for_user(string $usuario_id): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, dispositivo_id, ip_criacao, user_agent, criada_em, expira_em
                 FROM " . Schema::table('sessoes') . "
                 WHERE usuario_id = %s AND revogada_em IS NULL
                 ORDER BY criada_em DESC",
                $usuario_id
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Remove sessoes expiradas (cleanup).
     */
    public function cleanup_expired(): int
    {
        $result = $this->wpdb->query(
            "DELETE FROM " . Schema::table('sessoes') . "
             WHERE expira_em < UTC_TIMESTAMP() - INTERVAL 7 DAY"
        );
        return (int) $result;
    }

    private function generate_token(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hash_token(string $token): string
    {
        return hash('sha256', $token);
    }

    private function to_iso8601(string $mysql_datetime): string
    {
        $ts = strtotime($mysql_datetime . ' UTC');
        return gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }
}
