<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;

class ClientSeeder extends Seeder
{
    private const TOTAL = 1500;

    private const CHUNK = 300;

    private const DOMAINS = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.mx', 'icloud.com'];

    /**
     * Insercion masiva por lotes: assignRole() por registro es demasiado
     * lento a esta escala, asi que el role_id (mismo campo que assignRole()
     * deja embebido en Mongo) se escribe directo en el insert.
     */
    public function run(): void
    {
        $faker = FakerFactory::create('es_ES');
        $password = env('CLIENT_SEED_PASSWORD') ?: Str::password(14);
        $hashedPassword = Hash::make($password);
        $roleId = (string) Role::where('name', 'cliente')->firstOrFail()->id;
        $now = now();

        $userRows = [];
        $clientRows = [];
        $created = 0;

        for ($i = 1; $i <= self::TOTAL; $i++) {
            $isMale = random_int(0, 1) === 1;
            $name = ($isMale ? $faker->firstNameMale() : $faker->firstNameFemale()).' '.$faker->lastName().' '.$faker->lastName();
            $email = Str::slug($name, '.').".c{$i}@".self::DOMAINS[array_rand(self::DOMAINS)];
            $telefono = '55'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $edad = random_int(18, 65);
            $userId = new ObjectId;

            $userRows[] = [
                '_id' => $userId,
                'name' => $name,
                'email' => $email,
                'password' => $hashedPassword,
                'email_verified_at' => $now,
                'role_id' => [$roleId],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $clientRows[] = [
                'user_id' => (string) $userId,
                'telefono' => $telefono,
                'fecha_nacimiento' => Carbon::now()->subYears($edad)->subDays(random_int(0, 365)),
                'nivel' => 'nuevo',
                'puntos' => 0,
                'total_citas' => 0,
                'slug' => Str::slug($name).'-'.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($userRows) >= self::CHUNK) {
                User::insert($userRows);
                Client::insert($clientRows);
                $created += count($userRows);
                $userRows = [];
                $clientRows = [];
                $this->command->info("  ...{$created}/".self::TOTAL.' clientes creados');
            }
        }

        if (! empty($userRows)) {
            User::insert($userRows);
            Client::insert($clientRows);
            $created += count($userRows);
        }

        $this->command->info('Clientes sembrados: '.$created);
        if (! env('CLIENT_SEED_PASSWORD')) {
            $this->command->warn("Password compartida generada (guardala, no se repite): {$password}");
        }
    }
}
