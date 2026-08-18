<?php
/**
 * Suite de testes: CRUD de clientes.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Models\Cliente;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * @group clientes
 */
final class ClientesCrudTest extends WP_UnitTestCase
{
    private string $usuario_id;

    public function set_up(): void
    {
        parent::set_up();
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => 'clientes-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Teste Clientes',
        ]);
    }

    public function test_criar(): void
    {
        $model = new Cliente();
        $row = $model->upsert($this->usuario_id, [
            'nome' => 'Empresa X',
            'contatos' => [['tipo' => 'email', 'valor' => 'contato@x.com']],
            'status' => 'ATIVO',
        ]);
        $this->assertSame('Empresa X', $row['nome']);
        $this->assertSame('ATIVO', $row['status']);
    }

    public function test_criar_sem_nome_nem_organizacao_falha(): void
    {
        $this->expectException(\Gestor_Api\Util\Gestor_Api_Validation_Exception::class);
        $model = new Cliente();
        $model->upsert($this->usuario_id, [
            'nome' => '',
            'organizacao' => '',
        ]);
    }

    public function test_listar(): void
    {
        $model = new Cliente();
        $model->upsert($this->usuario_id, ['nome' => 'C1']);
        $model->upsert($this->usuario_id, ['nome' => 'C2']);
        $items = $model->list_for_user($this->usuario_id);
        $this->assertCount(2, $items);
    }

    public function test_soft_delete(): void
    {
        $model = new Cliente();
        $row = $model->upsert($this->usuario_id, ['nome' => 'D']);
        $this->assertTrue($model->soft_delete($row['id'], $this->usuario_id));
        $this->assertNull($model->find_by_id($row['id'], $this->usuario_id));
    }
}
