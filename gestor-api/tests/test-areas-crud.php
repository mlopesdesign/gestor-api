<?php
/**
 * Suite de testes: CRUD de areas.
 *
 * @package Gestor_Api
 */

declare(strict_types=1);

use Gestor_Api\Models\Area;
use Gestor_Api\Models\Usuario;
use Gestor_Api\Util\Ulid;
use PHPUnit\Framework\TestCase;

/**
 * @group areas
 */
final class AreasCrudTest extends WP_UnitTestCase
{
    private string $usuario_id;

    public function set_up(): void
    {
        parent::set_up();
        $user = new Usuario();
        $this->usuario_id = $user->criar([
            'email' => 'areas-' . Ulid::generate() . '@gestor.local',
            'senha' => 'Senha@1234',
            'nome' => 'Teste Areas',
        ]);
    }

    public function test_criar(): void
    {
        $model = new Area();
        $row = $model->upsert($this->usuario_id, ['nome' => 'Trabalho', 'cor' => '#FF0000']);
        $this->assertSame('Trabalho', $row['nome']);
        $this->assertSame('#FF0000', $row['cor']);
        $this->assertSame(1, (int) $row['versao']);
    }

    public function test_listar(): void
    {
        $model = new Area();
        $model->upsert($this->usuario_id, ['nome' => 'A1']);
        $model->upsert($this->usuario_id, ['nome' => 'A2']);
        $items = $model->list_for_user($this->usuario_id);
        $this->assertGreaterThanOrEqual(2, count($items));
    }

    public function test_editar(): void
    {
        $model = new Area();
        $row = $model->upsert($this->usuario_id, ['nome' => 'A1']);
        $atualizada = $model->upsert($this->usuario_id, [
            'id' => $row['id'],
            'versao_base' => 1,
            'nome' => 'A1 (edit)',
        ]);
        $this->assertSame('A1 (edit)', $atualizada['nome']);
        $this->assertSame(2, (int) $atualizada['versao']);
    }

    public function test_soft_delete(): void
    {
        $model = new Area();
        $row = $model->upsert($this->usuario_id, ['nome' => 'D']);
        $ok = $model->soft_delete($row['id'], $this->usuario_id);
        $this->assertTrue($ok);
        $this->assertNull($model->find_by_id($row['id'], $this->usuario_id));
    }
}
