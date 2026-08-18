<?php
/**
 * Suite de testes: resolucao de conflitos.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Models\Tarefa;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Sync\Sync_Conflict_Resolver;
use Gestor_Api\Sync\Sync_Push;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * @group conflitos
 */
final class ConflitosTest extends WP_UnitTestCase
{
    private string $usuario_id;

    public function set_up(): void
    {
        parent::set_up();
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => 'conflitos-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Teste Conflitos',
        ]);
    }

    public function test_listar_conflitos(): void
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'A']);
        $id = $row['id'];
        $model->upsert($this->usuario_id, [
            'id' => $id,
            'versao_base' => 1,
            'titulo' => 'A v2',
        ]);

        $push = new Sync_Push();
        $push->executar(
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

        $conflitos = $push->listar_conflitos_pendentes($this->usuario_id);
        $this->assertCount(1, $conflitos);
    }

    public function test_resolver_mine(): void
    {
        $conflito_id = $this->criar_conflito();
        $resolver = new Sync_Conflict_Resolver();

        $payload_cliente = $this->get_payload_cliente($conflito_id);
        $result = $resolver->resolver($conflito_id, $this->usuario_id, 'MINE', $payload_cliente);
        $this->assertSame('RESOLVIDO_MINE', $result['estado']);
    }

    public function test_resolver_theirs(): void
    {
        $conflito_id = $this->criar_conflito();
        $resolver = new Sync_Conflict_Resolver();

        $result = $resolver->resolver($conflito_id, $this->usuario_id, 'THEIRS');
        $this->assertSame('RESOLVIDO_THEIRS', $result['estado']);
    }

    public function test_resolver_merge(): void
    {
        $conflito_id = $this->criar_conflito();
        $resolver = new Sync_Conflict_Resolver();

        $result = $resolver->resolver(
            $conflito_id,
            $this->usuario_id,
            'MERGE',
            ['titulo' => 'A mergeado']
        );
        $this->assertSame('RESOLVIDO_MERGE', $result['estado']);
    }

    private function criar_conflito(): int
    {
        $model = new Tarefa();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'A']);
        $id = $row['id'];
        $model->upsert($this->usuario_id, [
            'id' => $id,
            'versao_base' => 1,
            'titulo' => 'A v2',
        ]);

        $push = new Sync_Push();
        $push->executar(
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

        $conflitos = $push->listar_conflitos_pendentes($this->usuario_id);
        $this->assertNotEmpty($conflitos);
        return (int) $conflitos[0]['id'];
    }

    private function get_payload_cliente(int $conflito_id): array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT payload_cliente_a FROM {$wpdb->prefix}gestor_sync_conflitos WHERE id = %d",
                $conflito_id
            ),
            ARRAY_A
        );
        return json_decode((string) $row['payload_cliente_a'], true) ?: [];
    }
}
