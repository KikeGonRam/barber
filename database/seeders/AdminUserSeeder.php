<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * La cuenta admin real no lleva password hardcodeado en el codigo (este
     * repo es publico). Se puede fijar via ADMIN_SEED_PASSWORD en el .env
     * (no versionado); si no esta definida, se genera una aleatoria y se
     * imprime una sola vez en la salida del seeder.
     */
    public function run(): void
    {
        $password = env('ADMIN_SEED_PASSWORD') ?: Str::password(16);

        $user = User::updateOrCreate(
            ['email' => 'kikermairez160418@gmail.com'],
            [
                'name' => 'Kike Ramirez',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('administrador')) {
            $user->assignRole('administrador');
        }

        $this->command->info('Administrador sembrado: kikermairez160418@gmail.com');
        if (! env('ADMIN_SEED_PASSWORD')) {
            $this->command->warn("Password generada (guardala, no se repite): {$password}");
        }
    }
}
