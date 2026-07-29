<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * La cuenta admin real no lleva password hardcodeado en el codigo. Se puede
     * fijar via ADMIN_SEED_EMAIL y ADMIN_SEED_PASSWORD en el .env; si no hay
     * password, se genera una aleatoria y se imprime una sola vez.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_SEED_EMAIL', 'kikeramirez160418@gmail.com');
        $password = env('ADMIN_SEED_PASSWORD') ?: Str::password(16);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Kike Ramirez',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if (! $user->hasRole('administrador')) {
            $user->assignRole('administrador');
        }

        $this->command->info("Administrador sembrado: {$email}");
        if (! env('ADMIN_SEED_PASSWORD')) {
            $this->command->warn("Password generada (guardala, no se repite): {$password}");
        }
    }
}
