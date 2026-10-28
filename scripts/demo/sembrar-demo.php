<?php

/*
 * Datos de DEMOSTRACIÓN para el entorno aislado de desarrollo (mongo-dev, base urbanblade_dev).
 * Crea una cuenta de prueba por rol, el catálogo y un historial de citas ficticio para poder
 * recorrer la aplicación y tomar capturas del manual sin tocar datos reales.
 *
 * Uso (contenedor aislado, nunca el que apunta a Atlas):
 *   docker exec ub-demo-api php artisan tinker --execute="include 'scripts/demo/sembrar-demo.php';"
 *
 * El script ABORTA si la conexión no resuelve a mongo-dev/urbanblade_dev.
 * Todas las cuentas son ficticias (@urbanblade.test) y comparten la contraseña de prueba de abajo.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

const DEMO_PASSWORD = 'Prueba-UrbanBlade-2026!';

$db = (string) config('database.connections.mongodb.database');
$dsn = (string) config('database.connections.mongodb.dsn');
if ($db !== 'urbanblade_dev' || ! str_contains($dsn, 'mongo-dev') || str_contains($dsn, 'mongodb.net')) {
    echo "ABORTADO: la conexión no es mongo-dev/urbanblade_dev.\n";

    return;
}

function demo_paso(string $nombre, callable $fn): void
{
    try {
        $fn();
        echo "ok  $nombre\n";
    } catch (Throwable $e) {
        echo "ERR $nombre: ".substr($e->getMessage(), 0, 160)."\n";
    }
}

demo_paso('roles y permisos', fn () => Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]));

function demo_usuario(string $rol, string $nombre, string $email): User
{
    $user = User::updateOrCreate(['email' => $email], [
        'name' => $nombre,
        'password' => Hash::make(DEMO_PASSWORD),
        'email_verified_at' => now(),
    ]);
    $user->syncRoles([Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web'])]);

    return $user;
}

$usuarios = [];
demo_paso('usuarios por rol', function () use (&$usuarios) {
    $usuarios['administrador'] = demo_usuario('administrador', 'Ana Administradora', 'demo-administrador@urbanblade.test');
    $usuarios['recepcionista'] = demo_usuario('recepcionista', 'Rosa Recepción', 'demo-recepcion@urbanblade.test');
    $usuarios['ingeniero'] = demo_usuario('ingeniero', 'Iván Ingeniero', 'demo-ingeniero@urbanblade.test');
    $usuarios['barbero'] = demo_usuario('barbero', 'Nava Panther', 'demo-barbero@urbanblade.test');
    $usuarios['barbero2'] = demo_usuario('barbero', 'Bruno Raven', 'demo-barbero2@urbanblade.test');
    $usuarios['cliente'] = demo_usuario('cliente', 'Carlos Cliente', 'demo-cliente@urbanblade.test');
    $usuarios['cliente2'] = demo_usuario('cliente', 'Diego Demo', 'demo-cliente2@urbanblade.test');
});

demo_paso('datos de la barbería', function () {
    BarbershopSetting::query()->delete();
    BarbershopSetting::create([
        'nombre' => 'UrbanBlade Centro', 'direccion' => 'Av. Juárez 120, Centro', 'telefono' => '+52 55 0000 0000',
        'horario_apertura' => '09:00', 'horario_cierre' => '21:00', 'politica_cancelacion' => 24,
    ]);
});

$servicios = [];
demo_paso('servicios', function () use (&$servicios) {
    foreach ([['Corte clásico', 150, 30, 'corte'], ['Barba y toalla caliente', 100, 30, 'barba'], ['Corte + barba', 230, 60, 'combo'], ['Afeitado clásico', 120, 30, 'barba']] as [$n, $p, $d, $c]) {
        $servicios[$n] = Service::updateOrCreate(['nombre' => $n], ['categoria' => $c, 'precio' => $p, 'duracion_min' => $d, 'descripcion' => 'Servicio de demostración.', 'activo' => true]);
    }
});

$barberos = [];
demo_paso('barberos y horarios', function () use (&$barberos, $usuarios) {
    $barberos[0] = Barber::updateOrCreate(['user_id' => (string) $usuarios['barbero']->id], ['nombre' => 'Nava Panther', 'especialidad' => 'Corte clásico', 'descripcion' => 'Especialista en cortes clásicos y fade.', 'activo' => true, 'comision_pct' => 40]);
    $barberos[1] = Barber::updateOrCreate(['user_id' => (string) $usuarios['barbero2']->id], ['nombre' => 'Bruno Raven', 'especialidad' => 'Barba', 'descripcion' => 'Barba, navaja y detalle.', 'activo' => true, 'comision_pct' => 40]);
    foreach ($barberos as $b) {
        BarberSchedule::where('barber_id', (string) $b->id)->delete();
        foreach ([1, 2, 3, 4, 5, 6] as $dia) {
            BarberSchedule::create(['barber_id' => (string) $b->id, 'day_of_week' => $dia, 'start_time' => '09:00:00', 'end_time' => '21:00:00', 'is_working' => true]);
        }
    }
});

demo_paso('productos y plan de membresía', function () {
    foreach ([['Cera Modeladora', 'cuidado', 80, 150], ['Aceite para barba', 'barba', 90, 180], ['Shampoo mentolado', 'cuidado', 70, 140], ['Peine de madera', 'accesorios', 30, 70]] as [$n, $c, $compra, $venta]) {
        Product::updateOrCreate(['nombre' => $n], ['categoria' => $c, 'descripcion' => 'Producto de demostración.', 'precio_compra' => $compra, 'precio_venta' => $venta, 'stock_actual' => 20, 'stock_minimo' => 5, 'tipo' => Product::TYPE_SALE, 'activo' => true]);
    }
    MembershipPlan::updateOrCreate(['nombre' => 'Plan Oro'], ['descripcion' => 'Descuento en todos tus servicios.', 'precio_mensual' => 299, 'descuento_pct' => 20, 'stripe_product_id' => null, 'stripe_price_id' => null, 'activo' => true]);
});

$clientes = [];
demo_paso('perfiles de cliente', function () use (&$clientes, $usuarios) {
    $clientes[0] = Client::updateOrCreate(['user_id' => (string) $usuarios['cliente']->id], ['telefono' => '5512345678', 'fecha_nacimiento' => '1995-04-12', 'sexo' => 'masculino', 'nivel' => 'regular', 'puntos' => 60, 'total_citas' => 6]);
    $clientes[1] = Client::updateOrCreate(['user_id' => (string) $usuarios['cliente2']->id], ['telefono' => '5598765432', 'fecha_nacimiento' => '1990-09-03', 'sexo' => 'masculino', 'nivel' => 'nuevo', 'puntos' => 10, 'total_citas' => 1]);
});

demo_paso('historial de citas del cliente', function () use ($clientes, $barberos, $servicios) {
    Appointment::query()->where('client_id', (string) $clientes[0]->id)->forceDelete();
    $corte = $servicios['Corte clásico'];
    $combo = $servicios['Corte + barba'];
    // 5 citas completadas con su pago verificado.
    foreach ([[-10, '11:00', $corte, 0], [-24, '16:00', $combo, 1], [-38, '10:00', $corte, 0], [-52, '17:00', $corte, 1], [-66, '12:00', $combo, 0]] as [$dias, $hora, $svc, $b]) {
        $fin = \Carbon\Carbon::parse($hora)->addMinutes($svc->duracion_min)->format('H:i:s');
        $cita = Appointment::create([
            'client_id' => (string) $clientes[0]->id, 'barber_id' => (string) $barberos[$b]->id, 'service_id' => (string) $svc->id,
            'fecha' => now()->addDays($dias)->toDateString(), 'hora_inicio' => $hora.':00', 'hora_fin' => $fin,
            'estado' => 'completada', 'metodo_pago' => 'efectivo', 'precio_cobrado' => $svc->precio,
        ]);
        Payment::create(['appointment_id' => (string) $cita->id, 'monto' => $svc->precio, 'propina' => 20, 'metodo_pago' => 'efectivo', 'es_deposito' => false, 'estado' => 'verificado']);
    }
    // Una confirmada próxima y una pendiente.
    Appointment::create(['client_id' => (string) $clientes[0]->id, 'barber_id' => (string) $barberos[0]->id, 'service_id' => (string) $corte->id,
        'fecha' => now()->addDays(2)->toDateString(), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada', 'metodo_pago' => 'efectivo']);
    Appointment::create(['client_id' => (string) $clientes[0]->id, 'barber_id' => (string) $barberos[1]->id, 'service_id' => (string) $servicios['Barba y toalla caliente']->id,
        'fecha' => now()->addDays(5)->toDateString(), 'hora_inicio' => '16:00:00', 'hora_fin' => '16:30:00', 'estado' => 'pendiente', 'metodo_pago' => 'efectivo']);
});

demo_paso('clientes adicionales y agenda de hoy y de la semana', function () use ($barberos, $servicios) {
    $extra = [
        ['Elena Flores', 'demo-cliente3@urbanblade.test', 'nuevo', 10, 1], ['Marco Díaz', 'demo-cliente4@urbanblade.test', 'regular', 70, 6],
        ['Sofía Ríos', 'demo-cliente5@urbanblade.test', 'vip', 130, 11], ['Luis Soto', 'demo-cliente6@urbanblade.test', 'regular', 55, 5],
        ['Paola Vega', 'demo-cliente7@urbanblade.test', 'leyenda', 240, 21], ['Iker Mora', 'demo-cliente8@urbanblade.test', 'nuevo', 0, 0],
    ];
    $clientesExtra = [];
    foreach ($extra as $i => [$nombre, $correo, $nivel, $puntos, $citas]) {
        $user = demo_usuario('cliente', $nombre, $correo);
        $clientesExtra[] = Client::updateOrCreate(['user_id' => (string) $user->id], ['telefono' => '55'.(20000000 + $i * 111111), 'fecha_nacimiento' => '199'.($i % 9).'-0'.(($i % 9) + 1).'-1'.($i % 9), 'sexo' => 'masculino', 'nivel' => $nivel, 'puntos' => $puntos, 'total_citas' => $citas]);
    }
    foreach ($clientesExtra as $c) {
        Appointment::query()->where('client_id', (string) $c->id)->forceDelete();
    }
    $svc = array_values($servicios);
    // [cliente, barbero, servicio, dias, hora, estado, pagado]
    $agenda = [
        [0, 0, 0, 0, '09:00', 'completada', true], [1, 1, 1, 0, '10:00', 'completada', true], [2, 0, 2, 0, '11:30', 'en_proceso', false],
        [3, 1, 0, 0, '13:00', 'confirmada', false], [4, 0, 3, 0, '15:00', 'confirmada', false], [5, 1, 0, 0, '17:30', 'pendiente', false],
        [0, 1, 0, 1, '10:00', 'confirmada', false], [2, 0, 1, 2, '12:00', 'pendiente', false], [3, 0, 0, -3, '16:00', 'completada', true],
        [4, 1, 2, -5, '11:00', 'completada', true], [1, 0, 0, -7, '14:00', 'no_asistio', false], [5, 1, 3, -9, '12:30', 'cancelada', false],
    ];
    foreach ($agenda as [$ci, $bi, $si, $dias, $hora, $estado, $pagado]) {
        $s = $svc[$si];
        $fin = \Carbon\Carbon::parse($hora)->addMinutes($s->duracion_min)->format('H:i:s');
        $cita = Appointment::create(['client_id' => (string) $clientesExtra[$ci]->id, 'barber_id' => (string) $barberos[$bi]->id, 'service_id' => (string) $s->id,
            'fecha' => now()->addDays($dias)->toDateString(), 'hora_inicio' => $hora.':00', 'hora_fin' => $fin, 'estado' => $estado, 'metodo_pago' => 'efectivo',
            'precio_cobrado' => $estado === 'completada' ? $s->precio : null]);
        if ($pagado) {
            Payment::create(['appointment_id' => (string) $cita->id, 'monto' => $s->precio, 'propina' => 10, 'metodo_pago' => $ci % 2 ? 'tarjeta' : 'efectivo', 'es_deposito' => false, 'estado' => 'verificado']);
        }
    }
});

echo "\nCuentas (correo): demo-administrador, demo-recepcion, demo-barbero, demo-barbero2, demo-ingeniero, demo-cliente, demo-cliente2 .. demo-cliente8 @urbanblade.test\n";
echo 'Usuarios: '.User::count().' | Citas: '.Appointment::count()."\n";
