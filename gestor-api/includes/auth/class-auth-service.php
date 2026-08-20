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

        // v0.1.4: tenta WP nativo PRIMEIRO, fallback pra tabela legada.
        // Usuarios novos: wp_users (com capability gestor_api_use).
        // Usuarios antigos (criados antes de v0.1.4): wp_gestor_usuarios (legado).
        $auth_source = null;     // 'wp' | 'legacy'
        $auth_user_id = null;    // string
        $auth_user_dto = null;   // array

        // Caminho 1: WP nativo.
        $wp_user = $this->find_wp_user_by_email($email);
        if ($wp_user !== null) {
            // Verifica capability ANTES de validar senha (evita user enumeration).
            if (!$this->wp_user_can_use_api($wp_user)) {
                Logger::warning('login.wp_no_capability', ['email' => $email, 'wp_id' => $wp_user->ID]);
                // Mensagem generica — mesmo erro de credenciais invalidas.
                return Response::error('credenciais_invalidas', 'Email ou senha incorretos', 401);
            }
            // Valida senha contra wp_users.user_pass (phpass).
            if (!wp_check_password($senha, (string) $wp_user->user_pass, (int) $wp_user->ID)) {
                Logger::warning('login.wp_wrong_password', ['email' => $email, 'wp_id' => $wp_user->ID]);
                Logger::audit(
                    (string) $wp_user->ID,
                    'auth',
                    (string) $wp_user->ID,
                    Logger::ACAO_LOGIN,
                    ['sucesso' => false, 'motivo' => 'senha_incorreta', 'origem' => 'wp'],
                    isset($body['dispositivo_id']) ? (string) $body['dispositivo_id'] : null
                );
                return Response::error('credenciais_invalidas', 'Email ou senha incorretos', 401);
            }
            // OK WP.
            $auth_source = 'wp';
            $auth_user_id = (string) $wp_user->ID;
            $auth_user_dto = $this->wp_user_to_dto($wp_user);
            Logger::info('login.wp_ok', ['wp_id' => $wp_user->ID, 'email' => $email]);
        } else {
            // Caminho 2: legado (wp_gestor_usuarios).
            $usuario = $this->find_usuario_by_email($email);
            if ($usuario === null) {
                Logger::warning('login.user_not_found', ['email' => $email]);
                return Response::error('credenciais_invalidas', 'Email ou senha incorretos', 401);
            }
            // Verifica LGPD legado.
            if ($usuario['conta_apagada_em'] !== null) {
                Logger::warning('login.user_deleted', ['email' => $email]);
                return Response::error('conta_apagada', 'Conta foi apagada', 403);
            }
            if (!password_verify($senha, $usuario['senha_hash'])) {
                Logger::warning('login.wrong_password', ['email' => $email]);
                Logger::audit(
                    $usuario['id'],
                    'auth',
                    $usuario['id'],
                    Logger::ACAO_LOGIN,
                    ['sucesso' => false, 'motivo' => 'senha_incorreta', 'origem' => 'legacy'],
                    isset($body['dispositivo_id']) ? (string) $body['dispositivo_id'] : null
                );
                return Response::error('credenciais_invalidas', 'Email ou senha incorretos', 401);
            }
            $auth_source = 'legacy';
            $auth_user_id = $usuario['id'];
            $auth_user_dto = $this->legacy_usuario_to_dto($usuario);
            Logger::info('login.legacy_ok', ['usuario_id' => $usuario['id'], 'email' => $email]);
        }

        // Cria sessao (sempre via tabela wp_gestor_sessoes, agnostic da origem).
        $meta = [
            'dispositivo_id' => isset($body['dispositivo_id']) ? substr((string) $body['dispositivo_id'], 0, 64) : null,
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '',
        ];

        $sessao = $this->tokens->create(
            $auth_user_id,
            GESTOR_API_TOKEN_TTL_DAYS,
            $meta
        );

        Logger::info('login.ok', [
            'usuario_id' => $auth_user_id,
            'origem' => $auth_source,
            'sessao_id' => $sessao['id'],
        ]);
        Logger::audit(
            $auth_user_id,
            'auth',
            $auth_user_id,
            Logger::ACAO_LOGIN,
            ['sucesso' => true, 'origem' => $auth_source, 'sistema' => (string) ($body['sistema'] ?? '')],
            $meta['dispositivo_id']
        );

        return [
            'token' => $sessao['token'],
            'expira_em' => $sessao['expira_em'],
            'usuario' => $auth_user_dto,
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
     * v0.1.4: tenta WP nativo primeiro, fallback legado.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function me(array $sessao)
    {
        $uid = (string) $sessao['usuario_id'];

        // Caminho 1: WP nativo.
        if (ctype_digit($uid)) {
            $wp_user = $this->find_wp_user_by_id((int) $uid);
            if ($wp_user !== null) {
                $apagada = get_user_meta($wp_user->ID, 'gestor_conta_apagada_em', true);
                if (!empty($apagada)) {
                    return Response::error('conta_apagada', 'Conta foi apagada', 403);
                }
                return $this->wp_user_to_dto($wp_user);
            }
        }

        // Caminho 2: legado.
        $usuario = $this->find_usuario_by_id($uid);
        if ($usuario === null) {
            return Response::error('usuario_nao_encontrado', 'Usuario nao encontrado', 404);
        }
        if ($usuario['conta_apagada_em'] !== null) {
            return Response::error('conta_apagada', 'Conta foi apagada', 403);
        }
        return $this->legacy_usuario_to_dto($usuario);
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
     * v0.1.4: busca user do WordPress (wp_users) por email.
     * Retorna WP_User se existir e tiver capability de uso da API, null caso contrario.
     *
     * @return \WP_User|null
     */
    private function find_wp_user_by_email(string $email)
    {
        $user = get_user_by('email', $email);
        if (!($user instanceof \WP_User)) {
            return null;
        }
        // Bloqueia login se conta foi marcada como apagada (LGPD).
        $apagada = get_user_meta($user->ID, 'gestor_conta_apagada_em', true);
        if (!empty($apagada)) {
            return null;
        }
        return $user;
    }

    /**
     * v0.1.4: busca user do WordPress por ID.
     *
     * @return \WP_User|null
     */
    private function find_wp_user_by_id(int $id)
    {
        $user = get_user_by('id', $id);
        return ($user instanceof \WP_User) ? $user : null;
    }

    /**
     * v0.1.4: verifica se user WP tem permissao pra usar a API do Gestor.
     * Aceita: capability 'gestor_api_use' OU capability 'manage_options' (admin WP).
     */
    private function wp_user_can_use_api(\WP_User $user): bool
    {
        if (user_can($user, 'gestor_api_use')) return true;
        if (user_can($user, 'manage_options')) return true; // admin WP sempre pode
        return false;
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
     * v0.1.4: DTO a partir de WP_User.
     * @return array<string, mixed>
     */
    private function wp_user_to_dto(\WP_User $user): array
    {
        return [
            'id' => (string) $user->ID,
            'email' => $user->user_email,
            'nome' => $user->display_name,
            'fuso' => get_user_meta($user->ID, 'gestor_fuso', true) ?: 'America/Sao_Paulo',
            'horario_trab_inicio' => get_user_meta($user->ID, 'gestor_horario_inicio', true) ?: '08:00',
            'horario_trab_fim' => get_user_meta($user->ID, 'gestor_horario_fim', true) ?: '18:00',
            'dias_trabalho' => json_decode((string) get_user_meta($user->ID, 'gestor_dias_trabalho', true), true) ?: [1, 2, 3, 4, 5],
            'tom_cobranca' => get_user_meta($user->ID, 'gestor_tom_cobranca', true) ?: 'PROFISSIONAL',
            'ia_habilitada' => (bool) get_user_meta($user->ID, 'gestor_ia_habilitada', true),
            'criado_em' => $this->to_iso8601((string) $user->user_registered),
            'atualizado_em' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'versao' => 1,
            'origem' => 'wp',
        ];
    }

    /**
     * Converte row do banco legado pra DTO de saida.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function legacy_usuario_to_dto(array $row): array
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
            'origem' => 'legacy',
        ];
    }

    /**
     * @deprecated 0.1.4 Manter por compat. Use legacy_usuario_to_dto() / wp_user_to_dto().
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function usuario_to_dto(array $row): array
    {
        return $this->legacy_usuario_to_dto($row);
    }

    private function to_iso8601(string $mysql_datetime): string
    {
        $ts = strtotime($mysql_datetime . ' UTC');
        return gmdate('Y-m-d\TH:i:s.v\Z', $ts);
    }
}
