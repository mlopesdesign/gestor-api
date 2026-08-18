<?php
/**
 * Suite de testes: sync push.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Models\Tarefa;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Sync\Sync_Push;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * @group sync-push
 */
final class SyncPushTest extends WP_UnitTestCase
{
    private string $usuario_id;

    public function set_up(): void
    {
        parent::set_up();
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => 'sync-push-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Teste Sync Push',
        ]);
    }

    public function test_push_criar(): void
    {
        $id = Ulid::generate();
        $push = new Sync_Push();
        $result = $push->executar(
            $this->usuario_id,
            'device-1',
            [
                [
                    'tabela' => 'tarefas',
                    'operacao' => 'UPSERT',
                    'registro_id' => $id,
                    'payload' => ['titulo' => 'Push criacao', 'status' => 'CAIXA_ENTRADA'],
                ],
            ]
        );
        $this->assertSame(1, $result['aplicadas']);
        $this->assertSame(0, count($result['conflitos']));

        // Verifica persistencia.
        $model = new Tarefa();
        $row = $model->find_by_id($id, $this->usuario_id);
        $this->assertNotNull($row);
        $this->assertSame('Push criacao', $row['titulo']);
    }

    public function test_push_editar_ok(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'Original']);
        $id = $row['id'];

        $push = new Sync_Push();
        $result = $push->executar(
            $this->usuario_id,
            'device-1',
            [
                [
                    'tabela' => 'tarefas',
                    'operacao' => 'UPSERT',
                    'registro_id' => $id,
                    'versao_base' => 1,
                    'payload' => ['titulo' => 'Editada'],
                ],
            ]
        );
        $this->assertSame(1, $result['aplicadas']);
        $this->assertSame(0, count($result['conflitos']));

        $atualizada = $model->find_by_id($id, $this->usuario_id);
        $this->assertSame('Editada', $atualizada['titulo']);
        $this->assertSame(2, (int) $atualizada['versao']);
    }

    public function test_push_editar_conflito_gera_conflito(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'A']);
        $id = $row['id'];

        // Bumpa versao no servidor.
        $model->upsert($this->usuario_id, [
            'id' => $id,
            'versao_base' => 1,
            'titulo' => 'A v2',
        ]);

        // Push com versao_base=1 (ja foi atualizado pra 2).
        $push = new Sync_Push();
        $result = $push->executar(
            $this->usuario_id,
            'device-1',
            [
                [
                    'tabela' => 'tarefas',
                    'operacao' => 'UPSERT',
                    'registro_id' => $id,
                    'versao_base' => 1,
                    'payload' => ['titulo' => 'A conflito'],
                ],
            ]
        );
        $this->assertSame(0, $result['aplicadas']);
        $this->assertGreaterThanOrEqual(1, count($result['conflitos']));
        $this->assertSame('PENDENTE', $result['conflitos'][0]['estado']);
    }

    public function test_push_delete(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'D']);
        $id = $row['id'];

        $push = new Sync_Push();
        $result = $push->executar(
            $this->usuario_id,
            'device-1',
            [
                [
                    'tabela' => 'tarefas',
                    'operacao' => 'DELETE',
                    'registro_id' => $id,
                ],
            ]
        );
        $this->assertSame(1, $result['aplicadas']);

        $this->assertNull($model->find_by_id($id, $this->usuario_id));
    }
}
