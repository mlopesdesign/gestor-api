# Gestor API v0.1.2 — Bugfix PHP 8 fatal (2026-08-18)

**Tipo:** bugfix obrigatório (atualize IMEDIATAMENTE se está em 0.1.1)

## O que mudou

A v0.1.1 introduziu um **fatal error que derrubou o WordPress inteiro** (HTTP 500 em `/wp-json/` E em todos os endpoints nativos do WP, não só do plugin). Causa raiz e fix abaixo.

## Causa raiz

Os 4 controllers REST (`Tarefas`, `Projetos`, `Clientes`, `Areas`) sobrescrevem métodos da classe pai `WP_REST_Controller`. Em PHP 8 com `strict_types=1`, a sobrescrita precisa ter **assinatura compatível** com a do pai — em particular, o tipo do parâmetro pode ser CONTRAVARIANTE (mais amplo) mas nunca mais específico.

```php
// WP_REST_Controller (pai, WordPress core):
public function get_item($request) { ... }    // sem type hint

// Meu controller (errado):
public function get_item(WP_REST_Request $request): WP_REST_Response|\WP_Error { ... }
```

O PHP 8 rejeita com:
> `Declaration of Gestor_Api\Rest\Rest_Tarefas_Controller::get_item(WP_REST_Request $request): WP_REST_Response|\WP_Error must be compatible with WP_REST_Controller::get_item($request)`

Resultado: fatal na hora que o WP instancia os controllers (no hook `rest_api_init`). Como o fatal acontece **depois do roteamento principal**, a request inteira retorna 500 — incluindo endpoints nativos do WP que nem usam o plugin.

## Por que `php -l` deixou passar

`php -l` checa **sintaxe**. Erro de assinatura de método é **erro semântico em tempo de carregamento de classe** — só dispara quando a classe é `require`'d E o Zend Engine compila os métodos dela.

A v0.1.0 tinha o mesmo erro mas estava **desativada** no servidor, então o `rest_api_init` nunca rodou e o fatal nunca disparou.

## Por que meu smoke test offline deixou passar

Meu smoke test (`_smoke-init.php`) carrega as classes mas só instancia controllers. O erro de assinatura é levantado no momento em que a classe é **declarada** — mas o `require_once` no meu script testava classe por classe em ordem alfabética, e os controllers vinham ANTES de serem efetivamente instanciados.

Após o fix, o smoke test foi ampliado pra **chamar `register_routes()`** em cada controller (não só instanciar). Agora pega esse tipo de erro.

## Lições (permanentes)

1. **`php -l` é INSUFICIENTE pra plugins WP.** Sempre rodar smoke test offline com stubs + `register_routes()` invocado.
2. **Ao estender `WP_REST_Controller`, o parâmetro de `get_item`/`create_item`/etc é `$request` SEM type hint.** Manter a assinatura do pai.
3. **Regra de ouro: ao sobrescrever método, o tipo do parâmetro só pode ser igual ou mais amplo que o do pai.** Mais específico = fatal.
4. **Bump de versão em TODO build (mesmo rebuild do mesmo dia).** Do PADRÃO-ML-LOPES-DESIGN.md §12.

## Arquivos alterados (16 métodos em 4 controllers)

- `includes/rest/class-rest-tarefas-controller.php` (4)
- `includes/rest/class-rest-projetos-controller.php` (4)
- `includes/rest/class-rest-clientes-controller.php` (4)
- `includes/rest/class-rest-areas-controller.php` (4)

Cada um trocou:
```php
public function get_item(WP_REST_Request $request): WP_REST_Response|\WP_Error
```
por:
```php
public function get_item($request): WP_REST_Response|\WP_Error
```

(O `: WP_REST_Response|\WP_Error` no retorno é **covariante** — permitido.)

## Compatibilidade

- v0.1.1 → v0.1.2: update in-place, schema MySQL inalterado, sem migração de banco.
- Mesma assinatura de API REST.
- Nenhum dado perdido.

## Como atualizar

1. Baixar `gestor-api-0.1.2.zip` (62.950 bytes, SHA-256 `b990231fff92c5e7b2bc5b03363b9648cc63b59a0bfa51d31b48d9a200532ead`)
2. WP Admin → Plugins → Adicionar novo → Upload → escolher ZIP → Instalar agora → Substituir
3. WP Admin → Plugins instalados → "Gestor API" → Ativar (se estiver desativado)
4. Pronto. Endpoint `/wp-json/gestor/v1/` volta a responder 200.

## Como verificar

```bash
curl -i https://tools.mlopesdesign.com.br/wp-json/gestor/v1/
# Esperado: 200 + JSON com rotas registradas
```
