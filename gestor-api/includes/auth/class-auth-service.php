<?php
/**
 * Servico de autenticacao (login, refresh, logout, me).
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Auth;

use Gestor_Api\DB\Schema;
use Gestor_Api\Util\Gestor_Api_Validation_Exception;
use Gestor_Api\Util\Logger;
use Gestor_Api\Util\Response;
use Gestor_Api\Util\Validator;
use WP_Error;
use wpdb;

defined('ABSPATH') || exit;

/**
 * Logica de autenticacao.
 */
final class Auth_Service
{
    private Token_Repository $tokens;
    private wpdb $wpdb;

    public function __construct()
    {
        $this->tokens = new Token_Repository();
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Autentica usuario. Retorna WP_Error em falha.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    public function login(array $body)
    {
        try {
            $email = Validator::email($body['email'] ?? '', 'email');
            $senha = (string) ($body['senha'] ?? '');
            if ($senha === '') {
                throw new Gestor_Api_Validation_Exception('senha_obrigatoria', 'Senha obrigatoria');
            }
        } catch (Gestor_Api_Validation_Exception $e) {
            return $e->wp_error;
        }

        // Busca usuario.
        $usuario = $this->find_usuario_by_email($email);
        if ($usuario === null) {
            Logger::warning('login.user_not_found', ['email' => $email]);
            return Response::error('credenciais_invalidas', 'Email ou senha incorretos', 401);
        }

        // Verifica se conta foi apagada (LGPD).
        if ($usuario['conta_apagada_em'] !== null) {
            Logger::warning('login.user_deleted', ['email' => $email]);
            return Response::error('conta_apagada', 'Conta foi apagada', 403);
        }

        // Valida senha.
        if (!password_verify($senha, $usuario['senha_hash'])) {
            Logger::warning('login.wrong_password', ['email' => $email]);
            Logger::audit(
                $usuario['id'],
                'auth',
                $usuario['id'],
                Logger::ACAO_LOGIN,
                ['sucesso' => false, 'motivo' => 'senha_incorreta'],
                isset($body['dispositivo_id']) ? (string) $body['dispositivo_id'] : null
            );
            return Response::error('credenciais_invalidas', 'Email ou senha incorretos', 401);
        }

        // Cria sessao.
        $meta = [
            'dispositivo_id' => isset($body['dispositivo_id']) ? substr((string) $body['dispositivo_id'], 0, 64) : null,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '',
        ];

        $sessao = $this->tokens->create(
            $usuario['id'],
            GESTOR_API_TOKEN_TTL_DAYS,
            $meta
        );

        Logger::info('login.ok', [
            'usuario_id' => $usuario['id'],
            'sessao_id' => $sessao['id'],
        ]);
        Logger::audit(
            $usuario['id'],
            'auth',
            $usuario['id'],
            Logger::ACAO_LOGIN,
            ['sucesso' => true, 'sistema' => (string) ($body['sistema'] ?? '')],
            $meta['dispositivo_id']
        );

        return [
            'token' => $sessao['token'],
            'expira_em' => $sessao['expira_em'],
            'usuario' => $this->usuario_to_dto($usuario),
        ];
    }

    /**
     * Renova token: revoga o atual e gera novo.
     *
     * @param array<string, mixed> $sessao Sessao atual (do token).
     * @return array<string, mixed>|WP_Error
     */
    public function refresh(array $sessao)
    {
        $usuario_id = (string) $sessao['usuario_id'];

        // Revoga o token atual.
        $this->tokens->revoke((string) $sessao['id']);

        // Cria novo.
        $meta = [
            'dispositivo_id' => $sessao['dispositivo_id'] ?? null,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
            'user_agent' => $sessao['user_agent'] ?? '',
        ];

        $nova = $this->tokens->create($usuario_id, GESTOR_API_TOKEN_TTL_DAYS, $meta);

        Logger::info('auth.refresh', [
            'usuario_id' => $usuario_id,
            'old_sessao' => $sessao['id'],
            'new_sessao' => $nova['id'],
        ]);

        return [
            'token' => $nova['token'],
            'expira_em' => $nova['expira_em'],
        ];
    }

    /**
     * Revoga sessao atual.
     */
    public function logout(array $sessao): bool
    {
        $ok = $this->tokens->revoke((string) $sessao['id']);
        Logger::audit(
            (string) $sessao['usuario_id'],
            'auth',
            (string) $sessao['id'],
            Logger::ACAO_LOGOUT,
            null,
            $sessao['dispositivo_id'] ?? null
        );
        Logger::info('auth.logout', [
            'usuario_id' => $sessao['usuario_id'],
            'sessao_id' => $sessao['id'],
        ]);
        return $ok;
    }

    /**
     * Retorna dados do usuario autenticado.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function me(array $sessao)
    {
        $usuario = $this->find_usuario_by_id((string) $sessao['usuario_id']);
        if ($usuario === null) {
            return Response::error('usuario_nao_encontrado', 'Usuario nao encontrado', 404);
        }
        return $this->usuario_to_dto($usuario);
    }

    /**
     * Rate limit em /auth/login. 5 tentativas / 15min / IP.
     */
    public function check_rate_limit(string $ip): bool
    {
        $key = 'gestor_api_rl_' . md5($ip);
        $count = (int) get_transient($key);
        return $count < GESTOR_API_LOGIN_RATE_LIMIT;
    }

    public function increment_rate_limit(string $ip): void
    {
        $key = 'gestor_api_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count === 0) {
            set_transient(
                $key,
                1,
                GESTOR_API_LOGIN_RATE_WINDOW_MIN * MINUTE_IN_SECONDS
            );
        } else {
            set_transient(
                $key,
                $count + 1,
                GESTOR_API_LOGIN_RATE_WINDOW_MIN * MINUTE_IN_SECONDS
            );
        }
    }

    public function rate_limit_remaining(string $ip): int
    {
        $key = 'gestor_api_rl_' . md5($ip);
        $count = (int) get_transient($key);
        return max(0, GESTOR_API_LOGIN_RATE_LIMIT - $count);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_usuario_by_email(string $email): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM " . Schema::table('usuarios') . " WHERE email = %s",
                $email
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_usuario_by_id(string $id): ?array
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
     * Converte row do banco pra DTO de saida.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function usuario_to_dto(array $row): array
    {
        return [
            'id' => $row['id'],
            'email' => $row['email'],
            'nome' => $row['nome'],
            'fuso' => $row['fuso'],
            'horario_trab_inicio' => $row['horario_trab_inicio'],
            'horario_trab_fim' => $row['horario_trab_fim'],
            'dias_trabalho' => json_decode($row['dias_trabalho_json'], true) ?: [],
            'tom_cobranca' => $row['tom_cobranca'],
            'ia_habilitada' => (bool) $row['ia_habilitada'],
            'criado_em' => $this->to_iso8601($row['criado_em']),
            'atualizado_em' => $this->to_iso8601($row['atualizado_em']),
            'versao' => (int) $row['versao'],
        ];
    }

    private function to_iso8601(string $mysql_datetime): string
    {
        $ts = strtotime($mysql_datetime . ' UTC');
        return gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }
}
