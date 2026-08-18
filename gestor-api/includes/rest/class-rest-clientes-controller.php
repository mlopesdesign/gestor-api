<?php
/**
 * Controller REST de Clientes.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Rest;

use Gestor_Api\Models\Cliente;
use Gestor_Api\Util\Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Endpoints /clientes/* (CRUD).
 */
final class Rest_Clientes_Controller extends Rest_Controller
{
    public function register_routes(): void
    {
        $instance = $this;

        register_rest_route(self::NAMESPACE, '/clientes', [
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

        register_rest_route(self::NAMESPACE, '/clientes/(?P<id>[0-9A-HJKMNP-TV-Z]{26})', [
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
        $model = new Cliente();
        $items = $model->list_for_user($sessao['usuario_id']);
        return $this->ok([
            'items' => array_map([Cliente::class, 'to_dto'], $items),
            'total' => count($items),
        ]);
    }

    public function get_item($request): WP_REST_Response|\WP_Error
    {
        $sessao = Rest_Auth_Controller::$current_sessao;
        if ($sessao === null) {
            return Response::error('nao_autenticado', 'Nao autenticado', 401);
        }
        $model = new Cliente();
        $row = $model->find_by_id((string) $request['id'], $sessao['usuario_id']);
        if ($row === null) {
            return Response::error('cliente_nao_encontrado', 'Cliente nao encontrado', 404);
        }
        return $this->ok(Cliente::to_dto($row));
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
        $model = new Cliente();
        $ok = $model->soft_delete((string) $request['id'], $sessao['usuario_id']);
        if (!$ok) {
            return Response::error('cliente_nao_encontrado', 'Cliente nao encontrado', 404);
        }
        return Response::no_content();
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
            $model = new Cliente();
            $row = $model->upsert($sessao['usuario_id'], $body);
            return $this->ok(Cliente::to_dto($row), $forced_id === null ? 201 : 200);
        } catch (\Throwable $e) {
            return $this->handle_exception($e);
        }
    }
}
