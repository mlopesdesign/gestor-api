# Gestor API v0.1.4 — Autenticação via usuários nativos do WordPress

> **Tipo:** feature estrutural (refactor de auth)
> **Breaking change:** NÃO — usuários legados (`wp_gestor_usuarios`) continuam funcionando como fallback.
> **Requer ação do admin WP:** nenhuma, mas veja "Como migrar" abaixo.

---

## O QUE MUDOU

A autenticação do plugin agora usa os **usuários nativos do WordPress** (`wp_users`), em vez da tabela própria `wp_gestor_usuarios`.

- ✅ Login tenta WP primeiro (`get_user_by('email')` + `wp_check_password`)
- ✅ Capability `gestor_api_use` controla quem pode usar a API (desktop/app mobile)
- ✅ Capability `gestor_api_manage` continua controlando quem administra o plugin
- ✅ `administrator` role do WP ganha `gestor_api_use` automaticamente
- ✅ Fallback pra tabela legada `wp_gestor_usuarios` (mantida por compatibilidade)
- ✅ LGPD (apagar conta) funciona pros 2 sistemas

## Por que essa mudança

Antes, o user do Gestor tinha que ser criado pelo admin do WP via menu "Gestor API → Criar usuário" ou WP-CLI. Não dava pra usar a mesma senha do WP, e "Esqueci minha senha" nativo do WP não funcionava.

Agora:
- O user do WP (já existente) **automaticamente** pode logar no app mobile e no desktop
- A senha é a mesma do WP (esqueceu? Usa `wp-login.php?action=lostpassword`)
- Pra criar user com acesso SÓ à API (sem outros poderes WP), basta criar WP user comum + dar a cap `gestor_api_use`

## Como migrar (admin WP)

**Se você já tinha um user legado na `wp_gestor_usuarios`:** ele continua funcionando, sem ação necessária. O login tenta WP primeiro, depois cai no legado.

**Se você quer usar o novo fluxo (recomendado):**
1. Vá em **Users → Add New** no admin WP
2. Crie o user com email + senha
3. Em **Role**, escolha o que quiser (Subscriber é o mais restrito, Administrator o mais amplo)
4. Se escolheu Subscriber ou outro role não-admin, edite o user e adicione a cap `gestor_api_use` (via plugin como Members/USER Role Editor, ou via `wp user add-cap <id> gestor_api_use` no WP-CLI)
5. Pronto. Esse user já consegue logar no app mobile e na sincronização do desktop

**Se você quer dar a cap a um user existente:** edite o user e adicione `gestor_api_use` no campo de capabilities (ou via plugin).

## Schema

Nenhuma migração de banco necessária. A tabela `wp_gestor_usuarios` continua existindo (deprecated). Usuários legados continuam logando normalmente.

## DTO novo

O objeto `usuario` retornado por `/auth/login`, `/auth/me` e `/auth/refresh` ganhou um campo novo:

```json
{
  "id": "2",
  "email": "marcio@...",
  "nome": "Marcio Lopes",
  "fuso": "America/Sao_Paulo",
  "...": "...",
  "origem": "wp"   // NOVO: "wp" (nativo) ou "legacy" (tabela antiga)
}
```

O campo `id` para users WP é o `wp_users.ID` (inteiro, em string). Para users legados, é o ULID da tabela antiga.

## Capability nova: `gestor_api_use`

Quem tem essa cap pode logar na API. Roles padrão do WP:
- `administrator` — tem (via `install_capabilities()` na ativação)
- `editor`, `author`, `contributor`, `subscriber` — **NÃO** tem (precisa adicionar manualmente)

Pra adicionar manualmente:
```php
$user = new \WP_User($user_id);
$user->add_cap('gestor_api_use');
```

Ou via WP-CLI:
```bash
wp user add-cap <user_id_or_login> gestor_api_use
```

## Como instalar

1. Baixe `gestor-api-0.1.4.zip` (abaixo)
2. No admin WP, **Plugins → Add New → Upload Plugin**
3. Faça upload do ZIP e clique **Install Now**
4. Clique **Replace existing** quando perguntar (se já tem v0.1.3)
5. Clique **Activate Plugin**
6. Pronto. Não precisa desativar antes.

## Como testar

```bash
# Login com seu user admin WP existente
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"seu-email@admin.com","senha":"SUA_SENHA_DO_WP"}'
```

Se der 200 com token, é WP nativo. Se der 401, confira a senha.

Para testar com user legado (se você criou um antes):
```bash
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"legacy@gestor.local","senha":"SENHA_LEGADA"}'
```

## Compatibilidade

- ✅ Desktop Gestor v0.2.24+: funciona (sync já fala o mesmo protocolo)
- ✅ Android v0.1.0+: funciona (não precisa de mudança)
- ✅ WordPress 6.0+ (testado em 6.6)
- ✅ PHP 8.0+
- ✅ MySQL 5.7+ / MariaDB 10.3+

## Próximos passos

- v0.1.5: tela "Minha conta" no desktop + Android (editar nome, trocar senha local)
- v0.1.6: sistema de licença/serial por user (preparado pra suportar plano free/pro)

## Arquivos modificados (6)

| Arquivo | Mudança |
|---|---|
| `gestor-api.php` | bump v0.1.3 → v0.1.4 |
| `readme.txt` | changelog + bump |
| `includes/class-activator.php` | adiciona cap `gestor_api_use` no role administrator |
| `includes/auth/class-auth-service.php` | login() e me() com WP-first + fallback legado + novos helpers (`find_wp_user_by_email`, `wp_user_to_dto`) |
| `includes/models/class-usuario.php` | `apagar_conta()` trata users WP (user meta + email limpo + revoga sessoes) |
| `includes/admin/class-admin-page.php` | form "Criar usuário" agora cria WP user com cap + nota explicativa no topo da página |
| `tests/test-auth.php` | +7 testes: login WP ok, senha errada, sem cap, admin pode logar, legado ainda funciona, me() WP, LGPD WP |

---

*Para suporte: mlopesdesign@gmail.com*
*Plugin: https://tools.mlopesdesign.com.br*
