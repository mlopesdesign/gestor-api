# Gestor API — Plugin WordPress

API REST central do **Gestor Inteligente de Demandas**, exposta como plugin WordPress. Consumida pelo app Android `com.mlopes.gestor`.

## Stack

- PHP 8.0+
- WordPress 6.x
- MySQL 5.7+ / MariaDB 10.3+
- WP REST API nativa (namespace `gestor/v1`)

**Custo: R$ 0,00** — sem certificado, sem hospedagem premium, sem Firebase.

## Identidade imutável

- Slug: `gestor-api`
- Prefixo de classes: `Gestor_Api\`
- Prefixo de tabelas: `wp_gestor_*`
- Prefixo de opções/functions: `gestor_api_`
- Namespace REST: `gestor/v1`
- URL de produção: `https://tools.mlopesdesign.com.br`

## Instalação

1. Baixe o ZIP `gestor-api.zip` da [última release](https://github.com/ml-lopes/gestor-api/releases/latest).
2. No admin WP: **Plugins → Adicionar novo → Enviar plugin → Escolher arquivo → Instalar agora**.
3. **Ative** o plugin. Ele cria as 10 tabelas `wp_gestor_*` automaticamente.
4. Acesse o menu lateral **Gestor API** para criar o primeiro usuário.
5. Configure o app Android para apontar para `https://<seu-site>/wp-json/gestor/v1/`.

## Endpoints

| Método | Rota | Auth | Descrição |
|---|---|---|---|
| `POST` | `/auth/login` | — | Login (email + senha) → token |
| `POST` | `/auth/refresh` | Bearer | Renovar token |
| `POST` | `/auth/logout` | Bearer | Encerrar sessão |
| `GET`  | `/auth/me` | Bearer | Dados do usuário logado |
| `GET`  | `/tarefas` | Bearer | Listar tarefas |
| `POST` | `/tarefas` | Bearer | Criar tarefa |
| `GET`  | `/tarefas/{id}` | Bearer | Detalhe |
| `PATCH`| `/tarefas/{id}` | Bearer | Editar |
| `DELETE`| `/tarefas/{id}` | Bearer | Excluir (soft) |
| `GET`  | `/tarefas/hoje` | Bearer | Tarefas do dia |
| `GET`  | `/tarefas/atrasadas` | Bearer | Tarefas atrasadas |
| `GET`  | `/projetos` | Bearer | Listar projetos |
| `POST` | `/projetos` | Bearer | Criar projeto |
| `GET`  | `/clientes` | Bearer | Listar clientes |
| `POST` | `/clientes` | Bearer | Criar cliente |
| `GET`  | `/areas` | Bearer | Listar áreas |
| `POST` | `/areas` | Bearer | Criar área |
| `GET`  | `/sync/pull?since=ISO&dispositivo_id=X` | Bearer | Pull delta |
| `POST` | `/sync/push` | Bearer | Push batch de mutations |
| `POST` | `/admin/usuarios` | `manage_options` | Criar usuário (admin) |
| `GET`  | `/admin/usuarios` | `manage_options` | Listar usuários (admin) |

## Documentação

- [`AGENTS.md`](./AGENTS.md) — governança do plugin (stack, identidade, regras)
- [`docs/GUIA-API.md`](./gestor-api/docs/GUIA-API.md) — guia completo da API
- [`gestor-api/CHANGELOG`](./gestor-api/readme.txt) — changelog

## Licença

Proprietária — ML Lopes Design.
