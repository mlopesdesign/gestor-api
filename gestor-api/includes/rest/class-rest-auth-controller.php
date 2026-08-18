<?php
/**
 * Controller REST de autenticacao.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Rest;

use Gestor_Api\Auth\Auth_Service;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Util\Logger;
use Gestor_Api\Util\Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Endpoints /auth/* + /admin/*.
 */
final class Rest_Auth_Controller extends Rest_Controller
{
    /**
     * @var array<string, mixed>|null
     */
    public static ?array $current_sessao = null;

    public function register_routes(): void
    {
        $instance = $this;

        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/refresh', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'refresh'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'logout'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/me', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'me'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        // Endpoints admin (WP capability, NAO token bearer).
        register_rest_route(self::NAMESPACE, '/admin/usuarios', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'admin_create_user'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route(self::NAMESPACE, '/admin/usuarios', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'admin_list_users'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Verifica se o request tem token valido (permission_callback WP REST).
     */
    public function auth_check(WP_REST_Request $request): bool
    {
        [$sessao, $error] = $this->require_auth($request);
        if ($error !== null) {
            // WP REST nao suporta retornar WP_Error em permission_callback,
            // mas em versoes recentes aceita. Guardamos a sessao em static.
            self::$current_sessao = null;
            return false;
        }
        self::$current_sessao = $sessao;
        return true;
    }

    public function login(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $auth = new Auth_Service();

        if (!$auth->check_rate_limit($ip)) {
            return Response::error(
                'rate_limit',
                sprintf(
                    'Limite de tentativas excedido. Tente em %d minutos.',
                    GESTOR_API_LOGIN_RATE_WINDOW_MIN
                ),
                429
            );
        }

        $body = $this->parse_json_body($request);
        $result = $auth->login($body);

        if (is_wp_error($result)) {
            $auth->increment_rate_limit($ip);
            return $result;
        }

        return $this->ok($result, 200);
    }

    public function refresh(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        if (self::$current_sessao === null) {
            return Response::error('token_invalido', 'Sessao nao disponivel', 401);
        }
        $auth = new Auth_Service();
        $result = $auth->refresh(self::$current_sessao);
        if (is_wp_error($result)) {
            return $result;
        }
        return $this->ok($result, 200);
    }

    public function logout(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        if (self::$current_sessao === null) {
            return Response::error('token_invalido', 'Sessao nao disponivel', 401);
        }
        $auth = new Auth_Service();
        $auth->logout(self::$current_sessao);
        return Response::no_content();
    }

    public function me(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        if (self::$current_sessao === null) {
            return Response::error('token_invalido', 'Sessao nao disponivel', 401);
        }
        $auth = new Auth_Service();
        $result = $auth->me(self::$current_sessao);
        if (is_wp_error($result)) {
            return $result;
        }
        return $this->ok($result, 200);
    }

    /**
     * Admin: cria usuario Gestor (sem passar por /auth/login).
     * Requer WP capability manage_options (cookie auth do admin WP).
     */
    public function admin_create_user(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $body = $this->parse_json_body($request);
        $email = (string) ($body['email'] ?? '');
        $senha = (string) ($body['senha'] ?? '');
        $nome  = (string) ($body['nome'] ?? '');

        if ($email === '' || $senha === '' || $nome === '') {
            return Response::error('campos_obrigatorios', 'email, senha e nome sao obrigatorios', 400);
        }

        try {
            $model = new Usuario();
            $id = $model->criar([
                'email' => $email,
                'senha' => $senha,
                'nome'  => $nome,
            ]);
            Logger::info('admin.user_created', [
                'id' => $id,
                'email' => $email,
                'by' => get_current_user_id(),
            ]);
            return $this->ok(['id' => $id, 'email' => $email, 'nome' => $nome], 201);
        } catch (\Throwable $e) {
            return $this->handle_exception($e);
        }
    }

    /**
     * Admin: lista usuarios Gestor.
     */
    public function admin_list_users(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $model = new Usuario();
        $rows = $model->list_all(100, 0);
        return $this->ok([
            'items' => array_map(static function (array $r): array {
                return [
                    'id' => $r['id'],
                    'email' => $r['email'],
                    'nome' => $r['nome'],
                    'criado_em' => $r['criado_em'],
                    'conta_apagada_em' => $r['conta_apagada_em'],
                ];
            }, $rows),
            'total' => count($rows),
        ]);
    }
}
