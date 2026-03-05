<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@barberia.local'],
            [
                'name' => 'Administrador Barbería',
                'password' => 'password',
            ]
        );

        $admin->assignRole('administrador');
    }
}
