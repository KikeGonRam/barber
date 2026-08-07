<?php

namespace App\Services\Business;

use Illuminate\Database\Eloquent\Model;

/**
 * Wrapper delgado sobre spatie/laravel-activitylog para registrar eventos
 * de negocio (auditoria) de forma uniforme desde cualquier parte del
 * dominio, sin acoplar a los llamadores con la API de `activity()`.
 */
class BusinessEventService
{
    /**
     * Registra un evento de auditoria en el log indicado. Efecto
     * secundario: escribe una fila en la coleccion de activity log
     * (persistencia inmediata, no hay batching).
     */
    public function record(string $logName, string $description, array $properties = [], ?Model $subject = null): void
    {
        $activity = activity($logName)->withProperties($properties);

        if ($subject) {
            // Asocia el evento a un modelo especifico (ej. la cita, el pedido)
            // para poder filtrar el historial por ese "subject" despues.
            $activity->performedOn($subject);
        }

        $activity->log($description);
    }
}
