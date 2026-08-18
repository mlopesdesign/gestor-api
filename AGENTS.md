# AGENTS — Plugin WP `gestor-api`

> Plugin WordPress que expõe a **API REST central do Gestor Inteligente de Demandas**.
> Consumido por: app Android (futuro), Gestor desktop (futuro, fase de sync).
> Lido integralmente **antes** de qualquer alteração. Vinculante.

---

## 0. Relação com o AGENTS.md raiz

Este arquivo **complementa** `E:\Projetos\LOPES FOCUS\AGENTS.md` §9 (Projetos irmãos).
Em conflito, prevalece §9 + o briefing do Marcio no chat de origem (a session atual).
Em silêncio deste, vale a seção 9 do AGENTS.md raiz e o `wp-architect` system prompt.

**REGRA DE FERRO herdada do raiz (AGENTS.md §9.1):** **NÃO MEXER no Gestor desktop v0.2.22.**
O plugin WP conversa com **Android** agora. A fase de mexer no desktop vem depois que o Marcio liberar.

---

## 1. Identidade imutável do plugin

| Atributo | Valor | Observação |
|---|---|---|
| Slug do plugin | `gestor-api` | Nome da pasta = slug. Preservado em updates |
| Versão atual | `0.1.0` | SemVer (MAJOR.MINOR.PATCH). Bump em toda release |
| Prefixo de função | `gestor_api_` | Funções, classes, opções, transients, capabilities |
| Prefixo de constante | `GESTOR_API_` | Constantes PHP |
| Prefixo de tabela | `wp_gestor_` | Custom tables, não toca em `wp_*` nativas |
| Prefixo de capability | `gestor_api_` | Custom caps pra roles (admin, operador) |
| Namespace REST | `gestor/v1` | `/wp-json/gestor/v1/...` |
| Domínio i18n | `gestor-api` | Text domain, idêntico ao slug |
| URL de produção | `https://tools.mlopesdesign.com.br` | Em construção, mas usável |
| Banco de produção | MySQL do WP do Marcio | Reusa o MySQL do site, sem banco separado |

> Mudou qualquer um destes → quebra update, ZIP no padrão WP, contrato com Android, sync com desktop.
> Conferir antes de cada release.

---

## 2. Stack do plugin (fixa, sem negociação)

| Camada | Tecnologia | Versão | Justificativa |
|---|---|---|---|
| Linguagem | **PHP** | 8.0+ (typed properties, match, nullsafe) | Padrão WP moderno, hosted no WP do Marcio |
| Framework | **WordPress** | 6.x | Já está rodando no `tools.mlopesdesign.com.br` |
| API REST | **WP REST API nativa** | do core | Sem dependência extra, namespace custom `gestor/v1` |
| Banco | **MySQL/MariaDB** | 5.7+ / 10.3+ | Já existe no WP do Marcio. Usar `$wpdb->prefix . 'gestor_'` |
| Auth | **Custom (email + senha) → token** | — | `password_hash`/`password_verify` (PHP nativo), token random 32 bytes, hash no banco |
| Testes | **PHPUnit + WP test framework** | 9.x + latest | Plugin Test Boilerplate, WP-CLI `wp scaffold plugin-tests` |
| Build/ZIP | **Nenhum** (PHP não compila) | — | `zip -r gestor-api.zip gestor-api/` igual wp-architect já faz |
| Hospedagem | **VPS do Marcio** (tools.mlopesdesign.com.br) | — | Custo zero, já existe |
| Cert SSL | **Let's Encrypt** (já configurado no VPS) | — | Custo zero |

### 2.1 PROIBIDO neste plugin

- Frameworks PHP (Laravel, Symfony, Slim) — usar só o WP
- Composer dependencies em produção (só devDependencies, em `composer.json` com `--no-dev` no install)
- Bancos separados (Redis, MongoDB) — só MySQL do WP
- JWT libs externas (`firebase/php-jwt`) — JWT é exagero, token opaco + DB basta
- Firebase / Supabase / serviços externos pagos
- Hospedagem premium / serverless / Lambda
- Certificado de assinatura de código pago (não precisa pra plugin WP)

---

## 3. Estrutura de arquivos (alvo)

```
E:\Projetos\LOPES FOCUS\wp-api\
├── AGENTS.md                                      ← este arquivo
├── README.md                                      ← visão geral + como instalar
├── composer.json                                  ← SÓ devDependencies (PHPUnit)
├── composer.lock                                  ← gerado
├── .gitignore
├── gestor-api/                                    ← ZIP = essa pasta
│   ├── gestor-api.php                             ← bootstrap, activation hook, autoload
│   ├── uninstall.php                              ← remove tabelas + capabilities
│   ├── readme.txt                                 ← changelog WP format
│   ├── includes/
│   │   ├── class-gestor-api.php                   ← singleton principal
│   │   ├── class-gestor-api-activator.php         ← activation hook
│   │   ├── class-gestor-api-deactivator.php       ← deactivation hook
│   │   ├── auth/
│   │   │   ├── class-auth-service.php             ← login, refresh, revoke
│   │   │   └── class-token-repository.php         ← CRUD em `wp_gestor_sessoes`
│   │   ├── db/
│   │   │   ├── class-schema.php                   ← criação/migração das tabelas
│   │   │   └── class-migrations.php               ← versão + delta
│   │   ├── rest/
│   │   │   ├── class-rest-controller.php          ← base
│   │   │   ├── class-rest-auth-controller.php     ← POST /auth/login, /auth/refresh, /auth/logout
│   │   │   ├── class-rest-tarefas-controller.php  ← CRUD /tarefas
│   │   │   ├── class-rest-projetos-controller.php ← CRUD /projetos
│   │   │   ├── class-rest-clientes-controller.php ← CRUD /clientes
│   │   │   ├── class-rest-areas-controller.php    ← CRUD /areas
│   │   │   └── class-rest-sync-controller.php     ← GET /sync/pull, POST /sync/push
│   │   ├── sync/
│   │   │   ├── class-sync-pull.php                ← desde cursor
│   │   │   ├── class-sync-push.php                ← batch de mutações
│   │   │   └── class-sync-conflict-resolver.php   ← last-write-wins por versao
│   │   ├── models/
│   │   │   ├── class-model-tarefa.php
│   │   │   ├── class-model-projeto.php
│   │   │   ├── class-model-cliente.php
│   │   │   ├── class-model-area.php
│   │   │   └── class-model-usuario.php
│   │   ├── util/
│   │   │   ├── class-ulid.php                     ← port da lib ULID do Gestor
│   │   │   ├── class-response.php                 ← helpers REST response/error
│   │   │   ├── class-validator.php                ← sanitização e validação
│   │   │   └── class-logger.php                   ← error_log estruturado
│   │   └── admin/
│   │       └── class-admin-page.php               ← tela WP admin (listar usuários, revogar tokens, ver logs)
│   ├── tests/
│   │   ├── bootstrap.php
│   │   ├── test-auth.php
│   │   ├── test-tarefas-crud.php
│   │   ├── test-sync-pull.php
│   │   ├── test-sync-push.php
│   │   └── test-conflitos.php
│   ├── docs/
│   │   ├── GUIA-API.md                            ← endpoints, payloads, exemplos curl
│   │   └── GUIA-API.pdf
│   └── languages/
│       └── gestor-api.pot
```

---

## 4. Banco de dados MySQL (espelho do `schema.sql` do Gestor)

> **REGRA: cada tabela do Gestor vira uma tabela `wp_gestor_*` aqui.**
> Espelho 1:1 em campos, tipos, defaults, constraints. IDs são ULID (string 26 chars).
> Datas são `DATETIME(3)` em UTC (Gestor usa ISO 8601 string; plugin converte).
> Soft-delete via `deletado_em` (Gestor usa tombstone + hard delete; aqui soft-delete pra preservar histórico de sync).

### 4.1 Tabelas (todas prefixadas com `$wpdb->prefix . 'gestor_'`)

```sql
-- Identidade -------------------------------------------------------------

CREATE TABLE wp_gestor_usuarios (
  id                    CHAR(26)     PRIMARY KEY,                       -- ULID
  email                 VARCHAR(255) NOT NULL UNIQUE,
  senha_hash            VARCHAR(255) NOT NULL,                          -- password_hash()
  nome                  VARCHAR(255) NOT NULL,
  fuso                  VARCHAR(64)  NOT NULL DEFAULT 'America/Sao_Paulo',
  horario_trab_inicio   VARCHAR(5)   NOT NULL DEFAULT '08:00',
  horario_trab_fim      VARCHAR(5)   NOT NULL DEFAULT '18:00',
  dias_trabalho_json    TEXT         NOT NULL,
  tom_cobranca          ENUM('PROFISSIONAL','FIRME','GENTIL') NOT NULL DEFAULT 'PROFISSIONAL',
  ia_habilitada         TINYINT(1)   NOT NULL DEFAULT 1,
  ia_consentimento_em   DATETIME(3)  NULL,
  conta_apagada_em      DATETIME(3)  NULL,
  criado_em             DATETIME(3)  NOT NULL,
  atualizado_em         DATETIME(3)  NOT NULL,
  versao                INT          NOT NULL DEFAULT 1,
  wp_user_id            BIGINT(20) UNSIGNED NULL,                       -- link opcional pro wp_users (pra capability check)
  INDEX idx_email (email),
  INDEX idx_apagada (conta_apagada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_gestor_sessoes (
  id              CHAR(26)     PRIMARY KEY,                             -- ULID
  usuario_id      CHAR(26)     NOT NULL,
  token_hash      CHAR(64)     NOT NULL UNIQUE,                         -- SHA-256 do token
  criada_em       DATETIME(3)  NOT NULL,
  expira_em       DATETIME(3)  NOT NULL,
  revogada_em     DATETIME(3)  NULL,
  dispositivo_id  VARCHAR(64)  NULL,
  ip_criacao      VARCHAR(45)  NULL,
  user_agent      VARCHAR(255) NULL,
  FOREIGN KEY (usuario_id) REFERENCES wp_gestor_usuarios(id) ON DELETE CASCADE,
  INDEX idx_usuario (usuario_id),
  INDEX idx_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Áreas, clientes, projetos ---------------------------------------------

CREATE TABLE wp_gestor_areas (
  id            CHAR(26)    PRIMARY KEY,
  usuario_id    CHAR(26)    NOT NULL,
  nome          VARCHAR(120) NOT NULL,
  cor           CHAR(7)     NOT NULL DEFAULT '#888888',
  ordem         INT         NOT NULL DEFAULT 0,
  criado_em     DATETIME(3) NOT NULL,
  atualizado_em DATETIME(3) NOT NULL,
  versao        INT         NOT NULL DEFAULT 1,
  deletado_em   DATETIME(3) NULL,
  FOREIGN KEY (usuario_id) REFERENCES wp_gestor_usuarios(id) ON DELETE CASCADE,
  UNIQUE KEY uq_usuario_nome (usuario_id, nome),
  INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_gestor_clientes (
  id              CHAR(26)     PRIMARY KEY,
  usuario_id      CHAR(26)     NOT NULL,
  nome            VARCHAR(255) NOT NULL,
  organizacao     VARCHAR(255) NULL,
  contatos_json   JSON         NOT NULL,
  observacoes     TEXT         NULL,
  status          ENUM('ATIVO','INATIVO','ARQUIVADO') NOT NULL DEFAULT 'ATIVO',
  criado_em       DATETIME(3)  NOT NULL,
  atualizado_em   DATETIME(3)  NOT NULL,
  versao          INT          NOT NULL DEFAULT 1,
  deletado_em     DATETIME(3)  NULL,
  FOREIGN KEY (usuario_id) REFERENCES wp_gestor_usuarios(id) ON DELETE CASCADE,
  INDEX idx_usuario (usuario_id),
  INDEX idx_status (usuario_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_gestor_projetos (
  id                  CHAR(26)     PRIMARY KEY,
  usuario_id          CHAR(26)     NOT NULL,
  titulo              VARCHAR(255) NOT NULL,
  descricao           TEXT         NULL,
  cliente_id          CHAR(26)     NULL,
  area_id             CHAR(26)     NULL,
  status              ENUM('PLANEJADO','EM_ANDAMENTO','PAUSADO','CONCLUIDO','CANCELADO','ARQUIVADO') NOT NULL DEFAULT 'PLANEJADO',
  prioridade          ENUM('BAIXA','NORMAL','ALTA','URGENTE','CRITICA') NOT NULL DEFAULT 'NORMAL',
  inicio_em           DATETIME(3)  NULL,
  fim_em              DATETIME(3)  NULL,
  progresso_calc      DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  participantes_json  JSON         NOT NULL,
  criado_em           DATETIME(3)  NOT NULL,
  atualizado_em       DATETIME(3)  NOT NULL,
  versao              INT          NOT NULL DEFAULT 1,
  deletado_em         DATETIME(3)  NULL,
  FOREIGN KEY (usuario_id) REFERENCES wp_gestor_usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (cliente_id) REFERENCES wp_gestor_clientes(id) ON DELETE SET NULL,
  FOREIGN KEY (area_id)    REFERENCES wp_gestor_areas(id)    ON DELETE SET NULL,
  INDEX idx_usuario (usuario_id),
  INDEX idx_status (usuario_id, status),
  INDEX idx_cliente (cliente_id),
  INDEX idx_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tarefas --------------------------------------------------------------

CREATE TABLE wp_gestor_tarefas (
  id                        CHAR(26)     PRIMARY KEY,
  usuario_id                CHAR(26)     NOT NULL,
  titulo                    VARCHAR(200) NOT NULL,
  descricao                 TEXT         NULL,
  area_id                   CHAR(26)     NULL,
  projeto_id                CHAR(26)     NULL,
  cliente_id                CHAR(26)     NULL,
  status                    ENUM('CAIXA_ENTRADA','PLANEJADA','EM_ANDAMENTO','AGUARDANDO_TERCEIRO','BLOQUEADA','EM_REVISAO','ENTREGUE_AGUARDANDO_CONFIRMACAO','CONCLUIDA','ADIADA','CANCELADA','ARQUIVADA') NOT NULL DEFAULT 'CAIXA_ENTRADA',
  prioridade                ENUM('BAIXA','NORMAL','ALTA','URGENTE','CRITICA') NOT NULL DEFAULT 'NORMAL',
  nivel_cobranca            ENUM('DISCRETA','PERSISTENTE','INTENSIVA','CRITICA') NOT NULL DEFAULT 'PERSISTENTE',
  inicio_em                 DATETIME(3)  NULL,
  vencimento_em             DATETIME(3)  NULL,
  duracao_estimada_min      INT          NULL,
  duracao_realizada_min     INT          NOT NULL DEFAULT 0,
  recorrencia_json          JSON         NULL,
  recorrencia_tipo          VARCHAR(32)  NULL,
  recorrencia_data_base     DATETIME(3)  NULL,
  etiquetas_json            JSON         NOT NULL,
  responsavel               VARCHAR(255) NULL,
  origem                    ENUM('MANUAL','NL','IMPORTADA','EMAIL','OUTRO') NOT NULL DEFAULT 'MANUAL',
  concluida_em              DATETIME(3)  NULL,
  entregue_em               DATETIME(3)  NULL,
  confirmada_em             DATETIME(3)  NULL,
  motivo_cancelamento       TEXT         NULL,
  motivo_adiamento          TEXT         NULL,
  cancelada_em              DATETIME(3)  NULL,
  cancelada_motivo          TEXT         NULL,
  adiada_ate                DATETIME(3)  NULL,
  adiada_motivo             TEXT         NULL,
  criado_em                 DATETIME(3)  NOT NULL,
  atualizado_em             DATETIME(3)  NOT NULL,
  versao                    INT          NOT NULL DEFAULT 1,
  deletado_em               DATETIME(3)  NULL,
  FOREIGN KEY (usuario_id) REFERENCES wp_gestor_usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (area_id)    REFERENCES wp_gestor_areas(id)    ON DELETE SET NULL,
  FOREIGN KEY (projeto_id) REFERENCES wp_gestor_projetos(id) ON DELETE SET NULL,
  FOREIGN KEY (cliente_id) REFERENCES wp_gestor_clientes(id) ON DELETE SET NULL,
  INDEX idx_usuario (usuario_id),
  INDEX idx_status (usuario_id, status),
  INDEX idx_projeto (projeto_id),
  INDEX idx_area (area_id),
  INDEX idx_cliente (cliente_id),
  INDEX idx_vencimento (vencimento_em),
  INDEX idx_prioridade (usuario_id, prioridade),
  INDEX idx_atualizado (atualizado_em),
  INDEX idx_deletado (deletado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subtarefas ---------------------------------------------------------

CREATE TABLE wp_gestor_subtarefas (
  id            CHAR(26)     PRIMARY KEY,
  tarefa_id     CHAR(26)     NOT NULL,
  usuario_id    CHAR(26)     NOT NULL,
  titulo        VARCHAR(255) NOT NULL,
  ordem         INT          NOT NULL,
  concluida_em  DATETIME(3)  NULL,
  criado_em     DATETIME(3)  NOT NULL,
  atualizado_em DATETIME(3)  NOT NULL,
  versao        INT          NOT NULL DEFAULT 1,
  deletado_em   DATETIME(3)  NULL,
  FOREIGN KEY (tarefa_id) REFERENCES wp_gestor_tarefas(id) ON DELETE CASCADE,
  UNIQUE KEY uq_tarefa_ordem (tarefa_id, ordem),
  INDEX idx_tarefa (tarefa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync ----------------------------------------------------------------

CREATE TABLE wp_gestor_sync_cursores (
  usuario_id      CHAR(26)     NOT NULL,
  dispositivo_id  VARCHAR(64)  NOT NULL,
  ultimo_id       BIGINT       NOT NULL DEFAULT 0,
  atualizado_em   DATETIME(3)  NOT NULL,
  PRIMARY KEY (usuario_id, dispositivo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wp_gestor_sync_conflitos (
  id                  BIGINT       PRIMARY KEY AUTO_INCREMENT,
  usuario_id          CHAR(26)     NOT NULL,
  tabela              VARCHAR(32)  NOT NULL,
  registro_id         CHAR(26)     NOT NULL,
  versao_servidor     INT          NOT NULL,
  versao_cliente_a    INT          NOT NULL,
  dispositivo_a_id    VARCHAR(64)  NOT NULL,
  payload_servidor    JSON         NOT NULL,
  payload_cliente_a   JSON         NOT NULL,
  estado              ENUM('PENDENTE','RESOLVIDO_MINE','RESOLVIDO_THEIRS','RESOLVIDO_MERGE','CANCELADO') NOT NULL DEFAULT 'PENDENTE',
  escolhido_por       CHAR(26)     NULL,
  escolhido_em        DATETIME(3)  NULL,
  diff_json           JSON         NULL,
  criado_em           DATETIME(3)  NOT NULL,
  INDEX idx_usuario_estado (usuario_id, estado, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoria (append-only) --------------------------------------------

CREATE TABLE wp_gestor_auditoria (
  id              BIGINT       PRIMARY KEY AUTO_INCREMENT,
  usuario_id      CHAR(26)     NOT NULL,
  entidade        VARCHAR(32)  NOT NULL,
  entidade_id     CHAR(26)     NOT NULL,
  acao            ENUM('CREATE','UPDATE','DELETE','LOGIN','LOGOUT','SYNC_PULL','SYNC_PUSH') NOT NULL,
  diff_json       JSON         NULL,
  dispositivo_id  VARCHAR(64)  NULL,
  em              DATETIME(3)  NOT NULL,
  INDEX idx_usuario_em (usuario_id, em),
  INDEX idx_entidade (entidade, entidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers de invariante ---------------------------------------------

DELIMITER $$
CREATE TRIGGER trg_gestor_auditoria_no_delete
BEFORE DELETE ON wp_gestor_auditoria
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'auditoria: append-only';
END$$
CREATE TRIGGER trg_gestor_auditoria_no_update
BEFORE UPDATE ON wp_gestor_auditoria
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'auditoria: append-only';
END$$
DELIMITER ;
```

### 4.2 Diferenças deliberadas em relação ao `schema.sql` do Gestor

| Diferença | Por quê |
|---|---|
| `ENUM` em vez de `CHECK` | MySQL não tem CHECK constraint confiável em todas as versões; ENUM é portável |
| `DATETIME(3)` em vez de TEXT ISO8601 | MySQL tem tipo nativo; conversão na camada PHP (UTC) |
| `JSON` em vez de TEXT (etiquetas, contatos, participantes) | MySQL 5.7+ valida; query fica mais fácil |
| `deletado_em` (soft-delete) em vez de `tombstones` | Plugin precisa preservar histórico pra sync; desktop faz hard delete |
| Sem `tarefas_ocorrencias`, `anexos`, `lembretes`, `cobranca_config`, `ia_telemetria` | Fora do escopo do MVP da API. Versões futuras |

---

## 5. Endpoints REST (`/wp-json/gestor/v1/...`)

> **Auth:** todos os endpoints exceto `POST /auth/login` exigem header `Authorization: Bearer <token>`.
> Token opaco (32 bytes random, base64url), armazenado como SHA-256 no banco. Validade 30 dias, renovável via `/auth/refresh`.

### 5.1 Autenticação

| Método | Path | Permissão | Body | Resposta |
|---|---|---|---|---|
| `POST` | `/auth/login` | público | `{ "email": "...", "senha": "...", "dispositivo_id": "...", "dispositivo_nome": "...", "sistema": "ANDROID", "app_versao": "0.1.0" }` | `200` `{ "token": "...", "expira_em": "ISO8601", "usuario": { ... } }` ou `401` |
| `POST` | `/auth/refresh` | autenticado | `{}` | `200` `{ "token": "novo", "expira_em": "ISO8601" }` (revoga o anterior) |
| `POST` | `/auth/logout` | autenticado | `{}` | `204` (revoga o token atual) |
| `GET`  | `/auth/me` | autenticado | — | `200` usuário logado |

### 5.2 CRUD genérico (todas as 4 entidades: tarefas, projetos, clientes, areas)

| Método | Path | Body | Resposta |
|---|---|---|---|
| `GET`    | `/<entidade>`              | — (query: `?since=ISO8601&limit=100&cursor=...&status=...&deleted=true`) | `200` `{ "items": [...], "next_cursor": "...", "has_more": bool }` |
| `GET`    | `/<entidade>/<id>`         | — | `200` objeto ou `404` |
| `POST`   | `/<entidade>`              | objeto novo (campos validados; ID opcional, se faltar gera ULID) | `201` objeto criado ou `400` validação |
| `PUT`    | `/<entidade>/<id>`         | objeto completo (substituição total, `versao` deve bater) | `200` objeto atualizado ou `409` conflito de versão |
| `PATCH`  | `/<entidade>/<id>`         | objeto parcial (merge; `versao` deve bater) | `200` objeto ou `409` |
| `DELETE` | `/<entidade>/<id>`         | — | `204` (soft-delete: marca `deletado_em`; sync propaga deleção) |

### 5.3 Endpoints especiais

| Método | Path | Body | Resposta |
|---|---|---|---|
| `GET`  | `/tarefas/hoje`            | — | `200` tarefas com vencimento = hoje, ordenadas por prioridade + hora |
| `GET`  | `/tarefas/atrasadas`       | — | `200` tarefas com `vencimento_em < now AND status NOT IN (CONCLUIDA, CANCELADA, ARQUIVADA)` |
| `GET`  | `/tarefas/projeto/<id>`    | — | `200` tarefas do projeto |
| `GET`  | `/tarefas/cliente/<id>`    | — | `200` tarefas do cliente |
| `POST` | `/tarefas/<id>/concluir`   | `{ "confirmada": bool }` | `200` tarefa com `concluida_em` setado |
| `POST` | `/tarefas/<id>/reabrir`    | — | `200` tarefa com `status` revertido pra `EM_ANDAMENTO` |
| `GET`  | `/busca?q=<texto>&entidades=tarefas,projetos,clientes` | — | `200` resultados agrupados |

### 5.4 Sync (delta incremental)

| Método | Path | Body | Resposta |
|---|---|---|---|
| `GET`  | `/sync/pull?since=<ISO8601>&dispositivo_id=<id>&limit=200` | — | `200` `{ "mudancas": [{ "tabela": "tarefas", "operacao": "UPSERT"|"DELETE", "registro_id": "ULID", "payload": {...}, "versao": N, "atualizado_em": "ISO8601" }], "next_cursor": "...", "server_time": "ISO8601" }` |
| `POST` | `/sync/push`               | `{ "dispositivo_id": "...", "mutacoes": [{ "tabela": "...", "operacao": "UPSERT"|"DELETE", "registro_id": "ULID", "versao_base": N (opcional, p/ conflito), "payload": {...} }] }` | `200` `{ "aplicadas": N, "conflitos": [{ "tabela": "...", "registro_id": "...", "versao_servidor": M, "payload_servidor": {...}, "estado": "PENDENTE" }] }` |
| `GET`  | `/sync/conflitos`          | — | `200` lista de conflitos pendentes do usuário |
| `POST` | `/sync/conflitos/<id>/resolver` | `{ "escolha": "MINE"\|"THEIRS"\|"MERGE", "payload_merged": {...} }` | `200` conflito resolvido |

### 5.5 Erros (padrão WP REST)

```json
{ "code": "tarefa_nao_encontrada", "message": "Tarefa ULID X não existe", "data": { "status": 404 } }
```

Códigos: `400` validação, `401` sem token, `403` token inválido/expirado, `404` não existe, `409` conflito de versão, `429` rate limit, `500` erro interno.

---

## 6. Autenticação — detalhes

### 6.1 Fluxo

```
Android                                          Plugin WP
  |                                                 |
  |-- POST /auth/login {email, senha} ------------>|
  |                                                 |-- password_verify()
  |                                                 |-- gera token (32 bytes random)
  |                                                 |-- INSERT wp_gestor_sessoes(token_hash, expira_em)
  |<-- 200 {token, expira_em, usuario} -------------|
  |                                                 |
  |-- GET /tarefas {Authorization: Bearer <token>}>|
  |                                                 |-- SHA-256(token) → busca sessoes
  |                                                 |-- valida expira_em > now()
  |                                                 |-- busca tarefas WHERE usuario_id = ? AND deletado_em IS NULL
  |<-- 200 {items: [...]} --------------------------|
```

### 6.2 Segurança

- `password_hash(PASSWORD_BCRYPT, ['cost' => 12])` (padrão PHP, sem lib externa)
- Token: `bin2hex(random_bytes(32))` (64 chars hex)
- Token armazenado: `hash('sha256', $token)` (nunca plain text)
- `Authorization: Bearer <token>` validado em **todo** endpoint exceto `/auth/login`
- HTTPS obrigatório (rejeitar HTTP em produção; `$_SERVER['HTTPS'] !== 'on' && WP_ENV === 'production'`)
- Rate limit em `/auth/login`: 5 tentativas / 15 min / IP (`transient`)
- `dispositivo_id` + `user_agent` registrados pra auditoria
- Sessão revogável: `POST /auth/logout` marca `revogada_em` (sessão fica mas não autentica mais)
- Senha mínima: 8 chars. Validação: 1 maiúscula, 1 minúscula, 1 número
- Tentativas de login falhadas registradas em `wp_gestor_auditoria` com acao=`LOGIN` (e diff `{"sucesso": false, "motivo": "senha_incorreta"}`)

### 6.3 Seed inicial (pra Marcio testar)

Via WP-CLI:
```bash
wp eval '
  $u = new Gestor_Api\Models\Usuario();
  $u->criar([
    "email" => "marcio@gestor.local",
    "senha" => "Ml@2026gestor",
    "nome"  => "Marcio Lopes",
  ]);
  echo "Usuário criado: " . $u->id . "\n";
'
```

OU via endpoint admin (com `manage_options` WP) `POST /gestor-api/v1/admin/usuarios` — só pra setup.

---

## 7. Sync — algoritmo

### 7.1 Pull (`GET /sync/pull`)

```
SELECT tabela, registro_id, operacao, versao, payload_json, atualizado_em
FROM (
  -- UPSERT: rows alteradas desde `since`
  SELECT 'tarefas' AS tabela, id AS registro_id, 'UPSERT' AS operacao, versao,
         JSON_OBJECT(...) AS payload_json, atualizado_em
  FROM wp_gestor_tarefas
  WHERE usuario_id = ? AND atualizado_em > ? AND deletado_em IS NULL

  UNION ALL

  -- DELETE: soft-deleted (pra propagar deleção)
  SELECT 'tarefas', id, 'DELETE', versao, JSON_OBJECT('id', id), deletado_em
  FROM wp_gestor_tarefas
  WHERE usuario_id = ? AND deletado_em > ?

  -- (mesmo pra projetos, clientes, areas)
)
ORDER BY atualizado_em ASC, id ASC
LIMIT ? OFFSET ?
```

Cursor: `ultimo_id` (BIGINT auto_increment) do registro mais recente. Cliente envia `?since=<ISO>` na primeira vez; servidor responde com `next_cursor` que o cliente usa na próxima.

### 7.2 Push (`POST /sync/push`)

Pra cada mutação do batch:
1. Se `operacao=UPSERT`:
   - Se registro não existe no servidor → INSERT
   - Se existe e `versao_servidor == versao_cliente - 1` → UPDATE + incrementar versao
   - Se existe e `versao_servidor > versao_cliente - 1` → CONFLITO, registra em `wp_gestor_sync_conflitos`
2. Se `operacao=DELETE`:
   - Soft-delete (`deletado_em = now()`) + incrementar versao
   - Se já soft-deleted, noop

Última escrita vence **por padrão**, mas conflito é registrado pra auditoria. Cliente pode ver depois em `GET /sync/conflitos` e resolver manualmente.

### 7.3 Last-Write-Wins + auditoria

Cada mutação (vinda do Android OU do desktop OU do admin) é registrada em `wp_gestor_auditoria` com `acao`, `diff_json`, `dispositivo_id`. Sem perda silenciosa: o conflito nunca é sobrescrito sem registro.

---

## 8. Ativação / Desativação / Desinstalação

### 8.1 `register_activation_hook`

```php
register_activation_hook(__FILE__, ['Gestor_Api\Activator', 'activate']);

class Gestor_Api\Activator {
  public static function activate() {
    // 1. Criar tabelas (idempotente)
    Gestor_Api\DB\Schema::instalar();
    // 2. Criar capabilities
    Gestor_Api\Admin::instalar_capabilities();
    // 3. Flush rewrite rules (pra REST routes funcionarem)
    flush_rewrite_rules();
    // 4. Log
    error_log('[gestor-api] Plugin ativado v' . GESTOR_API_VERSION);
  }
}
```

**SEMPRE try/catch** em volta de toda a lógica. Se falhar, exibir mensagem clara e `deactivate_plugins(__FILE__)`. Sem isso, ativação pode quebrar o site do Marcio.

### 8.2 `register_deactivation_hook`

Flush rewrite rules. NÃO remove tabelas (dados persistem entre desativa/reativa).

### 8.3 `uninstall.php`

Remove tabelas, capabilities, options, transients. **Só roda quando o Marcio desinstalar pelo admin WP** (não em simples deactivation).

---

## 9. Testes (PHPUnit + WP Test Framework)

### 9.1 Setup

```bash
# Uma vez por máquina
cd wp-api
composer install --dev
./vendor/bin/wp scaffold plugin-tests gestor-api
```

Gera `tests/bootstrap.php` + `phpunit.xml.dist` + WP test framework config.

### 9.2 Suites obrigatórias

| Suite | Cobre |
|---|---|
| `test-auth.php` | Login OK, login senha errada, login usuário inexistente, token expirado, refresh, logout, rate limit |
| `test-tarefas-crud.php` | Criar, listar, ver, editar (versao OK), editar (conflito), concluir, reabrir, soft-delete |
| `test-sync-pull.php` | Pull vazio, pull após criar, pull após soft-delete, paginação cursor |
| `test-sync-push.php` | Push criar, push editar OK, push editar conflito (gera entrada em conflitos), push delete |
| `test-conflitos.php` | Listar, resolver MINE, resolver THEIRS, resolver MERGE |
| `test-areas-crud.php`, `test-clientes-crud.php`, `test-projetos-crud.php` | CRUD básico de cada |
| `test-permissoes.php` | Usuário A não vê dados de B; sem token = 401; token revogado = 401 |

### 9.3 Comando

```bash
cd wp-api
./vendor/bin/phpunit
```

**Suíte verde é pré-requisito** pra qualquer release.

---

## 10. Segurança (threat model)

| Ameaça | Mitigação |
|---|---|
| Senha fraca | Validação + `password_hash` bcrypt cost 12 |
| Token roubado | HTTPS obrigatório + revogação por logout + token só SHA-256 no banco |
| Brute-force login | Rate limit 5/15min + auditoria |
| Injeção SQL | `$wpdb->prepare()` em 100% das queries, sem concatenar |
| XSS | `esc_html`, `esc_attr`, `esc_url` em todo output; REST já retorna JSON |
| CSRF | Não usar cookies WP, só `Authorization: Bearer`; CORS liberado só pro app |
| IDOR (ver dados de outro user) | Todo `WHERE usuario_id = ?` com bind do user autenticado, **nunca** confiar em ID do payload |
| Privilege escalation | Capability `gestor_api_manage` só pra admin WP; resto do acesso é via token |
| LGPD | Endpoint admin pra apagar conta (cascade tudo), exportar todos os dados em JSON |
| Auditoria apagada | Trigger MySQL que bloqueia DELETE/UPDATE em `wp_gestor_auditoria` |

---

## 11. Comando de release (subida do plugin)

```powershell
cd E:\Projetos\LOPES FOCUS\wp-api
# 1. Suite verde
composer install --dev
./vendor/bin/phpunit
# 2. Bump de versão (3 lugares: plugin header + constante GESTOR_API_VERSION + readme.txt)
# (criar tools/bump-version.ps1 — script simples)
# 3. Zipar (SEMPRE o formato TUDO PREMIUM do wp-architect)
# Pasta raiz do ZIP = nome do plugin (gestor-api/)
Remove-Item gestor-api.zip -ErrorAction SilentlyContinue
Compress-Archive -Path gestor-api/* -DestinationPath gestor-api.zip
# 4. Validar ZIP (zip-validator)
unzip -l gestor-api.zip
# 5. Passar pro release-manager (sobe no GitHub, marca como asset)
```

Custo de release: **R$ 0,00**. Sem certificado de assinatura (plugin WP roda no servidor, não precisa).

---

## 12. O que este plugin **NÃO** faz (escopo MVP)

- ❌ Push notifications (FCM / OneSignal) — fora do MVP, fase futura
- ❌ Upload de anexos — fora do MVP, fase futura
- ❌ Webhooks — fora do MVP
- ❌ OAuth (Google/Facebook login) — email+senha basta
- ❌ Multi-tenant (uma conta WP = um usuário Gestor) — fora do MVP
- ❌ Sincronização com o Gestor desktop — **BLOQUEADO** até Marcio liberar (§9.1 do AGENTS.md raiz)

---

## 13. Histórico de versões

| Versão | Data | Notas |
|---|---|---|
| 0.1.0 | 2026-08-17 | Release inicial: schema MySQL, auth, CRUD tarefas/projetos/clientes/areas, sync pull/push, admin page, PHPUnit suite |

---

*ML Lopes Design · Marcio · mlopesdesign@gmail.com · mlopesdesign.com.br · tools.mlopesdesign.com.br*
*Plugin WP `gestor-api` v0.1.0 — gerado em 17/08/2026. Stack: PHP 8 + WordPress 6 + MySQL + WP REST API. Custo zero.*
