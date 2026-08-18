<?php
/**
 * Base abstrata para todos os controllers REST.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Rest;

use Gestor_Api\Auth\Auth_Service;
use Gestor_Api\Auth\Token_Repository;
use Gestor_Api\Util\Gestor_Api_Validation_Exception;
use Gestor_Api\Util\Response;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Helpers comuns a todos os endpoints.
 */
abstract class Rest_Controller extends \WP_REST_Controller
{
    protected const NAMESPACE = 'gestor/v1';

    /**
     * Exige token valido no header Authorization: Bearer <token>.
     * Retorna (sessao, error) onde sessao e a row do banco ou error.
     *
     * @return array{0: array<string, mixed>|null, 1: WP_Error|null}
     */
    protected function require_auth(WP_REST_Request $request): array
    {
        $auth_header = (string) $request->get_header('authorization');
        if ($auth_header === '' || stripos($auth_header, 'bearer ') !== 0) {
            return [null, Response::error(
                'token_ausente',
                'Header Authorization: Bearer <token> obrigatorio',
                401
            )];
        }
        $token = trim(substr($auth_header, 7));
        if ($token === '') {
            return [null, Response::error('token_ausente', 'Token vazio', 401)];
        }

        $tokens = new Token_Repository();
        $sessao = $tokens->find_by_token($token);
        if ($sessao === null) {
            return [null, Response::error('token_invalido', 'Token invalido ou expirado', 401)];
        }
        return [$sessao, null];
    }

    /**
     * Parseia body JSON.
     *
     * @return array<string, mixed>
     */
    protected function parse_json_body(WP_REST_Request $request): array
    {
        $body = $request->get_body();
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [];
        }
        return $decoded;
    }

    /**
     * Helper para retornar sucesso.
     *
     * @param mixed $data
     */
    protected function ok($data = null, int $status = 200): WP_REST_Response
    {
        return Response::success($data, $status);
    }

    /**
     * Helper para retornar erro de validacao capturando exception.
     *
     * @return WP_Error|WP_REST_Response
     */
    protected function handle_exception(\Throwable $e)
    {
        if ($e instanceof Gestor_Api_Validation_Exception) {
            return $e->wp_error;
        }
        return Response::error(
            'erro_interno',
            'Erro interno: ' . $e->getMessage(),
            500
        );
    }
}
