<?php
/**
 * Helpers de resposta JSON para a API REST.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Util;

use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Constroi respostas REST padronizadas.
 */
final class Response
{
    /**
     * Resposta de sucesso.
     *
     * @param mixed $data Payload a retornar.
     * @param int   $status Codigo HTTP.
     */
    public static function success($data = null, int $status = 200): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    /**
     * Resposta de erro padrao WP REST.
     *
     * @param string               $code Codigo do erro (slug).
     * @param string               $message Mensagem legivel.
     * @param int                  $status Codigo HTTP.
     * @param array<string, mixed> $data Dados extras.
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $data = []
    ): WP_Error {
        return new WP_Error($code, $message, array_merge($data, ['status' => $status]));
    }

    /**
     * Resposta sem conteudo (204).
     */
    public static function no_content(): WP_REST_Response
    {
        return new WP_REST_Response(null, 204);
    }
}
