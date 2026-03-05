<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_reports_index(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Exportar reportes');
    }

    public function test_non_admin_cannot_open_reports_index(): void
    {
        $user = $this->makeUserWithRole('recepcionista');

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_admin_can_export_income_report_pdf_and_excel(): void
    {
        $admin = $this->makeUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'ingresos', 'format' => 'pdf']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('reports.export', ['type' => 'ingresos', 'format' => 'excel']))
            ->assertOk();
    }

    public function test_dashboard_is_accessible_for_any_verified_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
