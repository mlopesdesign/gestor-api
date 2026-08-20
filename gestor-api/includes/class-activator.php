<?php
/**
 * Hook de ativacao do plugin.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api;

use Gestor_Api\DB\Schema;
use Gestor_Api\Util\Logger;

defined('ABSPATH') || exit;

/**
 * Activator — chamado em register_activation_hook.
 */
final class Activator
{
    /**
     * Cria tabelas, capabilities, faz flush de rewrite rules.
     *
     * SEMPRE em try/catch. Se falhar, deactivate_plugins para nao quebrar o site.
     */
    public static function activate(): void
    {
        try {
            // 1. Schema.
            Schema::install();

            // 2. Capabilities.
            self::install_capabilities();

            // 3. Flush rewrite rules.
            flush_rewrite_rules();

            // 4. Log.
            Logger::info('plugin.activated', ['version' => GESTOR_API_VERSION]);
        } catch (\Throwable $e) {
            // Mostra erro no admin e desativa o plugin.
            deactivate_plugins(plugin_basename(GESTOR_API_PLUGIN_FILE));
            wp_die(
                wp_kses_post(
                    sprintf(
                        '<h1>%s</h1><p>%s</p><pre>%s</pre>',
                        esc_html__('Erro ao ativar o plugin Gestor API', 'gestor-api'),
                        esc_html__('Verifique permissoes do banco e versao do PHP (>= 8.0).', 'gestor-api'),
                        esc_html($e->getMessage())
                    )
                ),
                esc_html__('Erro de ativacao', 'gestor-api'),
                ['back_link' => true]
            );
        }
    }

    /**
     * Cria capabilities customizadas em roles conhecidas.
     *
     * v0.1.4: adicionada 'gestor_api_use' — capacidade de USAR a API
     * (login, sync). Separada de 'gestor_api_manage' (admin do plugin).
     * Ambas vao no role 'administrator' por padrao, mas 'gestor_api_use'
     * pode ser dada a roles custom (ex: 'subscriber_gestor') pra criar
     * usuarios com acesso so a API, sem poderes WP.
     */
    private static function install_capabilities(): void
    {
        $caps = [
            'gestor_api_manage',       // admin do plugin (cria users, revoga tokens, etc)
            'gestor_api_view_users',
            'gestor_api_revoke_tokens',
            'gestor_api_use',          // NOVO v0.1.4: usar API (login, sync CRUD)
        ];
        $roles = ['administrator'];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role === null) {
                continue;
            }
            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }
}
