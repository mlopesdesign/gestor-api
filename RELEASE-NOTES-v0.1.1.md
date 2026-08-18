# Gestor API v0.1.1

## Bugfixes criticos

Esta versao corrige **erro 500 fatal** em TODOS os endpoints REST do plugin. O plugin estava ativo no admin WP mas **nenhuma rota REST funcionava**.

### FIX 1: 500 em `/wp-json/gestor/v1/*`
A constante da classe `Rest_Controller` referenciava uma constante global via `const NAMESPACE = GESTOR_API_NAMESPACE`. Em PHP 8.0 isso quebra porque `const` de classe não aceita constantes globais resolvidas em runtime.

```php
// ANTES (quebra em PHP 8.0):
protected const NAMESPACE = GESTOR_API_NAMESPACE;

// DEPOIS:
protected const NAMESPACE = 'gestor/v1';
```

Mesmo problema em `Migrations::CURRENT_VERSION`.

### FIX 2: autoload PSR-4 nao carregava os models
Os arquivos estavam nomeados `class-model-*.php` mas o autoload PSR-4 procurava `class-*.php`. Os 5 models (Usuario, Tarefa, Projeto, Cliente, Area) simplesmente nao eram encontrados.

Renomeados:
- `class-model-area.php` -> `class-area.php`
- `class-model-cliente.php` -> `class-cliente.php`
- `class-model-projeto.php` -> `class-projeto.php`
- `class-model-tarefa.php` -> `class-tarefa.php`
- `class-model-usuario.php` -> `class-usuario.php`

### FIX 3: `register_routes` declarado como static
Os 6 controllers REST declaravam `public static function register_routes()` mas o metodo da classe pai `WP_REST_Controller::register_routes()` e nao-estatico. PHP 8.0 rejeita com `Cannot make non static method ... static`.

Removido `static` de todos os 6 controllers. `Gestor_Api::register_rest_routes()` agora instancia com `(new X())->register_routes()`.

## UX

- **Menu movido** de "Ferramentas" para o **menu lateral principal**, com icone SVG da paleta ML Lopes (amarelo `#F0A000`).

## Novidades

- **Form admin em Tools > Gestor API** (na verdade, agora no menu lateral) para criar usuario inicial sem precisar de WP-CLI.
- **Endpoint REST admin**:
  - `POST /gestor/v1/admin/usuarios` (capability: `manage_options`)
  - `GET /gestor/v1/admin/usuarios` (capability: `manage_options`)

## Instalacao

1. Desativar o plugin atual no WP admin.
2. Enviar o ZIP `gestor-api.zip`.
3. Ativar o plugin.
4. Acessar menu lateral "Gestor API" e criar usuario inicial.

## Stack

- PHP 8.0+
- WordPress 6.x
- MySQL 5.7+ / MariaDB 10.3+
- WP REST API nativa
- Custo: R$ 0,00
