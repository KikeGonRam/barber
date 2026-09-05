<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function __construct(Payment $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15)
    {
        return $this->model->newQuery()
            ->with(['appointment.client.user', 'appointment.barber.user', 'appointment.service', 'creator'])
            ->when(isset($filters['metodo_pago']) && $filters['metodo_pago'] !== '', function ($query) use ($filters) {
                $query->where('metodo_pago', $filters['metodo_pago']);
            })
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $q = $filters['q'];
                $query->whereHas('appointment.client.user', fn ($u) => $u->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('appointment.service', fn ($s) => $s->where('nombre', 'like', "%{$q}%"));
            })
            ->when(! empty($filters['barbero_id']), fn ($q) => $q->whereHas('appointment', fn ($a) => $a->where('barber_id', $filters['barbero_id'])))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereDate('created_at', '>=', $filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereDate('created_at', '<=', $filters['fecha_hasta']))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function existsForAppointment(string $appointmentId): bool
    {
        return $this->model->newQuery()
            ->where('appointment_id', $appointmentId)
            ->where('estado', '!=', Payment::ESTADO_RECHAZADO)
            ->exists();
    }
}
