<?php

use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Fase 4 (pagos/pedidos/inventario) del roadmap: PaymentService::create()/
 * uploadTransferReceipt() solo tenian PaymentRepository::existsForAppointment()
 * como guardia -- un check-then-create de aplicacion, sin ninguna garantia a
 * nivel de base de datos. Dos requests casi simultaneas para la misma cita
 * (p.ej. un reintento de webhook de Stripe cruzandose con un doble-click de
 * "cobrar" en recepcion) pueden pasar ambas la validacion antes de que
 * cualquiera termine de escribir -- mismo patron TOCTOU que el de citas en
 * Fase 3, aqui con impacto directo en dinero real y puntos de lealtad
 * duplicados.
 *
 * partialFilterExpression filtra por bloquea_cita:true (equivalente a
 * "estado != rechazado", mismo criterio que existsForAppointment()) en vez
 * de excluir 'rechazado' directamente: MongoDB no soporta $ne/$nin dentro de
 * un partial index filter, solo expresiones de igualdad/$exists/comparacion
 * -- ver el docblock de Payment::booted() para el campo derivado.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        // Backfill: los pagos que ya existian antes de esta migracion nunca
        // pasaron por Payment::booted()'s saving hook, asi que bloquea_cita
        // no existe en ellos todavia -- sin este paso quedan fuera del
        // indice (sin proteccion) hasta que alguien los vuelva a guardar.
        Payment::where('estado', '!=', Payment::ESTADO_RECHAZADO)->update(['bloquea_cita' => true]);
        Payment::where('estado', Payment::ESTADO_RECHAZADO)->update(['bloquea_cita' => false]);

        try {
            Schema::connection('mongodb')->table('payments', function (Blueprint $c) {
                $c->unique(['appointment_id'], 'payments_active_appointment_unique', null, [
                    'partialFilterExpression' => [
                        'bloquea_cita' => true,
                    ],
                ]);
            });
        } catch (CommandException $e) {
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::connection('mongodb')->table('payments', function (Blueprint $c) {
                $c->dropIndex('payments_active_appointment_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};
