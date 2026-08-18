<?php
/**
 * Uninstall handler — remove tabelas, capabilities, options, transients.
 *
 * So roda quando o Marcio desinstalar pelo admin WP (nao em simples deactivation).
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.NonceVerification.Recommended
// (uninstall nao passa por nonce.)

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix . 'gestor_';

$tables = [
    'auditoria',
    'sync_conflitos',
    'sync_cursores',
    'subtarefas',
    'tarefas',
    'projetos',
    'clientes',
    'areas',
    'sessoes',
    'usuarios',
];

foreach ($tables as $t) {
    $wpdb->query("DROP TABLE IF EXISTS `{$prefix}{$t}`");
}

$wpdb->query("DROP TRIGGER IF EXISTS `{$prefix}trg_auditoria_no_delete`");
$wpdb->query("DROP TRIGGER IF EXISTS `{$prefix}trg_auditoria_no_update`");

delete_option('gestor_api_db_version');
delete_option('gestor_api_db_schema');

// Limpa transients de rate limit.
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gestor_api_rl_%' OR option_name LIKE '_transient_timeout_gestor_api_rl_%'"
);

// Remove capabilities.
$caps = [
    'gestor_api_manage',
    'gestor_api_view_users',
    'gestor_api_revoke_tokens',
];
global $wp_roles;
if (isset($wp_roles) && is_object($wp_roles)) {
    foreach ($wp_roles->role_objects as $role) {
        foreach ($caps as $cap) {
            $role->remove_cap($cap);
        }
    }
}
