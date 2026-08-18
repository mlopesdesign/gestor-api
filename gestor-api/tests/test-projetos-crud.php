<?php
/**
 * Suite de testes: CRUD de projetos.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Models\Cliente;
use Gestor_Api\Models\Projeto;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * @group projetos
 */
final class ProjetosCrudTest extends WP_UnitTestCase
{
    private string $usuario_id;
    private string $cliente_id;

    public function set_up(): void
    {
        parent::set_up();
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => 'projetos-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Teste Projetos',
        ]);
        $cliente = new Cliente();
        $row = $cliente->upsert($this->usuario_id, ['nome' => 'Cliente Padrao']);
        $this->cliente_id = $row['id'];
    }

    public function test_criar(): void
    {
        $model = new Projeto();
        $row = $model->upsert($this->usuario_id, [
            'titulo' => 'Projeto A',
            'cliente_id' => $this->cliente_id,
            'status' => 'PLANEJADO',
            'prioridade' => 'ALTA',
        ]);
        $this->assertSame('Projeto A', $row['titulo']);
        $this->assertSame($this->cliente_id, $row['cliente_id']);
        $this->assertSame('ALTA', $row['prioridade']);
    }

    public function test_validar_datas_invalidas(): void
    {
        $this->expectException(\Gestor_Api\Util\Gestor_Api_Validation_Exception::class);
        $model = new Projeto();
        $model->upsert($this->usuario_id, [
            'titulo' => 'Datas ruins',
            'inicio_em' => '2026-12-01T00:00:00Z',
            'fim_em' => '2026-01-01T00:00:00Z',
        ]);
    }

    public function test_listar(): void
    {
        $model = new Projeto();
        $model->upsert($this->usuario_id, ['titulo' => 'P1']);
        $model->upsert($this->usuario_id, ['titulo' => 'P2']);
        $items = $model->list_for_user($this->usuario_id);
        $this->assertCount(2, $items);
    }

    public function test_soft_delete(): void
    {
        $model = new Projeto();
        $row = $model->upsert($this->usuario_id, ['titulo' => 'D']);
        $this->assertTrue($model->soft_delete($row['id'], $this->usuario_id));
        $this->assertNull($model->find_by_id($row['id'], $this->usuario_id));
    }
}
