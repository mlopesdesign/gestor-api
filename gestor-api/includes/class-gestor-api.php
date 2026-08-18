<?php
/**
 * Singleton principal do plugin.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api;

use Gestor_Api\Admin\Admin_Page;
use Gestor_Api\Rest\Rest_Auth_Controller;
use Gestor_Api\Rest\Rest_Tarefas_Controller;
use Gestor_Api\Rest\Rest_Projetos_Controller;
use Gestor_Api\Rest\Rest_Clientes_Controller;
use Gestor_Api\Rest\Rest_Areas_Controller;
use Gestor_Api\Rest\Rest_Sync_Controller;

defined('ABSPATH') || exit;

/**
 * Classe principal do plugin. Registra rotas, inicializa subsistemas.
 */
final class Gestor_Api
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Hooks de inicializacao (chamado em plugins_loaded).
     */
    public function init(): void
    {
        // Garante schema atualizado em todo load (idempotente, barato).
        \Gestor_Api\DB\Migrations::run();

        // Admin UI.
        if (is_admin()) {
            Admin_Page::register();
        }
    }

    /**
     * Registra rotas REST.
     */
    public function register_rest_routes(): void
    {
        (new Rest_Auth_Controller())->register_routes();
        (new Rest_Tarefas_Controller())->register_routes();
        (new Rest_Projetos_Controller())->register_routes();
        (new Rest_Clientes_Controller())->register_routes();
        (new Rest_Areas_Controller())->register_routes();
        (new Rest_Sync_Controller())->register_routes();
    }
}
