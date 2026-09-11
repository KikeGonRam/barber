<?php

namespace App\Models;

use App\Traits\HasPublicCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Una cita agendada por un cliente con un barbero para un servicio.
 *
 * Es el modelo central del negocio: controla la maquina de estados de
 * `estado` (pendiente/confirmada/en curso/completada/cancelada, etc.),
 * cobros (metodo_pago, precio_cobrado), recordatorios (reminder_24h/2h) y
 * bitacora via SoftDeletes + LogsActivity (Spatie). Usa HasPublicCode para
 * generar el 'code' publico mostrado al cliente.
 */
class Appointment extends Model
{
    use HasFactory, HasPublicCode, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'barber_id',
        'service_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'notas',
        'metodo_pago',
        'precio_cobrado',
        'productos',
        'motivo_reagendamiento',
        'cancelada_en',
        'code',
        'confirmation_sent_at',
        'reminder_24h_sent_at',
        'reminder_2h_sent_at',
        'cancellation_notified_at',
        'servicio_iniciado_en',
        'ultimo_aviso_barbero_en',
        'bloquea_horario',
        // Token opaco del enlace de gestión que viaja en los recordatorios
        // (ver AppointmentManageLinkService). Nunca se expone en respuestas
        // de la API: solo se compara contra el que llega en la petición.
        'manage_token',
    ];

    // $hidden, no solo disciplina: cualquier toArray()/toJson() de una cita
    // (incluido un dd() en logs) omitiría el token que da acceso a
    // gestionarla sin sesión.
    protected $hidden = ['manage_token'];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'productos' => 'array',
            'precio_cobrado' => 'float',
            'cancelada_en' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime',
            'reminder_2h_sent_at' => 'datetime',
            'cancellation_notified_at' => 'datetime',
            'servicio_iniciado_en' => 'datetime',
            'ultimo_aviso_barbero_en' => 'datetime',
            'bloquea_horario' => 'boolean',
        ];
    }

    /**
     * bloquea_horario se deriva de `estado` (y de si la cita esta soft-deleted)
     * en cada guardado, nunca se asigna a mano en ningun controlador/servicio
     * -- existe unicamente para que el indice unico parcial de la migracion
     * add_appointment_slot_unique_index pueda expresar "estado activo" como
     * una igualdad simple (bloquea_horario: true). MongoDB no permite
     * $ne/$nin/$in dentro de un partialFilterExpression, solo expresiones
     * de igualdad/$exists/comparacion y $and de nivel superior -- de ahi
     * este campo derivado en vez de filtrar directamente por `estado`.
     *
     * Soft-delete cuenta como "libera el slot", pero SoftDeletes::
     * runSoftDelete() pone deleted_at con un UPDATE de query builder directo
     * (bypasa Eloquent::save()), asi que el hook de 'saving' de abajo NUNCA
     * corre en un soft-delete -- confirmado leyendo el trait de Laravel.
     * Por eso el 'deleting' de aca abajo, que SI dispara siempre (soft y
     * hard delete), hace el mismo update directo para liberar el slot antes
     * de que el propio SoftDeletes lo marque como borrado. restore() SI pasa
     * por save() (confirmado en el trait), asi que el hook de 'saving' ya lo
     * cubre correctamente sin necesitar un listener de 'restored' aparte.
     */
    protected static function booted(): void
    {
        static::saving(function (self $appointment) {
            $appointment->bloquea_horario = $appointment->deleted_at === null
                && ! in_array($appointment->estado, ['cancelada', 'no_asistio'], true);
        });

        static::deleting(function (self $appointment) {
            $appointment->newQueryWithoutScopes()
                ->where($appointment->getKeyName(), $appointment->getKey())
                ->update(['bloquea_horario' => false]);
        });
    }

    // Cliente que reservo la cita.
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Barbero asignado a la cita.
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    // Servicio (corte, barba, etc.) que se presta en la cita.
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Pagos asociados a esta cita (puede haber mas de uno, ej. abono + saldo).
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Movimientos de inventario (consumo de productos) generados por esta cita.
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // Configuracion de Spatie Activitylog: registra solo cambios (dirty) en campos fillable,
    // bajo el log_name 'appointments' para poder filtrarlos en la bitacora.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('appointments')
            ->logFillable()
            ->logOnlyDirty();
    }
}
