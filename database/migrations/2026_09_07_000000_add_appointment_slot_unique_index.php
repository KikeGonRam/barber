<?php

use App\Models\Appointment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Fase 3 (citas) del roadmap: hasOverlap()/hasClientDayConflict() en
 * AppointmentRepository son un check-then-create de aplicacion, sin ninguna
 * garantia a nivel de base de datos -- dos POST/PUT concurrentes para el
 * mismo barbero+fecha+hora_inicio pueden pasar ambos la validacion antes de
 * que cualquiera termine de escribir (TOCTOU real, confirmado por auditoria).
 *
 * Este indice unico parcial cierra el caso mas comun y mas grave (dos
 * clientes reservando literalmente el mismo slot anunciado por
 * getAvailableSlots(), que siempre cae en la misma rejilla de 30 min) a
 * nivel de Mongo: un segundo insert/update con la misma combinacion
 * (barber_id, fecha, hora_inicio) truena con un duplicate key error real,
 * que AppointmentService::createAppointment()/updateAppointment() traduce a
 * AppointmentConflictException en vez de dejar pasar la doble reserva.
 *
 * No cubre el caso mas raro de dos servicios de duracion distinta que se
 * solapan sin compartir el mismo hora_inicio exacto (ver auditoria de
 * Fase 3) -- eso requeriria serializar escrituras por barbero, fuera de
 * alcance de este cambio aditivo.
 *
 * partialFilterExpression filtra por bloquea_horario:true (equivalente a
 * "estado no es cancelada/no_asistio", mismo criterio que
 * hasOverlap()/hasClientDayConflict()) en vez de excluir esos estados
 * directamente: MongoDB no soporta $ne/$nin/$in dentro de un partial index
 * filter, solo expresiones de igualdad/$exists/comparacion -- ver el
 * docblock de Appointment::booted() para el campo derivado.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        // Backfill: los documentos que ya existian antes de esta migracion
        // nunca pasaron por Appointment::booted()'s saving hook, asi que
        // bloquea_horario no existe en ellos todavia. Sin este paso, el
        // filtro parcial (igualdad exacta bloquea_horario:true) los deja
        // fuera del indice -- protegidos solo para citas creadas/editadas
        // despues de este deploy, no para las que ya estaban activas.
        // withTrashed() a proposito: un registro soft-deleted debe quedar en
        // false igual que uno cancelada/no_asistio (mismo criterio que el
        // hook de booted()).
        Appointment::withTrashed()
            ->whereNotIn('estado', ['cancelada', 'no_asistio'])
            ->whereNull('deleted_at')
            ->update(['bloquea_horario' => true]);
        Appointment::withTrashed()
            ->where(function ($q) {
                $q->whereIn('estado', ['cancelada', 'no_asistio'])->orWhereNotNull('deleted_at');
            })
            ->update(['bloquea_horario' => false]);

        try {
            Schema::connection('mongodb')->table('appointments', function (Blueprint $c) {
                $c->unique(['barber_id', 'fecha', 'hora_inicio'], 'appointments_active_slot_unique', null, [
                    'partialFilterExpression' => [
                        'bloquea_horario' => true,
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
            Schema::connection('mongodb')->table('appointments', function (Blueprint $c) {
                $c->dropIndex('appointments_active_slot_unique');
            });
        } catch (CommandException $e) {
            if ($e->getCode() !== 27) {
                throw $e;
            }
        }
    }
};
