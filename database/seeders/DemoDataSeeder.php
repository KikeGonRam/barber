<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPackage;
use App\Models\GiftCard;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RaffleResult;
use App\Models\Referral;
use App\Models\Service;
use App\Models\ServiceCombo;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\Waitlist;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Dataset pequeño y realista, solo para verificar en local/staging que el
 * dashboard de `spark` (y las features recientes: gift cards, membresías,
 * paquetes, combos, referidos, lista de espera, rifas, reseñas, comisión de
 * barberos) funcionan con datos reales de extremo a extremo.
 *
 * A propósito NO reutiliza BarberSeeder/ClientSeeder/AppointmentSeeder/
 * OrderSeeder a su escala real (50 barberos / 1500 clientes / miles de
 * registros) — ver guardrail #12 de barber (incidente real de 2026-09-04).
 * Usa Eloquent::create() normal (no bulk insert) porque a esta escala
 * pequeña el costo por registro no importa y así los eventos de modelo
 * (slug, code, activa, bloquea_horario, etc.) disparan correctamente.
 *
 * Uso: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const N_BARBEROS = 6;

    private const N_CLIENTES = 20;

    private const N_CITAS = 100;

    /** @var array<int, Barber> */
    private array $barbers = [];

    /** @var array<int, Client> */
    private array $clients = [];

    /** @var array<int, Service> */
    private array $services = [];

    /** @var array<int, Appointment> citas completadas, reutilizadas por seedPagos()/seedPedidos() */
    private array $completedAppointments = [];

    public function run(): void
    {
        $this->call([
            BarbershopSettingSeeder::class,
            ServiceSeeder::class,
            ProductSeeder::class,
        ]);
        $this->services = Service::query()->get()->all();

        $this->seedBarberos();
        $this->call(BarberScheduleSeeder::class);
        $this->seedClientes();
        $this->seedCitas();
        $this->seedPagos();
        $this->call(LoyaltyTransactionSeeder::class);
        $this->seedPedidos();
        $this->call([
            WorkSeeder::class,
            WorkImageSeeder::class,
            CommentSeeder::class,
            ReactionSeeder::class,
        ]);
        $this->seedGiftCards();
        $this->seedMembresias();
        $this->seedPaquetesYCombos();
        $this->seedReferidos();
        $this->seedListaEspera();
        $this->seedRifas();
        $this->seedResenas();

        $this->command->info('Dataset de demostración sembrado completo.');
    }

    private function seedBarberos(): void
    {
        $faker = FakerFactory::create('es_MX');
        $especialidades = [
            'Fades y degradados', 'Cortes clásicos', 'Diseño de barba',
            'Afeitado a navaja', 'Color y tratamientos', 'Cortes infantiles',
        ];
        // La mitad con comisión configurada, la mitad sin (default 0) — para
        // que la pestaña "Comisiones y Reseñas" tenga ambos casos que mostrar.
        $comisiones = [8.0, null, 12.0, null, 15.0, 10.0];

        for ($i = 0; $i < self::N_BARBEROS; $i++) {
            $name = $faker->firstNameMale().' '.$faker->lastName();
            $user = User::create([
                'name' => $name,
                'email' => Str::slug($name, '.').".demo{$i}@urbanblade.test",
                'password' => Hash::make('demo12345'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('barbero');

            $especialidad = $especialidades[$i % count($especialidades)];

            $this->barbers[] = Barber::create([
                'user_id' => (string) $user->id,
                'nombre' => $name,
                'especialidad' => $especialidad,
                'especialidades' => $especialidad,
                'telefono' => '55'.random_int(10000000, 99999999),
                'descripcion' => "Barbero profesional de UrbanBlade especializado en {$especialidad}.",
                'activo' => true,
                'comision_pct' => $comisiones[$i],
            ]);
        }

        $this->command->info('Barberos demo: '.count($this->barbers));
    }

    private function seedClientes(): void
    {
        $faker = FakerFactory::create('es_MX');

        for ($i = 0; $i < self::N_CLIENTES; $i++) {
            $isMale = random_int(0, 1) === 1;
            $name = ($isMale ? $faker->firstNameMale() : $faker->firstNameFemale()).' '.$faker->lastName();
            $user = User::create([
                'name' => $name,
                'email' => Str::slug($name, '.').".demo{$i}@urbanblade.test",
                'password' => Hash::make('demo12345'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('cliente');

            $this->clients[] = Client::create([
                'user_id' => (string) $user->id,
                'telefono' => '55'.random_int(10000000, 99999999),
                'fecha_nacimiento' => Carbon::now()->subYears(random_int(18, 55))->subDays(random_int(0, 365)),
                'sexo' => $isMale ? 'masculino' : 'femenino',
            ]);
        }

        $this->command->info('Clientes demo: '.count($this->clients));
    }

    private function seedCitas(): void
    {
        $metodos = ['efectivo', 'tarjeta', 'transferencia'];
        // ~67% completada, ~22% cancelada, ~11% no_asistio.
        $estadosPasado = ['completada', 'completada', 'completada', 'completada',
            'completada', 'completada', 'cancelada', 'cancelada', 'no_asistio'];
        $estadosFuturo = ['pendiente', 'confirmada'];

        $creadas = 0;
        for ($i = 0; $i < self::N_CITAS; $i++) {
            $client = $this->clients[array_rand($this->clients)];
            $barber = $this->barbers[array_rand($this->barbers)];
            $service = $this->services[array_rand($this->services)];
            $hora = random_int(9, 19);
            $minuto = [0, 15, 30, 45][array_rand([0, 15, 30, 45])];

            $esFutura = $i < 12; // últimas 12 citas, próximos 14 días.

            if ($esFutura) {
                $fecha = Carbon::today()->addDays(random_int(1, 14));
                $estado = $estadosFuturo[array_rand($estadosFuturo)];
                $precioCobrado = null;
                $metodoPago = null;
            } else {
                $fecha = Carbon::today()->subDays(random_int(0, 89));
                $estado = $estadosPasado[array_rand($estadosPasado)];
                $precioCobrado = $estado === 'completada' ? (float) $service->precio : null;
                $metodoPago = $estado === 'completada' ? $metodos[array_rand($metodos)] : null;
            }

            $appointment = Appointment::create([
                'client_id' => (string) $client->id,
                'barber_id' => (string) $barber->id,
                'service_id' => (string) $service->id,
                'fecha' => $fecha,
                'hora_inicio' => sprintf('%02d:%02d:00', $hora, $minuto),
                'hora_fin' => sprintf('%02d:%02d:00', $hora, min($minuto + (int) $service->duracion_min, 59)),
                'estado' => $estado,
                'metodo_pago' => $metodoPago,
                'precio_cobrado' => $precioCobrado,
                'cancelada_en' => $estado === 'cancelada' ? $fecha->copy()->addHours(random_int(1, 10)) : null,
                'created_at' => $esFutura ? now() : $fecha->copy()->addHours($hora),
            ]);

            if ($estado === 'completada') {
                $this->completedAppointments[] = $appointment;
            }
            $creadas++;
        }

        $this->command->info("Citas demo: {$creadas} ({$this->countCompleted()} completadas)");
    }

    private function countCompleted(): int
    {
        return count($this->completedAppointments);
    }

    private function seedPagos(): void
    {
        $staffId = (string) (User::whereRoleName('recepcionista')->first()?->id
            ?? User::whereRoleName('administrador')->first()?->id
            ?? '');

        $total = 0;
        foreach ($this->completedAppointments as $appt) {
            $monto = (float) $appt->precio_cobrado;
            $propina = round($monto * (random_int(0, 20) / 100), 2);

            // La mayoría verificados; unas pocas transferencias sin revisar o
            // rechazadas, para que "Pagos y Calidad" tenga algo que auditar.
            if ($appt->metodo_pago === 'transferencia' && random_int(1, 100) <= 25) {
                $estado = random_int(1, 100) <= 60
                    ? Payment::ESTADO_PENDIENTE_VERIFICACION
                    : Payment::ESTADO_RECHAZADO;
            } else {
                $estado = Payment::ESTADO_VERIFICADO;
            }

            Payment::create([
                'appointment_id' => (string) $appt->id,
                'monto' => $monto,
                'metodo_pago' => $appt->metodo_pago,
                'propina' => $propina,
                'created_by' => $staffId,
                'estado' => $estado,
                'created_at' => $appt->created_at,
            ]);
            $total++;
        }

        $this->command->info("Pagos demo: {$total}");
    }

    private function seedPedidos(): void
    {
        $products = Product::query()->where('precio_venta', '>', 0)->get();
        if ($products->isEmpty()) {
            return;
        }

        $metodos = ['efectivo', 'tarjeta', 'transferencia'];
        $total = 0;

        // Add-ons sobre ~30% de las citas completadas.
        foreach ($this->completedAppointments as $appt) {
            if (random_int(1, 100) > 30) {
                continue;
            }
            $items = $this->randomOrderItems($products);
            Order::create([
                'client_id' => (string) $appt->client_id,
                'folio' => $this->uniqueFolio(),
                'items' => $items,
                'total' => round(array_sum(array_column($items, 'subtotal')), 2),
                'estado' => 'entregado',
                'tipo' => 'cita',
                'appointment_id' => (string) $appt->id,
                'metodo_pago' => $appt->metodo_pago,
                'entregado_en' => $appt->created_at,
                'created_at' => $appt->created_at,
            ]);
            $total++;
        }

        // Pedidos sueltos de tienda.
        for ($i = 0; $i < 15; $i++) {
            $cliente = $this->clients[array_rand($this->clients)];
            $items = $this->randomOrderItems($products);
            $estado = random_int(1, 100) <= 85 ? 'entregado' : 'cancelado';
            $fecha = Carbon::now()->subDays(random_int(0, 89));

            Order::create([
                'client_id' => (string) $cliente->id,
                'folio' => $this->uniqueFolio(),
                'items' => $items,
                'total' => round(array_sum(array_column($items, 'subtotal')), 2),
                'estado' => $estado,
                'tipo' => 'tienda',
                'metodo_pago' => $estado === 'entregado' ? $metodos[array_rand($metodos)] : null,
                'entregado_en' => $estado === 'entregado' ? $fecha : null,
                'created_at' => $fecha,
            ]);
            $total++;
        }

        $this->command->info("Pedidos demo: {$total}");
    }

    /** @return array<int, array{nombre:string,cantidad:int,precio:float,subtotal:float}> */
    private function randomOrderItems($products): array
    {
        $picked = $products->random(min(random_int(1, 3), $products->count()));

        return collect($picked)->map(function ($p) {
            $cantidad = random_int(1, 2);

            return [
                'nombre' => $p->nombre,
                'cantidad' => $cantidad,
                'precio' => (float) $p->precio_venta,
                'subtotal' => round((float) $p->precio_venta * $cantidad, 2),
            ];
        })->values()->all();
    }

    private function uniqueFolio(): string
    {
        do {
            $folio = 'P-'.strtoupper(Str::random(6));
        } while (Order::where('folio', $folio)->exists());

        return $folio;
    }

    private function seedGiftCards(): void
    {
        $estados = [
            GiftCard::ESTADO_ACTIVA, GiftCard::ESTADO_ACTIVA, GiftCard::ESTADO_ACTIVA,
            GiftCard::ESTADO_AGOTADA, GiftCard::ESTADO_EXPIRADA, GiftCard::ESTADO_CANCELADA,
        ];
        $montos = [200, 300, 500, 500, 1000, 250];
        $metodos = ['tarjeta', 'efectivo'];

        foreach ($estados as $i => $estado) {
            $monto = $montos[$i];
            $saldo = match ($estado) {
                GiftCard::ESTADO_AGOTADA => 0.0,
                GiftCard::ESTADO_ACTIVA => round($monto * (random_int(30, 90) / 100), 2),
                default => (float) $monto,
            };
            // La última la compra un invitado sin cuenta (comprador_nombre libre).
            $comprador = $i < 5 ? $this->clients[array_rand($this->clients)] : null;

            GiftCard::create([
                'monto_inicial' => $monto,
                'saldo' => $saldo,
                'comprador_client_id' => $comprador?->id,
                'comprador_nombre' => $comprador ? null : 'Cliente invitado',
                'destinatario_email' => "destinatario{$i}@urbanblade.test",
                'metodo_pago' => $metodos[array_rand($metodos)],
                'comprado_en' => Carbon::now()->subDays(random_int(5, 90)),
                'expira_en' => Carbon::now()->addMonths(6),
                'estado' => $estado,
            ]);
        }

        $this->command->info('Gift cards demo: '.count($estados));
    }

    private function seedMembresias(): void
    {
        $planes = [
            MembershipPlan::create([
                'nombre' => 'Básico', 'descripcion' => '10% de descuento en todos los servicios.',
                'precio_mensual' => 199, 'descuento_pct' => 10,
                'stripe_product_id' => 'prod_demo_basico', 'stripe_price_id' => 'price_demo_basico', 'activo' => true,
            ]),
            MembershipPlan::create([
                'nombre' => 'Plus', 'descripcion' => '15% de descuento y prioridad de agenda.',
                'precio_mensual' => 349, 'descuento_pct' => 15,
                'stripe_product_id' => 'prod_demo_plus', 'stripe_price_id' => 'price_demo_plus', 'activo' => true,
            ]),
            MembershipPlan::create([
                'nombre' => 'Premium', 'descripcion' => '20% de descuento y beneficios VIP.',
                'precio_mensual' => 549, 'descuento_pct' => 20,
                'stripe_product_id' => 'prod_demo_premium', 'stripe_price_id' => 'price_demo_premium', 'activo' => true,
            ]),
        ];

        $estados = [
            ClientMembership::ESTADO_ACTIVA, ClientMembership::ESTADO_ACTIVA, ClientMembership::ESTADO_ACTIVA,
            ClientMembership::ESTADO_ACTIVA, ClientMembership::ESTADO_ACTIVA,
            ClientMembership::ESTADO_PAGO_FALLIDO, ClientMembership::ESTADO_CANCELADA, ClientMembership::ESTADO_CANCELADA,
        ];
        $miembros = collect($this->clients)->random(count($estados))->values();

        $totalInvoices = 0;
        foreach ($estados as $i => $estado) {
            $cliente = $miembros[$i];
            $plan = $planes[array_rand($planes)];

            $membership = ClientMembership::create([
                'client_id' => (string) $cliente->id,
                'membership_plan_id' => (string) $plan->id,
                'stripe_customer_id' => "cus_demo_{$i}",
                'stripe_subscription_id' => "sub_demo_{$i}",
                'estado' => $estado,
                'cancelar_al_finalizar' => $estado === ClientMembership::ESTADO_ACTIVA && random_int(1, 100) <= 20,
                'periodo_actual_fin' => Carbon::now()->addDays(random_int(1, 30)),
            ]);

            $meses = random_int(1, 4);
            for ($m = 0; $m < $meses; $m++) {
                MembershipInvoice::create([
                    'client_membership_id' => (string) $membership->id,
                    'monto' => $plan->precio_mensual,
                    'stripe_invoice_id' => "in_demo_{$i}_{$m}",
                    'pagado_en' => Carbon::now()->subMonths($meses - $m),
                ]);
                $totalInvoices++;
            }
        }

        $this->command->info('Membresías demo: '.count($estados)." suscripciones, {$totalInvoices} cobros");
    }

    private function seedPaquetesYCombos(): void
    {
        $corte = collect($this->services)->firstWhere('categoria', 'Cortes') ?? $this->services[0];
        $combo = collect($this->services)->firstWhere('categoria', 'Combos') ?? $this->services[0];

        $plantillas = [
            ServicePackage::create([
                'nombre' => '5 Cortes', 'service_id' => (string) $corte->id,
                'cantidad_usos' => 5, 'precio' => round($corte->precio * 5 * 0.85, 2),
                'vigencia_dias' => 180, 'activo' => true,
            ]),
            ServicePackage::create([
                'nombre' => '10 Cortes', 'service_id' => (string) $corte->id,
                'cantidad_usos' => 10, 'precio' => round($corte->precio * 10 * 0.8, 2),
                'vigencia_dias' => 365, 'activo' => true,
            ]),
            ServicePackage::create([
                'nombre' => '5 Combos', 'service_id' => (string) $combo->id,
                'cantidad_usos' => 5, 'precio' => round($combo->precio * 5 * 0.85, 2),
                'vigencia_dias' => 180, 'activo' => true,
            ]),
        ];

        $metodos = ['tarjeta', 'transferencia'];
        $compradores = collect($this->clients)->random(10)->values();
        foreach ($compradores as $cliente) {
            $plantilla = $plantillas[array_rand($plantillas)];
            $usosRestantes = random_int(0, (int) $plantilla->cantidad_usos);

            ClientPackage::create([
                'client_id' => (string) $cliente->id,
                'service_package_id' => (string) $plantilla->id,
                'service_id' => (string) $plantilla->service_id,
                'usos_totales' => $plantilla->cantidad_usos,
                'usos_restantes' => $usosRestantes,
                'precio_pagado' => $plantilla->precio,
                'metodo_pago' => $metodos[array_rand($metodos)],
                'comprado_en' => Carbon::now()->subDays(random_int(5, 120)),
                'expira_en' => Carbon::now()->addDays((int) $plantilla->vigencia_dias),
                'estado' => $usosRestantes === 0 ? ClientPackage::ESTADO_AGOTADO : ClientPackage::ESTADO_ACTIVO,
            ]);
        }

        $combosData = [
            ['nombre' => 'Combo Fiesta', 'precio_combo' => 500, 'descuento' => 50, 'servicios' => 3],
            ['nombre' => 'Combo Exprés', 'precio_combo' => 280, 'descuento' => 20, 'servicios' => 2],
            ['nombre' => 'Combo Novio', 'precio_combo' => 650, 'descuento' => 80, 'servicios' => 3],
        ];
        foreach ($combosData as $data) {
            $combo = ServiceCombo::create([
                'nombre' => $data['nombre'],
                'precio_combo' => $data['precio_combo'],
                'descuento' => $data['descuento'],
            ]);
            $serviceIds = collect($this->services)
                ->random(min($data['servicios'], count($this->services)))
                ->pluck('id')->map(fn ($id) => (string) $id)->all();
            $combo->services()->attach($serviceIds);
        }

        $this->command->info('Paquetes demo: '.count($plantillas).' plantillas, '.count($compradores).' comprados. Combos demo: '.count($combosData));
    }

    private function seedReferidos(): void
    {
        $estados = [
            Referral::ESTADO_COMPLETADO, Referral::ESTADO_COMPLETADO, Referral::ESTADO_COMPLETADO,
            Referral::ESTADO_COMPLETADO, Referral::ESTADO_COMPLETADO, Referral::ESTADO_COMPLETADO,
            Referral::ESTADO_PENDIENTE, Referral::ESTADO_PENDIENTE, Referral::ESTADO_PENDIENTE, Referral::ESTADO_PENDIENTE,
        ];

        // Un cliente solo puede ser "referido" una vez (indice unico
        // referral_referee_unique) -- se baraja la lista y se toman los
        // primeros N como referee, garantizando que no se repita ninguno.
        $referees = collect($this->clients)->shuffle()->values();

        foreach ($estados as $i => $estado) {
            $referee = $referees[$i];
            $referrer = collect($this->clients)->reject(fn ($c) => (string) $c->id === (string) $referee->id)->random();

            Referral::create([
                'referrer_client_id' => (string) $referrer->id,
                'referee_client_id' => (string) $referee->id,
                'estado' => $estado,
                'recompensa_otorgada_en' => $estado === Referral::ESTADO_COMPLETADO
                    ? Carbon::now()->subDays(random_int(1, 60)) : null,
            ]);
        }

        $this->command->info('Referidos demo: '.count($estados));
    }

    private function seedListaEspera(): void
    {
        $estados = [
            Waitlist::ESTADO_ACTIVO, Waitlist::ESTADO_ACTIVO, Waitlist::ESTADO_ACTIVO,
            Waitlist::ESTADO_NOTIFICADO, Waitlist::ESTADO_RESERVADO,
            Waitlist::ESTADO_CANCELADO, Waitlist::ESTADO_EXPIRADO, Waitlist::ESTADO_ACTIVO,
        ];

        foreach ($estados as $estado) {
            $cliente = $this->clients[array_rand($this->clients)];
            $barbero = $this->barbers[array_rand($this->barbers)];
            $servicio = $this->services[array_rand($this->services)];

            Waitlist::create([
                'client_id' => (string) $cliente->id,
                'barber_id' => (string) $barbero->id,
                'service_id' => (string) $servicio->id,
                'fecha' => Carbon::today()->addDays(random_int(1, 21)),
                'estado' => $estado,
                'notificado_en' => in_array($estado, [Waitlist::ESTADO_NOTIFICADO, Waitlist::ESTADO_RESERVADO], true) ? now() : null,
            ]);
        }

        $this->command->info('Lista de espera demo: '.count($estados));
    }

    private function seedRifas(): void
    {
        $premios = ['Corte gratis', 'Descuento del 50%', 'Tratamiento capilar gratis', 'Combo Premium gratis', 'Producto de regalo'];
        $ganadores = collect($this->clients)->random(5)->values();

        foreach ($ganadores as $i => $cliente) {
            $reclamado = $i < 2;

            RaffleResult::create([
                'client_id' => (string) $cliente->id,
                'mes' => Carbon::now()->subMonths($i)->format('Y-m'),
                'premio' => $premios[$i % count($premios)],
                'nivel_ganador' => $cliente->nivel,
                'vence_en' => Carbon::now()->subDays($i * 20)->addDays(RaffleResult::VIGENCIA_DIAS),
                'reclamado_en' => $reclamado ? Carbon::now()->subDays(random_int(1, 10)) : null,
            ]);
        }

        $this->command->info('Rifas demo: '.count($ganadores));
    }

    private function seedResenas(): void
    {
        $comentarios = [
            'Excelente atención y muy puntual.', 'El mejor barbero de la zona.',
            'Quedé muy satisfecho con el corte.', 'Buen servicio pero tardó un poco.',
            'Totalmente recomendado.', 'Ambiente agradable y buen trato.',
        ];

        $total = 0;
        foreach ($this->barbers as $barber) {
            $n = random_int(1, 4);
            // Un cliente solo puede reseñar a un barbero una vez (indice
            // unico barber_id_1_client_id_1) -- clientes distintos por barbero.
            $reseñadores = collect($this->clients)->random(min($n, count($this->clients)))->values();
            foreach ($reseñadores as $cliente) {
                BarberReview::create([
                    'barber_id' => (string) $barber->id,
                    'client_id' => (string) $cliente->id,
                    'rating' => random_int(3, 5),
                    'comment' => $comentarios[array_rand($comentarios)],
                ]);
                $total++;
            }
        }

        $this->command->info("Reseñas demo: {$total}");
    }
}
