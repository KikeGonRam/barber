<?php

use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Depósitos anti-no-show (roadmap P1): una cita puede tener hasta DOS pagos
 * activos ahora -- el depósito (es_deposito=true) y el cobro final
 * (es_deposito=false/ausente) -- justo el "abono + saldo" que el docblock de
 * Appointment::payments() ya anticipaba. El índice único de la migración
 * 2026_09_07_000001 solo permitía UNO por cita; se reemplaza por uno
 * compuesto (appointment_id, es_deposito) que sigue garantizando "como
 * máximo un depósito activo" Y "como máximo un cobro final activo" por
 * separado, con la misma técnica de bloquea_cita (Mongo no soporta
 * $ne/$nin en partialFilterExpression, solo igualdad).
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        // Backfill: todo pago que ya existía antes de esta migración es un
        // cobro normal, nunca un depósito.
        Payment::whereNull('es_deposito')->update(['es_deposito' => false]);

        try {
            Schema::connection('mongodb')->table('payments', function (Blueprint $c) {
                $c->dropIndex('payments_active_appointment_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }

        try {
            Schema::connection('mongodb')->table('payments', function (Blueprint $c) {
                $c->unique(['appointment_id', 'es_deposito'], 'payments_active_appointment_deposito_unique', null, [
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
                $c->dropIndex('payments_active_appointment_deposito_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }

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
};
