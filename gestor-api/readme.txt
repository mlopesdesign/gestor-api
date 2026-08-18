=== Gestor API ===
Contributors: mlopesdesign
Tags: rest-api, gestor, sync
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 0.1.1
License: Proprietary
License URI: https://mlopesdesign.com.br

API REST central do Gestor Inteligente de Demandas. Consumida pelo app Android.

== Description ==

Plugin WordPress que expoe a API REST usada pelo app Android do Gestor Inteligente de Demandas.

Funcionalidades:
* Autenticacao via email + senha (token opaco, 30 dias, renovavel)
* CRUD completo de tarefas, projetos, clientes e areas
* Endpoints especiais: /tarefas/hoje, /tarefas/atrasadas, /tarefas/projeto/{id}, /tarefas/cliente/{id}
* Sincronizacao bidirecional via /sync/pull e /sync/push com deteccao de conflitos
* Resolucao de conflitos (MINE / THEIRS / MERGE)
* Auditoria append-only com trigger MySQL (nao permite DELETE/UPDATE)
* Rate limit em /auth/login (5 tentativas / 15min / IP)
* Soft-delete em todas as entidades (preserva historico de sync)
* Painel admin WP para gerenciar usuarios e revogar tokens

Stack:
* PHP 8.0+ (sem dependencias de producao)
* WordPress 6.x
* MySQL 5.7+ / MariaDB 10.3+
* WP REST API nativa (namespace `gestor/v1`)

Custo: R$ 0,00 (sem certificado, sem hospedagem premium, sem Firebase).

== Installation ==

1. Faca upload do ZIP `gestor-api.zip` no admin WP (Plugins > Adicionar novo > Upload).
2. Ative o plugin.
3. O plugin cria as tabelas `wp_gestor_*` automaticamente.
4. Crie o primeiro usuario via WP-CLI:
   wp eval '
     $u = new Gestor_Api\Models\Usuario();
     $id = $u->criar([
       "email" => "marcio@gestor.local",
       "senha" => "Ml@2026gestor",
       "nome"  => "Marcio Lopes",
     ]);
     echo "Usuario criado: " . $id . PHP_EOL;
   '
5. Configure o app Android para apontar para `https://tools.mlopesdesign.com.br/wp-json/gestor/v1/`.

== Frequently Asked Questions ==

= O plugin funciona sem o app Android? =

Sim. O plugin expoe a API REST; qualquer cliente HTTP pode consumir.

= O que acontece se o MySQL nao tiver suporte a JSON? =

O plugin usa colunas LONGTEXT para campos JSON. Funciona em qualquer MySQL 5.6+.

= Os dados do Gestor desktop vao pra ca? =

Nao nesta versao. O sync com o desktop esta BLOQUEADO ate Marcio liberar (ver `AGENTS.md` raiz §9.1).

== Changelog ==

= 0.1.1 = 2026-08-18 =
* FIX: 500 em /wp-json/gestor/v1/ por causa de `const NAMESPACE = GESTOR_API_NAMESPACE` (constante global em const de classe quebra em PHP 8.0)
* FIX: 500 em /wp-json/gestor/v1/ por causa de `const CURRENT_VERSION = GESTOR_API_VERSION` (mesmo motivo)
* FIX: autoload PSR-4 nao encontrava models (arquivos `class-model-*.php`, autoload procurava `class-*.php`) — renomeados para `class-area.php`, `class-cliente.php`, `class-projeto.php`, `class-tarefa.php`, `class-usuario.php`
* UX: menu do plugin movido de "Ferramentas" para o menu lateral principal (com icone)
* NEW: form de admin em Tools > Gestor API para criar usuario inicial sem WP-CLI
* NEW: endpoint admin /admin/usuarios (POST/GET) protegido por `current_user_can('manage_options')`

= 0.1.0 = 2026-08-17 =
* Release inicial
* Schema MySQL: 10 tabelas (usuarios, sessoes, areas, clientes, projetos, tarefas, subtarefas, sync_cursores, sync_conflitos, auditoria)
* Triggers de auditoria append-only
* Auth: login, refresh, logout, me + rate limit 5/15min
* CRUD completo: /tarefas, /projetos, /clientes, /areas
* Endpoints especiais: /tarefas/hoje, /atrasadas, /projeto/{id}, /cliente/{id}, /concluir, /reabrir
* Sync pull/push com deteccao de conflito por versao
* Resolucao de conflitos: MINE / THEIRS / MERGE
* Painel admin WP para revogar tokens
* Suite PHPUnit (test-auth, test-tarefas-crud, test-sync-pull, test-sync-push, test-conflitos, test-permissoes, test-areas-crud, test-clientes-crud, test-projetos-crud)
* Documentacao em docs/GUIA-API.md

== Upgrade Notice ==

= 0.1.1 =
Bugfix de 500 fatal no namespace REST. Atualize imediatamente.

= 0.1.0 =
Primeira release publica.
