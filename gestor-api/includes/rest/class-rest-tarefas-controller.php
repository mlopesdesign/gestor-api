<?php
/**
 * Controller REST de Tarefas (CRUD + endpoints especiais).
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Rest;

use Gestor_Api\Models\Tarefa;
use Gestor_Api\Util\Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Endpoints /tarefas/* (CRUD + /hoje, /atrasadas, /projeto, /cliente, /concluir, /reabrir).
 */
final class Rest_Tarefas_Controller extends Rest_Controller
{
    public function register_routes(): void
    {
        $instance = $this;

        register_rest_route(self::NAMESPACE, '/tarefas', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$instance, 'list_items'],
                'permission_callback' => [$instance, 'auth_check'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$instance, 'create_item'],
                'permission_callback' => [$instance, 'auth_check'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/tarefas/hoje', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'list_hoje'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/tarefas/atrasadas', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'list_atrasadas'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/tarefas/projeto/(?P<id>[0-9A-HJKMNP-TV-Z]{26})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'list_por_projeto'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/tarefas/cliente/(?P<id>[0-9A-HJKMNP-TV-Z]{26})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$instance, 'list_por_cliente'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/tarefas/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/concluir', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'concluir'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/tarefas/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/reabrir', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$instance, 'reabrir'],
            'permission_callback' => [$instance, 'auth_check'],
        ]);

        register_rest_route(self::NAMESPACE, '/tarefas/(?P<id>[0-9A-HJKMNP-TV-Z]{26})', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$instance, 'get_item'],
                'permission_callback' => [$instance, 'auth_check'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$instance, 'update_item'],
                'permission_callback' => [$instance, 'auth_check'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$instance, 'delete_item'],
                'permission_callback' => [$instance, 'auth_check'],
            ],
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

    public function list_items(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $status = $request->get_param('status');
        $limit = (int) $request->get_param('limit') ?: 100;
        $offset = (int) $request->get_param('offset') ?: 0;
        $model = new Tarefa();
        $items = $model->list_for_user(
            $sessao['usuario_id'],
            false,
            is_string($status) ? $status : null,
            min(max($limit, 1), 500),
            max($offset, 0)
        );
        return $this->ok([
            'items' => array_map([Tarefa::class, 'to_dto'], $items),
            'total' => count($items),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function list_hoje(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $model = new Tarefa();
        $items = $model->list_hoje($sessao['usuario_id']);
        return $this->ok([
            'items' => array_map([Tarefa::class, 'to_dto'], $items),
            'total' => count($items),
        ]);
    }

    public function list_atrasadas(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $model = new Tarefa();
        $items = $model->list_atrasadas($sessao['usuario_id']);
        return $this->ok([
            'items' => array_map([Tarefa::class, 'to_dto'], $items),
            'total' => count($items),
        ]);
    }

    public function list_por_projeto(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $model = new Tarefa();
        $items = $model->list_por_projeto($sessao['usuario_id'], (string) $request['id']);
        return $this->ok([
            'items' => array_map([Tarefa::class, 'to_dto'], $items),
            'total' => count($items),
        ]);
    }

    public function list_por_cliente(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $model = new Tarefa();
        $items = $model->list_por_cliente($sessao['usuario_id'], (string) $request['id']);
        return $this->ok([
            'items' => array_map([Tarefa::class, 'to_dto'], $items),
            'total' => count($items),
        ]);
    }

    public function get_item($request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $model = new Tarefa();
        $row = $model->find_by_id((string) $request['id'], $sessao['usuario_id']);
        if ($row === null) {
            return Response::error('tarefa_nao_encontrada', 'Tarefa nao encontrada', 404);
        }
        return $this->ok(Tarefa::to_dto($row));
    }

    public function create_item($request): WP_REST_Response|\WP_Error
    {
        return $this->upsert($request);
    }

    public function update_item($request): WP_REST_Response|\WP_Error
    {
        return $this->upsert($request, (string) $request['id']);
    }

    public function delete_item($request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $model = new Tarefa();
        $ok = $model->soft_delete((string) $request['id'], $sessao['usuario_id']);
        if (!$ok) {
            return Response::error('tarefa_nao_encontrada', 'Tarefa nao encontrada', 404);
        }
        return Response::no_content();
    }

    public function concluir(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $body = $this->parse_json_body($request);
        $confirmada = (bool) ($body['confirmada'] ?? true);
        try {
            $model = new Tarefa();
            $row = $model->concluir((string) $request['id'], $sessao['usuario_id'], $confirmada);
            return $this->ok(Tarefa::to_dto($row));
        } catch (\Throwable $e) {
            return $this->handle_exception($e);
        }
    }

    public function reabrir(WP_REST_Request $request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        try {
            $model = new Tarefa();
            $row = $model->reabrir((string) $request['id'], $sessao['usuario_id']);
            return $this->ok(Tarefa::to_dto($row));
        } catch (\Throwable $e) {
            return $this->handle_exception($e);
        }
    }

    private function upsert(WP_REST_Request $request, ?string $forced_id = null): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $body = $this->parse_json_body($request);
        if ($forced_id !== null) {
            $body['id'] = $forced_id;
        }
        try {
            $model = new Tarefa();
            $row = $model->upsert($sessao['usuario_id'], $body);
            return $this->ok(Tarefa::to_dto($row), $forced_id === null ? 201 : 200);
        } catch (\Throwable $e) {
            return $this->handle_exception($e);
        }
    }
}
