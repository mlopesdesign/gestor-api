# Guia da API REST — Gestor API v0.1.3

> Plugin WP que expoe a API REST do Gestor Inteligente de Demandas.
> URL base: `https://tools.mlopesdesign.com.br/wp-json/gestor/v1/`
> Conteudo: este guia detalha todos os endpoints, payloads, respostas e exemplos curl.

---

## 1. Autenticacao

Todos os endpoints (exceto `POST /auth/login`) exigem header:

```
Authorization: Bearer <token>
```

O token e opaco, retornado pelo login. Validade: 30 dias. Renovavel via `/auth/refresh`.

### 1.1 `POST /auth/login`

Autentica o usuario e cria uma sessao.

**Request:**
```bash
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "marcio@gestor.local",
    "senha": "Ml@2026gestor",
    "dispositivo_id": "android-abc123",
    "sistema": "ANDROID",
    "app_versao": "0.1.3"
  }'
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "token": "a1b2c3d4...",
    "expira_em": "2026-09-16T00:00:00.000Z",
    "usuario": {
      "id": "01JABC...",
      "email": "marcio@gestor.local",
      "nome": "Marcio Lopes",
      "fuso": "America/Sao_Paulo",
      ...
    }
  }
}
```

**Response 401 (credenciais invalidas):**
```json
{
  "code": "credenciais_invalidas",
  "message": "Email ou senha incorretos",
  "data": { "status": 401 }
}
```

**Response 429 (rate limit):**
```json
{
  "code": "rate_limit",
  "message": "Limite de tentativas excedido. Tente em 15 minutos.",
  "data": { "status": 429 }
}
```

### 1.2 `POST /auth/refresh`

Renova o token atual (revoga o anterior).

**Request:**
```bash
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/auth/refresh \
  -H "Authorization: Bearer <token-atual>"
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "token": "novo-token...",
    "expira_em": "2026-09-16T00:00:00.000Z"
  }
}
```

### 1.3 `POST /auth/logout`

Revoga o token atual.

**Request:**
```bash
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/auth/logout \
  -H "Authorization: Bearer <token>"
```

**Response 204:** sem conteudo.

### 1.4 `GET /auth/me`

Retorna dados do usuario logado.

**Request:**
```bash
curl https://tools.mlopesdesign.com.br/wp-json/gestor/v1/auth/me \
  -H "Authorization: Bearer <token>"
```

---

## 2. CRUD — padrao comum

Todas as 4 entidades (tarefas, projetos, clientes, areas) seguem o mesmo padrao de endpoints.

### 2.1 `GET /<entidade>`

Lista todos os registros do usuario (nao soft-deleted).

**Exemplo:**
```bash
curl https://tools.mlopesdesign.com.br/wp-json/gestor/v1/tarefas \
  -H "Authorization: Bearer <token>"
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "items": [ /* ... */ ],
    "total": 42
  }
}
```

### 2.2 `GET /<entidade>/<id>`

Retorna um registro especifico.

### 2.3 `POST /<entidade>`

Cria novo registro. Se `id` nao for enviado, gera ULID automaticamente.

**Exemplo (criar tarefa):**
```bash
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/tarefas \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "Implementar feature X",
    "descricao": "Descricao detalhada",
    "prioridade": "ALTA",
    "vencimento_em": "2026-08-30T18:00:00.000Z"
  }'
```

**Response 201:** objeto criado.

### 2.4 `PUT /<entidade>/<id>`

Atualiza registro. O campo `versao_base` deve ser igual a `versao` atual — caso contrario retorna 409 (conflito).

**Exemplo:**
```bash
curl -X PUT https://tools.mlopesdesign.com.br/wp-json/gestor/v1/tarefas/01JABC... \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "titulo": "Titulo atualizado",
    "versao_base": 1
  }'
```

**Response 200:** objeto atualizado. `versao` foi incrementada pra 2.

**Response 409 (conflito de versao):**
```json
{
  "code": "conflito_versao",
  "message": "Versao base 1 nao bate com versao atual 3",
  "data": { "status": 409 }
}
```

### 2.5 `DELETE /<entidade>/<id>`

Soft-delete: marca `deletado_em`. O registro nao aparece mais em listagens mas pode ser recuperado via sync.

**Response 204:** sem conteudo.

---

## 3. Endpoints especiais de Tarefas

### 3.1 `GET /tarefas/hoje`

Tarefas com `vencimento_em` = hoje.

### 3.2 `GET /tarefas/atrasadas`

Tarefas com `vencimento_em < now AND status NOT IN (CONCLUIDA, CANCELADA, ARQUIVADA)`.

### 3.3 `GET /tarefas/projeto/<id>`

Tarefas do projeto `<id>`.

### 3.4 `GET /tarefas/cliente/<id>`

Tarefas do cliente `<id>`.

### 3.5 `POST /tarefas/<id>/concluir`

Marca tarefa como CONCLUIDA. Body opcional: `{"confirmada": true}`.

### 3.6 `POST /tarefas/<id>/reabrir`

Reabre tarefa concluida (status volta pra EM_ANDAMENTO, `concluida_em` vai pra NULL).

---

## 4. Sync (delta incremental)

### 4.1 `GET /sync/pull`

Retorna deltas desde o cursor.

**Parametros:**
- `dispositivo_id` (obrigatorio) — identifica o dispositivo
- `since` (opcional) — ISO 8601. Vazio = epoca
- `limit` (opcional) — padrao 200, max 1000
- `offset` (opcional) — padrao 0

**Request:**
```bash
curl "https://tools.mlopesdesign.com.br/wp-json/gestor/v1/sync/pull?dispositivo_id=android-abc&since=2026-08-01T00:00:00Z&limit=100" \
  -H "Authorization: Bearer <token>"
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "mudancas": [
      {
        "tabela": "tarefas",
        "operacao": "UPSERT",
        "registro_id": "01JABC...",
        "payload": { "id": "01JABC...", "titulo": "...", ... },
        "versao": 2,
        "atualizado_em": "2026-08-15T10:00:00.000Z"
      },
      {
        "tabela": "tarefas",
        "operacao": "DELETE",
        "registro_id": "01JDEF...",
        "payload": { "id": "01JDEF..." },
        "versao": 5,
        "atualizado_em": "2026-08-15T11:00:00.000Z"
      }
    ],
    "next_cursor": "2026-08-15T11:00:00.000Z",
    "has_more": false,
    "server_time": "2026-08-17T12:00:00.000Z"
  }
}
```

### 4.2 `POST /sync/push`

Envia batch de mutacoes locais.

**Request:**
```bash
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/sync/push \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "dispositivo_id": "android-abc",
    "mutacoes": [
      {
        "tabela": "tarefas",
        "operacao": "UPSERT",
        "registro_id": "01JABC...",
        "versao_base": 2,
        "payload": { "titulo": "Atualizado offline" }
      }
    ]
  }'
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "aplicadas": 1,
    "conflitos": [],
    "server_time": "2026-08-17T12:00:00.000Z"
  }
}
```

Em caso de conflito (versao_base < versao_servidor):
```json
{
  "success": true,
  "data": {
    "aplicadas": 0,
    "conflitos": [
      {
        "tabela": "tarefas",
        "registro_id": "01JABC...",
        "versao_servidor": 5,
        "versao_cliente": 2,
        "payload_servidor": { ... },
        "estado": "PENDENTE"
      }
    ],
    "server_time": "2026-08-17T12:00:00.000Z"
  }
}
```

### 4.3 `GET /sync/conflitos`

Lista conflitos pendentes do usuario.

### 4.4 `POST /sync/conflitos/<id>/resolver`

Resolve um conflito.

**Body:**
```json
{
  "escolha": "MINE" | "THEIRS" | "MERGE",
  "payload_merged": { ... }  // obrigatorio se escolha = MERGE
}
```

---

## 5. Erros (padrao WP REST)

Codigos HTTP:
- `200` — sucesso
- `201` — criado
- `204` — sem conteudo (delete / logout)
- `400` — validacao
- `401` — sem token / token invalido / expirado
- `403` — sem permissao (conflito de outro user, etc)
- `404` — nao encontrado
- `409` — conflito de versao
- `429` — rate limit
- `500` — erro interno

Formato:
```json
{
  "code": "slug_do_erro",
  "message": "Mensagem legivel",
  "data": { "status": 400 }
}
```

---

## 6. Exemplos completos (cURL)

### Login + criar tarefa + listar
```bash
# 1. Login
TOKEN=$(curl -s -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"marcio@gestor.local","senha":"Ml@2026gestor"}' \
  | python -c "import sys,json; print(json.load(sys.stdin)['data']['token'])")

# 2. Criar tarefa
curl -X POST https://tools.mlopesdesign.com.br/wp-json/gestor/v1/tarefas \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Minha primeira tarefa","prioridade":"ALTA"}'

# 3. Listar
curl https://tools.mlopesdesign.com.br/wp-json/gestor/v1/tarefas \
  -H "Authorization: Bearer $TOKEN"
```

---

## 7. Painel admin WP

Em `Tools > Gestor API` (requer capability `gestor_api_manage`):
- Lista de usuarios
- Sessoes ativas por usuario
- Botao "Revogar todos os tokens" por usuario
- Botao "Revogar" por sessao individual

---

*ML Lopes Design · Marcio · mlopesdesign@gmail.com · mlopesdesign.com.br*
*Versao 0.1.3 · 2026-08-18 · Stack: PHP 8 + WordPress 6 + MySQL · Custo R$ 0,00*
