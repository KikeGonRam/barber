<?php

namespace Tests\Unit;

use App\Http\Middleware\RejectMongoOperators;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La inyección NoSQL se bloquea en el middleware que va prepend al grupo `api`
 * completo (bootstrap/app.php), así que cubre todas las rutas /api/*.
 *
 * Estas pruebas fijan las DOS mitades del control: que rechace los operadores de
 * Mongo, y — igual de importante — que NO rechace peticiones legítimas. Un
 * middleware que devuelve 422 a todo también "pasa" la mitad de rechazo.
 *
 * Extiende Tests\TestCase (que arranca la aplicación) porque el camino de
 * rechazo usa el helper response()->json(), que resuelve la ResponseFactory
 * desde el contenedor. No toca ninguna base de datos.
 */
class RejectMongoOperatorsTest extends TestCase
{
    private const RECHAZADA = 422;

    private const ACEPTADA = 200;

    /** @param array<string, mixed> $query */
    private function statusForQuery(array $query): int
    {
        $request = Request::create('/api/v1/appointments?'.http_build_query($query), 'GET');

        return $this->runMiddleware($request);
    }

    /** @param array<string, mixed> $body */
    private function statusForJsonBody(array $body): int
    {
        $request = Request::create(
            '/api/v1/appointments',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );

        return $this->runMiddleware($request);
    }

    private function runMiddleware(Request $request): int
    {
        $middleware = new RejectMongoOperators;

        $response = $middleware->handle($request, fn () => new Response('ok'));

        return $response->getStatusCode();
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function operadoresEnQuery(): array
    {
        return [
            'not equal' => [['estado' => ['$ne' => 'cancelada']]],
            'greater than' => [['precio' => ['$gt' => '']]],
            'regex' => [['nombre' => ['$regex' => '.*']]],
            'where javascript' => [['$where' => 'this.x == 1']],
            'llave de primer nivel' => [['$or' => [['a' => 1]]]],
            'anidada a tres niveles' => [['filtro' => ['rango' => ['$gte' => '0']]]],
        ];
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('operadoresEnQuery')]
    public function test_rechaza_operadores_de_mongo_en_la_query(array $query): void
    {
        $this->assertSame(self::RECHAZADA, $this->statusForQuery($query));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function operadoresEnCuerpo(): array
    {
        return [
            'json simple' => [['email' => ['$ne' => null]]],
            'json anidado' => [['filtros' => ['fecha' => ['$exists' => true]]]],
            'json con $where' => [['$where' => 'sleep(1000)']],
        ];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('operadoresEnCuerpo')]
    public function test_rechaza_operadores_de_mongo_en_el_cuerpo_json(array $body): void
    {
        $this->assertSame(self::RECHAZADA, $this->statusForJsonBody($body));
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function peticionesLegitimas(): array
    {
        return [
            'sin parametros' => [[]],
            'texto normal' => [['estado' => 'confirmada', 'page' => '2']],
            'precio con signo de dolar' => [['precio' => '$150']],
            'clave con dolar pero no al inicio' => [['precio_usd' => '10']],
            'lista de ids' => [['ids' => ['1', '2', '3']]],
            'filtro anidado normal' => [['filtros' => ['estado' => 'pendiente']]],
        ];
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('peticionesLegitimas')]
    public function test_no_rechaza_peticiones_legitimas(array $query): void
    {
        $this->assertSame(self::ACEPTADA, $this->statusForQuery($query));
    }

    public function test_no_rechaza_un_cuerpo_json_legitimo(): void
    {
        $this->assertSame(
            self::ACEPTADA,
            $this->statusForJsonBody(['estado' => 'confirmada', 'precio_usd' => 150]),
        );
    }

    public function test_corta_la_recursion_en_estructuras_absurdamente_profundas(): void
    {
        // hasOperatorKey() corta a 32 niveles y trata el exceso como sospechoso:
        // si no, una carga anidada podría agotar la pila.
        $anidado = 'valor';
        for ($i = 0; $i < 40; $i++) {
            $anidado = ['nivel' => $anidado];
        }

        $this->assertSame(self::RECHAZADA, $this->statusForQuery(['filtro' => $anidado]));
    }
}
