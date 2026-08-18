<?php
/**
 * Pagina admin WP: gerenciar usuarios, sessoes, revogar tokens.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

namespace Gestor_Api\Admin;

use Gestor_Api\Auth\Token_Repository;
use Gestor_Api\Models\Usuario;

defined('ABSPATH') || exit;

/**
 * Tela WP admin.
 */
final class Admin_Page
{
    public const MENU_SLUG = 'gestor-api';
    public const CAPABILITY = 'gestor_api_manage';

    /**
     * Registra hooks.
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_post_gestor_api_revoke_all', [self::class, 'handle_revoke_all']);
        add_action('admin_post_gestor_api_revoke_one', [self::class, 'handle_revoke_one']);
        add_action('admin_post_gestor_api_create_user', [self::class, 'handle_create_user']);
    }

    public static function register_menu(): void
    {
        // Icone SVG inline (dashicons-equivalente) — sem dep externa.
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><rect x="2" y="3" width="16" height="14" rx="2" fill="#F0A000"/><rect x="4" y="6" width="8" height="2" fill="#fff"/><rect x="4" y="9" width="12" height="2" fill="#fff"/><rect x="4" y="12" width="10" height="2" fill="#fff"/></svg>'
        );

        add_menu_page(
            __('Gestor API', 'gestor-api'),
            __('Gestor API', 'gestor-api'),
            self::CAPABILITY,
            self::MENU_SLUG,
            [self::class, 'render_page'],
            $icon,
            30
        );
    }

    /**
     * Renderiza pagina admin.
     */
    public static function render_page(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Sem permissao.', 'gestor-api'));
        }

        $usuario_model = new Usuario();
        $usuarios = $usuario_model->list_all(100, 0);

        // Mensagens de feedback (de redirect apos admin-post).
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['gestor_api_msg'])) {
            $msg = sanitize_text_field((string) wp_unslash($_GET['gestor_api_msg']));
            $cls = isset($_GET['gestor_api_ok']) ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($cls) . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gestor API', 'gestor-api') . '</h1>';
        echo '<p>' . esc_html(sprintf('Versao do plugin: %s', GESTOR_API_VERSION)) . '</p>';

        // Form para criar usuario inicial (sem precisar de WP-CLI ou endpoint REST).
        echo '<h2>' . esc_html__('Criar usuario Gestor', 'gestor-api') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:520px;margin-bottom:24px;">';
        echo '<input type="hidden" name="action" value="gestor_api_create_user" />';
        wp_nonce_field('gestor_api_create_user');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="gu_email">Email</label></th>';
        echo '<td><input type="email" name="email" id="gu_email" class="regular-text" required /></td></tr>';
        echo '<tr><th scope="row"><label for="gu_nome">Nome</label></th>';
        echo '<td><input type="text" name="nome" id="gu_nome" class="regular-text" required /></td></tr>';
        echo '<tr><th scope="row"><label for="gu_senha">Senha (min 8 chars, 1 maiuscula, 1 minuscula, 1 numero)</label></th>';
        echo '<td><input type="password" name="senha" id="gu_senha" class="regular-text" required minlength="8" /></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Criar usuario', 'gestor-api'));
        echo '</form>';

        echo '<h2>' . esc_html__('Usuarios', 'gestor-api') . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Email', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Nome', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Criado em', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Conta apagada em', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Acoes', 'gestor-api') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($usuarios as $u) {
            $apagada = $u['conta_apagada_em'] !== null && $u['conta_apagada_em'] !== '0000-00-00 00:00:00.000';
            echo '<tr>';
            echo '<td><code>' . esc_html((string) $u['id']) . '</code></td>';
            echo '<td>' . esc_html((string) $u['email']) . '</td>';
            echo '<td>' . esc_html((string) $u['nome']) . '</td>';
            echo '<td>' . esc_html((string) $u['criado_em']) . '</td>';
            echo '<td>' . ($apagada ? esc_html((string) $u['conta_apagada_em']) : '—') . '</td>';
            echo '<td>';
            if (!$apagada) {
                $url = wp_nonce_url(
                    add_query_arg(
                        [
                            'action' => 'gestor_api_revoke_all',
                            'usuario_id' => (string) $u['id'],
                        ],
                        admin_url('admin-post.php')
                    ),
                    'gestor_api_revoke_all'
                );
                echo '<a class="button" href="' . esc_url($url) . '">';
                echo esc_html__('Revogar todos os tokens', 'gestor-api');
                echo '</a>';
            } else {
                echo '—';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        // Sessoes ativas.
        echo '<h2>' . esc_html__('Sessoes ativas', 'gestor-api') . '</h2>';
        $tokens = new Token_Repository();
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Usuario', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Dispositivo', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('IP', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Criada em', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Expira em', 'gestor-api') . '</th>';
        echo '<th>' . esc_html__('Acoes', 'gestor-api') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($usuarios as $u) {
            if ($u['conta_apagada_em'] !== null) {
                continue;
            }
            $sessoes = $tokens->list_active_for_user((string) $u['id']);
            foreach ($sessoes as $s) {
                echo '<tr>';
                echo '<td>' . esc_html((string) $u['email']) . '</td>';
                echo '<td>' . esc_html((string) ($s['dispositivo_id'] ?? '—')) . '</td>';
                echo '<td>' . esc_html((string) ($s['ip_criacao'] ?? '—')) . '</td>';
                echo '<td>' . esc_html((string) $s['criada_em']) . '</td>';
                echo '<td>' . esc_html((string) $s['expira_em']) . '</td>';
                echo '<td>';
                $url = wp_nonce_url(
                    add_query_arg(
                        [
                            'action' => 'gestor_api_revoke_one',
                            'sessao_id' => (string) $s['id'],
                        ],
                        admin_url('admin-post.php')
                    ),
                    'gestor_api_revoke_one'
                );
                echo '<a class="button" href="' . esc_url($url) . '">';
                echo esc_html__('Revogar', 'gestor-api');
                echo '</a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody>';
        echo '</table>';

        echo '</div>';
    }

    /**
     * Handler: revoga todas sessoes de um usuario.
     */
    public static function handle_revoke_all(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Sem permissao.', 'gestor-api'));
        }
        check_admin_referer('gestor_api_revoke_all');
        $usuario_id = (string) ($_GET['usuario_id'] ?? '');
        if ($usuario_id === '') {
            wp_die(esc_html__('usuario_id obrigatorio.', 'gestor-api'));
        }
        $tokens = new Token_Repository();
        $count = $tokens->revoke_all_for_user($usuario_id);
        wp_safe_redirect(
            add_query_arg(
                ['page' => self::MENU_SLUG, 'revoked' => $count],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Handler: revoga uma sessao especifica.
     */
    public static function handle_revoke_one(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Sem permissao.', 'gestor-api'));
        }
        check_admin_referer('gestor_api_revoke_one');
        $sessao_id = (string) ($_GET['sessao_id'] ?? '');
        if ($sessao_id === '') {
            wp_die(esc_html__('sessao_id obrigatorio.', 'gestor-api'));
        }
        $tokens = new Token_Repository();
        $tokens->revoke($sessao_id);
        wp_safe_redirect(
            add_query_arg(
                ['page' => self::MENU_SLUG, 'revoked_one' => 1],
                admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Handler: cria usuario Gestor (form no admin page).
     */
    public static function handle_create_user(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Sem permissao.', 'gestor-api'));
        }
        check_admin_referer('gestor_api_create_user');

        $email = (string) ($_POST['email'] ?? '');
        $nome  = (string) ($_POST['nome'] ?? '');
        $senha = (string) ($_POST['senha'] ?? '');

        $redirect_args = ['page' => self::MENU_SLUG];
        try {
            $model = new Usuario();
            $id = $model->criar([
                'email' => $email,
                'nome'  => $nome,
                'senha' => $senha,
            ]);
            $redirect_args['gestor_api_ok'] = 1;
            $redirect_args['gestor_api_msg'] = sprintf('Usuario %s criado (id=%s)', $email, $id);
        } catch (\Throwable $e) {
            $redirect_args['gestor_api_ok'] = 0;
            $redirect_args['gestor_api_msg'] = 'Erro: ' . $e->getMessage();
        }
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
