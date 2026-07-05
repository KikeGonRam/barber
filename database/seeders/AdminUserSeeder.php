<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'al222310427@gmail.com'],
            [
                'name' => 'Administrador UrbanBlade',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@Urban2025!')),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('administrador');
    }
}
