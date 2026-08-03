<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\Role;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;

class BarberSeeder extends Seeder
{
    private const TOTAL = 50;

    private const ESPECIALIDADES = [
        'Fades y degradados', 'Cortes clasicos', 'Diseno de barba', 'Afeitado a navaja',
        'Color y tratamientos', 'Cortes infantiles', 'Estilos modernos', 'Barbero senior',
    ];

    private const DOMAINS = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.mx'];

    /**
     * Insercion masiva: assignRole() por registro es demasiado lento a esta
     * escala (round-trip + refresco de cache de permisos por llamada), asi
     * que el role_id (mismo campo que assignRole() deja embebido en Mongo)
     * se escribe directo en el insert.
     */
    public function run(): void
    {
        $faker = FakerFactory::create('es_ES');
        $password = env('BARBER_SEED_PASSWORD') ?: Str::password(14);
        $hashedPassword = Hash::make($password);
        $roleId = (string) Role::where('name', 'barbero')->firstOrFail()->id;
        $now = now();

        $userRows = [];
        $barberRows = [];

        for ($i = 1; $i <= self::TOTAL; $i++) {
            $name = $faker->firstNameMale().' '.$faker->lastName().' '.$faker->lastName();
            $email = Str::slug($name, '.').".b{$i}@".self::DOMAINS[array_rand(self::DOMAINS)];
            $telefono = '55'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
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

            $especialidades = collect(self::ESPECIALIDADES)->random(random_int(2, 4))->values()->all();

            $barberRows[] = [
                'user_id' => (string) $userId,
                'nombre' => $name,
                'especialidad' => $especialidades[0],
                // 'especialidades' es string separado por comas en todo el
                // resto del proyecto (explode(',', ...) en vistas/controllers),
                // NO un array -- guardarlo como array rompe welcome/perfil/etc.
                'especialidades' => implode(', ', $especialidades),
                'telefono' => $telefono,
                'descripcion' => "Barbero profesional especializado en {$especialidades[0]} con años de experiencia en UrbanBlade.",
                'activo' => true,
                'slug' => Str::slug($name).'-'.$i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        User::insert($userRows);
        Barber::insert($barberRows);

        $this->command->info('Barberos sembrados: '.self::TOTAL);
        if (! env('BARBER_SEED_PASSWORD')) {
            $this->command->warn("Password compartida generada (guardala, no se repite): {$password}");
        }
    }
}
