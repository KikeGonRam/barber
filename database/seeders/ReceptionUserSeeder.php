<?php

namespace Database\Seeders;

use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReceptionUserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create('es_ES');
        $name = $faker->firstNameFemale().' '.$faker->lastName();
        $email = Str::slug($name, '.').random_int(10, 99).'@gmail.com';
        $password = env('RECEPTION_SEED_PASSWORD') ?: Str::password(14);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('recepcionista');

        $this->command->info("Recepcionista sembrada: {$name} <{$email}>");
        if (! env('RECEPTION_SEED_PASSWORD')) {
            $this->command->warn("Password generada (guardala, no se repite): {$password}");
        }
    }
}
