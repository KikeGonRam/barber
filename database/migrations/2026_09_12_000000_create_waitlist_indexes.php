<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Lista de espera (roadmap P1): un cliente no puede anotarse dos veces para
 * el mismo barbero+servicio+fecha mientras su anotación siga activa
 * (activo/notificado) -- índice único compuesto filtrado por `activa`,
 * mismo patrón que `bloquea_horario`/`bloquea_cita` de citas/pagos (Mongo
 * solo permite igualdad en partialFilterExpression, no $in/$nin).
 *
 * El segundo índice (sin unicidad) acelera la consulta de
 * WaitlistService::notifyIfAny() -- buscar anotados por barbero+servicio+
 * fecha+estado cada vez que se libera un horario.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        try {
            Schema::connection('mongodb')->table('waitlists', function (Blueprint $c) {
                $c->unique(['client_id', 'barber_id', 'service_id', 'fecha', 'activa'], 'waitlist_active_entry_unique', null, [
                    'partialFilterExpression' => [
                        'activa' => true,
                    ],
                ]);
            });
        } catch (CommandException $e) {
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }

        try {
            Schema::connection('mongodb')->table('waitlists', function (Blueprint $c) {
                $c->index(['barber_id', 'service_id', 'fecha', 'estado'], 'waitlist_lookup_index');
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
            Schema::connection('mongodb')->table('waitlists', function (Blueprint $c) {
                $c->dropIndex('waitlist_active_entry_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }

        try {
            Schema::connection('mongodb')->table('waitlists', function (Blueprint $c) {
                $c->dropIndex('waitlist_lookup_index');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};
