<?php
/**
 * Controller REST de Sync.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Rest;

use Gestor_Api\Sync\Sync_Conflict_Resolver;
use Gestor_Api\Sync\Sync_Pull;
use Gestor_Api\Sync\Sync_Push;
use Gestor_Api\Util\Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Endpoints /sync/* (pull, push, conflitos).
 */
final class Rest_Sync_Controller extends Rest_Controller
{
    public function register_routes(): void
    {
        $instance = $this;

        register_rest_route(self::NAMESPACE, '/sync/pull', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'pull'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/sync/push', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'push'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/sync/conflitos', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'list_conflitos'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/sync/conflitos/(?P<id>\d+)/resolver', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'resolver_conflito'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);
    }

    public function auth_check(WP_REST_Request $request): bool
    {
        [$sessao, $error] = $this->require_auth($request);
        if ($error !== null) {
            Rest_Auth_Controller::$current_sessao = null;
            return false;
        }
        Rest_Auth_Controller::$current_sessao = $sessao;
        return true;
    }

    public function pull(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $dispositivo_id = (string) ($request->get_param('dispositivo_id') ?? '');
        if ($dispositivo_id === '') {
            return Response::error('dispositivo_id_obrigatorio', 'dispositivo_id obrigatorio');
        }
        $since = (string) ($request->get_param('since') ?? '');
        $limit = (int) ($request->get_param('limit') ?? 200);
        $offset = (int) ($request->get_param('offset') ?? 0);

        $pull = new Sync_Pull();
        $result = $pull->executar(
            $sessao['usuario_id'],
            $dispositivo_id,
            $since,
            min(max($limit, 1), 1000),
            max($offset, 0)
        );
        return $this->ok($result);
    }

    public function push(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $body = $this->parse_json_body($request);
        $dispositivo_id = (string) ($body['dispositivo_id'] ?? '');
        if ($dispositivo_id === '') {
            return Response::error('dispositivo_id_obrigatorio', 'dispositivo_id obrigatorio');
        }
        $mutacoes = $body['mutacoes'] ?? [];
        if (!is_array($mutacoes)) {
            return Response::error('mutacoes_invalidas', 'mutacoes deve ser array');
        }

        $push = new Sync_Push();
        $result = $push->executar(
            $sessao['usuario_id'],
            $dispositivo_id,
            $mutacoes
        );
        return $this->ok($result);
    }

    public function list_conflitos(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $push = new Sync_Push();
        $conflitos = $push->listar_conflitos_pendentes($sessao['usuario_id']);
        return $this->ok([
            'items' => $conflitos,
            'total' => count($conflitos),
        ]);
    }

    public function resolver_conflito(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $body = $this->parse_json_body($request);
        $escolha = (string) ($body['escolha'] ?? '');
        $payload_merged = $body['payload_merged'] ?? null;

        try {
            $resolver = new Sync_Conflict_Resolver();
            $result = $resolver->resolver(
                (int) $request['id'],
                $sessao['usuario_id'],
                $escolha,
                is_array($payload_merged) ? $payload_merged : null
            );
            return $this->ok($result);
        } catch (\Throwable $e) {
            return $this->handle_exception($e);
        }
    }
}
