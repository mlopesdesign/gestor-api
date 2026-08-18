<?php
/**
 * Suite de testes: sync pull.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Models\Tarefa;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Sync\Sync_Pull;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * @group sync-pull
 */
final class SyncPullTest extends WP_UnitTestCase
{
    private string $usuario_id;

    public function set_up(): void
    {
        parent::set_up();
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => 'sync-pull-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Teste Sync Pull',
        ]);
    }

    public function test_pull_vazio(): void
    {
        $pull = new Sync_Pull();
        $result = $pull->executar(
            $this->usuario_id,
            'device-1',
            '',
            100,
            0
        );
        $this->assertSame(0, count($result['mudancas']));
        $this->assertFalse($result['has_more']);
    }

    public function test_pull_apos_criar(): void
    {
        $model = new Tarefa();
        $model->upsert($this->usuario_id, ['titulo' => 'T1']);
        $model->upsert($this->usuario_id, ['titulo' => 'T2']);
        $model->upsert($this->usuario_id, ['titulo' => 'T3']);

        $pull = new Sync_Pull();
        $result = $pull->executar(
            $this->usuario_id,
            'device-1',
            '',
            100,
            0
        );
        $this->assertGreaterThanOrEqual(3, count($result['mudancas']));
    }

    public function test_pull_apos_soft_delete(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'X']);
        $model->soft_delete($row['id'], $this->usuario_id);

        $pull = new Sync_Pull();
        $result = $pull->executar(
            $this->usuario_id,
            'device-1',
            '',
            100,
            0
        );
        // Deve trazer pelo menos o DELETE.
        $tem_delete = false;
        foreach ($result['mudancas'] as $m) {
            if ($m['tabela'] === 'tarefas' && $m['operacao'] === 'DELETE' && $m['registro_id'] === $row['id']) {
                $tem_delete = true;
                break;
            }
        }
        $this->assertTrue($tem_delete, 'Pull deveria retornar operacao DELETE para tarefa soft-deleted');
    }

    public function test_pull_paginacao(): void
    {
        $model = new Tarefa();
        for ($i = 0; $i < 5; $i++) {
            $model->upsert($this->usuario_id, ['titulo' => 'T' . $i]);
        }

        $pull = new Sync_Pull();
        $result1 = $pull->executar(
            $this->usuario_id,
            'device-1',
            '',
            2,
            0
        );
        $this->assertGreaterThanOrEqual(2, count($result1['mudancas']));
        $this->assertNotEmpty($result1['next_cursor']);
    }
}
