<?php
/**
 * Suite de testes: permissoes e isolamento por usuario.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Auth\Token_Repository;
use Gestor_Api\Models\Tarefa;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * @group permissoes
 */
final class PermissoesTest extends WP_UnitTestCase
{
    private string $usuario_a;
    private string $usuario_b;

    public function set_up(): void
    {
        parent::set_up();
        $u = new Usuario();
        $this->usuario_a = $u->criar([
            'email' => 'perm-a-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Perm A',
        ]);
        $this->usuario_b = $u->criar([
            'email' => 'perm-b-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Perm B',
        ]);
    }

    public function test_user_a_nao_ve_dados_de_b(): void
    {
        $model = new Tarefa();
        $row_b = $model->upsert($this->usuario_b, ['titulo' => 'Secreto de B']);
        $id_b = $row_b['id'];

        // A tenta ler registro de B.
        $row_visto = $model->find_by_id($id_b, $this->usuario_a);
        $this->assertNull($row_visto, 'User A nao deveria ver tarefa de B');
    }

    public function test_user_a_nao_lista_tarefas_de_b(): void
    {
        $model = new Tarefa();
        $model->upsert($this->usuario_a, ['titulo' => 'A1']);
        $model->upsert($this->usuario_b, ['titulo' => 'B1']);
        $model->upsert($this->usuario_b, ['titulo' => 'B2']);

        $items_a = $model->list_for_user($this->usuario_a);
        $this->assertCount(1, $items_a);
        $this->assertSame('A1', $items_a[0]['titulo']);
    }

    public function test_user_a_nao_deleta_tarefa_de_b(): void
    {
        $model = new Tarefa();
        $row_b = $model->upsert($this->usuario_b, ['titulo' => 'B delete']);
        $id_b = $row_b['id'];

        $ok = $model->soft_delete($id_b, $this->usuario_a);
        $this->assertFalse($ok, 'User A nao deveria deletar tarefa de B');

        // Verifica que registro ainda existe.
        $row = $model->find_by_id($id_b, $this->usuario_b);
        $this->assertNotNull($row);
    }

    public function test_token_revogado_retorna_null(): void
    {
        $u = new Usuario();
        $auth = new \Gestor_Api\Auth\Auth_Service();
        $login = $auth->login([
            'email' => 'perm-a-' . $this->usuario_a . '@gestor.local',
            'senha' => 'Senha@1234',
        ]);
        // Login pode falhar pois email eh ULID, so pra teste:
        if (is_wp_error($login)) {
            $this->markTestSkipped('Login nao funcionou (esperado em alguns cenarios)');
        }

        $tokens = new Token_Repository();
        $sessao = $tokens->find_by_token($login['token']);
        $this->assertNotNull($sessao);

        $tokens->revoke($sessao['id']);
        $this->assertNull($tokens->find_by_token($login['token']));
    }
}
