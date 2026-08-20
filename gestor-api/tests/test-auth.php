<?php
/**
 * Suite de testes: autenticacao.
 *
 * Requer WP test framework configurado (composer install --dev + wp scaffold plugin-tests).
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Auth\Auth_Service;
use Gestor_Api\Auth\Token_Repository;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Testes de auth: login, refresh, logout, me, rate limit.
 *
 * @group auth
 */
final class AuthTest extends WP_UnitTestCase
{
    private string $email;
    private string $senha;
    private string $usuario_id;

    public function set_up(): void
    {
        parent::set_up();
        $this->email = 'test-auth-' . Ulid::generate() . '@gestor.local';
        $this->senha = 'Senha@1234';
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => $this->email,
            'senha' => $this->senha,
            'nome' => 'Teste Auth',
        ]);
    }

    public function test_login_ok(): void
    {
        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => $this->email,
            'senha' => $this->senha,
        ]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expira_em', $result);
        $this->assertArrayHasKey('usuario', $result);
        $this->assertSame($this->usuario_id, $result['usuario']['id']);
    }

    public function test_login_senha_errada(): void
    {
        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => $this->email,
            'senha' => 'Errada@1234',
        ]);
        $this->assertWPError($result);
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function test_login_usuario_inexistente(): void
    {
        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => 'naoexiste-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
        ]);
        $this->assertWPError($result);
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function test_token_expirado(): void
    {
        global $wpdb;
        $auth = new Auth_Service();
        $login = $auth->login(['email' => $this->email, 'senha' => $this->senha]);
        $this->assertIsArray($login);

        // Expira o token no banco.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}gestor_sessoes SET expira_em = '2000-01-01 00:00:00' WHERE usuario_id = %s",
                $this->usuario_id
            )
        );

        $tokens = new Token_Repository();
        $sessao = $tokens->find_by_token($login['token']);
        $this->assertNull($sessao, 'Token expirado deveria retornar null');
    }

    public function test_refresh(): void
    {
        $auth = new Auth_Service();
        $login = $auth->login(['email' => $this->email, 'senha' => $this->senha]);
        $this->assertIsArray($login);

        $tokens = new Token_Repository();
        $sessao = $tokens->find_by_token($login['token']);
        $this->assertNotNull($sessao);

        $refresh = $auth->refresh($sessao);
        $this->assertIsArray($refresh);
        $this->assertArrayHasKey('token', $refresh);
        $this->assertNotSame($login['token'], $refresh['token'], 'Refresh deve gerar token diferente');

        // Token antigo deve estar revogado.
        $sessao_antiga = $tokens->find_by_token($login['token']);
        $this->assertNull($sessao_antiga, 'Token antigo deveria estar revogado');
    }

    public function test_logout(): void
    {
        $auth = new Auth_Service();
        $login = $auth->login(['email' => $this->email, 'senha' => $this->senha]);
        $this->assertIsArray($login);

        $tokens = new Token_Repository();
        $sessao = $tokens->find_by_token($login['token']);
        $this->assertNotNull($sessao);

        $ok = $auth->logout($sessao);
        $this->assertTrue($ok);

        $sessao_apos = $tokens->find_by_token($login['token']);
        $this->assertNull($sessao_apos, 'Apos logout, token nao deve autenticar');
    }

    public function test_rate_limit(): void
    {
        $auth = new Auth_Service();
        $ip = '192.168.1.99';
        $this->assertTrue($auth->check_rate_limit($ip));

        // 5 tentativas.
        for ($i = 0; $i < GESTOR_API_LOGIN_RATE_LIMIT; $i++) {
            $auth->increment_rate_limit($ip);
        }
        $this->assertFalse($auth->check_rate_limit($ip), 'Apos 5 tentativas, rate limit deve bloquear');
    }

    // ========================================================================
    // v0.1.4: testes do fluxo WP nativo (wp_users + capability gestor_api_use)
    // ========================================================================

    private int $wp_user_id = 0;
    private string $wp_user_email = '';
    private string $wp_user_senha = '';

    public function set_up_wp_user(): void
    {
        $this->wp_user_senha = 'WpUser@1234';
        $this->wp_user_email = 'wp-user-' . Ulid::generate() . '@gestor.local';
        $this->wp_user_id = wp_create_user($this->wp_user_email, $this->wp_user_senha, 'WP User Teste');
        if (is_wp_error($this->wp_user_id)) {
            $this->fail('wp_create_user falhou: ' . $this->wp_user_id->get_error_message());
        }
        // WP da admin cap por padrao; tira e da so a de uso da API.
        $user = new \WP_User($this->wp_user_id);
        $user->remove_cap('manage_options');
        $user->remove_cap('administrator');
        $user->add_cap('gestor_api_use');
    }

    public function test_login_wp_user_ok(): void
    {
        $this->set_up_wp_user();
        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => $this->wp_user_email,
            'senha' => $this->wp_user_senha,
        ]);
        $this->assertIsArray($result, 'Login de WP user com cap gestor_api_use deve retornar array');
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('usuario', $result);
        $this->assertSame((string) $this->wp_user_id, $result['usuario']['id']);
        $this->assertSame('wp', $result['usuario']['origem']);
        $this->assertSame($this->wp_user_email, $result['usuario']['email']);
    }

    public function test_login_wp_user_senha_errada(): void
    {
        $this->set_up_wp_user();
        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => $this->wp_user_email,
            'senha' => 'Errada@1234',
        ]);
        $this->assertWPError($result);
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function test_login_wp_user_sem_capability(): void
    {
        // Cria WP user sem nenhuma cap de uso da API.
        $this->wp_user_senha = 'NoCap@1234';
        $this->wp_user_email = 'no-cap-' . Ulid::generate() . '@gestor.local';
        $this->wp_user_id = wp_create_user($this->wp_user_email, $this->wp_user_senha, 'No Cap');
        $user = new \WP_User($this->wp_user_id);
        $user->remove_cap('manage_options');
        $user->remove_cap('administrator');
        // NAO da cap gestor_api_use.

        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => $this->wp_user_email,
            'senha' => $this->wp_user_senha,
        ]);
        $this->assertWPError($result, 'User sem cap deve falhar login');
        $this->assertSame(401, $result->get_error_data()['status']);
    }

    public function test_login_wp_admin_pode_usar(): void
    {
        // WP admin (manage_options) SEM cap gestor_api_use tambem deve poder logar.
        $this->wp_user_senha = 'Admin@1234';
        $this->wp_user_email = 'admin-' . Ulid::generate() . '@gestor.local';
        $this->wp_user_id = wp_create_user($this->wp_user_email, $this->wp_user_senha, 'Admin Teste');
        $user = new \WP_User($this->wp_user_id);
        $user->set_role('administrator');

        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => $this->wp_user_email,
            'senha' => $this->wp_user_senha,
        ]);
        $this->assertIsArray($result, 'WP admin (manage_options) deve poder logar sem cap gestor_api_use explicita');
        $this->assertSame('wp', $result['usuario']['origem']);
    }

    public function test_login_legacy_ainda_funciona(): void
    {
        // user legado ja foi criado no set_up()
        $auth = new Auth_Service();
        $result = $auth->login([
            'email' => $this->email,
            'senha' => $this->senha,
        ]);
        $this->assertIsArray($result);
        $this->assertSame('legacy', $result['usuario']['origem']);
    }

    public function test_me_wp_user(): void
    {
        $this->set_up_wp_user();
        $auth = new Auth_Service();
        $login = $auth->login(['email' => $this->wp_user_email, 'senha' => $this->wp_user_senha]);
        $this->assertIsArray($login);

        $tokens = new Token_Repository();
        $sessao = $tokens->find_by_token($login['token']);
        $this->assertNotNull($sessao);

        $me = $auth->me($sessao);
        $this->assertIsArray($me);
        $this->assertSame((string) $this->wp_user_id, $me['id']);
        $this->assertSame('wp', $me['origem']);
        $this->assertSame($this->wp_user_email, $me['email']);
    }

    public function test_apagar_conta_wp_user(): void
    {
        $this->set_up_wp_user();
        $user_model = new Usuario();
        $ok = $user_model->apagar_conta((string) $this->wp_user_id);
        $this->assertTrue($ok);

        // Verifica que a meta foi setada
        $apagada = get_user_meta($this->wp_user_id, 'gestor_conta_apagada_em', true);
        $this->assertNotEmpty($apagada);

        // Email deve ter sido limpo
        $user = new \WP_User($this->wp_user_id);
        $this->assertStringStartsWith('deleted-', $user->user_email);

        // Login deve falhar agora
        $auth = new Auth_Service();
        $result = $auth->login(['email' => $this->wp_user_email, 'senha' => $this->wp_user_senha]);
        $this->assertWPError($result, 'Login de conta apagada (LGPD) deve falhar');
    }
}
