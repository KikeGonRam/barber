<?php

namespace Tests\Concerns;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPackage;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Models\MobileApiToken;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/** Datos y limpieza comunes de las pruebas de membresías. */
trait CreatesMembershipData
{
    private function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    private function cleanMembershipData(): void
    {
        Appointment::withTrashed()->forceDelete();
        User::withTrashed()->forceDelete();

        foreach ([ClientPackage::class, MembershipInvoice::class, ClientMembership::class, MembershipPlan::class, Payment::class, Service::class, Barber::class, Client::class, MobileApiToken::class, Role::class, Permission::class] as $model) {
            $model::query()->delete();
        }

        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Membresia', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    private function tokenFor(User $user): string
    {
        $plaintext = (string) Str::uuid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plaintext)]);

        return $plaintext;
    }

    private function clientFor(User $user): Client
    {
        return Client::create(['user_id' => (string) $user->id, 'telefono' => '5551230000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    private function appointmentFor(Client $client, Service $service): Appointment
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada',
        ]);
    }
}
