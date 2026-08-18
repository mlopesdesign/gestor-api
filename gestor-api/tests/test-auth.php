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
}
