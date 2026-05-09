<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ValidateUserRoles extends Command
{
    protected $signature = 'validate:user-roles';
    protected $description = 'Validate that all user roles and permissions are correctly configured';

    public function handle()
    {
        $this->info('🔍 Validating User Roles & Permissions...\n');

        $users = User::with('roles', 'permissions')->get();

        if ($users->isEmpty()) {
            $this->error('❌ No users found in database. Run seeders first!');
            return 1;
        }

        $this->info("📋 Found " . $users->count() . " users\n");

        foreach ($users as $user) {
            $roles = $user->roles->pluck('name')->join(', ') ?: 'No roles';
            $perms = $user->permissions->pluck('name')->count();

            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("👤 {$user->name} ({$user->email})");
            $this->line("   Roles: {$roles}");
            $this->line("   Direct Permissions: {$perms}");

            // Show inherited permissions
            $allPerms = $user->getAllPermissions();
            $this->line("   Total Permissions (inherited): " . $allPerms->count());
            
            if ($allPerms->count() > 0) {
                $this->line("\n   Permissions:");
                foreach ($allPerms->take(5) as $perm) {
                    $this->line("      ✓ {$perm->name}");
                }
                if ($allPerms->count() > 5) {
                    $this->line("      ... and " . ($allPerms->count() - 5) . " more");
                }
            }
            $this->line("");
        }

        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        // Validate specific permissions
        $this->info("\n✅ Validating Permission Matrix...\n");

        $adminUser = User::whereHas('roles', fn ($q) => $q->where('name', 'administrador'))->first();
        $recepUser = User::whereHas('roles', fn ($q) => $q->where('name', 'recepcionista'))->first();
        $barberUser = User::whereHas('roles', fn ($q) => $q->where('name', 'barbero'))->first();
        $clientUser = User::whereHas('roles', fn ($q) => $q->where('name', 'cliente'))->first();

        // Check Administrador permissions
        if ($adminUser) {
            $this->validateRole($adminUser, 'Administrador', [
                'dashboard.ver' => true,
                'usuarios.gestionar' => true,
                'barberos.gestionar' => true,
                'clientes.gestionar' => true,
                'servicios.gestionar' => true,
                'citas.gestionar' => true,
                'pagos.gestionar' => true,
                'inventario.gestionar' => true,
                'reportes.ver' => true,
                'configuracion.gestionar' => true,
                'logs.ver' => true,
            ]);
        }

        // Check Recepcionista permissions
        if ($recepUser) {
            $this->validateRole($recepUser, 'Recepcionista', [
                'dashboard.ver' => true,
                'clientes.gestionar' => true,
                'citas.gestionar' => true,
                'pagos.gestionar' => true,
                'inventario.ver' => true,
                'reportes.ver' => true,
                'usuarios.gestionar' => false,
                'barberos.gestionar' => false,
            ]);
        }

        // Check Barbero permissions
        if ($barberUser) {
            $this->validateRole($barberUser, 'Barbero', [
                'dashboard.ver' => true,
                'citas.ver_propias' => true,
                'clientes.gestionar' => false,
                'pagos.gestionar' => false,
                'configuracion.gestionar' => false,
            ]);
        }

        // Check Cliente permissions
        if ($clientUser) {
            $this->validateRole($clientUser, 'Cliente', [
                'dashboard.ver' => true,
                'usuarios.gestionar' => false,
                'clientes.gestionar' => false,
                'citas.gestionar' => false,
            ]);
        }

        $this->info("\n✅ Role validation complete!\n");
        return 0;
    }

    protected function validateRole($user, $roleName, $permissions)
    {
        $this->line("\n📋 {$roleName}:");
        $allPass = true;

        foreach ($permissions as $permission => $shouldHave) {
            $hasPermission = $user->hasPermissionTo($permission);
            
            if ($hasPermission === $shouldHave) {
                $status = $shouldHave ? '✓' : '✗';
                $this->line("   {$status} {$permission}");
            } else {
                $this->line("   ❌ {$permission} (Expected: " . ($shouldHave ? 'YES' : 'NO') . ", Got: " . ($hasPermission ? 'YES' : 'NO') . ")");
                $allPass = false;
            }
        }

        return $allPass;
    }
}
