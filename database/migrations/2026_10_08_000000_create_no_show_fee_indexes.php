<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Cargos por inasistencia (flujo de citas V2, etapa 2): una cita genera como máximo UN cargo (índice único por
 * appointment_id, que hace idempotente marcar «no asistió» dos veces) y se consulta por cliente y estado para saber
 * si tiene un adeudo pendiente al reservar. Mongo no necesita crear la colección: solo sus índices.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        try {
            Schema::connection('mongodb')->table('no_show_fees', function (Blueprint $c) {
                $c->unique(['appointment_id'], 'no_show_fee_appointment_unique');
                $c->index(['client_id', 'estado'], 'no_show_fee_client_estado');
            });
        } catch (CommandException $e) {
            // 85/86: el índice ya existe (migración repetida o creado a mano).
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::connection('mongodb')->table('no_show_fees', function (Blueprint $c) {
                $c->dropIndex('no_show_fee_appointment_unique');
                $c->dropIndex('no_show_fee_client_estado');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};
