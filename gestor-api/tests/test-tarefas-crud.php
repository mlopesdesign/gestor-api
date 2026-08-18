<?php
/**
 * Suite de testes: CRUD de tarefas.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Models\Tarefa;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * Testes de CRUD de tarefas.
 *
 * @group tarefas
 */
final class TarefasCrudTest extends WP_UnitTestCase
{
    private string $usuario_id;

    public function set_up(): void
    {
        parent::set_up();
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => 'tarefas-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Teste Tarefas',
        ]);
    }

    public function test_criar_tarefa(): void
    {
        $model = new Tarefa();
        $id = $model->upsert($this->usuario_id, [
            'titulo' => 'Tarefa de teste',
            'descricao' => 'Descricao da tarefa',
        ]);
        $this->assertIsArray($id);
        $this->assertArrayHasKey('id', $id);
        $this->assertSame('CAIXA_ENTRADA', $id['status']);
        $this->assertSame(1, (int) $id['versao']);
    }

    public function test_listar_tarefas(): void
    {
        $model = new Tarefa();
        $model->upsert($this->usuario_id, ['titulo' => 'T1']);
        $model->upsert($this->usuario_id, ['titulo' => 'T2']);
        $model->upsert($this->usuario_id, ['titulo' => 'T3']);

        $items = $model->list_for_user($this->usuario_id);
        $this->assertCount(3, $items);
    }

    public function test_ver_tarefa(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'X']);
        $found = $model->find_by_id($row['id'], $this->usuario_id);
        $this->assertNotNull($found);
        $this->assertSame('X', $found['titulo']);
    }

    public function test_editar_versao_ok(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'A']);
        $id = $row['id'];

        $atualizada = $model->upsert($this->usuario_id, [
            'id' => $id,
            'versao_base' => 1,
            'titulo' => 'A (editada)',
        ]);
        $this->assertSame(2, (int) $atualizada['versao']);
        $this->assertSame('A (editada)', $atualizada['titulo']);
    }

    public function test_editar_conflito_409(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'A']);
        $id = $row['id'];

        // Bumpa versao pra 2.
        $model->upsert($this->usuario_id, [
            'id' => $id,
            'versao_base' => 1,
            'titulo' => 'A v2',
        ]);

        // Tentativa de update com versao_base errada.
        $this->expectException(\Gestor_Api\Util\Gestor_Api_Validation_Exception::class);
        $model->upsert($this->usuario_id, [
            'id' => $id,
            'versao_base' => 1,
            'titulo' => 'A conflito',
        ]);
    }

    public function test_concluir(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'C']);
        $concluida = $model->concluir($row['id'], $this->usuario_id, true);
        $this->assertSame('CONCLUIDA', $concluida['status']);
        $this->assertNotNull($concluida['concluida_em']);
    }

    public function test_reabrir(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'R']);
        $model->concluir($row['id'], $this->usuario_id, true);
        $reaberta = $model->reabrir($row['id'], $this->usuario_id);
        $this->assertSame('EM_ANDAMENTO', $reaberta['status']);
        $this->assertNull($reaberta['concluida_em']);
    }

    public function test_soft_delete(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'D']);
        $ok = $model->soft_delete($row['id'], $this->usuario_id);
        $this->assertTrue($ok);

        $found = $model->find_by_id($row['id'], $this->usuario_id);
        $this->assertNull($found, 'Tarefa soft-deleted nao deve aparecer em find_by_id');
    }
}
