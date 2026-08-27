<?php

namespace Tests\Unit;

use App\Http\Controllers\Concerns\Sortable;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class SortableTest extends TestCase
{
    use Sortable;

    public function test_uses_default_column_and_direction_when_no_query_params(): void
    {
        $query = $this->fakeQuery();
        $request = Request::create('/clients', 'GET');

        $this->applySort($query, $request, ['nombre', 'id'], 'id', 'desc');

        $this->assertSame('id', $query->column);
        $this->assertSame('desc', $query->direction);
    }

    public function test_applies_requested_column_and_direction_when_whitelisted(): void
    {
        $query = $this->fakeQuery();
        $request = Request::create('/clients?sort=nombre&dir=desc', 'GET');

        $this->applySort($query, $request, ['nombre', 'id'], 'id', 'asc');

        $this->assertSame('nombre', $query->column);
        $this->assertSame('desc', $query->direction);
    }

    /**
     * Regresión: una columna fuera de la lista blanca debía caer también en
     * la dirección por defecto, no solo en la columna por defecto (bug
     * corregido esta sesión: se conservaba la dirección pedida en la URL
     * incluso cuando la columna era inválida/rechazada).
     */
    public function test_falls_back_to_default_direction_when_column_is_not_whitelisted(): void
    {
        $query = $this->fakeQuery();
        $request = Request::create('/clients?sort=campo_no_permitido&dir=desc', 'GET');

        $this->applySort($query, $request, ['nombre', 'id'], 'id', 'asc');

        $this->assertSame('id', $query->column);
        $this->assertSame('asc', $query->direction);
    }

    public function test_invalid_direction_falls_back_to_asc(): void
    {
        $query = $this->fakeQuery();
        $request = Request::create('/clients?sort=nombre&dir=algo_invalido', 'GET');

        $this->applySort($query, $request, ['nombre', 'id'], 'id', 'desc');

        $this->assertSame('nombre', $query->column);
        $this->assertSame('asc', $query->direction);
    }

    private function fakeQuery(): object
    {
        return new class
        {
            public ?string $column = null;

            public ?string $direction = null;

            public function orderBy($column, $direction)
            {
                $this->column = $column;
                $this->direction = $direction;

                return $this;
            }
        };
    }
}
