<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Report\ReportNarrative;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Documentos de reporte (GET /api/v1/reports/{tipo}/{formato}): el PDF lleva portada, introducción,
 * análisis y detalle; el Excel es un libro de tres hojas. Ambos salen de las mismas filas que el JSON.
 */
class ReportDocumentsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach ([Payment::class, MobileApiToken::class, User::class, Role::class, Permission::class] as $model) {
            $model::query()->delete();
        }
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function adminToken(): string
    {
        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Ana Admin', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $token = 'tok-'.Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        return $token;
    }

    private function pago(float $monto, float $propina, string $metodo): void
    {
        Payment::create(['appointment_id' => (string) Str::uuid(), 'monto' => $monto, 'propina' => $propina, 'metodo_pago' => $metodo]);
    }

    public function test_el_excel_es_un_libro_con_portada_resumen_y_detalle(): void
    {
        $this->pago(200, 20, 'tarjeta');
        $this->pago(300, 0, 'efectivo');

        $response = $this->withToken($this->adminToken())->get('/api/v1/reports/ingresos/excel');
        $response->assertOk();

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);
        $book = $reader->load($file->getFile()->getPathname());
        $this->assertSame(['Portada', 'Resumen', 'Detalle'], $book->getSheetNames());

        $summary = $book->getSheetByName('Resumen');
        $texts = collect($summary->toArray(null, true, false))->flatten()->filter()->implode('|');
        $this->assertStringContainsString('INDICADORES CLAVE', $texts);
        $this->assertStringContainsString('$520.00', $texts, 'Ingreso total = 200 + 20 + 300');
        $this->assertCount(1, $summary->getChartCollection(), 'La gráfica nativa vive en el resumen');

        $detail = $book->getSheetByName('Detalle');
        $this->assertSame('Fecha', $detail->getCell('A1')->getValue());
        $this->assertSame('A2', $detail->getFreezePane(), 'Encabezado congelado');
        $this->assertSame('TOTAL', $detail->getCell('A4')->getValue(), 'Renglón de totales tras las dos filas de datos');
    }

    public function test_el_pdf_trae_portada_y_varias_paginas(): void
    {
        $this->pago(200, 20, 'tarjeta');

        $response = $this->withToken($this->adminToken())->get('/api/v1/reports/ingresos/pdf');

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $pdf = $response->baseResponse->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThanOrEqual(4, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf), 'Portada, introducción, resumen y detalle con notas');
    }

    public function test_todos_los_tipos_generan_pdf_y_excel_aun_sin_datos(): void
    {
        $token = $this->adminToken();

        foreach (['ingresos', 'citas', 'inventario', 'clientes'] as $type) {
            $this->withToken($token)->get("/api/v1/reports/{$type}/pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->withToken($token)->get("/api/v1/reports/{$type}/excel")->assertOk();
        }
    }

    public function test_el_json_no_cambia(): void
    {
        $this->pago(200, 20, 'tarjeta');

        $this->withToken($this->adminToken())->getJson('/api/v1/reports/ingresos/json')
            ->assertOk()
            ->assertJsonPath('title', 'Reporte de Ingresos')
            ->assertJsonCount(1, 'rows')
            ->assertJsonMissingPath('narrative');
    }

    public function test_la_narrativa_redacta_hallazgos_con_las_cifras_reales(): void
    {
        $rows = collect([
            ['fecha' => '2026-10-01 10:00', 'origen' => 'Servicio', 'barbero' => 'Nava', 'metodo_pago' => 'tarjeta', 'propina' => 20.0, 'total' => 220.0],
            ['fecha' => '2026-10-02 10:00', 'origen' => 'Producto', 'barbero' => '—', 'metodo_pago' => 'efectivo', 'propina' => 0.0, 'total' => 80.0],
        ]);

        $result = app(ReportNarrative::class)->build('ingresos', ['rows' => $rows, 'title' => 'x'], ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']);

        $this->assertSame('01/10/2026 al 31/10/2026', $result['period']);
        $this->assertSame('$300.00', $result['kpis'][0]['value']);
        $this->assertStringContainsString('73.3 %', implode(' ', $result['findings']), 'Servicios = 220 de 300');
        $this->assertStringContainsString('Nava', implode(' ', $result['findings']));

        $empty = app(ReportNarrative::class)->build('citas', ['rows' => collect(), 'title' => 'x'], []);
        $this->assertSame([], $empty['kpis']);
        $this->assertCount(1, $empty['findings'], 'Un reporte vacío no inventa hallazgos');
    }
}
